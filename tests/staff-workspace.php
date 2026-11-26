<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/public/tracking.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Disposable test database required. Run staff-consoles.php first.');
}
function workspaceCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function workspaceDenied(callable $action): void
{
    try {
        $action();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException('Expected rejection.');
}
$f = json_decode(
    file_get_contents(__DIR__ . '/console-fixture.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$tech = $f['accounts']['TECHNICIAN'];
workspaceCheck(
    $pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00',
    'Connection timezone is not UTC',
);
workspaceCheck(
    !str_contains(
        workspaceReturnQuery($tech, 'view=cash&q[]=bad&url=https://example.test'),
        'example',
    ),
    'Unsafe return data accepted',
);
$manager = $f['accounts']['MANAGER'];
foreach ($f['accounts'] as $roleName => $roleAccount) {
    workspaceCheck(
        consoleView($roleAccount, []) === 'overview',
        $roleName . ' should land on its overview.',
    );
}
$admin = ['user_id' => 1, 'role' => 'ADMIN'];
workspaceCheck(consoleView($admin, []) === 'overview', 'Admin console fallback should remain an overview.');
workspaceCheck(
    consoleView($f['accounts']['COURIER'], ['view' => 'board']) === 'overview',
    'Courier must not open the repair board.',
);
workspaceCheck(
    consoleView($tech, ['view' => 'cash']) === 'overview',
    'Technician must not open the cash desk.',
);
workspaceCheck(
    consoleView($f['accounts']['INTAKE_AGENT'], ['view' => 'cash']) === 'overview',
    'Intake agent must not open the cash desk.',
);
$device = consoleDevice($pdo, $tech, $f['device']);
$ticket = (int) $device['ticket_id'];
$pdo->prepare('UPDATE ticket_devices SET repair_started_at=NULL WHERE ticket_device_id=?')->execute(
    [$f['device']],
);
foreach (['IN_PROGRESS', 'AWAITING_PARTS', 'IN_PROGRESS'] as $status) {
    $device = consoleDevice($pdo, $tech, $f['device']);
    consoleSave($pdo, $tech['user_id'], $f['device'], consoleVersion($device), [
        'action' => 'progress',
        'status' => $status,
    ]);
    $after = consoleDevice($pdo, $tech, $f['device']);
    $firstStart = $firstStart ?? $after['repair_started_at'];
    workspaceCheck(
        $firstStart !== null && $after['repair_started_at'] === $firstStart,
        'First start changed on resume',
    );
}
$created = [];
for ($i = 0; $i < 12; $i++) {
    $pdo->prepare(
        "INSERT INTO ticket_devices (ticket_id,child_sequence,item_code,device_type_snapshot,model_snapshot,reported_symptoms,condition_notes,accessory_checklist_completed,alternative_identifiers,repair_status,custody_state) VALUES(?,?,?,'Laptop','Workspace test','No power','Intact',1,'Test fixture','RECEIVED','AT_LOCATION')",
    )->execute([$ticket, 100 + $i, 'WORK-' . $i]);
    $created[] = (int) $pdo->lastInsertId();
}
for ($i = 0; $i < technicianActiveLimit() - 1; $i++) {
    $item = consoleDevice($pdo, $manager, $created[$i]);
    consoleSave($pdo, $manager['user_id'], $created[$i], consoleVersion($item), [
        'action' => 'assign',
        'technician' => (string) $tech['user_id'],
    ]);
}
$item = consoleDevice($pdo, $manager, $created[5]);
workspaceDenied(
    fn() => consoleSave($pdo, $manager['user_id'], $created[5], consoleVersion($item), [
        'action' => 'assign',
        'technician' => (string) $tech['user_id'],
    ]),
);
$pdo->prepare(
    "UPDATE ticket_devices SET repair_status='CANCELLED' WHERE ticket_device_id=?",
)->execute([$created[0]]);
consoleSave($pdo, $manager['user_id'], $created[5], consoleVersion($item), [
    'action' => 'assign',
    'technician' => (string) $tech['user_id'],
]);
workspaceCheck(
    technicianActiveCount($pdo, $tech['user_id']) === technicianActiveLimit(),
    'Cancelled work consumes capacity',
);
$pdo->prepare(
    'UPDATE ticket_devices SET updated_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY) WHERE ticket_device_id = ?',
)->execute([$created[1]]);
$active = consoleDevices($pdo, $manager, ['q' => 'WORK-']);
workspaceCheck(
    (int) $active['page']['rows'][0]['ticket_device_id'] === (int) $created[1],
    'Queue must show the oldest last update first',
);
$pdo->prepare(
    'UPDATE ticket_devices SET updated_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE ticket_device_id = ?',
)->execute([$created[2]]);
$board = consoleDevices($pdo, $manager, ['view' => 'board', 'q' => 'WORK-']);
workspaceCheck(
    (int) $board['page']['rows'][0]['ticket_device_id'] === (int) $created[2],
    'Status board must keep the most recently updated work first',
);
$pdo->prepare(
    "UPDATE ticket_devices
     SET repair_status='COMPLETED', updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
     WHERE ticket_device_id=?",
)->execute([$created[1]]);
$pdo->prepare(
    'UPDATE ticket_devices SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE ticket_device_id=?',
)->execute([$created[0]]);
$orderedHistory = consoleDevices($pdo, $manager, ['view' => 'history', 'q' => 'WORK-']);
workspaceCheck(
    count($orderedHistory['page']['rows']) === 2 &&
        (int) $orderedHistory['page']['rows'][0]['ticket_device_id'] === (int) $created[1] &&
        (int) $orderedHistory['page']['rows'][1]['ticket_device_id'] === (int) $created[0],
    'Repair history must keep the newest update first',
);
$history = consoleDevices($pdo, $manager, ['view' => 'history', 'q' => 'WORK-0']);
workspaceCheck(
    count($active['page']['rows']) === 10 && $active['page']['pages'] > 1,
    'Pagination failed',
);
workspaceCheck($history['page']['total'] === 1, 'Cancelled repair not archived');
workspaceCheck(
    consoleDevices($pdo, $manager, ['q' => 'WORK-0'])['page']['total'] === 0,
    'Archive leaks into queue',
);
// The demo ticket may not have a phone number. Add a deterministic synthetic
// contact so this test exercises portal-code issuance without changing app rules.
$testPhone = '0917' . str_pad((string) ($ticket % 10000000), 7, '0', STR_PAD_LEFT);
$pdo->prepare(
    "INSERT INTO customer_contacts
        (customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications)
     SELECT customer_id, 'PHONE', ?, ?, 1, 0
     FROM repair_tickets WHERE ticket_id = ?
     ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_primary = 1",
)->execute([$testPhone, $testPhone, $ticket]);
$phone = $pdo->prepare(
    "SELECT cc.normalized_value FROM customer_contacts cc JOIN repair_tickets t ON t.customer_id=cc.customer_id WHERE t.ticket_id=? AND cc.contact_type='PHONE' ORDER BY cc.is_primary DESC,cc.contact_id DESC LIMIT 1",
);
$phone->execute([$ticket]);
$pin = substr(preg_replace('/[^0-9]/', '', (string) $phone->fetchColumn()), -4);
$version = $pdo->prepare('SELECT tracking_token_hash FROM repair_tickets WHERE ticket_id=?');
$version->execute([$ticket]);
$code = replaceTrackingCode($pdo, $manager['user_id'], $ticket, $version->fetchColumn(), true);
workspaceCheck((bool) preg_match('/^[A-F0-9]{5}$/D', $code), 'Wrong code format');
workspaceCheck(publicRepair($pdo, $code, $pin) !== null, 'Correct PIN rejected');
workspaceCheck(
    publicRepair($pdo, $code, $pin === '0000' ? '1111' : '0000') === null &&
        publicRepair($pdo, $code) === null,
    'Bad PIN accepted',
);
$version->execute([$ticket]);
$newCode = replaceTrackingCode($pdo, $manager['user_id'], $ticket, $version->fetchColumn(), true);
workspaceCheck(
    $newCode !== $code && publicRepair($pdo, $code, $pin) === null,
    'Old code not revoked',
);
$pdo->prepare(
    "INSERT INTO users(full_name,email,password_hash,role) VALUES('Cashier Test','cashier.workspace@example.test',?,'CASHIER')",
)->execute([password_hash('WorkspaceDemo2026!', PASSWORD_DEFAULT)]);
$cashier = ['user_id' => (int) $pdo->lastInsertId(), 'role' => 'CASHIER'];
workspaceCheck(consoleView($cashier, []) === 'overview', 'Cashier should land on its overview.');
workspaceCheck(
    consoleView($cashier, ['view' => 'queue']) === 'overview',
    'Cashier must not open the repair queue.',
);
workspaceCheck(consoleView($cashier, ['view' => 'cash']) === 'cash', 'Cashier cash desk is unavailable.');
$pdo->prepare(
    'INSERT INTO user_branch_assignments(user_id,branch_id,is_default) VALUES(?,?,1)',
)->execute([$cashier['user_id'], $device['accountable_branch_id']]);
$pdo->prepare(
    "INSERT INTO repair_bills(ticket_id,version_no,bill_number,bill_status,total_amount,prepared_by_user_id,issued_at) VALUES(?,99,'WORK-BILL','ISSUED',1250,1,UTC_TIMESTAMP())",
)->execute([$ticket]);
workspaceCheck(
    consoleCashDesk($pdo, $cashier, ['q' => $newCode])['total'] > 0,
    'Cash search failed',
);
workspaceCheck(
    consoleCashDesk($pdo, ['user_id' => 999999, 'role' => 'CASHIER'], [])['total'] === 0,
    'Cash scope failed',
);
workspaceDenied(fn() => consoleDevice($pdo, $cashier, $f['device']));
workspaceDenied(fn() => consoleCashDesk($pdo, $tech, []));
workspaceCheck(count(consoleStartTrend($pdo, $tech)) === 7, 'Missing trend dates');
file_put_contents(
    __DIR__ . '/workspace-fixture.json',
    json_encode(['ticket' => $ticket, 'device' => $f['device'], 'code' => $newCode, 'pin' => $pin]),
);
echo "PASS: repair start/resume, capacity, archives, pagination, code/PIN/revocation, cashier scope and trends.\n";
