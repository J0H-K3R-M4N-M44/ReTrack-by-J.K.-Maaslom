<?php
declare(strict_types=1);
/** Rollback-only domain acceptance on a disposable isolated academic database. */
if (PHP_SAPI!=='cli'||getenv('REPAIRTRACK_EDP_TEST_DB')!=='repairtrack_edp') {
    fwrite(STDERR,"Cashier test requires disposable repairtrack_edp.\n");exit(2);
}
require_once __DIR__.'/../app/cashier.php';
$db=new PDO('mysql:host='.(getenv('REPAIRTRACK_EDP_TEST_HOST')?:'127.0.0.1').';dbname=repairtrack_edp;charset=utf8mb4',
    getenv('REPAIRTRACK_EDP_TEST_USER')?:'root',getenv('MYSQL_PWD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("SET time_zone='+00:00'");
function cashPass(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException('FAIL: '.$msg);echo 'PASS: '.$msg."\n";}
function cashReject(callable $run,string $msg):void {try{$run();}catch(DomainException $e){cashPass(true,$msg);return;}throw new RuntimeException('FAIL accepted: '.$msg);}
function cashInsert(PDO $pdo,string $sql,array $args=[]):int {$s=$pdo->prepare($sql);$s->execute($args);return (int)$pdo->lastInsertId();}
$branch=(int)$db->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if(!$branch)throw new RuntimeException('Missing seed branch');
$db->beginTransaction();
try {
    $suffix=bin2hex(random_bytes(6));$portal=strtoupper(substr($suffix,0,5));
    $otherBranch=cashInsert($db,"INSERT INTO branches(branch_code,branch_name) VALUES (?,'Other fictional cashier branch')",['CA-'.$suffix]);
    $hash=password_hash('FictionalOnly!',PASSWORD_DEFAULT);
    $cashier=cashInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Fictional Cashier',?,?,'CASHIER')",['cash-'.$suffix.'@example.test',$hash]);
    $other=cashInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Other Cashier',?,?,'CASHIER')",['cash-other-'.$suffix.'@example.test',$hash]);
    $tech=cashInsert($db,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Fictional Technician',?,?,'TECHNICIAN')",['tech-'.$suffix.'@example.test',$hash]);
    foreach([[$cashier,$branch],[$other,$otherBranch],[$tech,$branch]] as [$u,$b])$db->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)')->execute([$u,$b]);
    $customer=cashInsert($db,"INSERT INTO customers(full_name,phone) VALUES ('Cashier Demo Customer','09123456789')");
    $code='RT-'.strtoupper(bin2hex(random_bytes(6)));
    $ticket=cashInsert($db,"INSERT INTO repair_tickets(customer_id,branch_id,ticket_code,portal_code,portal_pin_hash,ticket_state,issued_at,created_by_user_id,terms_version,terms_signed_at,terms_signature_path)
        VALUES (?,?,?,?,?,'OPEN',UTC_TIMESTAMP(),?,'CLASSROOM-1',UTC_TIMESTAMP(),'signed/cashier-test.png')",
        [$customer,$branch,$code,$portal,password_hash('6789',PASSWORD_DEFAULT),$cashier]);
    $s=$db->prepare("SELECT location_id FROM storage_locations WHERE branch_id=? AND location_code='COUNTER'");$s->execute([$branch]);$location=(int)$s->fetchColumn();
    $ids=[];
    foreach([['LAPTOP','READY_FOR_PICKUP','125.50'],['DESKTOP','TESTING','74.50']] as $i=>$row){
        [$type,$status,$amount]=$row;
        $owner=cashInsert($db,'INSERT INTO customer_devices(customer_id,device_type_code) VALUES (?,?)',[$customer,$type]);
        $item=cashInsert($db,"INSERT INTO ticket_devices(ticket_id,customer_id,branch_id,customer_device_id,sequence_no,item_code,device_type_code,status_code,custody_state,location_id,reported_symptoms)
            VALUES (?,?,?,?,?,?,?,?,'AT_LOCATION',?,'Fictional diagnosis')",
            [$ticket,$customer,$branch,$owner,$i+1,$code.'-0'.($i+1),$type,$status,$location]);
        $ids[]=$item;
        cashInsert($db,"INSERT INTO repair_quotes(ticket_device_id,quote_status,version_no,labor_amount,total_amount,prepared_by_user_id,decision_recorded_by,decision_at,sent_at)
            VALUES (?,'APPROVED',1,?,?, ?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())",
            [$item,$amount,$amount,$tech,$cashier]);
    }
    cashPass(edpCashierFindTicket($db,$cashier,$code)===$ticket,'exact internal code finds authorized ticket');
    cashReject(fn()=>edpCashierFindTicket($db,$cashier,$portal),'customer portal code rejected at cash desk');
    cashPass(edpCashierFindTicket($db,$other,$code)===null,'other branch exact Ticket Code not visible');
    cashPass(edpCashierTicket($db,$other,$ticket)===null,'other branch cannot read cash desk details');
    cashPass(edpCashierTicket($db,$tech,$ticket)===null,'technician cannot read cashier details');
    cashReject(fn()=>edpCashierIssueBill($db,$other,$ticket),'cross-branch invoice issuance rejected');
    cashReject(fn()=>edpCashierIssueBill($db,$cashier,$ticket),'billing blocked while one device is not ready');
    $db->prepare("UPDATE ticket_devices SET status_code='READY_FOR_PICKUP' WHERE ticket_device_id=?")->execute([$ids[1]]);
    $bill=edpCashierIssueBill($db,$cashier,$ticket);
    cashPass($bill===edpCashierIssueBill($db,$cashier,$ticket),'repeat invoice request is idempotent');
    $q=$db->prepare('SELECT COUNT(*),SUM(amount) FROM repair_bill_items WHERE bill_id=?');$q->execute([$bill]);$items=$q->fetch(PDO::FETCH_NUM);
    cashPass((int)$items[0]===2&&edpCents((string)$items[1])===20000,'two approved quotes become exact 200-peso itemized bill');
    $data=edpCashierTicket($db,$cashier,$ticket);
    cashPass($data['due_cents']===20000&&count($data['bill_items'])===2,'cash desk shows outstanding balance');
    cashReject(fn()=>edpCashierConfirmPickup($db,$cashier,$ticket,'09123456789'),'pickup blocked before settlement');
    cashReject(fn()=>edpRecordCashPayment($db,$tech,$bill,'50.00',str_repeat('3',64)),'technician cannot collect counter cash');
    cashReject(fn()=>edpRecordCashPayment($db,$cashier,$bill,'30.00',str_repeat('a',64),'COD'),'unverified COD is rejected');
    $payment=edpRecordCashPayment($db,$cashier,$bill,'50.00',str_repeat('a',64));
    cashPass($payment===edpRecordCashPayment($db,$cashier,$bill,'50.00',str_repeat('a',64)),'payment retry uses same receipt');
    cashReject(fn()=>edpRecordCashPayment($db,$cashier,$bill,'51.00',str_repeat('a',64)),'changed replay payment is rejected');
    cashReject(fn()=>edpRecordCashPayment($db,$cashier,$bill,'151.00',str_repeat('b',64)),'overpayment rejected');
    cashPass(edpCashierTicket($db,$cashier,$ticket)['due_cents']===15000,'partial cash reduces balance exactly');
    cashPass(edpCashierReceipt($db,$other,$payment)===null,'other branch cannot read payment receipt');
    cashPass(edpCashierReceipt($db,$tech,$payment)===null,'technician cannot read payment receipt');
    cashPass(edpCashierReceipt($db,$cashier,$payment)['due_cents']===15000,'receipt shows current balance');
    $second=edpRecordCashPayment($db,$cashier,$bill,'150.00',str_repeat('b',64));
    cashPass(edpCashierTicket($db,$cashier,$ticket)['due_cents']===0,'full cash collection settles bill');
    cashReject(fn()=>edpCashierConfirmPickup($db,$cashier,$ticket,'09999999999'),'wrong full phone cannot release devices');
    edpCashierConfirmPickup($db,$cashier,$ticket,'09123456789');
    cashPass(edpCashierTicket($db,$cashier,$ticket)['ticket_state']==='CLOSED','ticket closed by authorized counter pickup');
    $q=$db->prepare("SELECT COUNT(*) FROM ticket_devices WHERE ticket_id=? AND status_code='COMPLETED'
        AND custody_state='WITH_CUSTOMER' AND return_handover_at IS NOT NULL");$q->execute([$ticket]);
    cashPass((int)$q->fetchColumn()===2,'pickup timestamps and customer custody recorded on both devices');
    cashReject(fn()=>edpCashierConfirmPickup($db,$cashier,$ticket,'09123456789'),'duplicate counter handover rejected');
    cashReject(fn()=>edpRecordCashPayment($db,$cashier,$bill,'1.00',str_repeat('c',64)),'closed ticket cannot accept payment');
    $q=$db->prepare('SELECT COUNT(*) FROM repair_bill_payments WHERE bill_id=?');$q->execute([$bill]);
    cashPass((int)$q->fetchColumn()===2&&$payment!==$second,'only two real payments were inserted');
} finally {if($db->inTransaction())$db->rollBack();}
