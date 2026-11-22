<?php
declare(strict_types=1);
// Run only inside disposable repairtrack_test_* fixture from payment-pickup.php.
if (PHP_SAPI !== 'cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/losses.php';
[$manager,$cashier,, $otherManager,$technician] = $staff;
$receivingBranch = insertFixture($pdo,
    'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-LOSS-RECV','Loss test receiving branch']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id)
    VALUES (?,?)',[$otherManager['user_id'],$receivingBranch]);
$reviewerId=insertFixture($pdo,
    "INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,'ADMIN')",
    ['Independent source reviewer',$run.'-loss-reviewer@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id)
    VALUES (?,?)',[$reviewerId,$branch]);
$skuA=$run.'-LOSS-A';
$skuB=$run.'-LOSS-B';
$partA=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$skuA,'In-transit laptop SSD']);
$partB=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$skuB,'In-transit test adapter']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,5)',[$branch,$partA]);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,4)',[$branch,$partB]);
$dispatchNote='Sealed source-branch shipment handed over with signed departure count.';
$receiptNote='Receiving branch independently inspected and counted sealed package.';
$missingNote='Physical shortage documented after checking seal, box and courier slip.';
$investigation='Two SSD units remained absent after count, courier inquiry and seal inspection.';
$proof='CASE-'.$run.'-SIGNED-COUNT-01';
$approval='Independently compared the dispatch signature, receiving count and incident reference.';
$code=partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $skuA.',3'."\n".$skuB.',2',$dispatchNote,true);
$manifest=stockRun($pdo,'SELECT inventory_transfer_id FROM inventory_transfers
    WHERE transfer_code=?',[$code])->fetchColumn();
$ids=stockRun($pdo,'SELECT part_id,inventory_transfer_item_id FROM inventory_transfer_items
    WHERE inventory_transfer_id=?',[(int)$manifest])->fetchAll();
$byPart=[];
foreach ($ids as $row) $byPart[(int)$row['part_id']] = (int)$row['inventory_transfer_item_id'];
$itemA=$byPart[$partA];
$itemB=$byPart[$partB];
expectResult(stockShelfState($pdo,$branch,$partA)['on_hand']==='2.000',
    'Dispatch did not deduct the shipped SSDs.');
expectRefusal(fn()=>partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '0.000',$proof,$investigation,true),'Only an unresolved missing-part exception');
partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'0.000',
    'receive','1.000',$receiptNote,true);
expectRefusal(fn()=>partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '1.000',$proof,$investigation,true),'Record a missing-part report');
partsTransferReceive($pdo,$otherManager['user_id'],$itemA,'1.000',
    'missing','0.000',$missingNote,true);
expectRefusal(fn()=>partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '1.000','tiny',$investigation,true),'evidence reference');
expectRefusal(fn()=>partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '0.000',$proof,$investigation,true),'missing quantity changed');
expectRefusal(fn()=>partsLossReport($pdo,$cashier['user_id'],$itemA,
    '1.000',$proof,$investigation,true),'branch manager');
expectRefusal(fn()=>partsLossReport($pdo,$manager['user_id'],$itemA,
    '1.000',$proof,$investigation,true),'not assigned');
$caseId=partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '1.000',$proof,$investigation,true);
expectResult($caseId>0,'Formal loss report was not recorded.');
expectRefusal(fn()=>partsLossReport($pdo,$otherManager['user_id'],$itemA,
    '1.000',$proof,$investigation,true),'pending or approved loss case');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,
    '1.000','receive','2.000',$receiptNote,true),'pending approval');
expectRefusal(fn()=>partsLossDecide($pdo,$cashier['user_id'],$caseId,
    'approve',$approval,true),'branch manager');
// The shared fixture already assigns this receiving manager to the source branch.
 // Testing self-review denial must not insert that same assignment twice.
expectRefusal(fn()=>partsLossDecide($pdo,$otherManager['user_id'],$caseId,
    'approve',$approval,true),'different manager');
expectRefusal(fn()=>partsLossDecide($pdo,$manager['user_id'],$caseId,
    'approve',$approval,true),'dispatching manager');
expectRefusal(fn()=>partsLossDecide($pdo,$reviewerId,$caseId,
    'approve',$approval,false),'Confirm');
expectResult(stockShelfState($pdo,$receivingBranch,$partA)['on_hand']==='1.000',
    'Pending loss incorrectly increased physical destination stock.');
partsTransferReceive($pdo,$otherManager['user_id'],$itemB,'0.000',
    'receive','2.000',$receiptNote,true);
partsLossDecide($pdo,$reviewerId,$caseId,'approve',$approval,true);
$loss=stockRun($pdo,'SELECT status,missing_quantity,evidence_reference,reviewed_by_user_id,
    missing_audit_event_id FROM stock_transfer_loss_cases WHERE loss_case_id=?',
    [$caseId])->fetch();
expectResult($loss['status']==='APPROVED' && $loss['missing_quantity']==='2.000' &&
    $loss['evidence_reference']===$proof && (int)$loss['reviewed_by_user_id']===$reviewerId &&
    (int)$loss['missing_audit_event_id']>0,'Approved loss evidence or amount was lost.');
expectResult(stockRun($pdo,'SELECT item_status FROM inventory_transfer_items
    WHERE inventory_transfer_item_id=?',[$itemA])->fetchColumn()==='LOSS_CLOSED',
    'Approved missing quantity was not operationally closed.');
$closed=stockRun($pdo,'SELECT status,received_at FROM inventory_transfers
    WHERE inventory_transfer_id=?',[(int)$manifest])->fetch();
expectResult($closed['status']==='LOSS_CLOSED' && $closed['received_at']===null,
    'A loss-closed manifest must not claim its missing units were physically received.');
expectResult(stockShelfState($pdo,$branch,$partA)['on_hand']==='2.000' &&
    stockShelfState($pdo,$receivingBranch,$partA)['on_hand']==='1.000',
    'Approval incorrectly moved source or destination shelf stock.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_OUT'",
    [$itemA])->fetchColumn()===1,'Approved loss created an extra dispatch debit.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_IN'",
    [$itemA])->fetchColumn()===1,'Approved loss credited unreceived quantities.');
$sent = stockRun($pdo,"SELECT COALESCE(SUM(-quantity_delta),0) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_OUT'",
    [$itemA])->fetchColumn();
$arrived = stockRun($pdo,"SELECT COALESCE(SUM(quantity_delta),0) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND transaction_type='TRANSFER_IN'",
    [$itemA])->fetchColumn();
expectResult(stockUnits((string)$sent) ===
    stockUnits((string)$arrived) + stockUnits((string)$loss['missing_quantity']),
    'Dispatch does not reconcile to physically received plus independently approved loss.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='inventory_transfer_item' AND entity_id=?
      AND action='PARTS_MISSING'",[$itemA])->fetchColumn()===1,
    'Formal closure must preserve the original missing-shipment audit report.');
expectRefusal(fn()=>partsLossDecide($pdo,$reviewerId,$caseId,
    'approve',$approval,true),'no longer awaiting review');
expectRefusal(fn()=>partsTransferReceive($pdo,$otherManager['user_id'],$itemA,
    '1.000','receive','2.000',$receiptNote,true),'already completed');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events WHERE
    entity_type='inventory_transfer_item' AND entity_id=?
    AND action='PARTS_LOSS_APPROVED'",[$itemA])->fetchColumn()===1,
    'Independent loss approval must be audited exactly once.');

// Rejected reports do not write off stock and still allow physical late delivery.
// A new, improved case can be submitted after rejection.
$secondCode=partsTransferDispatch($pdo,$manager['user_id'],$branch,$receivingBranch,
    $skuB.',1',$dispatchNote,true);
$itemC=(int)stockRun($pdo,"SELECT x.inventory_transfer_item_id
    FROM inventory_transfer_items x JOIN inventory_transfers t
      ON t.inventory_transfer_id=x.inventory_transfer_id
    WHERE t.transfer_code=?",[$secondCode])->fetchColumn();
partsTransferReceive($pdo,$otherManager['user_id'],$itemC,'0.000',
    'missing','0.000',$missingNote,true);
$rejected=partsLossReport($pdo,$otherManager['user_id'],$itemC,
    '0.000','CASE-'.$run.'-UNCONFIRMED',$investigation,true);
partsLossDecide($pdo,$reviewerId,$rejected,'reject',
    'The courier evidence is incomplete and requires an additional physical recount.',true);
expectResult(stockRun($pdo,'SELECT item_status FROM inventory_transfer_items
    WHERE inventory_transfer_item_id=?',[$itemC])->fetchColumn()==='EXCEPTION',
    'Rejection silently closed an unresolved missing shipment.');
$reopened=partsLossReport($pdo,$otherManager['user_id'],$itemC,
    '0.000','CASE-'.$run.'-SECOND-REPORT',$investigation,true);
expectResult($reopened>$rejected,'Re-submitted investigation did not preserve rejection history.');
partsLossDecide($pdo,$reviewerId,$reopened,'reject',
    'Shipment located at the courier warehouse; no lost unit can be approved.',true);
partsTransferReceive($pdo,$otherManager['user_id'],$itemC,'0.000',
    'receive','1.000','Previously missing part physically located, counted and received.',true);
expectResult(stockRun($pdo,"SELECT t.status FROM inventory_transfers t JOIN
    inventory_transfer_items x ON x.inventory_transfer_id=t.inventory_transfer_id
    WHERE x.inventory_transfer_item_id=?",[$itemC])->fetchColumn()==='RECEIVED',
    'Rejection did not permit later genuine physical receipt.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM stock_transfer_loss_cases
    WHERE inventory_transfer_item_id=? AND status='REJECTED'",[$itemC])->fetchColumn()===2,
    'Two rejected investigation attempts were not preserved.');
$visible=partsTransferQueue($pdo,$otherManager['user_id'],$receivingBranch,$code,1);
expectResult($visible['total']===2 && $visible['records'][0]['loss_status']!==null,
    'Branch-scoped transfer list must show independent loss review state.');
echo "PASS: missing parts evidence, independent approval, stale refusal, signed stock reconciliation and loss rejection/recovery.\n";
