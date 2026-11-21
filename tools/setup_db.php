<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/migration_support.php';

try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Enable pdo_mysql in the PHP used by Git Bash.');
    }
    $config = databaseConfig();
    if (($config['database'] ?? 'repairtrack') !== 'repairtrack') {
        throw new RuntimeException('Set database to repairtrack in config/config.php to install the application schema.');
    }

    $pdo = connectDatabase($config, null);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS repairtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec('USE repairtrack');
    // Older databases should use the migration runner so any required backfill is preserved.
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables && !migrationHasTable($pdo, 'users')) {
        throw new RuntimeException('repairtrack already contains tables without the RepairTrack users table. Check the database before proceeding.');
    }
    migrationRunFile($pdo, __DIR__ . '/../database/schema.sql');
    $pending = migrationPending($pdo);
    if ($pending) {
        throw new RuntimeException('Existing database needs upgrades: ' . implode(', ', $pending) .
            '. Back up repairtrack, run php tools/migrate.php --apply, then repeat setup.');
    }
    echo "Schema loaded into repairtrack.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Database setup stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
