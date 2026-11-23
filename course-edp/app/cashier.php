<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/actions.php';

/** Independent academic cash desk: no portal PIN or private asset access. */
function edpCashierStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_edp_cashier');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (isset($_SESSION['last_activity']) && time()-(int)$_SESSION['last_activity']>1800) {
        $_SESSION=[];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity']=time();
}
function edpCashierCsrf(): string
{
    if (!is_string($_SESSION['csrf']??null)) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function edpCashierCsrfValid(mixed $value): bool
{
    return is_string($value) && $value!=='' && is_string($_SESSION['csrf']??null)
        && hash_equals($_SESSION['csrf'],$value);
}
function edpCashierLogout(): void
{
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $p=session_get_cookie_params();
        setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$p['path'],
            'domain'=>$p['domain'],'secure'=>$p['secure'],
            'httponly'=>$p['httponly'],'samesite'=>'Lax']);
    }
    session_destroy();
}
function edpCashierCurrent(PDO $pdo): ?array
{
    $id=(int)($_SESSION['edp_cashier_id']??0);
    $hash=$_SESSION['edp_cashier_fingerprint']??null;
    if ($id<=0 || !is_string($hash)) return null;
    $q=$pdo->prepare('SELECT user_id,full_name,email,password_hash,role_code,is_active FROM users WHERE user_id=?');
    $q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['is_active']!==1 || !in_array($user['role_code'],['CASHIER','ADMIN','MANAGER'],true)
        || !hash_equals($hash,edpPreviewCredentialFingerprint($user))) {
        unset($_SESSION['edp_cashier_id'],$_SESSION['edp_cashier_fingerprint']);
        return null;
    }
    $q=$pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b
        ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $q->execute([$id]);
    if (!$q->fetchColumn()) {
        unset($_SESSION['edp_cashier_id'],$_SESSION['edp_cashier_fingerprint']);
        return null;
    }
    unset($user['password_hash']);
    return $user;
}
function edpCashierLogin(PDO $pdo,string $email,string $password,string $ip): bool
{
    $email=strtolower(trim($email));
    if (!edpPreviewReserveAttempt($email,$ip)) {
        http_response_code(429);
        header('Retry-After: 900');
        throw new DomainException('Too many sign-in attempts. Try again later.');
    }
    $q=$pdo->prepare('SELECT user_id,full_name,email,password_hash,role_code,is_active FROM users WHERE email=? LIMIT 1');
    $q->execute([$email]);$user=$q->fetch(PDO::FETCH_ASSOC);
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password,$user['password_hash']??$dummy) || !$user ||
        (int)$user['is_active']!==1 || !in_array($user['role_code'],['CASHIER','ADMIN','MANAGER'],true)) return false;
    $q=$pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b
        ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $q->execute([(int)$user['user_id']]);if (!$q->fetchColumn()) return false;
    session_regenerate_id(true);
    $_SESSION=['edp_cashier_id'=>(int)$user['user_id'],
        'edp_cashier_fingerprint'=>edpPreviewCredentialFingerprint($user),
        'csrf'=>bin2hex(random_bytes(32)),'last_activity'=>time()];
    return true;
}

/** Exact internal Ticket Code only, never the five-character customer portal code. */
function edpCashierFindTicket(PDO $pdo,int $actorId,string $ticketCode): ?int
{
    $ticketCode=strtoupper(trim($ticketCode));
    if (!preg_match('/^RT-[0-9A-F]{12}$/D',$ticketCode)) {
        throw new DomainException('Enter the internal RT- Ticket Code from the intake receipt.');
    }
    $q=$pdo->prepare("SELECT t.ticket_id FROM repair_tickets t
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.branch_id=t.branch_id AND a.user_id=?
        JOIN users u ON u.user_id=a.user_id AND u.is_active=1
           AND u.role_code IN ('CASHIER','MANAGER','ADMIN')
        WHERE t.ticket_code=? AND t.ticket_state IN ('OPEN','CLOSED','CANCELLED') LIMIT 1");
    $q->execute([$actorId,$ticketCode]);
    $id=$q->fetchColumn();return $id===false?null:(int)$id;
}

/** An allowlisted cash-desk view scoped to the current staff member's branch. */
function edpCashierTicket(PDO $pdo,int $actorId,int $ticketId): ?array
{
    if ($ticketId<1) return null;
    $q=$pdo->prepare("SELECT t.ticket_id,t.ticket_code,t.ticket_state,t.branch_id,
        b.branch_name,c.full_name AS customer_name,RIGHT(c.phone,4) AS phone_suffix,t.closed_at
        FROM repair_tickets t JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN customers c ON c.customer_id=t.customer_id
        JOIN user_branch_assignments a ON a.branch_id=t.branch_id AND a.user_id=?
        JOIN users u ON u.user_id=a.user_id AND u.is_active=1
            AND u.role_code IN ('CASHIER','MANAGER','ADMIN')
        WHERE t.ticket_id=? AND t.ticket_state IN ('OPEN','CLOSED','CANCELLED') LIMIT 1");
    $q->execute([$actorId,$ticketId]);$ticket=$q->fetch(PDO::FETCH_ASSOC);
    if (!$ticket)return null;
    $q=$pdo->prepare("SELECT d.ticket_device_id,d.item_code,d.status_code,d.custody_state,
        d.return_handover_at,dt.label AS device_type,
        (SELECT q.quote_status FROM repair_quotes q WHERE q.ticket_device_id=d.ticket_device_id
         ORDER BY q.version_no DESC LIMIT 1) AS quote_status,
        (SELECT q.total_amount FROM repair_quotes q WHERE q.ticket_device_id=d.ticket_device_id
         ORDER BY q.version_no DESC LIMIT 1) AS quote_total
        FROM ticket_devices d LEFT JOIN device_types dt ON dt.device_type_code=d.device_type_code
        WHERE d.ticket_id=? ORDER BY d.sequence_no");
    $q->execute([$ticketId]);$ticket['devices']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT bill_id,bill_number,bill_status,total_amount,issued_at,version_no
        FROM repair_bills WHERE ticket_id=? ORDER BY version_no DESC LIMIT 1');
    $q->execute([$ticketId]);$ticket['bill']=$q->fetch(PDO::FETCH_ASSOC)?:null;
    $ticket['bill_items']=[];$ticket['payments']=[];$ticket['paid_cents']=0;$ticket['due_cents']=0;
    if ($ticket['bill']) {
        $billId=(int)$ticket['bill']['bill_id'];
        $q=$pdo->prepare('SELECT i.description,i.amount,d.item_code FROM repair_bill_items i
            JOIN ticket_devices d ON d.ticket_device_id=i.ticket_device_id
            WHERE i.bill_id=? ORDER BY i.bill_item_id');
        $q->execute([$billId]);$ticket['bill_items']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT payment_id,amount,received_at,payment_method FROM repair_bill_payments
            WHERE bill_id=? ORDER BY payment_id');
        $q->execute([$billId]);$ticket['payments']=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ticket['payments'] as $p) $ticket['paid_cents']+=edpCents((string)$p['amount']);
        $ticket['due_cents']=max(0,edpCents((string)$ticket['bill']['total_amount'])-$ticket['paid_cents']);
    }
    return $ticket;
}

function edpCashierMoney(int $cents): string
{
    return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}

/** Issue exactly one bill for all ready devices, snapshotting each latest APPROVED quote.
 * The parent ticket is locked first, matching payment and pickup lock order.
 */
function edpCashierIssueBill(PDO $pdo,int $actorId,int $ticketId): int
{
    return edpInTransaction($pdo,function()use($pdo,$actorId,$ticketId):int {
        $q=$pdo->prepare('SELECT ticket_id,branch_id,ticket_state FROM repair_tickets
            WHERE ticket_id=? FOR UPDATE');
        $q->execute([$ticketId]);$t=$q->fetch(PDO::FETCH_ASSOC);
        if (!$t || $t['ticket_state']!=='OPEN')throw new DomainException('Only an open ticket can be billed.');
        edpActiveBranchStaff($pdo,$actorId,(int)$t['branch_id'],['CASHIER','ADMIN','MANAGER']);
        $q=$pdo->prepare('SELECT bill_id,version_no,bill_status FROM repair_bills
            WHERE ticket_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$ticketId]);$last=$q->fetch(PDO::FETCH_ASSOC);
        if ($last && $last['bill_status']==='ISSUED')return (int)$last['bill_id'];
        if ($last && $last['bill_status']==='DRAFT') {
            throw new DomainException('An existing draft bill must be resolved before issuing a new bill.');
        }
        $q=$pdo->prepare("SELECT bill_id FROM repair_bills WHERE ticket_id=? AND bill_status='ISSUED' LIMIT 1 FOR UPDATE");
        $q->execute([$ticketId]);if ($q->fetchColumn())
            throw new DomainException('An older issued bill still exists. Reconcile it before billing.');
        $q=$pdo->prepare('SELECT ticket_device_id,item_code,status_code,custody_state FROM ticket_devices
            WHERE ticket_id=? ORDER BY ticket_device_id FOR UPDATE');
        $q->execute([$ticketId]);$devices=$q->fetchAll(PDO::FETCH_ASSOC);
        if (!$devices)throw new DomainException('A repair ticket must contain devices.');
        $quote=$pdo->prepare('SELECT quote_status,total_amount,sent_at FROM repair_quotes
            WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $items=[];$total=0;
        foreach ($devices as $device) {
            if ($device['status_code']!=='READY_FOR_PICKUP'||$device['custody_state']!=='AT_LOCATION')
                throw new DomainException('Every device must be ready and in branch custody before billing.');
            $quote->execute([(int)$device['ticket_device_id']]);$latest=$quote->fetch(PDO::FETCH_ASSOC);
            if (!$latest||$latest['quote_status']!=='APPROVED'||$latest['sent_at']===null)
                throw new DomainException('Every device needs a current sent, approved quotation.');
            $amount=edpCents((string)$latest['total_amount']);$total+=$amount;
            if ($total>999999999999)throw new DomainException('The bill total is outside the supported amount.');
            $items[]=[(int)$device['ticket_device_id'],'Repair service · '.$device['item_code'],edpCashierMoney($amount)];
        }
        $billNo='B-'.strtoupper(bin2hex(random_bytes(7)));
        $version=$last?(int)$last['version_no']+1:1;
        $q=$pdo->prepare("INSERT INTO repair_bills
            (ticket_id,version_no,bill_number,bill_status,subtotal,total_amount,created_by_user_id,issued_at)
            VALUES (?,?,?,'ISSUED',?,?,?,UTC_TIMESTAMP())");
        $q->execute([$ticketId,$version,$billNo,edpCashierMoney($total),edpCashierMoney($total),$actorId]);
        $billId=(int)$pdo->lastInsertId();
        $q=$pdo->prepare('INSERT INTO repair_bill_items
            (bill_id,ticket_id,ticket_device_id,description,amount) VALUES (?,?,?,?,?)');
        foreach ($items as [$deviceId,$description,$amount])$q->execute([$billId,$ticketId,$deviceId,$description,$amount]);
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_bill',?,'BILL_ISSUED')")->execute([$actorId,$billId]);
        return $billId;
    });
}

/** A receipt is accessible only to authorized branch staff, never the customer portal. */
function edpCashierReceipt(PDO $pdo,int $actorId,int $paymentId): ?array
{
    if ($paymentId<1)return null;
    $q=$pdo->prepare("SELECT p.payment_id,p.amount,p.received_at,p.payment_method,
        b.bill_id,b.bill_number,b.total_amount,t.ticket_id,t.ticket_code,t.branch_id,
        br.branch_name,u.full_name AS cashier_name
        FROM repair_bill_payments p JOIN repair_bills b ON b.bill_id=p.bill_id
        JOIN repair_tickets t ON t.ticket_id=b.ticket_id
        JOIN branches br ON br.branch_id=t.branch_id AND br.is_active=1
        JOIN users u ON u.user_id=p.received_by_user_id
        JOIN user_branch_assignments a ON a.branch_id=t.branch_id AND a.user_id=?
        JOIN users actor ON actor.user_id=a.user_id AND actor.is_active=1
          AND actor.role_code IN ('CASHIER','ADMIN','MANAGER')
        WHERE p.payment_id=? LIMIT 1");
    $q->execute([$actorId,$paymentId]);$receipt=$q->fetch(PDO::FETCH_ASSOC);
    if (!$receipt)return null;
    $q=$pdo->prepare('SELECT amount FROM repair_bill_payments WHERE bill_id=?');
    $q->execute([(int)$receipt['bill_id']]);$total=0;
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $amount)$total+=edpCents((string)$amount);
    $receipt['paid_cents']=$total;
    $receipt['due_cents']=max(0,edpCents((string)$receipt['total_amount'])-$total);
    return $receipt;
}

/** Phone confirmation is checked under lock; this is staff-attested counter pickup,
 * not proof of a cryptographic customer identity. No COD, courier or proxy handover.
 */
function edpCashierConfirmPickup(PDO $pdo,int $actorId,int $ticketId,string $confirmedPhone): void
{
    if (!preg_match('/^[0-9]{4,15}$/D',$confirmedPhone))
        throw new DomainException('Confirm the full recorded contact number at the counter.');
    edpInTransaction($pdo,function()use($pdo,$actorId,$ticketId,$confirmedPhone):void {
        $q=$pdo->prepare('SELECT t.branch_id,t.customer_id,t.ticket_state FROM repair_tickets t
            WHERE t.ticket_id=? FOR UPDATE');
        $q->execute([$ticketId]);$ticket=$q->fetch(PDO::FETCH_ASSOC);
        if (!$ticket||$ticket['ticket_state']!=='OPEN')
            throw new DomainException('Only an open ticket can be handed over.');
        edpActiveBranchStaff($pdo,$actorId,(int)$ticket['branch_id'],['CASHIER','ADMIN','MANAGER']);
        $q=$pdo->prepare('SELECT phone FROM customers WHERE customer_id=? FOR UPDATE');
        $q->execute([(int)$ticket['customer_id']]);$phone=$q->fetchColumn();
        if (!is_string($phone)||!hash_equals($phone,$confirmedPhone))
            throw new DomainException('Recorded customer contact does not match; do not release devices.');
        edpConfirmPickup($pdo,$actorId,$ticketId);
    });
}
