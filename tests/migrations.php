<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../tools/migration_support.php';
$pdo = getPDO();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_starts_with($database, 'repairtrack_test_')) {
    throw new RuntimeException('Disposable test database required.');
}
function migrationAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function runMigrationTest(array $args, string $input = ''): array
{
    $php = json_decode(getenv('REPAIRTRACK_TEST_PHP') ?: 'null', true) ?: [PHP_BINARY];
    $process = proc_open(
        [...$php, __DIR__ . '/../tools/migrate.php', ...$args],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($process), $output . $error];
}
$users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$tickets = $pdo->query('SELECT COUNT(*) FROM repair_tickets')->fetchColumn();
// Simulate the pre-workspace schema only inside the disposable fixture database.
$pdo->exec('DROP TABLE IF EXISTS staff_email_outbox');
$pdo->exec('DROP TABLE IF EXISTS customer_email_outbox');
$pdo->exec('DROP TABLE IF EXISTS staff_notifications');
$pdo->exec('ALTER TABLE customer_contacts DROP COLUMN email_updates_consent_at');
$pdo->exec('DROP TABLE IF EXISTS password_reset_tokens');
$pdo->exec('DROP TABLE IF EXISTS repair_bill_payments');
$pdo->exec(
    'ALTER TABLE repair_tickets DROP INDEX uq_portal_code, DROP COLUMN portal_code, DROP COLUMN portal_pin_hash',
);
$pdo->exec('ALTER TABLE ticket_devices DROP COLUMN repair_started_at');
[$code, $output] = runMigrationTest(['--status']);
migrationAssert($code === 0 && str_contains($output, '[pending]'), 'Status failed');
migrationAssert(
    !migrationHasColumn($pdo, 'ticket_devices', 'repair_started_at'),
    'Status wrote to database',
);
[$code, $output] = runMigrationTest(['--apply'], "wrong-database\n");
migrationAssert($code === 0 && str_contains($output, 'Cancelled'), 'Confirmation did not cancel');
migrationAssert(!migrationHasTable($pdo, 'password_reset_tokens'), 'Cancelled upgrade wrote data');
$lock = 'repairtrack-migrate-' . substr(hash('sha256', $database), 0, 40);
$query = $pdo->prepare('SELECT GET_LOCK(?, 0)');
$query->execute([$lock]);
[$code, $output] = runMigrationTest(['--apply', '--yes']);
migrationAssert(
    $code === 1 && str_contains($output, 'Another migration runner'),
    'Concurrent runner not blocked',
);
$query = $pdo->prepare('SELECT RELEASE_LOCK(?)');
$query->execute([$lock]);
[$code, $output] = runMigrationTest(['--apply', '--yes']);
migrationAssert(
    $code === 0 && str_contains($output, 'Upgrade verified'),
    'Upgrade failed: ' . $output,
);
migrationAssert(migrationPending($pdo) === [], 'Upgrade still pending');
migrationAssert(migrationHasColumn($pdo, 'customer_contacts', 'email_updates_consent_at'),
    'Customer email consent migration missing.');
migrationAssert(migrationHasTable($pdo, 'staff_notifications'), 'Staff inbox migration missing.');
migrationAssert(migrationHasTable($pdo, 'customer_email_outbox'), 'Customer email outbox migration missing.');
migrationAssert(migrationHasTable($pdo, 'staff_email_outbox'), 'Staff email outbox migration missing.');
[$code, $output] = runMigrationTest(['--apply', '--yes']);
migrationAssert(
    $code === 0 && str_contains($output, 'No changes needed'),
    'Rerun is not idempotent',
);
// Recover the interrupted-index case without removing the existing column or data.
$pdo->exec('ALTER TABLE repair_tickets DROP INDEX uq_portal_code');
[$code, $output] = runMigrationTest(['--apply', '--yes']);
migrationAssert(
    $code === 0 && migrationPending($pdo) === [],
    'Partial upgrade not recovered: ' . $output,
);
migrationAssert(
    $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $users,
    'Accounts changed',
);
migrationAssert(
    $pdo->query('SELECT COUNT(*) FROM repair_tickets')->fetchColumn() === $tickets,
    'Tickets changed',
);
[$code] = runMigrationTest(['--apply', '--status']);
migrationAssert($code === 1, 'Conflicting CLI flags accepted');
echo "PASS: migration status, cancellation, lock, upgrade, rerun, partial-index recovery and account/ticket preservation.\n";
