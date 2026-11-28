<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Repair part requests | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css">
<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/staff.css">
<link rel="stylesheet" href="../css/console.css">
<link rel="stylesheet" href="../css/workspace.css">
</head>
<body class="dashboard-body">
<a class="skip-link" href="#part-main">Skip to part requests</a>
<main class="staff-page workspace" id="part-main">
<header class="staff-heading">
<div><p class="eyebrow">Internal repair support</p><h1>Repair part requests</h1>
<p>Record parts needed for a repair, and follow up with an authorized branch manager. This does not buy, hold or install parts.</p></div>
<a class="secondary-btn" href="staff_home.php">Back to staff workspace</a>
</header>
<?php if ($notice): ?><p role="status" class="staff-notice"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="field-error"><?= h($error) ?> <a href="part_requests.php">Reload requests</a></p><?php endif; ?>
<?php if ($device): ?>
<section class="dashboard-card"><h2>Repair context</h2>
<p><?= h($device['item_code']) ?> · <?= h($device['branch_name']) ?> · <?= h(dashboardStatusLabel($device['repair_status'])) ?></p>
<p><a href="device.php?id=<?= (int) $device['ticket_device_id'] ?>">Return to repair</a></p>
</section>
<?php endif; ?>
<?php if (!$error && $device && !$request && $user['role'] === 'TECHNICIAN' && partRequestOpenDevice($device)): ?>
<section class="dashboard-card"><h2><?= $review ? 'Review part request' : 'Request a part for this repair' ?></h2>
<?php if ($review): ?>
<dl class="staff-review">
<div><dt>Part</dt><dd><?= h($values['part_description']) ?></dd></div>
<div><dt>Quantity</dt><dd><?= h($values['quantity']) ?></dd></div>
<div><dt>Why needed</dt><dd><?= h($values['reason']) ?></dd></div>
</dl>
<?php else: ?><p>Enter the part need and reason. Approval of an estimate, reservation and stock movements remain separate steps.</p><?php endif; ?>
<form method="post" class="quote-form">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="form_key" value="<?= h($key) ?>">
<?php if (!$review): ?>
<label for="part-description">Part name or description
<input id="part-description" name="part_description" maxlength="160" minlength="3" required value="<?= h($values['part_description']) ?>"></label>
<label for="part-quantity">Quantity
<input id="part-quantity" name="quantity" inputmode="decimal" placeholder="1" required value="<?= h($values['quantity']) ?>"></label>
<label for="part-reason">Reason for requesting
<textarea id="part-reason" name="reason" rows="3" minlength="10" maxlength="500" required><?= h($values['reason']) ?></textarea></label>
<div class="staff-actions"><button class="logout-btn" name="intent" value="review">Review request</button></div>
<?php else: ?><div class="staff-actions">
<button class="secondary-btn" name="intent" value="edit">Edit details</button>
<button class="logout-btn" name="intent" value="confirm">Submit internal request</button></div><?php endif; ?>
</form></section>
<?php endif; ?>
<?php if (!$error && $request): ?>
<section class="dashboard-card"><h2>Request #<?= (int) $request['part_request_id'] ?></h2>
<dl class="staff-review">
<div><dt>Requested part</dt><dd><?= h($request['part_description']) ?></dd></div>
<div><dt>Quantity</dt><dd><?= h($request['quantity']) ?></dd></div>
<div><dt>Reason</dt><dd><?= h($request['reason']) ?></dd></div>
<div><dt>Status</dt><dd><?= h($request['status']) ?></dd></div>
<div><dt>Review note</dt><dd><?= h($request['review_note'] ?: 'Not yet reviewed') ?></dd></div>
<div><dt>Resolution note</dt><dd><?= h($request['resolution_note'] ?: 'Not resolved') ?></dd></div>
</dl>
<?php if (partRequestCanReview($user) && in_array($request['status'], ['OPEN', 'ACKNOWLEDGED'], true)): ?>
<h3><?= $review ? 'Confirm request decision' : 'Manager decision' ?></h3>
<form method="post" class="quote-form">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="form_key" value="<?= h($key) ?>">
<?php if ($review): ?>
<p>Action: <strong><?= h(ucfirst($values['action'])) ?></strong></p>
<p>Reason: <?= h($values['note'] ?: 'No additional note') ?></p>
<p>This decision does not change inventory, billing, custody, customer consent or repair status.</p>
<div class="staff-actions">
<button class="secondary-btn" name="intent" value="edit">Edit decision</button>
<button class="logout-btn" name="intent" value="confirm">Record decision</button>
</div>
<?php else: ?>
<label for="part-action">Decision
<select id="part-action" name="action" required>
<?php if ($request['status'] === 'OPEN'): ?>
<option value="acknowledge">Acknowledge — manager informed</option>
<option value="decline">Decline — request not supported</option>
<?php else: ?><option value="resolve">Resolve — follow-up completed</option><?php endif; ?>
</select></label>
<label for="part-note">Manager note (required for decline or resolution)
<textarea id="part-note" name="note" rows="3" maxlength="500"><?= h($values['note'] ?? '') ?></textarea></label>
<div class="staff-actions"><button class="logout-btn" name="intent" value="review">Review decision</button></div>
<?php endif; ?>
</form>
<?php endif; ?>
</section>
<?php endif; ?>
<section class="dashboard-card"><h2><?= $user['role'] === 'TECHNICIAN' ? 'My assigned repair part requests' : 'Assigned-branch part requests' ?></h2>
<div class="staff-table-wrap"><table class="staff-table">
<thead><tr><th scope="col">Device</th><th scope="col">Branch</th><th scope="col">Requested part</th><th scope="col">Quantity</th><th scope="col">State</th><th scope="col">Action</th></tr></thead>
<tbody>
<?php foreach ($page['rows'] as $row): ?>
<tr><td><?= h($row['item_code']) ?></td><td><?= h($row['branch_name']) ?></td><td><?= h($row['part_description']) ?></td>
<td><?= h($row['quantity']) ?></td><td><?= h($row['status']) ?></td>
<td><a href="part_requests.php?request_id=<?= (int) $row['part_request_id'] ?>">Open request #<?= (int) $row['part_request_id'] ?></a></td></tr>
<?php endforeach; ?>
<?php if (!$page['rows']): ?><tr><td colspan="6">No requests in this view.</td></tr><?php endif; ?>
</tbody></table></div>
<nav class="staff-actions" aria-label="Request pages">
<span>Page <?= (int) $page['page'] ?> of <?= (int) $page['pages'] ?> · <?= (int) $page['total'] ?> requests</span>
<?php
$query = $deviceId && !$request ? '&device_id=' . (int) $deviceId : '';
?>
<?php if ($page['page'] > 1): ?><a class="secondary-btn" href="part_requests.php?page=<?= (int) $page['page'] - 1 ?><?= h($query) ?>">Previous</a><?php endif; ?>
<?php if ($page['page'] < $page['pages']): ?><a class="secondary-btn" href="part_requests.php?page=<?= (int) $page['page'] + 1 ?><?= h($query) ?>">Next</a><?php endif; ?>
</nav>
</section>
</main>
</body></html>
