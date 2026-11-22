<?php
declare(strict_types=1);

// Run with XAMPP's CLI PHP. Only this invocation's newly created database receives writes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/public/tracking.php';
require_once __DIR__ . '/../app/payments/repository.php';
require_once __DIR__ . '/../app/console/reports.php';
require_once __DIR__ . '/../app/console/activity.php';
require_once __DIR__ . '/../tools/migration_support.php';
require_once __DIR__ . '/../tools/simulation_data.php';

function checkWorkflow(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException($message);
    }
}

$database = 'repairtrack_test_' . bin2hex(random_bytes(8));
$created = false;
$file = null;
$result = 0;
try {
    startAppSession();
    $pdo = getPDO();
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo->exec("USE `$database`");
    // The SQL export has its own database selection for Workbench imports.
    // Redirect both installation statements to this run's new schema.
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $schema = str_replace(
        ['CREATE DATABASE IF NOT EXISTS repairtrack', 'USE repairtrack;'],
        ["CREATE DATABASE IF NOT EXISTS `$database`", "USE `$database`;"],
        $schema,
        $replacements,
    );
    checkWorkflow($replacements === 2, 'Schema installation header changed. Review test setup.');
    $file = tempnam(sys_get_temp_dir(), 'rt-schema-');
    checkWorkflow($file !== false && file_put_contents($file, $schema) !== false, 'Cannot stage schema.');
    migrationRunFile($pdo, $file);
    checkWorkflow($pdo->query('SELECT DATABASE()')->fetchColumn() === $database, 'Wrong test database.');
    // Fresh schema must be complete without needing the incremental migration runner.
    checkWorkflow(migrationPending($pdo) === [], 'Fresh schema left a pending migration.');
    foreach (['inventory_transfers'=>'status','inventory_transfer_items'=>'item_status'] as $table=>$column) {
        $query = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $query->execute([$table,$column]);
        checkWorkflow(str_contains((string)$query->fetchColumn(),"'LOSS_CLOSED'"),
            'Inventory transfer is missing operational loss closure status.');
    }
    foreach (['device_transfers'=>'status','device_transfer_items'=>'item_status'] as $table=>$column) {
        $query = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $query->execute([$table,$column]);
        checkWorkflow(!str_contains((string)$query->fetchColumn(),"'LOSS_CLOSED'"),
            'Device custody transfers must not acquire inventory-loss statuses.');
    }

    $pdo->exec('DROP TABLE staff_activity_reads');
    checkWorkflow(migrationPending($pdo) === ['staff_activity.sql'], 'Unexpected migration status.');
    migrationRunFile($pdo, __DIR__ . '/../database/staff_activity.sql');
    checkWorkflow(migrationPending($pdo) === [], 'Activity migration remains pending.');
    $pdo->beginTransaction();
    $password = seedSimulation($pdo);
    $pdo->commit();

    foreach (['MANAGER', 'TECHNICIAN', 'INTAKE_AGENT', 'CASHIER'] as $role) {
        $email = strtolower($role) . '@sim.example.test';
        checkWorkflow(loginAccount($email, $password), "Login failed for $role.");
        $user = currentAccount();
        checkWorkflow($user !== null && $user['role'] === $role, "Session role mismatch for $role.");
        foreach (activityGroups($role) as $group) {
            [$source, $parameters] = activitySource($user, $group);
            $query = $pdo->prepare($source);
            $query->execute($parameters);
            $event = $query->fetch();
            checkWorkflow((bool) $event, "Missing seeded $group activity for $role.");
            $query->closeCursor();
            // A repeated click should leave exactly one read receipt for this user.
            $id = (string) $event['event_id'];
            activityMarkRead($pdo, $user, $group, $id);
            activityMarkRead($pdo, $user, $group, $id);
            $seen = $pdo->prepare('SELECT COUNT(*) FROM staff_activity_reads
                WHERE user_id = ? AND event_group = ? AND event_id = ?');
            $seen->execute([$user['user_id'], $group, $id]);
            checkWorkflow((int) $seen->fetchColumn() === 1, "Read receipt mismatch for $role.");

        }
        if (consoleCanViewReports($user)) {
            $report = loadStaffReport($pdo, $user, staffReportPeriod([]), null);
            checkWorkflow(count($report['daily']) === 7, "Incomplete weekly report for $role.");
        }
    }

    // The last account signed in is the cashier. Follow the seeded partial payment to pickup.
    $ticket = $pdo->query("SELECT t.portal_code, b.bill_id FROM repair_tickets t
        JOIN repair_bills b ON b.ticket_id = t.ticket_id WHERE t.ticket_code = 'SIM-RT-005'")->fetch();
    checkWorkflow((bool) $ticket, 'Missing seeded pickup ticket.');
    $portal = $ticket['portal_code'];
    $billId = (int) $ticket['bill_id'];
    $actor = (int) $user['user_id'];
    checkWorkflow(publicRepair($pdo, $portal, '0000') === null, 'Incorrect PIN opened the portal.');
    $repair = publicRepair($pdo, $portal, '1004');
    checkWorkflow(($repair['devices'][0]['repair_status'] ?? '') === 'READY_FOR_PICKUP', 'Portal status mismatch.');
    $bill = cashBillDetails($pdo, $user, $billId);
    checkWorkflow($bill['balance_cents'] > 0 && count($bill['payments']) === 1, 'Missing partial payment.');
    try {
        cashCompleteHandover($pdo, $actor, $billId, $bill['version'], 'Rosa Reyes', 'CUSTOMER', 'yes');
        throw new RuntimeException('Unpaid pickup was accepted.');
    } catch (DomainException $error) {
        checkWorkflow(str_contains($error->getMessage(), 'full bill balance'), $error->getMessage());
    }
    cashRecordPayment($pdo, $actor, $billId, $bill['version'], cashAmountText($bill['balance_cents']), bin2hex(random_bytes(32)));
    $paid = cashBillDetails($pdo, $user, $billId);
    checkWorkflow($paid['balance_cents'] === 0 && count($paid['payments']) === 2, 'Settlement mismatch.');
    cashCompleteHandover($pdo, $actor, $billId, $paid['version'], 'Rosa Reyes', 'CUSTOMER', 'yes');
    $returned = publicRepair($pdo, $portal, '1004');
    checkWorkflow(($returned['devices'][0]['custody_state'] ?? '') === 'WITH_CUSTOMER', 'Portal missed the handover.');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    $result = 1;
} finally {
    if (is_string($file) && is_file($file)) {
        unlink($file);
    }
    if ($created) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec("DROP DATABASE `$database`");
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
if ($result === 0) {
    echo "PASS: migration, four staff logins, activity, reports, PIN, settlement and pickup.\n";
}
exit($result);
