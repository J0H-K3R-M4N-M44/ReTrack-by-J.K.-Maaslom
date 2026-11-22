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
