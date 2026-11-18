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
foreach (['MANAGER', 'CASHIER', 'CASHIER'] as $index => $role) {
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

// Stream these temporary credentials to the browser runner, outside the web root.
if ($browserFixture) {
    echo json_encode([
        'manager' => "$run-0@example.test",
        'cashier' => "$run-1@example.test",
        'password' => $password,
        'ticket' => $run,
    ], JSON_THROW_ON_ERROR);
    exit();
}

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
expectResult($current['balance_cents'] === 6000, 'Expected a PHP 60 balance.');
expectRefusal(fn() => $pickup($current['version']), 'full bill balance');
expectRefusal(fn() => $pay($second,$current['version'],'60.01'), 'remaining balance');
$pay($second,$current['version'],'60.00');

$paid = cashBillDetails($pdo,$second,$bill);
expectResult($paid['balance_cents'] === 0 && count($paid['payments']) === 2, 'Payment history mismatch.');
$pickup($paid['version']);
$closed = cashBillDetails($pdo,$second,$bill);
$returned = cashHandoverPreview($pdo,$second,$ticket)['devices'][0];
expectResult($closed['case_state'] === 'CLOSED' && $closed['closure_outcome'] === 'ALL_COMPLETED', 'Ticket still open.');
expectResult($returned['repair_status'] === 'COMPLETED' && $returned['custody_state'] === 'WITH_CUSTOMER'
    && $returned['physical_location_id'] === null, 'Device custody mismatch.');
echo "PASS: bill issuance, stale review, partial payment, overpayment refusal, pickup and closure.\n";
