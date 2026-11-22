<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/recoveries.php';
[$manager,$cashier,,$receiver,$technician]=$staff;
$destination=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-RECOVERY','Recovered parts destination']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$receiver['user_id'],$destination]);
$reviewerId=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Independent recovery reviewer',$run.'-review-recovered@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$reviewerId,$branch]);
$sku=$run.'-RECOVERED';
$partId=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$sku,'Recovered laptop SSD']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,5)',[$branch,$partId]);
$dispatch='Three sealed SSDs dispatched with checked source counter signature.';
$receipt='One SSD physically received and inspected by independent destination manager.';
$missing='Two remaining SSDs not found after courier and receiving shelf inspection.';
$investigation='Courier and destination manager could not account for the last two shipped SSDs.';
$review='Independent origin manager compared both warehouse counts and loss report.';
$proof='SEALED-CASE-'.$run.'-001';
$code=partsTransferDispatch($pdo,$manager['user_id'],$branch,$destination,
    $sku.',3',$dispatch,true);
$itemId=(int)stockRun($pdo,'SELECT x.inventory_transfer_item_id
    FROM inventory_transfer_items x JOIN inventory_transfers t
    ON t.inventory_transfer_id=x.inventory_transfer_id
    WHERE t.transfer_code=?',[$code])->fetchColumn();
partsTransferReceive($pdo,$receiver['user_id'],$itemId,'0.000',
    'receive','1.000',$receipt,true);
partsTransferReceive($pdo,$receiver['user_id'],$itemId,'1.000',
    'missing','0.000',$missing,true);
$lossId=partsLossReport($pdo,$receiver['user_id'],$itemId,'1.000',$proof,
    $investigation,true);
partsLossDecide($pdo,$reviewerId,$lossId,'approve',$review,true);
expectResult(stockShelfState($pdo,$branch,$partId)['on_hand']==='2.000',
    'Original dispatch debit was not retained.');
expectResult(stockShelfState($pdo,$destination,$partId)['on_hand']==='1.000',
    'Lost parts were incorrectly credited on approval.');
$reason='Recovered one intact sealed SSD and held it in the locked receiving inspection bin.';
$evidence='RECOVERY-'.$run.'-SIGNED-INSPECTION';
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '3.000',$evidence,$reason,true),'exceeds outstanding');
expectRefusal(fn()=>partsRecoveryReport($pdo,$cashier['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,true),'branch manager');
expectRefusal(fn()=>partsRecoveryReport($pdo,$manager['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,true),'not assigned');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'0.000',
    '1.000',$evidence,$reason,true),'snapshot changed');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000','short',$reason,true),'inspection reference');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,false),'Confirm');
$rejectedRecovery=partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,true);
expectResult($rejectedRecovery>0,'No pending recovery was recorded.');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,true),'already awaits');
expectResult(stockShelfState($pdo,$destination,$partId)['on_hand']==='1.000',
    'Unapproved recovered stock became usable.');
expectRefusal(fn()=>partsRecoveryDecide($pdo,$cashier['user_id'],$rejectedRecovery,
    'approve',$review,true),'branch manager');
expectRefusal(fn()=>partsRecoveryDecide($pdo,$manager['user_id'],$rejectedRecovery,
    'approve',$review,true),'dispatching manager');
expectRefusal(fn()=>partsRecoveryDecide($pdo,$receiver['user_id'],$rejectedRecovery,
    'approve',$review,true),'different manager');
expectRefusal(fn()=>partsRecoveryDecide($pdo,$reviewerId,$rejectedRecovery,
    'approve',$review,false),'Confirm');
partsRecoveryDecide($pdo,$reviewerId,$rejectedRecovery,'reject',
    'Physical condition could not be verified from the first signed inspection report.',true);
expectRefusal(fn()=>partsRecoveryDecide($pdo,$reviewerId,$rejectedRecovery,
    'approve',$review,true),'no longer awaiting');
expectResult(stockShelfState($pdo,$destination,$partId)['on_hand']==='1.000',
    'Rejected recovery changed the receiving branch stock.');
$acceptedRecovery=partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000','REINSPECT-'.$run.'-SIGNED-CASE',
    'Second physical inspection verified one usable SSD in the isolated recovery bin.',true);
partsRecoveryDecide($pdo,$reviewerId,$acceptedRecovery,'approve',$review,true);
expectResult(stockShelfState($pdo,$destination,$partId)['on_hand']==='2.000',
    'Approved recovery failed to add one and only one usable SSD.');
expectResult(stockRun($pdo,"SELECT item_status FROM inventory_transfer_items
    WHERE inventory_transfer_item_id=?",[$itemId])->fetchColumn()==='LOSS_CLOSED',
    'A partially recovered approved loss must stay loss-closed.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE inventory_transfer_item_id=? AND reference_code=?",
    [$itemId,'RECOVERY-'.$acceptedRecovery])->fetchColumn()===1,
    'Recovery approval requires one identifiable immutable TRANSFER_IN movement.');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'1.000',
    '1.000',$evidence,$reason,true),'snapshot changed');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'2.000',
    '2.000',$evidence,$reason,true),'exceeds outstanding');
$finalRecovery=partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'2.000',
    '1.000','RECOVER-FINAL-'.$run.'-SEAL',
    'Last missing SSD found intact after inventory sweep, physically held pending review.',true);
expectRefusal(fn()=>partsRecoveryDecide($pdo,$receiver['user_id'],$finalRecovery,
    'approve',$review,true),'different manager');
partsRecoveryDecide($pdo,$reviewerId,$finalRecovery,'approve',$review,true);
expectResult(stockShelfState($pdo,$destination,$partId)['on_hand']==='3.000',
    'Full recovery did not reconcile the receiving branch shelf.');
expectResult(stockShelfState($pdo,$branch,$partId)['on_hand']==='2.000',
    'Source branch was incorrectly debited again.');
$final=stockRun($pdo,'SELECT t.status,t.received_at,x.item_status,x.received_quantity
    FROM inventory_transfer_items x JOIN inventory_transfers t
    ON t.inventory_transfer_id=x.inventory_transfer_id
    WHERE x.inventory_transfer_item_id=?',[$itemId])->fetch();
expectResult($final['status']==='RECEIVED' && $final['item_status']==='RECEIVED'
    && $final['received_quantity']==='3.000' && $final['received_at']!==null,
    'After all missing parts physically return, manifest must show actual full receipt.');
expectResult(stockRun($pdo,'SELECT status FROM stock_transfer_loss_cases
    WHERE loss_case_id=?',[$lossId])->fetchColumn()==='APPROVED',
    'Original approved loss evidence must remain unchanged after full recovery.');
expectRefusal(fn()=>partsRecoveryReport($pdo,$receiver['user_id'],$lossId,'3.000',
    '1.000',$evidence,$reason,true),'no outstanding approved loss');
expectRefusal(fn()=>partsRecoveryDecide($pdo,$reviewerId,$finalRecovery,
    'approve',$review,true),'no longer awaiting');
$sent=stockRun($pdo,"SELECT COALESCE(SUM(-quantity_delta),0)
    FROM inventory_transactions WHERE inventory_transfer_item_id=?
    AND transaction_type='TRANSFER_OUT'",[$itemId])->fetchColumn();
$received=stockRun($pdo,"SELECT COALESCE(SUM(quantity_delta),0)
    FROM inventory_transactions WHERE inventory_transfer_item_id=?
    AND transaction_type='TRANSFER_IN'",[$itemId])->fetchColumn();
$approved=stockRun($pdo,"SELECT COALESCE(SUM(quantity),0)
    FROM stock_transfer_recoveries WHERE loss_case_id=? AND status='APPROVED'",
    [$lossId])->fetchColumn();
expectResult(stockUnits((string)$sent)===stockUnits((string)$received) &&
    stockUnits((string)$approved)===2000,
    'Final dispatch, physical receipts and recovery movement history do not reconcile.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM stock_transfer_recoveries
    WHERE loss_case_id=? AND status='REJECTED'",[$lossId])->fetchColumn()===1,
    'Rejected recovery evidence must remain independently auditable.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='inventory_transfer_item' AND entity_id=?
    AND action IN ('PARTS_RECOVERY_REPORTED','PARTS_RECOVERY_APPROVED','PARTS_RECOVERY_REJECTED')",
    [$itemId])->fetchColumn()===6,
    'Every recovery proposal and independent decision must retain an audit event.');
echo "PASS: staged recovery, independent review, rejection, partial/full recovery and original loss integrity.\n";
