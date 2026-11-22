<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/inventory/evidence.php';
require_once __DIR__ . '/../app/inventory/valuation.php';
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
        } elseif ($action==='report-recovery') {
            partsRecoveryReport($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'loss_case_id'),
                requestText($_POST,'expected'),
                requestText($_POST,'quantity'),requestText($_POST,'evidence_reference'),
                requestText($_POST,'reason'),requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] =
                'Recovered parts held pending independent inspection and release. No stock was added.';
        } elseif ($action==='decide-recovery') {
            partsRecoveryDecide($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'recovery_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] =
                'Independent recovery decision saved. Only approved quantities enter usable stock.';
        } elseif ($action==='attach-evidence') {
            stockEvidenceUpload($pdo,(int)$user['user_id'],requestText($_POST,'case_type'),
                (int)requestText($_POST,'case_id'),$_FILES['evidence'] ?? []);
            $_SESSION['parts_transfer_notice'] =
                'Private inspection evidence attached to the pending investigation.';
        } elseif ($action==='propose-valuation') {
            partsValuationPropose($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'loss_case_id'),requestText($_POST,'expected'),
                requestText($_POST,'unit_cost'),requestText($_POST,'cost_basis'),
                requestText($_POST,'cost_reference'),requestText($_POST,'reason'),
                requestText($_POST,'confirm')==='yes',
                (int)requestText($_POST,'cost_record_id'));
            $_SESSION['parts_transfer_notice'] =
                'PHP financial exposure submitted for independent source-branch administrator review. No cash or stock changed.';
        } elseif ($action==='decide-valuation') {
            partsValuationDecide($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'valuation_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm')==='yes');
            $_SESSION['parts_transfer_notice'] =
                'Independent PHP valuation decision recorded. No accounting journal, cash or stock was changed.';
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
</div><div><a class="secondary-btn" href="inventory.php">Branch inventory</a>
<a class="secondary-btn" href="acquisition_costs.php?branch=<?= $branch ?>">Supplier acquisition costs</a></div></header>
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
Investigation evidence remains private. Purchase-cost valuations below are internal PHP
exposure assessments only, not customer charges, cashier payments or accounting write-offs.</p>
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
$recoveryStatus=(string)($item['recovery_status'] ?? '');
$canReportRecovery=$isRecipient && $lossStatus==='APPROVED' &&
    $item['item_status']==='LOSS_CLOSED' && $recoveryStatus!=='PENDING' &&
    (int)$item['initiated_by_user_id']!==(int)$user['user_id'];
$canDecideRecovery=$isOrigin && $recoveryStatus==='PENDING' &&
    (int)$item['initiated_by_user_id']!==(int)$user['user_id'] &&
    (int)$item['reported_by_user_id']!==(int)$user['user_id'] &&
    (int)$item['recovery_reported_by']!==(int)$user['user_id'];
$lossFiles=$item['loss_case_id']
    ? stockEvidenceList($pdo,(int)$user['user_id'],'LOSS',(int)$item['loss_case_id']) : [];
$recoveryFiles=$item['recovery_id']
    ? stockEvidenceList($pdo,(int)$user['user_id'],'RECOVERY',(int)$item['recovery_id']) : [];
$valuationStatus=(string)($item['valuation_status']??'');
$valuationHistory=$item['loss_case_id']
    ? partsValuationHistory($pdo,(int)$user['user_id'],(int)$item['loss_case_id']) : [];
$hasPendingRecovery=$recoveryStatus==='PENDING';
$canProposeValuation=$isRecipient && $lossStatus==='APPROVED' &&
    $valuationStatus!=='PENDING' && !$hasPendingRecovery &&
    (int)$item['initiated_by_user_id']!==(int)$user['user_id'];
$canDecideValuation=$isOrigin && $user['role']==='ADMIN' &&
    $valuationStatus==='PENDING' &&
    !in_array((int)$user['user_id'],[
        (int)$item['initiated_by_user_id'],
        (int)$item['reported_by_user_id'],
        (int)$item['reviewed_by_user_id'],
        (int)$item['valuation_preparer'],
    ],true);
$valuationOutdated=$item['approved_valuation_id'] &&
    (string)$item['approved_valuation_remaining']!==$item['outstanding'];
?>
<article class="dashboard-card">
<h3><?= h($item['transfer_code']) ?> · <?= h($item['part_name']) ?></h3>
<p><strong><?= h($item['sku']) ?></strong> · <?= h($item['origin_name']) ?> → <?= h($item['destination_name']) ?></p>
<p>Shipped <strong><?= h((string)$item['quantity']) ?></strong> · Received
<strong><?= h((string)$item['received_quantity']) ?></strong> · Not physically received
<strong><?= h($item['outstanding']) ?></strong> · Status <?= h($item['item_status']) ?></p>
<?php if ($item['receipt_note']): ?><p>Latest receipt note: <?= h($item['receipt_note']) ?></p><?php endif; ?>
<?php if ($item['loss_case_id']): ?>
<p><strong>Formal loss case #<?= (int)$item['loss_case_id'] ?> · <?= h($lossStatus) ?></strong>
 · Reported missing <?= h((string)$item['loss_quantity']) ?></p>
<p>Investigation reference: <?= h($item['evidence_reference']) ?></p>
<p>Investigation: <?= h($item['report_reason']) ?></p>
<?php if ($item['review_reason']): ?><p>Review: <?= h($item['review_reason']) ?></p><?php endif; ?>
<?php if ($lossFiles): ?>
<p>Private loss evidence:
<?php foreach ($lossFiles as $file): ?>
<a href="stock_evidence.php?id=<?= (int)$file['evidence_id'] ?>">
<?= h($file['original_filename']) ?></a>
<?php endforeach; ?></p>
<?php endif; ?>
<?php if ($lossStatus==='PENDING' && count($lossFiles)<6): ?>
<details><summary>Attach private loss evidence</summary>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="case_type" value="LOSS">
<input type="hidden" name="case_id" value="<?= (int)$item['loss_case_id'] ?>">
<label>Inspection photo or PDF (maximum 5 MB)
<input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required></label>
<p>Stored privately; no public link. Only assigned sending/receiving branch managers
may download. PDFs are served as downloads rather than displayed in the browser.</p>
<button name="action" value="attach-evidence" class="secondary-btn">Attach loss evidence</button>
</form></details>
<?php endif; ?>
<?php if ($lossStatus==='APPROVED'): ?>
<p>Approved loss originally <?= h((string)$item['loss_quantity']) ?> ·
  Independently recovered <?= h((string)$item['recovered_approved_quantity']) ?> ·
  Still missing <?= h($item['outstanding']) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($lossStatus==='APPROVED'): ?>
<section class="rt-card" aria-label="Internal financial loss valuation">
<h4>PHP purchase-cost exposure · Internal only</h4>
<p>Verified registry costs are preferred; manual evidence is explicitly identified as an attestation.
This is a reviewed estimate of loss at documented acquisition cost. It is
<strong>not</strong> a sales price, customer bill, tax figure, bank transfer or posted accounting write-off.</p>
<?php if ($item['approved_valuation_id']): ?>
<p>Latest approved exposure: <strong>PHP <?= h((string)$item['approved_valuation_exposure']) ?></strong>
for <?= h((string)$item['approved_valuation_remaining']) ?> missing units.</p>
<?php if ($valuationOutdated): ?>
<p role="status"><strong>Reassessment needed:</strong> the approved exposure is a historic
snapshot. Physical recovery changed the current outstanding quantity to
<?= h($item['outstanding']) ?>; prepare a revised independent valuation.</p>
<?php else: ?>
<p>Current approved exposure corresponds to the latest physically outstanding quantity.</p>
<?php endif; ?>
<?php else: ?>
<p>No independently approved monetary valuation has been recorded for this loss.</p>
<?php endif; ?>
<?php if ($item['valuation_id']): ?>
<p>Latest valuation #<?= (int)$item['valuation_id'] ?> · Revision
<?= (int)$item['valuation_revision'] ?> · <strong><?= h($valuationStatus) ?></strong>
 · PHP <?= h((string)$item['valuation_exposure']) ?>
(<?= h((string)$item['valuation_remaining']) ?> × PHP <?= h((string)$item['valuation_unit_cost']) ?>)
 · Basis: <?= h((string)$item['valuation_basis']) ?>
 · Reference: <?= h((string)$item['valuation_reference']) ?></p>
<?php endif; ?>
<?php if ($valuationHistory): ?>
<details><summary>View valuation history (latest five)</summary>
<ol>
<?php foreach ($valuationHistory as $entry): ?>
<li>Revision <?= (int)$entry['revision_no'] ?> — <?= h($entry['status']) ?> ·
PHP <?= h((string)$entry['exposure_php']) ?> ·
<?= h((string)$entry['remaining_quantity']) ?> units at PHP
<?= h((string)$entry['unit_cost_php']) ?> · <?= h($entry['cost_basis']) ?>
(<?= h($entry['cost_reference']) ?>).
Prepared <?= h((string)$entry['prepared_at']) ?>
<?php if ($entry['reviewed_at']): ?>; reviewed <?= h((string)$entry['reviewed_at']) ?><?php endif; ?>
<?php if ($entry['review_reason']): ?> — <?= h($entry['review_reason']) ?><?php endif; ?>
</li>
<?php endforeach; ?>
</ol></details>
<?php endif; ?>
<?php if ($canProposeValuation): ?>
<?php if ($item['approved_source_cost_id']): ?>
<details><summary>Value loss using independently verified supplier cost</summary>
<p>The acquisition cost below belongs to this exact part at the sending branch.
It is loaded by the server, never taken from the submitted form.</p>
<p>Reviewed cost #<?= (int)$item['approved_source_cost_id'] ?> ·
PHP <?= h((string)$item['approved_source_unit_cost']) ?> per unit ·
<?= h((string)$item['approved_source_supplier']) ?> ·
<?= h((string)$item['approved_source_reference']) ?></p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="loss_case_id" value="<?= (int)$item['loss_case_id'] ?>">
<input type="hidden" name="cost_record_id" value="<?= (int)$item['approved_source_cost_id'] ?>">
<input type="hidden" name="expected" value="<?= h($item['outstanding']) ?>">
<label>Reason this recorded purchase cost applies
<textarea name="reason" required minlength="20" maxlength="500" rows="3"
placeholder="Explain how the verified invoice relates to this exact lost part."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I confirm this exact acquisition-cost record matches the physical loss, not a sale price.</label>
<button name="action" value="propose-valuation">Submit verified-cost valuation</button>
</form></details>
<?php else: ?>
<p>No approved source-branch cost is recorded for this SKU.
Use the <a href="acquisition_costs.php?branch=<?= (int)$item['from_branch_id'] ?>">
purchase-cost register</a> before preparing an attested financial estimate.</p>
<?php endif; ?>
<details><summary>Propose a PHP purchase-cost valuation (manual evidence fallback)</summary>
<p>Use the verified supplier acquisition cost, not the customer-facing quote price.
For recovered parts, enter a new revision for the <strong>current</strong> unreceived quantity.
Only an independent origin-branch ADMIN can approve it. A zero outstanding quantity
can be valued at PHP 0.00 using a documented unit cost.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="loss_case_id" value="<?= (int)$item['loss_case_id'] ?>">
<input type="hidden" name="expected" value="<?= h($item['outstanding']) ?>">
<label>Verified acquisition cost per part (PHP)
<input type="number" name="unit_cost" required min="0.01" max="1000000"
step="0.01" placeholder="2750.00"></label>
<label>Documented cost source<select name="cost_basis" required>
<option value="">Choose original purchase-cost evidence</option>
<option value="SUPPLIER_INVOICE">Supplier invoice</option>
<option value="PURCHASE_RECEIPT">Purchase receipt</option>
<option value="OTHER_VERIFIED">Other independently verified evidence</option>

</select></label>
<label>Cost document reference
<input name="cost_reference" minlength="8" maxlength="160" required
placeholder="Supplier invoice INV-2026-001"></label>
<label>Purchase-cost explanation<textarea name="reason" minlength="20" maxlength="500"
rows="3" required placeholder="Explain part identity, invoice source, acquisition cost and any reconciliation."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I verified the acquisition-cost source, and this is not a financial posting.</label>
<button name="action" value="propose-valuation" class="secondary-btn">Submit monetary valuation</button>
</form></details>
<?php elseif ($valuationStatus==='PENDING' && $isRecipient): ?>
<p>A monetary valuation is pending independent source-branch administrator review.</p>
<?php elseif ($hasPendingRecovery && $isRecipient): ?>
<p>Resolve physically held recovery stock before preparing or revising the monetary valuation.</p>
<?php endif; ?>
<?php if ($canDecideValuation): ?>
<details><summary>Independently approve PHP loss valuation</summary>
<p>Recheck supplier cost evidence, the original approved loss and all recorded recoveries.
Approval only certifies this monetary estimate; it does not debit cash or post an accounting journal.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="valuation_id" value="<?= (int)$item['valuation_id'] ?>">
<label>Financial review<select name="decision" required>
<option value="reject">Reject — cost or physical count cannot be verified</option>
<option value="approve">Approve — verified PHP acquisition-cost exposure</option>
</select></label>
<label>Independent verification record<textarea name="reason" rows="3"
minlength="20" maxlength="500" required
placeholder="Record invoice or source review, physical count, exclusions and decision."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I am an independent source-branch administrator and checked the cost and physical-loss evidence.</label>
<button name="action" value="decide-valuation">Record financial review</button>
</form></details>
<?php elseif ($isOrigin && $valuationStatus==='PENDING'): ?>
<p>Awaiting a separate eligible source-branch administrator for financial review.</p>
<?php endif; ?>
</section>
<?php endif; ?>
<?php if ($item['recovery_id']): ?>
<p><strong>Recovery #<?= (int)$item['recovery_id'] ?> · <?= h($recoveryStatus) ?></strong>
 · Quantity <?= h((string)$item['recovery_quantity']) ?></p>
<p>Recovery inspection: <?= h($item['recovery_evidence_reference']) ?> ·
<?= h($item['recovery_report_reason']) ?></p>
<?php if ($item['recovery_review_reason']): ?>
<p>Independent recovery decision: <?= h($item['recovery_review_reason']) ?></p>
<?php endif; ?>
<?php if ($recoveryFiles): ?>
<p>Private recovery evidence:
<?php foreach ($recoveryFiles as $file): ?>
<a href="stock_evidence.php?id=<?= (int)$file['evidence_id'] ?>">
<?= h($file['original_filename']) ?></a>
<?php endforeach; ?></p>
<?php endif; ?>
<?php if ($recoveryStatus==='PENDING' && count($recoveryFiles)<6): ?>
<details><summary>Attach private recovery evidence</summary>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="case_type" value="RECOVERY">
<input type="hidden" name="case_id" value="<?= (int)$item['recovery_id'] ?>">
<label>Recovery inspection photo or PDF (maximum 5 MB)
<input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required></label>
<button name="action" value="attach-evidence" class="secondary-btn">Attach recovery evidence</button>
</form></details>
<?php endif; ?>
<?php endif; ?>
<?php if ($lossStatus==='PENDING'): ?>
<p>Physical receiving is suspended while independent loss review is pending. The original
dispatch and receipt quantities remain unchanged.</p>
<?php elseif ($item['item_status']==='LOSS_CLOSED'): ?>
<p>Approved operational loss. Any unreceived quantity was not added to either branch.
Any physical recovery must be held and independently approved before usable stock is credited.</p>
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
<?php if ($canReportRecovery): ?>
<details><summary>Report parts recovered after approved loss</summary>
<p>Physically found stock must be isolated, identified and inspected; it stays
unavailable until an independent manager approves release. The original loss
and dispatch records remain unchanged. Report only the quantity found now.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="loss_case_id" value="<?= (int)$item['loss_case_id'] ?>">
<input type="hidden" name="expected" value="<?= h((string)$item['received_quantity']) ?>">
<label>Physically recovered quantity
<input name="quantity" type="number" min="0.001" step="0.001" required
max="<?= h($item['outstanding']) ?>" value="<?= h($item['outstanding']) ?>"></label>
<label>Private inspection reference
<input name="evidence_reference" required minlength="8" maxlength="160"
placeholder="SIGNED-RECOVERY-CASE-012"></label>
<label>Recovered condition and holding location
<textarea name="reason" required minlength="20" maxlength="500" rows="3"
placeholder="Where the missing parts were found, who inspected them and where they are held."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I counted and isolated these recovered parts outside usable stock pending independent approval.</label>
<button name="action" value="report-recovery">Hold and report recovered parts</button>
</form>
</details>
<?php endif; ?>
<?php if ($canDecideRecovery): ?>
<details><summary>Independently review recovered parts</summary>
<p>Verify the original loss, physically recovered items, quantity and inspection reference.
Approve only complete, usable items; reject any damaged or unverified stock.
Approval credits only the inspected amount to the receiving branch.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="recovery_id" value="<?= (int)$item['recovery_id'] ?>">
<label>Decision<select name="decision" required>
<option value="reject">Reject — do not credit stock</option>
<option value="approve">Approve inspected recovery — credit receiving shelf</option>
</select></label>
<label>Independent physical verification
<textarea name="reason" required minlength="20" maxlength="500" rows="3"
placeholder="State inspected count, part condition, evidence reference and acceptance decision."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I independently verified the usable parts and approve the recorded stock change.</label>
<button name="action" value="decide-recovery">Record recovery decision</button>
</form>
</details>
<?php elseif ($isOrigin && $recoveryStatus==='PENDING'): ?>
<p>Awaiting a different source-branch manager's recovered-stock review.</p>
<?php endif; ?>
<?php if ($recoveryStatus==='PENDING'): ?>
<p>Recovered parts are held outside usable stock; approval remains pending.</p>
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
