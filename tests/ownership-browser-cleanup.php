<?php
declare(strict_types=1);

// Removes ONLY the short-lived synthetic ownership browser fixture.
// Must run on the exact disposable MySQL database used by the browser test.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../app/db.php';

$path = getenv('PICKUP_FIXTURE');
if (!is_string($path) || !is_file($path)) {
    throw new RuntimeException('Missing private PICKUP_FIXTURE file.');
}
$pdo = getPDO();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack_test_pickup') {
    throw new RuntimeException('Refusing ownership fixture cleanup outside repairtrack_test_pickup.');
}
$fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$owner = $fixture['ownership'] ?? null;
if (!is_array($owner) || !isset($fixture['ticket'], $fixture['branch'], $owner['ticket_id'],
    $owner['local_device'], $owner['remote_device'], $owner['remote_label'],
    $owner['remote_manager'], $owner['remote_technician'], $owner['remote_cashier'])) {
    throw new RuntimeException('No complete ownership fixture metadata; nothing was deleted.');
}
$ticket = (int) $owner['ticket_id'];
$local = (int) $owner['local_device'];
$remote = (int) $owner['remote_device'];
$origin = (int) $fixture['branch'];
$check = $pdo->prepare(
    'SELECT t.ticket_code,t.accountable_branch_id,d.ticket_device_id,d.repair_branch_id,
            d.physical_location_id,b.branch_code,b.branch_name
     FROM repair_tickets t JOIN ticket_devices d ON d.ticket_id=t.ticket_id
     JOIN branches b ON b.branch_id=d.repair_branch_id
     WHERE t.ticket_id=? ORDER BY d.ticket_device_id FOR UPDATE'
);
$pdo->beginTransaction();
try {
    $check->execute([$ticket]);
    $devices = $check->fetchAll(PDO::FETCH_ASSOC);
    if (count($devices) !== 2 ||
        array_map('intval', array_column($devices, 'ticket_device_id')) !== [$local, $remote] ||
        $devices[0]['ticket_code'] !== $fixture['ticket'] . '-OWN' ||
        (int) $devices[0]['accountable_branch_id'] !== $origin ||
        (int) $devices[0]['repair_branch_id'] !== $origin ||
        (int) $devices[1]['accountable_branch_id'] !== $origin ||
        $devices[1]['branch_code'] !== $fixture['ticket'] . 'OWN' ||
        $devices[1]['branch_name'] !== $owner['remote_label']) {
        throw new RuntimeException('Synthetic fixture changed since browser testing; refusing deletion.');
    }
    $destination = (int) $devices[1]['repair_branch_id'];
    $location = (int) $devices[1]['physical_location_id'];
    if (!$destination || !$location || $destination === $origin) {
        throw new RuntimeException('Unexpected synthetic branch/location.');
    }
    $users = [];
    $lookup = $pdo->prepare('SELECT user_id,role FROM users WHERE email=?');
    foreach (['MANAGER' => 'remote_manager', 'TECHNICIAN' => 'remote_technician',
              'CASHIER' => 'remote_cashier'] as $role => $key) {
        $lookup->execute([$owner[$key]]);
        $u = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['role'] !== $role) {
            throw new RuntimeException('Synthetic user changed; refusing deletion.');
        }
        $users[] = (int) $u['user_id'];
    }
    $del = static function (string $sql, array $args, int $expected) use ($pdo): void {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        if ($stmt->rowCount() !== $expected) {
            throw new RuntimeException('Fixture deletion count mismatch; rolling back.');
        }
    };
    $del('DELETE FROM ticket_devices WHERE ticket_id=? AND ticket_device_id IN (?,?)',
        [$ticket, $local, $remote], 2);
    $del('DELETE FROM repair_tickets WHERE ticket_id=?', [$ticket], 1);
    foreach ($users as $userId) {
        $del('DELETE FROM user_branch_assignments WHERE user_id=? AND branch_id=?',
            [$userId, $destination], 1);
        $del('DELETE FROM users WHERE user_id=?', [$userId], 1);
    }
    $del('DELETE FROM storage_locations WHERE location_id=? AND branch_id=?',
        [$location, $destination], 1);
    $del('DELETE FROM branches WHERE branch_id=? AND branch_code=?',
        [$destination, $fixture['ticket'] . 'OWN'], 1);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}
unset($fixture['ownership']);
if (file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Records cleaned; could not reset private browser fixture metadata.');
}
chmod($path, 0600);
echo "PASS: synthetic cross-branch browser ticket, location, and accounts cleaned before normal payment browser tests.\n";
