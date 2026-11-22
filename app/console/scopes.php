<?php
declare(strict_types=1);

/**
 * SQL scope builders. Both aliases (t and d) are fixed, never user input.
 * Actor data MUST come from a current authenticated server-side user lookup.
 * Returned parameters are bound by the caller.
 *
 * This file does not grant permissions. The proposed repair scope is NOT used
 * by live requests until the separate security/stock cutover is approved.
 */

/** Preserve the original, ticket-scoped staff device behavior during rollout. */
function consoleCurrentDeviceScope(array $user): array
{
    $sql = "t.record_stage = 'ISSUED' AND EXISTS (
        SELECT 1 FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id
        WHERE a.user_id = ? AND b.is_active = 1 AND a.branch_id = t.accountable_branch_id)";
    $params = [(int) $user['user_id']];
    if ($user['role'] === 'TECHNICIAN') {
        $sql .= ' AND d.assigned_technician_id = ?';
        $params[] = (int) $user['user_id'];
    }
    return [$sql, $params];
}

/**
 * Future device-repair scope ONLY. No caller may use this for ticket-wide
 * customer records, final bills, cancellation, payment, pickup or refunds.
 * Not wired into production queries during the gated rollout.
 */
function consoleProposedRepairScope(array $user): array
{
    if (!in_array($user['role'] ?? '', ['ADMIN', 'MANAGER', 'TECHNICIAN'], true) ||
        (int) ($user['user_id'] ?? 0) <= 0) {
        throw new DomainException('This role cannot perform branch repair operations.');
    }
    $sql = "t.record_stage = 'ISSUED' AND d.repair_branch_id IS NOT NULL AND EXISTS (
        SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id = a.branch_id
        JOIN users u ON u.user_id = a.user_id
        WHERE a.user_id = ? AND u.is_active = 1 AND b.is_active = 1
        AND a.branch_id = d.repair_branch_id)";
    $params = [(int) $user['user_id']];
    if ($user['role'] === 'TECHNICIAN') {
        $sql .= ' AND d.assigned_technician_id = ?';
        $params[] = (int) $user['user_id'];
    }
    return [$sql, $params];
}

/**
 * Ticket contract/finance scope. Never derive cashier permission from custody
 * or from any device's repair_branch_id. Safe with queries containing only t.
 */
function consoleTicketFinanceScope(array $user): array
{
    if (!in_array($user['role'] ?? '', ['ADMIN', 'MANAGER', 'CASHIER'], true) ||
        (int) ($user['user_id'] ?? 0) <= 0) {
        throw new DomainException('This role cannot view ticket financial records.');
    }
    return [
        "t.record_stage = 'ISSUED' AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches b ON b.branch_id = a.branch_id
            JOIN users u ON u.user_id = a.user_id
            WHERE a.user_id = ? AND u.is_active = 1 AND b.is_active = 1
            AND a.branch_id = t.accountable_branch_id)",
        [(int) $user['user_id']],
    ];
}


/**
 * Deliberately restrictive cutover preflight:
 *
 * legacy   - exact existing ticket/assigned-technician behavior.
 * restrict - legacy AND confirmed, active, matching device/ticket branch.
 *
 * "restrict" never gives a new branch permission to open a repair. An eventual
 * mixed-branch authorization cutover requires a separate audited feature.
 * Do not enable this mode on a persistent database until every issued row is
 * backfilled and the operator has reviewed the effect on access.
 */
function consoleRepairGuardMode(): string
{
    $path = __DIR__ . '/../../config/config.php';
    $config = is_file($path) ? require $path : [];
    if (!is_array($config)) {
        throw new DomainException('Invalid repair authorization configuration.');
    }
    $mode = $config['repair_scope_mode'] ?? 'legacy';
    if (!in_array($mode, ['legacy', 'restrict'], true)) {
        // Never silently fall back to permissive access on a typo.
        throw new DomainException('Invalid repair authorization mode. Access is blocked.');
    }
    return $mode;
}

/**
 * Stable, device-specific branch-alignment predicate (SQL aliases t and d).
 * This is a DENY predicate, never a grant to a second branch.
 */
function consoleRepairAlignmentSql(): string
{
    return "d.repair_branch_id IS NOT NULL
        AND d.repair_branch_id = t.accountable_branch_id
        AND EXISTS (SELECT 1 FROM branches repair_guard
            WHERE repair_guard.branch_id = d.repair_branch_id
            AND repair_guard.is_active = 1)";
}

/**
 * Both aliases t and d are mandatory. Only use for device-specific data.
 * Caller-supplied $mode is intended for CLI scope tests; HTTP callers always
 * resolve the mode exclusively from the server-owned config file.
 */
function consoleRepairAccessScope(array $user, ?string $mode = null): array
{
    $mode ??= consoleRepairGuardMode();
    if (!in_array($mode, ['legacy', 'restrict'], true)) {
        throw new DomainException('Invalid repair authorization mode. Access is blocked.');
    }
    [$scope, $params] = consoleCurrentDeviceScope($user);
    if ($mode === 'restrict') {
        // A mismatched or unverified device is NOT an approved transfer.
        // Keep legacy branch AND technician assignment checks in force.
        $scope .= ' AND ' . consoleRepairAlignmentSql() . "
            AND EXISTS (SELECT 1 FROM users repair_actor
                WHERE repair_actor.user_id = ? AND repair_actor.is_active = 1)";
        $params[] = (int) $user['user_id'];
    }
    return [$scope, $params];
}
