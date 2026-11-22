<?php
declare(strict_types=1);

/** Two-process finance serialization test on an entirely disposable DB. */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR, "Use only a disposable repairtrack_edp test database.\n");
    exit(2);
}
require_once __DIR__ . '/../app/actions.php';
function conDb(): PDO
{
    $db = new PDO('mysql:host=' . (getenv('REPAIRTRACK_EDP_TEST_HOST') ?: '127.0.0.1') .
        ';dbname=repairtrack_edp;charset=utf8mb4', getenv('REPAIRTRACK_EDP_TEST_USER') ?: 'root',
        getenv('MYSQL_PWD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 7]);
    $db->exec("SET time_zone='+00:00'");
    $db->exec('SET innodb_lock_wait_timeout = 6');
    return $db;
}
if (($argv[1] ?? '') === '--worker') {
    $db = conDb();
    $db->beginTransaction();
    try {
        edpRecordCashPayment($db,(int)$argv[3],(int)$argv[2],'60.00',str_repeat('1',64));
        file_put_contents($argv[4], 'locked');
        usleep(950000); // The second independent connection must wait for this commit.
        $db->commit();
        exit(0);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
$pdo = conDb();
$branch=(int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
if (!$branch) throw new RuntimeException('Missing fictional branch');
$actor=0; $customer=0; $ticket=0; $bill=0; $process=null; $pipes=[];
$signal=tempnam(sys_get_temp_dir(),'edp-pay-');
if ($signal===false) throw new RuntimeException('Cannot allocate temporary test signal');
unlink($signal);
try {
    $suffix=bin2hex(random_bytes(4));
    $q=$pdo->prepare("INSERT INTO users(full_name,email,password_hash,role_code) VALUES('Concurrent cashier',?,?, 'CASHIER')");
    $q->execute(['concurrent-'.$suffix.'@example.test',password_hash('SyntheticTestOnly!',PASSWORD_DEFAULT)]);
    $actor=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES(?,?)')->execute([$actor,$branch]);
    $pdo->prepare("INSERT INTO customers(full_name,phone) VALUES('Concurrent fictitious customer','09123456789')")->execute();
    $customer=(int)$pdo->lastInsertId();
    $portal=strtoupper(substr(bin2hex(random_bytes(3)),0,5));
    $pdo->prepare("INSERT INTO repair_tickets(customer_id,branch_id,ticket_code,portal_code,portal_pin_hash,
        ticket_state,issued_at,created_by_user_id,terms_version,terms_signed_at,terms_signature_path)
        VALUES(?,?,?, ?,?,'OPEN',UTC_TIMESTAMP(),?,'CLASSROOM-1',UTC_TIMESTAMP(),'signed/concurrency.png')")
        ->execute([$customer,$branch,'RT-CON-'.$suffix,$portal,password_hash('6789',PASSWORD_DEFAULT),$actor]);
    $ticket=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO repair_bills(ticket_id,bill_number,bill_status,total_amount,subtotal,created_by_user_id,issued_at)
        VALUES(? ,?,'ISSUED',100.00,100.00,?,UTC_TIMESTAMP())")->execute([$ticket,'BC-'.$suffix,$actor]);
    $bill=(int)$pdo->lastInsertId();
    $cmd=[PHP_BINARY,__FILE__,'--worker',(string)$bill,(string)$actor,$signal];
    $process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start independent payment worker');
    fclose($pipes[0]);
    $ready=false;
    for ($i=0;$i<80;$i++) {
        if (is_file($signal) && file_get_contents($signal)==='locked') { $ready=true; break; }
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('Worker did not signal its uncommitted payment');
    $pdo->beginTransaction();
    try {
        // Intentionally establish an older REPEATABLE READ snapshot before child commit.
        $count=(int)$pdo->query("SELECT COUNT(*) FROM repair_bill_payments WHERE bill_id=$bill")->fetchColumn();
        if ($count!==0) throw new RuntimeException('Parent snapshot was not created before commit');
        $rejected=false;
        try {
            edpRecordCashPayment($pdo,$actor,$bill,'60.00',str_repeat('2',64));
        } catch (DomainException $e) {
            $rejected=str_contains($e->getMessage(),'exceeds');
        }
        if (!$rejected) throw new RuntimeException('Concurrent overpayment was not rejected');
        $pdo->rollBack();
    } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit=proc_close($process); $process=null;
    if ($exit!==0) throw new RuntimeException('Concurrent worker failed: '.$stderr);
    $query=$pdo->prepare('SELECT COUNT(*),COALESCE(SUM(amount),0) FROM repair_bill_payments WHERE bill_id=?');
    $query->execute([$bill]);
    $amount=$query->fetch(PDO::FETCH_NUM);
    if ((int)$amount[0]!==1 || (string)$amount[1]!=='60.00') {
        throw new RuntimeException('Concurrent payment ledger mismatch');
    }
    echo "PASS: two independent connections serialize payments despite an old transaction snapshot\n";
} finally {
    if ($process!==null && is_resource($process)) {
        proc_terminate($process); foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe); proc_close($process);
    }
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Only our unique fictional fixture; cleanup is not a DROP/TRUNCATE of the academic DB.
    if ($bill) $pdo->prepare('DELETE FROM repair_bill_payments WHERE bill_id=?')->execute([$bill]);
    if ($actor) $pdo->prepare('DELETE FROM audit_events WHERE actor_user_id=?')->execute([$actor]);
    if ($bill) $pdo->prepare('DELETE FROM repair_bills WHERE bill_id=?')->execute([$bill]);
    if ($ticket) $pdo->prepare('DELETE FROM repair_tickets WHERE ticket_id=?')->execute([$ticket]);
    if ($actor) $pdo->prepare('DELETE FROM user_branch_assignments WHERE user_id=?')->execute([$actor]);
    if ($actor) $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$actor]);
    if ($customer) $pdo->prepare('DELETE FROM customers WHERE customer_id=?')->execute([$customer]);
    if (is_file($signal)) unlink($signal);
}
