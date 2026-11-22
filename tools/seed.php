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
    // Give the operator one fictional customer example without another SQL lookup.
    $example = $pdo->query("SELECT t.portal_code,
        RIGHT(c.normalized_value, 4) AS pin
        FROM repair_tickets t
        JOIN customer_contacts c ON c.customer_id = t.customer_id
        WHERE t.ticket_code = 'SIM-RT-001' AND c.contact_type = 'PHONE'
        ORDER BY c.is_primary DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $pdo->commit();
    echo "Created 24 simulated repairs in SIM-SOGOD.\n";
    echo "Accounts: manager, technician, intake_agent and cashier @sim.example.test\n";
    echo "Generated simulation password (save it privately): $password\n";
    if ($example) {
        echo "Sample customer tracking: code {$example['portal_code']} / PIN {$example['pin']}\n";
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
