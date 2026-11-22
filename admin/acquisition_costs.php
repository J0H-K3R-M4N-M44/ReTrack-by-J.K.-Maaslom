<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/inventory/acquisition_costs.php';
startAppSession();
$user=requireRoles(['ADMIN','MANAGER']);
header('Cache-Control: no-store, private');
$pdo=getPDO();
$error='';
$notice=$_SESSION['cost_register_notice'] ?? '';
unset($_SESSION['cost_register_notice']);
$branches=intakeBranches($pdo,(int)$user['user_id']);
$allowed=array_map('intval',array_column($branches,'branch_id'));
$branch=(int)requestText($_GET,'branch');
if (!$branch || !in_array($branch,$allowed,true)) {
    $branch=(int)($allowed[0]??0);
}
$q=mb_substr(trim(requestText($_GET,'q')),0,60);
$page=max(1,(int)requestText($_GET,'page','1'));
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrfIsValid(requestText($_POST,'csrf_token'))) {
            unavailablePage('The cost review form expired. Open the page again.',403);
        }
        $branch=(int)requestText($_POST,'branch');
        if (!in_array($branch,$allowed,true)) {
            throw new DomainException('Choose your assigned branch.');
        }
        $action=requestText($_POST,'action');
        if ($action==='propose-cost') {
            $sku=trim(requestText($_POST,'sku'));
            $part=stockRun($pdo,'SELECT part_id FROM parts WHERE sku=? AND is_active=1',
                [$sku])->fetchColumn();
            if (!$part) throw new DomainException('Choose an active catalog SKU.');
            partCostPropose($pdo,(int)$user['user_id'],$branch,(int)$part,
                requestText($_POST,'unit_cost'),requestText($_POST,'source_type'),
                requestText($_POST,'supplier_name'),requestText($_POST,'document_reference'),
                requestText($_POST,'document_date'),requestText($_POST,'reason'),
                requestText($_POST,'confirm')==='yes');
            $_SESSION['cost_register_notice']='Supplier cost submitted for independent administrator review. No stock or cash changed.';
        } elseif ($action==='decide-cost') {
            partCostDecide($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'record_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm')==='yes');
            $_SESSION['cost_register_notice']='Independent supplier-cost decision recorded without financial posting.';
        } else {
            throw new DomainException('Choose a valid supplier-cost action.');
        }
        header('Location: acquisition_costs.php?'.http_build_query(['branch'=>$branch,'q'=>$q,'page'=>1]),true,303);
        exit();
    }
} catch (DomainException $problem) {
    $error=$problem->getMessage();
} catch (Throwable $problem) {
    error_log('Acquisition cost register write: '.$problem->getMessage());
    $error='Supplier cost change could not be saved. No partial update was accepted.';
}
$desk=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
try {
    if ($branch>0) {
        $desk=partCostDesk($pdo,(int)$user['user_id'],$branch,$q,$page);
    }
} catch (Throwable $problem) {
    error_log('Supplier cost register read: '.$problem->getMessage());
    unavailablePage('Supplier purchase-cost records are unavailable. Check database upgrades.');
}
$workspaceUser=$user;
$currentSection='acquisition-costs';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Supplier acquisition costs | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
<link rel="stylesheet" href="../css/workspace.css"><link rel="stylesheet" href="../css/workspace-design.css">
<script src="../js/workspace-design.js" defer></script>
</head><body class="dashboard-body rt-layout">
<?php require __DIR__ . '/../views/workspace/navigation.php'; ?>
<main id="rt-main" class="staff-page workspace rt-content" tabindex="-1">
<header class="staff-heading rt-page-head"><div>
<p class="eyebrow">RepairTrack · Internal purchasing evidence</p>
<h1>Verified supplier acquisition costs</h1>
<p>Propose documented purchase costs per SKU and branch. Another assigned administrator
must verify the original supplier evidence before these costs may support loss valuations.
No purchase order, cash transfer or accounting entry is created.</p>
</div><a class="secondary-btn" href="parts_transfers.php?branch=<?= $branch ?>">Parts transfers</a></header>
<?php if ($notice!==''): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error!==''): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="dashboard-card rt-card"><h2>Branch &amp; document search</h2>
<form method="get" class="console-search">
<label>Assigned branch<select name="branch" required>
<?php foreach ($branches as $b): ?>
<option value="<?= (int)$b['branch_id'] ?>" <?= (int)$b['branch_id']===$branch?'selected':'' ?>>
<?= h($b['branch_name']) ?></option>
<?php endforeach; ?></select></label>
<label>SKU or document reference<input type="search" name="q" maxlength="60" value="<?= h($q) ?>"></label>
<button class="secondary-btn">Search costs</button>
</form></section>
<section class="dashboard-card rt-card"><h2>New supplier-cost evidence</h2>
<p>Record the acquisition cost from an actual invoice or receipt, not a retail price,
repair quote or guessed average. Keep the purchase document for independent inspection.</p>
<details><summary>Propose documented cost</summary>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<label>Exact catalog SKU<input name="sku" required maxlength="80" placeholder="SSD-256"></label>
<label>Acquisition cost per unit (PHP)<input type="number" name="unit_cost" required
min="0.01" max="1000000" step="0.01" placeholder="1250.25"></label>
<label>Purchase document<select name="source_type" required>
<option value="SUPPLIER_INVOICE">Supplier invoice</option>
<option value="PURCHASE_RECEIPT">Purchase receipt</option>
<option value="OTHER_VERIFIED">Other independently verified purchasing document</option>
</select></label>
<label>Supplier name<input name="supplier_name" minlength="2" maxlength="120" required></label>
<label>Document identifier<input name="document_reference" minlength="8" maxlength="160"
required placeholder="INVOICE-2026-0021"></label>
<label>Document date<input type="date" name="document_date" required max="<?= gmdate('Y-m-d') ?>"></label>
<label>Purchase-cost explanation<textarea name="reason" rows="3" minlength="20"
maxlength="500" required placeholder="Describe the exact part, quantity basis and supplier record."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I checked the supplier record and understand this proposal does not change stock or cash.</label>
<button name="action" value="propose-cost">Submit cost for review</button>
</form></details></section>
<section class="dashboard-card rt-card"><h2>Branch cost review register</h2>
<p><?= (int)$desk['total'] ?> records · 10 per page. Approved records are retained,
not silently overwritten. New evidence requires a separate proposal and review.</p>
<?php if (!$desk['rows']): ?><p>No supplier-cost records match this search.</p><?php endif; ?>
<?php foreach ($desk['rows'] as $cost): ?>
<article class="dashboard-card">
<h3>Cost record #<?= (int)$cost['cost_record_id'] ?> · <?= h($cost['part_name']) ?></h3>
<p><?= h($cost['sku']) ?> · <strong><?= h($cost['status']) ?></strong> ·
PHP <?= h((string)$cost['unit_cost_php']) ?> per unit</p>
<p><?= h($cost['supplier_name']) ?> · <?= h($cost['source_type']) ?> ·
<?= h($cost['document_reference']) ?> (<?= h((string)$cost['document_date']) ?>)</p>
<p><?= h($cost['explanation']) ?></p>
<?php if ($cost['review_reason']): ?><p>Independent review: <?= h($cost['review_reason']) ?></p><?php endif; ?>
<?php if ($cost['status']==='PENDING' && $user['role']==='ADMIN' &&
    (int)$cost['prepared_by_user_id']!==(int)$user['user_id']): ?>
<details><summary>Independently approve or reject supplier cost</summary>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="record_id" value="<?= (int)$cost['cost_record_id'] ?>">
<label>Decision<select name="decision" required>
<option value="reject">Reject — document/cost not substantiated</option>
<option value="approve">Approve verified acquisition cost</option>
</select></label>
<label>Independent purchasing-document verification
<textarea name="reason" required minlength="20" maxlength="500" rows="3"
placeholder="Explain what was checked against supplier documents."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I independently inspected the original supplier evidence and accept this cost basis.</label>
<button name="action" value="decide-cost">Record independent decision</button>
</form></details>
<?php elseif ($cost['status']==='PENDING'): ?>
<p>A different branch administrator must review this pending cost.</p>
<?php endif; ?>
</article>
<?php endforeach; ?>
<?php if ($desk['pages']>1): ?>
<nav class="list-pager" aria-label="Supplier cost pages">
<span>Page <?= (int)$desk['page'] ?> of <?= (int)$desk['pages'] ?></span>
<?php foreach (['Previous'=>-1,'Next'=>1] as $label=>$step):
$target=$desk['page']+$step;if($target<1||$target>$desk['pages']) continue; ?>
<a class="secondary-btn" href="?<?= h(http_build_query([
'branch'=>$branch,'q'=>$q,'page'=>$target])) ?>"><?= h($label) ?></a>
<?php endforeach; ?></nav>
<?php endif; ?>
</section>
</main></body></html>
