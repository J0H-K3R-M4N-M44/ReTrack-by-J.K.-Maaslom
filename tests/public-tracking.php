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
// Older seed customers only have email; short-code access requires a recorded phone.
$pdo->prepare(
    "INSERT INTO customer_contacts
    (customer_id, contact_type, contact_value, normalized_value, is_primary)
    SELECT customer_id, 'PHONE', '09171234567', '09171234567', 0
    FROM repair_tickets WHERE ticket_id = ?",
)->execute([$ticketId]);

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
// Customer estimates are read-only and resolve to the newest revision only.
// Create fictional versions in the disposable fixture; never expose staff fields.
// Seeded demo devices may already have quote history. Append new versions instead
// of relying on a blank fixture or overwriting an existing customer decision.
$versionQuery = $pdo->prepare(
    'SELECT COALESCE(MAX(version_no), 0) FROM repair_quotes WHERE ticket_device_id=?',
);
$versionQuery->execute([$fixture['device']]);
$firstVersion = (int) $versionQuery->fetchColumn() + 1;
$pdo->prepare(
    "INSERT INTO repair_quotes
    (ticket_device_id, version_no, quote_status, labor_amount, parts_amount,
     other_amount, total_amount, prepared_by_user_id)
    VALUES (?, ?, 'DRAFT', 325.35, 0, 0, 325.35, ?)",
)->execute([$fixture['device'], $firstVersion, $manager['user_id']]);
$firstQuoteId = (int) $pdo->lastInsertId();
$draftView = publicRepair($pdo, $newCode);
checkPublic($draftView['devices'][0]['estimate'] === null, 'Draft estimate leaked');

$pdo->prepare(
    "UPDATE repair_quotes SET quote_status='AWAITING_CUSTOMER',
     sent_at=UTC_TIMESTAMP(), expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
     WHERE quote_id=?",
)->execute([$firstQuoteId]);
$sharedView = publicRepair($pdo, $newCode);
checkPublic($sharedView['devices'][0]['estimate']['total'] === '325.35', 'Shared total absent');
checkPublic($sharedView['devices'][0]['estimate']['revision'] === $firstVersion, 'Wrong shared version');
checkPublic(!$sharedView['devices'][0]['estimate']['expired'], 'Future estimate treated as expired');
checkPublic(str_contains(publicNextStep($sharedView['devices'][0]), 'decision'), 'No response instruction');

$pdo->prepare(
    'UPDATE repair_quotes SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE quote_id=?',
)->execute([$firstQuoteId]);
$expiredView = publicRepair($pdo, $newCode);
checkPublic($expiredView['devices'][0]['estimate']['expired'], 'Expired response not identified');
checkPublic(str_contains(publicNextStep($expiredView['devices'][0]), 'updated estimate'),
    'Expired quote action misleading');

$pdo->prepare("UPDATE repair_quotes SET quote_status='DECLINED' WHERE quote_id=?")
    ->execute([$firstQuoteId]);
$declinedView = publicRepair($pdo, $newCode);
checkPublic(str_contains(publicNextStep($declinedView['devices'][0]), 'declined'),
    'Declined estimate lacks next action');

$pdo->prepare(
    "INSERT INTO repair_quotes
    (ticket_device_id, version_no, quote_status, labor_amount, parts_amount,
     other_amount, total_amount, prepared_by_user_id)
    VALUES (?, ?, 'DRAFT', 99, 0, 0, 99, ?)",
)->execute([$fixture['device'], $firstVersion + 1, $manager['user_id']]);
$secondQuoteId = (int) $pdo->lastInsertId();
$newDraftView = publicRepair($pdo, $newCode);
checkPublic($newDraftView['devices'][0]['estimate'] === null,
    'Latest private draft should hide the obsolete estimate');
checkPublic(!str_contains(json_encode($newDraftView, JSON_THROW_ON_ERROR), '325.35'),
    'Superseded estimate amount leaked');
checkPublic(!str_contains(json_encode($newDraftView, JSON_THROW_ON_ERROR), '99.00'),
    'Latest draft price leaked');

$pdo->prepare(
    "UPDATE repair_quotes SET quote_status='AWAITING_CUSTOMER',
     sent_at=UTC_TIMESTAMP(), expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
     WHERE quote_id=?",
)->execute([$secondQuoteId]);
$latestView = publicRepair($pdo, $newCode);
checkPublic($latestView['devices'][0]['estimate']['total'] === '99.00',
    'Newest shared estimate missing');
checkPublic(!str_contains(json_encode($latestView, JSON_THROW_ON_ERROR), '325.35'),
    'Old shared estimate leaked when revised');
checkPublic(publicRepair($pdo, str_repeat('0', 64)) === null,
    'Unknown tracking secret obtained estimate');
checkPublic(publicRepair($pdo, $code) === null,
    'Revoked tracking secret obtained estimate');
checkPublic(!isset($latestView['devices'][0]['estimate']['prepared_by_user_id']) &&
    !isset($latestView['devices'][0]['estimate']['quote_id']),
    'Staff estimate metadata leaked');
checkPublic(publicNextStep(['custody_state' => 'WITH_CUSTOMER']) !== '',
    'Returned device lacks next action');

$partsWaiting = [
    'custody_state' => 'AT_LOCATION',
    'repair_status' => 'AWAITING_PARTS',
    'estimate' => null,
];
checkPublic(publicStageLabel($partsWaiting) === 'Waiting for parts',
    'Parts wait was presented as a generic assessment stage');
checkPublic(str_contains(publicNextStep($partsWaiting), 'required parts'),
    'Parts wait lacks a parts-specific customer action');

$customerDecisionWaiting = [
    'custody_state' => 'AT_LOCATION',
    'repair_status' => 'AWAITING_CUSTOMER_APPROVAL',
    'estimate' => null,
];
checkPublic(publicStageLabel($customerDecisionWaiting) === 'Waiting for customer decision',
    'Customer approval wait was not clearly labelled');
checkPublic(str_contains(publicNextStep($customerDecisionWaiting), 'Contact the branch'),
    'Customer approval wait lacks a branch contact action');

file_put_contents(
    __DIR__ . '/public-fixture.json',
    json_encode(['ticket' => $ticketId, 'code' => $newCode]),
);
echo "PASS: public privacy, access-code revocation, stale sharing, role/branch boundaries, customer updates, queue filtering and pagination.\n";
