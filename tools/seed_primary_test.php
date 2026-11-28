<?php
declare(strict_types=1);

// This command adds fictional simulation records to an existing disposable test database.
// It never creates, migrates, or drops a database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

if (($argv[1] ?? '') !== '--confirm' || count($argv) !== 2) {
    fwrite(STDERR, "No changes made. Run: php tools/seed_primary_test.php --confirm\n");
    exit(2);
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/simulation_data.php';

$pdo = null;
$password = null;

try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Enable pdo_mysql in the PHP used by this terminal.');
    }

    $config = databaseConfig();
    $configuredDatabase = (string) ($config['database'] ?? '');
    if (!preg_match('/^repairtrack_test_[a-zA-Z0-9_]+$/', $configuredDatabase)) {
        throw new RuntimeException(
            'Refusing to seed. config/config.php must select a database named repairtrack_test_ followed by a test identifier.',
        );
    }

    $pdo = getPDO();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== $configuredDatabase) {
        throw new RuntimeException('Configured and connected database names do not match.');
    }

    // Check the complete set of tables used by the simulation fixture before writing.
    $requiredTables = [
        'branches',
        'storage_locations',
        'users',
        'user_branch_assignments',
        'customers',
        'customer_contacts',
        'repair_tickets',
        'ticket_devices',
        'ticket_status_events',
        'repair_quotes',
        'repair_quote_decisions',
        'repair_quote_items',
        'repair_bills',
        'repair_bill_items',
        'repair_bill_payments',
    ];
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    );
    foreach ($requiredTables as $table) {
        $tableCheck->execute([$table]);
        if ((int) $tableCheck->fetchColumn() !== 1) {
            throw new RuntimeException(
                "Required table $table is missing. Import the current schema and migrations into the disposable test database first.",
            );
        }
    }

    $pdo->beginTransaction();
    $password = seedSimulation($pdo);
    $pdo->commit();

    echo "Simulation data added successfully.\n";
    echo "Database: $database\n";
    echo "Branch: SIM-SOGOD (24 fictional repair records)\n";
    echo "All four demo staff accounts use the same temporary password below.\n";
    echo "Password: $password\n";
    echo "Demo accounts:\n";
    foreach (['manager', 'technician', 'intake_agent', 'cashier'] as $role) {
        echo "  $role@sim.example.test\n";
    }
    echo "Keep these credentials local. Do not reuse them outside this disposable database.\n";
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Seeding stopped: " . $error->getMessage() . "\n");
    exit(1);
}
