<?php
declare(strict_types=1);
require_once __DIR__ . '/recoveries.php';

/**
 * The parts catalog has no trustworthy acquisition cost. Quote unit_price is a
 * customer SELLING price, not the stock purchase cost. Cost is therefore entered
 * from documented supplier evidence and independently approved in PHP only.
 * This register never changes stock, bill payments, refunds or GL accounts.
 */

/** Validate PHP unit acquisition cost in centavos without floating-point math. */
function lossValuationUnitCents(string $amount): int
{
    $amount=trim($amount);
    if (!preg_match('/^(0|[1-9]\d{0,6})(?:\.(\d{1,2}))?$/D',$amount,$parts)) {
        throw new DomainException('Enter a nonnegative PHP unit acquisition cost with at most two decimals.');
    }
    $cents=(int)$parts[1]*100+(int)str_pad($parts[2]??'',2,'0');
    if ($cents<1 || $cents>100000000) {
        throw new DomainException('Verified unit acquisition cost must be from PHP 0.01 to PHP 1,000,000.00.');
    }
    return $cents;
}

function lossValuationMoney(int $centavos): string
{
    if ($centavos<0) throw new DomainException('The monetary exposure cannot be negative.');
    return intdiv($centavos,100).'.'.str_pad((string)($centavos%100),2,'0',STR_PAD_LEFT);
}

/**
 * Quantity uses thousandths; money uses hundredths.
 * Round one final extended cost to nearest centavo, half-up, using integers.
 */
function lossValuationAmount(int $quantityUnits, int $unitCentavos): string
{
    if ($quantityUnits<0 || $quantityUnits>1000000000 ||
        $unitCentavos<1 || $unitCentavos>100000000) {
        throw new DomainException('The valuation exceeds the reviewed pilot quantity or cost limit.');
    }
    return lossValuationMoney(intdiv($quantityUnits*$unitCentavos+500,1000));
}

/** Call only while the corresponding manifest row is locked. */
function lossValuationPhysicalSnapshot(PDO $pdo, int $lossId, int $manifestId): array
{
    $itemId=(int)stockRun($pdo,'SELECT inventory_transfer_item_id
        FROM stock_transfer_loss_cases WHERE loss_case_id=?',[$lossId])->fetchColumn();
    if (!$itemId) throw new DomainException('The loss case was not found.');
    $item=stockRun($pdo,'SELECT * FROM inventory_transfer_items
        WHERE inventory_transfer_item_id=? AND inventory_transfer_id=? FOR UPDATE',
        [$itemId,$manifestId])->fetch();
    $loss=stockRun($pdo,'SELECT * FROM stock_transfer_loss_cases
        WHERE loss_case_id=? AND inventory_transfer_item_id=? FOR UPDATE',
        [$lossId,$itemId])->fetch();
    if (!$item || !$loss || $loss['status']!=='APPROVED') {
        throw new DomainException('Only independently approved operational losses can be valued.');
    }
    $original=stockUnits((string)$loss['missing_quantity']);
    $initialReceived=stockUnits((string)$loss['received_at_report']);
    $shipped=stockUnits((string)$item['quantity']);
    $received=stockUnits((string)($item['received_quantity']??'0'));
    $recoveries=stockRun($pdo,'SELECT quantity,status FROM stock_transfer_recoveries
        WHERE loss_case_id=? ORDER BY recovery_id FOR UPDATE',[$lossId])->fetchAll();
    $approvedRecovered=0;
    foreach ($recoveries as $recovery) {
        if ($recovery['status']==='PENDING') {
            throw new DomainException('Resolve physically recovered parts awaiting inspection before recording a monetary valuation.');
        }
        if ($recovery['status']==='APPROVED') {
            $approvedRecovered+=stockUnits((string)$recovery['quantity']);
        }
    }
    $remaining=$original-$approvedRecovered;
    if ($original<=0 || $remaining<0 || $shipped!==$initialReceived+$original ||
        $received!==$initialReceived+$approvedRecovered ||
        $remaining!==$shipped-$received ||
        !in_array($item['item_status'],['LOSS_CLOSED','RECEIVED'],true) ||
        ($remaining===0)!==($item['item_status']==='RECEIVED')) {
        throw new DomainException('The operational loss and physical recovery ledger do not reconcile.');
    }
    return ['loss'=>$loss,'item'=>$item,'remaining'=>$remaining,
        'original'=>$original,'recovered'=>$approvedRecovered,'item_id'=>$itemId];
}

function lossValuationSource(string $source, string $reference, string $explanation): array
{
    $reference=trim($reference);
    $explanation=trim($explanation);
    if (!in_array($source,[
        'SUPPLIER_INVOICE','PURCHASE_RECEIPT','VERIFIED_COST_RECORD','OTHER_VERIFIED'
    ],true) || mb_strlen($reference)<8 || mb_strlen($reference)>160 ||
        mb_strlen($explanation)<20 || mb_strlen($explanation)>500) {
        throw new DomainException('Choose a verified purchase-cost basis, source reference of 8–160 characters and explanation of 20–500 characters.');
    }
    return [$reference,$explanation];
}

/** Receiving branch proposes an append-only PHP valuation for the CURRENT loss. */
function partsValuationPropose(PDO $pdo, int $actorId, int $lossId,
    string $expectedMissing, string $unitCost, string $source,
    string $reference, string $explanation, bool $confirmed): int
{
    if (!$confirmed || $lossId<=0) {
        throw new DomainException('Confirm the cost source and current outstanding physical loss.');
    }
    $expected=stockUnits($expectedMissing);
    $cents=lossValuationUnitCents($unitCost);
    [$reference,$explanation]=lossValuationSource($source,$reference,$explanation);
    $manifestId=stockRun($pdo,'SELECT item.inventory_transfer_id
        FROM stock_transfer_loss_cases loss
        JOIN inventory_transfer_items item
            ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
        WHERE loss.loss_case_id=?',[$lossId])->fetchColumn();
    if (!$manifestId) throw new DomainException('The loss case was not found.');
    $pdo->beginTransaction();
    try {
        $manifest=stockRun($pdo,'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE',[(int)$manifestId])->fetch();
        if (!$manifest || $manifest['status']==='CANCELLED') {
            throw new DomainException('The transfer is unavailable for a financial valuation.');
        }
        stockAuthorizeManager($pdo,$actorId,(int)$manifest['to_branch_id']);
        if ($actorId===(int)$manifest['initiated_by_user_id']) {
            throw new DomainException('The original dispatcher cannot propose their own loss valuation.');
        }
        $physical=lossValuationPhysicalSnapshot($pdo,$lossId,(int)$manifestId);
        if ($physical['remaining']!==$expected) {
            throw new DomainException('The remaining loss quantity changed. Reload before preparing a valuation.');
        }
        $existing=stockRun($pdo,'SELECT valuation_id,revision_no,status
            FROM stock_transfer_loss_valuations WHERE loss_case_id=?
            ORDER BY valuation_id DESC FOR UPDATE',[$lossId])->fetchAll();
        foreach ($existing as $row) {
            if ($row['status']==='PENDING') {
                throw new DomainException('An existing financial valuation is awaiting independent approval.');
            }
        }
        $lastApproved=stockRun($pdo,"SELECT remaining_quantity,unit_cost_php
            FROM stock_transfer_loss_valuations
            WHERE loss_case_id=? AND status='APPROVED'
            ORDER BY valuation_id DESC LIMIT 1 FOR UPDATE",[$lossId])->fetch();
        if ($lastApproved &&
            stockUnits((string)$lastApproved['remaining_quantity'])===$expected &&
            lossValuationUnitCents((string)$lastApproved['unit_cost_php'])===$cents) {
            throw new DomainException('An unchanged physical quantity and unit cost already have an approved valuation.');
        }
        $revision=(int)($existing[0]['revision_no']??0)+1;
        $total=lossValuationAmount($physical['remaining'],$cents);
        stockRun($pdo,"INSERT INTO stock_transfer_loss_valuations
            (loss_case_id,revision_no,remaining_quantity,unit_cost_php,exposure_php,cost_basis,
             cost_reference,report_reason,prepared_by_user_id,prepared_at,status,currency)
            VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),'PENDING','PHP')",
            [$lossId,$revision,stockNumber($physical['remaining']),lossValuationMoney($cents),
                $total,$source,$reference,$explanation,$actorId]);
        $id=(int)$pdo->lastInsertId();
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,'LOSS_VALUATION_PROPOSED',?,?,UTC_TIMESTAMP())",
            [$actorId,$physical['item_id'],json_encode([
                'valuation_id'=>$id,'loss_case_id'=>$lossId,'revision'=>$revision,
                'remaining_quantity'=>stockNumber($physical['remaining']),
                'unit_cost_php'=>lossValuationMoney($cents),
                'exposure_php'=>$total,'currency'=>'PHP','cost_basis'=>$source,
                'cost_reference'=>$reference,'financial_posting'=>false
            ],JSON_THROW_ON_ERROR),$explanation]);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Different source-branch ADMIN reviews and freezes the submitted valuation.
 * Approval must recheck physical loss and all approved recoveries under locks.
 * No stock, cash, customer balance, tax or general ledger posting occurs.
 */
function partsValuationDecide(PDO $pdo, int $actorId, int $valuationId,
    string $decision, string $reviewReason, bool $confirmed): void
{
    $reviewReason=trim($reviewReason);
    if ($valuationId<=0 || !in_array($decision,['approve','reject'],true) ||
        !$confirmed || mb_strlen($reviewReason)<20 || mb_strlen($reviewReason)>500) {
        throw new DomainException('Confirm the independent valuation decision with an explanation of 20–500 characters.');
    }
    $locator=stockRun($pdo,'SELECT loss.loss_case_id,item.inventory_transfer_id
        FROM stock_transfer_loss_valuations valuation
        JOIN stock_transfer_loss_cases loss ON loss.loss_case_id=valuation.loss_case_id
        JOIN inventory_transfer_items item
            ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
        WHERE valuation.valuation_id=?',[$valuationId])->fetch();
    if (!$locator) throw new DomainException('The financial valuation was not found.');
    $lossId=(int)$locator['loss_case_id'];
    $pdo->beginTransaction();
    try {
        $manifest=stockRun($pdo,'SELECT * FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE',[(int)$locator['inventory_transfer_id']])->fetch();
        if (!$manifest || $manifest['status']==='CANCELLED') {
            throw new DomainException('The transfer is unavailable for valuation approval.');
        }
        stockAuthorizeManager($pdo,$actorId,(int)$manifest['from_branch_id']);
        $role=stockRun($pdo,'SELECT role FROM users WHERE user_id=?',[$actorId])->fetchColumn();
        if ($role!=='ADMIN') {
            throw new DomainException('Only an independent source-branch administrator may approve a monetary valuation.');
        }
        $itemId=(int)stockRun($pdo,'SELECT inventory_transfer_item_id FROM stock_transfer_loss_cases
            WHERE loss_case_id=?',[$lossId])->fetchColumn();
        stockRun($pdo,'SELECT inventory_transfer_item_id FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? FOR UPDATE',[$itemId]);
        $loss=stockRun($pdo,'SELECT * FROM stock_transfer_loss_cases
            WHERE loss_case_id=? FOR UPDATE',[$lossId])->fetch();
        $valuation=stockRun($pdo,'SELECT * FROM stock_transfer_loss_valuations
            WHERE valuation_id=? AND loss_case_id=? FOR UPDATE',[$valuationId,$lossId])->fetch();
        if (!$loss || !$valuation || $valuation['status']!=='PENDING') {
            throw new DomainException('This financial valuation is no longer awaiting review.');
        }
        if (in_array($actorId,[
            (int)$manifest['initiated_by_user_id'],
            (int)$loss['reported_by_user_id'],
            (int)$loss['reviewed_by_user_id'],
            (int)$valuation['prepared_by_user_id']
        ],true)) {
            throw new DomainException('A different administrator must independently review the financial loss.');
        }
        if ($decision==='approve') {
            $physical=lossValuationPhysicalSnapshot($pdo,$lossId,
                (int)$locator['inventory_transfer_id']);
            if ($physical['remaining']!==stockUnits((string)$valuation['remaining_quantity'])) {
                throw new DomainException('Physical loss changed. Reject this stale valuation and propose a revision.');
            }
            $actual=lossValuationAmount($physical['remaining'],
                lossValuationUnitCents((string)$valuation['unit_cost_php']));
            if ($actual!==(string)$valuation['exposure_php'] ||
                (string)$valuation['currency']!=='PHP') {
                throw new DomainException('The monetary snapshot no longer reconciles to the cost evidence.');
            }
        }
        stockRun($pdo,'UPDATE stock_transfer_loss_valuations
            SET status=?,reviewed_by_user_id=?,reviewed_at=UTC_TIMESTAMP(),review_reason=?
            WHERE valuation_id=? AND status=\'PENDING\'',
            [$decision==='approve'?'APPROVED':'REJECTED',$actorId,$reviewReason,$valuationId]);
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,?,?,?,?,UTC_TIMESTAMP())",
            [$actorId,$itemId,$decision==='approve'?'LOSS_VALUATION_APPROVED':'LOSS_VALUATION_REJECTED',
                json_encode(['valuation_id'=>$valuationId,'status'=>'PENDING'],JSON_THROW_ON_ERROR),
                json_encode([
                    'valuation_id'=>$valuationId,'loss_case_id'=>$lossId,
                    'status'=>$decision==='approve'?'APPROVED':'REJECTED',
                    'unit_cost_php'=>$valuation['unit_cost_php'],
                    'exposure_php'=>$valuation['exposure_php'],'currency'=>'PHP',
                    'financial_posting'=>false,'inventory_movement'=>false
                ],JSON_THROW_ON_ERROR),$reviewReason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Five latest historical assessments, scoped to assigned active transfer branches. */
function partsValuationHistory(PDO $pdo, int $actorId, int $lossId): array
{
    $case=stockRun($pdo,'SELECT transfer.from_branch_id,transfer.to_branch_id
        FROM stock_transfer_loss_cases loss
        JOIN inventory_transfer_items item ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
        JOIN inventory_transfers transfer ON transfer.inventory_transfer_id=item.inventory_transfer_id
        WHERE loss.loss_case_id=?',[$lossId])->fetch();
    if (!$case) throw new DomainException('Financial loss history is unavailable.');
    $check=stockRun($pdo,"SELECT 1 FROM users u
        JOIN user_branch_assignments a ON a.user_id=u.user_id
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE u.user_id=? AND u.is_active=1 AND u.role IN ('ADMIN','MANAGER')
          AND b.is_active=1 AND a.branch_id IN (?,?) LIMIT 1",
        [$actorId,(int)$case['from_branch_id'],(int)$case['to_branch_id']])->fetchColumn();
    if (!$check) throw new DomainException('Financial loss history is unavailable.');
    return stockRun($pdo,'SELECT valuation_id,revision_no,status,remaining_quantity,
        unit_cost_php,exposure_php,cost_basis,cost_reference,report_reason,
        prepared_by_user_id,prepared_at,reviewed_by_user_id,reviewed_at,review_reason
        FROM stock_transfer_loss_valuations WHERE loss_case_id=?
        ORDER BY revision_no DESC LIMIT 5',[$lossId])->fetchAll();
}
