<?php
declare(strict_types=1);

// Real service-layer path on ONE fictional ticket, from intake to pickup.
// Intentionally CLI-only; never invoke against an existing shop database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/issuance.php';
require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/quotes/repository.php';
require_once __DIR__ . '/../app/public/tracking.php';
require_once __DIR__ . '/../app/payments/repository.php';

function journeyCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('Journey: ' . $message);
    }
}
function journeyDeny(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException('Expected refusal: ' . $message);
}
function journeyInsert(PDO $pdo, string $sql, array $parameters): int
{
    $pdo->prepare($sql)->execute($parameters);
    return (int) $pdo->lastInsertId();
}
function journeyEvidence(): array
{
    $evidence = [];
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5FoAAAAASUVORK5CYII=');
    foreach (['photo', 'signed_form'] as $key) {
        $name = bin2hex(random_bytes(24)) . '.png';
        $path = intakeEvidencePath($name);
        if (file_put_contents($path, $pixel) === false) {
            throw new RuntimeException('Could not prepare fictional intake evidence.');
        }
        $evidence[$key] = ['name' => $name] + intakeInspectImage($path);
    }
    return $evidence;
}

$pdo = getPDO();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
journeyCheck($database === 'repairtrack_test_journey', 'Refusing any non-disposable database.');
journeyCheck((int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1,
    'Foreign keys must be enforced.');
$evidence = [];
try {
    $run = strtoupper(bin2hex(random_bytes(5)));
    $branch = journeyInsert($pdo, 'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
        [$run, 'Single ticket MVP fixture']);
    $location = journeyInsert($pdo,
        "INSERT INTO storage_locations (branch_id,location_code,location_name,location_type)
         VALUES (?,'JOURNEY','Fixture workbench','WORKBENCH')", [$branch]);
    $createActor = static function (string $role) use ($pdo, $branch, $run): array {
        $id = journeyInsert($pdo,
            'INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,?)',
            ['Journey ' . $role, strtolower($run) . '-' . strtolower($role) . '@example.test',
                'non-login-test-password', $role]);
        $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id,is_default) VALUES (?,?,1)')
            ->execute([$id, $branch]);
        return ['user_id' => $id, 'role' => $role];
    };
    $manager = $createActor('MANAGER');
    $technician = $createActor('TECHNICIAN');
    $cashier = $createActor('CASHIER');
    $viewer = $createActor('VIEWER');

    $customer = journeyInsert($pdo,
        'INSERT INTO customers (full_name,normalized_name) VALUES (?,?)',
        ['Journey Test Customer', 'journey test customer']);
    $pdo->prepare("INSERT INTO customer_contacts
        (customer_id,contact_type,contact_value,normalized_value,is_primary)
        VALUES (?,'PHONE','09171234567','09171234567',1)")->execute([$customer]);

    // 1. Actual intake draft and signed, photo-backed issuance functions.
    $values = array_replace(intakeDefaults(intakeBranches($pdo, $manager['user_id'])), [
        'customer_id' => (string) $customer,
        'device_type' => 'Laptop',
        'make' => 'Demo',
        'model' => 'Journey Laptop',
        'serial_number' => $run . '-SN',
        'reported_symptoms' => 'Won\'t charge',
        'condition_notes' => 'Case inspected at counter',
    ]);
    $ticketId = intakeSaveDraft($pdo, $values, $manager['user_id']);
    $draft = intakeIssueDraft($pdo, $ticketId, $manager['user_id']);
    $issue = intakeIssueValues([
        'location_id' => (string) $location, 'terms_version' => 'JOURNEY-TEST-v1',
        'checklist_confirmed' => '1', 'signature_confirmed' => '1',
        'duplicate_confirmed' => '1', 'charger' => '1',
    ]);
    $evidence = journeyEvidence();
    journeyDeny(fn() => intakeIssueTicket($pdo, $ticketId, $manager['user_id'],
        intakeDraftVersion($draft), array_replace($issue, ['signature_confirmed' => '']), $evidence),
        'Unsigned ticket cannot issue.');
    $ticketCode = intakeIssueTicket($pdo, $ticketId, $manager['user_id'],
        intakeDraftVersion($draft), $issue, $evidence);
    journeyCheck((bool) preg_match('/^RT-[A-F0-9]{12}$/D', $ticketCode), 'Issued receipt code missing.');
    $stmt = $pdo->prepare('SELECT ticket_device_id FROM ticket_devices WHERE ticket_id=?');
    $stmt->execute([$ticketId]);
    $deviceId = (int) $stmt->fetchColumn();
    journeyCheck($deviceId > 0, 'Issued ticket has no device.');

    // 2. Customer portal must require a private code plus PIN; receipt is not a secret.
    $stmt = $pdo->prepare('SELECT tracking_token_hash FROM repair_tickets WHERE ticket_id=?');
    $stmt->execute([$ticketId]);
    $portalCode = replaceTrackingCode($pdo, $manager['user_id'], $ticketId, $stmt->fetchColumn(), true);
    journeyCheck(publicRepair($pdo, $ticketCode) === null, 'Receipt wrongly grants portal access.');
    journeyCheck(publicRepair($pdo, $portalCode, '0000') === null, 'Wrong PIN accepted.');
    $initial = publicRepair($pdo, $portalCode, '4567');
    journeyCheck(($initial['devices'][0]['repair_status'] ?? null) === 'RECEIVED',
        'Public intake status missing.');

    // 3. Authorized assignment and technician diagnosis with private/public separation.
    $current = consoleDevice($pdo, $manager, $deviceId);
    consoleSave($pdo, $manager['user_id'], $deviceId, consoleVersion($current),
        ['action' => 'assign', 'technician' => (string) $technician['user_id']]);
    journeyDeny(fn() => consoleDevice($pdo, $viewer, $deviceId),
        'Viewer cannot gain technician-only detail.');
    $current = consoleDevice($pdo, $technician, $deviceId);
    consoleSave($pdo, $technician['user_id'], $deviceId, consoleVersion($current),
        ['action' => 'progress', 'status' => 'DIAGNOSING',
         'note' => 'PRIVATE_JOURNEY_DIAGNOSTIC', 'public_message' => 'Diagnosis started.']);
    $diagnosing = consoleDevice($pdo, $technician, $deviceId);
    journeyDeny(fn() => consoleSave($pdo, $technician['user_id'], $deviceId,
        consoleVersion($diagnosing), ['action' => 'progress', 'status' => 'IN_PROGRESS']),
        'Repair started without an approved quote.');

    // 4. Estimate shared and customer decision recorded by staff; no self-approval claim.
    $quoteInput = [
        'labor_description' => 'Battery connector labor',
        'labor_amount' => '375.00',
        'parts_description' => '',
        'parts_amount' => '0',
        'other_amount' => '0',
    ];
    quoteSave($pdo, $manager['user_id'], $deviceId, quoteVersion(null), 'draft', $quoteInput);
    $q = quoteLatest($pdo, $deviceId);
    journeyCheck($q['total_amount'] === '375.00', 'Incorrect quote amount.');
    quoteSave($pdo, $manager['user_id'], $deviceId, quoteVersion($q), 'share', ['confirm' => '1']);
    $q = quoteLatest($pdo, $deviceId);
    quoteSave($pdo, $manager['user_id'], $deviceId, quoteVersion($q), 'decision', [
        'decision' => 'APPROVED', 'customer_name' => 'Journey Test Customer',
        'reference' => 'Test-only confirmed counter conversation', 'confirm' => '1',
    ]);
    journeyCheck(quoteLatest($pdo, $deviceId)['quote_status'] === 'APPROVED',
        'Quote decision missing.');

    // 5. Repair progress and QA cannot be skipped, but do reach READY_FOR_PICKUP.
    foreach (['IN_PROGRESS', 'TESTING', 'READY_FOR_PICKUP'] as $status) {
        $current = consoleDevice($pdo, $technician, $deviceId);
        consoleSave($pdo, $technician['user_id'], $deviceId, consoleVersion($current),
            ['action' => 'progress', 'status' => $status,
             'public_message' => 'Repair update: ' . $status]);
    }
    $update = publicRepair($pdo, $portalCode, '4567');
    journeyCheck($update['devices'][0]['repair_status'] === 'READY_FOR_PICKUP',
        'Customer portal did not show pickup readiness.');
    journeyCheck(!str_contains(json_encode($update, JSON_THROW_ON_ERROR),
        'PRIVATE_JOURNEY_DIAGNOSTIC'), 'Private technician notes leaked.');

    // 6. Manager issues contract bill; cashier cannot hand over before full cash settlement.
    $billId = cashIssueFinalBill($pdo, $manager['user_id'], $ticketId,
        cashTicketVersion(cashPageTicket($pdo, $manager, $ticketId)));
    $bill = cashBillDetails($pdo, $cashier, $billId);
    journeyCheck($bill['balance_cents'] === 37500, 'Bill does not match approved labor.');
    journeyDeny(fn() => cashCompleteHandover($pdo, $cashier['user_id'], $billId,
        $bill['version'], 'Journey Test Customer', 'CUSTOMER', 'yes'),
        'Unpaid device was released.');
    cashRecordPayment($pdo, $cashier['user_id'], $billId, $bill['version'], '150.00',
        bin2hex(random_bytes(32)));
    $partPaid = cashBillDetails($pdo, $cashier, $billId);
    journeyCheck($partPaid['balance_cents'] === 22500, 'Partial payment not preserved.');
    journeyDeny(fn() => cashCompleteHandover($pdo, $cashier['user_id'], $billId,
        $partPaid['version'], 'Journey Test Customer', 'CUSTOMER', 'yes'),
        'Partially paid device was released.');
    cashRecordPayment($pdo, $cashier['user_id'], $billId, $partPaid['version'], '225.00',
        bin2hex(random_bytes(32)));
    $settled = cashBillDetails($pdo, $cashier, $billId);
    journeyCheck($settled['balance_cents'] === 0 && count($settled['payments']) === 2,
        'Complete settlement failed.');
    cashCompleteHandover($pdo, $cashier['user_id'], $billId, $settled['version'],
        'Journey Test Customer', 'CUSTOMER', 'yes');

    // 7. The same portal code now shows delivered custody, without exposing secrets.
    $final = publicRepair($pdo, $portalCode, '4567');
    journeyCheck($final['devices'][0]['custody_state'] === 'WITH_CUSTOMER',
        'Portal did not show customer handover.');
    $stmt = $pdo->prepare('SELECT case_state FROM repair_tickets WHERE ticket_id=?');
    $stmt->execute([$ticketId]);
    journeyCheck($stmt->fetchColumn() === 'CLOSED', 'Ticket remains open after handover.');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ticket_intake_gate_events WHERE ticket_id=?');
    $stmt->execute([$ticketId]);
    journeyCheck((int) $stmt->fetchColumn() >= 9, 'Intake evidence gates were lost.');
    echo "PASS: ONE ticket: draft -> evidence issue -> PIN tracking -> diagnosis -> approved quote -> repair/QA -> unpaid denial -> partial/full counter cash -> handover/closed portal.\n";
} finally {
    // No persistent test artifacts; database itself is discarded with CI runner.
    if ($evidence !== []) {
        intakeDiscardEvidence($evidence);
    }
}
