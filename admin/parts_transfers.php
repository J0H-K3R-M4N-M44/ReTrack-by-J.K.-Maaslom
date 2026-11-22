<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/inventory/losses.php';
startAppSession();
$user = requireRoles(['ADMIN','MANAGER']);
header('Cache-Control: no-store, private');
$pdo = getPDO();
$error = '';
$notice = $_SESSION['parts_transfer_notice'] ?? '';
unset($_SESSION['parts_transfer_notice']);
$branches = intakeBranches($pdo,(int)$user['user_id']);
$allowed = array_map('intval',array_column($branches,'branch_id'));
$branch = (int)requestText($_GET,'branch');
if (!$branch || !in_array($branch,$allowed,true)) {
    $branch = (int)($allowed[0] ?? 0);
}
$q = mb_substr(trim(requestText($_GET,'q')),0,60);
$pageNumber = max(1,(int)requestText($_GET,'page','1'));
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrfIsValid(requestText($_POST,'csrf_token'))) {
            unavailablePage('The parts manifest form expired. Reopen the page.',403);
        }
        $branch = (int)requestText($_POST,'branch');
        if (!in_array($branch,$allowed,true)) {
            throw new DomainException('Choose a branch assigned to your account.');
        }
        $action = requestText($_POST,'action');
        if ($action==='dispatch') {
            $code = partsTransferDispatch($pdo,(int)$user['user_id'],$branch,
                (int)requestText($_POST,'destination'),requestText($_POST,'sku_lines'),
                requestText($_POST,'note'),requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] = 'Parts dispatched under manifest '.$code.
                '. Keep the physical manifest with the shipment.';
        } elseif (in_array($action,['receive','missing'],true)) {
            partsTransferReceive($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'item_id'),requestText($_POST,'expected'),
                $action,requestText($_POST,'amount'),requestText($_POST,'note'),
                requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] = $action==='receive'
                ? 'Physical receipt recorded. Any remaining quantity stays in transit or under exception.'
                : 'Missing-part discrepancy recorded. No stock was added to the receiving branch.';
        } elseif ($action==='report-loss') {
            partsLossReport($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'item_id'),requestText($_POST,'expected'),
                requestText($_POST,'evidence_reference'),requestText($_POST,'reason'),
                requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] =
                'Formal loss investigation recorded. Source-branch review is required before closure.';
        } elseif ($action==='decide-loss') {
            partsLossDecide($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'loss_case_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] =
                'Independent loss decision recorded. Review the shipment and stock ledger.';
        } else {
            throw new DomainException('Choose a valid parts transfer action.');
        }
        header('Location: parts_transfers.php?'.http_build_query([
            'branch'=>$branch,'q'=>mb_substr(requestText($_POST,'q'),0,60),'page'=>1,
        ]),true,303);
        exit();
    }
} catch (DomainException $problem) {
    $error = $problem->getMessage();
} catch (Throwable $problem) {
    error_log('Parts transfer write error: '.$problem->getMessage());
    $error = 'The transfer could not be saved. No partial inventory update was accepted.';
}
$results=['records'=>[],'total'=>0,'page'=>1,'pages'=>1];
try {
    if ($branch > 0) {
        $results=partsTransferQueue($pdo,(int)$user['user_id'],$branch,$q,$pageNumber);
    }
    $destinations=$pdo->query('SELECT branch_id,branch_name FROM branches WHERE is_active=1
        ORDER BY branch_name')->fetchAll();
} catch (Throwable $problem) {
    error_log('Parts transfer read error: '.$problem->getMessage());
    unavailablePage('Parts transfers are unavailable. Check the database and try again.');
}
$workspaceUser=$user;
$currentSection='parts-transfers';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Parts transfers | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
<link rel="stylesheet" href="../css/workspace.css"><link rel="stylesheet" href="../css/workspace-design.css">
<script src="../js/workspace-design.js" defer></script>
</head><body class="dashboard-body rt-layout">
<?php require __DIR__ . '/../views/workspace/navigation.php'; ?>
<main id="rt-main" class="staff-page workspace rt-content" tabindex="-1">
<header class="staff-heading rt-page-head"><div>
<p class="eyebrow">RepairTrack · Cross-branch inventory custody</p>
<h1>Physical parts transfers</h1>
<p>Dispatch unreserved catalog parts; record what physically arrives and report shortages.
Device transfer manifests are managed separately.</p>
</div><a class="secondary-btn" href="inventory.php">Branch inventory</a></header>
<?php if ($notice!==''): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error!==''): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="dashboard-card rt-card">
<h2>Assigned branch</h2>
<form method="get" class="console-search">
<label>Branch<select required name="branch">
<?php foreach ($branches as $b): ?>
<option value="<?= (int)$b['branch_id'] ?>" <?= (int)$b['branch_id']===$branch?'selected':'' ?>>
<?= h($b['branch_name']) ?></option>
<?php endforeach; ?></select></label>
<label>Transfer code or SKU<input type="search" name="q" value="<?= h($q) ?>" maxlength="60"></label>
<button class="secondary-btn">Filter transfers</button>
</form>
</section>
<section class="dashboard-card rt-card">
<h2>Dispatch usable stock</h2>
<p>Stock leaves the origin shelf when you dispatch, not when the receiving branch confirms it.
Reserved parts, quarantined parts and inactive SKUs cannot be sent. Use one <code>SKU,quantity</code>
per line (maximum 20) and retain the physical shipment evidence.</p>
<details <?= requestText($_POST,'action')==='dispatch'?'open':'' ?>><summary>New parts manifest</summary>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<label>Destination branch<select name="destination" required>
<option value="">Select a different active branch</option>
<?php foreach ($destinations as $b):
if ((int)$b['branch_id']===$branch) continue; ?>
<option value="<?= (int)$b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
<?php endforeach; ?></select></label>
<label>SKU and shipped quantity<textarea name="sku_lines" rows="4" required
placeholder="SSD-256,2&#10;BAT-120,1"><?= h(requestText($_POST,'sku_lines')) ?></textarea></label>
<label>Dispatch note<textarea name="note" rows="2" minlength="10" maxlength="500" required
placeholder="List the checked boxes, seal and dispatch reference."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I physically counted these usable parts, checked the manifest and handed over the shipment.</label>
<button name="action" value="dispatch">Record parts dispatch</button>
</form></details>
</section>
<section class="dashboard-card rt-card"><h2>Transfer ledger</h2>
<p><?= (int)$results['total'] ?> manifest items · 10 per page. A partial receipt creates an exception.
A confirmed operational loss closes the missing quantity without crediting any unreceived stock.
Evidence references describe privately retained inspection records; files are not uploaded on this page.</p>
<?php if (!$results['records']): ?><p>No parts manifests match this branch and search.</p><?php endif; ?>
<?php foreach ($results['records'] as $item):
$isRecipient=(int)$item['to_branch_id']===$branch;
$isOrigin=(int)$item['from_branch_id']===$branch;
$lossStatus=(string)($item['loss_status'] ?? '');
$canReceive=$isRecipient && !in_array($item['item_status'],['RECEIVED','CANCELLED','LOSS_CLOSED'],true) &&
    $lossStatus!=='PENDING' && (int)$item['initiated_by_user_id']!==(int)$user['user_id'];
$canReportLoss=$isRecipient && $item['item_status']==='EXCEPTION' &&
    (int)$item['missing_reported']===1 && !in_array($lossStatus,['PENDING','APPROVED'],true) &&
    (int)$item['initiated_by_user_id']!==(int)$user['user_id'];
$canDecideLoss=$isOrigin && $lossStatus==='PENDING' &&
    (int)$item['reported_by_user_id']!==(int)$user['user_id'] &&
    (int)$item['initiated_by_user_id']!==(int)$user['user_id'];
?>
<article class="dashboard-card">
<h3><?= h($item['transfer_code']) ?> · <?= h($item['part_name']) ?></h3>
<p><strong><?= h($item['sku']) ?></strong> · <?= h($item['origin_name']) ?> → <?= h($item['destination_name']) ?></p>
<p>Shipped <strong><?= h((string)$item['quantity']) ?></strong> · Received
<strong><?= h((string)$item['received_quantity']) ?></strong> · Outstanding
<strong><?= h($item['outstanding']) ?></strong> · Status <?= h($item['item_status']) ?></p>
<?php if ($item['receipt_note']): ?><p>Latest receipt note: <?= h($item['receipt_note']) ?></p><?php endif; ?>
<?php if ($item['loss_case_id']): ?>
<p><strong>Formal loss case #<?= (int)$item['loss_case_id'] ?> · <?= h($lossStatus) ?></strong>
 · Reported missing <?= h((string)$item['loss_quantity']) ?></p>
<p>Investigation reference: <?= h($item['evidence_reference']) ?></p>
<p>Investigation: <?= h($item['report_reason']) ?></p>
<?php if ($item['review_reason']): ?><p>Review: <?= h($item['review_reason']) ?></p><?php endif; ?>
<?php endif; ?>
<?php if ($lossStatus==='PENDING'): ?>
<p>Physical receiving is suspended while independent loss review is pending. The original
dispatch and receipt quantities remain unchanged.</p>
<?php elseif ($item['item_status']==='LOSS_CLOSED'): ?>
<p>Approved operational loss. Any unreceived quantity was not added to either branch.
Unexpected late recovery requires a separate reviewed recovery process.</p>
<?php endif; ?>
<?php if ($canReceive): ?>
<details><summary>Confirm receipt or report shortage</summary>
<p>Record only what you inspected at this branch. The dispatching manager cannot
approve their own delivery. For a partial delivery, enter just the quantity received now;
the remainder stays under exception until a later physical receipt.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="item_id" value="<?= (int)$item['inventory_transfer_item_id'] ?>">
<input type="hidden" name="expected" value="<?= h((string)$item['received_quantity']) ?>">
<label>Quantity newly received<input name="amount" type="number" min="0.001"
step="0.001" max="<?= h($item['outstanding']) ?>" value="<?= h($item['outstanding']) ?>" required></label>
<label>Receipt or shortage note<textarea name="note" required rows="2"
minlength="10" maxlength="500" placeholder="Record visible seals, condition, count and discrepancy."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I verified this physical receipt or missing quantity against the shipment manifest.</label>
<button name="action" value="receive">Record physical receipt</button>
<?php if (!(int)$item['missing_reported']): ?>
<button name="action" value="missing" class="secondary-btn">
Report missing shipment
</button>
<?php endif; ?>
</form></details>
<?php elseif ($isRecipient && (int)$item['initiated_by_user_id']===(int)$user['user_id']): ?>
<p>A different receiving manager must verify delivery.</p>
<?php endif; ?>
<?php if ($canReportLoss): ?>
<details><summary>Open formal loss investigation</summary>
<p>Only after reporting a missing shipment. Provide the identifier of the sealed-container
inspection, incident report, or privately stored evidence. This is a reference field,
not a file upload. The sending branch must review the report independently.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="item_id" value="<?= (int)$item['inventory_transfer_item_id'] ?>">
<input type="hidden" name="expected" value="<?= h((string)$item['received_quantity']) ?>">
<label>Evidence or incident reference
<input name="evidence_reference" required minlength="8" maxlength="160"
placeholder="INV-2026-024 signed seal inspection"></label>
<label>Investigation and physical recount
<textarea name="reason" required minlength="20" maxlength="500" rows="3"
placeholder="Document who inspected the shipment, missing quantity and retained evidence."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I physically checked the outstanding quantity and retained the supporting inspection record.</label>
<button name="action" value="report-loss" class="secondary-btn">Submit formal loss report</button>
</form></details>
<?php endif; ?>
<?php if ($canDecideLoss): ?>
<details><summary>Independently review missing-stock case</summary>
<p>Only another manager assigned to the sending branch may approve an operational loss.
Approval never issues a second debit or a receiving credit. Reject if evidence
or the actual physical count is insufficient.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="loss_case_id" value="<?= (int)$item['loss_case_id'] ?>">
<label>Decision<select name="decision" required>
<option value="reject">Reject — keep shortage unresolved</option>
<option value="approve">Approve operational loss — close outstanding quantity</option>
</select></label>
<label>Independent verification
<textarea name="reason" minlength="20" maxlength="500" required rows="3"
placeholder="Explain cross-checks against the sending record, receiving count and incident reference."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I independently verified the shipment shortage and understand this is not a financial write-off.</label>
<button name="action" value="decide-loss">Record independent loss decision</button>
</form></details>
<?php elseif ($isOrigin && $lossStatus==='PENDING'): ?>
<p>Awaiting another source-branch manager's independent decision.</p>
<?php endif; ?>
</article>
<?php endforeach; ?>
<?php if ($results['pages']>1): ?>
<nav class="list-pager" aria-label="Parts transfer pages">
<span>Page <?= (int)$results['page'] ?> of <?= (int)$results['pages'] ?></span>
<?php foreach (['Previous'=>-1,'Next'=>1] as $name=>$step):
$target=$results['page']+$step;
if ($target<1 || $target>$results['pages']) continue; ?>
<a class="secondary-btn" href="?<?= h(http_build_query([
'branch'=>$branch,'q'=>$q,'page'=>$target])) ?>"><?= h($name) ?></a>
<?php endforeach; ?></nav>
<?php endif; ?></section>
</main></body></html>
