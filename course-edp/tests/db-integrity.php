<?php
declare(strict_types=1);

/**
 * Run ONLY on the disposable repairtrack_edp CI database.
 * The code never migrates, truncates or drops tables, and rolls back all fixtures.
 * Requires PHP pdo_mysql and a seeded schema.
 */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR, "Set REPAIRTRACK_EDP_TEST_DB=repairtrack_edp for this disposable test.\n");
    exit(2);
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "pdo_mysql is required for database execution tests.\n");
    exit(2);
}

$host = getenv('REPAIRTRACK_EDP_TEST_HOST') ?: '127.0.0.1';
$username = getenv('REPAIRTRACK_EDP_TEST_USER') ?: 'root';
$password = getenv('MYSQL_PWD') ?: '';
$dsn = 'mysql:host=' . $host . ';dbname=repairtrack_edp;charset=utf8mb4';
$db = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$db->exec("SET time_zone = '+00:00'");

function check(bool $result, string $description): void
{
    if (!$result) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    echo "PASS: $description\n";
}

function mustReject(PDO $db, string $sql, array $params, string $description): void
{
    try {
        $statement = $db->prepare($sql);
        $statement->execute($params);
    } catch (PDOException $error) {
        // Expected database constraint error. Deliberately avoid logging secret values.
        check($error->getCode() !== '00000', $description);
        return;
    }
    throw new RuntimeException('FAIL (accepted invalid data): ' . $description);
}

$version = (string) $db->query('SELECT VERSION()')->fetchColumn();
$tables = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='repairtrack_edp' AND table_type='BASE TABLE'")->fetchColumn();
check($tables === 22, 'exactly 22 classroom tables in MySQL ' . $version);
check((int) $db->query('SELECT COUNT(*) FROM repair_statuses')->fetchColumn() === 9, 'nine controlled repair statuses');
check((int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 5, 'five staff role codes');
check((int) $db->query("SELECT COUNT(*) FROM allowed_status_transitions WHERE from_status='READY_FOR_PICKUP' AND to_status='COMPLETED'")->fetchColumn() === 1, 'completion transition cataloged');

$db->beginTransaction();
try {
    $userHash = password_hash('FictionalOnly-2026!', PASSWORD_DEFAULT);
    $st = $db->prepare("INSERT INTO users (full_name,email,password_hash,role_code) VALUES (?,?,?,'TECHNICIAN')");
    $st->execute(['Fictional Technician', 'test.tech@example.test', $userHash]);
    $staff = (int) $db->lastInsertId();

    $db->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,1)')->execute([$staff]);
    $db->prepare("INSERT INTO customers (full_name,phone,email) VALUES ('Fictional Customer','09123456789',NULL)")->execute();
    $customer = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO customer_devices (customer_id,device_type_code,brand,model) VALUES (?,'LAPTOP','Demo','Model 1')")->execute([$customer]);
    $device = (int) $db->lastInsertId();

    mustReject($db,
        'INSERT INTO customer_devices (customer_id,device_type_code) VALUES (?,?)',
        [$customer, 'TELEVISION'],
        'invalid device category rejected by FK');

    // Drafts legitimately have no cashier ticket code until issued.
    $db->prepare("INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_state) VALUES (?,1,?,'DRAFT')")
        ->execute([$customer,$staff]);
    $ticket = (int) $db->lastInsertId();
    $db->prepare("UPDATE repair_tickets SET ticket_code=?, portal_code=?, portal_pin_hash=?, ticket_state='OPEN', issued_at=UTC_TIMESTAMP() WHERE ticket_id=?")
        ->execute(['RT-TEST-00001', 'AB123', password_hash('6789', PASSWORD_DEFAULT), $ticket]);
    check((int) $db->query("SELECT COUNT(*) FROM repair_tickets WHERE portal_code='AB123' AND ticket_state='OPEN'")->fetchColumn() === 1,
        'issued ticket retains separate internal ticket code and 5-hex customer code');

    mustReject($db,
        "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_code,portal_code,portal_pin_hash,ticket_state,issued_at) VALUES (?,1,?,'RT-TEST-00002','AB123','fakehash','OPEN',UTC_TIMESTAMP())",
        [$customer,$staff], 'duplicate portal code rejected');
    mustReject($db,
        "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_code,portal_code,portal_pin_hash,ticket_state,issued_at) VALUES (?,1,?,'RT-TEST-00002','ZZZZZ','fakehash','OPEN',UTC_TIMESTAMP())",
        [$customer,$staff], 'non-hex portal code rejected');

    $db->prepare("INSERT INTO ticket_devices (ticket_id,customer_device_id,sequence_no,status_code,assigned_technician_id,reported_symptoms) VALUES (?,?,1,'RECEIVED',?,'Does not power on')")
        ->execute([$ticket,$device,$staff]);
    $repairDevice = (int) $db->lastInsertId();
    mustReject($db, 'UPDATE ticket_devices SET status_code=? WHERE ticket_device_id=?',
        ['TELEPORTED',$repairDevice], 'invalid status rejected by FK');
    mustReject($db, 'INSERT INTO ticket_defects (ticket_device_id,defect_code) VALUES (?,?)',
        [$repairDevice,'HYPERSPACE'], 'invalid defect code rejected by FK');
    $db->prepare("INSERT INTO ticket_status_events (ticket_device_id,to_status,actor_user_id,is_customer_visible) VALUES (?,'RECEIVED',?,TRUE)")->execute([$repairDevice,$staff]);
    $db->prepare("UPDATE ticket_devices SET status_code='IN_PROGRESS',repair_started_at=UTC_TIMESTAMP() WHERE ticket_device_id=?")
        ->execute([$repairDevice]);
    $startedAt = $db->query("SELECT repair_started_at FROM ticket_devices WHERE ticket_device_id=$repairDevice")->fetchColumn();
    check(is_string($startedAt) && strlen($startedAt) > 0, 'repair first-start DateTime field supports the workflow');

    mustReject($db,
        'INSERT INTO repair_quotes (ticket_device_id,prepared_by_user_id,labor_amount,parts_amount,total_amount) VALUES (?,?,?,?,?)',
        [$repairDevice,$staff,100,20,130], 'incorrect quote total rejected');
    $db->prepare("INSERT INTO repair_quotes (ticket_device_id,prepared_by_user_id,labor_amount,parts_amount,total_amount) VALUES (?,?,?,?,?)")
        ->execute([$repairDevice,$staff,100,20,120]);
    mustReject($db,
        "INSERT INTO repair_quotes (ticket_device_id,prepared_by_user_id,version_no,quote_status,decision_recorded_by,total_amount) VALUES (?, ?,2,'APPROVED',NULL,0)",
        [$repairDevice,$staff], 'approved quote requires staff-recorded decision');

    $db->prepare("INSERT INTO repair_bills (ticket_id,bill_number,created_by_user_id,subtotal,total_amount) VALUES (?,?,?,?,?)")
        ->execute([$ticket,'BILL-TEST-1',$staff,120,120]);
    $bill = (int) $db->lastInsertId();
    mustReject($db,
        "INSERT INTO repair_bill_payments (bill_id,amount,request_key,received_by_user_id) VALUES (?,0,?,?)",
        [$bill,str_repeat('a',64),$staff], 'nonpositive payment rejected');
    $db->prepare("INSERT INTO repair_bill_payments (bill_id,amount,request_key,received_by_user_id) VALUES (?,?,?,?)")
        ->execute([$bill,120,str_repeat('b',64),$staff]);
    mustReject($db,
        'INSERT INTO repair_bill_payments (bill_id,amount,request_key,received_by_user_id) VALUES (?,?,?,?)',
        [$bill,10,str_repeat('b',64),$staff], 'duplicate payment request rejected');

    mustReject($db,
        "INSERT INTO media_assets (ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id) VALUES (?,'DEVICE_CONDITION','private/1.jpg','image/jpeg',6000000,?,?)",
        [$repairDevice,str_repeat('a',64),$staff], 'image larger than 5 MiB rejected');
    $db->prepare("INSERT INTO media_assets (ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id) VALUES (?,'DEVICE_CONDITION','private/1.jpg','image/jpeg',4096,?,?)")
        ->execute([$repairDevice,str_repeat('a',64),$staff]);
    check((int)$db->query("SELECT COUNT(*) FROM media_assets WHERE stored_path='private/1.jpg'")->fetchColumn() === 1,
        'private image metadata stored without embedding image bytes');

    check(true, 'fixture transaction can be rolled back without writing permanent demo data');
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
