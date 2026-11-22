<?php
declare(strict_types=1);

// This destructive rehearsal is only for a disposable database after the
// payment/pickup and stock fixture tests have completed.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../tools/migration_support.php';
$pdo = getPDO();
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_starts_with($db, 'repairtrack_test_')) {
    throw new RuntimeException('Only a disposable repairtrack_test_ database is allowed.');
}
function branchMigrationCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function branchMigrationMissing(PDO $pdo): bool
{
    return in_array('device_repair_branches.sql', migrationPending($pdo), true);
}
branchMigrationCheck(migrationHasColumn($pdo, 'ticket_devices', 'repair_branch_id'),
    'Fresh schema is missing the repair branch column.');
branchMigrationCheck(!branchMigrationMissing($pdo),
    'The initial backfill or schema migration is incomplete.');

$sample = $pdo->query("SELECT d.ticket_device_id, t.accountable_branch_id
    FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
    WHERE t.record_stage='ISSUED' AND t.accountable_branch_id IS NOT NULL
    ORDER BY d.ticket_device_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
branchMigrationCheck((bool) $sample, 'The disposable suite must contain an issued device.');
$id = (int) $sample['ticket_device_id'];
$branch = (int) $sample['accountable_branch_id'];
$remaining = $pdo->query("SELECT COUNT(*) FROM ticket_devices d
    JOIN repair_tickets t ON t.ticket_id=d.ticket_id
    WHERE t.record_stage='ISSUED' AND d.repair_branch_id IS NULL")->fetchColumn();
branchMigrationCheck((int) $remaining === 0, 'Issued device branch missing after backfill.');

// Simulate an interrupted backfill, including an intentionally old timestamp.
$pdo->prepare("UPDATE ticket_devices
    SET repair_branch_id=NULL, updated_at='2021-01-01 00:00:00'
    WHERE ticket_device_id=?")->execute([$id]);
branchMigrationCheck(branchMigrationMissing($pdo),
    'Pending migration did not detect an uninitialized issued device.');
migrationRunFile($pdo, __DIR__ . '/../database/device_repair_branches.sql');
$row = $pdo->prepare('SELECT repair_branch_id, updated_at FROM ticket_devices WHERE ticket_device_id=?');
$row->execute([$id]);
$restored = $row->fetch(PDO::FETCH_ASSOC);
branchMigrationCheck((int) $restored['repair_branch_id'] === $branch,
    'The migration did not restore ticket-aligned ownership.');
branchMigrationCheck($restored['updated_at'] === '2021-01-01 00:00:00',
    'Historical device update timestamp was rewritten during backfill.');
branchMigrationCheck(!branchMigrationMissing($pdo), 'Backfill remains pending.');

// Rehearse a partially completed DDL deployment: column survives, FK and
// explicit lookup index do not. Migration must recover them independently.
$pdo->exec('ALTER TABLE ticket_devices
    DROP FOREIGN KEY fk_td_repair_branch, DROP INDEX idx_td_repair_branch');
branchMigrationCheck(branchMigrationMissing($pdo),
    'Pending migration did not detect a missing repair branch FK/index.');
migrationRunFile($pdo, __DIR__ . '/../database/device_repair_branches.sql');
branchMigrationCheck(!branchMigrationMissing($pdo), 'FK/index recovery failed.');

// Rerunning should be harmless and must not overwrite explicit non-null values.
migrationRunFile($pdo, __DIR__ . '/../database/device_repair_branches.sql');
$row->execute([$id]);
$again = $row->fetch(PDO::FETCH_ASSOC);
branchMigrationCheck($again === $restored, 'An idempotent rerun changed an issued repair.');
$draftChanges = $pdo->query("SELECT COUNT(*) FROM ticket_devices d
    JOIN repair_tickets t ON t.ticket_id=d.ticket_id
    WHERE t.record_stage='DRAFT' AND d.repair_branch_id IS NOT NULL")->fetchColumn();
branchMigrationCheck((int) $draftChanges === 0, 'Draft devices should not receive repair ownership.');

// FK must reject a nonexistent branch; leave the row unchanged afterward.
try {
    $pdo->prepare('UPDATE ticket_devices SET repair_branch_id=? WHERE ticket_device_id=?')
        ->execute([PHP_INT_MAX, $id]);
    throw new RuntimeException('FK accepted a nonexistent repair branch.');
} catch (PDOException $expected) {
    // Expected: referenced branch does not exist.
}
$row->execute([$id]);
branchMigrationCheck($row->fetch(PDO::FETCH_ASSOC) === $restored,
    'FK rejection changed the device row.');
echo "PASS: additive repair branch migration, interrupted backfill, timestamp retention, FK/index recovery and idempotence.\n";
