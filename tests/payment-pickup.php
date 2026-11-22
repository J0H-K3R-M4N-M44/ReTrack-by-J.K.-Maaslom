<?php
declare(strict_types=1);

// Run after loading the RepairTrack schema into a disposable repairtrack_test_* database.
// Each run creates a separate branch, two cashier accounts and one repair ticket.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/payments/repository.php';

$pdo = getPDO();
if (!str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')) {
    throw new RuntimeException('Select a disposable repairtrack_test_* database first.');
}

function insertFixture(PDO $pdo, string $sql, array $values): int
{
    $pdo->prepare($sql)->execute($values);
    return (int) $pdo->lastInsertId();
}

function expectResult(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function expectRefusal(callable $action, string $reason): void
{
    try {
        $action();
    } catch (DomainException $error) {
        expectResult(str_contains($error->getMessage(), $reason), $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected refusal: ' . $reason);
}

$run = bin2hex(random_bytes(5));
$branch = insertFixture($pdo, 'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run, 'Pickup test branch']);
$location = insertFixture($pdo,
    "INSERT INTO storage_locations (branch_id,location_code,location_name) VALUES (?,'BENCH','Bench')", [$branch]);

$browserFixture = in_array('--browser-fixture', $argv, true);
$password = $browserFixture ? bin2hex(random_bytes(20)) : '';
$hash = $browserFixture ? password_hash($password, PASSWORD_DEFAULT) : 'unused';
$staff = [];
$roles = ['MANAGER', 'CASHIER', 'CASHIER'];
if (in_array('--concurrency', $argv, true) || in_array('--stock-pilot', $argv, true) ||
    in_array('--stock-operations', $argv, true)) {
    $roles[] = 'MANAGER';
    $roles[] = 'TECHNICIAN';
}
if ($browserFixture) {
    $roles = array_merge($roles, ['ADMIN', 'INTAKE_AGENT', 'TECHNICIAN', 'COURIER', 'VIEWER']);
}
$emails = [];
foreach ($roles as $index => $role) {
    $emails[strtolower($role)] ??= "$run-$index@example.test";
    $id = insertFixture($pdo,
        'INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,?)',
        ['Test ' . $role, "$run-$index@example.test", $hash, $role]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')->execute([$id,$branch]);
    $staff[] = ['user_id' => $id, 'role' => $role];
}
[$manager,$first,$second] = $staff;
$customer = insertFixture($pdo, 'INSERT INTO customers (full_name,normalized_name) VALUES (?,?)',
    ['Pickup Test','pickup test']);
$ticket = insertFixture($pdo,
    "INSERT INTO repair_tickets (ticket_code,record_stage,case_state,customer_id,intake_branch_id,
        accountable_branch_id,accepted_by_user_id,arrival_method,intake_date,issued_at,
        terms_accepted,terms_signature_path,terms_signed_at)
     VALUES (?,'ISSUED','OPEN',?,?,?,?,'WALK_IN',UTC_DATE(),UTC_TIMESTAMP(),1,'fixture',UTC_TIMESTAMP())",
    [$run,$customer,$branch,$branch,$manager['user_id']]);
$device = insertFixture($pdo,
    "INSERT INTO ticket_devices (ticket_id,child_sequence,item_code,device_type_snapshot,reported_symptoms,
        condition_notes,serial_number_snapshot,accessory_checklist_completed,repair_status,custody_state,physical_location_id)
     VALUES (?,1,?,'Laptop','Charging fault','Checked',?,1,'READY_FOR_PICKUP','AT_LOCATION',?)",
    [$ticket,$run.'-01',$run,$location]);
$quote = insertFixture($pdo,
    "INSERT INTO repair_quotes (ticket_device_id,version_no,quote_status,labor_amount,total_amount,prepared_by_user_id)
     VALUES (?,1,'APPROVED',100,100,?)", [$device,$manager['user_id']]);
$pdo->prepare("INSERT INTO repair_quote_items (quote_id,item_type,description,quantity,unit_price,line_total)
    VALUES (?,'LABOR','Charging repair',1,100,100)")->execute([$quote]);

if (in_array('--stock-pilot', $argv, true)) {
    require __DIR__ . '/stock-pilot.php';
    exit();
}
if (in_array('--stock-operations', $argv, true)) {
    require __DIR__ . '/stock-quarantine.php';
    exit();
}
if (in_array('--concurrency', $argv, true)) {
    require __DIR__ . '/checkout-concurrency.php';
    testCheckoutRaces($pdo, $ticket, $device, $staff);
    exit();
}

// Stream these temporary credentials to the browser runner, outside the web root.
if ($browserFixture) {
    $part = insertFixture($pdo, 'INSERT INTO parts (sku,part_name) VALUES (?,?)', [$run,'Demo SSD']);
    stockRun($pdo, 'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,1)', [$branch,$part]);
    $stockPart = insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
        [$run.'-BAT','Quarantine test battery']);
    stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,3)',
        [$branch,$stockPart]);
    $techId = stockRun($pdo, 'SELECT user_id FROM users WHERE email=?', [$emails['technician']])->fetchColumn();
    stockRun($pdo, "UPDATE ticket_devices SET repair_status='DIAGNOSING',assigned_technician_id=?
        WHERE ticket_device_id=?", [$techId,$device]);
    echo json_encode($emails + [
        'password' => $password,
        'ticket' => $run,
        'device' => $device,
        'part' => $part,
        'stock_part' => $stockPart,
        'branch' => $branch,
    ], JSON_THROW_ON_ERROR);
    exit();
}

if (in_array('--cancel', $argv, true) || in_array('--cancel-free', $argv, true)) {
    $fee = in_array('--cancel-free', $argv, true) ? '0.00' : '350.00';
    $pdo->prepare("UPDATE repair_quotes SET total_amount=2750, parts_amount=2650 WHERE quote_id=?")->execute([$quote]);
    $pdo->prepare("INSERT INTO repair_quote_items (quote_id,item_type,description,quantity,unit_price,line_total)
        VALUES (?,'PART','Unused SSD',1,2650,2650)")->execute([$quote]);
    $version = cashTicketVersion(cashPageTicket($pdo, $manager, $ticket));
    $decision = ['amount' => $fee, 'reason' => 'Customer agreed at the counter.', 'confirm' => 'yes'];
    $issue = fn(array $values) => cashIssueFinalBill($pdo, $manager['user_id'], $ticket, $version, $values);
    expectRefusal(fn() => $issue(['confirm' => 'no'] + $decision), 'agreed');
    $bill = $issue($decision);
    expectRefusal(fn() => $issue($decision), 'ticket changed');
    $record = cashBillDetails($pdo, $first, $bill);
    expectResult($record['total_amount'] === $fee, 'Cancellation fee was replaced by the quote.');
    expectResult(quoteLatest($pdo, $device)['total_amount'] === '2750.00', 'Original estimate changed.');
    if ($fee !== '0.00') {
        cashRecordPayment($pdo, $first['user_id'], $bill, $record['version'], $fee, bin2hex(random_bytes(32)));
        $record = cashBillDetails($pdo, $first, $bill);
    }
    cashCompleteHandover($pdo, $first['user_id'], $bill, $record['version'], 'Pickup Test', 'CUSTOMER', 'yes');
    $closed = cashBillDetails($pdo, $first, $bill);
    $returned = cashHandoverPreview($pdo, $first, $ticket)['devices'][0];
    expectResult($closed['closure_outcome'] === 'ALL_CANCELLED' && $returned['repair_status'] === 'CANCELLED'
        && $returned['custody_state'] === 'WITH_CUSTOMER', 'Cancellation handover was lost.');
    echo "PASS: cancellation, fee $fee, stale retry, quote preservation and return.\n";
    exit();
}

// Add a second device for the PHP regression while keeping the browser fixture single-device.
$delayed = insertFixture($pdo,
    "INSERT INTO ticket_devices (ticket_id,child_sequence,item_code,device_type_snapshot,reported_symptoms,
        condition_notes,serial_number_snapshot,accessory_checklist_completed,repair_status,custody_state,physical_location_id)
     VALUES (?,2,?,'Laptop','No display','Checked',?,1,'READY_FOR_PICKUP','AT_LOCATION',?)",
    [$ticket, $run . '-02', $run . '-device-2', $location]);
$secondQuote = insertFixture($pdo,
    "INSERT INTO repair_quotes (ticket_device_id,version_no,quote_status,labor_amount,total_amount,prepared_by_user_id)
     VALUES (?,1,'APPROVED',100,100,?)", [$delayed, $manager['user_id']]);
$pdo->prepare("INSERT INTO repair_quote_items (quote_id,item_type,description,quantity,unit_price,line_total)
    VALUES (?,'LABOR','Display repair',1,100,100)")->execute([$secondQuote]);

$version = cashTicketVersion(cashPageTicket($pdo,$manager,$ticket));
$bill = cashIssueFinalBill($pdo,$manager['user_id'],$ticket,$version);
$pay = static function (array $cashier, string $version, string $amount) use ($pdo,$bill): void {
    cashRecordPayment($pdo,$cashier['user_id'],$bill,$version,$amount,bin2hex(random_bytes(32)));
};
$pickup = static function (string $version) use ($pdo,$bill,$second): void {
    cashCompleteHandover($pdo,$second['user_id'],$bill,$version,'Pickup Test','CUSTOMER','yes');
};

// Both cashiers open the bill before either records money.
$firstView = cashBillDetails($pdo,$first,$bill);
$secondView = cashBillDetails($pdo,$second,$bill);
$pay($first,$firstView['version'],'40.00');
expectRefusal(fn() => $pay($second,$secondView['version'],'100.00'), 'bill changed');
$current = cashBillDetails($pdo,$second,$bill);
expectResult($current['balance_cents'] === 16000, 'Expected a PHP 160 balance.');
expectRefusal(fn() => $pickup($current['version']), 'full bill balance');
expectRefusal(fn() => $pay($second,$current['version'],'160.01'), 'remaining balance');
$pay($second,$current['version'],'160.00');

$paid = cashBillDetails($pdo,$second,$bill);
expectResult($paid['balance_cents'] === 0 && count($paid['payments']) === 2, 'Payment history mismatch.');
// Simulate a final check finding a fault after the bill has been paid.
$pdo->prepare("UPDATE ticket_devices SET repair_status = 'TESTING' WHERE ticket_device_id = ?")->execute([$delayed]);
$preview = cashHandoverPreview($pdo, $second, $ticket);
expectResult(str_contains(implode(' ', cashHandoverBlockers($paid, $preview)), $run . '-02 is testing'),
    'Preview must name the delayed device.');
expectRefusal(fn() => $pickup($paid['version']), $run . '-02 is testing');
expectResult(cashHandoverPreview($pdo, $second, $ticket)['devices'][0]['custody_state'] === 'AT_LOCATION',
    'Refused handover must leave the ready device at the branch.');
$pdo->prepare("UPDATE ticket_devices SET repair_status = 'READY_FOR_PICKUP' WHERE ticket_device_id = ?")->execute([$delayed]);
$pickup($paid['version']);
$closed = cashBillDetails($pdo,$second,$bill);
$returned = cashHandoverPreview($pdo,$second,$ticket)['devices'][0];
expectResult($closed['case_state'] === 'CLOSED' && $closed['closure_outcome'] === 'ALL_COMPLETED', 'Ticket still open.');
expectResult($returned['repair_status'] === 'COMPLETED' && $returned['custody_state'] === 'WITH_CUSTOMER'
    && $returned['physical_location_id'] === null, 'Device custody mismatch.');
echo "PASS: bill issuance, stale review, partial payment, overpayment refusal, pickup and closure.\n";

// Correct a paid bill after pickup and race two refund reviews against the same version.
cashRecordBillChange($pdo, $manager['user_id'], $bill, $closed['version'],
    'correction', '180.00', 'Remove duplicate work charge', true);
$corrected = cashBillDetails($pdo, $first, $bill);
expectResult($corrected['refund_due_cents'] === 2000 && cashAmountCents($corrected['total_amount']) === 20000,
    'The reduction should preserve the original total and create a PHP 20 refund due.');
cashRecordBillChange($pdo, $first['user_id'], $bill, $corrected['version'],
    'refund', '20.00', 'Returned at counter', true);
expectRefusal(fn() => cashRecordBillChange($pdo, $second['user_id'], $bill, $corrected['version'],
    'refund', '20.00', 'Duplicate cashier review', true), 'bill changed');
$refunded = cashBillDetails($pdo, $first, $bill);
expectRefusal(fn() => cashRecordBillChange($pdo, $second['user_id'], $bill, $refunded['version'],
    'refund', '0.01', 'Excess cash', true), 'refund due');
expectResult($refunded['refund_due_cents'] === 0 && $refunded['case_state'] === 'CLOSED'
    && count($refunded['payments']) === 2, 'Refund changed closure or original receipts.');
cashRecordBillChange($pdo, $manager['user_id'], $bill, $refunded['version'],
    'correction', '190.00', 'Customer approved replacement charge', true);
$increased = cashBillDetails($pdo, $second, $bill);
expectResult($increased['balance_cents'] === 1000, 'Expected PHP 10 due after the second correction.');
$pay($second, $increased['version'], '10.00');
expectResult(cashBillDetails($pdo, $second, $bill)['balance_cents'] === 0, 'Corrected bill was not settled.');
echo "PASS: paid bill correction, refund, stale duplicate refund refusal and extra payment.\n";
