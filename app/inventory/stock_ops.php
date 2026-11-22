<?php
declare(strict_types=1);
require_once __DIR__ . '/reservations.php';

// Work in integer thousandths so a shelf count never depends on float arithmetic.
function stockUnits(string $raw): int
{
    $raw = trim($raw);
    if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,3}))?$/D', $raw, $matches)) {
        throw new DomainException('Use a nonnegative quantity with at most three decimal places.');
    }
    return (int) $matches[1] * 1000 + (int) str_pad($matches[2] ?? '', 3, '0');
}

function stockNumber(int $units): string
{
    if ($units < 0 || $units > 999999999999) {
        throw new DomainException('Quantity exceeds the supported inventory range.');
    }
    return intdiv($units, 1000) . '.' . str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT);
}

function stockSigned(int $units): string
{
    return ($units < 0 ? '-' : '') . stockNumber(abs($units));
}

// The role and active branch assignment are checked again inside each write transaction.
function stockAuthorizeManager(PDO $pdo, int $actorId, int $branchId): void
{
    $staff = stockRun($pdo, 'SELECT role,is_active FROM users WHERE user_id=? FOR UPDATE', [$actorId])->fetch();
    if (!$staff || !(int) $staff['is_active'] ||
        !in_array($staff['role'], ['ADMIN','MANAGER'], true)) {
        throw new DomainException('Only an active branch manager or administrator can change inventory counts.');
    }
    $assignment = stockRun($pdo, 'SELECT a.user_id FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND a.branch_id=? AND b.is_active=1 FOR UPDATE',
        [$actorId,$branchId])->fetchColumn();
    if (!$assignment) {
        throw new DomainException('Your account is not assigned to this active branch.');
    }
}

function stockShelfState(PDO $pdo, int $branchId, int $partId, bool $lock = false): array
{
    $query = 'SELECT i.quantity_on_hand,p.part_name,p.sku
        FROM branch_inventory i JOIN parts p ON p.part_id=i.part_id
        WHERE i.branch_id=? AND i.part_id=?' . ($lock ? ' FOR UPDATE' : '');
    $row = stockRun($pdo, $query, [$branchId,$partId])->fetch();
    if (!$row) {
        throw new DomainException('This part has no stock record at the selected branch.');
    }
    $holds = stockRun($pdo, 'SELECT quantity FROM stock_reservations
        WHERE branch_id=? AND part_id=?' . ($lock ? ' FOR UPDATE' : ''), [$branchId,$partId])->fetchAll(PDO::FETCH_COLUMN);
    $reserved = 0;
    foreach ($holds as $quantity) {
        $reserved += stockUnits((string) $quantity);
    }
    $latest = stockRun($pdo, 'SELECT COALESCE(MAX(inventory_transaction_id),0)
        FROM inventory_transactions WHERE branch_id=? AND part_id=?', [$branchId,$partId])->fetchColumn();
    return [
        'on_hand' => stockNumber(stockUnits((string) $row['quantity_on_hand'])),
        'reserved' => stockNumber($reserved),
        'movement_id' => (int) $latest,
        'sku' => $row['sku'],
        'part_name' => $row['part_name'],
    ];
}

function stockShelfAction(PDO $pdo, int $actorId, int $branchId, int $partId,
    string $expectedOnHand, int $expectedMovement, string $action, string $quantity,
    string $reason, bool $confirmed): void
{
    $reason = trim($reason);
    if (!in_array($action, ['quarantine', 'count'], true) || !$confirmed ||
        mb_strlen($reason) < 10 || mb_strlen($reason) > 500 ||
        $expectedMovement < 0 || $branchId <= 0 || $partId <= 0) {
        throw new DomainException('Confirm the stock action with an explanation of 10–500 characters.');
    }
    // Validate user-supplied quantities BEFORE acquiring row locks.
    $beforeUnits = stockUnits($expectedOnHand);
    $requestedUnits = stockUnits($quantity);
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actorId,$branchId);
        $state = stockShelfState($pdo,$branchId,$partId,true);
        $onHand = stockUnits($state['on_hand']);
        $held = stockUnits($state['reserved']);
        if ($onHand !== $beforeUnits || $state['movement_id'] !== $expectedMovement) {
            throw new DomainException('Stock changed. Reload the shelf counts and review the action again.');
        }
        if ($action === 'quarantine') {
            if ($requestedUnits === 0) {
                throw new DomainException('Quarantine at least one positive quantity.');
            }
            $target = $onHand - $requestedUnits;
            if ($target < $held) {
                throw new DomainException('This would quarantine stock already reserved for a repair.');
            }
            $delta = -$requestedUnits;
        } else {
            $target = $requestedUnits;
            if ($target < $held) {
                throw new DomainException('The counted stock cannot be lower than existing repair reservations.');
            }
            $delta = $target - $onHand;
            if ($delta === 0) {
                throw new DomainException('The verified count matches the shelf. No adjustment is needed.');
            }
        }
        $newOnHand = stockNumber($target);
        stockRun($pdo, 'UPDATE branch_inventory SET quantity_on_hand=? WHERE branch_id=? AND part_id=?',
            [$newOnHand,$branchId,$partId]);
        $reference = ($action === 'quarantine' ? 'QUAR-' : 'COUNT-') . strtoupper(bin2hex(random_bytes(10)));
        stockRun($pdo, "INSERT INTO inventory_transactions
            (branch_id,part_id,transaction_type,quantity_delta,reference_code,reason,performed_by_user_id,created_at)
            VALUES (?,?,'ADJUSTMENT',?,?,?, ?,UTC_TIMESTAMP())",
            [$branchId,$partId,stockSigned($delta),$reference,$reason,$actorId]);
        $movement = (int) $pdo->lastInsertId();
        $quarantineId = null;
        if ($action === 'quarantine') {
            stockRun($pdo, "INSERT INTO stock_quarantines
                (branch_id,part_id,quantity,source_type,adjustment_movement_id,reason,recorded_by_user_id,created_at)
                VALUES (?,?,?,'SHELF',?,?,?,UTC_TIMESTAMP())",
                [$branchId,$partId,stockNumber($requestedUnits),$movement,$reason,$actorId]);
            $quarantineId = (int) $pdo->lastInsertId();
        }
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'branch_inventory',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,$partId,$action === 'quarantine' ? 'STOCK_QUARANTINED' : 'STOCK_COUNT_ADJUSTED',
                json_encode(['branch_id'=>$branchId,'quantity_on_hand'=>$state['on_hand'],
                    'reserved'=>$state['reserved'],'latest_movement_id'=>$expectedMovement], JSON_THROW_ON_ERROR),
                json_encode(['branch_id'=>$branchId,'quantity_on_hand'=>$newOnHand,
                    'movement_id'=>$movement,'quarantine_id'=>$quarantineId], JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function stockDesk(PDO $pdo, int $actorId, int $branchId, string $search, int $requestedPage): array
{
    $allowed = stockRun($pdo, 'SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND a.branch_id=? AND b.is_active=1', [$actorId,$branchId])->fetchColumn();
    if (!$allowed) {
        throw new DomainException('This branch is not assigned to your account.');
    }
    $term = likePattern(mb_substr(trim($search),0,80));
    $params = [$branchId,$term,$term];
    $where = " FROM branch_inventory i JOIN parts p ON p.part_id=i.part_id
        WHERE i.branch_id=? AND (p.part_name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
    $count = (int) stockRun($pdo,'SELECT COUNT(*)' . $where,$params)->fetchColumn();
    $pages = max(1,(int) ceil($count/10));
    $page = min($pages,max(1,$requestedPage));
    $offset = ($page-1)*10; // Computed integer, not user SQL.
    $rows = stockRun($pdo,"SELECT i.branch_id,i.part_id,i.quantity_on_hand,p.part_name,p.sku,p.is_active,
        COALESCE((SELECT SUM(r.quantity) FROM stock_reservations r
          WHERE r.branch_id=i.branch_id AND r.part_id=i.part_id),0) AS reserved,
        COALESCE((SELECT SUM(q.quantity) FROM stock_quarantines q
          WHERE q.branch_id=i.branch_id AND q.part_id=i.part_id),0) AS quarantined,
        COALESCE((SELECT MAX(m.inventory_transaction_id) FROM inventory_transactions m
          WHERE m.branch_id=i.branch_id AND m.part_id=i.part_id),0) AS movement_id
        " . $where . " ORDER BY p.part_name,p.sku LIMIT 10 OFFSET $offset", $params)->fetchAll();
    foreach ($rows as &$row) {
        $row['available'] = stockNumber(stockUnits((string)$row['quantity_on_hand']) -
            stockUnits((string)$row['reserved']));
    }
    unset($row);
    return ['rows'=>$rows,'page'=>$page,'pages'=>$pages,'total'=>$count];
}
