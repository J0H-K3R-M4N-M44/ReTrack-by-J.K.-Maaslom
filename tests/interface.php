<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Disposable test database required.');
}
// Reuse the seeded branch administrator only in the test database.
$pdo->prepare('UPDATE users SET email=?, password_hash=? WHERE user_id=1')->execute([
    'interface.admin@example.test',
    password_hash('InterfaceDemo2026!', PASSWORD_DEFAULT),
]);
echo "PASS: interface test account prepared.\n";
