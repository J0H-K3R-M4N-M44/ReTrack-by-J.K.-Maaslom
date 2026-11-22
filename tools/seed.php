<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';

require_once __DIR__ . '/simulation_data.php';

try {
    if ((databaseConfig()['database'] ?? '') !== 'repairtrack_test_operator') {
        throw new RuntimeException('Simulation is permitted only in repairtrack_test_operator.');
    }
    $pdo = getPDO();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack_test_operator') {
        throw new RuntimeException('Simulation requires the isolated operator test database.');
    }
    $pdo->beginTransaction();
    $password = seedSimulation($pdo);
    $pdo->commit();
    echo "Created 24 simulated repairs in SIM-SOGOD.\n";
    echo "Accounts: manager, technician, intake_agent and cashier @sim.example.test\n";
    echo "Generated simulation password (save it now): $password\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
