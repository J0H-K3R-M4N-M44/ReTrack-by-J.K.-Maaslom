<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/console/scopes.php';

function scopeAssert(bool $true, string $message): void
{
    if (!$true) {
        throw new RuntimeException($message);
    }
}
function scopeReject(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException($message);
}

$admin = ['user_id' => 21, 'role' => 'ADMIN'];
$technician = ['user_id' => 37, 'role' => 'TECHNICIAN'];
$cashier = ['user_id' => 45, 'role' => 'CASHIER'];

[$current, $currentArgs] = consoleCurrentDeviceScope($admin);
scopeAssert(str_contains($current, 'a.branch_id = t.accountable_branch_id'),
    'Legacy device scope must retain ticket authority during rollout.');
scopeAssert(!str_contains($current, 'd.repair_branch_id'),
    'Proposed device-branch scope leaked into existing authorizations.');
scopeAssert($currentArgs === [21], 'Legacy admin scope parameters changed.');

[$currentTech, $currentTechArgs] = consoleCurrentDeviceScope($technician);
scopeAssert(str_contains($currentTech, 'd.assigned_technician_id = ?') &&
    $currentTechArgs === [37, 37], 'Existing assigned-technician scope changed.');

[$repair, $repairArgs] = consoleProposedRepairScope($admin);
scopeAssert(str_contains($repair, 'a.branch_id = d.repair_branch_id') &&
    str_contains($repair, 'd.repair_branch_id IS NOT NULL') &&
    !str_contains($repair, 'a.branch_id = t.accountable_branch_id'),
    'Proposed repair scope must use device branch only.');
scopeAssert(str_contains($repair, 'u.is_active = 1') &&
    str_contains($repair, 'b.is_active = 1') && $repairArgs === [21],
    'Repair scope must require a live user/branch assignment.');

[$repairTech, $repairTechArgs] = consoleProposedRepairScope($technician);
scopeAssert(str_contains($repairTech, 'd.assigned_technician_id = ?') &&
    $repairTechArgs === [37, 37], 'Future technician scope must stay assignment-limited.');

[$finance, $financeArgs] = consoleTicketFinanceScope($cashier);
scopeAssert(str_contains($finance, 'a.branch_id = t.accountable_branch_id') &&
    !str_contains($finance, 'd.') && $financeArgs === [45],
    'Cash scope must work without a device alias and never use repair ownership.');
scopeAssert(str_contains($finance, 'u.is_active = 1') &&
    str_contains($finance, 'b.is_active = 1'),
    'Cash scope must require an active user and branch.');
scopeAssert(consoleTicketFinanceScope($admin)[1] === [21],
    'Admin ticket financial scope not available.');

foreach (['CASHIER', 'COURIER', 'INTAKE_AGENT', 'VIEWER'] as $role) {
    scopeReject(fn() => consoleProposedRepairScope(['user_id' => 8, 'role' => $role]),
        'Non-repair role was granted proposed device-repair scope: ' . $role);
}
foreach (['TECHNICIAN', 'COURIER', 'INTAKE_AGENT', 'VIEWER'] as $role) {
    scopeReject(fn() => consoleTicketFinanceScope(['user_id' => 8, 'role' => $role]),
        'Non-finance role was granted ticket-finance scope: ' . $role);
}
scopeReject(fn() => consoleProposedRepairScope(['user_id' => 0, 'role' => 'ADMIN']),
    'Anonymous actor received proposed repair scope.');
scopeReject(fn() => consoleTicketFinanceScope(['user_id' => 0, 'role' => 'MANAGER']),
    'Anonymous actor received financial scope.');

echo "PASS: separate ticket-finance and proposed repair scopes; legacy device access unchanged.\n";
