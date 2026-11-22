<?php
declare(strict_types=1);
// Isolated disposable-MySQL fixture provided by payment-pickup.php --parts-transfer.
if (PHP_SAPI !== 'cli' || !isset($staff,$pdo)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/transfers.php';
[$manager,$cashier,, $otherManager,$technician] = $staff;
$receivingBranch=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-PART-DEST','Receiving parts branch']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$otherManager['user_id'],$receivingBranch]);
$sku1=$run.'-SSD';
$sku2=$run.'-BAT';
$partA=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$sku1,'Transfer SSD']);
$partB=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$sku2,'Transfer Battery']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,5)',[$branch,$partA]);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,4)',[$branch,$partB]);
// Two SSDs belong to an approved repair and cannot leave on a general manifest.
$quoteItem=insertFixture($pdo,"INSERT INTO repair_quote_items
    (quote_id,part_id,tracks_branch_stock,item_type,description,quantity,unit_price,line_total)
    VALUES (?,?,1,'PART','Reserved transfer SSD',2,600,1200)",[$quote,$partA]);
stockRun($pdo,'INSERT INTO stock_reservations (quote_item_id,branch_id,part_id,quantity,reserved_by)
    VALUES (?,?,?,?,?)',[$quoteItem,$branch,'2.000',$manager['user_id']]);
$dispatchNote='Origin manager counted and sealed physical shipment for branch receiving.';
$receiptNote='Receiving manager checked seals and independently counted delivered units.';
$missingNote='Container opened at receiving bench; remaining units not physically delivered.';
$lines=$sku1.',3'."\n".$sku2.',2';

expectRefusal(fn()=>partsTransferDispatch($pdo,$cashier['user_id'],$branch,$receivingBranch,
    $lines,$dispatchNote,true),'branch manager');
expectRefusal(fn()=>partsTransferDispatch($pdo,$technician['user_id'],$branch,$receivingBranch,
    $lines,$dispatchNote,true),'branch manager');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $sku1.',4',$dispatchNote,true),'Not enough unreserved');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$branch,
    $lines,$dispatchNote,true),'distinct receiving branch');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $sku1.',1'."\n".$sku1.',1',$dispatchNote,true),'repeat a part SKU');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $sku1.',0',$dispatchNote,true),'positive');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $lines,'bad',true),'10');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $lines,$dispatchNote,false),'Confirm');
expectResult(stockShelfState($pdo,$branch,$partA)['on_hand']==='5.000',
    'Refused dispatch incorrectly consumed origin inventory.');

$code=partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $lines,$dispatchNote,true);
$transfer=stockRun($pdo,'SELECT * FROM inventory_transfers WHERE transfer_code=?',
    [$code])->fetch();
expectResult($transfer && $transfer['status']==='IN_TRANSIT',
    'Transfer did not create an in-transit physical manifest.');
$transferId=(int)$transfer['inventory_transfer_id'];
$items=stockRun($pdo,'SELECT inventory_transfer_item_id,part_id,quantity
    FROM inventory_transfer_items WHERE inventory_transfer_id=? ORDER BY part_id',
    [$transferId])->fetchAll();
expectResult(count($items)===2,'Multi-part shipment must have exactly two lines.');
$itemA=(int)$items[0]['inventory_transfer_item_id'];
$itemB=(int)$items[1]['inventory_transfer_item_id'];
expectResult(stockShelfState($pdo,$branch,$partA)['on_hand']==='2.000' &&
    stockShelfState($pdo,$branch,$partA)['reserved']==='2.000' &&
    stockShelfState($pdo,$branch,$partB)['on_hand']==='2.000',
    'Dispatch did not reconcile origin stock or protect its reservations.');
expectResult(!stockRun($pdo,'SELECT quantity_on_hand FROM branch_inventory WHERE
    branch_id=? AND part_id=?',[$receivingBranch,$partA])->fetchColumn(),
    'Destination stock was credited before receiving physical parts.');
expectRefusal(fn()=>partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $sku1.',1',$dispatchNote,true),'Not enough unreserved');

// Receiving branch checks: wrong user, wrong branch, dispatch author, stale quantity.
expectRefusal(fn()=>partsTransferReceive($pdo,$cashier['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true),'branch manager');
expectRefusal(fn()=>partsTransferReceive($pdo,$manager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true),'not assigned');
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$manager['user_id'],$receivingBranch]);
expectRefusal(fn()=>partsTransferReceive($pdo,$manager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true),'dispatching manager');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'0.000',
    'receive','4.000',$receiptNote,true),'more than the outstanding');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,false),'Confirm');

partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true);
$receipt=stockRun($pdo,'SELECT item_status,received_quantity FROM inventory_transfer_items
    WHERE inventory_transfer_item_id=?',[$itemA])->fetch();
expectResult($receipt['item_status']==='EXCEPTION' &&
    $receipt['received_quantity']==='1.000','Partial receipt must remain an exception.');
expectResult(stockShelfState($pdo,$receivingBranch,$partA)['on_hand']==='1.000',
    'Only physically counted units can become destination stock.');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true),'Receipt changed');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'1.000',
    'missing','0.000',$missingNote,true),'already reported');

partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'1.000',
    'receive','2.000','Late container delivered and inspected against original transfer.',true);
expectResult(stockShelfState($pdo,$receivingBranch,$partA)['on_hand']==='3.000',
    'Late arrival must add only previously unreceived physical stock.');
expectResult(stockRun($pdo,'SELECT status FROM inventory_transfers WHERE
    inventory_transfer_id=?',[$transferId])->fetchColumn()==='IN_TRANSIT',
    'Manifest should remain in transit while another line is unreceived.');

partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'0.000',
    'missing','0.000',$missingNote,true);
expectResult(stockRun($pdo,'SELECT status FROM inventory_transfers WHERE
    inventory_transfer_id=?',[$transferId])->fetchColumn()==='EXCEPTION',
    'Missing shipment must mark the manifest as an exception.');
expectResult(!stockRun($pdo,'SELECT quantity_on_hand FROM branch_inventory WHERE
    branch_id=? AND part_id=?',[$receivingBranch,$partB])->fetchColumn(),
    'Missing stock may not be credited to destination shelf.');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'0.000',
    'missing','0.000',$missingNote,true),'already reported');
partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'0.000',
    'receive','1.000',$receiptNote,true);
expectResult(stockShelfState($pdo,$receivingBranch,$partB)['on_hand']==='1.000',
    'Only the one received battery should be counted.');
partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'1.000',
    'receive','1.000','Final missing battery delivered with signed replenishment sheet.',true);
expectResult(stockShelfState($pdo,$receivingBranch,$partB)['on_hand']==='2.000',
    'Both received batteries must reconcile.');
$closed=stockRun($pdo,'SELECT status,received_at FROM inventory_transfers WHERE
    inventory_transfer_id=?',[$transferId])->fetch();
expectResult($closed['status']==='RECEIVED' && $closed['received_at']!==null,
    'All manifest items complete, but transfer was not closed.');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'2.000',
    'receive','1.000',$receiptNote,true),'already completed');

$ledger=stockRun($pdo,"SELECT branch_id,part_id,transaction_type,
    COALESCE(SUM(quantity_delta),0) AS total, COUNT(*) AS entries
    FROM inventory_transactions WHERE inventory_transfer_item_id IN (?,?)
    GROUP BY branch_id,part_id,transaction_type
    ORDER BY branch_id,part_id,transaction_type",[$itemA,$itemB])->fetchAll();
expectResult(count($ledger)===4,'Expected exactly two dispatch and two destination movement groups.');
$totals=[];
foreach ($ledger as $entry) {
    $totals[$entry['branch_id'].'-'.$entry['part_id'].'-'.$entry['transaction_type']] =
        $entry['total'];
}
expectResult($totals[$branch.'-'.$partA.'-TRANSFER_OUT']==='-3.000' &&
    $totals[$branch.'-'.$partB.'-TRANSFER_OUT']==='-2.000' &&
    $totals[$receivingBranch.'-'.$partA.'-TRANSFER_IN']==='3.000' &&
    $totals[$receivingBranch.'-'.$partB.'-TRANSFER_IN']==='2.000',
    'Transfer ledger debits and confirmed receipt credits do not reconcile.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='inventory_transfer_item' AND entity_id IN (?,?)",
    [$itemA,$itemB])->fetchColumn()===5,
    'Expected partial, late, missing and final arrival audit events.');

// Branch-scoped listing cannot be used to view another branch's physical stock movements.
$visible=partsTransferQueue($pdo,$otherManager['user_id'],$receivingBranch,'PT-',1);
expectResult($visible['total']===2,'Receiving branch transfer queue must list both part lines.');
$restricted=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-NOACCESS','Unassigned independent shop']);
expectRefusal(fn()=>partsTransferQueue($pdo,$otherManager['user_id'],$restricted,'',1),
    'not assigned');
echo "PASS: parts dispatch, reservations, partial receipt, missing reports, late arrivals, role scope and ledger reconciliation.\n";
