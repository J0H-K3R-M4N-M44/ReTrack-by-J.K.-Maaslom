<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/parts/requests.php';
$pdo = getPDO();
if (!str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')) {
    throw new RuntimeException('Refusing any non-disposable test database.');
}
function partCheck(bool $result, string $message): void
{
    if (!$result) {
        throw new RuntimeException($message);
    }
}
function partDenied(callable $test, string $message): void
{
    try {
        $test();
    } catch (DomainException $error) {
        return;
    }
    throw new RuntimeException($message);
}
$f = json_decode(file_get_contents(__DIR__ . '/console-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$tech = $f['accounts']['TECHNICIAN'];
$manager = $f['accounts']['MANAGER'];
$viewer = $f['accounts']['VIEWER'];
$intake = $f['accounts']['INTAKE_AGENT'];
$deviceId = (int) $f['device'];
$device = consoleDevice($pdo, $tech, $deviceId);
$pdo->prepare("UPDATE ticket_devices SET repair_status='DIAGNOSING', repair_started_at=NULL WHERE ticket_device_id=?")->execute([$deviceId]);
$before = $pdo->prepare('SELECT repair_status,assigned_technician_id FROM ticket_devices WHERE ticket_device_id=?');
$before->execute([$deviceId]);
$oldDevice = $before->fetch();
$values = partRequestValues(['part_description' => 'Replacement cooling fan', 'quantity' => '2.500',
    'reason' => 'The installed fan is no longer rotating and must be inspected.']);
partRequestValidate($values);
foreach ([
    ['quantity' => '0'],
    ['quantity' => '-1'],
    ['quantity' => '1e4'],
    ['quantity' => '1.2345'],
    ['quantity' => '10000'],
    ['part_description' => ''],
    ['reason' => 'short'],
] as $bad) {
    partDenied(fn() => partRequestValidate(array_replace($values, $bad)), 'Invalid request accepted.');
}
partDenied(fn() => partRequestCreate($pdo, $manager['user_id'], $deviceId, $values),
    'Manager submitted technician request.');
partDenied(fn() => partRequestCreate($pdo, $viewer['user_id'], $deviceId, $values),
    'Viewer submitted internal request.');
partDenied(fn() => partRequestCreate($pdo, $intake['user_id'], $deviceId, $values),
    'Intake staff submitted internal request.');
partDenied(fn() => partRequestCreate($pdo, $tech['user_id'], (int) $f['outside'], $values),
    'Technician created a request for another branch.');
$id = partRequestCreate($pdo, $tech['user_id'], $deviceId, $values);
partCheck($id > 0, 'Request id missing');
$draft = partRequestLoad($pdo, $manager, $id);
partCheck($draft['status'] === 'OPEN' && $draft['part_description'] === $values['part_description'],
    'Request not persisted as open');
partDenied(fn() => partRequestLoad($pdo, $viewer, $id), 'Viewer opened part request.');
partDenied(fn() => partRequestLoad($pdo, $intake, $id), 'Intake staff opened part request.');
partCheck(partRequestList($pdo, $tech, '1', $deviceId)['total'] >= 1,
    'Assigned technician cannot see request.');
partCheck(partRequestList($pdo, $manager, '1', $deviceId)['total'] >= 1,
    'Manager cannot see request.');
partDenied(fn() => partRequestChange($pdo, $tech['user_id'], $id,
    partRequestVersion($draft), 'acknowledge', ''),
    'Technician reviewed request.');
partDenied(fn() => partRequestChange($pdo, $manager['user_id'], $id,
    partRequestVersion($draft), 'resolve', 'Part arrived at the branch.'),
    'Open request directly resolved.');
partRequestChange($pdo, $manager['user_id'], $id, partRequestVersion($draft),
    'acknowledge', 'We will check availability.');
$ack = partRequestLoad($pdo, $tech, $id);
partCheck($ack['status'] === 'ACKNOWLEDGED', 'Acknowledgement not saved');
partDenied(fn() => partRequestChange($pdo, $manager['user_id'], $id,
    partRequestVersion($draft), 'resolve', 'Verified branch supply received.'),
    'Stale request revision was accepted.');
partDenied(fn() => partRequestChange($pdo, $manager['user_id'], $id,
    partRequestVersion($ack), 'decline', 'No longer needed.'),
    'Acknowledged request was declined.');
partRequestChange($pdo, $manager['user_id'], $id, partRequestVersion($ack),
    'resolve', 'Supplier confirmed the replacement fan is available for quoting.');
partCheck(partRequestLoad($pdo, $tech, $id)['status'] === 'RESOLVED',
    'Resolved request not recorded.');
partDenied(fn() => partRequestChange($pdo, $manager['user_id'], $id,
    partRequestVersion($ack), 'resolve', 'Replay request decision.'),
    'Resolved request replay accepted.');
$second = partRequestCreate($pdo, $tech['user_id'], $deviceId, $values);
partRequestChange($pdo, $manager['user_id'], $second,
    partRequestVersion(partRequestLoad($pdo, $manager, $second)),
    'decline', 'This part is incompatible with the device model.');
partCheck(partRequestLoad($pdo, $manager, $second)['status'] === 'DECLINED',
    'Decline decision not recorded');
$third = partRequestCreate($pdo, $tech['user_id'], $deviceId, $values);
// A late audit failure should roll back the status change and version.
$pdo->exec("CREATE TRIGGER test_part_request_audit BEFORE INSERT ON audit_events
    FOR EACH ROW BEGIN IF NEW.action='PART_REQUEST_ACKNOWLEDGE'
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected audit failure'; END IF; END");
try {
    try {
        partRequestChange($pdo, $manager['user_id'], $third,
            partRequestVersion(partRequestLoad($pdo, $manager, $third)),
            'acknowledge', 'Checking availability');
        throw new RuntimeException('Audit failure did not abort request decision');
    } catch (PDOException $expected) {
        // Deliberate audit failure.
    }
} finally {
    $pdo->exec('DROP TRIGGER test_part_request_audit');
}
partCheck(partRequestLoad($pdo, $manager, $third)['status'] === 'OPEN',
    'Audit failure left a partially acknowledged request.');
$after = $pdo->prepare('SELECT repair_status,assigned_technician_id FROM ticket_devices WHERE ticket_device_id=?');
$after->execute([$deviceId]);
partCheck($after->fetch() === $oldDevice,
    'Internal part request unexpectedly changed repair status or technician assignment.');
$ledger = $pdo->prepare('SELECT COUNT(*) FROM inventory_transactions WHERE reference_code LIKE ?');
$ledger->execute(['PART-REQUEST-' . $id . '%']);
partCheck((int) $ledger->fetchColumn() === 0, 'Request unexpectedly moved inventory.');
echo "PASS: technician needs, scoped review, optimistic versioning, status gates, audit rollback, no repair/stock writes.\n";
