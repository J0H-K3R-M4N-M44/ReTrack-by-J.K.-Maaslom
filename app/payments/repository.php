<?php
declare(strict_types=1);
require_once __DIR__ . '/../quotes/repository.php';

function cashCanOpen(array $user): bool
{
    return in_array($user['role'] ?? '', ['ADMIN', 'MANAGER', 'CASHIER'], true);
}

function cashAmountCents(string $value): int
{
    if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', trim($value), $parts)) {
        throw new DomainException('Enter a cash amount using up to two decimal places.');
    }
    return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
}

function cashAmountText(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function cashPageTicket(PDO $pdo, array $user, int $ticketId): array
{
    if (!in_array($user['role'] ?? '', ['ADMIN', 'MANAGER'], true)) {
        throw new DomainException('Only an administrator or manager can issue a final bill.');
    }
    $query = $pdo->prepare(
        "SELECT t.ticket_id, t.ticket_code, t.case_state, t.closure_outcome,
            t.accountable_branch_id, b.branch_name
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.accountable_branch_id
        WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND a.branch_id = t.accountable_branch_id AND active.is_active = 1
        )",
    );
    $query->execute([$ticketId, (int) $user['user_id']]);
    $ticket = $query->fetch();
    if (!$ticket) {
        throw new DomainException('Ticket unavailable or outside your assigned branches.');
    }

    $devices = $pdo->prepare(
        'SELECT d.ticket_device_id, d.item_code, d.device_type_snapshot, d.make_snapshot,
            d.model_snapshot, d.repair_status, d.custody_state, d.physical_location_id,
            l.location_name, l.branch_id AS location_branch_id,
            q.quote_id, q.quote_status, q.total_amount, q.updated_at AS quote_updated_at
        FROM ticket_devices d
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        LEFT JOIN repair_quotes q ON q.quote_id = (
            SELECT latest.quote_id FROM repair_quotes latest
            WHERE latest.ticket_device_id = d.ticket_device_id
            ORDER BY latest.version_no DESC, latest.quote_id DESC LIMIT 1
        )
        WHERE d.ticket_id = ? ORDER BY d.child_sequence',
    );
    $devices->execute([$ticketId]);
    $ticket['devices'] = $devices->fetchAll();
    return $ticket;
}

function cashTicketVersion(array $ticket): string
{
    return hash(
        'sha256',
        json_encode(
            [
                $ticket['ticket_id'],
                $ticket['case_state'],
                array_map(
                    static fn(array $device): array => [
                        $device['ticket_device_id'],
                        $device['repair_status'],
                        $device['custody_state'],
                        $device['physical_location_id'],
                        $device['quote_id'],
                        $device['quote_status'],
                        $device['quote_updated_at'],
                    ],
                    $ticket['devices'],
                ),
            ],
            JSON_THROW_ON_ERROR,
        ),
    );
}

function cashBillDetails(PDO $pdo, array $user, int $billId): array
{
    if (!cashCanOpen($user)) {
        throw new DomainException('Your role cannot open the cash desk.');
    }
    $query = $pdo->prepare(
        'SELECT rb.*, t.ticket_code, t.case_state, t.closure_outcome,
            t.accountable_branch_id, b.branch_name,
            COALESCE(payments.amount_paid, 0) AS amount_paid,
            COALESCE(payments.latest_payment_id, 0) AS latest_payment_id
        FROM repair_bills rb
        JOIN repair_tickets t ON t.ticket_id = rb.ticket_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id
        LEFT JOIN (
            SELECT bill_id, SUM(amount) AS amount_paid, MAX(payment_id) AS latest_payment_id
            FROM repair_bill_payments GROUP BY bill_id
        ) payments ON payments.bill_id = rb.bill_id
        WHERE rb.bill_id = ? AND t.record_stage = \'ISSUED\'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND active.is_active = 1
                AND a.branch_id = t.accountable_branch_id
        )',
    );
    $query->execute([$billId, (int) $user['user_id']]);
    $bill = $query->fetch();
    if (!$bill) {
        throw new DomainException('Bill unavailable or outside your assigned branches.');
    }
    $lines = $pdo->prepare(
        'SELECT bi.ticket_device_id, d.item_code, bi.item_type, bi.description,
            bi.quantity, bi.unit_price, bi.line_total
        FROM repair_bill_items bi
        LEFT JOIN ticket_devices d ON d.ticket_device_id = bi.ticket_device_id
        WHERE bi.bill_id = ? ORDER BY bi.bill_item_id',
    );
    $lines->execute([$billId]);
    $bill['items'] = $lines->fetchAll();
    $payments = $pdo->prepare(
        'SELECT payment_id, payment_method, amount, received_at, u.full_name AS collector
        FROM repair_bill_payments p JOIN users u ON u.user_id = p.received_by_user_id
        WHERE p.bill_id = ? ORDER BY p.payment_id DESC',
    );
    $payments->execute([$billId]);
    $bill['payments'] = $payments->fetchAll();
    $bill['balance_cents'] = max(
        0,
        cashAmountCents((string) $bill['total_amount']) -
            cashAmountCents((string) $bill['amount_paid']),
    );
    $bill['version'] = cashBillVersion($bill);
    return $bill;
}

function cashHandoverPreview(PDO $pdo, array $user, int $ticketId): array
{
    if (!cashCanOpen($user)) {
        throw new DomainException('Your role cannot open this cash record.');
    }
    $query = $pdo->prepare(
        "SELECT t.ticket_id, t.ticket_code, t.case_state, t.accountable_branch_id,
            b.branch_name, d.ticket_device_id, d.item_code, d.device_type_snapshot,
            d.model_snapshot, d.repair_status, d.custody_state, d.physical_location_id,
            l.location_name, l.branch_id AS location_branch_id
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.accountable_branch_id
        JOIN ticket_devices d ON d.ticket_id = t.ticket_id
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND active.is_active = 1
                AND a.branch_id = t.accountable_branch_id
        ) ORDER BY d.child_sequence",
    );
    $query->execute([$ticketId, (int) $user['user_id']]);
    $devices = $query->fetchAll();
    if (!$devices) {
        throw new DomainException('Ticket unavailable or outside your assigned branches.');
    }
    $ticket = array_intersect_key($devices[0], array_flip([
        'ticket_id', 'ticket_code', 'case_state', 'accountable_branch_id', 'branch_name',
    ]));
    $ticket['devices'] = array_map(
        static fn(array $device): array => array_intersect_key($device, array_flip([
            'ticket_device_id', 'item_code', 'device_type_snapshot', 'model_snapshot',
            'repair_status', 'custody_state', 'physical_location_id', 'location_name',
            'location_branch_id',
        ])),
        $devices,
    );
    return $ticket;
}

function cashBillVersion(array $bill): string
{
    return hash(
        'sha256',
        implode('|', [
            $bill['bill_id'],
            $bill['bill_status'],
            $bill['total_amount'],
            $bill['updated_at'],
            $bill['amount_paid'],
            $bill['latest_payment_id'],
        ]),
    );
}

// A ticket receives one final bill after every device passes testing and is ready.
function cashIssueFinalBill(
    PDO $pdo,
    int $actorId,
    int $ticketId,
    string $expectedVersion,
): int {
    $pdo->beginTransaction();
    try {
        $actorQuery = $pdo->prepare(
            'SELECT user_id, role, is_active FROM users WHERE user_id = ? FOR UPDATE',
        );
        $actorQuery->execute([$actorId]);
        $actor = $actorQuery->fetch();
        if (!$actor || !(int) $actor['is_active']) {
            throw new DomainException('Your account cannot issue this bill.');
        }
        $ticket = cashPageTicketForUpdate($pdo, $actor, $ticketId);
        if (!hash_equals(cashTicketVersion($ticket), $expectedVersion)) {
            throw new DomainException('The ticket changed. Reload before issuing the bill.');
        }
        if ($ticket['case_state'] !== 'OPEN' || !$ticket['devices']) {
            throw new DomainException('This ticket is closed or has no issued devices.');
        }

        $existing = $pdo->prepare(
            "SELECT bill_id FROM repair_bills WHERE ticket_id = ? AND bill_status = 'ISSUED'
            ORDER BY version_no DESC LIMIT 1 FOR UPDATE",
        );
        $existing->execute([$ticketId]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $pdo->commit();
            return (int) $existingId;
        }

        $billLines = [];
        $subtotalCents = 0;
        foreach ($ticket['devices'] as $device) {
            if (
                $device['repair_status'] !== 'READY_FOR_PICKUP' ||
                $device['custody_state'] !== 'AT_LOCATION' ||
                !$device['physical_location_id'] ||
                (int) $device['location_branch_id'] !== (int) $ticket['accountable_branch_id']
            ) {
                throw new DomainException(
                    'Every device must pass testing and be stored at its assigned branch before billing.',
                );
            }
            $quote = quoteLatest($pdo, (int) $device['ticket_device_id'], true);
            if (!$quote || $quote['quote_status'] !== 'APPROVED') {
                throw new DomainException(
                    'Every device needs a current approved estimate before the final bill can be issued.',
                );
            }
            $items = $pdo->prepare(
                'SELECT quote_item_id, item_type, description, quantity, unit_price, line_total
                FROM repair_quote_items WHERE quote_id = ? ORDER BY quote_item_id',
            );
            $items->execute([(int) $quote['quote_id']]);
            $quoteItems = $items->fetchAll();
            $quoteTotal = 0;
            foreach ($quoteItems as $item) {
                $lineCents = cashAmountCents((string) $item['line_total']);
                $quoteTotal += $lineCents;
                $subtotalCents += $lineCents;
                $billLines[] = [
                    'ticket_device_id' => (int) $device['ticket_device_id'],
                    'quote_item_id' => (int) $item['quote_item_id'],
                    'item_type' => $item['item_type'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ];
            }
            if ($quoteTotal !== cashAmountCents((string) $quote['total_amount'])) {
                throw new DomainException(
                    'An estimate total does not match its line items. Ask a manager to review it.',
                );
            }
        }
        if (!$billLines || $subtotalCents < 0) {
            throw new DomainException('The approved estimates have no billable line items.');
        }

        $nextVersion = $pdo->prepare(
            'SELECT COALESCE(MAX(version_no), 0) + 1 FROM repair_bills WHERE ticket_id = ?',
        );
        $nextVersion->execute([$ticketId]);
        $version = (int) $nextVersion->fetchColumn();
        if ($version > 65535) {
            throw new DomainException('The bill revision limit has been reached.');
        }
        $billNumber = 'RTB-' . strtoupper(bin2hex(random_bytes(5)));
        $amount = cashAmountText($subtotalCents);
        $insertBill = $pdo->prepare(
            "INSERT INTO repair_bills
            (ticket_id, version_no, bill_number, bill_status, subtotal, total_amount,
                prepared_by_user_id, issued_at)
            VALUES (?, ?, ?, 'ISSUED', ?, ?, ?, UTC_TIMESTAMP())",
        );
        $insertBill->execute([
            $ticketId,
            $version,
            $billNumber,
            $amount,
            $amount,
            $actorId,
        ]);
        $billId = (int) $pdo->lastInsertId();
        $insertLine = $pdo->prepare(
            'INSERT INTO repair_bill_items
            (bill_id, ticket_device_id, quote_item_id, item_type, description,
                quantity, unit_price, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $usedQuoteItem = $pdo->prepare(
            'SELECT 1 FROM repair_bill_items WHERE quote_item_id = ? LIMIT 1',
        );
        foreach ($billLines as $line) {
            $usedQuoteItem->execute([$line['quote_item_id']]);
            // A voided old bill may already own the unique quote item. The replacement keeps a snapshot.
            $quoteItemId = $usedQuoteItem->fetchColumn() ? null : $line['quote_item_id'];
            $insertLine->execute([
                $billId,
                $line['ticket_device_id'],
                $quoteItemId,
                $line['item_type'],
                $line['description'],
                $line['quantity'],
                $line['unit_price'],
                $line['line_total'],
            ]);
        }
        cashAudit($pdo, $actorId, 'repair_bill', $billId, 'BILL_ISSUED', [
            'ticket_code' => $ticket['ticket_code'],
            'bill_number' => $billNumber,
            'total_amount' => $amount,
        ]);
        $pdo->commit();
        return $billId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function cashPageTicketForUpdate(PDO $pdo, array $actor, int $ticketId): array
{
    if (!in_array($actor['role'], ['ADMIN', 'MANAGER'], true)) {
        throw new DomainException('Only an administrator or manager can issue a final bill.');
    }
    $ticketQuery = $pdo->prepare(
        "SELECT t.ticket_id, t.ticket_code, t.case_state, t.closure_outcome,
            t.accountable_branch_id, b.branch_name
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.accountable_branch_id
        WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND a.branch_id = t.accountable_branch_id AND active.is_active = 1
        ) FOR UPDATE",
    );
    $ticketQuery->execute([$ticketId, (int) $actor['user_id']]);
    $ticket = $ticketQuery->fetch();
    if (!$ticket) {
        throw new DomainException('Ticket unavailable or outside your assigned branches.');
    }
    $deviceQuery = $pdo->prepare(
        'SELECT d.ticket_device_id, d.item_code, d.repair_status, d.custody_state,
            d.physical_location_id, l.branch_id AS location_branch_id,
            q.quote_id, q.quote_status, q.updated_at AS quote_updated_at
        FROM ticket_devices d LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        LEFT JOIN repair_quotes q ON q.quote_id = (
            SELECT latest.quote_id FROM repair_quotes latest
            WHERE latest.ticket_device_id = d.ticket_device_id
            ORDER BY latest.version_no DESC, latest.quote_id DESC LIMIT 1
        )
        WHERE d.ticket_id = ? ORDER BY d.child_sequence FOR UPDATE',
    );
    $deviceQuery->execute([$ticketId]);
    $ticket['devices'] = $deviceQuery->fetchAll();
    return $ticket;
}

function cashRecordPayment(
    PDO $pdo,
    int $actorId,
    int $billId,
    string $expectedVersion,
    string $amountText,
    string $requestKey,
): void {
    $amountCents = cashAmountCents($amountText);
    if ($amountCents <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
        throw new DomainException('Enter a valid amount and reopen the payment form.');
    }
    $pdo->beginTransaction();
    try {
        $actor = cashLockActor($pdo, $actorId);
        if (!cashCanOpen($actor)) {
            throw new DomainException('Your role cannot record cash payments.');
        }
        $bill = cashBillForUpdate($pdo, $actor, $billId);
        cashRefreshPaymentTotals($pdo, $bill);
        if (!hash_equals(cashBillVersion($bill), $expectedVersion)) {
            throw new DomainException('The bill changed. Reload before recording another payment.');
        }
        if ($bill['bill_status'] !== 'ISSUED' || $bill['case_state'] !== 'OPEN') {
            throw new DomainException('Only an issued bill on an open ticket can receive cash.');
        }
        $balance = cashAmountCents((string) $bill['total_amount']) -
            cashAmountCents((string) $bill['amount_paid']);
        if ($amountCents > $balance) {
            throw new DomainException('The payment cannot be greater than the remaining balance.');
        }
        $pdo->prepare(
            "INSERT INTO repair_bill_payments
            (bill_id, payment_method, amount, request_key, received_by_user_id, received_at)
            VALUES (?, 'COUNTER_CASH', ?, ?, ?, UTC_TIMESTAMP())",
        )->execute([$billId, cashAmountText($amountCents), $requestKey, $actorId]);
        $pdo->prepare('UPDATE repair_bills SET updated_at = UTC_TIMESTAMP() WHERE bill_id = ?')->execute([
            $billId,
        ]);
        cashAudit($pdo, $actorId, 'repair_bill', $billId, 'COUNTER_CASH_RECEIVED', [
            'amount' => cashAmountText($amountCents),
            'balance_after' => cashAmountText($balance - $amountCents),
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function cashCompleteHandover(
    PDO $pdo,
    int $actorId,
    int $billId,
    string $expectedVersion,
    string $recipient,
    string $relationship,
    string $conditionConfirmed,
): void {
    $recipient = trim($recipient);
    if (
        $recipient === '' ||
        mb_strlen($recipient) > 160 ||
        !in_array($relationship, ['CUSTOMER', 'AUTHORIZED_REPRESENTATIVE'], true) ||
        $conditionConfirmed !== 'yes'
    ) {
        throw new DomainException(
            'Enter the recipient name, choose their relationship, and confirm the device check.',
        );
    }
    $pdo->beginTransaction();
    try {
        $actor = cashLockActor($pdo, $actorId);
        if (!cashCanOpen($actor)) {
            throw new DomainException('Your role cannot complete a counter handover.');
        }
        $bill = cashBillForUpdate($pdo, $actor, $billId);
        cashRefreshPaymentTotals($pdo, $bill);
        if (!hash_equals(cashBillVersion($bill), $expectedVersion)) {
            throw new DomainException('The bill changed. Reload before confirming handover.');
        }
        if ($bill['bill_status'] !== 'ISSUED' || $bill['case_state'] !== 'OPEN') {
            throw new DomainException('Only an issued bill on an open ticket can be handed over.');
        }
        if (
            cashAmountCents((string) $bill['total_amount']) !==
            cashAmountCents((string) $bill['amount_paid'])
        ) {
            throw new DomainException('Collect the full bill balance before releasing the device.');
        }
        $ticket = cashPageTicketForHandover($pdo, $actor, (int) $bill['ticket_id']);
        if (!$ticket['devices']) {
            throw new DomainException('This ticket has no devices to return.');
        }
        foreach ($ticket['devices'] as $device) {
            if (
                $device['repair_status'] !== 'READY_FOR_PICKUP' ||
                $device['custody_state'] !== 'AT_LOCATION' ||
                !$device['physical_location_id'] ||
                (int) $device['location_branch_id'] !== (int) $ticket['accountable_branch_id']
            ) {
                throw new DomainException(
                    'All devices must be ready and present at the assigned branch before handover.',
                );
            }
        }

        $relationshipLabel = $relationship === 'CUSTOMER'
            ? 'customer'
            : 'authorized representative';
        $internalNote = 'Released to ' . $recipient . ' (' . $relationshipLabel .
            '). Recipient confirmed the device and accessories were checked.';
        foreach ($ticket['devices'] as $device) {
            $deviceId = (int) $device['ticket_device_id'];
            $pdo->prepare(
                "UPDATE ticket_devices SET repair_status = 'COMPLETED', custody_state = 'WITH_CUSTOMER',
                    physical_location_id = NULL, return_handover_at = UTC_TIMESTAMP(), return_handover_by = ?
                WHERE ticket_device_id = ?",
            )->execute([$actorId, $deviceId]);
            $pdo->prepare(
                "INSERT INTO ticket_status_events
                (ticket_device_id, from_status, to_status, actor_type, actor_user_id,
                    internal_note, public_summary, is_customer_visible, created_at)
                VALUES (?, 'READY_FOR_PICKUP', 'COMPLETED', 'USER', ?, ?,
                    'Device returned to the customer.', 1, UTC_TIMESTAMP())",
            )->execute([$deviceId, $actorId, $internalNote]);
            $pdo->prepare(
                "INSERT INTO device_custody_events
                (ticket_device_id, event_type, from_location_id, performed_by_user_id, note, happened_at)
                VALUES (?, 'RETURNED_TO_CUSTOMER', ?, ?, ?, UTC_TIMESTAMP())",
            )->execute([
                $deviceId,
                $device['physical_location_id'],
                $actorId,
                $internalNote,
            ]);
        }
        $pdo->prepare(
            "UPDATE repair_tickets SET case_state = 'CLOSED', closure_outcome = 'ALL_COMPLETED',
                closed_at = UTC_TIMESTAMP() WHERE ticket_id = ? AND case_state = 'OPEN'",
        )->execute([(int) $bill['ticket_id']]);
        cashAudit($pdo, $actorId, 'repair_ticket', (int) $bill['ticket_id'], 'TICKET_HANDED_OVER', [
            'bill_id' => $billId,
            'recipient' => $recipient,
            'relationship' => $relationship,
            'device_count' => count($ticket['devices']),
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function cashLockActor(PDO $pdo, int $actorId): array
{
    $query = $pdo->prepare('SELECT user_id, role, is_active FROM users WHERE user_id = ? FOR UPDATE');
    $query->execute([$actorId]);
    $actor = $query->fetch();
    if (!$actor || !(int) $actor['is_active']) {
        throw new DomainException('Your account cannot record cash or handover actions.');
    }
    return $actor;
}

function cashBillForUpdate(PDO $pdo, array $actor, int $billId): array
{
    $query = $pdo->prepare(
        'SELECT rb.bill_id, rb.ticket_id, rb.bill_number, rb.bill_status, rb.total_amount,
            rb.currency, rb.updated_at, t.ticket_code, t.case_state, t.closure_outcome,
            t.accountable_branch_id, b.branch_name,
            COALESCE(payments.amount_paid, 0) AS amount_paid,
            COALESCE(payments.latest_payment_id, 0) AS latest_payment_id
        FROM repair_bills rb
        JOIN repair_tickets t ON t.ticket_id = rb.ticket_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id
        LEFT JOIN (
            SELECT bill_id, SUM(amount) AS amount_paid, MAX(payment_id) AS latest_payment_id
            FROM repair_bill_payments GROUP BY bill_id
        ) payments ON payments.bill_id = rb.bill_id
        WHERE rb.bill_id = ? AND t.record_stage = \'ISSUED\'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND active.is_active = 1
                AND a.branch_id = t.accountable_branch_id
        ) FOR UPDATE',
    );
    $query->execute([$billId, (int) $actor['user_id']]);
    $bill = $query->fetch();
    if (!$bill) {
        throw new DomainException('Bill unavailable or outside your assigned branches.');
    }
    return $bill;
}

// Lock and total payment rows after locking the bill, so concurrent cash actions see the latest balance.
function cashRefreshPaymentTotals(PDO $pdo, array &$bill): void
{
    $query = $pdo->prepare(
        'SELECT payment_id, amount FROM repair_bill_payments WHERE bill_id = ? ORDER BY payment_id FOR UPDATE',
    );
    $query->execute([(int) $bill['bill_id']]);
    $paidCents = 0;
    $latestPaymentId = 0;
    foreach ($query->fetchAll() as $payment) {
        $paidCents += cashAmountCents((string) $payment['amount']);
        $latestPaymentId = (int) $payment['payment_id'];
    }
    $bill['amount_paid'] = cashAmountText($paidCents);
    $bill['latest_payment_id'] = $latestPaymentId;
}

function cashPageTicketForHandover(PDO $pdo, array $actor, int $ticketId): array
{
    $query = $pdo->prepare(
        "SELECT t.ticket_id, t.case_state, t.accountable_branch_id
        FROM repair_tickets t
        WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED' AND t.case_state = 'OPEN'
        AND EXISTS (
            SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND active.is_active = 1
                AND a.branch_id = t.accountable_branch_id
        ) FOR UPDATE",
    );
    $query->execute([$ticketId, (int) $actor['user_id']]);
    $ticket = $query->fetch();
    if (!$ticket) {
        throw new DomainException('Ticket unavailable or already closed.');
    }
    $devices = $pdo->prepare(
        'SELECT d.ticket_device_id, d.item_code, d.repair_status, d.custody_state,
            d.physical_location_id, l.branch_id AS location_branch_id
        FROM ticket_devices d LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        WHERE d.ticket_id = ? ORDER BY d.child_sequence FOR UPDATE',
    );
    $devices->execute([$ticketId]);
    $ticket['devices'] = $devices->fetchAll();
    return $ticket;
}

function cashAudit(
    PDO $pdo,
    int $actorId,
    string $entityType,
    int $entityId,
    string $action,
    array $after,
): void {
    $pdo->prepare(
        "INSERT INTO audit_events
        (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at)
        VALUES ('USER', ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
    )->execute([$actorId, $entityType, $entityId, $action, json_encode($after, JSON_THROW_ON_ERROR)]);
}
