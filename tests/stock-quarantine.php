<?php
declare(strict_types=1);
// Loaded only by the disposable repairtrack_test_* fixture in payment-pickup.php.
if (PHP_SAPI !== 'cli' || !isset($staff, $pdo)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/stock_ops.php';
[$manager, $cashier, , $otherManager, $technician] = $staff;
$part = insertFixture($pdo, 'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$run . '-SHELF','Damaged pilot battery']);
stockRun($pdo, 'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,5)',
    [$branch,$part]);
function pilotShelf(PDO $pdo, int $branch, int $part): array
{
    return stockShelfState($pdo,$branch,$part);
}
function checkShelf(PDO $pdo, int $branch, int $part, string $onHand): void
{
    expectResult(pilotShelf($pdo,$branch,$part)['on_hand'] === $onHand,
        'Incorrect usable shelf count after inventory action.');
}
$reason = 'Physical recount verified and independently checked at shelf.';
$damage = 'Damaged battery isolated in sealed quarantine container.';
expectRefusal(fn()=>stockShelfAction($pdo,$cashier['user_id'],$branch,$part,'5.000',0,
    'count','4.000',$reason,true), 'branch manager');
expectRefusal(fn()=>stockShelfAction($pdo,$technician['user_id'],$branch,$part,'5.000',0,
    'quarantine','1.000',$damage,true), 'branch manager');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'5.000',0,
    'count','4.00x',$reason,true), 'nonnegative quantity');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'5.000',0,
    'count','4.000','too short',true), '10');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'5.000',0,
    'count','4.000',$reason,false), 'Confirm');

$otherBranch = insertFixture($pdo, 'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-OTHER', 'Other warehouse']);
stockRun($pdo, 'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,5)',
    [$otherBranch,$part]);
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$otherBranch,$part,'5.000',0,
    'count','4.000',$reason,true), 'not assigned');

stockShelfAction($pdo,$manager['user_id'],$branch,$part,'5.000',0,'count','4.000',$reason,true);
checkShelf($pdo,$branch,$part,'4.000');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'5.000',0,
    'count','3.000',$reason,true), 'Stock changed');
$initial = pilotShelf($pdo,$branch,$part);
expectResult($initial['movement_id'] > 0, 'Count correction movement was not recorded.');
stockShelfAction($pdo,$manager['user_id'],$branch,$part,'4.000',$initial['movement_id'],
    'quarantine','1.000',$damage,true);
checkShelf($pdo,$branch,$part,'3.000');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'4.000',$initial['movement_id'],
    'quarantine','1.000',$damage,true), 'Stock changed');
expectResult((int) stockRun($pdo,"SELECT COUNT(*) FROM stock_quarantines WHERE branch_id=? AND part_id=?
    AND source_type='SHELF'",[$branch,$part])->fetchColumn() === 1,
    'Shelf quarantine record was not retained.');

// Active holds cannot be written off by counting or quarantining them.
$item = insertFixture($pdo,"INSERT INTO repair_quote_items
    (quote_id,part_id,tracks_branch_stock,item_type,description,quantity,unit_price,line_total)
    VALUES (?,?,1,'PART','Reserved batteries',2,100,200)",[$quote,$part]);
stockRun($pdo,'INSERT INTO stock_reservations (quote_item_id,branch_id,part_id,quantity,reserved_by)
    VALUES (?,?,?,?,?)',[$item,$branch,$part,'2.000',$manager['user_id']]);
$state=pilotShelf($pdo,$branch,$part);
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'3.000',$state['movement_id'],
    'count','1.000',$reason,true), 'lower than existing repair reservations');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'3.000',$state['movement_id'],
    'quarantine','2.000',$damage,true), 'already reserved');
checkShelf($pdo,$branch,$part,'3.000');
// An unreserved unit can still be isolated, even while two units are held.
stockShelfAction($pdo,$manager['user_id'],$branch,$part,'3.000',$state['movement_id'],
    'quarantine','1.000',$damage,true);
checkShelf($pdo,$branch,$part,'2.000');
$state=pilotShelf($pdo,$branch,$part);
expectResult($state['reserved']==='2.000', 'A quarantine removed somebody else\'s stock hold.');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'2.000',$state['movement_id'],
    'count','2.000',$reason,true),'No adjustment is needed');
stockShelfAction($pdo,$manager['user_id'],$branch,$part,'2.000',$state['movement_id'],
    'count','3.000',$reason,true);
checkShelf($pdo,$branch,$part,'3.000');
expectResult((int) stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE branch_id=? AND part_id=? AND transaction_type='ADJUSTMENT'",[$branch,$part])->fetchColumn() === 4,
    'Count/quarantine movement history must contain two quarantines and two recounts.');

// Quarantine a removed, already-installed damaged component without adding it back to usable stock.
$replacementPart=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$run.'-REPAIR', 'Replacement repair SSD']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,2)',
    [$branch,$replacementPart]);
$installedItem=insertFixture($pdo,"INSERT INTO repair_quote_items
    (quote_id,part_id,tracks_branch_stock,item_type,description,quantity,unit_price,line_total)
    VALUES (?,?,1,'PART','Installed repair SSD',1,500,500)",[$quote,$replacementPart]);
stockRun($pdo,"UPDATE ticket_devices SET assigned_technician_id=?,repair_status='IN_PROGRESS'
    WHERE ticket_device_id=?",[$technician['user_id'],$device]);
stockChange($pdo,$manager['user_id'],$device,$installedItem,0,'reserve');
$hold=(int) stockRun($pdo,'SELECT reservation_id FROM stock_reservations WHERE quote_item_id=?',
    [$installedItem])->fetchColumn();
stockChange($pdo,$technician['user_id'],$device,$installedItem,$hold,'install');
$firstUse=stockActiveInstallation($pdo,$device,$installedItem);
expectResult($firstUse>0, 'Installed movement was not active.');
checkShelf($pdo,$branch,$replacementPart,'1.000');
expectRefusal(fn()=>stockChange($pdo,$technician['user_id'],$device,$installedItem,
    $firstUse,'quarantine',$damage,true), 'branch manager');
expectRefusal(fn()=>stockChange($pdo,$manager['user_id'],$device,$installedItem,
    $firstUse,'quarantine',$damage,false), 'physically removed');
stockChange($pdo,$manager['user_id'],$device,$installedItem,$firstUse,'quarantine',$damage,true);
checkShelf($pdo,$branch,$replacementPart,'1.000');
expectResult(stockActiveInstallation($pdo,$device,$installedItem) === 0,
    'Quarantined installation still treated as active.');
expectRefusal(fn()=>stockChange($pdo,$manager['user_id'],$device,$installedItem,
    $firstUse,'quarantine',$damage,true), 'Stock changed');
expectRefusal(fn()=>stockCheckInstalled($pdo,$device,$quote), 'Record installation');
expectResult((int) stockRun($pdo,"SELECT COUNT(*) FROM stock_quarantines
    WHERE original_use_movement_id=? AND source_type='INSTALLATION'",
    [$firstUse])->fetchColumn() === 1, 'Missing quarantined-installation record.');
stockChange($pdo,$manager['user_id'],$device,$installedItem,0,'reserve');
$newHold=(int) stockRun($pdo,'SELECT reservation_id FROM stock_reservations WHERE quote_item_id=?',
    [$installedItem])->fetchColumn();
stockChange($pdo,$technician['user_id'],$device,$installedItem,$newHold,'install');
expectResult(stockActiveInstallation($pdo,$device,$installedItem)>$firstUse,
    'Replacement installation was not recorded.');
checkShelf($pdo,$branch,$replacementPart,'0.000');
expectResult((int) stockRun($pdo,'SELECT COUNT(*) FROM ticket_parts_used WHERE ticket_device_id=?',
    [$device])->fetchColumn() === 2, 'Original installed-part history was overwritten.');

// A second authorized manager must finalize quarantines. Disposition cannot be repeated.
$shelfQuarantines=stockRun($pdo,"SELECT quarantine_id FROM stock_quarantines
    WHERE branch_id=? AND part_id=? AND source_type='SHELF'
    ORDER BY quarantine_id",[$branch,$part])->fetchAll(PDO::FETCH_COLUMN);
expectResult(count($shelfQuarantines)===2,'Expected two separate shelf quarantine records.');
$releaseId=(int)$shelfQuarantines[0];
$scrapId=(int)$shelfQuarantines[1];
$reviewReason='Independently verified quantity, condition and sealed holding location.';
expectRefusal(fn()=>stockDecideQuarantine($pdo,$manager['user_id'],$releaseId,
    'RELEASE',$reviewReason,true),'different branch manager');
expectRefusal(fn()=>stockDecideQuarantine($pdo,$cashier['user_id'],$releaseId,
    'RELEASE',$reviewReason,true),'branch manager');
stockDecideQuarantine($pdo,$otherManager['user_id'],$releaseId,'RELEASE',$reviewReason,true);
checkShelf($pdo,$branch,$part,'4.000');
expectRefusal(fn()=>stockDecideQuarantine($pdo,$otherManager['user_id'],$releaseId,
    'RELEASE',$reviewReason,true),'already has a final disposition');
stockDecideQuarantine($pdo,$otherManager['user_id'],$scrapId,'SCRAP',
    'Independently verified damage and consigned unit for permanent scrap.',true);
checkShelf($pdo,$branch,$part,'4.000');
expectResult((int)stockRun($pdo,'SELECT COUNT(*) FROM stock_quarantine_dispositions
    WHERE quarantine_id IN (?,?)',[$releaseId,$scrapId])->fetchColumn()===2,
    'Both quarantine decisions must remain on the ledger.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE branch_id=? AND part_id=? AND reference_code=?",[$branch,$part,'QREL-'.$releaseId])
    ->fetchColumn()===1,'A quarantine release must create one positive stock movement.');
$installedQuarantineId=(int)stockRun($pdo,"SELECT quarantine_id FROM stock_quarantines
    WHERE original_use_movement_id=?",[$firstUse])->fetchColumn();
stockDecideQuarantine($pdo,$otherManager['user_id'],$installedQuarantineId,
    'SCRAP','Broken SSD verified and sent to secure disposal; no usable stock recovered.',true);
checkShelf($pdo,$branch,$replacementPart,'0.000');

// Large discrepancies stay pending. Same-author approval and wrong-role review are refused.
$state=pilotShelf($pdo,$branch,$part);
$large=stockShelfAction($pdo,$manager['user_id'],$branch,$part,$state['on_hand'],
    $state['movement_id'],'count','10.000',$reason,true);
expectResult($large==='pending','A six-unit count correction must request review.');
checkShelf($pdo,$branch,$part,'4.000');
$reviewId=(int)stockRun($pdo,"SELECT stock_count_review_id FROM stock_count_reviews
    WHERE branch_id=? AND part_id=? AND status='PENDING'",[$branch,$part])->fetchColumn();
expectResult($reviewId>0,'Pending second-person count request missing.');
expectRefusal(fn()=>stockShelfAction($pdo,$manager['user_id'],$branch,$part,'4.000',
    $state['movement_id'],'count','10.000',$reason,true),'already awaits');
expectRefusal(fn()=>stockDecideCount($pdo,$manager['user_id'],$reviewId,
    'approve',$reviewReason,true),'different branch manager');
expectRefusal(fn()=>stockDecideCount($pdo,$cashier['user_id'],$reviewId,
    'approve',$reviewReason,true),'branch manager');
// A new quarantine invalidates the old shelf snapshot even when the approver is authorized.
stockShelfAction($pdo,$manager['user_id'],$branch,$part,'4.000',$state['movement_id'],
    'quarantine','1.000',$damage,true);
checkShelf($pdo,$branch,$part,'3.000');
expectRefusal(fn()=>stockDecideCount($pdo,$otherManager['user_id'],$reviewId,
    'approve',$reviewReason,true),'Stock changed');
stockDecideCount($pdo,$otherManager['user_id'],$reviewId,
    'reject','Fresh shelf count is required after newly quarantined damage.',true);
expectRefusal(fn()=>stockDecideCount($pdo,$otherManager['user_id'],$reviewId,
    'approve',$reviewReason,true),'no longer pending');
$state=pilotShelf($pdo,$branch,$part);
expectResult(stockShelfAction($pdo,$manager['user_id'],$branch,$part,$state['on_hand'],
    $state['movement_id'],'count','9.000',$reason,true)==='pending',
    'Corrected high discrepancy should still await independent review.');
$newId=(int)stockRun($pdo,"SELECT stock_count_review_id FROM stock_count_reviews
    WHERE branch_id=? AND part_id=? AND status='PENDING'",[$branch,$part])->fetchColumn();
stockDecideCount($pdo,$otherManager['user_id'],$newId,'approve',$reviewReason,true);
checkShelf($pdo,$branch,$part,'9.000');
expectRefusal(fn()=>stockDecideCount($pdo,$otherManager['user_id'],$newId,
    'approve',$reviewReason,true),'no longer pending');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM inventory_transactions
    WHERE reference_code=?",['COUNT-APPROVED-'.$newId])->fetchColumn()===1,
    'Approved high count must issue exactly one adjustment.');

// Previously approved corrections count toward the rolling 24-hour threshold.
$state=pilotShelf($pdo,$branch,$part);
expectResult(stockShelfAction($pdo,$manager['user_id'],$branch,$part,$state['on_hand'],
    $state['movement_id'],'count','10.000',$reason,true)==='pending',
    'Repeated small counts must not bypass independent approval.');
$cancelId=(int)stockRun($pdo,"SELECT stock_count_review_id FROM stock_count_reviews
    WHERE branch_id=? AND part_id=? AND status='PENDING'",[$branch,$part])->fetchColumn();
expectRefusal(fn()=>stockDecideCount($pdo,$otherManager['user_id'],$cancelId,
    'cancel',$reviewReason,true),'Only the requester');
stockDecideCount($pdo,$manager['user_id'],$cancelId,'cancel',
    'Requester cancelled further correction pending a fresh count.',true);
checkShelf($pdo,$branch,$part,'9.000');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM stock_count_reviews
    WHERE stock_count_review_id=? AND status='CANCELLED'",[$cancelId])->fetchColumn()===1,
    'Cancelled pending review must remain visible in its immutable history.');
echo "PASS: quarantine disposition, independent count approval, stale review and rolling threshold.\n";

