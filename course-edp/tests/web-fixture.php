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
$branch = (int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if (!$branch) throw new RuntimeException('Fictional branch not seeded.');
$suffix = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(16));
$cashierPassword = bin2hex(random_bytes(16));
$add = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,role_code) VALUES (?,?,?,?)');
$add->execute(['Browser Intake Fixture',"intake-{$suffix}@example.test",password_hash($password,PASSWORD_DEFAULT),'INTAKE_AGENT']);
$staffId = (int)$pdo->lastInsertId();
$add->execute(['Browser Cashier Fixture',"cashier-{$suffix}@example.test",password_hash($cashierPassword,PASSWORD_DEFAULT),'CASHIER']);
$cashierId = (int)$pdo->lastInsertId();
$assign = $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)');
$assign->execute([$staffId,$branch]);
$assign->execute([$cashierId,$branch]);
echo json_encode([
    'email'=>"intake-{$suffix}@example.test",'password'=>$password,
    'cashier_email'=>"cashier-{$suffix}@example.test",'cashier_password'=>$cashierPassword,
    'branch_id'=>$branch,
], JSON_THROW_ON_ERROR);
