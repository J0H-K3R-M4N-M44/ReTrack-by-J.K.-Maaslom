<?php
declare(strict_types=1);

// Only included from payment-pickup.php on a disposable repairtrack_test_* DB.
// Simulates a mixed-branch ticket WITHOUT changing application authorization.
// This is not a partial transfer/approval implementation.
if (PHP_SAPI !== 'cli' || !isset($pdo, $staff, $ticket, $device, $branch, $manager, $run)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/console/scopes.php';

$recipientBranch = insertFixture($pdo, 'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run . 'SCOPE', 'Receiving repair branch fixture']);
$recipientLocation = insertFixture($pdo,
    "INSERT INTO storage_locations (branch_id,location_code,location_name,location_type)
     VALUES (?,'SCOPE-BENCH','Receiving workbench','WORKBENCH')", [$recipientBranch]);

$actorAt = static function (string $role, string $suffix) use ($pdo, $run, $recipientBranch): array {
    $id = insertFixture($pdo,
        'INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,?)',
        ['Scope test ' . $suffix, $run . '-' . $suffix . '@example.test', 'unused', $role]);
    $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')
        ->execute([$id, $recipientBranch]);
    return ['user_id' => $id, 'role' => $role];
};
$receivingManager = $actorAt('MANAGER', 'manager');
$receivingCashier = $actorAt('CASHIER', 'cashier');
$receivingTechnician = $actorAt('TECHNICIAN', 'technician');
$unassignedTechnician = $actorAt('TECHNICIAN', 'unassigned');

// The main fixture inserts raw SQL rather than going through intake issuance.
$pdo->prepare('UPDATE ticket_devices SET repair_branch_id=? WHERE ticket_device_id=?')
    ->execute([$branch, $device]);
$sibling = insertFixture($pdo,
    "INSERT INTO ticket_devices
     (ticket_id,child_sequence,item_code,device_type_snapshot,reported_symptoms,
      condition_notes,serial_number_snapshot,accessory_checklist_completed,
      repair_status,custody_state,physical_location_id,repair_branch_id,assigned_technician_id)
     VALUES (?,2,?,'Laptop','No signal','Condition reviewed',?,1,
       'RECEIVED','AT_LOCATION',?,?,?)",
    [$ticket, $run . '-02', $run . '-sibling', $recipientLocation, $recipientBranch,
        $receivingTechnician['user_id']]);

// Queries below are only inspections of hypothetical scope builders. The app
// still calls consoleScope() for normal repair access.
$visibleDevices = static function (array $scope) use ($pdo, $ticket): array {
    [$where, $params] = $scope;
    $stmt = $pdo->prepare("SELECT d.ticket_device_id FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.ticket_id=? AND $where ORDER BY d.ticket_device_id");
    $stmt->execute([$ticket, ...$params]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
};
$visibleTicket = static function (array $scope) use ($pdo, $ticket): bool {
    [$where, $params] = $scope;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets t
        WHERE t.ticket_id=? AND $where");
    $stmt->execute([$ticket, ...$params]);
    return (int) $stmt->fetchColumn() === 1;
};
expectResult($visibleDevices(consoleCurrentDeviceScope($manager)) === [$device, $sibling],
    'Legacy source branch device scope changed before rollout.');
expectResult($visibleDevices(consoleCurrentDeviceScope($receivingManager)) === [],
    'Receiving manager gained existing device access before cutover.');
expectResult($visibleDevices(consoleProposedRepairScope($manager)) === [$device],
    'Proposed origin repair scope exposed a device owned by the receiving branch.');
expectResult($visibleDevices(consoleProposedRepairScope($receivingManager)) === [$sibling],
    'Proposed receiving repair scope exposed sibling under the ticket branch.');
expectResult($visibleDevices(consoleProposedRepairScope($receivingTechnician)) === [$sibling] &&
    $visibleDevices(consoleProposedRepairScope($unassignedTechnician)) === [],
    'Proposed technician scope ignored specific device assignment.');

expectResult($visibleTicket(consoleTicketFinanceScope($staff[1])),
    'Origin cashier could not see its ticket-level financial scope.');
expectResult($visibleTicket(consoleTicketFinanceScope($manager)),
    'Origin manager could not see ticket-level financial scope.');
expectResult(!$visibleTicket(consoleTicketFinanceScope($receivingCashier)) &&
    !$visibleTicket(consoleTicketFinanceScope($receivingManager)),
    'Device repair ownership exposed ticket financial records to destination staff.');
expectRefusal(fn() => consoleDevice($pdo, $receivingManager, $sibling),
    'outside your assigned work');
expectRefusal(fn() => cashPageTicket($pdo, $receivingManager, $ticket),
    'outside your assigned branches');
expectResult(count(cashPageTicket($pdo, $manager, $ticket)['devices']) === 2,
    'Ticket financial authority no longer includes all of its devices.');

// Fail closed when an operator is deactivated, membership is removed or a
// branch is disabled. Every change is confined to disposable test fixtures.
$pdo->prepare('UPDATE users SET is_active=0 WHERE user_id=?')
    ->execute([$receivingManager['user_id']]);
expectResult($visibleDevices(consoleProposedRepairScope($receivingManager)) === [],
    'Deactivated manager retained proposed device access.');
$pdo->prepare('UPDATE users SET is_active=1 WHERE user_id=?')
    ->execute([$receivingManager['user_id']]);

$pdo->prepare('DELETE FROM user_branch_assignments WHERE user_id=? AND branch_id=?')
    ->execute([$receivingManager['user_id'], $recipientBranch]);
expectResult($visibleDevices(consoleProposedRepairScope($receivingManager)) === [],
    'Revoked branch assignment retained proposed device access.');
$pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)')
    ->execute([$receivingManager['user_id'], $recipientBranch]);

$pdo->prepare('UPDATE branches SET is_active=0 WHERE branch_id=?')
    ->execute([$recipientBranch]);
expectResult($visibleDevices(consoleProposedRepairScope($receivingManager)) === [] &&
    !$visibleTicket(consoleTicketFinanceScope($receivingCashier)),
    'Inactive branch retained device or ticket-finance access.');
$pdo->prepare('UPDATE branches SET is_active=1 WHERE branch_id=?')
    ->execute([$recipientBranch]);

$pdo->prepare('UPDATE ticket_devices SET assigned_technician_id=NULL WHERE ticket_device_id=?')
    ->execute([$sibling]);
expectResult($visibleDevices(consoleProposedRepairScope($receivingTechnician)) === [],
    'Technician without a device assignment retained repair access.');

echo "PASS: two-device isolation, legacy permissions, independent ticket money and revoked-account/branch denials.\n";
