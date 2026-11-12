<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/rules.php';
require_once __DIR__ . '/../app/intake/drafts.php';
require_once __DIR__ . '/../app/console/repository.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Use a disposable test database.');
}
function verify(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function denied(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $error) {
        return;
    }
    throw new RuntimeException($message);
}
$branch = (int) $pdo->query('SELECT MIN(branch_id) FROM branches')->fetchColumn();
$otherBranch = (int) $pdo->query('SELECT MAX(branch_id) FROM branches')->fetchColumn();
$accounts = [];
foreach (['MANAGER', 'INTAKE_AGENT', 'TECHNICIAN', 'COURIER', 'VIEWER'] as $role) {
    $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)',
    )->execute([
        'Console ' . $role,
        strtolower($role) . '.console@example.test',
        password_hash('ConsoleDemo2026!', PASSWORD_DEFAULT),
        $role,
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO user_branch_assignments (user_id, branch_id, is_default) VALUES (?, ?, 1)',
    )->execute([$id, $branch]);
    $accounts[$role] = ['user_id' => $id, 'role' => $role];
}
$admin = ['user_id' => 1, 'role' => 'ADMIN'];
$deviceId = (int) $pdo
    ->query(
        "SELECT d.ticket_device_id FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
    WHERE t.record_stage = 'ISSUED' AND t.accountable_branch_id = $branch AND t.case_state = 'OPEN' LIMIT 1",
    )
    ->fetchColumn();
verify($deviceId > 0, 'Issued fixture exists');
$pdo->prepare(
    "UPDATE ticket_devices SET repair_status = 'RECEIVED', custody_state = 'AT_LOCATION', assigned_technician_id = NULL WHERE ticket_device_id = ?",
)->execute([$deviceId]);
$device = consoleDevice($pdo, $admin, $deviceId);
consoleSave($pdo, $accounts['MANAGER']['user_id'], $deviceId, consoleVersion($device), [
    'action' => 'assign',
    'technician' => (string) $accounts['TECHNICIAN']['user_id'],
]);
$tech = $accounts['TECHNICIAN'];
$device = consoleDevice($pdo, $tech, $deviceId);
consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
    'action' => 'progress',
    'status' => 'DIAGNOSING',
    'note' => 'Private console test note',
]);
denied(
    fn() => consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'DIAGNOSING',
        'note' => 'Replay',
    ]),
    'Stale write rejected',
);
$device = consoleDevice($pdo, $tech, $deviceId);
denied(
    fn() => consoleSave($pdo, $accounts['VIEWER']['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'DIAGNOSING',
        'note' => 'Forbidden',
    ]),
    'Viewer write rejected',
);
denied(
    fn() => consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'COMPLETED',
    ]),
    'Cannot skip handover',
);
denied(
    fn() => consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'assign',
        'technician' => '1',
    ]),
    'Technician cannot assign',
);
$pdo->prepare(
    "UPDATE repair_quotes SET quote_status = 'DRAFT' WHERE ticket_device_id = ?",
)->execute([$deviceId]);
denied(
    fn() => consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'IN_PROGRESS',
    ]),
    'Customer approval required',
);
$outside = (int) $pdo
    ->query(
        "SELECT d.ticket_device_id FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id WHERE t.record_stage = 'ISSUED' AND t.accountable_branch_id = $otherBranch LIMIT 1",
    )
    ->fetchColumn();
denied(fn() => consoleDevice($pdo, $accounts['MANAGER'], $outside), 'Cross-branch details denied');
denied(fn() => consoleDevice($pdo, $tech, $outside), 'Unassigned technician denied');
$scoped = loadDashboardData($pdo, [], $accounts['MANAGER']['user_id']);
$all = loadDashboardData($pdo, [], 1);
verify(
    $scoped['pages']['queue']['total'] < $all['pages']['queue']['total'],
    'Dashboard scoped to assigned branch',
);
$noBranches = loadDashboardData($pdo, [], 999999);
verify(
    $noBranches['pages']['queue']['total'] === 0 && $noBranches['metrics']['activeRepairs'] === 0,
    'Empty scope fails closed',
);

// A late audit failure must undo both status and history changes.
$pdo->exec(
    "CREATE TRIGGER console_audit_failure BEFORE INSERT ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test rollback'",
);
try {
    try {
        consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
            'action' => 'progress',
            'status' => 'AWAITING_PARTS',
            'note' => 'Must roll back',
        ]);
        throw new RuntimeException('Expected rollback error');
    } catch (PDOException $error) {
    }
} finally {
    $pdo->exec('DROP TRIGGER console_audit_failure');
}
verify(
    consoleDevice($pdo, $tech, $deviceId)['repair_status'] === 'DIAGNOSING',
    'Status rolled back',
);

// An old approved quote must not authorize a newer unapproved revision.
$pdo->prepare(
    "UPDATE repair_quotes SET quote_status = 'APPROVED' WHERE ticket_device_id = ?",
)->execute([$deviceId]);
$nextVersion = $pdo->prepare(
    'SELECT COALESCE(MAX(version_no), 0) + 1 FROM repair_quotes WHERE ticket_device_id = ?',
);
$nextVersion->execute([$deviceId]);
$pdo->prepare(
    "INSERT INTO repair_quotes (ticket_device_id, version_no, quote_status, prepared_by_user_id) VALUES (?, ?, 'DRAFT', 1)",
)->execute([$deviceId, (int) $nextVersion->fetchColumn()]);
$newQuote = (int) $pdo->lastInsertId();
$device = consoleDevice($pdo, $tech, $deviceId);
denied(
    fn() => consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
        'action' => 'progress',
        'status' => 'IN_PROGRESS',
    ]),
    'Latest quote must be approved',
);
$pdo->prepare("UPDATE repair_quotes SET quote_status = 'APPROVED' WHERE quote_id = ?")->execute([
    $newQuote,
]);
consoleSave($pdo, $tech['user_id'], $deviceId, consoleVersion($device), [
    'action' => 'progress',
    'status' => 'IN_PROGRESS',
    'note' => 'Approved repair started',
]);
verify(
    consoleDevice($pdo, $tech, $deviceId)['repair_status'] === 'IN_PROGRESS',
    'Approved repair can start',
);
$pdo->prepare(
    "UPDATE ticket_devices SET repair_status = 'DIAGNOSING' WHERE ticket_device_id = ?",
)->execute([$deviceId]);

$values = intakeDefaults(intakeBranches($pdo, $accounts['INTAKE_AGENT']['user_id']));
$values = array_replace($values, [
    'customer_name' => 'Quick Menu Test',
    'contact_value' => '09171234567',
    'device_type' => 'Laptop',
    'make' => 'Acer',
]);
[$quick, $errors] = intakeQuickValues($values, [
    'quick_choices' => '1',
    'problems' => ['Will not turn on'],
    'conditions' => ['No visible damage'],
]);
verify(
    !$errors &&
        !intakeValidate($pdo, $quick, intakeBranches($pdo, $accounts['INTAKE_AGENT']['user_id'])),
    'Menus replace mandatory typing',
);
$draft = intakeSaveDraft($pdo, $quick, $accounts['INTAKE_AGENT']['user_id']);
verify($draft > 0, 'Intake agent saves draft');
denied(fn() => intakeSaveDraft($pdo, $quick, $tech['user_id']), 'Technician cannot create intake');
[, $errors] = intakeQuickValues($values, [
    'quick_choices' => '1',
    'problems' => ['Other'],
    'conditions' => ['No visible damage'],
]);
verify(count($errors) > 0, 'Other needs description');
[, $errors] = intakeQuickValues($values, [
    'quick_choices' => '1',
    'problems' => ['No display'],
    'conditions' => ['No visible damage', 'Cracked screen'],
]);
verify(count($errors) > 0, 'Contradictory condition rejected');
[, $errors] = intakeQuickValues($values, [
    'quick_choices' => '1',
    'problems' => [['bad']],
    'conditions' => [],
]);
verify(count($errors) > 0, 'Malformed choices rejected');
verify(
    intakeChoiceState("Selected: Other\r\nDetails: Custom fault", intakeProblemChoices())[
        'selected'
    ] === ['Other'],
    'Browser line endings preserved',
);
$state = intakeChoiceState($quick['reported_symptoms'], intakeProblemChoices());
verify($state['selected'] === ['Will not turn on'], 'Choices reopen correctly');
verify(
    intakeChoiceState('Old custom note', intakeProblemChoices())['details'] === 'Old custom note',
    'Legacy notes preserved',
);
file_put_contents(
    __DIR__ . '/console-fixture.json',
    json_encode(['device' => $deviceId, 'outside' => $outside, 'accounts' => $accounts]),
);
echo "PASS: role/branch scope, assignments, stages, approval gate, stale writes, rollback, dashboard scope, intake menus and intake-role guards.\n";
