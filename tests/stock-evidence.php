<?php
declare(strict_types=1);
// Run only from disposable database fixture using --stock-evidence.
if (PHP_SAPI!=='cli' || !isset($pdo,$staff,$run,$branch)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/inventory/evidence.php';
[$sender,$cashier,,$receiver,$technician]=$staff;
$dest=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-EVIDENCE','Evidence review receiving branch']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$receiver['user_id'],$dest]);
$approver=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'ADMIN')",['Independent evidence approver',$run.'-proof@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$approver,$branch]);
$outsideBranch=insertFixture($pdo,'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)',
    [$run.'-PRIVATE','Third unrelated branch']);
$outsider=insertFixture($pdo,"INSERT INTO users (full_name,email,password_hash,role)
    VALUES (?,?,?,'MANAGER')",['Unrelated manager',$run.'-other@example.test','unused']);
stockRun($pdo,'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$outsider,$outsideBranch]);
$part=insertFixture($pdo,'INSERT INTO parts (sku,part_name) VALUES (?,?)',
    [$run.'-PHOTO','Evidence SSD']);
stockRun($pdo,'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand)
    VALUES (?,?,3)',[$branch,$part]);
$dispatchReason='Two verified SSDs left source branch inside a signed tamper-evident carton.';
$note='Receiving staff verified missing package and signed the courier exception.';
$lossNote='The last SSD was not located after receiving branch count and seal review.';
$approveNote='Independent source manager checked shipment, discrepancy and investigation.';
$ref='PHOTO-'.$run.'-INVENTORY-INCIDENT';
$code=partsTransferDispatch($pdo,$sender['user_id'],$branch,$dest,
    $run.'-PHOTO,2',$dispatchReason,true);
$item=(int)stockRun($pdo,'SELECT item.inventory_transfer_item_id
    FROM inventory_transfer_items item
    JOIN inventory_transfers transfer ON transfer.inventory_transfer_id=item.inventory_transfer_id
    WHERE transfer.transfer_code=?',[$code])->fetchColumn();
partsTransferReceive($pdo,$receiver['user_id'],$item,'0.000',
    'missing','0.000',$note,true);
$loss=partsLossReport($pdo,$receiver['user_id'],$item,'0.000',$ref,$lossNote,true);
$private=stockEvidenceDirectory();
expectResult(is_dir($private) && !str_starts_with($private,realpath(__DIR__.'/..').DIRECTORY_SEPARATOR),
    'Configured inventory evidence storage is not isolated from repository.');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8//8/AwMDEwMDAwMDAwAkBgMB/DXemwAAAABJRU5ErkJggg==',true);
expectResult(is_string($png) && strlen($png)>50,'Private PNG fixture invalid.');
function evidenceFixturePath(string $ext, string $data): string
{
    $path=stockEvidencePath(bin2hex(random_bytes(24)).'.'.$ext);
    if (file_put_contents($path,$data)===false) throw new RuntimeException('Could not create evidence fixture.');
    return $path;
}
$path=evidenceFixturePath('png',$png);
$details=stockEvidenceInspect($path);
expectResult($details['mime']==='image/png','Valid PNG was not recognized by content.');
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$cashier['user_id'],'LOSS',$loss,
    $path,'evidence.png',$details),'unavailable');
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$outsider,'LOSS',$loss,
    $path,'evidence.png',$details),'unavailable');
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,
    $path,'evidence.pdf',$details),'extension does not match');
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,
    $path,'evidence.svg',$details),'extension does not match');
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'RECOVERY',$loss,
    $path,'evidence.png',$details),'unavailable');
$id=stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,$path,
    'loss-inspection.png',$details);
expectResult($id>0,'Private loss attachment metadata was not inserted.');
$entry=stockRun($pdo,'SELECT sha256,stored_name,mime_type,file_bytes FROM stock_transfer_evidence
    WHERE evidence_id=?',[$id])->fetch();
expectResult($entry && $entry['sha256']===$details['sha256'] &&
    $entry['mime_type']==='image/png' && (int)$entry['file_bytes']===strlen($png),
    'Private evidence hash or upload metadata was not stored correctly.');
expectResult(count(stockEvidenceList($pdo,$approver,'LOSS',$loss))===1,
    'Independent source reviewer cannot view evidence from assigned branch.');
expectResult(count(stockEvidenceList($pdo,$receiver['user_id'],'LOSS',$loss))===1,
    'Receiving branch cannot access its own evidence.');
expectRefusal(fn()=>stockEvidenceList($pdo,$cashier['user_id'],'LOSS',$loss),'unavailable');
expectRefusal(fn()=>stockEvidenceList($pdo,$outsider,'LOSS',$loss),'unavailable');
expectRefusal(fn()=>stockEvidenceRead($pdo,$outsider,$id),'unavailable');
expectRefusal(fn()=>stockEvidenceRead($pdo,$cashier['user_id'],$id),'unavailable');
$file=stockEvidenceRead($pdo,$approver,$id);
expectResult($file['path']===$path && $file['mime']==='image/png',
    'Authorized read did not return the private verified original.');
expectResult((int)stockRun($pdo,"SELECT COUNT(*) FROM audit_events
    WHERE entity_type='stock_transfer_evidence' AND entity_id=?
    AND action='STOCK_EVIDENCE_DOWNLOADED'",[$id])->fetchColumn()===1,
    'Authorized evidence access was not independently audited.');
file_put_contents($path, $png.'tampered');
expectRefusal(fn()=>stockEvidenceRead($pdo,$approver,$id),'did not pass');
file_put_contents($path,$png);
expectResult(stockEvidenceRead($pdo,$approver,$id)['bytes']===strlen($png),
    'Restored evidence did not pass integrity revalidation.');
$fake=evidenceFixturePath('png','<script>alert(1)</script>');
expectRefusal(fn()=>stockEvidenceInspect($fake),'Only verified');
unlink($fake);
$oversize=evidenceFixturePath('png',str_repeat('X',5*1024*1024+1));
expectRefusal(fn()=>stockEvidenceInspect($oversize),'at most 5 MB');
unlink($oversize);
// Six immutable attachments per case, no seventh file.
for ($n=1;$n<6;$n++) {
    $extra=evidenceFixturePath('png',$png);
    stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,$extra,
        "loss-review-$n.png",stockEvidenceInspect($extra));
}
expectResult(count(stockEvidenceList($pdo,$approver,'LOSS',$loss))===6,
    'Evidence limit of six files per case was not enforced.');
$seventh=evidenceFixturePath('png',$png);
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,
    $seventh,'seventh.png',stockEvidenceInspect($seventh)),'six evidence files');
unlink($seventh);
partsLossDecide($pdo,$approver,$loss,'approve',$approveNote,true);
$late=evidenceFixturePath('png',$png);
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'LOSS',$loss,
    $late,'late.png',stockEvidenceInspect($late)),'before the investigation');
unlink($late);
expectResult(count(stockEvidenceList($pdo,$approver,'LOSS',$loss))===6,
    'Closed loss evidence must remain available for authorized audit.');
// A separate pending recovery case also accepts private evidence.
$recovery=partsRecoveryReport($pdo,$receiver['user_id'],$loss,'0.000',
    '1.000','RECOVERY-'.$run.'-SIGNED',
    'Physically found one missing SSD and isolated it for independent inspection.',true);
$recoveredPhoto=evidenceFixturePath('png',$png);
$recoveryFile=stockEvidenceSaveStaged($pdo,$receiver['user_id'],'RECOVERY',$recovery,
    $recoveredPhoto,'recovery-photo.png',stockEvidenceInspect($recoveredPhoto));
expectResult(count(stockEvidenceList($pdo,$approver,'RECOVERY',$recovery))===1,
    'Reviewer cannot view the private recovery case photo.');
partsRecoveryDecide($pdo,$approver,$recovery,'approve',
    'Independently verified recovered SSD condition and signed physical inspection.',true);
expectResult(stockEvidenceRead($pdo,$approver,$recoveryFile)['bytes']===strlen($png),
    'Recovery evidence became unavailable after case approval.');
$after=evidenceFixturePath('png',$png);
expectRefusal(fn()=>stockEvidenceSaveStaged($pdo,$receiver['user_id'],'RECOVERY',$recovery,
    $after,'after.png',stockEvidenceInspect($after)),'before the investigation');
unlink($after);
echo "PASS: private image validation, cross-branch authorization, audit, checksums, six-file limit and case closure.\n";
