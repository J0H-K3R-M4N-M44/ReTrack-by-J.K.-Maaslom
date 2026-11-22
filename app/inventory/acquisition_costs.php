<?php
declare(strict_types=1);
require_once __DIR__ . '/valuation.php';

/** Purchasing documents are manually checked by a different branch administrator. */
function partCostDetails(string $source, string $supplier, string $reference,
    string $date, string $reason): array
{
    $supplier=trim($supplier);
    $reference=trim($reference);
    $reason=trim($reason);
    if (!in_array($source,['SUPPLIER_INVOICE','PURCHASE_RECEIPT','OTHER_VERIFIED'],true) ||
        mb_strlen($supplier)<2 || mb_strlen($supplier)>120 ||
        mb_strlen($reference)<8 || mb_strlen($reference)>160 ||
        mb_strlen($reason)<20 || mb_strlen($reason)>500 ||
        !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$matches) ||
        !checkdate((int)$matches[2],(int)$matches[3],(int)$matches[1]) ||
        $date>'9999-12-31') {
        throw new DomainException('Provide a valid supplier, document type/date/reference and purchase-cost explanation.');
    }
    return [$supplier,$reference,$reason];
}

/**
 * One pending proposal per branch/SKU. Approval never affects on-hand inventory,
 * cash receipts, sales quotations or accounting journals.
 */
function partCostPropose(PDO $pdo, int $actor, int $branch, int $part, string $unitCost,
    string $source, string $supplier, string $reference, string $documentDate,
    string $reason, bool $confirmed): int
{
    if (!$confirmed || $branch<=0 || $part<=0) {
        throw new DomainException('Confirm an assigned branch and its cost document.');
    }
    $cents=lossValuationUnitCents($unitCost);
    [$supplier,$reference,$reason]=partCostDetails($source,$supplier,$reference,$documentDate,$reason);
    if ($documentDate>gmdate('Y-m-d')) {
        throw new DomainException('A supplier document date cannot be in the future.');
    }
    $pdo->beginTransaction();
    try {
        stockAuthorizeManager($pdo,$actor,$branch);
        $catalog=stockRun($pdo,'SELECT p.part_id FROM parts p
            JOIN branch_inventory i ON i.part_id=p.part_id
            WHERE p.part_id=? AND i.branch_id=? AND p.is_active=1 FOR UPDATE',
            [$part,$branch])->fetchColumn();
        if (!$catalog) {
            throw new DomainException('Choose an active catalog part stocked at the selected branch.');
        }
        // Locks serialize competing proposals for this branch and part.
        $history=stockRun($pdo,'SELECT cost_record_id,status,unit_cost_php,
            document_reference,document_date FROM part_acquisition_cost_records
            WHERE branch_id=? AND part_id=? ORDER BY cost_record_id DESC FOR UPDATE',
            [$branch,$part])->fetchAll();
        foreach ($history as $past) {
            if ($past['status']==='PENDING') {
                throw new DomainException('A cost document for this part already awaits independent review.');
            }
            if ($past['status']==='APPROVED' &&
                $past['document_reference']===$reference &&
                $past['document_date']===$documentDate &&
                lossValuationUnitCents((string)$past['unit_cost_php'])===$cents) {
                throw new DomainException('The same supplier cost evidence is already approved.');
            }
        }
        stockRun($pdo,"INSERT INTO part_acquisition_cost_records
            (branch_id,part_id,unit_cost_php,source_type,supplier_name,document_reference,
             document_date,explanation,prepared_by_user_id,prepared_at,status)
            VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),'PENDING')",
            [$branch,$part,lossValuationMoney($cents),$source,$supplier,
                $reference,$documentDate,$reason,$actor]);
        $id=(int)$pdo->lastInsertId();
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'part',?,'ACQUISITION_COST_PROPOSED',?,?,UTC_TIMESTAMP())",
            [$actor,$part,json_encode(['record_id'=>$id,'branch_id'=>$branch,
                'unit_cost_php'=>lossValuationMoney($cents),'document_reference'=>$reference,
                'document_date'=>$documentDate,'financial_posting'=>false],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Only a different assigned ADMIN can attest to checking the supplier evidence. */
function partCostDecide(PDO $pdo,int $actor,int $recordId,
    string $decision,string $reason,bool $confirmed): void
{
    $reason=trim($reason);
    if (!$confirmed || $recordId<=0 ||
        !in_array($decision,['approve','reject'],true) ||
        mb_strlen($reason)<20 || mb_strlen($reason)>500) {
        throw new DomainException('Confirm approval or rejection with a 20–500 character independent review.');
    }
    $locator=stockRun($pdo,'SELECT branch_id,part_id FROM part_acquisition_cost_records
        WHERE cost_record_id=?',[$recordId])->fetch();
    if (!$locator) throw new DomainException('The acquisition-cost record was not found.');
    $pdo->beginTransaction();
    try {
        $branch=(int)$locator['branch_id'];
        stockAuthorizeManager($pdo,$actor,$branch);
        $role=stockRun($pdo,'SELECT role FROM users WHERE user_id=?',[$actor])->fetchColumn();
        if ($role!=='ADMIN') {
            throw new DomainException('Only an independent branch administrator may approve purchase costs.');
        }
        $part=(int)$locator['part_id'];
        // Same ordering as proposals: actor, catalog/stock row, cost records.
        stockRun($pdo,'SELECT part_id FROM branch_inventory
            WHERE branch_id=? AND part_id=? FOR UPDATE',[$branch,$part]);
        $record=stockRun($pdo,'SELECT * FROM part_acquisition_cost_records
            WHERE cost_record_id=? AND branch_id=? AND part_id=? FOR UPDATE',
            [$recordId,$branch,$part])->fetch();
        if (!$record || $record['status']!=='PENDING') {
            throw new DomainException('This acquisition cost is no longer awaiting review.');
        }
        if ($actor===(int)$record['prepared_by_user_id']) {
            throw new DomainException('A different administrator must independently verify this supplier document.');
        }
        stockRun($pdo,"UPDATE part_acquisition_cost_records
            SET status=?,reviewed_by_user_id=?,reviewed_at=UTC_TIMESTAMP(),review_reason=?
            WHERE cost_record_id=? AND status='PENDING'",
            [$decision==='approve'?'APPROVED':'REJECTED',$actor,$reason,$recordId]);
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,before_data,after_data,reason,created_at)
            VALUES ('USER',?,'part',?,?,?, ?,?,UTC_TIMESTAMP())",
            [$actor,$part,$decision==='approve'?'ACQUISITION_COST_APPROVED':'ACQUISITION_COST_REJECTED',
                json_encode(['record_id'=>$recordId,'status'=>'PENDING'],JSON_THROW_ON_ERROR),
                json_encode(['record_id'=>$recordId,
                    'status'=>$decision==='approve'?'APPROVED':'REJECTED',
                    'branch_id'=>$branch,'unit_cost_php'=>$record['unit_cost_php'],
                    'document_reference'=>$record['document_reference'],
                    'inventory_movement'=>false,'financial_posting'=>false],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Only assigned branches can browse the cost evidence register. */
function partCostDesk(PDO $pdo,int $actor,int $branch,string $search,int $requestedPage): array
{
    $assigned=stockRun($pdo,"SELECT 1 FROM users u
        JOIN user_branch_assignments a ON a.user_id=u.user_id
        JOIN branches b ON b.branch_id=a.branch_id
        WHERE u.user_id=? AND a.branch_id=? AND u.is_active=1 AND b.is_active=1
          AND u.role IN ('ADMIN','MANAGER') LIMIT 1",[$actor,$branch])->fetchColumn();
    if (!$assigned) {
        throw new DomainException('Purchase costs are unavailable outside your assigned branch.');
    }
    $pattern=likePattern(mb_substr(trim($search),0,60));
    $where=" FROM part_acquisition_cost_records c
        JOIN parts p ON p.part_id=c.part_id
        WHERE c.branch_id=? AND (p.sku LIKE ? ESCAPE '!' OR
        c.document_reference LIKE ? ESCAPE '!')";
    $params=[$branch,$pattern,$pattern];
    $total=(int)stockRun($pdo,'SELECT COUNT(*)'.$where,$params)->fetchColumn();
    $pages=max(1,(int)ceil($total/10));
    $page=min($pages,max(1,$requestedPage));
    $offset=($page-1)*10;
    $rows=stockRun($pdo,"SELECT c.*,p.sku,p.part_name
        ".$where." ORDER BY c.cost_record_id DESC LIMIT 10 OFFSET $offset",$params)->fetchAll();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
}

/** Checked inside the valuation transaction under its source transfer lock. */
function partCostApproved(PDO $pdo,int $recordId,int $sourceBranch,int $partId): array
{
    if ($recordId<=0) throw new DomainException('Select an approved source-branch purchase cost.');
    $record=stockRun($pdo,"SELECT * FROM part_acquisition_cost_records
        WHERE cost_record_id=? AND branch_id=? AND part_id=?
          AND status='APPROVED' FOR UPDATE",
        [$recordId,$sourceBranch,$partId])->fetch();
    if (!$record) {
        throw new DomainException('Only an approved acquisition cost for the sending branch and exact part may be used.');
    }
    return $record;
}
