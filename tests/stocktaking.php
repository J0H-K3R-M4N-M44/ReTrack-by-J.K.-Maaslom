<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/stocktaking.php';
[$manager,$cashier,,$secondManager,$technician]=$staff;
$admin=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Stocktake first verifier',$run.'-count-admin@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$admin,$branch]);
$signer=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Independent final stock auditor',$run.'-signoff-admin@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$signer,$branch]);
$otherBranch=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-STOTHER','Other stocktake branch']);
$outsider=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'MANAGER')",['Wrong branch counter',$run.'-wrong-count@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$outsider,$otherBranch]);
$parts=[];
foreach ([['-ST-A','Physical SSD',5,1],['-ST-B','Physical battery',4,1],
    ['-ST-C','Inactive but stocked part',2,0]] as [$suffix,$label,$qty,$active]) {
    $id=insertFixture($pdo,'INSERT INTO parts (sku,part_name,is_active) VALUES (?,?,?)',
        [$run.$suffix,$label,$active]);
    stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
        VALUES (?,?,?)',[$branch,$id,$qty]);
    $parts[]=$id;
}
[$a,$b,$c]=$parts;
$period=gmdate('Y-m');
$note='Physically counted all usable units, checked location and excluded quarantine.';
$opening='Scheduled full monthly count of all branch shelves and inactive catalog stock.';
$submitNote='All recorded physical counts submitted to independent source branch administrator.';
$reviewNote='Independently checked signed count evidence and current shelf movement IDs.';
$paymentStart=(int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])->fetchColumn();
expectRefusal(fn()=>stocktakeOpen($pdo,$cashier['user_id'],$branch,$period,
    $opening,true),'branch manager');
expectRefusal(fn()=>stocktakeOpen($pdo,$manager['user_id'],$branch,$period,
    $opening,false),'Confirm');
expectRefusal(fn()=>stocktakeOpen($pdo,$manager['user_id'],$branch,'2001-01',
    $opening,true),'present UTC monthly');
expectRefusal(fn()=>stocktakeOpen($pdo,$outsider,$branch,$period,
    $opening,true),'not assigned');
$first=stocktakeOpen($pdo,$manager['user_id'],$branch,$period,$opening,true);
expectResult($first>0,'A monthly physical count was not opened.');
expectResult(stocktakeDesk($pdo,$manager['user_id'],$branch)['total']===3,
    'Opening cycle does not include all branch shelves (including inactive SKU).');
expectRefusal(fn()=>stocktakeOpen($pdo,$manager['user_id'],$branch,$period,
    $opening,true),'unfinished');
expectRefusal(fn()=>stocktakeDesk($pdo,$outsider,$branch),'outside');
expectRefusal(fn()=>stocktakeCount($pdo,$outsider,$first,$a,'5.000','0.000',0,
    '5.000',$note,true),'not assigned');
expectRefusal(fn()=>stocktakeCount($pdo,$cashier['user_id'],$first,$a,'5.000','0.000',0,
    '5.000',$note,true),'branch manager');
expectRefusal(fn()=>stocktakeCount($pdo,$manager['user_id'],$first,$a,'4.000','0.000',0,
    '5.000',$note,true),'Stock changed');
expectRefusal(fn()=>stocktakeCount($pdo,$manager['user_id'],$first,$a,'5.000','0.000',0,
    '5.000',$note,false),'Confirm');
expectRefusal(fn()=>stocktakeSubmit($pdo,$manager['user_id'],$first,
    $submitNote,true),'Every stocked SKU');
$firstShelf=stockShelfState($pdo,$branch,$a);
stocktakeCount($pdo,$admin,$first,$a,$firstShelf['on_hand'],$firstShelf['reserved'],
    $firstShelf['movement_id'],'5.000',$note,true);
$secondShelf=stockShelfState($pdo,$branch,$b);
stocktakeCount($pdo,$manager['user_id'],$first,$b,$secondShelf['on_hand'],
    $secondShelf['reserved'],$secondShelf['movement_id'],'3.000',
    'Actual battery physical count is one below the recorded shelf quantity.',true);
$thirdShelf=stockShelfState($pdo,$branch,$c);
stocktakeCount($pdo,$manager['user_id'],$first,$c,$thirdShelf['on_hand'],
    $thirdShelf['reserved'],$thirdShelf['movement_id'],'2.000',$note,true);
// An early counter must remain ineligible as reviewer even after a newer count
// replaces the working counted_by field: count history is independently audited.
stocktakeCount($pdo,$manager['user_id'],$first,$a,$firstShelf['on_hand'],
    $firstShelf['reserved'],$firstShelf['movement_id'],'5.000',$note,true);
expectResult(stocktakeDesk($pdo,$manager['user_id'],$branch)['discrepancies']===1,
    'One physical count variance was not reported.');
expectRefusal(fn()=>stocktakeSubmit($pdo,$secondManager['user_id'],$first,
    $submitNote,true),'Only the session opener');
stocktakeSubmit($pdo,$manager['user_id'],$first,$submitNote,true);
expectRefusal(fn()=>stocktakeCount($pdo,$manager['user_id'],$first,$a,'5.000','0.000',0,
    '5.000',$note,true),'Only an open stocktake');
expectRefusal(fn()=>stocktakeDecide($pdo,$manager['user_id'],$first,
    'signoff',$reviewNote,true),'administrator');
expectRefusal(fn()=>stocktakeDecide($pdo,$admin,$first,'signoff',$reviewNote,true),
    'physically counted');
expectRefusal(fn()=>stocktakeDecide($pdo,$outsider,$first,'signoff',$reviewNote,true),
    'not assigned');
expectRefusal(fn()=>stocktakeDecide($pdo,$signer,$first,'signoff',$reviewNote,true),
    'Physical count discrepancies remain');
stocktakeDecide($pdo,$signer,$first,'return',
    'Physical count differs from shelf; recount and independently record correction.',true);
expectResult(stockRun($pdo,'SELECT status FROM inventory_stocktakes
    WHERE stocktake_id=?',[$first])->fetchColumn()==='OPEN',
    'Exception cycle was not returned for reconciliation.');
$oldB=$secondShelf;
$adjustment='Independently verified the missing battery and recorded corrected usable shelf count.';
expectResult(stockShelfAction($pdo,$manager['user_id'],$branch,$b,'4.000',0,
    'count','3.000',$adjustment,true)==='applied',
    'Existing count adjustment workflow was not available to resolve an exception.');
expectRefusal(fn()=>stocktakeSubmit($pdo,$manager['user_id'],$first,
    $submitNote,true),'Stock or reservations changed');
expectRefusal(fn()=>stocktakeCount($pdo,$manager['user_id'],$first,$b,
    $oldB['on_hand'],$oldB['reserved'],$oldB['movement_id'],'3.000',$note,true),
    'Stock changed');
$changed=stockShelfState($pdo,$branch,$b);
stocktakeCount($pdo,$manager['user_id'],$first,$b,$changed['on_hand'],
    $changed['reserved'],$changed['movement_id'],'3.000',$note,true);
stocktakeSubmit($pdo,$manager['user_id'],$first,$submitNote,true);
$movementsBefore=(int)stockRun($pdo,'SELECT COUNT(*) FROM inventory_transactions
    WHERE branch_id=?',[$branch])->fetchColumn();
expectRefusal(fn()=>stocktakeDecide($pdo,$admin,$first,'signoff',$reviewNote,true),
    'physically counted');
stocktakeDecide($pdo,$signer,$first,'signoff',$reviewNote,true);
expectResult(stockRun($pdo,'SELECT status FROM inventory_stocktakes WHERE stocktake_id=?',
    [$first])->fetchColumn()==='SIGNED_OFF','Reconciled cycle was not signed.');
expectResult((int)stockRun($pdo,'SELECT COUNT(*) FROM inventory_transactions
    WHERE branch_id=?',[$branch])->fetchColumn()===$movementsBefore,
    'Sign-off illegally created an inventory movement.');
expectRefusal(fn()=>stocktakeDecide($pdo,$signer,$first,'signoff',$reviewNote,true),
    'Only a submitted');
expectRefusal(fn()=>stocktakeCount($pdo,$manager['user_id'],$first,$a,
    '5.000','0.000',0,'5.000',$note,true),'Only an open stocktake');
expectResult(stocktakeDesk($pdo,$signer,$branch,1,$first)['session']['status']==='SIGNED_OFF',
    'Signed history cannot be inspected independently.');

// A new cycle needs a new physical snapshot after movements.
$second=stocktakeOpen($pdo,$manager['user_id'],$branch,$period,$opening,true);
foreach ([$a,$b,$c] as $part) {
    $s=stockShelfState($pdo,$branch,$part);
    stocktakeCount($pdo,$manager['user_id'],$second,$part,$s['on_hand'],
        $s['reserved'],$s['movement_id'],$s['on_hand'],$note,true);
}
stocktakeSubmit($pdo,$manager['user_id'],$second,$submitNote,true);
$aBefore=stockShelfState($pdo,$branch,$a);
stockShelfAction($pdo,$manager['user_id'],$branch,$a,$aBefore['on_hand'],
    $aBefore['movement_id'],'quarantine','1.000',
    'Physically separated one damaged SSD from usable stock before final review.',true);
expectRefusal(fn()=>stocktakeDecide($pdo,$signer,$second,'signoff',$reviewNote,true),
    'Stock or reservations changed');
stocktakeDecide($pdo,$signer,$second,'return',
    'Quarantined SSD changed count baseline. Physically recount this item.',true);
$aAfter=stockShelfState($pdo,$branch,$a);
stocktakeCount($pdo,$manager['user_id'],$second,$a,$aAfter['on_hand'],
    $aAfter['reserved'],$aAfter['movement_id'],$aAfter['on_hand'],$note,true);
stocktakeSubmit($pdo,$manager['user_id'],$second,$submitNote,true);
stocktakeDecide($pdo,$signer,$second,'signoff',$reviewNote,true);
expectResult(stockRun($pdo,'SELECT status FROM inventory_stocktakes WHERE stocktake_id=?',
    [$second])->fetchColumn()==='SIGNED_OFF','Fresh recount did not pass independent sign-off.');

// New SKU additions cannot silently escape the branch-wide audit.
$third=stocktakeOpen($pdo,$manager['user_id'],$branch,$period,$opening,true);
foreach ([$a,$b,$c] as $part) {
    $s=stockShelfState($pdo,$branch,$part);
    stocktakeCount($pdo,$manager['user_id'],$third,$part,$s['on_hand'],
        $s['reserved'],$s['movement_id'],$s['on_hand'],$note,true);
}
$addedPart=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$run.'-ADDED','Newly introduced physical stock part']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,1)',[$branch,$addedPart]);
expectRefusal(fn()=>stocktakeSubmit($pdo,$manager['user_id'],$third,$submitNote,true),
    'catalog SKUs changed');
expectRefusal(fn()=>stocktakeCancel($pdo,$secondManager['user_id'],$third,
    'Different manager cannot cancel this original cycle.',true),'Only the opener');
stocktakeCancel($pdo,$manager['user_id'],$third,
    'New stock was added after opening; cancelling to resnapshot complete branch.',true);
expectResult(stockRun($pdo,'SELECT status FROM inventory_stocktakes WHERE stocktake_id=?',
    [$third])->fetchColumn()==='CANCELLED',
    'Cancelled original session not retained as evidence.');

// An audit with more than ten lines must remain accessible by pagination.
for ($i=0;$i<11;$i++) {
    $newPart=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
        [$run.'-PAGE-'.$i,'Stocktaking pagination fixture '.$i]);
    stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
        VALUES (?,?,1)',[$branch,$newPart]);
}
$fourth=stocktakeOpen($pdo,$manager['user_id'],$branch,$period,$opening,true);
$one=stocktakeDesk($pdo,$signer,$branch,1,$fourth);
$two=stocktakeDesk($pdo,$signer,$branch,2,$fourth);
expectResult($one['total']===15 && count($one['lines'])===10 &&
    count($two['lines'])===5 && $two['page']===2,
    'Long physical audit list is not paginated across all SKUs.');
expectRefusal(fn()=>stocktakeDesk($pdo,$outsider,$branch,1,$fourth),'outside');
stocktakeCancel($pdo,$manager['user_id'],$fourth,
    'Pagination verified; do not sign off uncounted sample catalog parts.',true);
expectResult(stockShelfState($pdo,$branch,$b)['on_hand']==='3.000' &&
    stockShelfState($pdo,$branch,$c)['on_hand']==='2.000',
    'Stocktake incorrectly changed or removed inventory.');
expectResult((int)stockRun($pdo,'SELECT COUNT(*) FROM repair_bill_payments',[])
    ->fetchColumn()===$paymentStart,'Physical stocktaking changed cashier payments.');
$audits=(int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='branch' AND entity_id=? AND action='STOCKTAKE_SIGNED_OFF'",
    [$branch])->fetchColumn();
expectResult($audits===2,'Independent sign-offs are not recorded in audit history.');
echo "PASS: monthly full-branch counts, stale movements, independent zero-variance signoff, exceptions, new SKU protection and pagination.\n";
