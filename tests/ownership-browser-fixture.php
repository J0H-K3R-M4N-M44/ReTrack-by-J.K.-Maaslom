<?php
declare(strict_types=1);

// Disposable browser fixture only. Creates an intentionally mismatched
// two-device ticket to test *existing* ticket-scoped permissions and safe
// read-only branch diagnostics. This is not a partial-transfer implementation.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../app/db.php';

$path = getenv('PICKUP_FIXTURE');
if (!is_string($path) || $path === '' || !is_file($path)) {
    throw new RuntimeException('Set PICKUP_FIXTURE to the private payment-browser fixture file.');
}
$pdo = getPDO();
$name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($name !== 'repairtrack_test_pickup') {
    throw new RuntimeException('This browser fixture may only write to repairtrack_test_pickup.');
}
$fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
foreach (['ticket', 'manager', 'technician', 'password', 'branch'] as $key) {
    if (!isset($fixture[$key])) {
        throw new RuntimeException('Missing payment fixture prerequisite: ' . $key);
    }
}

$find = $pdo->prepare('SELECT user_id FROM users WHERE email=? AND is_active=1');
$find->execute([$fixture['manager']]);
$manager = (int) $find->fetchColumn();
$find->execute([$fixture['technician']]);
$technician = (int) $find->fetchColumn();
$origin = (int) $fixture['branch'];
$loc = $pdo->prepare('SELECT location_id FROM storage_locations WHERE branch_id=? LIMIT 1');
$loc->execute([$origin]);
$originLocation = (int) $loc->fetchColumn();
if (!$manager || !$technician || !$originLocation) {
    throw new RuntimeException('Source manager, technician or branch location is missing.');
}
$lookup = $pdo->prepare('SELECT customer_id FROM repair_tickets WHERE ticket_code=? AND accountable_branch_id=?');
$lookup->execute([$fixture['ticket'], $origin]);
$customer = (int) $lookup->fetchColumn();
if (!$customer) {
    throw new RuntimeException('Source browser ticket is missing.');
}

$run = (string) $fixture['ticket'];
$remoteLabel = '<em data-rt-ownership>Untrusted destination label</em>';
$pdo->beginTransaction();
try {
    $insert = static function (string $sql, array $args) use ($pdo): int {
        $pdo->prepare($sql)->execute($args);
        return (int) $pdo->lastInsertId();
    };
    $destination = $insert('INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
        [$run . 'OWN', $remoteLabel]);
    $destinationLocation = $insert(
        "INSERT INTO storage_locations (branch_id,location_code,location_name,location_type)
         VALUES (?,'OWN-BENCH','Ownership test counter','WORKBENCH')", [$destination]);
    $users = [];
    foreach (['MANAGER', 'TECHNICIAN', 'CASHIER'] as $role) {
        $email = $run . '-ownership-' . strtolower($role) . '@example.test';
        $id = $insert('INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,?)',
            ['Ownership fixture ' . $role, $email, password_hash((string) $fixture['password'], PASSWORD_DEFAULT), $role]);
        $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')
            ->execute([$id, $destination]);
        $users[strtolower($role)] = $email;
    }
    $ticket = $insert(
        "INSERT INTO repair_tickets
          (ticket_code,record_stage,case_state,customer_id,intake_branch_id,
           accountable_branch_id,accepted_by_user_id,arrival_method,intake_date,
           issued_at,terms_accepted,terms_signature_path,terms_signed_at)
         VALUES (?,'ISSUED','OPEN',?,?,?,?,'WALK_IN',UTC_DATE(),UTC_TIMESTAMP(),1,'fixture',UTC_TIMESTAMP())",
        [$run . '-OWN', $customer, $origin, $origin, $manager]);
    $makeDevice = static function (int $sequence, int $branch, int $location, ?int $assigned) use ($insert, $run, $ticket): int {
        return $insert(
            "INSERT INTO ticket_devices
             (ticket_id,child_sequence,item_code,device_type_snapshot,reported_symptoms,
              condition_notes,serial_number_snapshot,accessory_checklist_completed,
              repair_status,custody_state,physical_location_id,repair_branch_id,assigned_technician_id)
             VALUES (?,?,?,'Laptop','Intermittent display','Fixture inspected',?,1,
                'DIAGNOSING','AT_LOCATION',?,?,?)",
            [$ticket, $sequence, $run . '-OWN-0' . $sequence, $run . '-OWN-SERIAL-' . $sequence,
                $location, $branch, $assigned]);
    };
    $localDevice = $makeDevice(1, $origin, $originLocation, $technician);
    $remoteDevice = $makeDevice(2, $destination, $destinationLocation, null);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$fixture['ownership'] = [
    'ticket_id' => $ticket,
    'local_device' => $localDevice,
    'remote_device' => $remoteDevice,
    'source_label' => 'Pickup test branch',
    'remote_label' => $remoteLabel,
    'remote_manager' => $users['manager'],
    'remote_technician' => $users['technician'],
    'remote_cashier' => $users['cashier'],
];
// Keep credentials in RUNNER_TEMP only, never print them to action logs.
$flags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR;
if (file_put_contents($path, json_encode($fixture, $flags)) === false) {
    throw new RuntimeException('Unable to update private browser fixture file.');
}
chmod($path, 0600);
echo "PASS: disposable browser fixture for read-only ownership and role-isolation tests.\n";
