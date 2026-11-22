<?php
declare(strict_types=1);
require_once __DIR__ . '/losses.php';

/**
 * A recovery is physical stock found AFTER an independently approved loss.
 * The receiving branch must isolate it outside usable stock while review is
 * pending. This action creates evidence, never an inventory movement.
 */
function partsRecoveryReport(PDO $pdo, int $actorId, int $lossId,
    string $expectedReceived, string $quantity, string $evidenceReference,
    string $reason, bool $confirmed): int
{
    $evidenceReference = trim($evidenceReference);
    $reason = trim($reason);
    if ($lossId <= 0 || !$confirmed || mb_strlen($evidenceReference) < 8 ||
        mb_strlen($evidenceReference) > 160 || mb_strlen($reason) < 20 ||
        mb_strlen($reason) > 500) {
        throw new DomainException('Confirm the recovered quantity, inspection reference (8–160 characters) and explanation (20–500 characters).');
    }
    $expected = stockUnits($expectedReceived);
    $units = stockUnits($quantity);
    if ($units <= 0) {
        throw new DomainException('Recovery must contain a positive physically found quantity.');
    }
    $transferId = stockRun($pdo, 'SELECT item.inventory_transfer_id
        FROM stock_transfer_loss_cases loss
        JOIN inventory_transfer_items item
          ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
        WHERE loss.loss_case_id=?', [$lossId])->fetchColumn();
    if (!$transferId) throw new DomainException('The approved loss case was not found.');
    $pdo->beginTransaction();
    try {
        // Transfer header first, then item, loss case and recovery records.
        $manifest = stockRun($pdo, 'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE', [(int)$transferId])->fetch();
        if (!$manifest || $manifest['status']==='CANCELLED') {
            throw new DomainException('The transfer is unavailable for physical recovery.');
        }
        stockAuthorizeManager($pdo,$actorId,(int)$manifest['to_branch_id']);
        if ((int)$manifest['initiated_by_user_id']===$actorId) {
            throw new DomainException('The dispatcher cannot verify their own recovered shipment.');
        }
        $itemId = (int)stockRun($pdo, 'SELECT inventory_transfer_item_id
            FROM stock_transfer_loss_cases WHERE loss_case_id=?',[$lossId])->fetchColumn();
        $item = stockRun($pdo, 'SELECT * FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
            [$itemId,(int)$transferId])->fetch();
        $loss = stockRun($pdo, 'SELECT * FROM stock_transfer_loss_cases
            WHERE loss_case_id=? FOR UPDATE',[$lossId])->fetch();
        if (!$loss || $loss['status']!=='APPROVED') {
            throw new DomainException('Only independently approved losses can be physically recovered.');
        }
        if (!$item || $item['item_status']!=='LOSS_CLOSED') {
            throw new DomainException('This shipment line has no outstanding approved loss.');
        }
        $received = stockUnits((string)($item['received_quantity'] ?? '0'));
        $shipped = stockUnits((string)$item['quantity']);
        $originalLoss = stockUnits((string)$loss['missing_quantity']);
        if ($received!==$expected || $originalLoss<=0) {
            throw new DomainException('Recovery snapshot changed. Reload the transfer line.');
        }
        $previous = stockRun($pdo, 'SELECT quantity,status FROM stock_transfer_recoveries
            WHERE loss_case_id=? FOR UPDATE',[$lossId])->fetchAll();
        $approved = 0;
        foreach ($previous as $entry) {
            if ($entry['status']==='PENDING') {
                throw new DomainException('A recovered quantity already awaits independent approval.');
            }
            if ($entry['status']==='APPROVED') {
                $approved += stockUnits((string)$entry['quantity']);
            }
        }
        // The original loss never changes. Its remaining missing quantity equals
        // dispatched minus all physically posted deliveries (including recoveries).
        $missing = $originalLoss-$approved;
        if ($missing <= 0 || $missing!==$shipped-$received) {
            throw new DomainException('Recovery does not reconcile to the original approved loss.');
        }
        if ($units>$missing) {
            throw new DomainException('Recovered quantity exceeds outstanding approved lost stock.');
        }
        stockRun($pdo, "INSERT INTO stock_transfer_recoveries
            (loss_case_id,quantity,expected_received_quantity,evidence_reference,
             report_reason,reported_by_user_id,reported_at,status)
            VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(),'PENDING')",
            [$lossId,stockNumber($units),stockNumber($received),$evidenceReference,$reason,$actorId]);
        $recoveryId=(int)$pdo->lastInsertId();
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,'PARTS_RECOVERY_REPORTED',?,?,UTC_TIMESTAMP())",
            [$actorId,(int)$item['inventory_transfer_item_id'],json_encode([
                'recovery_id'=>$recoveryId,'loss_case_id'=>$lossId,
                'transfer_code'=>$manifest['transfer_code'],
                'quantity'=>stockNumber($units),'expected_received'=>stockNumber($received),
                'evidence_reference'=>$evidenceReference,'stock_credited'=>false
            ],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
        return $recoveryId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * A separate source-branch manager must inspect the staged recovery evidence
 * before destination usable inventory is credited. Rejections create NO stock
 * movement. Approved recoveries post one unique TRANSFER_IN and preserve the
 * original LOSS_APPROVED audit history and immutable dispatched quantity.
 */
function partsRecoveryDecide(PDO $pdo, int $actorId, int $recoveryId,
    string $decision, string $reason, bool $confirmed): void
{
    $reason=trim($reason);
    if ($recoveryId<=0 || !in_array($decision,['approve','reject'],true) ||
        !$confirmed || mb_strlen($reason)<20 || mb_strlen($reason)>500) {
        throw new DomainException('Confirm an independent recovery approval or rejection with a reason of 20–500 characters.');
    }
    $locator=stockRun($pdo,'SELECT item.inventory_transfer_id
        FROM stock_transfer_recoveries recovery
        JOIN stock_transfer_loss_cases loss ON loss.loss_case_id=recovery.loss_case_id
        JOIN inventory_transfer_items item
          ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
        WHERE recovery.recovery_id=?',[$recoveryId])->fetchColumn();
    if (!$locator) throw new DomainException('The recovered-parts case was not found.');
    $transferId=(int)$locator;
    $pdo->beginTransaction();
    try {
        $manifest=stockRun($pdo,'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE',[$transferId])->fetch();
        if (!$manifest || $manifest['status']==='CANCELLED') {
            throw new DomainException('This shipment is unavailable for approval.');
        }
        stockAuthorizeManager($pdo,$actorId,(int)$manifest['from_branch_id']);
        if ((int)$manifest['initiated_by_user_id']===$actorId) {
            throw new DomainException('The dispatching manager cannot approve their own recovered shipment.');
        }
        $record=stockRun($pdo,'SELECT recovery.loss_case_id,loss.inventory_transfer_item_id
            FROM stock_transfer_recoveries recovery
            JOIN stock_transfer_loss_cases loss ON loss.loss_case_id=recovery.loss_case_id
            WHERE recovery.recovery_id=?',[$recoveryId])->fetch();
        $item=stockRun($pdo,'SELECT * FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
            [(int)$record['inventory_transfer_item_id'],$transferId])->fetch();
        $loss=stockRun($pdo,'SELECT * FROM stock_transfer_loss_cases
            WHERE loss_case_id=? FOR UPDATE',[(int)$record['loss_case_id']])->fetch();
        $recovery=stockRun($pdo,'SELECT * FROM stock_transfer_recoveries
            WHERE recovery_id=? AND loss_case_id=? FOR UPDATE',
            [$recoveryId,(int)$record['loss_case_id']])->fetch();
        if (!$item || !$loss || !$recovery || $recovery['status']!=='PENDING') {
            throw new DomainException('This recovery is no longer awaiting approval.');
        }
        if ($loss['status']!=='APPROVED' || $item['item_status']!=='LOSS_CLOSED') {
            throw new DomainException('The approved loss or physical transfer status changed.');
        }
        if ($actorId===(int)$recovery['reported_by_user_id'] ||
            $actorId===(int)$loss['reported_by_user_id']) {
            throw new DomainException('A different manager must independently approve the recovered stock.');
        }
        $received=stockUnits((string)($item['received_quantity'] ?? '0'));
        $shipped=stockUnits((string)$item['quantity']);
        $units=stockUnits((string)$recovery['quantity']);
        if ($received!==stockUnits((string)$recovery['expected_received_quantity'])) {
            throw new DomainException('Physical receipts changed; reject the stale recovery and recount.');
        }
        $previous=stockRun($pdo, "SELECT quantity FROM stock_transfer_recoveries
            WHERE loss_case_id=? AND status='APPROVED' FOR UPDATE",
            [(int)$loss['loss_case_id']])->fetchAll(PDO::FETCH_COLUMN);
        $approvedUnits=0;
        foreach ($previous as $q) $approvedUnits+=stockUnits((string)$q);
        $remaining=stockUnits((string)$loss['missing_quantity'])-$approvedUnits;
        if ($units<=0 || $remaining!==$shipped-$received || $units>$remaining) {
            throw new DomainException('Recovered quantity no longer matches the approved remaining loss.');
        }
        $movement=null;
        $itemState=$item['item_status'];
        $status=$manifest['status'];
        if ($decision==='approve') {
            $destination=(int)$manifest['to_branch_id'];
            $part=(int)$item['part_id'];
            // The pending part has been physically held, not available for sale.
            // Approval credits precisely the accepted units and no others.
            stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
                VALUES (?,?,0) ON DUPLICATE KEY UPDATE part_id=VALUES(part_id)',
                [$destination,$part]);
            $shelf=stockShelfState($pdo,$destination,$part,true);
            $after=stockNumber(stockUnits($shelf['on_hand'])+$units);
            stockRun($pdo,'UPDATE branch_inventory SET quantity_on_hand=?
                WHERE branch_id=? AND part_id=?',[$after,$destination,$part]);
            stockRun($pdo, "INSERT INTO inventory_transactions
                (branch_id,part_id,transaction_type,quantity_delta,inventory_transfer_item_id,
                 reference_code,reason,performed_by_user_id,created_at)
                VALUES (?,?,'TRANSFER_IN',?,?,?,?,?,UTC_TIMESTAMP())",
                [$destination,$part,stockNumber($units),(int)$item['inventory_transfer_item_id'],
                    'RECOVERY-'.$recoveryId,mb_substr($reason,0,500),$actorId]);
            $movement=(int)$pdo->lastInsertId();
            $newReceived=$received+$units;
            $itemState=$newReceived===$shipped?'RECEIVED':'LOSS_CLOSED';
            stockRun($pdo,'UPDATE inventory_transfer_items
                SET received_quantity=?,item_status=? WHERE inventory_transfer_item_id=?',
                [stockNumber($newReceived),$itemState,(int)$item['inventory_transfer_item_id']]);
        }
        stockRun($pdo,'UPDATE stock_transfer_recoveries
            SET status=?,reviewed_by_user_id=?,reviewed_at=UTC_TIMESTAMP(),
                review_reason=?,movement_id=? WHERE recovery_id=? AND status=\'PENDING\'',
            [$decision==='approve'?'APPROVED':'REJECTED',$actorId,$reason,$movement,$recoveryId]);
        if ($decision==='approve') {
            $statuses=stockRun($pdo,'SELECT item_status FROM inventory_transfer_items
                WHERE inventory_transfer_id=? ORDER BY inventory_transfer_item_id FOR UPDATE',
                [$transferId])->fetchAll(PDO::FETCH_COLUMN);
            $allReceived=count($statuses)>0 && !array_diff($statuses,['RECEIVED']);
            $allTerminal=count($statuses)>0 && !array_diff($statuses,['RECEIVED','LOSS_CLOSED']);
            $anyLost=in_array('LOSS_CLOSED',$statuses,true);
            $anyException=in_array('EXCEPTION',$statuses,true);
            $status=$allReceived?'RECEIVED'
                : ($allTerminal && $anyLost?'LOSS_CLOSED'
                : (($anyLost || $anyException)?'EXCEPTION':'IN_TRANSIT'));
            stockRun($pdo,"UPDATE inventory_transfers SET status=?,
                received_at=CASE WHEN ?='RECEIVED' THEN UTC_TIMESTAMP() ELSE NULL END,
                received_by_user_id=CASE WHEN ?='RECEIVED'
                    THEN COALESCE(received_by_user_id,?) ELSE received_by_user_id END
                WHERE inventory_transfer_id=?",
                [$status,$status,$status,(int)$recovery['reported_by_user_id'],$transferId]);
        }
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,(int)$item['inventory_transfer_item_id'],
                $decision==='approve'?'PARTS_RECOVERY_APPROVED':'PARTS_RECOVERY_REJECTED',
                json_encode(['recovery_id'=>$recoveryId,'original_loss_case_id'=>$loss['loss_case_id'],
                    'received_quantity'=>$item['received_quantity'],'status'=>$item['item_status']],
                    JSON_THROW_ON_ERROR),
                json_encode(['recovery_id'=>$recoveryId,'approved_quantity'=>
                    $decision==='approve'?stockNumber($units):'0.000',
                    'item_status'=>$itemState,'manifest_status'=>$status,
                    'movement_id'=>$movement],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
