<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/inventory/stock_ops.php';
startAppSession();
$user = requireRoles(['ADMIN','MANAGER']);
header('Cache-Control: no-store, private');
$error = '';
$notice = $_SESSION['inventory_notice'] ?? '';
unset($_SESSION['inventory_notice']);
$pdo = getPDO();
$branches = intakeBranches($pdo, (int)$user['user_id']);
$allowed = array_map('intval',array_column($branches,'branch_id'));
$branch = (int) requestText($_GET,'branch');
if ($branch === 0 || !in_array($branch,$allowed,true)) {
    $branch = (int) ($allowed[0] ?? 0);
}
$q = mb_substr(trim(requestText($_GET,'q')),0,80);
$pageNumber = max(1,(int)requestText($_GET,'page','1'));
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST,'csrf_token'))) {
            unavailablePage('The stock form expired. Reopen Inventory and try again.',403);
        }
        $selected = (int)requestText($_POST,'branch');
        if (!in_array($selected,$allowed,true)) {
            throw new DomainException('Choose a branch assigned to your account.');
        }
        $branch = $selected;
        $action = requestText($_POST,'action');
        if ($action === 'quarantine-decision') {
            stockDecideQuarantine($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'quarantine_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm') === 'yes');
            $_SESSION['inventory_notice'] = 'Independent quarantine disposition recorded.';
        } elseif ($action === 'count-decision') {
            stockDecideCount($pdo,(int)$user['user_id'],
                (int)requestText($_POST,'review_id'),requestText($_POST,'decision'),
                requestText($_POST,'reason'),requestText($_POST,'confirm') === 'yes');
            $_SESSION['inventory_notice'] = 'Count review decision recorded.';
        } else {
            $result = stockShelfAction($pdo,(int)$user['user_id'],$selected,
                (int)requestText($_POST,'part'),requestText($_POST,'expected_on_hand'),
                (int)requestText($_POST,'movement_id'),$action,requestText($_POST,'quantity'),
                requestText($_POST,'reason'),requestText($_POST,'confirm') === 'yes');
            $_SESSION['inventory_notice'] = $result === 'pending'
                ? 'Large count difference queued for approval by another branch manager. Stock was not changed.'
                : ($action === 'quarantine'
                    ? 'Damaged stock isolated and removed from usable inventory.'
                    : 'Verified shelf count recorded in the inventory ledger.');
        }
        $redirect = http_build_query([
            'branch'=>$selected,'q'=>mb_substr(requestText($_POST,'q'),0,80),
            'page'=>max(1,(int)requestText($_POST,'page','1')),
        ]);
        header('Location: inventory.php?' . $redirect,true,303);
        exit();
    }
} catch (DomainException $problem) {
    $error = $problem->getMessage();
} catch (Throwable $problem) {
    error_log('RepairTrack inventory write: ' . $problem->getMessage());
    $error = 'Inventory could not be updated. No partial stock movement was saved.';
}
$inventory = ['rows'=>[], 'page'=>1,'pages'=>1,'total'=>0];
$pendingWork = ['quarantines'=>[],'counts'=>[]];
try {
    if ($branch > 0) {
        $inventory = stockDesk($pdo,(int)$user['user_id'],$branch,$q,$pageNumber);
        $pendingWork = stockPendingWork($pdo,(int)$user['user_id'],$branch);
    }
} catch (Throwable $problem) {
    error_log('RepairTrack inventory read: ' . $problem->getMessage());
    unavailablePage('Inventory is unavailable. Check the quarantine migration and try again.');
}
$workspaceUser = $user;
$currentSection = 'inventory';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Branch inventory | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
<link rel="stylesheet" href="../css/workspace.css"><link rel="stylesheet" href="../css/workspace-design.css">
<script src="../js/workspace-design.js" defer></script>
</head><body class="dashboard-body rt-layout">
<?php require __DIR__ . '/../views/workspace/navigation.php'; ?>
<main id="rt-main" class="staff-page workspace rt-content" tabindex="-1">
<header class="staff-heading rt-page-head"><div>
<p class="eyebrow">RepairTrack · Controlled stock operations</p>
<h1>Branch inventory</h1><p>Quarantine damaged parts and reconcile physical shelf counts without using another ticket.</p>
</div><a class="secondary-btn" href="staff_home.php">Back to workspace</a></header>
<?php if ($notice !== ''): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="dashboard-card rt-card"><h2>Inventory filters</h2>
<form method="get" class="console-search">
<label>Assigned branch<select name="branch" required>
<?php foreach ($branches as $b): ?>
<option value="<?= (int)$b['branch_id'] ?>" <?= (int)$b['branch_id']===$branch?'selected':'' ?>>
<?= h($b['branch_name']) ?></option>
<?php endforeach; ?></select></label>
<label>Part name or SKU<input name="q" type="search" maxlength="80" value="<?= h($q) ?>"></label>
<button class="secondary-btn">Show parts</button>
</form><p>On hand is usable stock. Available is on hand minus active repair reservations.
Quarantined parts stay outside usable stock until a different manager approves release or scrap.
Count discrepancies of at least 5.000 units require a separate reviewer and do not change stock until approved.</p>
</section>
<section class="dashboard-card rt-card"><h2>Pending independent review</h2>
<p>The same person cannot create and approve a quarantine decision or a significant count correction.
Only your assigned branch's records appear here. Review the physical evidence before approving.</p>
<?php if (!$pendingWork['quarantines'] && !$pendingWork['counts']): ?>
<p>No pending quarantine decisions or significant count reviews on this page.</p>
<?php endif; ?>
<?php foreach ($pendingWork['quarantines'] as $item): ?>
<article class="dashboard-card">
<h3>Quarantine #<?= (int)$item['quarantine_id'] ?> · <?= h($item['part_name']) ?></h3>
<p><?= h((string)$item['quantity']) ?> isolated · <?= h($item['source_type']) ?> ·
 recorded by <?= h($item['recorded_by']) ?></p>
<p><?= h($item['reason']) ?></p>
<?php if ((int)$item['recorded_by_user_id']===(int)$user['user_id']): ?>
<p>A different manager or administrator must decide whether this part is reusable or must be scrapped.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="quarantine_id" value="<?= (int)$item['quarantine_id'] ?>">
<label>Disposition<select name="decision" required>
<option value="SCRAP">Scrap permanently — no stock added</option>
<option value="RELEASE">Reinspect and release entire quantity to usable inventory</option>
</select></label>
<label>Inspection or disposal evidence
<textarea name="reason" required minlength="10" maxlength="500" rows="2"
placeholder="Explain physical verification and where the quarantined part went."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I independently verified the entire quarantined quantity and accept responsibility for this final decision.</label>
<button class="secondary-btn" name="action" value="quarantine-decision">Record disposition</button>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
<?php foreach ($pendingWork['counts'] as $review): ?>
<article class="dashboard-card">
<h3>Count request #<?= (int)$review['stock_count_review_id'] ?> · <?= h($review['part_name']) ?></h3>
<p>Requested by <?= h($review['requested_by']) ?> · Usable count:
<strong><?= h((string)$review['expected_on_hand']) ?></strong> → 
<strong><?= h((string)$review['target_on_hand']) ?></strong>
 · reserved <?= h((string)$review['expected_reserved']) ?></p>
<p><?= h($review['reason']) ?></p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="review_id" value="<?= (int)$review['stock_count_review_id'] ?>">
<?php if ((int)$review['requested_by_user_id']===(int)$user['user_id']): ?>
<p>You cannot approve your own request; you can cancel it to make a fresh count.</p>
<input type="hidden" name="decision" value="cancel">
<?php else: ?>
<label>Decision<select name="decision" required>
<option value="reject">Reject count — make no stock movement</option>
<option value="approve">Approve verified count — adjust usable stock</option>
</select></label>
<?php endif; ?>
<label>Review reason<textarea name="reason" required minlength="10" maxlength="500" rows="2"
placeholder="Describe independent recount, rejection or cancellation."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I verified this decision against the latest branch stock and reservation count.</label>
<button class="secondary-btn" name="action" value="count-decision">Record count decision</button>
</form>
</article>
<?php endforeach; ?>
</section>
<section class="dashboard-card rt-card"><h2>Branch stock</h2>
<p><?= (int)$inventory['total'] ?> stock records · 10 per page</p>
<?php if (!$inventory['rows']): ?><p>No stock records for this branch or search.</p><?php endif; ?>
<?php foreach ($inventory['rows'] as $part):
$available = $part['available'];
$partId = (int)$part['part_id'];
$stock = h((string)$part['quantity_on_hand']);
$movement = (int)$part['movement_id'];
?>
<article class="dashboard-card">
<h3><?= h($part['part_name']) ?> <small><?= h($part['sku']) ?></small></h3>
<p><strong><?= $stock ?></strong> on hand · <strong><?= h($available) ?></strong> available ·
<?= h((string)$part['reserved']) ?> reserved ·
<?= h((string)$part['quarantined']) ?> quarantined</p>
<?php if (!(int)$part['is_active']): ?><p>Inactive catalog part — existing stock can still be audited.</p><?php endif; ?>
<details><summary>Quarantine damaged stock</summary>
<p>Remove only physically identified damaged or unsellable items from usable stock.
Reserved units may not be quarantined. Quarantine is not a return-to-stock action.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="part" value="<?= $partId ?>">
<input type="hidden" name="expected_on_hand" value="<?= $stock ?>">
<input type="hidden" name="movement_id" value="<?= $movement ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="page" value="<?= (int)$inventory['page'] ?>">
<label>Damaged quantity<input type="number" name="quantity" min="0.001" step="0.001" required></label>
<label>Damage reason<textarea name="reason" rows="2" minlength="10" maxlength="500" required
placeholder="Describe the physical defect and where the part is isolated."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I counted, identified, and physically separated these parts from usable stock.</label>
<button class="secondary-btn" name="action" value="quarantine">Quarantine damaged items</button>
</form></details>
<details><summary>Record a verified shelf count</summary>
<p>Enter the physically verified <strong>usable on-hand count</strong>, excluding quarantine.
A count lower than existing reservations cannot be recorded. A discrepancy of at least 5.000 units
creates a pending review instead of changing stock immediately.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="branch" value="<?= $branch ?>">
<input type="hidden" name="part" value="<?= $partId ?>">
<input type="hidden" name="expected_on_hand" value="<?= $stock ?>">
<input type="hidden" name="movement_id" value="<?= $movement ?>">
<input type="hidden" name="q" value="<?= h($q) ?>">
<input type="hidden" name="page" value="<?= (int)$inventory['page'] ?>">
<label>Verified usable quantity<input type="number" name="quantity" min="0" step="0.001"
value="<?= $stock ?>" required></label>
<label>Count discrepancy reason<textarea name="reason" rows="2" minlength="10" maxlength="500" required
placeholder="Describe the recount and the reason for the difference."></textarea></label>
<label><input type="checkbox" name="confirm" value="yes" required>
I verified the usable shelf count and understand it creates a permanent adjustment record.</label>
<button class="secondary-btn" name="action" value="count">Save verified count</button>
</form></details>
</article>
<?php endforeach; ?>
<?php if ($inventory['pages'] > 1): ?><nav class="list-pager" aria-label="Stock pages">
<span>Page <?= (int)$inventory['page'] ?> of <?= (int)$inventory['pages'] ?></span>
<?php foreach (['Previous'=>-1,'Next'=>1] as $label=>$offset):
$target = $inventory['page']+$offset;
if ($target<1 || $target>$inventory['pages']) continue; ?>
<a class="secondary-btn" href="?<?= h(http_build_query(['branch'=>$branch,'q'=>$q,'page'=>$target])) ?>"><?= h($label) ?></a>
<?php endforeach; ?></nav><?php endif; ?>
</section>
</main></body></html>
