<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/console/transfers.php';
require_once __DIR__ . '/../app/public/tracking.php';
require_once __DIR__ . '/../tools/simulation_data.php';
$pdo=getPDO();
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='repairtrack_test_activity') exit(1);
function checkTransfer(bool $ok,string $case): void {
    if (!$ok) throw new RuntimeException($case);
    echo "PASS $case\n";
}
function deniedTransfer(callable $action): void {
    try { $action(); } catch (DomainException $error) { return; }
    throw new RuntimeException('Expected transfer refusal');
}
$origin=(int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SIM-SOGOD'")->fetchColumn();
$sender=(int)$pdo->query("SELECT user_id FROM users WHERE email='manager@sim.example.test'")->fetchColumn();
$dest=seedRow($pdo,'branches',['branch_code'=>'TEST-DEST','branch_name'=>'Receiving branch']);
$bin=seedRow($pdo,'storage_locations',['branch_id'=>$dest,'location_code'=>'BIN','location_name'=>'Counter','location_type'=>'BRANCH_BIN']);
seedRow($pdo,'storage_locations',['branch_id'=>$origin,'location_code'=>'VAN','location_name'=>'Transit','location_type'=>'TRANSIT']);
$receiver=seedRow($pdo,'users',['full_name'=>'Receiving courier','role'=>'COURIER','email'=>'receiver@example.test','password_hash'=>password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);
seedRow($pdo,'user_branch_assignments',['branch_id'=>$dest,'user_id'=>$receiver]);
$codes='SIM-RT-001-01 SIM-RT-002-01';
deniedTransfer(fn()=>dispatchDevices($pdo,$sender,$origin,$bin,'SIM-RT-001-01 Z-MISSING'));
$code=dispatchDevices($pdo,$sender,$origin,$bin,$codes);
$q=$pdo->prepare('SELECT x.device_transfer_item_id FROM device_transfer_items x JOIN device_transfers t USING(device_transfer_id) WHERE t.transfer_code=? ORDER BY x.device_transfer_item_id');
$q->execute([$code]);
[$a,$b]=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
deniedTransfer(fn()=>dispatchDevices($pdo,$sender,$origin,$bin,$codes));
deniedTransfer(fn()=>confirmTransferItem($pdo,$sender,$a,'IN_TRANSIT','receive',''));
confirmTransferItem($pdo,$receiver,$a,'IN_TRANSIT','receive','Arrived');
deniedTransfer(fn()=>confirmTransferItem($pdo,$receiver,$a,'IN_TRANSIT','receive',''));
confirmTransferItem($pdo,$receiver,$b,'IN_TRANSIT','missing','Absent from parcel');
function trackedCustody(PDO $pdo,string $ref,string $pin): array {
    $q=$pdo->prepare('SELECT portal_code FROM repair_tickets WHERE ticket_code=?');
    $q->execute([$ref]);
    return publicRepair($pdo,$q->fetchColumn(),$pin)['devices'][0];
}
checkTransfer(publicCustody(trackedCustody($pdo,'SIM-RT-001','1000'))==='At Receiving branch','first device arrived');
checkTransfer(trackedCustody($pdo,'SIM-RT-002','1001')['custody_state']==='UNKNOWN','missing device has no confirmed location');
$q=$pdo->prepare('SELECT status FROM device_transfers WHERE transfer_code=?');
$q->execute([$code]);
checkTransfer($q->fetchColumn()==='EXCEPTION','manifest remains open for discrepancy');
deniedTransfer(fn()=>confirmTransferItem($pdo,$receiver,$b,'EXCEPTION','receive',''));
confirmTransferItem($pdo,$receiver,$b,'EXCEPTION','receive','Found and checked at counter');
$q->execute([$code]);
checkTransfer($q->fetchColumn()==='RECEIVED','resolved manifest closed');
checkTransfer(publicCustody(trackedCustody($pdo,'SIM-RT-002','1001'))==='At Receiving branch','customer sees resolved location');
$q=$pdo->prepare('SELECT COUNT(*) FROM device_custody_events e JOIN device_transfers t USING(device_transfer_id) WHERE t.transfer_code=?');
$q->execute([$code]);
checkTransfer((int)$q->fetchColumn()===5,'five custody events retained');

if (getenv('HANDOVER_BROWSER_FIXTURE')) {
    $password = bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id IN (?,?)')
        ->execute([password_hash($password,PASSWORD_DEFAULT),$sender,$receiver]);
    file_put_contents(getenv('HANDOVER_BROWSER_FIXTURE'),json_encode([
        'origin'=>$origin,'destination'=>$bin,'password'=>$password
    ],JSON_THROW_ON_ERROR));
}
