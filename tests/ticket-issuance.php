<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/issuance.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Use a disposable repairtrack_test_ database.');
}
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function mustReject(callable $action, string $message): void
{
    try {
        $action();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($message);
}
function newIssueDraft(PDO $pdo, string $serial): int
{
    $values = array_replace(intakeDefaults(intakeBranches($pdo, 1)), [
        'customer_id' => '1',
        'device_type' => 'Laptop',
        'make' => 'Demo',
        'model' => 'Test',
        'serial_number' => $serial,
        'reported_symptoms' => 'No power',
        'condition_notes' => 'No visible damage',
    ]);
    return intakeSaveDraft($pdo, $values, 1);
}
function testEvidence(): array
{
    $files = [];
    foreach (['photo', 'signed_form'] as $kind) {
        $name = bin2hex(random_bytes(24)) . '.png';
        file_put_contents(
            intakeEvidencePath($name),
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5FoAAAAASUVORK5CYII=',
            ),
        );
        $files[$kind] = ['name' => $name] + intakeInspectImage(intakeEvidencePath($name));
    }
    return $files;
}
$id = newIssueDraft($pdo, 'ISSUE-TEST-001');
$draft = intakeIssueDraft($pdo, $id, 1);
$version = intakeDraftVersion($draft);
$locations = intakeLocations($pdo, (int) $draft['intake_branch_id']);
$values = intakeIssueValues([
    'location_id' => (string) $locations[0]['location_id'],
    'terms_version' => 'TEST-ONLY-v1',
    'checklist_confirmed' => '1',
    'signature_confirmed' => '1',
    'duplicate_confirmed' => '1',
    'charger' => '1',
]);
$evidence = testEvidence();
expect(intakeValidateIssue($pdo, $draft, $values, 1) === [], 'Valid issuance checks');
foreach (
    [
        'location_id' => '9999',
        'signature_confirmed' => '',
        'checklist_confirmed' => '',
        'duplicate_confirmed' => '',
        'terms_version' => '',
    ]
    as $key => $bad
) {
    mustReject(
        fn() => intakeIssueTicket(
            $pdo,
            $id,
            1,
            $version,
            array_replace($values, [$key => $bad]),
            $evidence,
        ),
        'Invalid ' . $key . ' accepted',
    );
}
mustReject(
    fn() => intakeIssueTicket($pdo, $id, 2, $version, $values, $evidence),
    'Non-admin accepted',
);
mustReject(
    fn() => intakeIssueTicket($pdo, $id, 1, 'stale', $values, $evidence),
    'Stale review accepted',
);
mustReject(
    fn() => intakeIssueTicket($pdo, $id, 1, $version, $values, []),
    'Missing evidence accepted',
);
mustReject(fn() => intakeEvidencePath('../config.php'), 'Traversal accepted');
$fake = intakePrivateDirectory() . '/fake.png';
file_put_contents($fake, '<?php echo "not an image";');
mustReject(fn() => intakeInspectImage($fake), 'Renamed PHP accepted');
unlink($fake);
// A late audit failure must undo all earlier ticket/device/evidence inserts.
$pdo->exec(
    "CREATE TRIGGER test_reject_issue BEFORE INSERT ON audit_events FOR EACH ROW BEGIN IF NEW.action = 'TICKET_ISSUED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected failure'; END IF; END",
);
try {
    mustReject(
        fn() => intakeIssueTicket($pdo, $id, 1, $version, $values, $evidence),
        'Injected failure accepted',
    );
} finally {
    $pdo->exec('DROP TRIGGER test_reject_issue');
}
expect(intakeDraftVersion(intakeIssueDraft($pdo, $id, 1)) === $version, 'Ticket/device rollback');
foreach (
    [
        'ticket_accessory_items',
        'ticket_device_photos',
        'ticket_device_acknowledgments',
        'ticket_status_events',
    ]
    as $table
) {
    $query = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ticket_device_id = ?');
    $query->execute([$draft['ticket_device_id']]);
    expect((int) $query->fetchColumn() === 0, 'Child records rolled back: ' . $table);
}
$code = intakeIssueTicket($pdo, $id, 1, $version, $values, $evidence);
expect((bool) preg_match('/^RT-[A-F0-9]{12}$/', $code), 'Ticket reference created');
$query = $pdo->prepare(
    'SELECT record_stage, accountable_branch_id, tracking_token_hash FROM repair_tickets WHERE ticket_id = ?',
);
$query->execute([$id]);
$issued = $query->fetch();
expect(
    $issued['record_stage'] === 'ISSUED' && strlen($issued['tracking_token_hash']) === 64,
    'Ticket issued with opaque hash',
);
$query = $pdo->prepare(
    'SELECT repair_status, item_code, custody_state FROM ticket_devices WHERE ticket_id = ?',
);
$query->execute([$id]);
$device = $query->fetch();
expect(
    $device['repair_status'] === 'RECEIVED' &&
        $device['item_code'] === $code . '-01' &&
        $device['custody_state'] === 'AT_LOCATION',
    'Device received at location',
);
$query = $pdo->prepare('SELECT COUNT(*) FROM ticket_intake_gate_events WHERE ticket_id = ?');
$query->execute([$id]);
expect((int) $query->fetchColumn() === 9, 'Nine gate records');
mustReject(
    fn() => intakeIssueTicket($pdo, $id, 1, $version, $values, $evidence),
    'Repeat issue accepted',
);
$duplicateId = newIssueDraft($pdo, 'issue test 001');
$duplicate = intakeIssueDraft($pdo, $duplicateId, 1);
mustReject(
    fn() => intakeIssueTicket(
        $pdo,
        $duplicateId,
        1,
        intakeDraftVersion($duplicate),
        $values,
        $evidence,
    ),
    'Normalized same-day duplicate accepted',
);
$noSerialId = newIssueDraft($pdo, '');
$noSerial = intakeIssueDraft($pdo, $noSerialId, 1);
expect(
    intakeValidateIssue($pdo, $noSerial, $values, 1) !== [],
    'Missing alternate identity rejected',
);
// Save a fresh draft for browser issuance tests.
$browserId = newIssueDraft($pdo, 'BROWSER-ISSUE-001');
file_put_contents(__DIR__ . '/browser-issue-id.txt', (string) $browserId);
echo "PASS: issue gates, evidence checks, stale/access rejection, complete rollback, issuance, repeat and duplicate protection.\n";

// A separate administrator can sign in but must not access this branch's evidence.
$otherEmail = 'other-branch-admin@example.test';
$pdo->prepare(
    "INSERT INTO users (full_name, email, password_hash, role) VALUES ('Other Branch Admin', ?, ?, 'ADMIN')",
)->execute([$otherEmail, password_hash('DemoRepair2026!', PASSWORD_DEFAULT)]);
$otherId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO user_branch_assignments (user_id, branch_id, is_default) SELECT ?, branch_id, 0 FROM branches WHERE branch_id <> ? LIMIT 1',
)->execute([$otherId, $draft['intake_branch_id']]);
