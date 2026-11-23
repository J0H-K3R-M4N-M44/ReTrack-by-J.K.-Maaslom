<?php
declare(strict_types=1);

/**
 * Small, isolated academic domain actions for the reduced schema.
 * NOT wired to the existing website. All callers still need session and CSRF checks.
 * Dates are UTC; the PDO session must use +00:00 (see the academic README).
 */

function edpInTransaction(PDO $pdo, callable $work): mixed
{
    static $savepointSequence = 0;
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = $ownsTransaction ? null : 'edp_course_action_' . (++$savepointSequence);
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    } else {
        // Unique for nested services; do not overwrite an outer action's savepoint.
        $pdo->exec('SAVEPOINT ' . $savepoint);
    }
    try {
        $result = $work();
        if ($ownsTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
        }
        throw $error;
    }
}

function edpActiveBranchStaff(PDO $pdo, int $actorId, int $branchId, array $allowedRoles): string
{
    $query = $pdo->prepare('SELECT role_code, is_active FROM users WHERE user_id = ? FOR UPDATE');
    $query->execute([$actorId]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int) $user['is_active'] !== 1 || !in_array($user['role_code'], $allowedRoles, true)) {
        throw new DomainException('This account cannot perform the action.');
    }
    $query = $pdo->prepare('SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id = a.branch_id AND b.is_active = 1
        WHERE a.user_id = ? AND a.branch_id = ?');
    $query->execute([$actorId, $branchId]);
    if (!$query->fetchColumn()) {
        throw new DomainException('This record is outside your assigned branch.');
    }
    return (string)$user['role_code'];
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
            d.repair_started_at, d.custody_state, t.branch_id, t.ticket_state
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
        if ($device['custody_state'] !== 'AT_LOCATION') {
            throw new DomainException('Repair status cannot change while the device is away.');
        }
        if ($next === $device['status_code']) {
            throw new DomainException('Choose a different repair stage.');
        }
        $query = $pdo->prepare('SELECT 1 FROM allowed_status_transitions WHERE from_status = ? AND to_status = ?');
        $query->execute([$device['status_code'], $next]);
        if (!$query->fetchColumn()) {
            throw new DomainException('This repair status transition is not allowed.');
        }
        // A technician cannot describe a quotation as awaiting customer approval
        // until a manager-approved sending workflow has actually sent it.
        if ($next === 'AWAITING_CUSTOMER_APPROVAL') {
            $query = $pdo->prepare('SELECT quote_status,sent_at FROM repair_quotes
                WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
            $query->execute([$ticketDeviceId]);
            $approval = $query->fetch(PDO::FETCH_ASSOC);
            if (!$approval || $approval['quote_status'] !== 'AWAITING_CUSTOMER' ||
                $approval['sent_at'] === null) {
                throw new DomainException('A sent quotation is required before customer approval can be awaited.');
            }
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

/** Assign only an active branch technician, with a small classroom queue limit. */
function edpAssignTechnician(PDO $pdo, int $actorId, int $ticketDeviceId, int $technicianId,
    int $capacity = 5): void
{
    if ($capacity < 1 || $capacity > 50) {
        throw new DomainException('Invalid technician capacity.');
    }
    edpInTransaction($pdo, function () use ($pdo, $actorId, $ticketDeviceId, $technicianId, $capacity) {
        $query = $pdo->prepare('SELECT d.assigned_technician_id, t.branch_id, t.ticket_state,
            d.status_code FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $query->execute([$ticketDeviceId]);
        $device = $query->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['ticket_state'] !== 'OPEN' ||
            in_array($device['status_code'], ['COMPLETED','CANCELLED'], true)) {
            throw new DomainException('Only an open device can be assigned.');
        }
        $branchId = (int) $device['branch_id'];
        edpActiveBranchStaff($pdo, $actorId, $branchId, ['ADMIN','MANAGER']);
        edpActiveBranchStaff($pdo, $technicianId, $branchId, ['TECHNICIAN']);
        if ((int) $device['assigned_technician_id'] === $technicianId) {
            return;
        }
        // Serializes all assignments to this technician against other uses of this helper.
        $query = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE');
        $query->execute([$technicianId]);
        $query = $pdo->prepare("SELECT COUNT(*) FROM ticket_devices d
            JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.assigned_technician_id=? AND t.ticket_state='OPEN'
              AND d.status_code NOT IN ('COMPLETED','CANCELLED')");
        $query->execute([$technicianId]);
        if ((int) $query->fetchColumn() >= $capacity) {
            throw new DomainException('Technician active repair limit reached.');
        }
        $pdo->prepare('UPDATE ticket_devices SET assigned_technician_id=? WHERE ticket_device_id=?')
            ->execute([$technicianId,$ticketDeviceId]);
        $pdo->prepare("INSERT INTO audit_events (actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'ticket_device',?,'TECHNICIAN_ASSIGNED')")
            ->execute([$actorId,$ticketDeviceId]);
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

/** All reads are current/locking reads; never use a stale consistent-read SUM. */
function edpPaidCentsForUpdate(PDO $pdo, int $billId): int
{
    $query = $pdo->prepare('SELECT amount FROM repair_bill_payments WHERE bill_id=? FOR UPDATE');
    $query->execute([$billId]);
    $total = 0;
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $amount) {
        $total += edpCents((string) $amount);
    }
    return $total;
}

/** Record confirmed counter cash; COD requires a separate verified collection action. */
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
        // Lock the parent before the bill; all academic bill writers must use this order.
        $query = $pdo->prepare('SELECT t.ticket_id,t.branch_id,t.ticket_state FROM repair_tickets t
            JOIN repair_bills b ON b.ticket_id=t.ticket_id WHERE b.bill_id=? FOR UPDATE');
        $query->execute([$billId]);
        $ticket = $query->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || $ticket['ticket_state'] !== 'OPEN') {
            throw new DomainException('This ticket cannot accept payments.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$ticket['branch_id'],['CASHIER','MANAGER','ADMIN']);
        $query = $pdo->prepare('SELECT bill_id,bill_status,total_amount,version_no
            FROM repair_bills WHERE ticket_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $query->execute([(int)$ticket['ticket_id']]);
        $bill = $query->fetch(PDO::FETCH_ASSOC);
        if (!$bill || (int)$bill['bill_id'] !== $billId || $bill['bill_status'] !== 'ISSUED') {
            throw new DomainException('This is not the current issued bill.');
        }
        $query = $pdo->prepare('SELECT payment_id,bill_id,amount,payment_method,received_by_user_id
            FROM repair_bill_payments WHERE request_key=? FOR UPDATE');
        $query->execute([strtolower($requestKey)]);
        $previous = $query->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            if ((int)$previous['bill_id'] !== $billId ||
                edpCents((string)$previous['amount']) !== $cents ||
                (int)$previous['received_by_user_id'] !== $actorId ||
                $previous['payment_method'] !== $method) {
                throw new DomainException('Payment request identifier was already used differently.');
            }
            return (int)$previous['payment_id'];
        }
        if (edpPaidCentsForUpdate($pdo,$billId) + $cents > edpCents((string)$bill['total_amount'])) {
            throw new DomainException('The payment exceeds the remaining balance.');
        }
        $query = $pdo->prepare('INSERT INTO repair_bill_payments
            (bill_id,amount,request_key,received_by_user_id,received_at)
            VALUES (?,?,?,?,UTC_TIMESTAMP())');
        $query->execute([$billId,$amount,strtolower($requestKey),$actorId]);
        $paymentId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO audit_events
            (actor_user_id,entity_type,entity_id,action_code,created_at)
            VALUES (?,'repair_bill_payment',?,'COUNTER_CASH_RECORDED',UTC_TIMESTAMP())")
            ->execute([$actorId,$paymentId]);
        return $paymentId;
    });
}

/** Complete entire ticket only after every device is ready and the bill is settled. */
function edpConfirmPickup(PDO $pdo, int $actorId, int $ticketId): void
{
    edpInTransaction($pdo, function () use ($pdo,$actorId,$ticketId) {
        $query = $pdo->prepare('SELECT ticket_id,branch_id,ticket_state FROM repair_tickets WHERE ticket_id=? FOR UPDATE');
        $query->execute([$ticketId]);
        $ticket = $query->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || $ticket['ticket_state'] !== 'OPEN') {
            throw new DomainException('Only an open ticket can be handed over.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$ticket['branch_id'],['CASHIER','MANAGER','ADMIN']);
        $query = $pdo->prepare('SELECT bill_id,bill_status,total_amount FROM repair_bills
            WHERE ticket_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $query->execute([$ticketId]);
        $bill = $query->fetch(PDO::FETCH_ASSOC);
        if (!$bill || $bill['bill_status'] !== 'ISSUED') {
            throw new DomainException('An issued current bill is required.');
        }
        $billId = (int)$bill['bill_id'];
        $query = $pdo->prepare('SELECT ticket_device_id,status_code,custody_state FROM ticket_devices
            WHERE ticket_id=? ORDER BY ticket_device_id FOR UPDATE');
        $query->execute([$ticketId]);
        $devices = $query->fetchAll(PDO::FETCH_ASSOC);
        if (!$devices) {
            throw new DomainException('No devices on this ticket.');
        }
        foreach ($devices as $device) {
            if ($device['status_code'] !== 'READY_FOR_PICKUP' || $device['custody_state'] !== 'AT_LOCATION') {
                throw new DomainException('Every device must be ready and at the counter.');
            }
        }
        $query = $pdo->prepare('SELECT ticket_device_id,amount FROM repair_bill_items WHERE bill_id=? FOR UPDATE');
        $query->execute([$billId]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $billedIds = array_map('intval',array_column($rows,'ticket_device_id'));
        $deviceIds = array_map('intval',array_column($devices,'ticket_device_id'));
        sort($billedIds); sort($deviceIds);
        $billCents = 0;
        foreach ($rows as $row) {
            $billCents += edpCents((string)$row['amount']);
        }
        // Academic bill items represent the complete total with no separate discount/tax.
        if ($billedIds !== $deviceIds || $billCents !== edpCents((string)$bill['total_amount'])) {
            throw new DomainException('The issued bill breakdown does not match this ticket.');
        }
        if (edpPaidCentsForUpdate($pdo,$billId) !== edpCents((string)$bill['total_amount'])) {
            throw new DomainException('The ticket has an unpaid balance.');
        }
        foreach ($devices as $device) {
            $id=(int)$device['ticket_device_id'];
            $pdo->prepare("UPDATE ticket_devices SET status_code='COMPLETED',custody_state='WITH_CUSTOMER',
                location_id=NULL,return_handover_at=UTC_TIMESTAMP(),return_handover_by=?
                WHERE ticket_device_id=?")
                ->execute([$actorId,$id]);
            $pdo->prepare("INSERT INTO ticket_status_events
                (ticket_device_id,from_status,to_status,actor_user_id,public_summary,is_customer_visible,created_at)
                VALUES (?,'READY_FOR_PICKUP','COMPLETED',?,'Collected by customer',1,UTC_TIMESTAMP())")
                ->execute([$id,$actorId]);
        }
        $pdo->prepare("UPDATE repair_tickets SET ticket_state='CLOSED',closed_at=UTC_TIMESTAMP()
            WHERE ticket_id=?")->execute([$ticketId]);
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_ticket',?,'CUSTOMER_PICKUP_COMPLETED')")
            ->execute([$actorId,$ticketId]);
    });
}
