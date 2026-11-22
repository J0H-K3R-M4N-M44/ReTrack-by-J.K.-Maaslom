<?php
declare(strict_types=1);
// Disposable test database only. Each invocation has an independent staff/branch fixture.
if (PHP_SAPI!=='cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/valuation.php';
[$sender,$cashier,,$receiver,$technician]=$staff;
$cashCountBefore=(int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])->fetchColumn();

$destination=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-VAL-DEST','Financial loss valuation receiving branch']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$receiver['user_id'],$destination]);
$operationalReviewer=insertFixture($pdo,"INSERT INTO users
    (full_name,email,password_hash,role) VALUES (?,?,?,'MANAGER')",
    ['Independent physical loss manager',$run.'-stockreview@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$operationalReviewer,$branch]);
$financeAdmin=insertFixture($pdo,"INSERT INTO users
    (full_name,email,password_hash,role) VALUES (?,?,?,'ADMIN')",
    ['Independent finance administrator',$run.'-financial@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$financeAdmin,$branch]);
$unrelated=insertFixture($pdo,"INSERT INTO users
    (full_name,email,password_hash,role) VALUES (?,?,?,'ADMIN')",
    ['Unrelated finance administrator',$run.'-unrelated@example.test','unused']);
$unrelatedBranch=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-VAL-OTHER','Unrelated workshop']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$unrelated,$unrelatedBranch]);

$sku=$run.'-FIN-COST';
$part=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$sku,'Verified acquisition cost SSD']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,4)',[$branch,$part]);
$dispatchNote='Three SSDs physically counted and dispatched under sealed manifest.';
$receiptNote='Independent receiving manager inspected and counted first sealed SSD.';
$missingNote='Two SSDs missing after courier check and physical crate investigation.';
$reportNote='Receiving manager inspected all packages and recorded two absent SSDs.';
$opDecision='Independent origin manager verified signed dispatch and missing receipt records.';
$reason='Verified per-unit purchase cost against supplier invoice and original part SKU.';
$source='SUPPLIER_INVOICE';
$costRef='SUPPLIER-INVOICE-'.$run.'-001';
$financeReason='Source-branch administrator verified supplier invoice, cost, physical quantity and all recoveries.';

$code=partsTransferDispatch($pdo,$sender['user_id'],$branch,$destination,
    $sku.',3',$dispatchNote,true);
$itemId=(int)stockRun($pdo,'SELECT x.inventory_transfer_item_id
    FROM inventory_transfer_items x JOIN inventory_transfers t
    ON t.inventory_transfer_id=x.inventory_transfer_id
    WHERE t.transfer_code=?',[$code])->fetchColumn();
partsTransferReceive($pdo,$receiver['user_id'],$itemId,'0.000',
    'receive','1.000',$receiptNote,true);
partsTransferReceive($pdo,$receiver['user_id'],$itemId,'1.000',
    'missing','0.000',$missingNote,true);
$lossId=partsLossReport($pdo,$receiver['user_id'],$itemId,'1.000',
    'SIGNED-MISSING-'.$run,$reportNote,true);
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true),'Only independently approved operational losses');
partsLossDecide($pdo,$operationalReviewer,$lossId,'approve',$opDecision,true);

expectResult(lossValuationAmount(2000,125025)==='2500.50',
    'Two missing units at PHP 1,250.25 must be PHP 2,500.50.');
expectResult(lossValuationAmount(500,1)==='0.01',
    'Half-cent extended acquisition cost must round up exactly once.');
expectResult(lossValuationAmount(1499,101)==='1.51',
    'Fractional unit × PHP 1.01 calculation must avoid floating-point rounding.');
expectResult(lossValuationAmount(0,125025)==='0.00',
    'Fully recovered stock should have zero remaining financial exposure.');
expectRefusal(fn()=>lossValuationUnitCents('100.005'),'at most two decimals');
expectRefusal(fn()=>lossValuationUnitCents('-20.00'),'at most two decimals');
expectRefusal(fn()=>lossValuationUnitCents('1000000.01'),'PHP 0.01');
expectRefusal(fn()=>lossValuationUnitCents('0.00'),'PHP 0.01');
expectRefusal(fn()=>lossValuationAmount(1000000001,100),'pilot quantity');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25','UNKNOWN',$costRef,$reason,true),'verified purchase-cost basis');
expectRefusal(fn()=>partsValuationPropose($pdo,$cashier['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true),'branch manager');
expectRefusal(fn()=>partsValuationPropose($pdo,$unrelated,$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true),'not assigned');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'1.000',
    '1250.25',$source,$costRef,$reason,true),'remaining loss quantity changed');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,'short',$reason,true),'source reference');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,false),'Confirm');

$first=partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true);
expectResult($first>0,'First valuation was not recorded.');
$row=stockRun($pdo,'SELECT * FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$first])->fetch();
expectResult($row['status']==='PENDING' && $row['revision_no']==1 &&
    $row['remaining_quantity']==='2.000' &&
    $row['unit_cost_php']==='1250.25' && $row['exposure_php']==='2500.50' &&
    $row['currency']==='PHP','Valuation quantity, cost and currency snapshot are incorrect.');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true),'existing financial valuation');
expectRefusal(fn()=>partsValuationDecide($pdo,$cashier['user_id'],$first,
    'approve',$financeReason,true),'branch manager');
expectRefusal(fn()=>partsValuationDecide($pdo,$receiver['user_id'],$first,
    'approve',$financeReason,true),'administrator');
expectRefusal(fn()=>partsValuationDecide($pdo,$operationalReviewer,$first,
    'approve',$financeReason,true),'administrator');
expectRefusal(fn()=>partsValuationDecide($pdo,$unrelated,$first,
    'approve',$financeReason,true),'not assigned');
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$first,
    'approve','short',true),'20–500');
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$first,
    'approve',$financeReason,false),'Confirm');
partsValuationDecide($pdo,$financeAdmin,$first,'reject',
    'This supplier cost needs an additional signed invoice reference.',true);
expectResult(stockRun($pdo,'SELECT status FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$first])->fetchColumn()==='REJECTED',
    'Rejected valuation was not retained.');
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$first,
    'approve',$financeReason,true),'no longer awaiting');

$second=partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,'SUPPLIER-INVOICE-'.$run.'-VERIFIED',$reason,true);
partsValuationDecide($pdo,$financeAdmin,$second,'approve',$financeReason,true);
expectResult(stockRun($pdo,'SELECT status FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$second])->fetchColumn()==='APPROVED',
    'Independently approved valuation was not recorded.');
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$second,
    'approve',$financeReason,true),'no longer awaiting');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1250.25',$source,$costRef,$reason,true),'already have an approved valuation');

// A later recovery must make any older monetary estimate HISTORICAL, not rewrite it.
// Pending physical recovery blocks all valuation approvals until independently resolved.
$physicalReport='One originally missing SSD was found intact and held in the secured review bin.';
$recoveryRef='RECOVERED-'.$run.'-001';
$third=partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1249.25','VERIFIED_COST_RECORD','STOCK-COST-CORRECTION-'.$run,$reason,true);
$recovery=partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000',$recoveryRef,$physicalReport,true);
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$third,
    'approve',$financeReason,true),'Resolve physically recovered parts');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'2.000',
    '1249.25',$source,$costRef,$reason,true),'Resolve physically recovered parts');
partsRecoveryDecide($pdo,$operationalReviewer,$recovery,'approve',
    'Independent source-branch manager verified the recovered SSD was usable.',true);
expectResult(stockShelfState($pdo,$destination,$part)['on_hand']==='2.000',
    'Physical recovery did not credit just one recovered SSD.');
expectRefusal(fn()=>partsValuationDecide($pdo,$financeAdmin,$third,
    'approve',$financeReason,true),'Physical loss changed');
partsValuationDecide($pdo,$financeAdmin,$third,'reject',
    'Physical receipt superseded the original two-unit cost correction snapshot.',true);
expectResult(stockRun($pdo,'SELECT exposure_php FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$second])->fetchColumn()==='2500.50',
    'Original approved monetary valuation must never be silently overwritten.');

$fourth=partsValuationPropose($pdo,$receiver['user_id'],$lossId,'1.000',
    '1250.25',$source,$costRef,$reason,true);
partsValuationDecide($pdo,$financeAdmin,$fourth,'approve',$financeReason,true);
expectResult(stockRun($pdo,'SELECT exposure_php FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$fourth])->fetchColumn()==='1250.25',
    'Updated physical exposure must reflect just one still-missing part.');

// Complete physical recovery: a new zero-exposure assessment is permitted.
$finalRecovery=partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'2.000',
    '1.000','SIGNED-FOUND-'.$run.'-002',
    'Final sealed SSD located, counted and isolated for approved usable release.',true);
partsRecoveryDecide($pdo,$operationalReviewer,$finalRecovery,'approve',
    'Independent origin manager verified the last SSD is fit for usable inventory.',true);
$zero=partsValuationPropose($pdo,$receiver['user_id'],$lossId,'0.000',
    '1250.25',$source,$costRef,
    'Physical recovery fully accounts for all originally lost stock; net current exposure is zero.',true);
partsValuationDecide($pdo,$financeAdmin,$zero,'approve',$financeReason,true);
expectResult(stockRun($pdo,'SELECT exposure_php FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$zero])->fetchColumn()==='0.00',
    'Fully recovered inventory should have zero current approved loss exposure.');
expectRefusal(fn()=>partsValuationPropose($pdo,$receiver['user_id'],$lossId,'0.000',
    '1250.25',$source,$costRef,$reason,true),'already have an approved valuation');
expectResult(stockRun($pdo,'SELECT status FROM stock_transfer_loss_cases
    WHERE loss_case_id=?',[$lossId])->fetchColumn()==='APPROVED',
    'Historical approved operational loss was improperly modified.');
$historic=stockRun($pdo,'SELECT revision_no,status,exposure_php FROM stock_transfer_loss_valuations
    WHERE loss_case_id=? ORDER BY valuation_id',[$lossId])->fetchAll();
expectResult(count($historic)===5 &&
    array_column($historic,'status')===['REJECTED','APPROVED','REJECTED','APPROVED','APPROVED'] &&
    array_column($historic,'exposure_php')===['2500.50','2500.50','2498.50','1250.25','0.00'],
    'Superseded monetary proposals and independent decisions were not preserved.');
expectResult(count(partsValuationHistory($pdo,$financeAdmin,$lossId))===5,
    'Branch-authorized valuation history does not return all five revisions.');
expectRefusal(fn()=>partsValuationHistory($pdo,$unrelated,$lossId),'unavailable');
expectRefusal(fn()=>partsValuationHistory($pdo,$cashier['user_id'],$lossId),'unavailable');
expectResult(stockShelfState($pdo,$branch,$part)['on_hand']==='1.000' &&
    stockShelfState($pdo,$destination,$part)['on_hand']==='3.000',
    'Monetary valuation inappropriately changed physical stock balances.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_OUT'",
    [$itemId])->fetchColumn()===1 &&
    (int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_IN'",
    [$itemId])->fetchColumn()===3,
    'Valuation generated an extra stock movement instead of preserving physical history.');
expectResult((int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])->fetchColumn()===$cashCountBefore,
    'The financial cost register must not create customer payment activity.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='inventory_transfer_item' AND entity_id=?
    AND action IN ('LOSS_VALUATION_PROPOSED','LOSS_VALUATION_APPROVED','LOSS_VALUATION_REJECTED')",
    [$itemId])->fetchColumn()===10,
    'Every financial proposal and independent outcome must be audited.');
echo "PASS: PHP acquisition-cost valuations, reviewer separation, stale recoveries, five immutable revisions and zero monetary side effects.\n";
