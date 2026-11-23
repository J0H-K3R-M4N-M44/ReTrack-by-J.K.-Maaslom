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
