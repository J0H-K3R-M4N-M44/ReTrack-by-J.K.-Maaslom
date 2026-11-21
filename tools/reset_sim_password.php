<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
try {
    $pdo = getPDO();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack') {
        throw new RuntimeException('Select repairtrack in config/config.php.');
    }
    $pdo->beginTransaction();
    $lookup = $pdo->prepare("SELECT u.user_id FROM users u
        JOIN user_branch_assignments a ON a.user_id=u.user_id
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE u.email=? AND u.role=? AND u.is_active=1 AND b.branch_code='SIM-SOGOD'
        AND b.is_active=1 AND NOT EXISTS (SELECT 1 FROM user_branch_assignments other
        WHERE other.user_id=u.user_id AND other.branch_id<>b.branch_id) FOR UPDATE");
    $password = bin2hex(random_bytes(12));
    $update = $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?');
    $revoke = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=? AND used_at IS NULL');
    foreach (['MANAGER','TECHNICIAN','INTAKE_AGENT','CASHIER'] as $role) {
        $email = strtolower($role).'@sim.example.test';
        $lookup->execute([$email,$role]);
        $id = $lookup->fetchColumn();
        if (!$id) {
            throw new RuntimeException("Missing or changed simulation account: $email. Reset cancelled.");
        }
        $update->execute([password_hash($password, PASSWORD_DEFAULT),$id]);
        $revoke->execute([$id]);
    }
    $pdo->commit();
    echo "New simulation password (save it now): $password\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
