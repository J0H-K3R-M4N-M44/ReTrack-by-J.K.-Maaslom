<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/inventory/stocktaking.php';

startAppSession();
$user=requireRoles(['ADMIN','MANAGER']);
header('Cache-Control: no-store, private');
$pdo=getPDO();
$notice=$_SESSION['stocktake_notice']??'';
unset($_SESSION['stocktake_notice']);
$error='';
$branches=intakeBranches($pdo,(int)$user['user_id']);
$allowed=array_map('intval',array_column($branches,'branch_id'));
$branch=(int)requestText($_GET,'branch');
if (!$branch || !in_array($branch,$allowed,true)) $branch=(int)($allowed[0]??0);
$page=max(1,(int)requestText($_GET,'page','1'));
$selected=max(0,(int)requestText($_GET,'stocktake'));
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrfIsValid(requestText($_POST,'csrf_token'))) {
            unavailablePage('The stocktaking form expired. Open it again.',403);
        }
        $branch=(int)requestText($_POST,'branch');
        if (!in_array($branch,$allowed,true)) {
            throw new DomainException('Choose your assigned branch.');
        }
        $action=requestText($_POST,'action');
        $id=(int)requestText($_POST,'stocktake_id');
        $reason=requestText($_POST,'reason');
        $confirmed=requestText($_POST,'confirm')==='yes';
        if ($action==='open') {
            stocktakeOpen($pdo,(int)$user['user_id'],$branch,
                requestText($_POST,'period'),$reason,$confirmed);
            $_SESSION['stocktake_notice']='Monthly stocktake opened. Count every branch SKU before submitting.';
        } elseif ($action==='count') {
            stocktakeCount($pdo,(int)$user['user_id'],$id,
                (int)requestText($_POST,'part'),requestText($_POST,'expected_on_hand'),
                requestText($_POST,'expected_reserved'),(int)requestText($_POST,'expected_movement'),
                requestText($_POST,'counted_quantity'),$reason,$confirmed);
            $_SESSION['stocktake_notice']='Physical shelf count recorded without adjusting inventory.';
        } elseif ($action==='submit') {
            stocktakeSubmit($pdo,(int)$user['user_id'],$id,$reason,$confirmed);
            $_SESSION['stocktake_notice']='Stocktake submitted for independent reconciliation sign-off.';
        } elseif ($action==='decide') {
            stocktakeDecide($pdo,(int)$user['user_id'],$id,
                requestText($_POST,'decision'),$reason,$confirmed);
            $_SESSION['stocktake_notice']='Independent stocktake decision recorded.';
        } elseif ($action==='cancel') {
            stocktakeCancel($pdo,(int)$user['user_id'],$id,$reason,$confirmed);
            $_SESSION['stocktake_notice']='Unfinished stocktake cancelled with its history retained.';
        } else {
            throw new DomainException('Choose a valid stocktaking action.');
        }
        header('Location: stocktaking.php?'.http_build_query(['branch'=>$branch]),true,303);
        exit();
    }
} catch (DomainException $problem) {
    $error=$problem->getMessage();
} catch (Throwable $problem) {
    error_log('Stocktaking write: '.$problem->getMessage());
    $error='Stocktake could not be updated. No partial count was accepted.';
}
$desk=['session'=>null,'recent'=>[],'lines'=>[],'total'=>0,'counted'=>0,
    'discrepancies'=>0,'page'=>1,'pages'=>1];
try {
    if ($branch>0) $desk=stocktakeDesk($pdo,(int)$user['user_id'],$branch,$page,$selected);
} catch (DomainException $problem) {
    $error=$problem->getMessage();
} catch (Throwable $problem) {
    error_log('Stocktaking read: '.$problem->getMessage());
    unavailablePage('Branch stocktaking is unavailable. Check the database migration.');
}
$session=$desk['session'];
$sessionId=(int)($session['stocktake_id']??0);
$status=(string)($session['status']??'');
$isOpener=$session && (int)$session['opened_by_user_id']===(int)$user['user_id'];
$isIndependent=$session && $user['role']==='ADMIN' && !$isOpener;
if ($isIndependent) {
    $countedSelf=stockRun($pdo,'SELECT 1 FROM inventory_stocktake_lines
        WHERE stocktake_id=? AND counted_by_user_id=? LIMIT 1',
        [$sessionId,(int)$user['user_id']])->fetchColumn();
    $priorCounter=stockRun($pdo,"SELECT 1 FROM audit_events
        WHERE actor_user_id=? AND entity_type='branch' AND entity_id=?
          AND action='STOCKTAKE_COUNT_RECORDED'
          AND CAST(JSON_UNQUOTE(JSON_EXTRACT(after_data,'$.stocktake_id')) AS UNSIGNED)=?
        LIMIT 1",[(int)$user['user_id'],$branch,$sessionId])->fetchColumn();
    $isIndependent=!$countedSelf && !$priorCounter;
}
$workspaceUser=$user;
$currentSection='stocktaking';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Periodic stocktaking | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
<link rel="stylesheet" href="../css/workspace.css"><link rel="stylesheet" href="../css/workspace-design.css">
<script src="../js/workspace-design.js" defer></script>
</head><body class="dashboard-body rt-layout">
<?php require __DIR__ . '/../views/workspace/navigation.php'; ?>
<main id="rt-main" class="staff-page workspace rt-content" tabindex="-1">
<header class="staff-heading rt-page-head"><div>
<p class="eyebrow">RepairTrack · Physical reconciliation</p>
<h1>Monthly branch stocktaking</h1>
<p>Record usable physical on-hand quantities for every branch SKU, including inactive catalog parts.
A separate administrator signs off only after all quantities and reservations match current records.</p>
</div><a class="secondary-btn" href="inventory.php?branch=<?= $branch ?>">Branch inventory</a></header>
<?php if ($notice!==''): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error!==''): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="dashboard-card rt-card"><h2>Assigned branch</h2>
<form method="get" class="console-search">
<label>Branch<select name="branch" required>
<?php foreach ($branches as $b): ?>
<option value="<?= (int)$b['branch_id'] ?>" <?= (int)$b['branch_id']===$branch?'selected':'' ?>>
<?= h($b['branch_name']) ?></option>
<?php endforeach; ?></select></label>
<button class="secondary-btn">View stocktake</button>
</form>
<p>Counting and signing off do <strong>not</strong> adjust stock or customer bills.
Resolve discrepancies separately through <a href="inventory.php?branch=<?= $branch ?>">verified inventory counts</a>
and any required independent approval, then physically recount affected SKUs.</p>
</section>
<section class="dashboard-card rt-card"><h2>Stocktake history</h2>
<?php if (!$desk['recent']): ?><p>No previous stocktaking cycles at this branch.</p><?php endif; ?>
<?php foreach ($desk['recent'] as $historic): ?>
<p><a href="?<?= h(http_build_query(['branch'=>$branch,'stocktake'=>$historic['stocktake_id']])) ?>">
Cycle #<?= (int)$historic['stocktake_id'] ?> · <?= h($historic['period_ym']) ?></a>
 · <?= h($historic['status']) ?> · Opened <?= h($historic['opened_at']) ?>
<?php if ($historic['signed_at']): ?> · Signed <?= h($historic['signed_at']) ?><?php endif; ?></p>
<?php endforeach; ?>
</section>
<?php if (!$session || !in_array($status,['OPEN','PENDING'],true)): ?>
<section class="dashboard-card rt-card"><h2>Start a new monthly count</h2>
<p>Only one active cycle per branch. This creates a snapshot of all existing SKU shelves
(maximum 250); new catalog items added mid-cycle require cancelling and restarting.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="period" value="<?= gmdate('Y-m') ?>">
<label>Count plan / location<textarea name="reason" required minlength="15" maxlength="500" rows="3"
placeholder="Describe the physical count area and intended verification process."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I will physically count all currently stocked parts during <?= gmdate('Y-m') ?>.</label>
<button name="action" value="open">Open monthly stocktake</button>
</form></section>
<?php endif; ?>
<?php if ($session): ?>
<section class="dashboard-card rt-card"><h2>Cycle #<?= $sessionId ?> · <?= h($session['period_ym']) ?></h2>
<p><strong><?= h($status) ?></strong> ·
<?= (int)$desk['counted'] ?>/<?= (int)$desk['total'] ?> SKUs physically counted ·
<?= (int)$desk['discrepancies'] ?> discrepancies</p>
<?php if ($session['decision_note']): ?><p>Latest review note: <?= h($session['decision_note']) ?></p><?php endif; ?>
<?php if ($status==='OPEN' && $isOpener): ?>
<details><summary>Submit all physical counts for independent review</summary>
<p>Every SKU must have a recorded count against the latest shelf snapshot. Unresolved
discrepancies can be submitted for review, but cannot receive a clean sign-off.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="stocktake_id" value="<?= $sessionId ?>">
<label>Submission explanation<textarea name="reason" rows="2" minlength="15" maxlength="500" required
placeholder="Document how and when every physical shelf was verified."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I checked that all recorded counts are complete and ready for independent review.</label>
<button name="action" value="submit">Submit stocktake for sign-off</button>
</form></details>
<details><summary>Cancel incomplete stocktake</summary>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="stocktake_id" value="<?= $sessionId ?>">
<label>Cancellation explanation<textarea name="reason" minlength="15" maxlength="500" rows="2" required></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I understand the cycle will be retained as cancelled evidence.</label>
<button class="secondary-btn" name="action" value="cancel">Cancel this cycle</button>
</form></details>
<?php elseif ($status==='PENDING' && $isIndependent): ?>
<details><summary>Independent sign-off or return for recount</summary>
<p>Review every SKU, unresolved variance, current reservations, and movement history.
<strong>Sign-off is possible only if every count is fresh and all differences are zero.</strong></p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="stocktake_id" value="<?= $sessionId ?>">
<label>Review decision<select name="decision" required>
<option value="return">Return for recount or approved inventory correction</option>
<option value="signoff">Sign off reconciled branch stock (no stock movement)</option>
</select></label>
<label>Independent verification<textarea name="reason" required minlength="15"
maxlength="500" rows="3" placeholder="Explain independent sample checks, reservation review and reconciliation."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I independently checked all SKU counts and am not the opener or a physical counter.</label>
<button name="action" value="decide">Record independent stocktake decision</button>
</form></details>
<?php elseif ($status==='PENDING'): ?>
<p>A separate, non-counting branch administrator must review this submitted stocktake.</p>
<?php endif; ?>
</section>
<section class="dashboard-card rt-card"><h2>Physical counts and reconciliation</h2>
<p>Showing 10 of <?= (int)$desk['total'] ?> SKU records per page.
The opening snapshot remains intact; each recount stores the latest stock and reservation
baseline, while the audit log records earlier counts.</p>
<?php foreach ($desk['lines'] as $line):
$partId=(int)$line['part_id'];
$hasCount=$line['counted_on_hand']!==null;
$diff=$hasCount ? stockUnits((string)$line['counted_on_hand'])-
    stockUnits((string)$line['counted_expected_on_hand']) : 0;
?>
<article class="dashboard-card">
<h3><?= h($line['part_name']) ?> <small><?= h($line['sku']) ?></small></h3>
<p>Opening usable <?= h($line['opening_on_hand']) ?> ·
Opening reserved <?= h($line['opening_reserved']) ?> ·
Current usable <?= h((string)$line['current_on_hand']) ?> ·
Current reserved <?= h((string)$line['current_reserved']) ?></p>
<?php if ($hasCount): ?>
<p><strong>Physical <?= h($line['counted_on_hand']) ?></strong> ·
Count baseline <?= h($line['counted_expected_on_hand']) ?> ·
Variance <?= h(stockSigned($diff)) ?> · Counted <?= h($line['counted_at']) ?></p>
<?php if ($line['stale']): ?>
<p role="status">Shelf or reservations changed after this count; physically recount before sign-off.</p>
<?php endif; ?>
<?php if ($line['count_note']): ?><p>Count evidence: <?= h($line['count_note']) ?></p><?php endif; ?>
<?php else: ?><p>Not yet counted.</p><?php endif; ?>
<?php if (!(int)$line['is_active']): ?><p>Inactive SKU — physical stock still requires a count.</p><?php endif; ?>
<?php if ($status==='OPEN'): ?>
<details><summary><?= $hasCount?'Physically recount this SKU':'Record physical SKU count' ?></summary>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="stocktake_id" value="<?= $sessionId ?>">
<input type="hidden" name="part" value="<?= $partId ?>">
<input type="hidden" name="expected_on_hand" value="<?= h((string)$line['current_on_hand']) ?>">
<input type="hidden" name="expected_reserved" value="<?= h((string)$line['current_reserved']) ?>">
<input type="hidden" name="expected_movement" value="<?= (int)$line['current_movement'] ?>">
<label>Physically counted usable on-hand quantity
<input type="number" name="counted_quantity" required min="0" step="0.001"
placeholder="Enter the independently observed count"></label>
<label>Physical verification / exception evidence
<textarea name="reason" rows="2" required minlength="15" maxlength="500"
placeholder="Where and how this SKU was counted, including any discrepancy."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I actually counted usable shelf stock. I excluded quarantined items and did not adjust inventory.</label>
<button name="action" value="count">Record physical count</button>
</form></details>
<?php endif; ?>
</article>
<?php endforeach; ?>
<?php if ($desk['pages']>1): ?>
<nav class="list-pager" aria-label="Stocktake count pages">
<span>Page <?= (int)$desk['page'] ?> of <?= (int)$desk['pages'] ?></span>
<?php foreach (['Previous'=>-1,'Next'=>1] as $label=>$step):
$target=$desk['page']+$step;if($target<1||$target>$desk['pages']) continue; ?>
<a class="secondary-btn" href="?<?= h(http_build_query([
    'branch'=>$branch,'stocktake'=>$sessionId,'page'=>$target])) ?>"><?= h($label) ?></a>
<?php endforeach; ?></nav>
<?php endif; ?>
</section>
<?php endif; ?>
</main></body></html>
