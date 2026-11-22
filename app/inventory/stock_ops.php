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
    string $reason, bool $confirmed): string
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
        if ($action === 'count') {
            $pending = stockRun($pdo, "SELECT stock_count_review_id FROM stock_count_reviews
                WHERE branch_id=? AND part_id=? AND status='PENDING' FOR UPDATE",
                [$branchId,$partId])->fetchColumn();
            if ($pending) {
                throw new DomainException('A count for this part already awaits independent review.');
            }
            // Pilot policy: a single difference of >=5 units OR >=5 units across
            // count adjustments in 24 hours must have a different manager's review.
            // This prevents splitting one large discrepancy into smaller requests.
            $recent=stockRun($pdo, "SELECT COALESCE(SUM(ABS(quantity_delta)),0)
                FROM inventory_transactions
                WHERE branch_id=? AND part_id=? AND transaction_type='ADJUSTMENT'
                AND reference_code LIKE 'COUNT-%'
                AND created_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR",
                [$branchId,$partId])->fetchColumn();
            if (abs($delta)+stockUnits((string)$recent) >= 5000) {
                stockRun($pdo, "INSERT INTO stock_count_reviews
                    (branch_id,part_id,expected_on_hand,expected_reserved,expected_movement_id,
                     target_on_hand,reason,requested_by_user_id,created_at)
                    VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP())",
                    [$branchId,$partId,$state['on_hand'],$state['reserved'],$expectedMovement,
                     stockNumber($target),$reason,$actorId]);
                $reviewId = (int)$pdo->lastInsertId();
                stockRun($pdo, "INSERT INTO audit_events
                    (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
                    VALUES ('USER',?,'branch_inventory',?,'STOCK_COUNT_REVIEW_REQUESTED',?,?,UTC_TIMESTAMP())",
                    [$actorId,$partId,json_encode([
                        'branch_id'=>$branchId,'review_id'=>$reviewId,'expected_on_hand'=>$state['on_hand'],
                        'target_on_hand'=>stockNumber($target),'expected_movement_id'=>$expectedMovement
                    ],JSON_THROW_ON_ERROR),$reason]);
                $pdo->commit();
                return 'pending';
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
        return 'applied';
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


// Display only pending requests for one assigned branch; all writes re-check permissions.
function stockPendingWork(PDO $pdo, int $actorId, int $branchId): array
{
    $assigned = stockRun($pdo, 'SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND a.branch_id=? AND b.is_active=1',
        [$actorId,$branchId])->fetchColumn();
    if (!$assigned) {
        throw new DomainException('Your account is not assigned to this active branch.');
    }
    $quarantines = stockRun($pdo, "SELECT q.quarantine_id,q.part_id,q.quantity,q.source_type,
        q.reason,q.recorded_by_user_id,q.created_at,p.part_name,p.sku,u.full_name AS recorded_by
        FROM stock_quarantines q JOIN parts p ON p.part_id=q.part_id
        JOIN users u ON u.user_id=q.recorded_by_user_id
        LEFT JOIN stock_quarantine_dispositions d ON d.quarantine_id=q.quarantine_id
        WHERE q.branch_id=? AND d.disposition_id IS NULL
        ORDER BY q.quarantine_id DESC LIMIT 10",[$branchId])->fetchAll();
    $counts = stockRun($pdo, "SELECT r.stock_count_review_id,r.part_id,r.expected_on_hand,
        r.expected_reserved,r.target_on_hand,r.reason,r.requested_by_user_id,
        r.created_at,p.part_name,p.sku,u.full_name AS requested_by
        FROM stock_count_reviews r JOIN parts p ON p.part_id=r.part_id
        JOIN users u ON u.user_id=r.requested_by_user_id
        WHERE r.branch_id=? AND r.status='PENDING'
        ORDER BY r.stock_count_review_id DESC LIMIT 10",[$branchId])->fetchAll();
    return ['quarantines'=>$quarantines,'counts'=>$counts];
}

/**
 * The quarantine recorder cannot authorize releasing or scrapping their own record.
 * RELEASE returns physically reinspected stock to usable on-hand, never to a device.
 * SCRAP records an irreversible zero-stock-movement disposition: quarantine already
 * removed that part from usable on-hand.
 */
function stockDecideQuarantine(PDO $pdo, int $actorId, int $quarantineId,
    string $decision, string $reason, bool $confirmed): void
{
    $reason=trim($reason);
    if ($quarantineId<=0 || !in_array($decision,['RELEASE','SCRAP'],true) ||
        !$confirmed || mb_strlen($reason)<10 || mb_strlen($reason)>500) {
        throw new DomainException('Confirm a release or scrap decision with a reason of 10–500 characters.');
    }
    $locator=stockRun($pdo,'SELECT branch_id,part_id FROM stock_quarantines WHERE quarantine_id=?',
        [$quarantineId])->fetch();
    if (!$locator) {
        throw new DomainException('The quarantine record no longer exists.');
    }
    $branch=(int)$locator['branch_id'];
    $part=(int)$locator['part_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actorId,$branch);
        // Match ordinary shelf operations: lock the shelf before the quarantine row.
        $state=stockShelfState($pdo,$branch,$part,true);
        $q=stockRun($pdo,'SELECT quarantine_id,quantity,source_type,recorded_by_user_id
            FROM stock_quarantines WHERE quarantine_id=? AND branch_id=? AND part_id=? FOR UPDATE',
            [$quarantineId,$branch,$part])->fetch();
        if (!$q) {
            throw new DomainException('The quarantine record changed.');
        }
        if ((int)$q['recorded_by_user_id']===$actorId) {
            throw new DomainException('A different branch manager must approve this quarantine disposition.');
        }
        $existing=stockRun($pdo,'SELECT disposition_id FROM stock_quarantine_dispositions
            WHERE quarantine_id=? FOR UPDATE',[$quarantineId])->fetchColumn();
        if ($existing) {
            throw new DomainException('This quarantine already has a final disposition.');
        }
        $movement=null;
        if ($decision==='RELEASE') {
            $oldUnits=stockUnits($state['on_hand']);
            $added=stockUnits((string)$q['quantity']);
            $newUnits=$oldUnits+$added;
            $newCount=stockNumber($newUnits);
            stockRun($pdo,'UPDATE branch_inventory SET quantity_on_hand=?
                WHERE branch_id=? AND part_id=?',[$newCount,$branch,$part]);
            stockRun($pdo,"INSERT INTO inventory_transactions
                (branch_id,part_id,transaction_type,quantity_delta,reference_code,reason,
                 performed_by_user_id,created_at)
                VALUES (?,?,'ADJUSTMENT',?,?,?, ?,UTC_TIMESTAMP())",
                [$branch,$part,stockNumber($added),'QREL-' . $quarantineId,$reason,$actorId]);
            $movement=(int)$pdo->lastInsertId();
        }
        stockRun($pdo,"INSERT INTO stock_quarantine_dispositions
            (quarantine_id,decision,release_movement_id,approved_by_user_id,reason,approved_at)
            VALUES (?,?,?,?,?,UTC_TIMESTAMP())",
            [$quarantineId,$decision,$movement,$actorId,$reason]);
        $id=(int)$pdo->lastInsertId();
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'branch_inventory',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,$part,'STOCK_QUARANTINE_' . $decision,
                json_encode(['branch_id'=>$branch,'quarantine_id'=>$quarantineId,
                    'quantity_on_hand'=>$state['on_hand']],JSON_THROW_ON_ERROR),
                json_encode(['disposition_id'=>$id,'decision'=>$decision,'movement_id'=>$movement,
                    'quantity_on_hand'=>$decision==='RELEASE' ? $newCount : $state['on_hand']],
                    JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Independent review is required for physical count differences >= 5.000 units.
 * The requester can cancel a pending review, but cannot approve or reject it.
 */
function stockDecideCount(PDO $pdo, int $actorId, int $reviewId,
    string $decision, string $reason, bool $confirmed): void
{
    $reason=trim($reason);
    if ($reviewId<=0 || !in_array($decision,['approve','reject','cancel'],true) ||
        !$confirmed || mb_strlen($reason)<10 || mb_strlen($reason)>500) {
        throw new DomainException('Confirm the count review with a reason of 10–500 characters.');
    }
    $locator=stockRun($pdo,'SELECT branch_id,part_id FROM stock_count_reviews
        WHERE stock_count_review_id=?',[$reviewId])->fetch();
    if (!$locator) throw new DomainException('The count request no longer exists.');
    $branch=(int)$locator['branch_id'];
    $part=(int)$locator['part_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actorId,$branch);
        $state=stockShelfState($pdo,$branch,$part,true);
        $review=stockRun($pdo,'SELECT * FROM stock_count_reviews
            WHERE stock_count_review_id=? AND branch_id=? AND part_id=? FOR UPDATE',
            [$reviewId,$branch,$part])->fetch();
        if (!$review || $review['status']!=='PENDING') {
            throw new DomainException('This count review is no longer pending.');
        }
        $requestedBy=(int)$review['requested_by_user_id'];
        if ($decision==='cancel' ? $actorId!==$requestedBy : $actorId===$requestedBy) {
            throw new DomainException($decision==='cancel'
                ? 'Only the requester may cancel their pending count.'
                : 'A different branch manager must independently review this count.');
        }
        $movement=null;
        $status=['approve'=>'APPROVED','reject'=>'REJECTED','cancel'=>'CANCELLED'][$decision];
        if ($decision==='approve') {
            if ($state['on_hand']!==(string)$review['expected_on_hand'] ||
                $state['reserved']!==(string)$review['expected_reserved'] ||
                $state['movement_id']!==(int)$review['expected_movement_id']) {
                throw new DomainException('Stock changed. Reject the outdated count and submit a fresh review.');
            }
            $target=stockUnits((string)$review['target_on_hand']);
            if ($target<stockUnits($state['reserved'])) {
                throw new DomainException('The approved count cannot consume reserved repair stock.');
            }
            $delta=$target-stockUnits($state['on_hand']);
            if ($delta===0) {
                throw new DomainException('No count difference remains to approve.');
            }
            stockRun($pdo,'UPDATE branch_inventory SET quantity_on_hand=?
                WHERE branch_id=? AND part_id=?',
                [stockNumber($target),$branch,$part]);
            stockRun($pdo,"INSERT INTO inventory_transactions
                (branch_id,part_id,transaction_type,quantity_delta,reference_code,reason,
                 performed_by_user_id,created_at)
                VALUES (?,?,'ADJUSTMENT',?,?,?, ?,UTC_TIMESTAMP())",
                [$branch,$part,stockSigned($delta),'COUNT-APPROVED-' . $reviewId,
                 mb_substr($review['reason'] . ' | Approved: ' . $reason,0,500),$actorId]);
            $movement=(int)$pdo->lastInsertId();
        }
        stockRun($pdo,'UPDATE stock_count_reviews SET status=?,reviewed_by_user_id=?,
            reviewed_at=UTC_TIMESTAMP(),review_reason=?,adjustment_movement_id=?
            WHERE stock_count_review_id=? AND status=\'PENDING\'',
            [$status,$actorId,$reason,$movement,$reviewId]);
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'branch_inventory',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,$part,'STOCK_COUNT_' . $status,
             json_encode(['branch_id'=>$branch,'review_id'=>$reviewId,
                'expected_on_hand'=>$review['expected_on_hand'],
                'expected_reserved'=>$review['expected_reserved']],JSON_THROW_ON_ERROR),
             json_encode(['target_on_hand'=>$review['target_on_hand'],
                'movement_id'=>$movement,'status'=>$status],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
