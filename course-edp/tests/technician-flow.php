<?php
declare(strict_types=1);
/** Isolated domain tests: all fictional writes are rolled back. */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR,"Use only disposable repairtrack_edp DB.\n");exit(2);
}
require_once __DIR__ . '/../app/technician.php';
require_once __DIR__ . '/../app/tracking.php';
$db=new PDO('mysql:host='.(getenv('REPAIRTRACK_EDP_TEST_HOST')?:'127.0.0.1').';dbname=repairtrack_edp;charset=utf8mb4',
    getenv('REPAIRTRACK_EDP_TEST_USER')?:'root',getenv('MYSQL_PWD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("SET time_zone='+00:00'");
function techOK(bool $v,string $why): void {if (!$v) throw new RuntimeException('FAIL: '.$why);echo 'PASS: '.$why."\n";}
function techReject(callable $f,string $why): void {try{$f();}catch(DomainException $e){techOK(true,$why);return;}throw new RuntimeException('FAIL accepted: '.$why);}
function techInsert(PDO $db,string $sql,array $args=[]): int {$q=$db->prepare($sql);$q->execute($args);return (int)$db->lastInsertId();}
$branch=(int)$db->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if (!$branch) throw new RuntimeException('Missing Sogod seed');
$db->beginTransaction();
try {
  $u=bin2hex(random_bytes(6));
  $manager=techInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Tech Test Manager',?,?,'MANAGER')",
      ["manager-$u@example.test",password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
  $tech=techInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Assigned Technician',?,?,'TECHNICIAN')",
      ["tech-$u@example.test",password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
  $other=techInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Other Technician',?,?,'TECHNICIAN')",
      ["other-tech-$u@example.test",password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
  $cashier=techInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Cashier Sample',?,?,'CASHIER')",
      ["cash-$u@example.test",password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
  foreach ([$manager,$tech,$other,$cashier] as $id) $db->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)')->execute([$id,$branch]);
  $customer=techInsert($db,"INSERT INTO customers(full_name,phone) VALUES('Fictional Technician Customer','09123456789')");
  $dev=techInsert($db,"INSERT INTO customer_devices(customer_id,device_type_code) VALUES (?,'LAPTOP')",[$customer]);
  $ticket=techInsert($db,"INSERT INTO repair_tickets(customer_id,branch_id,ticket_code,portal_code,portal_pin_hash,
    ticket_state,issued_at,created_by_user_id,terms_version,terms_signed_at,terms_signature_path)
    VALUES (?,? ,? ,? ,?,'OPEN',UTC_TIMESTAMP(),?,'CLASSROOM-1',UTC_TIMESTAMP(),'signed/tech-fixture.png')",
    [$customer,$branch,'RT-TECH-'.$u,strtoupper(substr($u,0,5)),password_hash('6789',PASSWORD_DEFAULT),$manager]);
  $loc=(int)$db->query("SELECT location_id FROM storage_locations WHERE branch_id=$branch AND location_code='COUNTER'")->fetchColumn();
  $device=techInsert($db,"INSERT INTO ticket_devices(ticket_id,customer_id,branch_id,customer_device_id,sequence_no,
    item_code,device_type_code,status_code,custody_state,location_id,reported_symptoms)
    VALUES (?,?,?,?,1,?,'LAPTOP','RECEIVED','AT_LOCATION',?,'Laptop overheats')",
    [$ticket,$customer,$branch,$dev,'RT-TECH-'.$u.'-01',$loc]);
  edpAssignTechnician($db,$manager,$device,$tech);
  techReject(fn()=>edpTechQueue($db,$cashier), 'cashier cannot open technician queue');
  techOK(edpTechQueue($db,$other)['total']===0, 'other technician has no access to assigned device');
  techOK(edpTechDevice($db,$other,$device)===null, 'other technician cannot open repair details');
  techOK(edpTechQueue($db,$tech)['total']===1, 'assigned technician sees only active device');
  techReject(fn()=>edpTechSaveDiagnosis($db,$tech,$device,'Too early'), 'diagnosis requires diagnosing stage');
  edpChangeRepairStatus($db,$tech,$device,'DIAGNOSING','Starting diagnosis');
  techReject(fn()=>edpTechSaveDiagnosis($db,$tech,$device,'short'),'short diagnosis rejected');
  edpTechSaveDiagnosis($db,$tech,$device,'The cooling fan is blocked by dust and thermal paste is dry.');
  techReject(fn()=>edpTechSaveQuoteDraft($db,$other,$device,'600.00','250.00','0.00'), 'other technician cannot draft quote');
  techReject(fn()=>edpChangeRepairStatus($db,$tech,$device,'AWAITING_CUSTOMER_APPROVAL'), 'cannot await customer approval without sent quotation');
  techReject(fn()=>edpChangeRepairStatus($db,$tech,$device,'IN_PROGRESS'), 'cannot start repair without approved quote');
  $qid=edpTechSaveQuoteDraft($db,$tech,$device,'600.00','250.00','0.00');
  techOK($qid>0, 'technician can prepare unapproved quotation draft');
  techOK(edpTechSaveQuoteDraft($db,$tech,$device,'650.00','200.00','0.00')===$qid, 'editable draft keeps quotation version');
  $details=edpTechDevice($db,$tech,$device);
  techOK($details!==null && $details['quote']['quote_status']==='DRAFT' &&
     $details['quote']['total_amount']==='850.00','quote draft visible to assigned technician only');
  $public=edpTrackingView($db,$ticket);
  techOK($public!==null && $public['devices'][0]['quote']===null &&
      !str_contains(json_encode($public,JSON_THROW_ON_ERROR),'cooling fan'),
      'draft quote and internal diagnosis are hidden from customer');
  $db->prepare("UPDATE repair_quotes SET quote_status='APPROVED',sent_at=UTC_TIMESTAMP(),
    decision_recorded_by=?,decision_at=UTC_TIMESTAMP() WHERE quote_id=?")
    ->execute([$manager,$qid]); // Fixture emulates later manager/customer approval UI.
  $started=edpChangeRepairStatus($db,$tech,$device,'IN_PROGRESS','Repair has started.');
  techOK(is_string($started) && $started!=='', 'first in-repair status records a start time');
  edpChangeRepairStatus($db,$tech,$device,'TESTING','Testing repaired laptop.');
  $again=edpChangeRepairStatus($db,$tech,$device,'IN_PROGRESS','Further repair required.');
  techOK($again===$started, 'second in-repair entry preserves first start timestamp');
  techOK(count(edpTechQueue($db,$tech)['rows'])===1, 'active queue still reflects updated status');
  $public=edpTrackingView($db,$ticket);
  techOK($public['devices'][0]['repair_started_at']===$started &&
     count($public['devices'][0]['timeline'])===4,'customer-safe timeline reflects technician status updates');
  $db->prepare('UPDATE users SET is_active=0 WHERE user_id=?')->execute([$tech]);
  techReject(fn()=>edpTechQueue($db,$tech),'disabled technician cannot query the queue');
  echo "All academic technician domain checks passed.\n";
} finally {if($db->inTransaction())$db->rollBack();}
