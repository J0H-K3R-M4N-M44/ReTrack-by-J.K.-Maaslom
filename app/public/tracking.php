<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../web.php';
require_once __DIR__ . '/../dashboard_data.php';

// Receipt numbers identify a repair; only a private random code grants access.
function publicRepair(PDO $pdo, string $token, string $pin = '', string $historyPage = '1'): ?array
{
    $shortCode = preg_match('/^[a-f0-9]{5}$/iD', $token) === 1;
    if (!$shortCode && !preg_match('/^[a-f0-9]{64}$/iD', $token)) {
        return null;
    }
    $lookup = $shortCode ? 't.portal_code = ?' : 't.tracking_token_hash = ?';
    $query = $pdo->prepare("SELECT t.ticket_id, t.ticket_code, t.portal_pin_hash, b.branch_name, b.phone
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.accountable_branch_id
        WHERE $lookup AND t.record_stage = 'ISSUED' LIMIT 1");
    $query->execute([$shortCode ? strtoupper($token) : hash('sha256', strtolower($token))]);
    $repair = $query->fetch();
    if ($shortCode) {
        // Equalize valid-format unknown codes; do not reveal which code exists.
        $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $matches = password_verify($pin, $repair['portal_pin_hash'] ?? $dummyHash);
        if (!$matches || !preg_match('/^[0-9]{4}$/D', $pin)) {
            return null;
        }
    }
    if (!$repair) {
        return null;
    }
    unset($repair['portal_pin_hash']);
    // List safe columns explicitly. Never return contacts, serials, evidence or private notes.
    $devices = $pdo->prepare("SELECT d.ticket_device_id, d.item_code,
        d.device_type_snapshot, d.make_snapshot, d.model_snapshot, d.repair_status,
        d.custody_state, d.public_summary, b.branch_name AS location_branch
        FROM ticket_devices d
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        LEFT JOIN branches b ON b.branch_id = l.branch_id
        WHERE d.ticket_id = ? ORDER BY d.child_sequence");
    $devices->execute([$repair['ticket_id']]);
    $repair['devices'] = $devices->fetchAll();

    // Resolve the newest revision only after private ticket access is validated.
    // Drafts, superseded prices and staff conversation evidence stay private.
    $quoteQuery = $pdo->prepare(
        'SELECT version_no, quote_status, currency, total_amount, sent_at, expires_at
         FROM repair_quotes WHERE ticket_device_id = ?
         ORDER BY version_no DESC LIMIT 1',
    );
    foreach ($repair['devices'] as &$device) {
        $device['estimate'] = null;
        $quoteQuery->execute([(int) $device['ticket_device_id']]);
        $quote = $quoteQuery->fetch();
        if (
            $quote &&
            $quote['sent_at'] !== null &&
            in_array($quote['quote_status'], ['AWAITING_CUSTOMER', 'APPROVED', 'DECLINED'], true)
        ) {
            $device['estimate'] = [
                'revision' => (int) $quote['version_no'],
                'status' => $quote['quote_status'],
                'currency' => $quote['currency'],
                'total' => $quote['total_amount'],
                'expired' => $quote['quote_status'] === 'AWAITING_CUSTOMER' &&
                    $quote['expires_at'] !== null &&
                    strtotime($quote['expires_at'] . ' UTC') <= time(),
            ];
        }
    }
    unset($device);

    $repair['history_page'] = dashboardPage(
        $pdo,
        "SELECT e.status_event_id, e.to_status, e.public_summary, e.created_at, d.item_code
        FROM ticket_status_events e JOIN ticket_devices d ON d.ticket_device_id = e.ticket_device_id
        WHERE d.ticket_id = ? AND e.is_customer_visible = 1",
        [$repair['ticket_id']],
        'created_at DESC, status_event_id DESC',
        $historyPage,
    );
    $repair['events'] = $repair['history_page']['rows'];
    return $repair;
}

function publicCustody(array $device): string
{
    $branch = $device['location_branch'] ?: 'the service branch';
    return match ($device['custody_state']) {
        'AT_LOCATION' => 'At ' . $branch,
        'IN_TRANSIT' => 'In transit · contact the branch for the next handoff',
        'WITH_CUSTOMER' => 'Returned to the customer',
        default => 'Location awaiting staff confirmation',
    };
}

// Labels explain a staff-recorded estimate; viewing it does not grant approval rights.
function publicEstimateLabel(array $estimate): string
{
    if ($estimate['status'] === 'AWAITING_CUSTOMER' && $estimate['expired']) {
        return 'Response period ended';
    }
    return match ($estimate['status']) {
        'AWAITING_CUSTOMER' => 'Awaiting your response',
        'APPROVED' => 'Approved (recorded by staff)',
        'DECLINED' => 'Declined (recorded by staff)',
        default => 'Contact the branch',
    };
}

function publicNextStep(array $device): string
{
    if ($device['custody_state'] === 'WITH_CUSTOMER') {
        return 'This device has been returned. Contact the branch if you need further help.';
    }
    if ($device['repair_status'] === 'CANCELLED') {
        return 'Contact the branch to discuss collection arrangements and any remaining charges.';
    }
    if ($device['custody_state'] === 'IN_TRANSIT') {
        return 'The device is in transit. Contact the branch to confirm the next handoff.';
    }
    $estimate = $device['estimate'] ?? null;
    if ($estimate && $estimate['status'] === 'AWAITING_CUSTOMER') {
        return $estimate['expired']
            ? 'The estimate response period ended. Contact the branch to request an updated estimate.'
            : 'Review the estimate and contact the branch with your decision. Staff must record your response.';
    }
    if ($estimate && $estimate['status'] === 'DECLINED') {
        return 'The estimate was declined. Contact the branch to agree on next steps; this does not cancel the repair.';
    }
    return match ($device['repair_status']) {
        'RECEIVED' => 'The branch has received the device. Staff will begin the assessment.',
        'DIAGNOSING' => $estimate && $estimate['status'] === 'APPROVED'
            ? 'The estimate was recorded as approved. Staff can arrange the repair.'
            : 'Diagnosis is underway. Ask the branch if you need a progress update.',
        'AWAITING_CUSTOMER_APPROVAL' => 'Ask the branch to confirm the current estimate and how to respond.',
        'AWAITING_PARTS' => 'Parts are being arranged. Ask the branch for updated timing.',
        'IN_PROGRESS' => 'Repair work is in progress. Check back for the next staff update.',
        'TESTING' => 'The device is being tested. The branch will confirm when it is ready.',
        'READY_FOR_PICKUP' => 'Contact the branch to confirm pickup and any balance before collection. Staff must complete checkout.',
        'COMPLETED' => 'Ask the branch to confirm the final handover details.',
        default => 'Contact the branch for the next confirmed step.',
    };
}

// Recheck account, branch and version inside the transaction before replacing access.
function replaceTrackingCode(
    PDO $pdo,
    int $userId,
    int $ticketId,
    string $expectedHash,
    bool $shortCode = false,
): string {
    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare('SELECT role, is_active FROM users WHERE user_id = ? FOR UPDATE');
        $account->execute([$userId]);
        $user = $account->fetch();
        if (
            !$user ||
            !$user['is_active'] ||
            !in_array($user['role'], ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)
        ) {
            throw new DomainException('Your account cannot share this repair.');
        }
        $query = $pdo->prepare("SELECT t.tracking_token_hash, t.customer_id, t.portal_code FROM repair_tickets t
            JOIN branches b ON b.branch_id = t.accountable_branch_id AND b.is_active = 1
            JOIN user_branch_assignments a ON a.branch_id = b.branch_id AND a.user_id = ?
            WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED' FOR UPDATE");
        $query->execute([$userId, $ticketId]);
        $ticket = $query->fetch();
        if (!$ticket || !hash_equals((string) $ticket['tracking_token_hash'], $expectedHash)) {
            throw new DomainException(
                'This ticket or tracking access changed. Reopen the receipt.',
            );
        }
        if ($shortCode) {
            $phoneQuery = $pdo->prepare("SELECT normalized_value FROM customer_contacts
                WHERE customer_id = ? AND contact_type = 'PHONE'
                ORDER BY is_primary DESC, contact_id DESC LIMIT 1");
            $phoneQuery->execute([$ticket['customer_id']]);
            $digits = preg_replace('/[^0-9]/', '', (string) $phoneQuery->fetchColumn());
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                throw new DomainException(
                    'Record a valid customer phone number before creating a portal code.',
                );
            }
            // Snapshot the suffix; later contact changes require new portal access.
            $pinHash = password_hash(substr($digits, -4), PASSWORD_BCRYPT, ['cost' => 10]);
            $saved = false;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $token = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                if ($token === $ticket['portal_code']) {
                    continue;
                }
                try {
                    $pdo->prepare(
                        'UPDATE repair_tickets SET portal_code = ?, portal_pin_hash = ?,
                        tracking_token_hash = ?, tracking_token_ciphertext = NULL WHERE ticket_id = ?',
                    )->execute([$token, $pinHash, hash('sha256', random_bytes(32)), $ticketId]);
                    $saved = true;
                    break;
                } catch (PDOException $error) {
                    // The unique database index handles collisions, including concurrent requests.
                    if ((int) ($error->errorInfo[1] ?? 0) !== 1062) {
                        throw $error;
                    }
                }
            }
            if (!$saved) {
                throw new DomainException('Could not allocate a tracking code. Please try again.');
            }
        } else {
            // Preserve legacy private-code integrations; a replacement revokes short access too.
            $token = bin2hex(random_bytes(32));
            $pdo->prepare(
                'UPDATE repair_tickets SET tracking_token_hash = ?, tracking_token_ciphertext = NULL,
                portal_code = NULL, portal_pin_hash = NULL WHERE ticket_id = ?',
            )->execute([hash('sha256', $token), $ticketId]);
        }
        // Record the action, never the usable code.
        $pdo->prepare(
            "INSERT INTO audit_events (actor_type, actor_user_id, entity_type, entity_id, action, created_at)
            VALUES ('USER', ?, 'repair_ticket', ?, 'TRACKING_ACCESS_REPLACED', UTC_TIMESTAMP())",
        )->execute([$userId, $ticketId]);
        $pdo->commit();
        return $token;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
