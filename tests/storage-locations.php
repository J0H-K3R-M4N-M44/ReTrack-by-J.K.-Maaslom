<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/locations.php';
$pdo = getPDO();
if (!str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')) {
    throw new RuntimeException('Disposable test database required.');
}
function locationCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function locationReject(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException($message);
}
$fixture = json_decode(file_get_contents(__DIR__ . '/console-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$manager = $fixture['accounts']['MANAGER'];
$admin = $fixture['accounts']['ADMIN'];
$intake = $fixture['accounts']['INTAKE_AGENT'];
$tech = $fixture['accounts']['TECHNICIAN'];
$branches = intakeBranches($pdo, (int) $manager['user_id']);
locationCheck((bool) $branches, 'Fixture manager has no assigned branch.');
$branchId = (int) $branches[0]['branch_id'];
$code = 'TEST-LOC-' . strtoupper(bin2hex(random_bytes(4)));
$values = locationReadValues([
    'branch_id' => (string) $branchId,
    'location_code' => strtolower($code),
    'location_name' => 'QA workbench',
    'location_type' => 'WORKBENCH',
]);
locationCheck($values['location_code'] === $code, 'Location code not normalized');
locationCheck(locationValidate($values) === [], 'Valid location rejected');
foreach ([
    ['location_code' => 'x'],
    ['location_code' => 'BAD/FILE'],
    ['location_name' => '<' . chr(1)],
    ['location_type' => 'TRANSIT'],
    ['branch_id' => '0'],
] as $bad) {
    locationCheck(locationValidate(array_replace($values, $bad)) !== [], 'Unsafe location accepted');
}
locationReject(fn() => locationCreate($pdo, (int) $intake['user_id'], $values),
    'Intake agent created a location.');
locationReject(fn() => locationCreate($pdo, (int) $tech['user_id'], $values),
    'Technician created a location.');
$outside = $pdo->prepare(
    'SELECT b.branch_id FROM branches b WHERE b.is_active=1 AND NOT EXISTS
    (SELECT 1 FROM user_branch_assignments a WHERE a.user_id=? AND a.branch_id=b.branch_id)
    ORDER BY b.branch_id LIMIT 1',
);
$outside->execute([$manager['user_id']]);
$outsideId = $outside->fetchColumn();
if ($outsideId) {
    locationReject(fn() => locationCreate($pdo, (int) $manager['user_id'],
        array_replace($values, ['branch_id' => (string) $outsideId])),
        'Manager created a location outside assigned branches.');
}
$locationId = locationCreate($pdo, (int) $manager['user_id'], $values);
locationCheck($locationId > 0, 'Location not created');
$check = $pdo->prepare('SELECT location_name, location_type FROM storage_locations WHERE location_id=?');
$check->execute([$locationId]);
$stored = $check->fetch();
locationCheck($stored['location_name'] === 'QA workbench' && $stored['location_type'] === 'WORKBENCH',
    'Stored location does not match');
locationReject(fn() => locationCreate($pdo, (int) $manager['user_id'], $values),
    'Duplicate location allowed');
$available = intakeLocations($pdo, $branchId);
locationCheck(in_array($locationId, array_map('intval', array_column($available, 'location_id')), true),
    'Created location unavailable to intake issuance.');
$listed = locationPage($pdo, (int) $manager['user_id']);
locationCheck($listed['total'] > 0 && count($listed['rows']) <= 20, 'Location list bounds incorrect');
$adminList = locationPage($pdo, (int) $admin['user_id']);
locationCheck($adminList['total'] >= 1, 'Administrator cannot view assigned locations.');
$audit = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_events WHERE entity_type='storage_location'
    AND entity_id=? AND action='STORAGE_LOCATION_CREATED' AND actor_user_id=?",
);
$audit->execute([$locationId, $manager['user_id']]);
locationCheck((int) $audit->fetchColumn() === 1, 'Location audit missing');
// A late audit failure must roll the new row back completely.
$pdo->exec(
    "CREATE TRIGGER test_location_audit_failure BEFORE INSERT ON audit_events
    FOR EACH ROW BEGIN IF NEW.action='STORAGE_LOCATION_CREATED'
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected audit failure'; END IF; END",
);
$rollback = array_replace($values, ['location_code' => 'ROLLBACK-' . strtoupper(bin2hex(random_bytes(4)))]);
try {
    try {
        locationCreate($pdo, (int) $manager['user_id'], $rollback);
        throw new RuntimeException('Audit failure did not abort transaction');
    } catch (PDOException $expected) {
        // Expected injected failure.
    }
} finally {
    $pdo->exec('DROP TRIGGER test_location_audit_failure');
}
$q = $pdo->prepare('SELECT COUNT(*) FROM storage_locations WHERE branch_id=? AND location_code=?');
$q->execute([$branchId, $rollback['location_code']]);
locationCheck((int) $q->fetchColumn() === 0, 'Audit rollback left a location');
echo "PASS: location validation, role/branch scope, duplicate denial, audit rollback and intake availability.\n";
