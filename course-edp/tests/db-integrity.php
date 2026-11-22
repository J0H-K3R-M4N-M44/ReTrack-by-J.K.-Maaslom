<?php
declare(strict_types=1);

/** Disposable MySQL integration tests. All fictional records are rolled back. */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR, "Test only a disposable repairtrack_edp database.\n");
    exit(2);
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "pdo_mysql is required.\n");
    exit(2);
}
require_once __DIR__ . '/../app/actions.php';

$pdo = new PDO('mysql:host=' . (getenv('REPAIRTRACK_EDP_TEST_HOST') ?: '127.0.0.1') .
    ';dbname=repairtrack_edp;charset=utf8mb4',
    getenv('REPAIRTRACK_EDP_TEST_USER') ?: 'root', getenv('MYSQL_PWD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$pdo->exec("SET time_zone = '+00:00'");

function pass(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: $message");
    }
    echo "PASS: $message\n";
}

/** Never mistake a syntax/connection bug for a successful constraint test. */
function rejectsConstraint(PDO $pdo, string $sql, array $params, int $mysqlError, string $message): void
{
    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
    } catch (PDOException $error) {
        $actual = (int) ($error->errorInfo[1] ?? 0);
        if ($actual !== $mysqlError) {
            throw new RuntimeException("FAIL: $message; expected MySQL $mysqlError, got $actual", 0, $error);
        }
        pass(true, $message);
        return;
    }
    throw new RuntimeException("FAIL: invalid data accepted: $message");
}

function rejectsDomain(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $error) {
        pass(true, $message);
        return;
    }
    throw new RuntimeException("FAIL: forbidden action accepted: $message");
}

function insert(PDO $pdo, string $sql, array $args = []): int
{
    $pdo->prepare($sql)->execute($args);
    return (int) $pdo->lastInsertId();
}

$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'repairtrack_edp' AND table_type = 'BASE TABLE'")->fetchColumn();
pass($tables === 23, 'exactly 23 independent academic tables');
pass((int) $pdo->query('SELECT COUNT(*) FROM repair_statuses')->fetchColumn() === 10,
    'draft and nine repair-stage codes are predefined');
pass((int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 5,
    'five supported staff roles are seeded');
pass((int) $pdo->query("SELECT COUNT(*) FROM allowed_status_transitions WHERE from_status = 'READY_FOR_PICKUP' AND to_status = 'COMPLETED'")->fetchColumn() === 1,
    'pickup completion is catalogued but reserved for a future handover action');
$branch = (int) $pdo->query("SELECT branch_id FROM branches WHERE branch_code = 'SOGOD-DEMO'")->fetchColumn();
pass($branch > 0, 'Sogod fiction branch is found by code, never assumed ID');
pass((int) $pdo->query('SELECT COUNT(*) FROM storage_locations')->fetchColumn() === 3,
    'three example locations attached to the fictional branch');

$pdo->beginTransaction();
try {
    $otherBranch = insert($pdo, "INSERT INTO branches (branch_code,branch_name) VALUES ('OTHER-TEST','Second Fictional Branch')");
    $otherLocation = insert($pdo, "INSERT INTO storage_locations (branch_id,location_code,location_name) VALUES (?,'BENCH','Other Branch Bench')", [$otherBranch]);
    $staffHash = password_hash('FictionalOnly!', PASSWORD_DEFAULT);
    $tech = insert($pdo, "INSERT INTO users (full_name,email,password_hash,role_code) VALUES ('Tech Example','tech@example.test',?,'TECHNICIAN')", [$staffHash]);
    $cashier = insert($pdo, "INSERT INTO users (full_name,email,password_hash,role_code) VALUES ('Cash Example','cash@example.test',?,'CASHIER')", [$staffHash]);
    $manager = insert($pdo, "INSERT INTO users (full_name,email,password_hash,role_code) VALUES ('Manager Example','manager@example.test',?,'MANAGER')", [$staffHash]);
    $otherTech = insert($pdo, "INSERT INTO users (full_name,email,password_hash,role_code) VALUES ('Other Tech','tech-other@example.test',?,'TECHNICIAN')", [$staffHash]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')->execute([$tech, $branch]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')->execute([$cashier, $branch]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')->execute([$manager, $branch]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')->execute([$otherTech, $otherBranch]);
    $alice = insert($pdo, "INSERT INTO customers (full_name,phone) VALUES ('Customer A','09123456789')");
    $bob = insert($pdo, "INSERT INTO customers (full_name,phone) VALUES ('Customer B','09998887777')");
    $aliceDevice = insert($pdo, "INSERT INTO customer_devices (customer_id,device_type_code,brand,model) VALUES (?,'LAPTOP','Fictional','Example A')", [$alice]);
    $bobDevice = insert($pdo, "INSERT INTO customer_devices (customer_id,device_type_code,brand,model) VALUES (?,'DESKTOP','Fictional','Example B')", [$bob]);
    $aliceDevice2 = insert($pdo, "INSERT INTO customer_devices (customer_id,device_type_code,brand,model) VALUES (?,'DESKTOP','Fictional','Example C')", [$alice]);

    rejectsConstraint($pdo, 'INSERT INTO customer_devices (customer_id,device_type_code) VALUES (?,?)',
        [$alice,'NOT_A_DEVICE'], 1452, 'unknown device type rejected by foreign key');
    $draft = insert($pdo, "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id) VALUES (NULL,?,?)", [$branch, $tech]);
    pass((int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE ticket_id=$draft AND customer_id IS NULL AND ticket_state='DRAFT'")->fetchColumn() === 1,
        'draft intake can exist before customer selection');
    $staged = insert($pdo, "INSERT INTO ticket_devices(ticket_id,branch_id,sequence_no,status_code,device_type_code,reported_symptoms) VALUES(?, ?,2,'DRAFT','DESKTOP','Demo device before customer')", [$draft,$branch]);
    pass($staged > 0, 'device-first draft saves a category and symptoms before customer selection');
    rejectsConstraint($pdo, "UPDATE repair_tickets SET portal_code='AAA11',portal_pin_hash='test-hash' WHERE ticket_id=?",[$draft],3819,
        'draft cannot issue tracking credentials prematurely');
    rejectsConstraint($pdo, "UPDATE repair_tickets SET ticket_state='OPEN' WHERE ticket_id=?", [$draft], 3819,
        'cannot issue incomplete draft without customer and ticket code');
    $pdo->prepare('UPDATE repair_tickets SET customer_id=? WHERE ticket_id=?')->execute([$alice,$draft]);
    $pdo->prepare("UPDATE repair_tickets SET ticket_code='RT-SAMPLE-1',portal_code='AB123',
        portal_pin_hash=?,ticket_state='OPEN',issued_at=UTC_TIMESTAMP(),terms_version='CLASSROOM-1',
        terms_signed_at=UTC_TIMESTAMP(),terms_signature_path='signed/classroom_1.png' WHERE ticket_id=?")
        ->execute([password_hash('6789', PASSWORD_DEFAULT),$draft]);
    $pdo->prepare("UPDATE ticket_devices SET customer_id=?,customer_device_id=?,item_code='RT-SAMPLE-1-02',
        status_code='RECEIVED',custody_state='AT_LOCATION' WHERE ticket_device_id=?")
        ->execute([$alice,$aliceDevice2,$staged]);
    pass(password_verify('6789', (string) $pdo->query("SELECT portal_pin_hash FROM repair_tickets WHERE ticket_id=$draft")->fetchColumn()),
        'five-character portal code pairs with hashed PIN; internal Ticket Code remains separate');
    rejectsConstraint($pdo, "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_code,portal_code,portal_pin_hash,ticket_state,issued_at,terms_version,terms_signed_at,terms_signature_path) VALUES (?,?,?,'RT-UNIQUE-2','AB123','hash','OPEN',UTC_TIMESTAMP(),'CLASSROOM-1',UTC_TIMESTAMP(),'signed/demo.png')",
        [$alice,$branch,$tech], 1062, 'five-character portal code uniqueness enforced');
    rejectsConstraint($pdo, "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_code,portal_code,portal_pin_hash,ticket_state,issued_at,terms_version,terms_signed_at,terms_signature_path) VALUES (?,?,?,'RT-UNIQUE-3','ZZZZZ','hash','OPEN',UTC_TIMESTAMP(),'CLASSROOM-1',UTC_TIMESTAMP(),'signed/demo.png')",
        [$alice,$branch,$tech], 3819, 'portal code rejects nonhexadecimal characters');

    rejectsConstraint($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,reported_symptoms) VALUES (?,?,?,?,1,'TEST-A','LAPTOP','RECEIVED','None')",
        [$draft,$alice,$branch,$bobDevice], 1452, 'Customer A ticket cannot attach Customer B device');
    rejectsConstraint($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,reported_symptoms) VALUES (?,?,?,?,1,'TEST-B','LAPTOP','RECEIVED','None')",
        [$draft,$alice,$otherBranch,$aliceDevice], 1452, 'ticket device must use accountable branch');
    rejectsConstraint($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,assigned_technician_id,reported_symptoms) VALUES (?,?,?,?,1,'TEST-C','LAPTOP','RECEIVED',?,'None')",
        [$draft,$alice,$branch,$aliceDevice,$otherTech], 1452, 'assigned technician must belong to ticket branch');
    rejectsConstraint($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,location_id,reported_symptoms) VALUES (?,?,?,?,1,'TEST-D','LAPTOP','RECEIVED',?,'None')",
        [$draft,$alice,$branch,$aliceDevice,$otherLocation], 1452, 'device location must belong to accountable branch');
    $item = insert($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,custody_state,assigned_technician_id,reported_symptoms) VALUES (?,?,?,?,1,'RT-SAMPLE-1-01','LAPTOP','RECEIVED','AT_LOCATION',?,'Not powering on')",
        [$draft,$alice,$branch,$aliceDevice,$tech]);
    rejectsConstraint($pdo, 'UPDATE ticket_devices SET status_code=? WHERE ticket_device_id=?',
        ['IN_OUTER_SPACE',$item], 1452, 'unknown repair status rejected');
    rejectsConstraint($pdo, 'INSERT INTO ticket_defects (ticket_device_id,defect_code) VALUES (?,?)',
        [$item,'UNKNOWN_FAULT'], 1452, 'unknown defect rejected');
    rejectsConstraint($pdo, "INSERT INTO ticket_status_events (ticket_device_id,from_status,to_status,actor_user_id) VALUES (?,'RECEIVED','READY_FOR_PICKUP',?)",
        [$item,$tech], 1452, 'recorded history cannot cite an invalid status transition');
    insert($pdo, "INSERT INTO ticket_status_events (ticket_device_id,from_status,to_status,actor_user_id,is_customer_visible) VALUES (?,NULL,'RECEIVED',?,1)", [$item,$tech]);
    rejectsDomain(fn() => edpAssignTechnician($pdo,$manager,$item,$cashier),
        'cashier cannot be assigned as technician');
    rejectsDomain(fn() => edpAssignTechnician($pdo,$cashier,$staged,$tech),
        'cashier cannot assign technicians');
    rejectsDomain(fn() => edpAssignTechnician($pdo,$manager,$staged,$otherTech),
        'technician from wrong branch cannot be assigned');
    rejectsDomain(fn() => edpAssignTechnician($pdo,$manager,$staged,$tech,1),
        'technician active limit respected');
    edpAssignTechnician($pdo,$manager,$staged,$tech,2);
    insert($pdo, "INSERT INTO ticket_status_events(ticket_device_id,to_status,actor_user_id,is_customer_visible) VALUES (?,'RECEIVED',?,1)", [$staged,$tech]);
    $pdo->prepare('UPDATE branches SET is_active=0 WHERE branch_id=?')->execute([$branch]);
    rejectsDomain(fn() => edpChangeRepairStatus($pdo,$tech,$item,'DIAGNOSING'),
        'inactive branch blocks technician changes');
    $pdo->prepare('UPDATE branches SET is_active=1 WHERE branch_id=?')->execute([$branch]);
    rejectsDomain(fn() => edpChangeRepairStatus($pdo,$cashier,$item,'DIAGNOSING'),
        'cashier cannot advance technician repair');
    rejectsDomain(fn() => edpChangeRepairStatus($pdo,$otherTech,$item,'DIAGNOSING'),
        'technician outside branch cannot advance repair');
    rejectsDomain(fn() => edpChangeRepairStatus($pdo,$tech,$item,'READY_FOR_PICKUP'),
        'skipping directly to ready for pickup is rejected');
    edpChangeRepairStatus($pdo,$tech,$item,'DIAGNOSING');
    rejectsDomain(fn() => edpChangeRepairStatus($pdo,$tech,$item,'IN_PROGRESS'),
        'repair cannot start without current approved quote');
    rejectsConstraint($pdo, 'INSERT INTO repair_quotes (ticket_device_id,prepared_by_user_id,labor_amount,parts_amount,total_amount) VALUES (?,?,?,?,?)',
        [$item,$tech,100,20,130], 3819, 'quote components must equal quote total');
    $quote = insert($pdo, "INSERT INTO repair_quotes (ticket_device_id,prepared_by_user_id,version_no,quote_status,labor_amount,parts_amount,total_amount,decision_recorded_by,decision_at)
        VALUES (?,?,1,'APPROVED',100,20,120,?,UTC_TIMESTAMP())", [$item,$tech,$tech]);
    $firstStart = edpChangeRepairStatus($pdo,$tech,$item,'IN_PROGRESS');
    pass(is_string($firstStart) && $firstStart !== '', 'legal repair start records first timestamp');
    edpChangeRepairStatus($pdo,$tech,$item,'TESTING');
    $secondStart = edpChangeRepairStatus($pdo,$tech,$item,'IN_PROGRESS');
    pass($secondStart === $firstStart, 'reentering repair does not replace original start timestamp');
    pass((int) $pdo->query("SELECT COUNT(*) FROM ticket_status_events WHERE ticket_device_id=$item")->fetchColumn() === 5,
        'successful transitions append event history once each');

    $bobTicket = insert($pdo, "INSERT INTO repair_tickets (customer_id,branch_id,created_by_user_id,ticket_state,ticket_code,portal_code,portal_pin_hash,issued_at,terms_version,terms_signed_at,terms_signature_path) VALUES (?,?,?,'OPEN','RT-BOB-2','CD345','fake-hash',UTC_TIMESTAMP(),'CLASSROOM-1',UTC_TIMESTAMP(),'signed/bob.png')",
        [$bob,$branch,$tech]);
    $bobItem = insert($pdo, "INSERT INTO ticket_devices (ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,custody_state,reported_symptoms)
        VALUES (?,?,?,?,1,'RT-BOB-2-01','DESKTOP','RECEIVED','AT_LOCATION','Fictional fault')", [$bobTicket,$bob,$branch,$bobDevice]);
    rejectsConstraint($pdo, "INSERT INTO media_assets (customer_id,ticket_id,ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id)
        VALUES (?,?,?,'DEVICE_CONDITION','private/test.jpg','image/jpeg',4096,?,?)",
        [$bob,$draft,$item,str_repeat('a',64),$tech], 1452, 'asset cannot associate Customer B with Customer A ticket');
    rejectsConstraint($pdo, "INSERT INTO media_assets (customer_id,ticket_id,ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id)
        VALUES (?,?,?,'DEVICE_CONDITION','private/test.jpg','image/jpeg',4096,?,?)",
        [$alice,$draft,$bobItem,str_repeat('a',64),$tech], 1452, 'asset device must belong to associated ticket');
    rejectsConstraint($pdo, "INSERT INTO media_assets (customer_id,ticket_id,ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id)
        VALUES (?,?,?,'DEVICE_CONDITION','../private/test.jpg','image/jpeg',4096,?,?)",
        [$alice,$draft,$item,str_repeat('a',64),$tech], 3819, 'private asset path rejects traversal');
    rejectsConstraint($pdo, "INSERT INTO media_assets (customer_id,ticket_id,ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id)
        VALUES (?,?,?,'DEVICE_CONDITION','private/test.jpg','image/jpeg',6000000,?,?)",
        [$alice,$draft,$item,str_repeat('a',64),$tech], 3819, 'media metadata cannot exceed 5 MiB');
    insert($pdo, "INSERT INTO media_assets (customer_id,ticket_id,ticket_device_id,asset_category,stored_path,mime_type,size_bytes,sha256_hash,uploaded_by_user_id)
        VALUES (?,?,?,'DEVICE_CONDITION','private/test.jpg','image/jpeg',4096,?,?)",
        [$alice,$draft,$item,str_repeat('a',64),$tech]);
    rejectsConstraint($pdo, 'INSERT INTO notes (ticket_id,ticket_device_id,author_user_id,note_title,note_text) VALUES (?,?,?,?,?)',
        [$draft,$bobItem,$tech,'Incorrect owner','Should be rejected'], 1452,
        'note cannot associate a device with an unrelated ticket');

    $draftBill = insert($pdo, "INSERT INTO repair_bills (ticket_id,created_by_user_id,subtotal,total_amount) VALUES (?,?,150,150)", [$draft,$cashier]);
    insert($pdo, 'INSERT INTO repair_bill_items(bill_id,ticket_id,ticket_device_id,description,amount) VALUES(?,?,?,?,?)',
        [$draftBill,$draft,$item,'Laptop repair',120]);
    insert($pdo, 'INSERT INTO repair_bill_items(bill_id,ticket_id,ticket_device_id,description,amount) VALUES(?,?,?,?,?)',
        [$draftBill,$draft,$staged,'Desktop diagnosis',30]);
    rejectsConstraint($pdo,'INSERT INTO repair_bill_items(bill_id,ticket_id,ticket_device_id,description,amount) VALUES(?,?,?,?,?)',
        [$draftBill,$draft,$bobItem,'Wrong customer device',1],1452,
        'bill line cannot reference another ticket device');
    pass((int) $pdo->query("SELECT COUNT(*) FROM repair_bills WHERE bill_id=$draftBill AND bill_number IS NULL AND bill_status='DRAFT'")->fetchColumn() === 1,
        'draft bill does not require issued bill number');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$cashier,$draftBill,'20.00',str_repeat('a',64)),
        'draft bill cannot accept payment');
    $pdo->prepare("UPDATE repair_bills SET bill_status='ISSUED',bill_number='BILL-TEST-1',issued_at=UTC_TIMESTAMP() WHERE bill_id=?")
        ->execute([$draftBill]);
    rejectsConstraint($pdo, "INSERT INTO repair_bills(ticket_id,version_no,bill_number,bill_status,subtotal,total_amount,created_by_user_id,issued_at) VALUES (?,2,'BILL-DUP-2','ISSUED',150,150,?,UTC_TIMESTAMP())",
        [$draft,$cashier],1062,'two simultaneous issued bills on same ticket rejected');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$tech,$draftBill,'20.00',str_repeat('b',64)),
        'technician cannot record cashier payment');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$cashier,$draftBill,'20.00',str_repeat('b',64),'COD'),
        'COD rejected until verified collection workflow exists');
    $key = str_repeat('c',64);
    $paymentId = edpRecordCashPayment($pdo,$cashier,$draftBill,'20.00',$key);
    pass($paymentId === edpRecordCashPayment($pdo,$cashier,$draftBill,'20.00',$key),
        'identical payment retry is idempotent');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$cashier,$draftBill,'30.00',$key),
        'payment key cannot be reused with different amount');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$cashier,$draftBill,'131.00',str_repeat('d',64)),
        'cannot overpay issued bill');
    edpRecordCashPayment($pdo,$cashier,$draftBill,'130.00',str_repeat('e',64));
    pass((int) $pdo->query("SELECT COUNT(*) FROM repair_bill_payments WHERE bill_id=$draftBill")->fetchColumn() === 2,
        'exact bill settlement records two payments and no duplicate');
    rejectsDomain(fn() => edpRecordCashPayment($pdo,$cashier,$draftBill,'0.01',str_repeat('f',64)),
        'paid-in-full bill cannot receive additional cash');
    rejectsDomain(fn() => edpConfirmPickup($pdo,$cashier,$draft),
        'pickup fails until all devices are tested and ready');
    edpChangeRepairStatus($pdo,$tech,$item,'TESTING');
    edpChangeRepairStatus($pdo,$tech,$item,'READY_FOR_PICKUP');
    rejectsDomain(fn() => edpConfirmPickup($pdo,$cashier,$draft),
        'pickup rejects second device that is not yet ready');
    edpChangeRepairStatus($pdo,$tech,$staged,'DIAGNOSING');
    $stagedQuote=insert($pdo, "INSERT INTO repair_quotes(ticket_device_id,prepared_by_user_id,quote_status,
        labor_amount,total_amount,decision_recorded_by,decision_at) VALUES (?,?,'APPROVED',30,30,?,UTC_TIMESTAMP())",
        [$staged,$tech,$tech]);
    edpChangeRepairStatus($pdo,$tech,$staged,'IN_PROGRESS');
    edpChangeRepairStatus($pdo,$tech,$staged,'TESTING');
    edpChangeRepairStatus($pdo,$tech,$staged,'READY_FOR_PICKUP');
    edpConfirmPickup($pdo,$cashier,$draft);
    pass((int)$pdo->query("SELECT COUNT(*) FROM ticket_devices WHERE ticket_id=$draft AND status_code='COMPLETED' AND custody_state='WITH_CUSTOMER'")->fetchColumn() === 2,
        'two devices handed over with per-device completed status and custody history');
    pass((int)$pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE ticket_id=$draft AND ticket_state='CLOSED' AND closed_at IS NOT NULL")->fetchColumn() === 1,
        'ticket closes only after all devices are settled and handed over');
    rejectsDomain(fn() => edpConfirmPickup($pdo,$cashier,$draft),'repeat pickup is rejected');
    rejectsConstraint($pdo, "INSERT INTO repair_bill_payments (bill_id,amount,request_key,received_by_user_id) VALUES (?,?,?,?)",
        [$draftBill,0,str_repeat('f',64),$cashier], 3819, 'zero payment rejected by CHECK constraint');
    pass(true, 'all temporary records are isolated in a rollback-only fixture');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
