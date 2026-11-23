<?php
declare(strict_types=1);

/** Creates fictional staff only in a disposable CI database; never run on actual customers. */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_DB') !== 'repairtrack_edp' ||
    getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR, "Use only the disposable academic CI database.\n");
    exit(2);
}
require_once __DIR__ . '/../app/preview.php';
$pdo = edpPreviewDb();
if (($argv[1] ?? '') === 'revoke') {
    $email = (string)($argv[2] ?? '');
    if (!preg_match('/^intake-(?:other-)?[a-f0-9]{12}@example\.test$/D', $email)) {
        throw new RuntimeException('Only synthetic intake fixtures may be revoked.');
    }
    $q = $pdo->prepare("UPDATE users SET is_active=0 WHERE email=? AND role_code='INTAKE_AGENT'");
    $q->execute([$email]);
    if ($q->rowCount() !== 1) throw new RuntimeException('Synthetic staff revocation failed.');
    echo "Revoked\n";
    exit;
}
if (($argv[1] ?? '') === 'portal-revoke') {
    $code = strtoupper((string)($argv[2] ?? ''));
    if (!preg_match('/^[0-9A-F]{5}$/D',$code)) throw new RuntimeException('Invalid fictional portal code.');
    $q=$pdo->prepare("UPDATE repair_tickets t
        JOIN customers c ON c.customer_id=t.customer_id
        JOIN users u ON u.user_id=t.created_by_user_id
        SET t.portal_pin_hash=?
        WHERE t.portal_code=? AND c.full_name='Fictional Classroom Customer'
        AND u.email LIKE 'intake-%@example.test'");
    $q->execute([password_hash('9999',PASSWORD_DEFAULT),$code]);
    if ($q->rowCount()!==1) throw new RuntimeException('Fictional tracking credentials not found.');
    echo "Fictional customer credential changed\n";
    exit;
}

/** Technicians are provisioned only by the disposable CLI fixture, never by a web endpoint. */
if (($argv[1] ?? '') === 'tech-create') {
    $branch=(int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
    if (!$branch) throw new RuntimeException('Fictional branch missing');
    $suffix=bin2hex(random_bytes(6));
    $techPassword=bin2hex(random_bytes(12));$otherPassword=bin2hex(random_bytes(12));
    $q=$pdo->prepare('INSERT INTO users(full_name,email,password_hash,role_code) VALUES (?,?,?,?)');
    $q->execute(['Fixture Supervisor',"techmanager-$suffix@example.test",
        password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT),'MANAGER']);
    $manager=(int)$pdo->lastInsertId();
    $q->execute(['Assigned Fixture Technician',"fixturetech-$suffix@example.test",
        password_hash($techPassword,PASSWORD_DEFAULT),'TECHNICIAN']);
    $tech=(int)$pdo->lastInsertId();
    $q->execute(['Other Fixture Technician',"otherfixturetech-$suffix@example.test",
        password_hash($otherPassword,PASSWORD_DEFAULT),'TECHNICIAN']);
    $other=(int)$pdo->lastInsertId();
    $assign=$pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES(?,?)');
    foreach ([$manager,$tech,$other] as $id) $assign->execute([$id,$branch]);
    $pdo->prepare("INSERT INTO customers(full_name,phone) VALUES('Fictional Technician Customer','09123456789')")->execute();
    $customer=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO customer_devices(customer_id,device_type_code) VALUES(?,'LAPTOP')")->execute([$customer]);
    $customerDevice=(int)$pdo->lastInsertId();
    $portal=strtoupper(substr(bin2hex(random_bytes(3)),0,5));
    $ticketCode='RT-TECH-'.strtoupper($suffix);
    $pdo->prepare("INSERT INTO repair_tickets(customer_id,branch_id,ticket_code,portal_code,portal_pin_hash,
        ticket_state,issued_at,created_by_user_id,terms_version,terms_signed_at,terms_signature_path)
        VALUES (?,?,?,?,?,'OPEN',UTC_TIMESTAMP(),?,'CLASSROOM-1',UTC_TIMESTAMP(),'signed/fixture-technician.png')")
        ->execute([$customer,$branch,$ticketCode,$portal,password_hash('6789',PASSWORD_DEFAULT),$manager]);
    $ticket=(int)$pdo->lastInsertId();
    $q=$pdo->prepare("SELECT location_id FROM storage_locations WHERE branch_id=? AND location_code='COUNTER'");
    $q->execute([$branch]);$location=(int)$q->fetchColumn();
    $pdo->prepare("INSERT INTO ticket_devices(ticket_id,customer_id,branch_id,customer_device_id,
        sequence_no,item_code,device_type_code,status_code,custody_state,location_id,reported_symptoms)
        VALUES (?,?,?,?,1,?,'LAPTOP','RECEIVED','AT_LOCATION',?,'Laptop overheats')")
        ->execute([$ticket,$customer,$branch,$customerDevice,$ticketCode.'-01',$location]);
    $device=(int)$pdo->lastInsertId();
    edpAssignTechnician($pdo,$manager,$device,$tech);
    echo json_encode(['tech_email'=>"fixturetech-$suffix@example.test",'tech_password'=>$techPassword,
        'other_email'=>"otherfixturetech-$suffix@example.test",'other_password'=>$otherPassword,
        'device_id'=>$device,'portal_code'=>$portal,'ticket_code'=>$ticketCode],JSON_THROW_ON_ERROR);
    exit;
}
if (($argv[1] ?? '') === 'tech-approve') {
    $deviceId=(int)($argv[2]??0);$portal=strtoupper((string)($argv[3]??''));
    if ($deviceId<1 || !preg_match('/^[A-F0-9]{5}$/D',$portal)) throw new RuntimeException('Invalid fixture identifiers');
    $q=$pdo->prepare("UPDATE repair_quotes q
        JOIN ticket_devices d ON d.ticket_device_id=q.ticket_device_id
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN users u ON u.user_id=t.created_by_user_id
        SET q.quote_status='APPROVED',q.sent_at=UTC_TIMESTAMP(),
            q.decision_recorded_by=u.user_id,q.decision_at=UTC_TIMESTAMP()
        WHERE d.ticket_device_id=? AND t.portal_code=? AND q.quote_status='DRAFT'
          AND u.email LIKE 'techmanager-%@example.test'");
    $q->execute([$deviceId,$portal]);
    if ($q->rowCount()!==1) throw new RuntimeException('Expected synthetic draft quote not found');
    echo "Synthetic manager/customer approval recorded for CI only\n";
    exit;
}
if (($argv[1] ?? '') === 'tech-revoke') {
    $email=(string)($argv[2]??'');
    if (!preg_match('/^fixturetech-[a-f0-9]{12}@example[.]test$/D',$email))
        throw new RuntimeException('Only fictional technician fixture accounts can be revoked');
    $q=$pdo->prepare("UPDATE users SET is_active=0 WHERE email=? AND role_code='TECHNICIAN'");
    $q->execute([$email]);
    if ($q->rowCount()!==1) throw new RuntimeException('Fictional technician was not found');
    echo "Synthetic technician disabled\n";exit;
}

// Add a realistic but strictly fictional repair update for customer-portal HTTP checks.
if (($argv[1] ?? '') === 'portal-progress') {
    $code = strtoupper((string)($argv[2] ?? ''));
    if (!preg_match('/^[0-9A-F]{5}$/D', $code)) throw new RuntimeException('Invalid fixture portal code.');
    $q = $pdo->prepare("SELECT d.ticket_device_id,t.branch_id FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.portal_code=? ORDER BY d.sequence_no LIMIT 1");
    $q->execute([$code]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Fictional issued repair not found.');
    $deviceId=(int)$row['ticket_device_id'];$branchId=(int)$row['branch_id'];
    $suffix=bin2hex(random_bytes(6));
    $insert=$pdo->prepare('INSERT INTO users (full_name,email,password_hash,role_code) VALUES (?,?,?,?)');
    $secret=password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT);
    $insert->execute(['Fictional Supervisor','supervisor-'.$suffix.'@example.test',$secret,'ADMIN']);
    $adminId=(int)$pdo->lastInsertId();
    $insert->execute(['Fictional Repair Technician','repairtech-'.$suffix.'@example.test',$secret,'TECHNICIAN']);
    $techId=(int)$pdo->lastInsertId();
    $assign=$pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)');
    $assign->execute([$adminId,$branchId]);$assign->execute([$techId,$branchId]);
    edpAssignTechnician($pdo,$adminId,$deviceId,$techId);
    edpChangeRepairStatus($pdo,$techId,$deviceId,'DIAGNOSING','The diagnostic assessment is underway.');
    $pdo->prepare("INSERT INTO repair_quotes (ticket_device_id,quote_status,labor_amount,parts_amount,
        total_amount,prepared_by_user_id,decision_recorded_by,decision_at,sent_at)
        VALUES (?,'APPROVED',600,250,850,?, ?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([$deviceId,$techId,$adminId]);
    edpChangeRepairStatus($pdo,$techId,$deviceId,'IN_PROGRESS','The approved repair has started.');
    $pdo->prepare("UPDATE ticket_status_events SET internal_note='PRIVATE_DIAGNOSIS_NEVER_SHOW'
        WHERE ticket_device_id=? AND to_status='IN_PROGRESS'")->execute([$deviceId]);
    $pdo->prepare("INSERT INTO ticket_status_events (ticket_device_id,from_status,to_status,actor_user_id,
        internal_note,public_summary,is_customer_visible)
        VALUES (?,NULL,'IN_PROGRESS',?,'HIDDEN_INTERNAL_ONLY',NULL,0)")->execute([$deviceId,$techId]);
    // This unsent draft quote on the OTHER device must never be disclosed.
    $other=$pdo->prepare('SELECT ticket_device_id FROM ticket_devices WHERE ticket_id=(
        SELECT ticket_id FROM ticket_devices WHERE ticket_device_id=?) AND ticket_device_id<>? LIMIT 1');
    $other->execute([$deviceId,$deviceId]);$otherId=$other->fetchColumn();
    if ($otherId) {
        $pdo->prepare("INSERT INTO repair_quotes (ticket_device_id,quote_status,labor_amount,total_amount,prepared_by_user_id)
            VALUES (?,'DRAFT',987654.32,987654.32,?)")->execute([(int)$otherId,$techId]);
    }
    echo "Fictional customer progress recorded\n";
    exit;
}

$branch = (int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if (!$branch) throw new RuntimeException('Fictional branch not seeded.');
$suffix = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(16));
$cashierPassword = bin2hex(random_bytes(16));
$otherPassword = bin2hex(random_bytes(16));
$code = 'EDP-'.strtoupper($suffix);
$q = $pdo->prepare('INSERT INTO branches (branch_code,branch_name) VALUES (?,?)');
$q->execute([$code,'Other Fictional Branch']);
$otherBranch = (int)$pdo->lastInsertId();
$add = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,role_code) VALUES (?,?,?,?)');
$add->execute(['Browser Intake Fixture',"intake-{$suffix}@example.test",password_hash($password,PASSWORD_DEFAULT),'INTAKE_AGENT']);
$staffId = (int)$pdo->lastInsertId();
$add->execute(['Browser Cashier Fixture',"cashier-{$suffix}@example.test",password_hash($cashierPassword,PASSWORD_DEFAULT),'CASHIER']);
$cashierId = (int)$pdo->lastInsertId();
$add->execute(['Other Branch Intake',"intake-other-{$suffix}@example.test",
    password_hash($otherPassword,PASSWORD_DEFAULT),'INTAKE_AGENT']);
$otherId = (int)$pdo->lastInsertId();
$assign = $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)');
$assign->execute([$staffId,$branch]);
$assign->execute([$cashierId,$branch]);
$assign->execute([$otherId,$otherBranch]);
echo json_encode([
    'email'=>"intake-{$suffix}@example.test",'password'=>$password,
    'cashier_email'=>"cashier-{$suffix}@example.test",'cashier_password'=>$cashierPassword,
    'branch_id'=>$branch,
    'other_email'=>"intake-other-{$suffix}@example.test",'other_password'=>$otherPassword,
    'other_branch_id'=>$otherBranch,
], JSON_THROW_ON_ERROR);
