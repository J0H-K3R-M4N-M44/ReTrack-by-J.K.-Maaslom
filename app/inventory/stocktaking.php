<?php
declare(strict_types=1);
require_once __DIR__ . '/stock_ops.php';

/**
 * Branch-wide physical stocktaking is a signed inventory audit.
 * It NEVER changes quantity_on_hand. Corrections use the existing independently
 * reviewed shelf-count process, followed by a fresh physical count.
 */
function stocktakeAccess(PDO $pdo, int $actor, int $branch): void
{
    $allowed=stockRun($pdo,"SELECT 1 FROM users u
        JOIN user_branch_assignments a ON a.user_id=u.user_id
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE u.user_id=? AND u.is_active=1 AND u.role IN ('ADMIN','MANAGER')
          AND a.branch_id=? AND b.is_active=1 LIMIT 1",[$actor,$branch])->fetchColumn();
    if (!$allowed) throw new DomainException('Stocktaking is unavailable outside your active assigned branch.');
}

function stocktakeReason(string $reason): string
{
    $reason=trim($reason);
    if (mb_strlen($reason)<15 || mb_strlen($reason)>500) {
        throw new DomainException('Record a physical-count or review explanation of 15–500 characters.');
    }
    return $reason;
}

function stocktakeAudit(PDO $pdo, int $actor, int $branch, int $id, string $action,
    array $after, string $reason): void
{
    stockRun($pdo,"INSERT INTO audit_events
        (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
        VALUES ('USER',?,'branch',?,?,?, ?,UTC_TIMESTAMP())",
        [$actor,$branch,$action,json_encode(array_merge([
            'stocktake_id'=>$id,'branch_id'=>$branch
        ],$after),JSON_THROW_ON_ERROR),$reason]);
}

/** Lock every stocked SKU in a stable order before locking session/count rows. */
function stocktakeLockShelves(PDO $pdo, int $branch): array
{
    $parts=stockRun($pdo,'SELECT part_id FROM branch_inventory
        WHERE branch_id=? ORDER BY part_id FOR UPDATE',[$branch])->fetchAll(PDO::FETCH_COLUMN);
    if (!$parts || count($parts)>250) {
        throw new DomainException('Stocktaking requires 1–250 branch inventory SKUs. For larger branches, use a reviewed segmented inventory procedure.');
    }
    $states=[];
    foreach ($parts as $part) {
        $states[(int)$part]=stockShelfState($pdo,$branch,(int)$part,true);
    }
    return $states;
}

function stocktakeOpen(PDO $pdo, int $actor, int $branch, string $period,
    string $reason, bool $confirmed): int
{
    $reason=stocktakeReason($reason);
    if (!$confirmed || $branch<=0 || !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$period)
        || $period!==gmdate('Y-m')) {
        throw new DomainException('Confirm the present UTC monthly stocktake for your assigned branch.');
    }
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $states=stocktakeLockShelves($pdo,$branch);
        $active=stockRun($pdo,"SELECT stocktake_id FROM inventory_stocktakes
            WHERE branch_id=? AND status IN ('OPEN','PENDING') FOR UPDATE",
            [$branch])->fetchColumn();
        if ($active) throw new DomainException('An unfinished branch stocktake already exists.');
        stockRun($pdo,"INSERT INTO inventory_stocktakes
            (branch_id,period_ym,status,opened_by_user_id,opened_at)
            VALUES (?,?,'OPEN',?,UTC_TIMESTAMP())",[$branch,$period,$actor]);
        $id=(int)$pdo->lastInsertId();
        foreach ($states as $part=>$snapshot) {
            stockRun($pdo,'INSERT INTO inventory_stocktake_lines
                (stocktake_id,part_id,opening_on_hand,opening_reserved,opening_movement_id)
                VALUES (?,?,?,?,?)',
                [$id,$part,$snapshot['on_hand'],$snapshot['reserved'],$snapshot['movement_id']]);
        }
        stocktakeAudit($pdo,$actor,$branch,$id,'STOCKTAKE_OPENED',[
            'period_ym'=>$period,'part_count'=>count($states),
            'stock_credited'=>false,'inventory_movement'=>false
        ],$reason);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function stocktakeLocator(PDO $pdo, int $id): array
{
    if ($id<=0) throw new DomainException('This stocktake does not exist.');
    $row=stockRun($pdo,'SELECT stocktake_id,branch_id,status,opened_by_user_id
        FROM inventory_stocktakes WHERE stocktake_id=?',[$id])->fetch();
    if (!$row) throw new DomainException('This stocktake does not exist.');
    return $row;
}

/** Recounting is allowed and audited; submitter may not falsify a stale shelf snapshot. */
function stocktakeCount(PDO $pdo,int $actor,int $id,int $part,
    string $expectedOnHand,string $expectedReserved,int $expectedMovement,
    string $physicalCount,string $reason,bool $confirmed): void
{
    $reason=stocktakeReason($reason);
    if (!$confirmed || $part<=0 || $expectedMovement<0) {
        throw new DomainException('Confirm this physical count and the expected shelf snapshot.');
    }
    $before=stockUnits($expectedOnHand);
    $reserved=stockUnits($expectedReserved);
    $count=stockUnits($physicalCount);
    $locator=stocktakeLocator($pdo,$id);
    $branch=(int)$locator['branch_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $state=stockShelfState($pdo,$branch,$part,true);
        $batch=stockRun($pdo,'SELECT * FROM inventory_stocktakes
            WHERE stocktake_id=? AND branch_id=? FOR UPDATE',[$id,$branch])->fetch();
        if (!$batch || $batch['status']!=='OPEN') {
            throw new DomainException('Only an open stocktake accepts new physical counts.');
        }
        $line=stockRun($pdo,'SELECT * FROM inventory_stocktake_lines
            WHERE stocktake_id=? AND part_id=? FOR UPDATE',[$id,$part])->fetch();
        if (!$line) {
            throw new DomainException('This SKU was not part of the opening stocktake snapshot.');
        }
        if ($state['on_hand']!==stockNumber($before) ||
            $state['reserved']!==stockNumber($reserved) ||
            $state['movement_id']!==$expectedMovement) {
            throw new DomainException('Stock changed since this form was displayed. Reload and physically recount.');
        }
        stockRun($pdo,'UPDATE inventory_stocktake_lines
            SET counted_on_hand=?,counted_expected_on_hand=?,counted_reserved=?,
                counted_movement_id=?,counted_by_user_id=?,counted_at=UTC_TIMESTAMP(),
                count_note=?
            WHERE stocktake_id=? AND part_id=?',
            [stockNumber($count),$state['on_hand'],$state['reserved'],
                $state['movement_id'],$actor,$reason,$id,$part]);
        stocktakeAudit($pdo,$actor,$branch,$id,'STOCKTAKE_COUNT_RECORDED',[
            'part_id'=>$part,'previous_count'=>$line['counted_on_hand'],
            'counted_on_hand'=>stockNumber($count),
            'expected_on_hand'=>$state['on_hand'],'reserved'=>$state['reserved'],
            'movement_id'=>$state['movement_id'],
            'variance'=>stockSigned($count-$before),'inventory_movement'=>false
        ],$reason);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Must run inside a transaction after actor authorization and shelf locks.
 * Both submission and sign-off must see the same complete, fresh part set.
 */
function stocktakeVerify(PDO $pdo,int $id,int $branch,array $states,bool $requireBalanced): array
{
    $lines=stockRun($pdo,'SELECT * FROM inventory_stocktake_lines
        WHERE stocktake_id=? ORDER BY part_id FOR UPDATE',[$id])->fetchAll();
    if (!$lines || count($lines)!==count($states)) {
        throw new DomainException('Branch catalog SKUs changed during this cycle. Cancel and open a new stocktake.');
    }
    $discrepancies=0;
    $counters=[];
    foreach ($lines as $line) {
        $part=(int)$line['part_id'];
        if (!isset($states[$part])) {
            throw new DomainException('The branch catalog changed since stocktake opened.');
        }
        if ($line['counted_on_hand']===null) {
            throw new DomainException('Every stocked SKU must be physically counted before submission.');
        }
        $state=$states[$part];
        if ($state['on_hand']!==(string)$line['counted_expected_on_hand'] ||
            $state['reserved']!==(string)$line['counted_reserved'] ||
            $state['movement_id']!==(int)$line['counted_movement_id']) {
            throw new DomainException('Stock or reservations changed since a physical count. Return to counting and recount affected SKUs.');
        }
        if (stockUnits((string)$line['counted_on_hand'])!==
            stockUnits($state['on_hand'])) $discrepancies++;
        $counters[]=(int)$line['counted_by_user_id'];
    }
    if ($requireBalanced && $discrepancies>0) {
        throw new DomainException('Physical count discrepancies remain. Return for verified count adjustments and fresh recount.');
    }
    return ['lines'=>count($lines),'discrepancies'=>$discrepancies,
        'counters'=>array_values(array_unique($counters))];
}

function stocktakeSubmit(PDO $pdo,int $actor,int $id,string $reason,bool $confirmed): void
{
    $reason=stocktakeReason($reason);
    if (!$confirmed) throw new DomainException('Confirm submission for independent sign-off.');
    $branch=(int)stocktakeLocator($pdo,$id)['branch_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $states=stocktakeLockShelves($pdo,$branch);
        $session=stockRun($pdo,'SELECT * FROM inventory_stocktakes
            WHERE stocktake_id=? AND branch_id=? FOR UPDATE',[$id,$branch])->fetch();
        if (!$session || $session['status']!=='OPEN') {
            throw new DomainException('This stocktake is not open for submission.');
        }
        if ((int)$session['opened_by_user_id']!==$actor) {
            throw new DomainException('Only the session opener may submit this physical count.');
        }
        $verified=stocktakeVerify($pdo,$id,$branch,$states,false);
        stockRun($pdo,"UPDATE inventory_stocktakes SET status='PENDING',
            submitted_at=UTC_TIMESTAMP(),decision_note=?
            WHERE stocktake_id=? AND status='OPEN'",[$reason,$id]);
        stocktakeAudit($pdo,$actor,$branch,$id,'STOCKTAKE_SUBMITTED',[
            'line_count'=>$verified['lines'],
            'discrepancies'=>$verified['discrepancies'],
            'approval_required'=>true,'inventory_movement'=>false
        ],$reason);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Independent ADMIN may either return it for recount or sign off ZERO variance. */
function stocktakeDecide(PDO $pdo,int $actor,int $id,
    string $decision,string $reason,bool $confirmed): void
{
    $reason=stocktakeReason($reason);
    if (!$confirmed || !in_array($decision,['signoff','return'],true)) {
        throw new DomainException('Confirm a separate administrator sign-off or recount return.');
    }
    $branch=(int)stocktakeLocator($pdo,$id)['branch_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $role=stockRun($pdo,'SELECT role FROM users WHERE user_id=?',[$actor])->fetchColumn();
        if ($role!=='ADMIN') {
            throw new DomainException('Only a separate branch administrator may sign off a stocktake.');
        }
        $states=$decision==='signoff'?stocktakeLockShelves($pdo,$branch):[];
        $session=stockRun($pdo,'SELECT * FROM inventory_stocktakes
            WHERE stocktake_id=? AND branch_id=? FOR UPDATE',[$id,$branch])->fetch();
        if (!$session || $session['status']!=='PENDING') {
            throw new DomainException('Only a submitted stocktake can be reviewed.');
        }
        if ((int)$session['opened_by_user_id']===$actor) {
            throw new DomainException('The stocktake opener cannot independently sign off their session.');
        }
        // A person who counted any line cannot independently sign off the same batch.
        $own=stockRun($pdo,'SELECT 1 FROM inventory_stocktake_lines
            WHERE stocktake_id=? AND counted_by_user_id=? LIMIT 1',[$id,$actor])->fetchColumn();
        $everCounted=stockRun($pdo,"SELECT 1 FROM audit_events
            WHERE actor_user_id=? AND entity_type='branch' AND entity_id=?
              AND action='STOCKTAKE_COUNT_RECORDED'
              AND CAST(JSON_UNQUOTE(JSON_EXTRACT(after_data,'$.stocktake_id')) AS UNSIGNED)=?
            LIMIT 1",[$actor,$branch,$id])->fetchColumn();
        if ($own || $everCounted) {
            throw new DomainException('The person who physically counted a line cannot sign off that batch.');
        }
        if ($decision==='signoff') {
            $verified=stocktakeVerify($pdo,$id,$branch,$states,true);
            stockRun($pdo,"UPDATE inventory_stocktakes
                SET status='SIGNED_OFF',signed_by_user_id=?,signed_at=UTC_TIMESTAMP(),
                    decision_note=? WHERE stocktake_id=? AND status='PENDING'",
                [$actor,$reason,$id]);
        } else {
            stockRun($pdo,"UPDATE inventory_stocktakes
                SET status='OPEN',submitted_at=NULL,decision_note=?
                WHERE stocktake_id=? AND status='PENDING'",[$reason,$id]);
            $verified=['lines'=>0,'discrepancies'=>null];
        }
        stocktakeAudit($pdo,$actor,$branch,$id,
            $decision==='signoff'?'STOCKTAKE_SIGNED_OFF':'STOCKTAKE_RETURNED',[
                'line_count'=>$verified['lines'],
                'discrepancies'=>$verified['discrepancies'],
                'status'=>$decision==='signoff'?'SIGNED_OFF':'OPEN',
                'inventory_movement'=>false
            ],$reason);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function stocktakeCancel(PDO $pdo,int $actor,int $id,string $reason,bool $confirmed): void
{
    $reason=stocktakeReason($reason);
    if (!$confirmed) throw new DomainException('Confirm cancellation of the open stocktake.');
    $branch=(int)stocktakeLocator($pdo,$id)['branch_id'];
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $session=stockRun($pdo,'SELECT * FROM inventory_stocktakes
            WHERE stocktake_id=? AND branch_id=? FOR UPDATE',[$id,$branch])->fetch();
        if (!$session || $session['status']!=='OPEN') {
            throw new DomainException('Only an open stocktake can be cancelled.');
        }
        if ((int)$session['opened_by_user_id']!==$actor) {
            throw new DomainException('Only the opener can cancel an unfinished stocktake.');
        }
        stockRun($pdo,"UPDATE inventory_stocktakes SET status='CANCELLED',decision_note=?
            WHERE stocktake_id=? AND status='OPEN'",[$reason,$id]);
        stocktakeAudit($pdo,$actor,$branch,$id,'STOCKTAKE_CANCELLED',[
            'period_ym'=>$session['period_ym'],'inventory_movement'=>false
        ],$reason);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Read-only records; 10 rows/page and last five cycles. Never expose other branches. */
function stocktakeDesk(PDO $pdo,int $actor,int $branch,int $page=1,int $requestedId=0): array
{
    stocktakeAccess($pdo,$actor,$branch);
    $recent=stockRun($pdo,'SELECT stocktake_id,period_ym,status,opened_at,submitted_at,
        signed_at,opened_by_user_id,signed_by_user_id,decision_note
        FROM inventory_stocktakes WHERE branch_id=?
        ORDER BY stocktake_id DESC LIMIT 5',[$branch])->fetchAll();
    $session=null;
    if ($requestedId>0) {
        $session=stockRun($pdo,'SELECT * FROM inventory_stocktakes
            WHERE stocktake_id=? AND branch_id=?',[$requestedId,$branch])->fetch();
        if (!$session) throw new DomainException('This stocktake is unavailable in the assigned branch.');
    } else {
        $session=stockRun($pdo,"SELECT * FROM inventory_stocktakes
            WHERE branch_id=? AND status IN ('OPEN','PENDING')
            ORDER BY stocktake_id DESC LIMIT 1",[$branch])->fetch();
        if (!$session && $recent) {
            $session=stockRun($pdo,'SELECT * FROM inventory_stocktakes
                WHERE stocktake_id=? AND branch_id=?',
                [(int)$recent[0]['stocktake_id'],$branch])->fetch();
        }
    }
    $out=['session'=>$session,'recent'=>$recent,'lines'=>[],'total'=>0,
        'counted'=>0,'discrepancies'=>0,'page'=>1,'pages'=>1,'stale'=>0];
    if (!$session) return $out;
    $id=(int)$session['stocktake_id'];
    $totals=stockRun($pdo,"SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN counted_at IS NOT NULL THEN 1 ELSE 0 END),0) AS counted,
        COALESCE(SUM(CASE WHEN counted_at IS NOT NULL
          AND counted_on_hand<>counted_expected_on_hand THEN 1 ELSE 0 END),0) AS disagreements
        FROM inventory_stocktake_lines WHERE stocktake_id=?",[$id])->fetch();
    $out['total']=(int)$totals['total'];
    $out['counted']=(int)$totals['counted'];
    $out['discrepancies']=(int)$totals['disagreements'];
    $out['pages']=max(1,(int)ceil($out['total']/10));
    $out['page']=min($out['pages'],max(1,$page));
    $offset=($out['page']-1)*10;
    $rows=stockRun($pdo,"SELECT l.*,p.part_name,p.sku,p.is_active,
        COALESCE(i.quantity_on_hand,-1) AS current_on_hand,
        COALESCE((SELECT SUM(r.quantity) FROM stock_reservations r
          WHERE r.branch_id=? AND r.part_id=l.part_id),0) AS current_reserved,
        COALESCE((SELECT MAX(m.inventory_transaction_id) FROM inventory_transactions m
          WHERE m.branch_id=? AND m.part_id=l.part_id),0) AS current_movement
        FROM inventory_stocktake_lines l
        JOIN parts p ON p.part_id=l.part_id
        LEFT JOIN branch_inventory i ON i.branch_id=? AND i.part_id=l.part_id
        WHERE l.stocktake_id=? ORDER BY l.part_id LIMIT 10 OFFSET $offset",
        [$branch,$branch,$branch,$id])->fetchAll();
    foreach ($rows as &$row) {
        $row['stale']=$row['counted_at']!==null && (
            (string)$row['counted_expected_on_hand']!==(string)$row['current_on_hand'] ||
            (string)$row['counted_reserved']!==(string)$row['current_reserved'] ||
            (int)$row['counted_movement_id']!==(int)$row['current_movement']);
    }
    unset($row);
    $out['lines']=$rows;
    return $out;
}
