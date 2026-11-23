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
