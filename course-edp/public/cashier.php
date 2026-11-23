<?php
declare(strict_types=1);

/** Isolated local cash desk. Never load the legacy application's database. */
require_once __DIR__ . '/../app/cashier.php';
edpPreviewHeaders();
edpCashierStartSession();

function cashH(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function cashId(mixed $s): int
{
    if (!is_string($s)||!preg_match('/^[1-9][0-9]{0,17}$/D',$s))
        throw new DomainException('Choose a valid ticket.');
    return (int)$s;
}
function cashRedirect(string $to): never
{
    header('Location: '.$to,true,303);exit;
}

$page=is_string($_GET['page']??null)?$_GET['page']:'desk';
$staff=null;$ticket=null;$receipt=null;$pdo=null;$error='';$flash='';
try {
    $pdo=edpPreviewDb();
    $staff=edpCashierCurrent($pdo);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!edpCashierCsrfValid($_POST['csrf_token']??null)) {
            http_response_code(403);
            throw new DomainException('The form expired. Reload and try again.');
        }
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        if ($action==='login') {
            if ($staff)cashRedirect('cashier.php');
            $email=is_string($_POST['email']??null)?$_POST['email']:'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if (strlen($email)>190||strlen($password)>1024||
                !edpCashierLogin($pdo,$email,$password,$_SERVER['REMOTE_ADDR']??'unknown')) {
                http_response_code(401);throw new DomainException('Invalid cashier credentials.');
            }
            cashRedirect('cashier.php');
        }
        if (!$staff)cashRedirect('cashier.php?page=login');
        if ($action==='logout'){
            edpCashierLogout();cashRedirect('cashier.php?page=login');
        }
        $id=cashId($_POST['ticket_id']??null);
        $ticket=edpCashierTicket($pdo,(int)$staff['user_id'],$id);
        if (!$ticket){http_response_code(404);throw new DomainException('Ticket not found in your assigned branches.');}
        if ($action==='issue_bill') {
            edpCashierIssueBill($pdo,(int)$staff['user_id'],$id);
            $_SESSION['cashier_flash']='Itemized bill issued for the ready repair devices.';
            cashRedirect('cashier.php?page=ticket&id='.$id);
        }
        if ($action==='payment') {
            $billId=cashId($_POST['bill_id']??null);
            if (!is_array($ticket['bill'])||(int)$ticket['bill']['bill_id']!==$billId)
                throw new DomainException('Select this ticket’s current issued bill.');
            $amount=$_POST['amount']??null;
            $key=$_POST['request_key']??null;
            if (!is_string($amount)||!is_string($key))throw new DomainException('Invalid cash payment details.');
            $paymentId=edpRecordCashPayment($pdo,(int)$staff['user_id'],$billId,$amount,$key);
            cashRedirect('cashier.php?page=receipt&id='.$paymentId);
        }
        if ($action==='pickup') {
            if (($_POST['attested']??null)!=='yes')
                throw new DomainException('Confirm the customer received all repaired devices at the counter.');
            $phone=$_POST['confirmed_phone']??null;
            if (!is_string($phone))throw new DomainException('Confirm the recorded customer contact number.');
            edpCashierConfirmPickup($pdo,(int)$staff['user_id'],$id,trim($phone));
            $_SESSION['cashier_flash']='Counter pickup confirmed; ticket and device history closed.';
            cashRedirect('cashier.php?page=ticket&id='.$id);
        }
        http_response_code(400);throw new DomainException('Unknown cash desk action.');
    }
    if (!$staff && $page!=='login')cashRedirect('cashier.php?page=login');
    if ($staff && $page==='login')cashRedirect('cashier.php');
    if ($staff) {
        $flash=is_string($_SESSION['cashier_flash']??null)?$_SESSION['cashier_flash']:'';
        unset($_SESSION['cashier_flash']);
        if ($page==='desk') {
            $searched=$_GET['ticket_code']??null;
            if (is_string($searched)&&trim($searched)!=='') {
                $id=edpCashierFindTicket($pdo,(int)$staff['user_id'],$searched);
                if (!$id){http_response_code(404);throw new DomainException('Ticket not found in your assigned branches.');}
                cashRedirect('cashier.php?page=ticket&id='.$id);
            }
        } elseif ($page==='ticket') {
            $id=cashId($_GET['id']??null);
            $ticket=edpCashierTicket($pdo,(int)$staff['user_id'],$id);
            if (!$ticket){http_response_code(404);throw new DomainException('Ticket not found in your assigned branches.');}
        } elseif ($page==='receipt') {
            $id=cashId($_GET['id']??null);
            $receipt=edpCashierReceipt($pdo,(int)$staff['user_id'],$id);
            if (!$receipt){http_response_code(404);throw new DomainException('Payment receipt is not available in your branch.');}
        } else {
            http_response_code(404);throw new DomainException('Cash desk page not found.');
        }
    }
} catch (DomainException $e) {
    $error=$e->getMessage();
    if(http_response_code()<400)http_response_code(422);
    if ($pdo instanceof PDO && $staff && ($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        $id=is_string($_POST['ticket_id']??null)&&ctype_digit($_POST['ticket_id'])?(int)$_POST['ticket_id']:0;
        if ($id>0){$ticket=edpCashierTicket($pdo,(int)$staff['user_id'],$id);if($ticket)$page='ticket';}
    }
} catch (Throwable $e) {
    error_log('EDP academic cashier unavailable: '.get_class($e));
    http_response_code(503);
    $error='Cash desk is temporarily unavailable. Please try again.';
}
$csrf=edpCashierCsrf();$requestKey=bin2hex(random_bytes(32));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cashier Console · RepairTrack Academic</title><link rel="stylesheet" href="assets/cashier.css"></head>
<body><header class="topbar"><a class="brand" href="cashier.php">Repair<span>Track</span></a>
<div class="tag">Cash Desk <small>EDP classroom · localhost only</small></div>
<?php if($staff): ?><div class="account"><span><?= cashH($staff['full_name']) ?> · <?= cashH($staff['role_code']) ?></span>
<form method="post" action="cashier.php"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= cashH($csrf) ?>"><button class="ghost" type="submit">Sign out</button></form></div><?php endif; ?>
</header><main class="container">
<?php if($error!==''): ?><p class="alert" role="alert"><?= cashH($error) ?></p><?php endif; ?>
<?php if($flash!==''): ?><p class="success" role="status"><?= cashH($flash) ?></p><?php endif; ?>
<?php if(!$staff): ?>
<section class="panel login"><p class="eyebrow">Staff-only access</p><h1>Cashier sign in</h1><p>Use a fictional cashier, manager or administrator account authorized for this branch.</p>
<form method="post" action="cashier.php?page=login" class="stack"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= cashH($csrf) ?>">
<label>Email<input type="email" name="email" maxlength="190" autocomplete="username" required></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit" class="primary">Sign in</button></form>
<p class="muted">The customer portal uses a separate access code and session. <a href="track.php">Customer tracking</a></p></section>
<?php elseif($page==='desk'): ?>
<section class="hero"><p class="eyebrow">Counter cash and collection</p><h1>Cashier desk</h1><p>Look up a repair by its internal Ticket Code. The five-character customer portal code is not accepted.</p></section>
<section class="panel search"><h2>Find repair ticket</h2><form method="get" action="cashier.php" class="stack">
<input type="hidden" name="page" value="desk"><label>Internal Ticket Code<input name="ticket_code" type="text" pattern="RT-[0-9A-Fa-f]{12}" maxlength="15" minlength="15" placeholder="RT-ABC012DEF345" autocapitalize="characters" autocomplete="off" required></label>
<button class="primary" type="submit">Find ticket</button></form><p class="muted">Exact Ticket Code required. Search is restricted to your active branches.</p></section>
<?php elseif($page==='ticket' && $ticket): ?>
<nav class="breadcrumb"><a href="cashier.php">← Cash desk</a><span><?= cashH($ticket['ticket_code']) ?></span></nav>
<section class="hero"><div><p class="eyebrow"><?= cashH($ticket['branch_name']) ?></p><h1><?= cashH($ticket['ticket_code']) ?></h1><p><?= cashH($ticket['customer_name']) ?> · recorded phone ending <?= cashH($ticket['phone_suffix']) ?></p></div><span class="status"><?= cashH($ticket['ticket_state']) ?></span></section>
<div class="layout"><section class="panel"><h2>Repair devices</h2><p class="muted">Every device must be ready and its current quotation approved before the bill can be issued.</p>
<div class="table-wrap"><table><thead><tr><th>Item code</th><th>Repair status</th><th>Quotation</th></tr></thead><tbody>
<?php foreach($ticket['devices'] as $device): ?><tr><td><strong><?= cashH($device['item_code']) ?></strong><small><?= cashH($device['device_type']) ?></small></td><td><?= cashH(str_replace('_',' ',$device['status_code'])) ?></td><td><?= cashH($device['quote_status']??'Not sent') ?><?= $device['quote_total']!==null?' · ₱'.cashH($device['quote_total']):'' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if($ticket['ticket_state']==='OPEN' && !$ticket['bill']): ?><form method="post" action="cashier.php?page=ticket&amp;id=<?= (int)$ticket['ticket_id'] ?>" class="stack">
<input type="hidden" name="csrf_token" value="<?= cashH($csrf) ?>"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['ticket_id'] ?>"><input type="hidden" name="action" value="issue_bill">
<button type="submit" class="primary">Issue itemized bill</button></form><?php endif; ?></section>
<section class="panel"><h2>Current bill</h2>
<?php if(!$ticket['bill']): ?><p class="muted">No bill has been issued yet. Diagnosis and customer-approved repair must finish first.</p>
<?php else: ?>
<p class="muted"><?= cashH($ticket['bill']['bill_number']??'Draft') ?> · <?= cashH($ticket['bill']['bill_status']) ?></p>
<?php foreach($ticket['bill_items'] as $item): ?><div class="line"><span><?= cashH($item['item_code']) ?></span><strong>₱<?= cashH($item['amount']) ?></strong></div><?php endforeach; ?>
<div class="line total"><span>Bill total</span><strong>₱<?= cashH($ticket['bill']['total_amount']) ?></strong></div>
<div class="line"><span>Paid</span><strong>₱<?= cashH(edpCashierMoney($ticket['paid_cents'])) ?></strong></div>
<div class="line amount-due"><span>Remaining balance</span><strong>₱<?= cashH(edpCashierMoney($ticket['due_cents'])) ?></strong></div>
<?php if($ticket['bill']['bill_status']==='ISSUED'&&$ticket['ticket_state']==='OPEN'&&$ticket['due_cents']>0): ?>
<form method="post" action="cashier.php?page=ticket&amp;id=<?= (int)$ticket['ticket_id'] ?>" class="stack payment-form">
<input type="hidden" name="action" value="payment"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['ticket_id'] ?>"><input type="hidden" name="bill_id" value="<?= (int)$ticket['bill']['bill_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= cashH($csrf) ?>"><input type="hidden" name="request_key" value="<?= cashH($requestKey) ?>">
<label>Counter cash received (₱)<input type="text" name="amount" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="<?= cashH(edpCashierMoney($ticket['due_cents'])) ?>" required></label>
<button class="primary" type="submit">Record received cash</button><p class="muted">No digital payments or unverified COD. Partial counter cash payments are permitted.</p></form><?php endif; ?>
<?php endif; ?></section>
<section class="panel"><h2>Payment history</h2>
<?php if(!$ticket['payments']): ?><p class="muted">No counter payments recorded.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Amount</th><th>Recorded (UTC)</th></tr></thead><tbody>
<?php foreach($ticket['payments'] as $payment): ?><tr><td><a href="cashier.php?page=receipt&amp;id=<?= (int)$payment['payment_id'] ?>">#<?= (int)$payment['payment_id'] ?></a></td><td>₱<?= cashH($payment['amount']) ?></td><td><?= cashH($payment['received_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></section>
<section class="panel"><h2>Counter pickup</h2>
<?php if($ticket['ticket_state']==='CLOSED'): ?><p class="success">Completed. Device custody was recorded as returned to the customer.</p>
<?php elseif($ticket['ticket_state']!=='OPEN'): ?><p class="muted">Cancelled or unavailable for pickup.</p>
<?php elseif(!$ticket['bill']||$ticket['bill']['bill_status']!=='ISSUED'||$ticket['due_cents']>0): ?><p class="muted">Pickup is blocked until the current issued bill is settled.</p>
<?php else: ?><p class="muted">Confirm the recorded full contact number and actual handover of every device. The phone is never shown or copied into a receipt.</p>
<form method="post" action="cashier.php?page=ticket&amp;id=<?= (int)$ticket['ticket_id'] ?>" class="stack">
<input type="hidden" name="action" value="pickup"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['ticket_id'] ?>"><input type="hidden" name="csrf_token" value="<?= cashH($csrf) ?>">
<label>Confirm complete recorded phone<input type="tel" name="confirmed_phone" minlength="4" maxlength="15" inputmode="numeric" autocomplete="off" required></label>
<label class="choice"><input type="checkbox" name="attested" value="yes" required> I confirm the customer received all devices at the counter.</label>
<button class="primary" type="submit">Complete counter pickup</button></form><?php endif; ?></section></div>
<?php elseif($page==='receipt' && $receipt): ?>
<nav class="breadcrumb no-print"><a href="cashier.php?page=ticket&amp;id=<?= (int)$receipt['ticket_id'] ?>">← Ticket details</a><span>Payment receipt</span></nav>
<section class="panel receipt"><p class="eyebrow">Counter cash · Payment acknowledgement</p><h1>Cash receipt #<?= (int)$receipt['payment_id'] ?></h1>
<p><?= cashH($receipt['branch_name']) ?></p><dl><dt>Internal Ticket Code</dt><dd><?= cashH($receipt['ticket_code']) ?></dd>
<dt>Bill number</dt><dd><?= cashH($receipt['bill_number']) ?></dd><dt>Received (UTC)</dt><dd><?= cashH($receipt['received_at']) ?></dd>
<dt>Recorded by</dt><dd><?= cashH($receipt['cashier_name']) ?></dd><dt>Payment method</dt><dd>Counter cash</dd>
<dt>Cash received</dt><dd class="paid">₱<?= cashH($receipt['amount']) ?></dd><dt>Current remaining balance</dt><dd>₱<?= cashH(edpCashierMoney($receipt['due_cents'])) ?></dd></dl>
<p class="muted">Local classroom receipt. This acknowledges cash recorded, not device pickup or customer authorization.</p>
<button type="button" class="primary no-print" data-print-receipt>Print receipt</button></section>
<?php else: ?><section class="panel"><h1>Unavailable</h1><p>Unable to open the requested cash desk screen.</p></section><?php endif; ?>
</main><script src="assets/cashier.js" defer></script><footer class="no-print">Isolated EDP classroom application · Counter cash only · Not connected to the deployed website</footer></body></html>
