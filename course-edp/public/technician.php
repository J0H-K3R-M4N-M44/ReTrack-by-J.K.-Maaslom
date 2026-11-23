<?php
declare(strict_types=1);

/** Local academic technician console; never use the legacy database. */
require_once __DIR__ . '/../app/technician.php';
edpPreviewHeaders();
edpTechStartSession();

function edpTechEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function edpTechRedirect(string $url): never
{
    header('Location: '.$url,true,303);
    exit;
}
function edpTechDeviceId(mixed $value): int
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D',$value))
        throw new DomainException('Choose a valid repair.');
    return (int)$value;
}

$pdo=null;
$staff=null;
$error='';
$flash='';
$page=is_string($_GET['page']??null)?$_GET['page']:'queue';
$view=is_string($_GET['view']??null)?$_GET['view']:'active';
$pageNo=(int)($_GET['p']??1);
$queue=null;
$detail=null;
$next=[];
try {
    $pdo=edpPreviewDb();
    $staff=edpTechStaff($pdo);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!edpTechCsrfValid($_POST['csrf_token']??null)) {
            http_response_code(403);
            throw new DomainException('Your form expired. Reload the page and try again.');
        }
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        if ($action==='login') {
            if ($staff) edpTechRedirect('technician.php');
            $email=is_string($_POST['email']??null)?$_POST['email']:'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if (strlen($email)>190 || strlen($password)>1024 ||
                !edpTechLogin($pdo,$email,$password,$_SERVER['REMOTE_ADDR']??'unknown')) {
                http_response_code(401);
                throw new DomainException('Invalid technician credentials.');
            }
            edpTechRedirect('technician.php');
        }
        if (!$staff) edpTechRedirect('technician.php?page=login');
        if ($action==='logout') {
            edpTechLogout();
            edpTechRedirect('technician.php?page=login');
        }
        if (!in_array($action,['status','diagnosis','quote'],true)) {
            http_response_code(400);
            throw new DomainException('Unknown technician action.');
        }
        $id=edpTechDeviceId($_POST['device_id']??null);
        $device=edpTechDevice($pdo,(int)$staff['user_id'],$id);
        if (!$device) {
            http_response_code(404);
            throw new DomainException('This repair is unavailable in your queue.');
        }
        if ($action==='status') {
            $target=is_string($_POST['next_status']??null)?$_POST['next_status']:'';
            $valid=array_column(edpTechNextStatuses($pdo,$device),'status_code');
            if (!in_array($target,$valid,true)) throw new DomainException('This status change is not available.');
            $summary=is_string($_POST['public_summary']??null)?$_POST['public_summary']:'';
            edpChangeRepairStatus($pdo,(int)$staff['user_id'],$id,$target,$summary);
            $_SESSION['tech_flash']='Repair status and customer timeline updated.';
        } elseif ($action==='diagnosis') {
            edpTechSaveDiagnosis($pdo,(int)$staff['user_id'],$id,
                is_string($_POST['diagnosis']??null)?$_POST['diagnosis']:'');
            $_SESSION['tech_flash']='Private diagnosis saved.';
        } else {
            edpTechSaveQuoteDraft($pdo,(int)$staff['user_id'],$id,
                is_string($_POST['labor']??null)?$_POST['labor']:'',
                is_string($_POST['parts']??null)?$_POST['parts']:'',
                is_string($_POST['other']??null)?$_POST['other']:'');
            $_SESSION['tech_flash']='Quotation draft saved for staff review; not sent to the customer.';
        }
        edpTechRedirect('technician.php?page=device&id='.$id);
    }
    if (!$staff && $page!=='login') edpTechRedirect('technician.php?page=login');
    if ($staff && $page==='login') edpTechRedirect('technician.php');
    if ($staff) {
        $flash=is_string($_SESSION['tech_flash']??null)?$_SESSION['tech_flash']:'';
        unset($_SESSION['tech_flash']);
        if ($page==='queue') {
            $queue=edpTechQueue($pdo,(int)$staff['user_id'],$view,$pageNo);
        } elseif ($page==='device') {
            $id=edpTechDeviceId($_GET['id']??null);
            $detail=edpTechDevice($pdo,(int)$staff['user_id'],$id);
            if (!$detail) { http_response_code(404); throw new DomainException('Repair not found in your queue.'); }
            $next=edpTechNextStatuses($pdo,$detail);
        } else { http_response_code(404); throw new DomainException('Page not found.'); }
    }
} catch (DomainException $e) {
    $error=$e->getMessage();
    if (http_response_code()<400) http_response_code(422);
    // After a failed POST, re-fetch only a record that the technician may view.
    if ($pdo instanceof PDO && $staff && ($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        $id=is_string($_POST['device_id']??null) && ctype_digit($_POST['device_id'])?(int)$_POST['device_id']:0;
        if ($id>0) {
            $detail=edpTechDevice($pdo,(int)$staff['user_id'],$id);
            if ($detail) {
                $page='device';
                $next=edpTechNextStatuses($pdo,$detail);
            } else $page='queue';
        }
        if ($page==='queue') { $view='active';$pageNo=1;$queue=edpTechQueue($pdo,(int)$staff['user_id']); }
    }
} catch (Throwable $e) {
    error_log('RepairTrack EDP technician error: '.get_class($e));
    http_response_code(503);
    $error='The academic technician workspace is temporarily unavailable.';
}
$csrf=edpTechCsrf();
$h='edpTechEscape';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Technician Workspace · RepairTrack Academic</title>
<link rel="stylesheet" href="assets/technician.css">
<script defer src="assets/technician.js"></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="technician.php">Repair<span>Track</span></a>
  <span class="top-sub">Technician Workspace <small>Academic demo</small></span>
  <?php if ($staff): ?><a href="lab.php" class="lab-shortcut">EDP Lab Tools</a><?php endif; ?>
  <?php if ($staff): ?>
  <div class="account"><span><?= $h($staff['full_name']) ?></span>
  <form method="post" action="technician.php"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><button class="quiet" type="submit">Sign out</button></form></div>
  <?php endif; ?>
</header>
<main class="container">
<?php if ($error!==''): ?><div class="alert" role="alert"><?= $h($error) ?></div><?php endif; ?>
<?php if ($flash!==''): ?><div class="notice" role="status"><?= $h($flash) ?></div><?php endif; ?>
<?php if (!$staff): ?>
<section class="panel signin">
  <p class="eyebrow">Staff-only access</p>
  <h1>Technician sign in</h1><p>Use an assigned, active technician account for the fictional academic branch.</p>
  <form method="post" action="technician.php?page=login" class="stack">
    <input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
    <label>Email<input type="email" name="email" autocomplete="username" maxlength="190" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="primary" type="submit">Open my workspace</button>
  </form>
  <p class="hint"><a href="index.php?page=login">Intake staff</a> · <a href="track.php">Customer tracking</a></p>
</section>
<?php elseif ($page==='queue' && $queue): ?>
<section class="hero"><div><p class="eyebrow">Today's work</p><h1>My repair queue</h1><p>Only devices assigned to your account at active branches appear here.</p></div>
<span class="counter" aria-label="Repair count"><?= (int)$queue['total'] ?> <?= $view==='history'?'archived':'assigned' ?></span></section>
<nav class="tabs" aria-label="Repair queue views"><a <?= $view==='active'?'aria-current="page"':'' ?> href="technician.php?view=active">Active repairs</a><a <?= $view==='history'?'aria-current="page"':'' ?> href="technician.php?view=history">Repair history</a></nav>
<div class="panel">
<?php if (!$queue['rows']): ?><div class="empty"><h2>No repairs in this view</h2><p>New assignments will appear here when a manager assigns them.</p></div>
<?php else: ?>
<div class="queue-list">
<?php foreach ($queue['rows'] as $row): ?>
<a class="queue-item" href="technician.php?page=device&amp;id=<?= (int)$row['ticket_device_id'] ?>">
  <div><small><?= $h($row['item_code']) ?> · <?= $h($row['branch_name']) ?></small><strong><?= $h($row['device_type']??'Device') ?><?= $row['model_snapshot']?' · '.$h($row['model_snapshot']):'' ?></strong><span class="quiet-text">Ticket <?= $h($row['ticket_code']) ?> · Updated <?= $h(edpTechLocalTime($row['updated_at'])) ?></span></div>
  <div class="queue-right"><span class="status-pill"><?= $h($row['status_code']) ?></span><span class="quiet-text"><?= $row['quote_status']?$h('Quote: '.$row['quote_status']):'No quote' ?></span></div>
</a>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="pagination"><span>Page <?= (int)$queue['page'] ?> of <?= (int)$queue['pages'] ?></span>
<?php if ($queue['page']>1): ?><a href="technician.php?view=<?= $h($view) ?>&amp;p=<?= (int)($queue['page']-1) ?>">Previous</a><?php endif; ?>
<?php if ($queue['page']<$queue['pages']): ?><a href="technician.php?view=<?= $h($view) ?>&amp;p=<?= (int)($queue['page']+1) ?>">Next</a><?php endif; ?></div>
</div>
<?php elseif ($page==='device' && $detail): ?>
<nav class="breadcrumbs"><a href="technician.php">← My queue</a><span><?= $h($detail['item_code']) ?></span></nav>
<section class="hero"><div><p class="eyebrow"><?= $h($detail['ticket_code']) ?> · <?= $h($detail['branch_name']) ?></p>
<h1><?= $h($detail['device_type']??'Device') ?><?= $detail['model_snapshot']?' · '.$h($detail['model_snapshot']):'' ?></h1><p>Staff-only repair details. Internal diagnosis is never shown in the customer portal.</p></div>
<span class="status-pill large"><?= $h($detail['status_label']) ?></span></section>
<div class="facts"><div><small>Repair started</small><strong><?= $h(edpTechLocalTime($detail['repair_started_at'])) ?></strong></div><div><small>Custody</small><strong><?= $h(str_replace('_',' ',$detail['custody_state'])) ?></strong></div><div><small>Quote status</small><strong><?= $h($detail['quote']['quote_status']??'Not prepared') ?></strong></div></div>
<div class="columns">
<section class="panel"><h2>Assessment</h2><p class="eyebrow">Reported problem</p><p><?= $h($detail['reported_symptoms']) ?></p>
<?php if ($detail['defects']): ?><p class="hint">Defects: <?= $h(implode(', ',array_column($detail['defects'],'defect_code'))) ?></p><?php endif; ?>
<?php if ($detail['status_code']==='DIAGNOSING' && $detail['ticket_state']==='OPEN' && $detail['custody_state']==='AT_LOCATION'): ?>
<form class="stack separated" method="post" action="technician.php?page=device&amp;id=<?= (int)$detail['ticket_device_id'] ?>">
<input type="hidden" name="action" value="diagnosis"><input type="hidden" name="device_id" value="<?= (int)$detail['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Private diagnosis<textarea name="diagnosis" minlength="10" maxlength="3000" rows="5" required><?= $h($detail['diagnosis']) ?></textarea></label>
<button class="primary" type="submit">Save diagnosis</button></form>
<?php else: ?><p class="eyebrow separated">Private diagnosis</p><p><?= $detail['diagnosis']?$h($detail['diagnosis']):'No diagnosis recorded yet.' ?></p><?php endif; ?>
</section>
<section class="panel"><h2>Repair progress</h2>
<?php if ($next): ?><form class="stack" method="post" action="technician.php?page=device&amp;id=<?= (int)$detail['ticket_device_id'] ?>">
<input type="hidden" name="action" value="status"><input type="hidden" name="device_id" value="<?= (int)$detail['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Next allowed status<select name="next_status" data-repair-status-choice required><option value="">Choose next stage</option>
<?php foreach ($next as $option): ?><option value="<?= $h($option['status_code']) ?>"><?= $h($option['label']) ?></option><?php endforeach; ?></select></label>
<label>Customer-visible progress note (optional)<textarea name="public_summary" maxlength="500" rows="3" placeholder="Describe the update without sensitive details"></textarea></label>
<p class="hint" data-status-preview role="status">The customer timeline will update after you save.</p>
<button class="primary" type="submit">Update repair status</button></form>
<?php else: ?><p class="hint">No status transition is currently available. An approved or sent quotation, or a manager action, may be required.</p><?php endif; ?>
<div class="rule"><strong>Repair start time</strong><p>The first authorized transition into In Repair sets this DateTime once. Later status changes never reset it.</p></div>
</section>
<section class="panel"><h2>Quotation draft</h2>
<?php if ($detail['quote']): ?><p class="quote-info">Latest estimate: <strong>₱<?= $h($detail['quote']['total_amount']) ?></strong> <span class="status-pill"><?= $h($detail['quote']['quote_status']) ?></span></p><?php endif; ?>
<?php if ($detail['ticket_state']==='OPEN' && in_array($detail['status_code'],['DIAGNOSING','AWAITING_PARTS'],true)
    && trim((string)$detail['diagnosis'])!=='' && (!$detail['quote'] || in_array($detail['quote']['quote_status'],['DRAFT','DECLINED'],true))): ?>
<form method="post" class="stack" action="technician.php?page=device&amp;id=<?= (int)$detail['ticket_device_id'] ?>">
<input type="hidden" name="action" value="quote"><input type="hidden" name="device_id" value="<?= (int)$detail['ticket_device_id'] ?>"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<div class="two-col"><label>Labor (₱)<input name="labor" type="number" min="0" step="0.01" value="<?= $h($detail['quote']['quote_status']??null)==='DRAFT'?$h($detail['quote']['labor_amount']):'0.00' ?>" required></label>
<label>Parts (₱)<input name="parts" type="number" min="0" step="0.01" value="<?= $h($detail['quote']['quote_status']??null)==='DRAFT'?$h($detail['quote']['parts_amount']):'0.00' ?>" required></label></div>
<label>Other (₱)<input name="other" type="number" min="0" step="0.01" value="<?= $h($detail['quote']['quote_status']??null)==='DRAFT'?$h($detail['quote']['other_amount']):'0.00' ?>" required></label>
<button class="primary" type="submit">Save quotation draft</button></form>
<?php endif; ?><p class="hint">Technicians cannot approve or send quotations. Manager/customer approval will be connected in the next milestone.</p></section>
<section class="panel"><h2>Recorded timeline</h2>
<?php if (!$detail['events']): ?><p class="hint">No events yet.</p><?php else: ?><ol class="timeline">
<?php foreach ($detail['events'] as $event): ?><li><div><strong><?= $h($event['to_status']) ?></strong><time><?= $h(edpTechLocalTime($event['created_at'])) ?></time></div>
<?php if ($event['public_summary']): ?><p><?= $h($event['public_summary']) ?></p><?php endif; ?>
<?php if ($event['internal_note']): ?><p class="internal-note">Staff only: <?= $h($event['internal_note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section>
</div>
<?php else: ?><section class="panel"><h1>Unavailable</h1><p>Use your assigned queue to open repairs.</p></section><?php endif; ?>
</main>
<footer>RepairTrack · Isolated academic technician preview · No public deployment</footer>
</body></html>
