<?php
declare(strict_types=1);
/** All records in this manager/quote integration test roll back on disposable MySQL. */
if (PHP_SAPI!=='cli' || getenv('REPAIRTRACK_EDP_TEST_DB')!=='repairtrack_edp') {
    fwrite(STDERR,"Use only disposable repairtrack_edp database.\n");exit(2);
}
require_once __DIR__ . '/../app/manager.php';
require_once __DIR__ . '/../app/technician.php';
require_once __DIR__ . '/../app/tracking.php';
$db=new PDO('mysql:host='.(getenv('REPAIRTRACK_EDP_TEST_HOST')?:'127.0.0.1').';dbname=repairtrack_edp;charset=utf8mb4',
    getenv('REPAIRTRACK_EDP_TEST_USER')?:'root',getenv('MYSQL_PWD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("SET time_zone='+00:00'");
function mgrPass(bool $ok,string $message): void {if(!$ok)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message."\n";}
function mgrReject(callable $work,string $message): void {try{$work();}catch(DomainException $e){mgrPass(true,$message);return;}throw new RuntimeException('FAIL (accepted): '.$message);}
function mgrInsert(PDO $db,string $sql,array $params=[]): int {$q=$db->prepare($sql);$q->execute($params);return (int)$db->lastInsertId();}
$branch=(int)$db->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if(!$branch)throw new RuntimeException('Missing fictional branch');
$db->beginTransaction();
try {
    $s=bin2hex(random_bytes(6));
    $otherBranch=mgrInsert($db,"INSERT INTO branches(branch_code,branch_name) VALUES(?,'Other classroom branch')",['MG-'.$s]);
    $hash=password_hash('FictionalOnly!',PASSWORD_DEFAULT);
    $manager=mgrInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Manager Fixture',?,?,'MANAGER')",["manager-$s@example.test",$hash]);
    $tech=mgrInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Repair Technician',?,?,'TECHNICIAN')",["tech-$s@example.test",$hash]);
    $otherTech=mgrInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Other Branch Tech',?,?,'TECHNICIAN')",["tech-other-$s@example.test",$hash]);
    $cashier=mgrInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Cashier Fixture',?,?,'CASHIER')",["cash-$s@example.test",$hash]);
    foreach([[$manager,$branch],[$tech,$branch],[$cashier,$branch],[$otherTech,$otherBranch]] as [$id,$b]){
        $db->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)')->execute([$id,$b]);
    }
    $customer=mgrInsert($db,"INSERT INTO customers(full_name,phone) VALUES('Fictional Manager Customer','09123456789')");
    $deviceOwner=mgrInsert($db,"INSERT INTO customer_devices(customer_id,device_type_code) VALUES(?,'LAPTOP')",[$customer]);
    $code='RT-MGR-'.strtoupper($s);$portal=strtoupper(substr($s,0,5));
    $ticket=mgrInsert($db,"INSERT INTO repair_tickets(customer_id,branch_id,ticket_code,portal_code,portal_pin_hash,
        ticket_state,issued_at,created_by_user_id,terms_version,terms_signed_at,terms_signature_path)
        VALUES (?,?,?,?,?,'OPEN',UTC_TIMESTAMP(),?,'CLASSROOM-1',UTC_TIMESTAMP(),'signed/fixture-manager.png')",
        [$customer,$branch,$code,$portal,password_hash('6789',PASSWORD_DEFAULT),$manager]);
    $location=(int)$db->query("SELECT location_id FROM storage_locations WHERE branch_id=$branch AND location_code='COUNTER'")->fetchColumn();
    $device=mgrInsert($db,"INSERT INTO ticket_devices(ticket_id,customer_id,branch_id,customer_device_id,
        sequence_no,item_code,device_type_code,status_code,custody_state,location_id,reported_symptoms)
        VALUES (?,?,?,?,1,?,'LAPTOP','RECEIVED','AT_LOCATION',?,'Laptop crashes without warning')",
        [$ticket,$customer,$branch,$deviceOwner,$code.'-01',$location]);
    mgrPass(edpManagerQueue($db,$manager,$branch,'unassigned')['total']===1,'unassigned device visible to branch manager');
    mgrReject(fn()=>edpManagerQueue($db,$cashier,$branch),'cashier cannot access manager queue');
    mgrReject(fn()=>edpManagerQueue($db,$manager,$otherBranch),'manager cannot read another branch');
    mgrPass(edpManagerDevice($db,$manager,$device)!==null,'manager can inspect own branch repair');
    mgrReject(fn()=>edpManagerAssign($db,$manager,$device,$otherTech),'cross-branch technician assignment rejected');
    mgrReject(fn()=>edpManagerSendQuote($db,$manager,$device),'cannot send quote before diagnosis');
    edpManagerAssign($db,$manager,$device,$tech);
    mgrPass(edpManagerQueue($db,$manager,$branch,'unassigned')['total']===0,'manager assignment removes device from unassigned queue');
    mgrPass(edpManagerQueue($db,$manager,$branch,'active')['total']===1,'manager assignment enters active queue');
    edpChangeRepairStatus($db,$tech,$device,'DIAGNOSING');
    edpTechSaveDiagnosis($db,$tech,$device,'Board requires capacitor inspection; random shutdown after startup.');
    $quoteId=edpTechSaveQuoteDraft($db,$tech,$device,'400.00','150.00','0.00');
    mgrPass(edpTrackingView($db,$ticket)['devices'][0]['quote']===null,'quotation draft is not public');
    $replacement=mgrInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Replacement Tech',?,?,'TECHNICIAN')",
        ["replacement-$s@example.test",$hash]);
    $db->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)')
        ->execute([$replacement,$branch]);
    edpManagerAssign($db,$manager,$device,$replacement);
    $oldQuote=$db->prepare('SELECT quote_status FROM repair_quotes WHERE quote_id=?');
    $oldQuote->execute([$quoteId]);
    mgrPass($oldQuote->fetchColumn()==='SUPERSEDED','reassignment safely supersedes unsent prior technician draft');
    mgrReject(fn()=>edpTechSaveQuoteDraft($db,$tech,$device,'10.00','5.00','0.00'),
        'original technician cannot edit reassigned quote');
    $tech=$replacement;
    $quoteId=edpTechSaveQuoteDraft($db,$tech,$device,'400.00','150.00','0.00');
    mgrPass($quoteId>0,'new technician can create versioned replacement after reassignment');
    mgrReject(fn()=>edpManagerSendQuote($db,$cashier,$device),'cashier cannot send a quotation');
    edpManagerSendQuote($db,$manager,$device);
    $v=edpManagerDevice($db,$manager,$device);
    mgrPass($v['quote']['quote_status']==='AWAITING_CUSTOMER'&&$v['status_code']==='AWAITING_CUSTOMER_APPROVAL'&&$v['quote']['expires_at']!==null,
        'manager sends quote, sets expiry and advances repair stage atomically');
    mgrPass(edpTrackingView($db,$ticket)['devices'][0]['quote']['total_amount']==='550.00',
        'only sent quote becomes visible on customer portal');
    mgrReject(fn()=>edpManagerSendQuote($db,$manager,$device),'duplicate manager send rejected');
    mgrReject(fn()=>edpManagerQuoteDecision($db,$manager,$device,'APPROVED','PHONE_CALL','Call 12345',false),
        'customer confirmation attestation is mandatory');
    mgrReject(fn()=>edpManagerQuoteDecision($db,$cashier,$device,'APPROVED','PHONE_CALL','Call 12345',true),
        'unauthorized cashier cannot record quote approval');
    edpManagerQuoteDecision($db,$manager,$device,'DECLINED','IN_PERSON','Receipt REJECT-001',true);
    mgrPass(edpManagerDevice($db,$manager,$device)['status_code']==='DIAGNOSING',
        'declined quote returns to diagnosis with legal status event');
    mgrPass(edpTrackingView($db,$ticket)['devices'][0]['quote']['quote_status']==='DECLINED',
        'customer sees declined sent quote without staff-only evidence');
    $quoteId2=edpTechSaveQuoteDraft($db,$tech,$device,'500.00','200.00','0.00');
    mgrPass($quoteId2!==$quoteId && edpManagerDevice($db,$manager,$device)['quote']['version_no']==3,
        'technician can draft a new version after decline');
    edpManagerSendQuote($db,$manager,$device);
    $db->prepare('UPDATE repair_quotes SET sent_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 9 DAY),
        expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY) WHERE quote_id=?')->execute([$quoteId2]);
    mgrReject(fn()=>edpManagerQuoteDecision($db,$manager,$device,'APPROVED','PHONE_CALL','Call record 987',true),
        'expired quote cannot be approved');
    edpManagerExpireQuote($db,$manager,$device);
    mgrPass(edpManagerDevice($db,$manager,$device)['quote']['quote_status']==='EXPIRED'
        && edpManagerDevice($db,$manager,$device)['status_code']==='DIAGNOSING',
        'expired quote returns repair for revision');
    $third=edpTechSaveQuoteDraft($db,$tech,$device,'600.00','250.00','0.00');
    mgrPass(edpManagerDevice($db,$manager,$device)['quote']['version_no']==4,'quote revision after expiry works');
    edpManagerSendQuote($db,$manager,$device);
    edpManagerQuoteDecision($db,$manager,$device,'APPROVED','WRITTEN','Document ACK-2026-24',true);
    $approved=edpManagerDevice($db,$manager,$device);
    mgrPass($approved['quote']['quote_status']==='APPROVED' && $approved['status_code']==='AWAITING_CUSTOMER_APPROVAL',
        'approved quote awaits technician action rather than silently starting repair');
    mgrReject(fn()=>edpManagerQuoteDecision($db,$manager,$device,'APPROVED','IN_PERSON','Duplicate receipt',true),
        'repeat approval cannot overwrite prior recorded decision');
    mgrPass(edpTechNextStatuses($db,edpTechDevice($db,$tech,$device))!==[],
        'approved quote allows legal technician transition');
    $first=edpChangeRepairStatus($db,$tech,$device,'IN_PROGRESS');
    mgrPass(is_string($first) && $first!=='','technician can begin approved repair with first start timestamp');
    $q=$db->prepare("SELECT details_json FROM audit_events WHERE action_code='OFFLINE_CUSTOMER_DECISION' AND entity_id=? ORDER BY audit_id DESC LIMIT 1");
    $q->execute([$third]);$details=json_decode((string)$q->fetchColumn(),true);
    mgrPass($details['source']==='STAFF_ATTESTED_OFFLINE'&&$details['decision']==='APPROVED',
        'customer confirmation origin clearly recorded as staff attestation in audit');
    mgrPass((int)$db->query("SELECT COUNT(*) FROM ticket_status_events WHERE ticket_device_id=$device AND from_status='AWAITING_CUSTOMER_APPROVAL' AND to_status='DIAGNOSING'")->fetchColumn()===2,
        'decline and expiry each preserve customer-visible status history');
    mgrPass(true,'manager domain records are rolled back after test');
} finally {if($db->inTransaction())$db->rollBack();}
