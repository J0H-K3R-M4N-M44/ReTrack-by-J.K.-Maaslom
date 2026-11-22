<?php
declare(strict_types=1);

/**
 * Small, isolated academic domain actions for the reduced schema.
 * NOT wired to the existing website. All callers still need session and CSRF checks.
 * Dates are UTC; the PDO session must use +00:00 (see the academic README).
 */

function edpInTransaction(PDO $pdo, callable $work): mixed
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    } else {
        // Lets disposable tests roll back all fixtures after exercising real actions.
        $pdo->exec('SAVEPOINT edp_course_action');
    }
    try {
        $result = $work();
        if ($ownsTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT edp_course_action');
        }
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT edp_course_action');
                $pdo->exec('RELEASE SAVEPOINT edp_course_action');
            }
        }
        throw $error;
    }
}

function edpActiveBranchStaff(PDO $pdo, int $actorId, int $branchId, array $allowedRoles): void
{
    $query = $pdo->prepare('SELECT role_code, is_active FROM users WHERE user_id = ? FOR UPDATE');
    $query->execute([$actorId]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int) $user['is_active'] !== 1 || !in_array($user['role_code'], $allowedRoles, true)) {
        throw new DomainException('This account cannot perform the action.');
    }
    $query = $pdo->prepare('SELECT 1 FROM user_branch_assignments WHERE user_id = ? AND branch_id = ?');
    $query->execute([$actorId, $branchId]);
    if (!$query->fetchColumn()) {
        throw new DomainException('This record is outside your assigned branch.');
    }
}

/** Advance a technician's assigned repair and record the timeline atomically. */
function edpChangeRepairStatus(PDO $pdo, int $actorId, int $ticketDeviceId, string $next,
    string $publicSummary = ''): ?string
{
    $techStages = ['DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS',
        'IN_PROGRESS', 'TESTING', 'READY_FOR_PICKUP'];
    if (!in_array($next, $techStages, true)) {
        throw new DomainException('The technician cannot select this status.');
    }
    $publicSummary = trim($publicSummary);
    if ((function_exists('mb_strlen') ? mb_strlen($publicSummary) : strlen($publicSummary)) > 500) {
        throw new DomainException('The customer-facing message is too long.');
    }

    return edpInTransaction($pdo, function () use ($pdo, $actorId, $ticketDeviceId, $next, $publicSummary) {
        $query = $pdo->prepare('SELECT d.ticket_device_id, d.status_code, d.assigned_technician_id,
            d.repair_started_at, t.branch_id, t.ticket_state
            FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
            WHERE d.ticket_device_id = ? FOR UPDATE');
        $query->execute([$ticketDeviceId]);
        $device = $query->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['ticket_state'] !== 'OPEN') {
            throw new DomainException('Repair ticket is not open.');
        }
        edpActiveBranchStaff($pdo, $actorId, (int) $device['branch_id'], ['TECHNICIAN']);
        if ((int) $device['assigned_technician_id'] !== $actorId) {
            throw new DomainException('Repair is not assigned to this technician.');
        }
        if ($next === $device['status_code']) {
            throw new DomainException('Choose a different repair stage.');
        }
        $query = $pdo->prepare('SELECT 1 FROM allowed_status_transitions WHERE from_status = ? AND to_status = ?');
        $query->execute([$device['status_code'], $next]);
        if (!$query->fetchColumn()) {
            throw new DomainException('This repair status transition is not allowed.');
        }
        if ($next === 'IN_PROGRESS' || $next === 'READY_FOR_PICKUP') {
            $query = $pdo->prepare('SELECT quote_status FROM repair_quotes
                WHERE ticket_device_id = ? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
            $query->execute([$ticketDeviceId]);
            if ($query->fetchColumn() !== 'APPROVED') {
                throw new DomainException('A current approved quotation is required.');
            }
        }
        $query = $pdo->prepare("UPDATE ticket_devices
            SET status_code = ?, repair_started_at = CASE
              WHEN repair_started_at IS NULL AND ? = 'IN_PROGRESS' THEN UTC_TIMESTAMP()
              ELSE repair_started_at END
            WHERE ticket_device_id = ?");
        $query->execute([$next, $next, $ticketDeviceId]);
        $query = $pdo->prepare('INSERT INTO ticket_status_events
            (ticket_device_id, from_status, to_status, actor_user_id, public_summary, is_customer_visible, created_at)
            VALUES (?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())');
        $query->execute([$ticketDeviceId, $device['status_code'], $next, $actorId,
            $publicSummary === '' ? null : $publicSummary]);
        $pdo->prepare("INSERT INTO audit_events
            (actor_user_id, entity_type, entity_id, action_code, created_at)
            VALUES (?, 'ticket_device', ?, 'STATUS_CHANGED', UTC_TIMESTAMP())")
            ->execute([$actorId, $ticketDeviceId]);
        $query = $pdo->prepare('SELECT repair_started_at FROM ticket_devices WHERE ticket_device_id = ?');
        $query->execute([$ticketDeviceId]);
        $startedAt = $query->fetchColumn();
        return $startedAt === null || $startedAt === false ? null : (string) $startedAt;
    });
}

/** Use integer cents rather than floating point for bill-settlement checks. */
function edpCents(string $amount): int
{
    if (!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D', $amount)) {
        throw new DomainException('Enter a valid peso amount with at most two decimals.');
    }
    $parts = explode('.', $amount, 2);
    return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
}

/** Record confirmed counter cash; COD is reserved until an actual collection flow exists. */
function edpRecordCashPayment(PDO $pdo, int $actorId, int $billId, string $amount,
    string $requestKey, string $method = 'COUNTER_CASH'): int
{
    $cents = edpCents($amount);
    if ($cents <= 0 || !preg_match('/^[0-9a-fA-F]{64}$/D', $requestKey)) {
        throw new DomainException('Invalid payment amount or request identifier.');
    }
    if ($method !== 'COUNTER_CASH') {
        throw new DomainException('COD cannot be recorded before its collection workflow is implemented.');
    }
    return edpInTransaction($pdo, function () use ($pdo, $actorId, $billId, $amount, $cents, $requestKey, $method) {
        $query = $pdo->prepare('SELECT b.bill_id, b.bill_status, b.total_amount,
            t.branch_id, t.ticket_state FROM repair_bills b
            JOIN repair_tickets t ON t.ticket_id = b.ticket_id
            WHERE b.bill_id = ? FOR UPDATE');
        $query->execute([$billId]);
        $bill = $query->fetch(PDO::FETCH_ASSOC);
        if (!$bill || $bill['bill_status'] !== 'ISSUED' || $bill['ticket_state'] !== 'OPEN') {
            throw new DomainException('The bill cannot accept a payment.');
        }
        edpActiveBranchStaff($pdo, $actorId, (int) $bill['branch_id'], ['CASHIER', 'MANAGER', 'ADMIN']);
        $query = $pdo->prepare('SELECT payment_id, bill_id, amount, payment_method, received_by_user_id
            FROM repair_bill_payments WHERE request_key = ?');
        $query->execute([$requestKey]);
        $previous = $query->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            if ((int) $previous['bill_id'] !== $billId ||
                edpCents((string) $previous['amount']) !== $cents ||
                (int) $previous['received_by_user_id'] !== $actorId ||
                $previous['payment_method'] !== $method) {
                throw new DomainException('This payment request identifier was already used differently.');
            }
            return (int) $previous['payment_id'];
        }
        $query = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM repair_bill_payments WHERE bill_id = ?');
        $query->execute([$billId]);
        $alreadyPaid = edpCents((string) $query->fetchColumn());
        if ($alreadyPaid + $cents > edpCents((string) $bill['total_amount'])) {
            throw new DomainException('The payment exceeds the remaining balance.');
        }
        $query = $pdo->prepare('INSERT INTO repair_bill_payments
            (bill_id, amount, request_key, received_by_user_id, received_at)
            VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
        $query->execute([$billId, $amount, strtolower($requestKey), $actorId]);
        $paymentId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO audit_events
            (actor_user_id, entity_type, entity_id, action_code, created_at)
            VALUES (?, 'repair_bill_payment', ?, 'COUNTER_CASH_RECORDED', UTC_TIMESTAMP())")
            ->execute([$actorId, $paymentId]);
        return $paymentId;
    });
}
