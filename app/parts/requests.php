<?php
declare(strict_types=1);
require_once __DIR__ . '/../console/repository.php';

// This is an INTERNAL needs register, not a purchase order, stock reservation,
// customer quote change, receipt, payment or inventory movement.
function partRequestCanReview(array $user): bool
{
    return in_array($user['role'], ['ADMIN', 'MANAGER'], true);
}
function partRequestDevice(PDO $pdo, array $user, int $deviceId, bool $lock = false): array
{
    if (!in_array($user['role'], ['ADMIN', 'MANAGER', 'TECHNICIAN'], true)) {
        throw new DomainException('Your role cannot access internal part requests.');
    }
    return consoleDevice($pdo, $user, $deviceId, $lock);
}
function partRequestValues(array $input): array
{
    return [
        'part_description' => trim(requestText($input, 'part_description')),
        'quantity' => trim(requestText($input, 'quantity')),
        'reason' => trim(requestText($input, 'reason')),
    ];
}
function partRequestValidate(array $values): void
{
    if (!mb_check_encoding($values['part_description'], 'UTF-8') ||
        mb_strlen($values['part_description']) < 3 ||
        mb_strlen($values['part_description']) > 160) {
        throw new DomainException('Describe the needed part in 3–160 characters.');
    }
    if (!preg_match('/^(?:0|[1-9][0-9]{0,3})(?:\.[0-9]{1,3})?$/D', $values['quantity']) ||
        !preg_match('/[1-9]/', $values['quantity'])) {
        throw new DomainException('Enter a positive quantity up to 9,999.999, with at most three decimals.');
    }
    if (!mb_check_encoding($values['reason'], 'UTF-8') ||
        mb_strlen($values['reason']) < 10 ||
        mb_strlen($values['reason']) > 500) {
        throw new DomainException('Explain why this part is needed in 10–500 characters.');
    }
}
function partRequestVersion(array $request): string
{
    return hash('sha256', (string) $request['part_request_id'] . ':' . (string) $request['version_no']);
}
function partRequestOpenDevice(array $device): bool
{
    return $device['case_state'] === 'OPEN' &&
        in_array($device['repair_status'], ['DIAGNOSING', 'AWAITING_PARTS', 'IN_PROGRESS'], true);
}
function partRequestLoad(PDO $pdo, array $user, int $requestId): array
{
    $lookup = $pdo->prepare('SELECT * FROM repair_part_requests WHERE part_request_id=?');
    $lookup->execute([$requestId]);
    $request = $lookup->fetch();
    if (!$request) {
        throw new DomainException('This part request is unavailable.');
    }
    $device = partRequestDevice($pdo, $user, (int) $request['ticket_device_id']);
    if ((int) $device['accountable_branch_id'] !== (int) $request['branch_id']) {
        throw new DomainException('This request belongs to a different repair branch.');
    }
    return $request;
}
function partRequestAudit(PDO $pdo, int $actorId, int $requestId, string $action): void
{
    $pdo->prepare(
        "INSERT INTO audit_events
         (actor_type,actor_user_id,entity_type,entity_id,action,after_data,created_at)
         VALUES ('USER',?,'repair_part_request',?,?,?,UTC_TIMESTAMP())",
    )->execute([$actorId, $requestId, $action, json_encode(['part_request_id' => $requestId], JSON_THROW_ON_ERROR)]);
}
function partRequestCreate(PDO $pdo, int $actorId, int $deviceId, array $values): int
{
    partRequestValidate($values);
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT user_id, role, is_active FROM users WHERE user_id=? FOR UPDATE');
        $query->execute([$actorId]);
        $actor = $query->fetch();
        if (!$actor || !(int) $actor['is_active'] || $actor['role'] !== 'TECHNICIAN') {
            throw new DomainException('Only an active assigned technician can request a repair part.');
        }
        $device = partRequestDevice($pdo, $actor, $deviceId, true);
        if (!partRequestOpenDevice($device)) {
            throw new DomainException('Part requests require an open repair in diagnosis, awaiting parts or repair.');
        }
        $pdo->prepare(
            "INSERT INTO repair_part_requests
             (ticket_device_id,branch_id,part_description,quantity,reason,requested_by_user_id)
             VALUES (?,?,?,?,?,?)",
        )->execute([
            $deviceId,
            $device['accountable_branch_id'],
            $values['part_description'],
            $values['quantity'],
            $values['reason'],
            $actorId,
        ]);
        $id = (int) $pdo->lastInsertId();
        partRequestAudit($pdo, $actorId, $id, 'PART_REQUEST_CREATED');
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
function partRequestChange(
    PDO $pdo,
    int $actorId,
    int $requestId,
    string $expectedVersion,
    string $action,
    string $note,
): void {
    $note = trim($note);
    if (!mb_check_encoding($note, 'UTF-8') || mb_strlen($note) > 500 ||
        ($action !== 'acknowledge' && mb_strlen($note) < 10)) {
        throw new DomainException('For a decline or resolution, enter a reason of 10–500 characters.');
    }
    if (!in_array($action, ['acknowledge', 'decline', 'resolve'], true)) {
        throw new DomainException('Choose a supported review action.');
    }
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT user_id, role, is_active FROM users WHERE user_id=? FOR UPDATE');
        $query->execute([$actorId]);
        $actor = $query->fetch();
        if (!$actor || !(int) $actor['is_active'] || !partRequestCanReview($actor)) {
            throw new DomainException('Only an active administrator or branch manager can review requests.');
        }
        // Lock the device before the request, consistent with repair and quote writes.
        $lookup = $pdo->prepare('SELECT ticket_device_id FROM repair_part_requests WHERE part_request_id=?');
        $lookup->execute([$requestId]);
        $deviceId = (int) $lookup->fetchColumn();
        if (!$deviceId) {
            throw new DomainException('This part request is unavailable.');
        }
        $device = partRequestDevice($pdo, $actor, $deviceId, true);
        $locked = $pdo->prepare('SELECT * FROM repair_part_requests WHERE part_request_id=? FOR UPDATE');
        $locked->execute([$requestId]);
        $request = $locked->fetch();
        if (!$request || (int) $request['ticket_device_id'] !== $deviceId ||
            (int) $request['branch_id'] !== (int) $device['accountable_branch_id']) {
            throw new DomainException('This request is no longer part of your branch.');
        }
        if (!hash_equals(partRequestVersion($request), $expectedVersion)) {
            throw new DomainException('This request changed. Reload it before continuing.');
        }
        if ($device['case_state'] !== 'OPEN' ||
            in_array($device['repair_status'], ['CANCELLED','COMPLETED'], true)) {
            throw new DomainException('Closed or returned repairs cannot receive new part request decisions.');
        }
        if ($action === 'resolve') {
            if ($request['status'] !== 'ACKNOWLEDGED') {
                throw new DomainException('Only acknowledged requests can be resolved.');
            }
            $pdo->prepare(
                "UPDATE repair_part_requests
                 SET status='RESOLVED', resolved_by_user_id=?, resolution_note=?,
                     resolved_at=UTC_TIMESTAMP(), version_no=version_no+1 WHERE part_request_id=?",
            )->execute([$actorId, $note, $requestId]);
        } else {
            if ($request['status'] !== 'OPEN') {
                throw new DomainException('Only open requests can be reviewed.');
            }
            $pdo->prepare(
                'UPDATE repair_part_requests SET status=?, reviewed_by_user_id=?, review_note=?,
                 reviewed_at=UTC_TIMESTAMP(), version_no=version_no+1 WHERE part_request_id=?',
            )->execute([
                $action === 'acknowledge' ? 'ACKNOWLEDGED' : 'DECLINED',
                $actorId,
                $note ?: null,
                $requestId,
            ]);
        }
        partRequestAudit($pdo, $actorId, $requestId, 'PART_REQUEST_' . strtoupper($action));
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
function partRequestList(PDO $pdo, array $user, string $rawPage, ?int $deviceId = null): array
{
    if (!in_array($user['role'], ['ADMIN', 'MANAGER', 'TECHNICIAN'], true)) {
        throw new DomainException('Your role cannot open internal part requests.');
    }
    $sql = 'FROM repair_part_requests r
        JOIN ticket_devices d ON d.ticket_device_id=r.ticket_device_id
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN branches b ON b.branch_id=r.branch_id
        WHERE t.record_stage = \'ISSUED\' AND t.accountable_branch_id=r.branch_id
        AND EXISTS (SELECT 1 FROM user_branch_assignments a
            JOIN branches active ON active.branch_id=a.branch_id
            WHERE a.user_id=? AND a.branch_id=r.branch_id AND active.is_active=1)';
    $params = [(int) $user['user_id']];
    if ($user['role'] === 'TECHNICIAN') {
        $sql .= ' AND d.assigned_technician_id=?';
        $params[] = (int) $user['user_id'];
    }
    if ($deviceId !== null) {
        $sql .= ' AND r.ticket_device_id=?';
        $params[] = $deviceId;
    }
    $count = $pdo->prepare('SELECT COUNT(*) ' . $sql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 10));
    $page = ctype_digit($rawPage) ? min($pages, max(1, (int) $rawPage)) : 1;
    $offset = ($page - 1) * 10;
    $statement = $pdo->prepare(
        'SELECT r.part_request_id, r.ticket_device_id, r.part_description, r.quantity,
                r.status, r.created_at, d.item_code, b.branch_name ' .
        $sql . ' ORDER BY r.created_at DESC, r.part_request_id DESC LIMIT 10 OFFSET ' . $offset,
    );
    $statement->execute($params);
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}
