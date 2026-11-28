<?php
declare(strict_types=1);

// Local command only. Never expose database upgrades through a public web page.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/migration_support.php';

$args = array_slice($argv, 1);
$allowed = ['--status', '--dry-run', '--apply', '--yes', '--help'];
if (
    array_diff($args, $allowed) ||
    (in_array('--yes', $args, true) && !in_array('--apply', $args, true)) ||
    (in_array('--apply', $args, true) &&
        (in_array('--status', $args, true) || in_array('--dry-run', $args, true)))
) {
    fwrite(STDERR, "Invalid options. Use php tools/migrate.php --help\n");
    exit(1);
}
if (in_array('--help', $args, true)) {
    echo "RepairTrack database upgrade\n\n";
    echo "php tools/migrate.php                 Show pending migrations (no writes)\n";
    echo "php tools/migrate.php --apply         Apply after confirming the database name\n";
    echo "php tools/migrate.php --apply --yes   Apply without an interactive prompt\n\n";
    echo "Uses config/config.php. Back up first and pause application writes.\n";
    echo "Upgrades an existing RepairTrack database; never imports seeds, creates accounts, or switches branches.\n";
    exit();
}
$pdo = null;
$lock = null;
try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Enable pdo_mysql in the PHP used by this terminal.');
    }
    $pdo = getPDO();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    migrationPreflight($pdo);
    echo "Database: $database\nServer: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n\n";
    $pending = migrationPending($pdo);
    migrationPrintStatus($pending);
    if (!$pending || !in_array('--apply', $args, true)) {
        echo $pending
            ? "\nNo changes made. Back up, then run: php tools/migrate.php --apply\n"
            : "\nDatabase is up to date. No changes needed.\n";
        exit();
    }
    echo "\nBack up your database and pause application writes before continuing.\n";
    echo "MySQL schema changes commit immediately. A failed upgrade may be partially applied; rerun after correcting the error.\n";
    if (!in_array('--yes', $args, true)) {
        echo "Type the database name ($database) to apply: ";
        if (trim((string) fgets(STDIN)) !== $database) {
            echo "Cancelled. No changes made.\n";
            exit();
        }
    }
    // Prevent two copies of this runner from changing the same database together.
    $lock = 'repairtrack-migrate-' . substr(hash('sha256', $database), 0, 40);
    $query = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $query->execute([$lock]);
    if ((int) $query->fetchColumn() !== 1) {
        $lock = null;
        throw new RuntimeException(
            'Another migration runner is active. Try again after it finishes.',
        );
    }
    // Recheck after acquiring the lock, since another runner may have completed.
    foreach (migrationPending($pdo) as $filename) {
        echo "Applying $filename ... ";
        migrationRunFile($pdo, __DIR__ . '/../database/migrations/' . $filename);
        echo "done\n";
    }
    if (migrationPending($pdo)) {
        throw new RuntimeException(
            'Some expected schema changes are still missing. Check database permissions and the migration files.',
        );
    }
    echo "\nUpgrade verified. Existing accounts and repair records were preserved.\n";
    echo "Quote-and-Approval uses existing tables. Counter-cash payments use repair_bill_payments.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "\nMigration stopped: " . $error->getMessage() . "\n");
    $failed = true;
} finally {
    if ($pdo && $lock) {
        $query = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $query->execute([$lock]);
    }
}
exit(isset($failed) ? 1 : 0);
