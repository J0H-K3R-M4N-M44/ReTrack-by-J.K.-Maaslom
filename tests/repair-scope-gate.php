<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../app/console/scopes.php';

function repairGuardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function repairGuardDenies(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException($message);
}

$roles = [
    ['user_id' => 11, 'role' => 'ADMIN'],
    ['user_id' => 12, 'role' => 'MANAGER'],
    ['user_id' => 13, 'role' => 'TECHNICIAN'],
    ['user_id' => 14, 'role' => 'INTAKE_AGENT'],
    ['user_id' => 15, 'role' => 'VIEWER'],
];
foreach ($roles as $actor) {
    [$original, $params] = consoleCurrentDeviceScope($actor);
    [$legacy, $legacyParams] = consoleRepairAccessScope($actor, 'legacy');
    repairGuardAssert($original === $legacy && $params === $legacyParams,
        'Legacy mode altered original device access: ' . $actor['role']);
    [$guard, $guardParams] = consoleRepairAccessScope($actor, 'restrict');
    repairGuardAssert(str_starts_with($guard, $original) &&
        str_contains($guard, 'd.repair_branch_id = t.accountable_branch_id') &&
        str_contains($guard, 'd.repair_branch_id IS NOT NULL') &&
        str_contains($guard, 'repair_guard.is_active = 1') &&
        str_contains($guard, 'repair_actor.is_active = 1'),
        'Restrictive mode failed to require matching active repair authority.');
    repairGuardAssert($guardParams === [...$params, $actor['user_id']],
        'Guard parameters changed the existing scope.');
}
$tech = $roles[2];
repairGuardAssert(str_contains(consoleRepairAccessScope($tech, 'restrict')[0],
    'd.assigned_technician_id = ?'), 'Guard lost technician assignment restrictions.');
$cashier = ['user_id' => 30, 'role' => 'CASHIER'];
[$finance, $financeParams] = consoleTicketFinanceScope($cashier);
repairGuardAssert(!str_contains($finance, 'd.') &&
    str_contains($finance, 't.accountable_branch_id') && $financeParams === [30],
    'Ticket financial scope became dependent on device repair branch.');
repairGuardDenies(fn() => consoleRepairAccessScope($roles[0], 'enforce'),
    'Unsupported full cutover was silently accepted.');
repairGuardDenies(fn() => consoleRepairAccessScope($roles[0], 'disabled'),
    'Unsupported flag value was silently accepted.');
repairGuardDenies(fn() => consoleRepairAccessScope($roles[0], true),
    'Boolean feature flag was incorrectly permitted.');

echo "PASS: default-equivalent legacy scope, restrict-only repair gate, technician assignment, finance separation, invalid modes denied.\n";
