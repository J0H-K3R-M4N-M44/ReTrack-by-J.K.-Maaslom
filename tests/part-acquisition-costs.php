<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/acquisition_costs.php';
[$sender,$cashier,,$receivingManager,$technician]=$staff;
$destination=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-CDEST','Cost audit receiving branch']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$receivingManager['user_id'],$destination]);
$costAdmin=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Document review administrator',$run.'-cost-audit@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$costAdmin,$branch]);
$financeAdmin=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Monetary review administrator',$run.'-money-audit@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$financeAdmin,$branch]);
$unrelatedBranch=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-COTHER','Unassigned acquisition branch']);
$outsider=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'MANAGER')",['Unassigned cost reviewer',$run.'-outsider@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$outsider,$unrelatedBranch]);
$sku=$run.'-PURCHASE';
$part=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$sku,'Verified purchase SSD']);
$otherPart=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$run.'-OTHER-SKU','Different replacement device part']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,5)',[$branch,$part]);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,5)',[$branch,$otherPart]);
$source='SUPPLIER_INVOICE';
$supplier='Regional Computer Parts Supply';
$ref='INVOICE-'.$run.'-SSD-2026';
$explanation='Checked supplier invoice for the exact purchased SSD, inclusive of documented acquisition cost.';
$review='Independently checked invoice identifiers, part identity, purchase date, supplier and unit cost.';
$costAtFirst='1200.25';
$date='2026-05-18';
$payments=(int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])->fetchColumn();
$stockStart=stockShelfState($pdo,$branch,$part)['on_hand'];

expectRefusal(fn()=>partCostPropose($pdo,$cashier['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,$ref,$date,$explanation,true),'branch manager');
expectRefusal(fn()=>partCostPropose($pdo,$technician['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,$ref,$date,$explanation,true),'branch manager');
expectRefusal(fn()=>partCostPropose($pdo,$outsider,$branch,$part,
    $costAtFirst,$source,$supplier,$ref,$date,$explanation,true),'not assigned');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,$ref,'2038-02-20',$explanation,true),'future');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    '0.00',$source,$supplier,$ref,$date,$explanation,true),'PHP 0.01');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    '1200.252',$source,$supplier,$ref,$date,$explanation,true),'at most two decimals');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,'short',$date,$explanation,true),'valid supplier');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,$ref,$date,$explanation,false),'Confirm');
$first=partCostPropose($pdo,$sender['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,$ref,$date,$explanation,true);
expectResult($first>0,'Supplier cost not recorded.');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    '1199.00',$source,$supplier,'DIFFERENT-'.$run,$date,$explanation,true),'already awaits');
expectRefusal(fn()=>partCostDecide($pdo,$cashier['user_id'],$first,'approve',$review,true),
    'branch manager');
expectRefusal(fn()=>partCostDecide($pdo,$receivingManager['user_id'],$first,
    'approve',$review,true),'administrator');
expectRefusal(fn()=>partCostDecide($pdo,$sender['user_id'],$first,'approve',$review,true),
    'administrator');
expectRefusal(fn()=>partCostDecide($pdo,$outsider,$first,'approve',$review,true),
    'not assigned');
expectRefusal(fn()=>partCostDecide($pdo,$costAdmin,$first,'approve',$review,false),
    'Confirm');
// Self-approval by a branch ADMIN is also blocked.
partCostDecide($pdo,$costAdmin,$first,'reject',
    'Supplier document is missing the signed receiving confirmation.',true);
expectRefusal(fn()=>partCostDecide($pdo,$costAdmin,$first,'approve',$review,true),
    'no longer awaiting');
$byAdmin=partCostPropose($pdo,$costAdmin,$branch,$part,
    $costAtFirst,$source,$supplier,'ADMIN-COST-'.$run,$date,$explanation,true);
expectRefusal(fn()=>partCostDecide($pdo,$costAdmin,$byAdmin,'approve',$review,true),
    'different administrator');
partCostDecide($pdo,$financeAdmin,$byAdmin,'approve',$review,true);
expectResult(stockRun($pdo,'SELECT status FROM part_acquisition_cost_records
    WHERE cost_record_id=?',[$byAdmin])->fetchColumn()==='APPROVED',
    'Approved supplier document decision is missing.');
expectRefusal(fn()=>partCostPropose($pdo,$sender['user_id'],$branch,$part,
    $costAtFirst,$source,$supplier,'ADMIN-COST-'.$run,$date,$explanation,true),
    'already approved');
expectResult(partCostApproved($pdo,$byAdmin,$branch,$part)['unit_cost_php']==='1200.25',
    'Verified source acquisition cost could not be retrieved.');
expectRefusal(fn()=>partCostApproved($pdo,$byAdmin,$destination,$part),'Only an approved');
expectRefusal(fn()=>partCostApproved($pdo,$byAdmin,$branch,$otherPart),'Only an approved');
expectRefusal(fn()=>partCostApproved($pdo,$first,$branch,$part),'Only an approved');
expectResult(partCostDesk($pdo,$sender['user_id'],$branch,$sku,1)['total']===2,
    'Per-branch cost history is incomplete.');
expectRefusal(fn()=>partCostDesk($pdo,$outsider,$branch,'',1),'outside your assigned');
expectRefusal(fn()=>partCostDesk($pdo,$cashier['user_id'],$branch,'',1),
    'outside your assigned');

// This same approved record must be used by loss valuation, never caller-supplied
// unit cost, cost basis, or document reference.
$code=partsTransferDispatch($pdo,$sender['user_id'],$branch,$destination,
    $sku.',2','Sealed dispatch with verified SSD quantity and receiving signature.',true);
$item=(int)stockRun($pdo,'SELECT x.inventory_transfer_item_id
    FROM inventory_transfer_items x JOIN inventory_transfers t
      ON t.inventory_transfer_id=x.inventory_transfer_id
    WHERE t.transfer_code=?',[$code])->fetchColumn();
partsTransferReceive($pdo,$receivingManager['user_id'],$item,'0.000',
    'missing','0.000','Unreceived SSDs verified missing after physical handover inspection.',true);
$loss=partsLossReport($pdo,$receivingManager['user_id'],$item,'0.000',
    'LOSS-'.$run.'-SIGNED','Inspected manifest and courier record; two SSDs still missing.',true);
partsLossDecide($pdo,$costAdmin,$loss,'approve',
    'Independently reviewed signed manifest and loss evidence from both branches.',true);
$costReference='ACQ-COST-'.$byAdmin;
expectRefusal(fn()=>partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','10.00','VERIFIED_COST_RECORD',$costReference,$explanation,true),
    'Verified cost records must come');
expectRefusal(fn()=>partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','5.00','VERIFIED_COST_RECORD',$costReference,$explanation,true,$first),
    'Only an approved acquisition cost');
expectRefusal(fn()=>partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','5.00','VERIFIED_COST_RECORD',$costReference,$explanation,true,9999999),
    'Only an approved acquisition cost');
$valuation=partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','1.00','SUPPLIER_INVOICE','FORGED-PRICE-'.$run,$explanation,true,$byAdmin);
$v=stockRun($pdo,'SELECT * FROM stock_transfer_loss_valuations WHERE valuation_id=?',
    [$valuation])->fetch();
expectResult((int)$v['part_acquisition_cost_id']===$byAdmin &&
    $v['unit_cost_php']==='1200.25' && $v['exposure_php']==='2400.50' &&
    $v['cost_basis']==='VERIFIED_COST_RECORD' && $v['cost_reference']===$costReference,
    'Loss valuation trusted unverified form values or lost its exact supplier provenance.');
expectRefusal(fn()=>partsValuationDecide($pdo,$costAdmin,$valuation,'approve',
    'Independent valuation reviewer checked source records, quantity and cost.',true),
    'different administrator');
partsValuationDecide($pdo,$financeAdmin,$valuation,'approve',
    'Independent valuation reviewer verified signed supplier cost and unrecovered quantity.',true);
expectResult(stockRun($pdo,'SELECT status FROM stock_transfer_loss_valuations
    WHERE valuation_id=?',[$valuation])->fetchColumn()==='APPROVED',
    'Source-linked valuation was not approved.');

$next=partCostPropose($pdo,$sender['user_id'],$branch,$part,
    '1300.00',$source,$supplier,'NEW-PURCHASE-'.$run,$date,$explanation,true);
expectRefusal(fn()=>partCostApproved($pdo,$next,$branch,$part),'Only an approved');
partCostDecide($pdo,$financeAdmin,$next,'approve',$review,true);
expectResult(partCostApproved($pdo,$next,$branch,$part)['unit_cost_php']==='1300.00',
    'New supplier document approval did not become available.');
$old=stockRun($pdo,'SELECT unit_cost_php,cost_reference,part_acquisition_cost_id,exposure_php
    FROM stock_transfer_loss_valuations WHERE valuation_id=?',[$valuation])->fetch();
expectResult($old['unit_cost_php']==='1200.25' && $old['exposure_php']==='2400.50' &&
    $old['cost_reference']===$costReference &&
    (int)$old['part_acquisition_cost_id']===$byAdmin,
    'Later approved purchase prices rewrote historical loss valuation.');

$revised=partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','0.01','PURCHASE_RECEIPT','FORGED-REVISION-'.$run,$explanation,true,$next);
$revisedValue=stockRun($pdo,'SELECT unit_cost_php,exposure_php,part_acquisition_cost_id
    FROM stock_transfer_loss_valuations WHERE valuation_id=?',[$revised])->fetch();
expectResult($revisedValue['unit_cost_php']==='1300.00' &&
    $revisedValue['exposure_php']==='2600.00' &&
    (int)$revisedValue['part_acquisition_cost_id']===$next,
    'New purchase cost revision did not use exact independent approved record.');
partsValuationDecide($pdo,$financeAdmin,$revised,'approve',
    'Independently verified revised supplier cost source and still-missing quantity.',true);
expectRefusal(fn()=>partsValuationPropose($pdo,$receivingManager['user_id'],$loss,
    '2.000','0.01','SUPPLIER_INVOICE','DIFFERENT-'.$run,$explanation,true,$next),
    'already have an approved valuation');
expectResult(stockShelfState($pdo,$branch,$part)['on_hand']==='3.000',
    'Supplier cost evidence incorrectly changed source inventory.');
expectResult((int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])
    ->fetchColumn()===$payments,'Supplier cost register modified cashier payment rows.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE part_id=? AND transaction_type IN ('TRANSFER_OUT','TRANSFER_IN')",[$part])
    ->fetchColumn()===1,'Cost approval or valuation caused an unauthorized inventory movement.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='part' AND entity_id=?
      AND action IN ('ACQUISITION_COST_PROPOSED','ACQUISITION_COST_APPROVED','ACQUISITION_COST_REJECTED')",
    [$part])->fetchColumn()===6,'Source cost submissions and decisions lack audit events.');

// More than ten cost records should remain reachable by pagination.
for ($i=0;$i<11;$i++) {
    $other=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
        [$run.'-ARCHIVE-'.$i,'Audit cost fixture '.$i]);
    stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
        VALUES (?,?,0)',[$branch,$other]);
    $id=partCostPropose($pdo,$sender['user_id'],$branch,$other,
        '15.00',$source,$supplier,'AUDIT-'.$run.'-DOC-'.$i,$date,$explanation,true);
    partCostDecide($pdo,$financeAdmin,$id,'reject','Historical catalog unit not supported by supplier paperwork.',true);
}
$firstPage=partCostDesk($pdo,$costAdmin,$branch,'',1);
$secondPage=partCostDesk($pdo,$costAdmin,$branch,'',2);
expectResult($firstPage['total']>=14 && count($firstPage['rows'])===10 &&
    count($secondPage['rows'])>0,
    'Older supplier cost records cannot be reached through pagination.');
echo "PASS: independently reviewed source SKU acquisition cost, forged-cost denial, exact provenance, immutable valuations and branch isolation.\n";
