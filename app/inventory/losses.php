<?php
declare(strict_types=1);
require_once __DIR__ . '/transfers.php';

/**
 * Receiving managers open a formal case only after their missing-shipment event.
 * The physical manifest and original stock movement are never edited by reporting.
 */
function partsLossReport(PDO $pdo, int $actorId, int $itemId, string $expectedReceived,
    string $evidenceReference, string $reason, bool $confirmed): int
{
    $reason = trim($reason);
    $evidenceReference = trim($evidenceReference);
    if ($itemId <= 0 || !$confirmed || mb_strlen($reason) < 20 ||
        mb_strlen($reason) > 500 || mb_strlen($evidenceReference) < 8 ||
        mb_strlen($evidenceReference) > 160) {
        throw new DomainException('Confirm the missing quantity with an investigation note (20–500 characters) and evidence reference (8–160 characters).');
    }
    $expectedUnits = stockUnits($expectedReceived);
    $transferId = stockRun($pdo, 'SELECT inventory_transfer_id FROM inventory_transfer_items
        WHERE inventory_transfer_item_id=?', [$itemId])->fetchColumn();
    if (!$transferId) {
        throw new DomainException('The parts manifest item could not be found.');
    }
    $pdo->beginTransaction();
    try {
        // Match receiving lock order to serialize reports against late deliveries.
        $manifest = stockRun($pdo, 'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE', [(int)$transferId])->fetch();
        if (!$manifest || $manifest['status'] === 'CANCELLED') {
            throw new DomainException('The shipment is no longer available for a loss report.');
        }
        stockAuthorizeManager($pdo, $actorId, (int)$manifest['to_branch_id']);
        if ((int)$manifest['initiated_by_user_id'] === $actorId) {
            throw new DomainException('The dispatching manager cannot report their own shipment as lost.');
        }
        $item = stockRun($pdo, 'SELECT * FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
            [$itemId,(int)$transferId])->fetch();
        if (!$item || $item['item_status'] !== 'EXCEPTION') {
            throw new DomainException('Only an unresolved missing-part exception can be formally reported.');
        }
        $receivedUnits = stockUnits((string)($item['received_quantity'] ?? '0'));
        $outstanding = stockUnits((string)$item['quantity']) - $receivedUnits;
        if ($receivedUnits !== $expectedUnits || $outstanding <= 0) {
            throw new DomainException('The missing quantity changed. Reload and recount the shipment.');
        }
        $missingEvent = stockRun($pdo, "SELECT audit_event_id FROM audit_events
            WHERE entity_type='inventory_transfer_item' AND entity_id=?
                AND action='PARTS_MISSING'
            ORDER BY audit_event_id DESC LIMIT 1 FOR UPDATE", [$itemId])->fetchColumn();
        if (!$missingEvent) {
            throw new DomainException('Record a missing-part report before opening a formal loss case.');
        }
        $active = stockRun($pdo, "SELECT loss_case_id FROM stock_transfer_loss_cases
            WHERE inventory_transfer_item_id=? AND status IN ('PENDING','APPROVED')
            LIMIT 1 FOR UPDATE", [$itemId])->fetchColumn();
        if ($active) {
            throw new DomainException('This shipment item already has a pending or approved loss case.');
        }
        stockRun($pdo, "INSERT INTO stock_transfer_loss_cases
            (inventory_transfer_item_id,missing_audit_event_id,received_at_report,missing_quantity,
             evidence_reference,report_reason,reported_by_user_id,reported_at,status)
            VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(),'PENDING')",
            [$itemId,(int)$missingEvent,stockNumber($receivedUnits),stockNumber($outstanding),
                $evidenceReference,$reason,$actorId]);
        $caseId = (int)$pdo->lastInsertId();
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,'PARTS_LOSS_REPORTED',?,?,UTC_TIMESTAMP())",
            [$actorId,$itemId,json_encode([
                'loss_case_id'=>$caseId,'transfer_code'=>$manifest['transfer_code'],
                'missing_event_id'=>(int)$missingEvent,'evidence_reference'=>$evidenceReference,
                'physically_received'=>stockNumber($receivedUnits),
                'reported_missing'=>stockNumber($outstanding),
            ],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
        return $caseId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Independent origin-branch decision. Approval makes the physically absent
 * quantity an operational loss; it never posts another stock movement, since
 * TRANSFER_OUT already deducted that stock at dispatch.
 */
function partsLossDecide(PDO $pdo, int $actorId, int $caseId, string $decision,
    string $reviewReason, bool $confirmed): void
{
    $reviewReason = trim($reviewReason);
    if ($caseId <= 0 || !in_array($decision,['approve','reject'],true) ||
        !$confirmed || mb_strlen($reviewReason) < 20 || mb_strlen($reviewReason) > 500) {
        throw new DomainException('Confirm an approval or rejection with a review reason of 20–500 characters.');
    }
    $locator = stockRun($pdo, 'SELECT i.inventory_transfer_id
        FROM stock_transfer_loss_cases loss
        JOIN inventory_transfer_items i ON i.inventory_transfer_item_id=loss.inventory_transfer_item_id
        WHERE loss.loss_case_id=?', [$caseId])->fetchColumn();
    if (!$locator) {
        throw new DomainException('This loss case could not be found.');
    }
    $transferId = (int)$locator;
    $pdo->beginTransaction();
    try {
        $manifest = stockRun($pdo, 'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE', [$transferId])->fetch();
        if (!$manifest || $manifest['status'] === 'CANCELLED') {
            throw new DomainException('The shipment can no longer be reviewed.');
        }
        stockAuthorizeManager($pdo,$actorId,(int)$manifest['from_branch_id']);
        if ((int)$manifest['initiated_by_user_id'] === $actorId) {
            throw new DomainException('The dispatching manager cannot approve their own missing shipment.');
        }
        // Lock transfer item before the case, just like reporting and physical receipt.
        $itemId = (int)stockRun($pdo, 'SELECT inventory_transfer_item_id FROM stock_transfer_loss_cases
            WHERE loss_case_id=?', [$caseId])->fetchColumn();
        $item = stockRun($pdo, 'SELECT * FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
            [$itemId,$transferId])->fetch();
        $loss = stockRun($pdo, 'SELECT * FROM stock_transfer_loss_cases
            WHERE loss_case_id=? AND inventory_transfer_item_id=? FOR UPDATE',
            [$caseId,$itemId])->fetch();
        if (!$loss || $loss['status'] !== 'PENDING' || !$item) {
            throw new DomainException('This loss case is no longer awaiting review.');
        }
        if ((int)$loss['reported_by_user_id'] === $actorId) {
            throw new DomainException('A different manager must review the missing-part report.');
        }
        if ($item['item_status'] !== 'EXCEPTION' ||
            stockUnits((string)$item['received_quantity']) !== stockUnits((string)$loss['received_at_report']) ||
            stockUnits((string)$item['quantity']) - stockUnits((string)$item['received_quantity']) !==
                stockUnits((string)$loss['missing_quantity'])) {
            throw new DomainException('Shipment quantities changed. Reject the stale loss case and investigate.');
        }
        $approved = $decision === 'approve';
        stockRun($pdo, "UPDATE stock_transfer_loss_cases SET status=?,
            reviewed_by_user_id=?,reviewed_at=UTC_TIMESTAMP(),review_reason=?
            WHERE loss_case_id=? AND status='PENDING'",
            [$approved ? 'APPROVED' : 'REJECTED',$actorId,$reviewReason,$caseId]);
        if ($approved) {
            stockRun($pdo, "UPDATE inventory_transfer_items SET item_status='LOSS_CLOSED'
                WHERE inventory_transfer_item_id=? AND item_status='EXCEPTION'",[$itemId]);
        }
        $statuses = stockRun($pdo, 'SELECT item_status FROM inventory_transfer_items
            WHERE inventory_transfer_id=? ORDER BY inventory_transfer_item_id FOR UPDATE',
            [$transferId])->fetchAll(PDO::FETCH_COLUMN);
        $allReceived = count($statuses)>0 && !array_diff($statuses,['RECEIVED']);
        $allFinal = count($statuses)>0 && !array_diff($statuses,['RECEIVED','LOSS_CLOSED']);
        $anyLost = in_array('LOSS_CLOSED',$statuses,true);
        $anyException = in_array('EXCEPTION',$statuses,true);
        $status = $allReceived ? 'RECEIVED'
            : ($allFinal && $anyLost ? 'LOSS_CLOSED'
            : (($anyLost || $anyException) ? 'EXCEPTION' : 'IN_TRANSIT'));
        stockRun($pdo, "UPDATE inventory_transfers SET status=?,
            received_at=CASE WHEN ? = 'RECEIVED' THEN UTC_TIMESTAMP() ELSE NULL END
            WHERE inventory_transfer_id=?", [$status,$status,$transferId]);
        stockRun($pdo, "INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actorId,$itemId,$approved?'PARTS_LOSS_APPROVED':'PARTS_LOSS_REJECTED',
                json_encode(['case_id'=>$caseId,'status'=>'PENDING'],JSON_THROW_ON_ERROR),
                json_encode(['case_id'=>$caseId,'status'=>$approved?'APPROVED':'REJECTED',
                    'missing_quantity'=>$loss['missing_quantity'],
                    'received_quantity'=>$loss['received_at_report'],
                    'manifest_status'=>$status,'additional_stock_movement'=>false],
                    JSON_THROW_ON_ERROR),$reviewReason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
