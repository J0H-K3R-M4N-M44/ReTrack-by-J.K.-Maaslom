<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/public/tracking.php';
require_once __DIR__ . '/../app/console/repository.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Use a disposable database and run staff-consoles.php first.');
}
function checkPublic(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function rejectPublic(callable $action): void
{
    try {
        $action();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException('Expected access rejection.');
}
$fixture = json_decode(
    file_get_contents(__DIR__ . '/console-fixture.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$manager = $fixture['accounts']['MANAGER'];
$tech = $fixture['accounts']['TECHNICIAN'];
$device = consoleDevice($pdo, $manager, $fixture['device']);
$ticketId = (int) $device['ticket_id'];
$query = $pdo->prepare('SELECT tracking_token_hash FROM repair_tickets WHERE ticket_id = ?');
$query->execute([$ticketId]);
$code = replaceTrackingCode($pdo, $manager['user_id'], $ticketId, $query->fetchColumn());
$repair = publicRepair($pdo, $code);
checkPublic($repair !== null, 'Private code opens issued repair');
checkPublic(
    publicRepair($pdo, $repair['ticket_code']) === null,
    'Receipt numbers do not grant access',
);
checkPublic(publicRepair($pdo, str_repeat('0', 64)) === null, 'Unknown token denied');
foreach ([$fixture['accounts']['VIEWER'], $tech] as $deniedUser) {
    rejectPublic(
        fn() => replaceTrackingCode($pdo, $deniedUser['user_id'], $ticketId, hash('sha256', $code)),
    );
}
$outside = $pdo
    ->query(
        'SELECT ticket_id, tracking_token_hash FROM repair_tickets WHERE ticket_id = (SELECT ticket_id FROM ticket_devices WHERE ticket_device_id = ' .
            (int) $fixture['outside'] .
            ')',
    )
    ->fetch();
rejectPublic(
    fn() => replaceTrackingCode(
        $pdo,
        $manager['user_id'],
        (int) $outside['ticket_id'],
        $outside['tracking_token_hash'],
    ),
);
$newCode = replaceTrackingCode($pdo, $manager['user_id'], $ticketId, hash('sha256', $code));
checkPublic(publicRepair($pdo, $code) === null, 'Old tracking code revoked');
rejectPublic(
    fn() => replaceTrackingCode($pdo, $manager['user_id'], $ticketId, hash('sha256', $code)),
);
$device = consoleDevice($pdo, $tech, $fixture['device']);
consoleSave($pdo, $tech['user_id'], $fixture['device'], consoleVersion($device), [
    'action' => 'progress',
    'status' => $device['repair_status'],
    'note' => 'PRIVATE_SENTINEL_NEVER_PUBLIC',
    'public_message' => 'Your device is being assessed. <script>alert(1)</script>',
]);
$repair = publicRepair($pdo, $newCode);
$json = json_encode($repair);
checkPublic(!str_contains($json, 'PRIVATE_SENTINEL'), 'Private note leaked');
checkPublic(!str_contains($json, 'internal_note'), 'Private field returned');
checkPublic(str_contains($json, 'Your device is being assessed.'), 'Customer update missing');
checkPublic(!isset($repair['devices'][0]['serial_number_snapshot']), 'Serial number leaked');
$filtered = consoleDevices($pdo, $manager, ['status' => 'DIAGNOSING']);
foreach ($filtered['page']['rows'] as $row) {
    checkPublic($row['repair_status'] === 'DIAGNOSING', 'Status filter ignored');
}
checkPublic(consoleStatusFilter(['status' => ['bad']]) === '', 'Malformed filter accepted');
$url = consolePageUrl(
    ['q' => 'Laptop', 'status' => 'DIAGNOSING', 'page' => '2'],
    'draft_page',
    2,
    'drafts',
);
checkPublic(
    str_contains($url, 'q=Laptop') &&
        str_contains($url, 'page=2') &&
        str_contains($url, 'status=DIAGNOSING'),
    'Pagination lost state',
);
file_put_contents(
    __DIR__ . '/public-fixture.json',
    json_encode(['ticket' => $ticketId, 'code' => $newCode]),
);
echo "PASS: public privacy, access-code revocation, stale sharing, role/branch boundaries, customer updates, queue filtering and pagination.\n";
