<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/console/activity.php';
require_once __DIR__ . '/../tools/simulation_data.php';

function expectValue(mixed $expected, mixed $actual, string $case): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($case . ' failed: ' . json_encode($actual));
    }
    echo "PASS $case\n";
}

$pdo = getPDO();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack_test_activity') {
    throw new RuntimeException('Use the disposable repairtrack_test_activity database.');
}
$pdo->beginTransaction();
try {
    seedSimulation($pdo);
    $accounts = $pdo->query("SELECT role, user_id FROM users
        WHERE email LIKE '%@sim.example.test'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $manager = ['role' => 'MANAGER', 'user_id' => $accounts['MANAGER']];
    $tech = ['role' => 'TECHNICIAN', 'user_id' => $accounts['TECHNICIAN']];
    $cashier = ['role' => 'CASHIER', 'user_id' => $accounts['CASHIER']];
    $counts = static fn(array $user): array => array_map('intval', activityUnreadCounts($pdo, $user));
    $all = ['repairs' => 129, 'payments' => 20, 'pickups' => 19];
    expectValue($all, $counts($manager), 'manager sees all three categories');
    expectValue(['payments' => 20, 'pickups' => 19], $counts($cashier), 'cashier sees counter activity');
    expectValue(['repairs' => 129], $counts($tech), 'technician sees assigned repairs');

    $payment = (string) $pdo->query("SELECT p.payment_id FROM repair_bill_payments p
        JOIN repair_bills b ON b.bill_id = p.bill_id WHERE b.bill_number = 'SIM-RT-005-B'")->fetchColumn();
    activityMarkRead($pdo, $cashier, 'payments', $payment);
    activityMarkRead($pdo, $cashier, 'payments', $payment);
    expectValue(['payments' => 19, 'pickups' => 19], $counts($cashier), 'repeat read changes one count once');
    expectValue($all, $counts($manager), 'second staff member still has the same payment unread');

    $device = (int) $pdo->query("SELECT ticket_device_id FROM ticket_devices
        WHERE item_code = 'SIM-RT-001-01'")->fetchColumn();
    $event = (string) $pdo->query("SELECT status_event_id FROM ticket_status_events
        WHERE ticket_device_id = $device")->fetchColumn();
    $pdo->prepare('UPDATE ticket_devices SET assigned_technician_id = NULL WHERE ticket_device_id = ?')
        ->execute([$device]);
    expectValue(['repairs' => 128], $counts($tech), 'unassigned device disappears from technician count');
    activityMarkRead($pdo, $tech, 'repairs', $event);
    $reads = $pdo->prepare('SELECT COUNT(*) FROM staff_activity_reads WHERE user_id = ?');
    $reads->execute([$tech['user_id']]);
    expectValue(0, (int) $reads->fetchColumn(), 'outside-assignment event creates no read receipt');

    $otherBranch = seedRow($pdo, 'branches', ['branch_code' => 'TEST-B', 'branch_name' => 'Branch B']);
    $pdo->prepare("UPDATE repair_tickets SET accountable_branch_id = ? WHERE ticket_code = 'SIM-RT-002'")
        ->execute([$otherBranch]);
    expectValue(['repairs' => 126], $counts($tech), 'assigned device outside branch excluded');
    expectValue(['repairs' => 127, 'payments' => 20, 'pickups' => 19], $counts($manager),
        'manager cannot count events from another branch');
} finally {
    $pdo->rollBack();
}
