<?php
declare(strict_types=1);
require_once __DIR__ . '/stock_ops.php';

/**
 * Parse one catalog SKU and a physical quantity on each line.
 * This uses existing inventory transfer tables; there is no separate stock balance.
 */
function partsTransferLines(PDO $pdo, string $input): array
{
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim($input)) ?: [])));
    if (!$lines || count($lines) > 20) {
        throw new DomainException('Enter 1–20 SKU,quantity lines.');
    }
    $items = [];
    foreach ($lines as $line) {
        if (!preg_match('/^([A-Za-z0-9][A-Za-z0-9._-]{0,79})\s*,\s*(\d+(?:\.\d{1,3})?)$/D', $line, $match)) {
            throw new DomainException('Use one SKU,quantity per line (for example SSD-1,2).');
        }
        $units = stockUnits($match[2]);
        if ($units <= 0) {
            throw new DomainException('Every dispatched quantity must be positive.');
        }
        $record = stockRun($pdo, 'SELECT part_id,sku FROM parts WHERE sku=? AND is_active=1',
            [$match[1]])->fetch();
        if (!$record) {
            throw new DomainException('The selected SKU is not an active catalog part.');
        }
        $id = (int)$record['part_id'];
        if (isset($items[$id])) {
            throw new DomainException('Do not repeat a part SKU within the same transfer.');
        }
        $items[$id] = ['part_id'=>$id, 'sku'=>$record['sku'], 'quantity'=>stockNumber($units)];
    }
    ksort($items, SORT_NUMERIC); // Shelf locks must be acquired in consistent order.
    return array_values($items);
}

/**
 * Origin stock is deducted at dispatch. Only unreserved stock can travel.
 * The negative TRANSFER_OUT movement remains even if receipt reports a loss.
 */
function partsTransferDispatch(PDO $pdo, int $actorId, int $origin, int $destination,
    string $lines, string $reason, bool $confirmed): string
{
    $reason = trim($reason);
    if (!$confirmed || $origin <= 0 || $destination <= 0 || $origin === $destination ||
        mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
        throw new DomainException('Confirm a distinct receiving branch and a dispatch reason of 10–500 characters.');
    }
    $items = partsTransferLines($pdo, $lines);
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo, $actorId, $origin);
        $to = stockRun($pdo, 'SELECT branch_id FROM branches
            WHERE branch_id=? AND is_active=1 FOR UPDATE', [$destination])->fetchColumn();
        if (!$to) {
            throw new DomainException('The receiving branch is not active.');
        }
        foreach ($items as $item) {
            $state = stockShelfState($pdo, $origin, $item['part_id'], true);
            $available = stockUnits($state['on_hand']) - stockUnits($state['reserved']);
            if (stockUnits($item['quantity']) > $available) {
                throw new DomainException('Not enough unreserved source stock for SKU ' . $item['sku'] . '.');
            }
        }
        $code = 'PT-' . strtoupper(bin2hex(random_bytes(7)));
        stockRun($pdo, "INSERT INTO inventory_transfers
            (transfer_code,from_branch_id,to_branch_id,status,initiated_by_user_id,dispatched_at,notes)
            VALUES (?,?,?,'IN_TRANSIT',?,UTC_TIMESTAMP(),?)",
            [$code,$origin,$destination,$actorId,$reason]);
        $transferId = (int)$pdo->lastInsertId();
        foreach ($items as $item) {
            $id = $item['part_id'];
            $units = stockUnits($item['quantity']);
            $state = stockShelfState($pdo, $origin, $id, true);
            $after = stockNumber(stockUnits($state['on_hand']) - $units);
            stockRun($pdo, 'UPDATE branch_inventory SET quantity_on_hand=?
                WHERE branch_id=? AND part_id=?', [$after,$origin,$id]);
            stockRun($pdo, "INSERT INTO inventory_transfer_items
                (inventory_transfer_id,part_id,quantity,received_quantity,item_status)
                VALUES (?,?,?,0,'IN_TRANSIT')", [$transferId,$id,$item['quantity']]);
            $itemId = (int)$pdo->lastInsertId();
            stockRun($pdo, "INSERT INTO inventory_transactions
                (branch_id,part_id,transaction_type,quantity_delta,inventory_transfer_item_id,
                 reference_code,reason,performed_by_user_id,created_at)
                VALUES (?,?,'TRANSFER_OUT',?,?,?,?,?,UTC_TIMESTAMP())",
                [$origin,$id,stockSigned(-$units),$itemId,$code,$reason,$actorId]);
        }
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer',?,'PARTS_DISPATCHED',?,?,UTC_TIMESTAMP())",
            [$actorId,$transferId,json_encode([
                'code'=>$code,'from_branch_id'=>$origin,'to_branch_id'=>$destination,'items'=>$items
            ], JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
        return $code;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Only the receiving branch may confirm physical delivery, including late deliveries
 * following a shortage. EXPECTED received quantity is a stale-form and duplicate
 * submission guard. Missing reports do not move shelf stock.
 */
function partsTransferReceive(PDO $pdo, int $actorId, int $itemId, string $expected,
    string $action, string $amount, string $note, bool $confirmed): void
{
    $note = trim($note);
    if ($itemId <= 0 || !in_array($action, ['receive','missing'], true) ||
        !$confirmed || mb_strlen($note) < 10 || mb_strlen($note) > 500) {
        throw new DomainException('Confirm receiving or a missing-part report with a note of 10–500 characters.');
    }
    $expectedUnits = stockUnits($expected);
    $additional = $action === 'receive' ? stockUnits($amount) : 0;
    if ($action === 'receive' && $additional <= 0) {
        throw new DomainException('Physically received quantity must be greater than zero.');
    }
    $locator = stockRun($pdo, 'SELECT inventory_transfer_id FROM inventory_transfer_items
        WHERE inventory_transfer_item_id=?', [$itemId])->fetchColumn();
    if (!$locator) throw new DomainException('The transfer item could not be found.');
    $transferId = (int)$locator;
    $pdo->beginTransaction();
    try {
        $manifest = stockRun($pdo, 'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE', [$transferId])->fetch();
        if (!$manifest || $manifest['status']==='CANCELLED') {
            throw new DomainException('This transfer is not available for receipt.');
        }
        $destination = (int)$manifest['to_branch_id'];
        stockAuthorizeManager($pdo, $actorId, $destination);
        if ((int)$manifest['initiated_by_user_id'] === $actorId) {
            throw new DomainException('The dispatching manager cannot confirm their own physical delivery.');
        }
        $item = stockRun($pdo, 'SELECT * FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
            [$itemId,$transferId])->fetch();
        if (!$item || in_array($item['item_status'],['CANCELLED','RECEIVED','LOSS_CLOSED'],true)) {
            throw new DomainException('This item is already completed or is unavailable.');
        }
        $pendingLoss = stockRun($pdo,"SELECT loss_case_id FROM stock_transfer_loss_cases
            WHERE inventory_transfer_item_id=? AND status='PENDING' FOR UPDATE",
            [$itemId])->fetchColumn();
        if ($pendingLoss) {
            throw new DomainException('A formal loss report is pending approval. Resolve it before recording a late receipt.');
        }
        $shipped = stockUnits((string)$item['quantity']);
        $received = stockUnits((string)($item['received_quantity'] ?? '0'));
        if ($received !== $expectedUnits) {
            throw new DomainException('Receipt changed. Reload the manifest before recording another movement.');
        }
        $outstanding = $shipped - $received;
        if ($outstanding <= 0) {
            throw new DomainException('All dispatched stock has already been received.');
        }
        if ($action==='receive' && $additional > $outstanding) {
            throw new DomainException('You cannot receive more than the outstanding dispatched quantity.');
        }
        if ($action==='missing') {
            $reported = stockRun($pdo,"SELECT audit_event_id FROM audit_events
                WHERE entity_type='inventory_transfer_item' AND entity_id=?
                  AND action='PARTS_MISSING' LIMIT 1 FOR UPDATE",[$itemId])->fetchColumn();
            if ($reported) {
                throw new DomainException('This shortage is already reported. Record a late receipt instead.');
            }
        }
        $newReceived = $received + $additional;
        $itemStatus = $action==='missing' || $newReceived < $shipped ? 'EXCEPTION' : 'RECEIVED';
        if ($action==='receive') {
            // Destination inventory is created only when a person confirms physical receipt.
            stockRun($pdo, 'INSERT INTO branch_inventory
                (branch_id,part_id,quantity_on_hand) VALUES (?,?,0)
                ON DUPLICATE KEY UPDATE part_id=VALUES(part_id)',
                [$destination,(int)$item['part_id']]);
            $state = stockShelfState($pdo,$destination,(int)$item['part_id'],true);
            $balance = stockNumber(stockUnits($state['on_hand']) + $additional);
            stockRun($pdo, 'UPDATE branch_inventory SET quantity_on_hand=?
                WHERE branch_id=? AND part_id=?',[$balance,$destination,(int)$item['part_id']]);
            stockRun($pdo, "INSERT INTO inventory_transactions
                (branch_id,part_id,transaction_type,quantity_delta,inventory_transfer_item_id,
                 reference_code,reason,performed_by_user_id,created_at)
                VALUES (?,?,'TRANSFER_IN',?,?,?,?,?,UTC_TIMESTAMP())",
                [$destination,(int)$item['part_id'],stockNumber($additional),$itemId,
                    $manifest['transfer_code'],$note,$actorId]);
        }
        stockRun($pdo, 'UPDATE inventory_transfer_items
            SET received_quantity=?,item_status=?,receipt_note=?
            WHERE inventory_transfer_item_id=?',
            [stockNumber($newReceived),$itemStatus,$note,$itemId]);
        // The transfer row lock serializes receipts on different items in the same manifest.
        $states = stockRun($pdo, 'SELECT item_status FROM inventory_transfer_items
            WHERE inventory_transfer_id=? ORDER BY inventory_transfer_item_id FOR UPDATE',
            [$transferId])->fetchAll(PDO::FETCH_COLUMN);
        $allReceived = count($states)>0 && !array_diff($states,['RECEIVED']);
        $allTerminal = count($states)>0 && !array_diff($states,['RECEIVED','LOSS_CLOSED']);
        $anyLoss = in_array('LOSS_CLOSED',$states,true);
        $anyException = in_array('EXCEPTION',$states,true);
        $manifestStatus = $allReceived ? 'RECEIVED'
            : ($allTerminal && $anyLoss ? 'LOSS_CLOSED'
            : (($anyException || $anyLoss) ? 'EXCEPTION' : 'IN_TRANSIT'));
        stockRun($pdo, 'UPDATE inventory_transfers
            SET status=?,received_by_user_id=?,received_at=CASE
                WHEN ? = \'RECEIVED\' THEN UTC_TIMESTAMP() ELSE NULL END
            WHERE inventory_transfer_id=?',
            [$manifestStatus,$actorId,$manifestStatus,$transferId]);
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,$itemId,$action==='receive' ? 'PARTS_RECEIVED' : 'PARTS_MISSING',
                json_encode(['received_quantity'=>stockNumber($received),'item_status'=>$item['item_status']],
                    JSON_THROW_ON_ERROR),
                json_encode(['received_quantity'=>stockNumber($newReceived),
                    'received_now'=>stockNumber($additional),'item_status'=>$itemStatus,
                    'manifest_status'=>$manifestStatus],JSON_THROW_ON_ERROR),$note]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** One page of one branch's transfers, including documented shortages. */
function partsTransferQueue(PDO $pdo, int $actorId, int $branchId, string $search,
    int $requestedPage): array
{
    $assigned = stockRun($pdo, 'SELECT 1 FROM user_branch_assignments a
        JOIN users u ON u.user_id=a.user_id JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND a.branch_id=? AND b.is_active=1
        AND u.is_active=1 AND u.role IN (\'ADMIN\',\'MANAGER\')',
        [$actorId,$branchId])->fetchColumn();
    if (!$assigned) throw new DomainException('This branch is not assigned to your account.');
    $pattern = likePattern(mb_substr(trim($search),0,60));
    $where = " FROM inventory_transfer_items item
        JOIN inventory_transfers t ON t.inventory_transfer_id=item.inventory_transfer_id
        JOIN parts p ON p.part_id=item.part_id
        LEFT JOIN stock_transfer_loss_cases loss ON loss.loss_case_id=(
            SELECT MAX(l.loss_case_id) FROM stock_transfer_loss_cases l
            WHERE l.inventory_transfer_item_id=item.inventory_transfer_item_id
        )
        LEFT JOIN stock_transfer_recoveries recovery ON recovery.recovery_id=(
            SELECT MAX(r.recovery_id) FROM stock_transfer_recoveries r
            WHERE r.loss_case_id=loss.loss_case_id
        )
        LEFT JOIN stock_transfer_loss_valuations valuation ON valuation.valuation_id=(
            SELECT MAX(v.valuation_id) FROM stock_transfer_loss_valuations v
            WHERE v.loss_case_id=loss.loss_case_id
        )
        LEFT JOIN stock_transfer_loss_valuations accepted_value ON accepted_value.valuation_id=(
            SELECT MAX(v.valuation_id) FROM stock_transfer_loss_valuations v
            WHERE v.loss_case_id=loss.loss_case_id AND v.status='APPROVED'
        )
        JOIN branches origin ON origin.branch_id=t.from_branch_id
        JOIN branches destination ON destination.branch_id=t.to_branch_id
        WHERE (t.from_branch_id=? OR t.to_branch_id=?)
        AND (t.transfer_code LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
    $params = [$branchId,$branchId,$pattern,$pattern];
    $total = (int)stockRun($pdo,'SELECT COUNT(*)'.$where,$params)->fetchColumn();
    $pages = max(1,(int)ceil($total/10));
    $page = min($pages,max(1,$requestedPage));
    $offset = ($page-1)*10;
    $records = stockRun($pdo, "SELECT item.inventory_transfer_item_id,
        item.quantity,item.received_quantity,item.item_status,item.receipt_note,
        t.transfer_code,t.from_branch_id,t.to_branch_id,t.initiated_by_user_id,
        t.status AS transfer_status,t.dispatched_at,
        EXISTS(SELECT 1 FROM audit_events a WHERE a.entity_type='inventory_transfer_item'
          AND a.entity_id=item.inventory_transfer_item_id AND a.action='PARTS_MISSING') AS missing_reported,
        loss.loss_case_id,loss.status AS loss_status,loss.missing_quantity AS loss_quantity,
        loss.evidence_reference,loss.report_reason,loss.reported_by_user_id,
        loss.reviewed_by_user_id,loss.review_reason,
        recovery.recovery_id,recovery.status AS recovery_status,
        recovery.quantity AS recovery_quantity,recovery.evidence_reference AS recovery_evidence_reference,
        recovery.report_reason AS recovery_report_reason,
        recovery.review_reason AS recovery_review_reason,
        recovery.reported_by_user_id AS recovery_reported_by,
        COALESCE((SELECT SUM(r.quantity) FROM stock_transfer_recoveries r
          WHERE r.loss_case_id=loss.loss_case_id AND r.status='APPROVED'),0)
          AS recovered_approved_quantity,
        valuation.valuation_id,valuation.revision_no AS valuation_revision,
        valuation.status AS valuation_status,
        valuation.remaining_quantity AS valuation_remaining,
        valuation.unit_cost_php AS valuation_unit_cost,
        valuation.exposure_php AS valuation_exposure,
        valuation.cost_basis AS valuation_basis,
        valuation.cost_reference AS valuation_reference,
        valuation.prepared_by_user_id AS valuation_preparer,
        accepted_value.valuation_id AS approved_valuation_id,
        accepted_value.remaining_quantity AS approved_valuation_remaining,
        accepted_value.exposure_php AS approved_valuation_exposure,
        p.part_name,p.sku,origin.branch_name AS origin_name,
        destination.branch_name AS destination_name
        ".$where." ORDER BY t.inventory_transfer_id DESC,
        item.inventory_transfer_item_id ASC LIMIT 10 OFFSET $offset",$params)->fetchAll();
    foreach ($records as &$item) {
        $item['outstanding'] = stockNumber(
            stockUnits((string)$item['quantity']) - stockUnits((string)$item['received_quantity']));
    }
    unset($item);
    return ['records'=>$records,'total'=>$total,'page'=>$page,'pages'=>$pages];
}
