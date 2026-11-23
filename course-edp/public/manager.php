<?php
declare(strict_types=1);

/** Isolated localhost academic manager console; no legacy database or customer session. */
require_once __DIR__ . '/../app/manager.php';
edpPreviewHeaders();
edpManagerStartSession();
function mgrH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mgrRedirect(string $route): never
{
    header('Location: '.$route, true, 303);
    exit;
}
function mgrPositiveId(mixed $value): int
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D',$value)) {
        throw new DomainException('Choose a valid repair or branch.');
    }
    return (int)$value;
}
$pdo=null;$staff=null;$error='';$flash='';$device=null;$queue=null;
$page=is_string($_GET['page']??null)?$_GET['page']:'queue';
$branches=[];$technicians=[];
$filter=is_string($_GET['filter']??null)?$_GET['filter']:'unassigned';
$pageNo=is_string($_GET['p']??null)?(int)$_GET['p']:1;
$branchId=0;
try {
    $pdo=edpPreviewDb();
    $staff=edpManagerCurrent($pdo);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!edpManagerCsrfValid($_POST['csrf_token']??null)) {
            http_response_code(403);
            throw new DomainException('Your form expired. Reload this page.');
        }
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        if ($action==='login') {
            if ($staff) mgrRedirect('manager.php');
            $email=is_string($_POST['email']??null)?$_POST['email']:'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if (strlen($email)>190 || strlen($password)>1024 ||
                !edpManagerLogin($pdo,$email,$password,$_SERVER['REMOTE_ADDR']??'unknown')) {
                http_response_code(401);
                throw new DomainException('Invalid manager credentials.');
            }
            mgrRedirect('manager.php');
        }
        if (!$staff) mgrRedirect('manager.php?page=login');
        if ($action==='logout') {
            edpManagerLogout();
            mgrRedirect('manager.php?page=login');
        }
        if (!in_array($action,['assign','send_quote','decision','expire_quote'],true)) {
            http_response_code(400);
            throw new DomainException('Unknown manager action.');
        }
        $id=mgrPositiveId($_POST['device_id']??null);
        $device=edpManagerDevice($pdo,(int)$staff['user_id'],$id);
        if (!$device) {
            http_response_code(404);
            throw new DomainException('Repair is outside your managed branches.');
        }
        if ($action==='assign') {
            edpManagerAssign($pdo,(int)$staff['user_id'],$id,mgrPositiveId($_POST['technician_id']??null));
            $_SESSION['manager_flash']='Technician assignment saved.';
        } elseif ($action==='send_quote') {
            edpManagerSendQuote($pdo,(int)$staff['user_id'],$id);
            $_SESSION['manager_flash']='Current quotation sent for customer review.';
        } elseif ($action==='decision') {
            edpManagerQuoteDecision($pdo,(int)$staff['user_id'],$id,
                is_string($_POST['decision']??null)?$_POST['decision']:'',
                is_string($_POST['channel']??null)?$_POST['channel']:'',
                is_string($_POST['reference']??null)?$_POST['reference']:'',
                ($_POST['confirmed']??null)==='yes');
            $_SESSION['manager_flash']='Offline customer decision recorded and audited.';
        } else {
            edpManagerExpireQuote($pdo,(int)$staff['user_id'],$id);
            $_SESSION['manager_flash']='Expired quotation recorded; repair returned to diagnosis.';
        }
        mgrRedirect('manager.php?page=device&id='.$id);
    }
    if (!$staff && $page!=='login') mgrRedirect('manager.php?page=login');
    if ($staff && $page==='login') mgrRedirect('manager.php');
    if ($staff) {
        $flash=is_string($_SESSION['manager_flash']??null)?$_SESSION['manager_flash']:'';
        unset($_SESSION['manager_flash']);
        $branches=edpManagerBranches($pdo,(int)$staff['user_id']);
        if ($page==='device') {
            $id=mgrPositiveId($_GET['id']??null);
            $device=edpManagerDevice($pdo,(int)$staff['user_id'],$id);
            if (!$device) {
                http_response_code(404);
                throw new DomainException('Repair unavailable in your branches.');
            }
            $branchId=(int)$device['branch_id'];
            $technicians=edpManagerTechnicians($pdo,(int)$staff['user_id'],$branchId);
        } elseif ($page==='queue') {
            $branchId=isset($_GET['branch'])?mgrPositiveId($_GET['branch']): (int)($branches[0]['branch_id']??0);
            $queue=edpManagerQueue($pdo,(int)$staff['user_id'],$branchId,$filter,$pageNo);
        } else {
            http_response_code(404);
            throw new DomainException('Manager page not found.');
        }
    }
} catch (DomainException $e) {
    $error=$e->getMessage();
    if (http_response_code()<400) http_response_code(422);
    if ($staff && $pdo instanceof PDO && ($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        $id=is_string($_POST['device_id']??null)&&ctype_digit($_POST['device_id'])?(int)$_POST['device_id']:0;
        $device=$id>0?edpManagerDevice($pdo,(int)$staff['user_id'],$id):null;
        if ($device) {
            $page='device';
            $technicians=edpManagerTechnicians($pdo,(int)$staff['user_id'],(int)$device['branch_id']);
        }
    }
} catch (Throwable $e) {
    error_log('RepairTrack EDP manager: '.get_class($e));
    http_response_code(503);
    $error='The academic manager console is temporarily unavailable.';
}
$csrf=edpManagerCsrf();$h='mgrH';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Console · RepairTrack Academic</title>
<link rel="stylesheet" href="assets/manager.css">
</head>
<body>
<header class="topbar"><a class="brand" href="manager.php">Repair<span>Track</span></a>
<div class="brand-meta">Manager Console <small>Academic · localhost only</small></div>
<?php if ($staff): ?><div class="account"><span><?= $h($staff['full_name']) ?></span>
<form method="post" action="manager.php"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><button class="ghost" type="submit">Sign out</button></form></div><?php endif; ?></header>
<main class="container">
<?php if ($error!==''): ?><p class="alert" role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if ($flash!==''): ?><p class="success" role="status"><?= $h($flash) ?></p><?php endif; ?>
<?php if (!$staff): ?>
<section class="panel login"><p class="eyebrow">Authorized supervisor access</p><h1>Manager sign in</h1>
<p>Only active managers or administrators assigned to a branch can open this academic console.</p>
<form method="post" action="manager.php?page=login" class="stack"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Email<input type="email" name="email" required maxlength="190" autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button type="submit" class="primary">Sign in</button></form>
<p class="muted"><a href="technician.php">Technician workspace</a> · <a href="track.php">Customer tracking</a></p></section>
<?php elseif ($page==='queue' && $queue): ?>
<section class="hero"><div><p class="eyebrow">Work assignments</p><h1>Manager queue</h1><p>Assign repairs and review technician quotations at your authorized branches.</p></div><span class="count"><?= (int)$queue['total'] ?> repairs</span></section>
<form class="branch-select" method="get" action="manager.php"><label>Active branch
<select name="branch">
<?php foreach ($branches as $branch): ?><option value="<?= (int)$branch['branch_id'] ?>" <?= (int)$branch['branch_id']===$branchId?'selected':'' ?>><?= $h($branch['branch_name']) ?></option><?php endforeach; ?>
</select></label><button class="ghost" type="submit">View branch</button></form>
<nav class="tabs" aria-label="Manager queue filters">
<?php foreach (['unassigned'=>'Unassigned','active'=>'Assigned','approval'=>'Quotes to review'] as $slug=>$label): ?><a href="manager.php?branch=<?= $branchId ?>&amp;filter=<?= $h($slug) ?>" <?= $filter===$slug?'aria-current="page"':'' ?>><?= $label ?></a><?php endforeach; ?>
</nav>
<section class="panel" aria-label="Repair worklist">
<?php if (!$queue['rows']): ?><p class="muted">No repairs in this view.</p><?php else: ?>
<div class="table-scroll"><table><thead><tr><th>Ticket / device</th><th>Status</th><th>Technician</th><th>Quotation</th><th>Action</th></tr></thead><tbody>
<?php foreach ($queue['rows'] as $row): ?><tr><td><strong><?= $h($row['ticket_code']) ?></strong><small><?= $h($row['item_code']) ?></small></td>
<td><?= $h(str_replace('_',' ',$row['status_code'])) ?></td><td><?= $h($row['technician_name']??'Unassigned') ?></td><td><?= $h($row['quote_status']??'No draft') ?></td>
<td><a href="manager.php?page=device&amp;id=<?= (int)$row['ticket_device_id'] ?>">Review repair</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="pagination"><?php if ($pageNo>1): ?><a href="manager.php?branch=<?= $branchId ?>&amp;filter=<?= $h($filter) ?>&amp;p=<?= $pageNo-1 ?>">Previous</a><?php endif; ?>
<span>Page <?= $pageNo ?> of <?= (int)$queue['pages'] ?></span>
<?php if ($pageNo<(int)$queue['pages']): ?><a href="manager.php?branch=<?= $branchId ?>&amp;filter=<?= $h($filter) ?>&amp;p=<?= $pageNo+1 ?>">Next</a><?php endif; ?></div>
</section>
<?php elseif ($page==='device' && $device): ?>
<nav class="breadcrumb"><a href="manager.php?branch=<?= (int)$device['branch_id'] ?>">← Manager queue</a><span><?= $h($device['item_code']) ?></span></nav>
<section class="hero"><div><p class="eyebrow"><?= $h($device['ticket_code']) ?> · <?= $h($device['branch_name']) ?></p>
<h1>Repair supervision</h1><p><?= $h($device['reported_symptoms']) ?></p></div><span class="count"><?= $h(str_replace('_',' ',$device['status_code'])) ?></span></section>
<div class="layout">
<section class="panel"><h2>Assign technician</h2><p class="muted">Active repair capacity: five devices per technician. Reassignment uses the same locked service as the technician console.</p>
<p>Current: <strong><?= $h($device['technician_name']??'Unassigned') ?></strong></p>
<form class="stack" method="post" action="manager.php?page=device&amp;id=<?= (int)$device['ticket_device_id'] ?>">
<input type="hidden" name="action" value="assign"><input type="hidden" name="device_id" value="<?= (int)$device['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Available branch technician<select name="technician_id" required><option value="">Choose technician</option>
<?php foreach ($technicians as $tech): ?><option value="<?= (int)$tech['user_id'] ?>" <?= (int)$device['assigned_technician_id']===(int)$tech['user_id']?'selected':'' ?>><?= $h($tech['full_name']) ?> (<?= (int)$tech['active_count'] ?> active)</option><?php endforeach; ?></select></label>
<button class="primary" type="submit" <?= $technicians?'':'disabled' ?>>Save assignment</button></form></section>
<section class="panel"><h2>Technician assessment</h2><p class="eyebrow">Internal diagnosis</p><p><?= $device['diagnosis']?$h($device['diagnosis']):'Awaiting technician diagnosis.' ?></p>
<p class="muted">Diagnosis stays staff-only. Quotation prices appear to customers only after sending.</p></section>
<section class="panel"><h2>Quotation review</h2>
<?php if (!is_array($device['quote'])): ?><p class="muted">No technician quotation draft yet.</p>
<?php else: ?>
<?php $quote=$device['quote']; ?>
<div class="quote-summary"><span>Version <?= (int)$quote['version_no'] ?> · <?= $h($quote['quote_status']) ?></span><strong>₱<?= $h($quote['total_amount']) ?></strong></div>
<dl><dt>Labor</dt><dd>₱<?= $h($quote['labor_amount']) ?></dd><dt>Parts</dt><dd>₱<?= $h($quote['parts_amount']) ?></dd><dt>Other</dt><dd>₱<?= $h($quote['other_amount']) ?></dd></dl>
<?php if ($quote['sent_at']): ?><p class="muted">Sent: <?= $h($quote['sent_at']) ?> UTC · Expires: <?= $h($quote['expires_at']) ?> UTC</p><?php endif; ?>
<?php if ($quote['quote_status']==='DRAFT' && $device['status_code']==='DIAGNOSING'): ?>
<form method="post" action="manager.php?page=device&amp;id=<?= (int)$device['ticket_device_id'] ?>" class="stack">
<input type="hidden" name="action" value="send_quote"><input type="hidden" name="device_id" value="<?= (int)$device['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<button class="primary" type="submit">Send quotation for customer review</button></form>
<?php elseif ($quote['quote_status']==='AWAITING_CUSTOMER' && $device['status_code']==='AWAITING_CUSTOMER_APPROVAL'): ?>
<div class="subsection"><h3>Record offline customer decision</h3>
<p class="muted">This form logs staff attestation of a customer decision. It does <strong>not</strong> independently prove customer authorization.</p>
<form method="post" action="manager.php?page=device&amp;id=<?= (int)$device['ticket_device_id'] ?>" class="stack">
<input type="hidden" name="action" value="decision"><input type="hidden" name="device_id" value="<?= (int)$device['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Customer's decision<select name="decision" required><option value="">Choose decision</option><option value="APPROVED">Approved the quotation</option><option value="DECLINED">Declined the quotation</option></select></label>
<label>How was decision confirmed?<select name="channel" required><option value="">Choose method</option><option value="IN_PERSON">In person</option><option value="PHONE_CALL">Telephone call</option><option value="WRITTEN">Written confirmation</option></select></label>
<label>Confirmation reference<input type="text" name="reference" required minlength="8" maxlength="160" placeholder="Receipt note, call record ID or consent reference"></label>
<label class="confirm"><input type="checkbox" name="confirmed" value="yes" required> I personally confirmed this response and will retain its supporting record.</label>
<button type="submit" class="primary">Record customer decision</button></form>
<form method="post" action="manager.php?page=device&amp;id=<?= (int)$device['ticket_device_id'] ?>" class="stack separated">
<input type="hidden" name="action" value="expire_quote"><input type="hidden" name="device_id" value="<?= (int)$device['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<button class="ghost" type="submit">Mark expired (after deadline)</button></form></div>
<?php endif; ?>
<?php endif; ?>
</section></div>
<?php else: ?><section class="panel"><h1>Manager view unavailable</h1><p>Open an authorized branch queue.</p></section><?php endif; ?>
</main><footer>RepairTrack · Isolated academic manager preview · Not a production customer approval system</footer>
</body></html>
