<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/quotes/repository.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Disposable test database required.');
}
function quoteCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function quoteDenied(callable $action): void
{
    try {
        $action();
    } catch (DomainException $error) {
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
$id = $f['device'];
$tech = $f['accounts']['TECHNICIAN'];
$manager = $f['accounts']['MANAGER'];
$intake = $f['accounts']['INTAKE_AGENT'];
// Reset only disposable fixture state; normal application users cannot undo a start.
$pdo->prepare(
    "UPDATE ticket_devices SET repair_status='DIAGNOSING', repair_started_at=NULL, custody_state='AT_LOCATION' WHERE ticket_device_id=?",
)->execute([$id]);
$input = [
    'labor_description' => 'Diagnose and replace cooling fan <script>example</script>',
    'labor_amount' => '125.15',
    'parts_description' => 'Fan estimate',
    'parts_amount' => '200.20',
    'other_amount' => '0',
];
quoteCheck(quoteValues($input)['total'] === '325.35', 'Money rounding failed');
foreach (['-1', '1.234', '1e3', '10000000', 'NaN'] as $bad) {
    quoteDenied(fn() => quoteCents($bad));
}
quoteDenied(fn() => quoteValues(['labor_description' => '', 'labor_amount' => '1']));
quoteDenied(fn() => quoteDevice($pdo, $f['accounts']['VIEWER'], $id));
quoteDenied(fn() => quoteDevice($pdo, $manager, $f['outside']));
$expected = quoteVersion(quoteLatest($pdo, $id));
quoteDenied(fn() => quoteSave($pdo, $intake['user_id'], $id, $expected, 'draft', $input));
quoteSave($pdo, $tech['user_id'], $id, $expected, 'draft', $input);
$draft = quoteLatest($pdo, $id);
quoteCheck(
    $draft['quote_status'] === 'DRAFT' && $draft['total_amount'] === '325.35',
    'Draft not persisted',
);
quoteDenied(fn() => quoteSave($pdo, $manager['user_id'], $id, $expected, 'draft', $input));
$decision = [
    'decision' => 'APPROVED',
    'customer_name' => 'Fixture Customer',
    'reference' => 'Counter conversation; confirmed the displayed revision.',
    'confirm' => '1',
];
quoteDenied(
    fn() => quoteSave($pdo, $manager['user_id'], $id, quoteVersion($draft), 'decision', $decision),
);
quoteSave($pdo, $tech['user_id'], $id, quoteVersion($draft), 'share', ['confirm' => '1']);
$pending = quoteLatest($pdo, $id);
quoteDenied(
    fn() => quoteSave($pdo, $tech['user_id'], $id, quoteVersion($pending), 'decision', $decision),
);
quoteSave(
    $pdo,
    $intake['user_id'],
    $id,
    quoteVersion($pending),
    'decision',
    array_replace($decision, ['decision' => 'DECLINED']),
);
$device = consoleDevice($pdo, $tech, $id);
quoteDenied(
    fn() => consoleSave($pdo, $tech['user_id'], $id, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'IN_PROGRESS',
    ]),
);
$declined = quoteLatest($pdo, $id);
quoteSave($pdo, $manager['user_id'], $id, quoteVersion($declined), 'draft', $input);
$draft = quoteLatest($pdo, $id);
quoteSave($pdo, $manager['user_id'], $id, quoteVersion($draft), 'share', ['confirm' => '1']);
$pdo->prepare(
    'UPDATE repair_quotes SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE quote_id=?',
)->execute([$draft['quote_id']]);
$expired = quoteLatest($pdo, $id);
quoteDenied(
    fn() => quoteSave(
        $pdo,
        $manager['user_id'],
        $id,
        quoteVersion($expired),
        'decision',
        $decision,
    ),
);
quoteSave($pdo, $manager['user_id'], $id, quoteVersion($expired), 'draft', $input);
quoteSave($pdo, $manager['user_id'], $id, quoteVersion(quoteLatest($pdo, $id)), 'share', [
    'confirm' => '1',
]);
$pending = quoteLatest($pdo, $id);
quoteSave($pdo, $manager['user_id'], $id, quoteVersion($pending), 'decision', $decision);
quoteDenied(
    fn() => quoteSave(
        $pdo,
        $manager['user_id'],
        $id,
        quoteVersion($pending),
        'decision',
        $decision,
    ),
);
$device = consoleDevice($pdo, $tech, $id);
consoleSave($pdo, $tech['user_id'], $id, consoleVersion($device), [
    'action' => 'progress',
    'status' => 'IN_PROGRESS',
]);
quoteCheck(
    consoleDevice($pdo, $tech, $id)['repair_started_at'] !== null,
    'Approved repair did not start',
);
quoteDenied(
    fn() => quoteSave(
        $pdo,
        $manager['user_id'],
        $id,
        quoteVersion(quoteLatest($pdo, $id)),
        'draft',
        $input,
    ),
);
$query = $pdo->prepare('SELECT decision FROM repair_quote_decisions WHERE quote_id=?');
$query->execute([$declined['quote_id']]);
quoteCheck($query->fetchColumn() === 'DECLINED', 'Previous decision lost');
// Leave a diagnosable fixture for the browser's independent approval workflow.
$pdo->prepare(
    "UPDATE ticket_devices SET repair_status='DIAGNOSING', repair_started_at=NULL WHERE ticket_device_id=?",
)->execute([$id]);
echo "PASS: exact money, scope, draft/revision, stale writes, expiry, rejection, approval/start and immutable decisions.\n";
