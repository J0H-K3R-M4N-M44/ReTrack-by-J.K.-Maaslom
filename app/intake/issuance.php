<?php
declare(strict_types=1);
require_once __DIR__ . '/../web.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/drafts.php';
require_once __DIR__ . '/evidence.php';

function intakeIssueDraft(PDO $pdo, int $ticketId, int $userId, bool $lock = false): array
{
    $draft = intakeLoadDraft($pdo, $ticketId, $userId, $lock);
    $customer = $pdo->prepare(
        'SELECT full_name, normalized_name, updated_at FROM customers WHERE customer_id = ?' .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $customer->execute([$draft['customer_id']]);
    $person = $customer->fetch();
    if (!$person) {
        throw new DomainException('Select a customer before issuing this draft.');
    }
    return $draft + [
        'customer_name' => $person['full_name'],
        'normalized_name' => $person['normalized_name'],
        'customer_updated_at' => $person['updated_at'],
    ];
}

function intakeLocations(PDO $pdo, int $branchId): array
{
    $query = $pdo->prepare("SELECT location_id, location_name FROM storage_locations
        WHERE branch_id = ? AND is_active = 1 AND location_type IN ('BRANCH_BIN', 'WORKBENCH') ORDER BY location_name");
    $query->execute([$branchId]);
    return $query->fetchAll();
}

function intakeIssueValues(array $input): array
{
    $values = [];
    foreach (
        [
            'location_id',
            'alternative_identifiers',
            'terms_version',
            'other_accessories',
            'checklist_confirmed',
            'signature_confirmed',
            'duplicate_confirmed',
        ]
        as $field
    ) {
        $values[$field] = trim(requestText($input, $field));
    }
    foreach (['charger', 'bag', 'cable'] as $accessory) {
        $values[$accessory] = requestText($input, $accessory) === '1' ? '1' : '0';
    }
    return $values;
}

function intakeValidateIssue(PDO $pdo, array $draft, array $values, int $userId): array
{
    $errors = [];
    $draftValues = intakeDefaults(intakeBranches($pdo, $userId));
    foreach ($draftValues as $key => $value) {
        if (array_key_exists($key, $draft)) {
            $draftValues[$key] = (string) ($draft[$key] ?? '');
        }
    }
    $errors = intakeValidate($pdo, $draftValues, intakeBranches($pdo, $userId));
    $locations = array_map(
        'strval',
        array_column(intakeLocations($pdo, (int) $draft['intake_branch_id']), 'location_id'),
    );
    if (!in_array($values['location_id'], $locations, true)) {
        $errors[] = 'Choose an active storage location at the intake branch.';
    }
    if (
        trim((string) $draft['serial_number']) === '' &&
        $values['alternative_identifiers'] === ''
    ) {
        $errors[] =
            'Describe identifying marks or specifications when no serial number is available.';
    }
    if (mb_strlen($values['alternative_identifiers']) > 1000) {
        $errors[] = 'Keep identifying details within 1000 characters.';
    }
    if ($values['terms_version'] === '' || mb_strlen($values['terms_version']) > 40) {
        $errors[] = 'Enter the terms version printed on the signed form (up to 40 characters).';
    }
    if (mb_strlen($values['other_accessories']) > 500) {
        $errors[] = 'Keep other accessory details within 500 characters.';
    }
    if ($values['checklist_confirmed'] !== '1') {
        $errors[] = 'Confirm the accessory checklist, including when nothing was received.';
    }
    if ($values['signature_confirmed'] !== '1') {
        $errors[] = 'Confirm the customer signed the condition assessment and the stated terms.';
    }
    if ($values['duplicate_confirmed'] !== '1') {
        $errors[] = 'Check that this is not another copy of an existing intake.';
    }
    return $errors;
}

function intakeIssueEntries(array $draft, array $values): array
{
    $entries = [];
    foreach ($draft['devices'] as $index => $device) {
        $details = $index === 0 ? $values : intakeIssueValues(
            [
                'terms_version' => $values['terms_version'],
                'signature_confirmed' => $values['signature_confirmed'],
                'duplicate_confirmed' => $values['duplicate_confirmed'],
            ] + ($values['extra_devices'][$index - 1] ?? []),
        );
        $entries[] = [$device + $draft, $details];
    }
    return $entries;
}

function intakeValidateIssueEntries(PDO $pdo, array $draft, array $values, int $userId): array
{
    $errors = [];
    foreach (intakeIssueEntries($draft, $values) as $index => [$device, $details]) {
        foreach (intakeValidateIssue($pdo, $device, $details, $userId) as $error) {
            $errors[] = 'Device ' . ($index + 1) . ': ' . $error;
        }
    }
    return $errors;
}

function intakeDuplicateFingerprint(array $draft, array $values, string $date): string
{
    $config = require __DIR__ . '/../../config/config.php';
    $key = $config['intake_hmac_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/i', $key)) {
        throw new RuntimeException('Configure a stable random intake_hmac_key.');
    }
    $serial = preg_replace(
        '/[^\\p{L}\\p{N}]/u',
        '',
        mb_strtolower((string) $draft['serial_number'], 'UTF-8'),
    );
    $identity =
        $serial !== ''
            ? 'serial:' . $serial
            : 'description:' .
                $draft['make'] .
                ' ' .
                $draft['model'] .
                ' ' .
                $values['alternative_identifiers'];
    $identity = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $identity)), 'UTF-8');
    return hash_hmac(
        'sha256',
        json_encode([$draft['normalized_name'], $identity, $date], JSON_THROW_ON_ERROR),
        hex2bin($key),
    );
}

// Issue everything together. Files already exist privately; the controller removes
// those temporary uploads if this transaction fails or the review is discarded.
function intakeIssueTicket(
    PDO $pdo,
    int $ticketId,
    int $userId,
    string $version,
    array $values,
    array $evidence,
): string {
    intakeVerifyEvidence($evidence);
    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare(
            "SELECT user_id FROM users WHERE user_id = ? AND role IN ('ADMIN', 'MANAGER', 'INTAKE_AGENT') AND is_active = 1 FOR UPDATE",
        );
        $account->execute([$userId]);
        if (!$account->fetchColumn()) {
            throw new DomainException('An active intake staff member must issue the ticket.');
        }
        $draft = intakeIssueDraft($pdo, $ticketId, $userId, true);
        if (!hash_equals($version, intakeDraftVersion($draft))) {
            throw new DomainException(
                'This draft or customer changed. Reopen issuance and review it again.',
            );
        }
        $errors = intakeValidateIssueEntries($pdo, $draft, $values, $userId);
        if ($errors) {
            throw new DomainException(implode(' ', $errors));
        }
        $date = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        $ticketCode = 'RT-' . strtoupper(bin2hex(random_bytes(6)));
        $ticket = $pdo->prepare("UPDATE repair_tickets SET ticket_code = ?, record_stage = 'ISSUED', case_state = 'OPEN', accountable_branch_id = intake_branch_id,
            intake_date = ?, tracking_token_hash = ?, terms_version = ?, terms_accepted = 1, terms_signature_path = ?, terms_signed_at = UTC_TIMESTAMP(), issued_at = UTC_TIMESTAMP() WHERE ticket_id = ?");
        $ticket->execute([
            $ticketCode,
            $date,
            hash('sha256', random_bytes(32)),
            $values['terms_version'],
            $evidence['signed_form']['name'],
            $ticketId,
        ]);
        foreach (intakeIssueEntries($draft, $values) as $index => [$entry, $details]) {
            $deviceId = (int) $entry['ticket_device_id'];
            $photoKey = $index === 0 ? 'photo' : 'photo_' . ($index + 1);
            intakeVerifyEvidence(['photo' => $evidence[$photoKey] ?? [], 'signed_form' => $evidence['signed_form']]);
            // Lock the chosen physical location so it cannot be deactivated mid-issue.
            $location = $pdo->prepare(
                "SELECT location_id FROM storage_locations WHERE location_id = ? AND branch_id = ? AND is_active = 1 AND location_type IN ('BRANCH_BIN','WORKBENCH') FOR UPDATE",
            );
            $location->execute([$details['location_id'], $draft['intake_branch_id']]);
            if (!$location->fetchColumn()) {
                throw new DomainException('The selected storage location is no longer available.');
            }
            $fingerprint = intakeDuplicateFingerprint($entry, $details, $date);
            $duplicate = $pdo->prepare(
                'SELECT ticket_device_id FROM ticket_devices WHERE duplicate_fingerprint = ? LIMIT 1',
            );
            $duplicate->execute([$fingerprint]);
            if ($duplicate->fetchColumn()) {
                throw new DomainException(
                    'This customer and device already have an issued intake today. Open the existing ticket.',
                );
            }
            $device = $pdo->prepare("UPDATE ticket_devices SET item_code = ?, alternative_identifiers = ?, accessory_checklist_completed = 1,
                repair_status = 'RECEIVED', repair_branch_id = ?, physical_location_id = ?, custody_state = 'AT_LOCATION', duplicate_fingerprint = ?, public_summary = 'Received for assessment.' WHERE ticket_device_id = ?");
            $device->execute([
                $ticketCode . '-' . str_pad((string) $entry['child_sequence'], 2, '0', STR_PAD_LEFT),
                $details['alternative_identifiers'] ?: null,
                (int) $draft['intake_branch_id'],
                $details['location_id'],
                $fingerprint,
                $deviceId,
            ]);
            // Reject pre-existing evidence rather than changing an earlier signed record.
            foreach (
                ['ticket_accessory_items', 'ticket_device_photos', 'ticket_device_acknowledgments']
                as $table
            ) {
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM ' . $table . ' WHERE ticket_device_id = ?',
                );
                $check->execute([$deviceId]);
                if ((int) $check->fetchColumn() > 0) {
                    throw new DomainException(
                        'This draft already has intake evidence. It needs manual review before issuance.',
                    );
                }
            }
            $accessory = $pdo->prepare(
                'INSERT INTO ticket_accessory_items (ticket_device_id, accessory_name, received, notes, recorded_by_user_id) VALUES (?, ?, ?, ?, ?)',
            );
            foreach (
                [
                    'charger' => 'Charger / power adapter',
                    'bag' => 'Bag / case',
                    'cable' => 'Other cable',
                ]
                as $key => $name
            ) {
                $accessory->execute([$deviceId, $name, (int) $details[$key], null, $userId]);
            }
            $accessory->execute([
                $deviceId,
                'Other accessories',
                $details['other_accessories'] !== '' ? 1 : 0,
                $details['other_accessories'] ?: 'None received',
                $userId,
            ]);
            $photo = $pdo->prepare(
                "INSERT INTO ticket_device_photos (ticket_device_id, photo_category, stored_path, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at) VALUES (?, 'OVERALL_CONDITION', ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
            );
            $photo->execute([
                $deviceId,
                $evidence[$photoKey]['name'],
                $evidence[$photoKey]['mime'],
                $evidence[$photoKey]['bytes'],
                $evidence[$photoKey]['hash'],
                $userId,
            ]);
            $ack = $pdo->prepare(
                "INSERT INTO ticket_device_acknowledgments (ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at) VALUES (?, ?, ?, ?, 1, 'COUNTER_CAPTURE', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            );
            $ack->execute([
                $deviceId,
                $entry['customer_name'],
                $evidence['signed_form']['name'],
                $details['terms_version'],
            ]);
            $gate = $pdo->prepare(
                'INSERT INTO ticket_intake_gate_events (ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at) VALUES (?, ?, ?, 1, ?, ?, UTC_TIMESTAMP())',
            );
            foreach (
                [
                    'CUSTOMER_MATCH',
                    'DEVICE_IDENTITY',
                    'SYMPTOMS',
                    'CONDITION_NOTES',
                    'ACCESSORY_CHECKLIST',
                    'REQUIRED_PHOTOS',
                    'CONDITION_SIGNOFF',
                    'TERMS_ACCEPTANCE',
                    'DUPLICATE_CHECK',
                ]
                as $code
            ) {
                $gate->execute([
                    $ticketId,
                    $deviceId,
                    $code,
                    'Validated at issuance; signed form and condition image retained.',
                    $userId,
                ]);
            }
            $event = $pdo->prepare(
                "INSERT INTO ticket_status_events (ticket_device_id, from_status, to_status, actor_type, actor_user_id, reason_code, public_summary, is_customer_visible, created_at) VALUES (?, 'DRAFT', 'RECEIVED', 'USER', ?, 'INTAKE_ISSUED', 'Received for assessment.', 1, UTC_TIMESTAMP())",
            );
            $event->execute([$deviceId, $userId]);
        }
        $audit = $pdo->prepare(
            "INSERT INTO audit_events (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at) VALUES ('USER', ?, 'repair_ticket', ?, 'TICKET_ISSUED', ?, UTC_TIMESTAMP())",
        );
        $audit->execute([
            $userId,
            $ticketId,
            json_encode(
                [
                    'ticket_code' => $ticketCode,
                    'location_id' => $values['location_id'],
                    'terms_version' => $values['terms_version'],
                    'signed_form_sha256' => $evidence['signed_form']['hash'],
                ],
                JSON_THROW_ON_ERROR,
            ),
        ]);
        $pdo->commit();
        return $ticketCode;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
