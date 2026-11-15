<?php
// Every action uses the same CSRF and one-use review state.
function quoteFormFields(string $formKey, string $action = ''): void
{
    echo '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
    echo '<input type="hidden" name="form_key" value="' . h($formKey) . '">';
    if ($action !== '') {
        echo '<input type="hidden" name="action" value="' . h($action) . '">';
    }
}
$deviceUrl = 'device.php?' . http_build_query(['id' => $id, 'return' => $returnQuery]);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $error ? 'Error · ' : '' ?>Repair estimate | RepairTrack</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css"><link rel="stylesheet" href="../css/workspace.css">
</head><body class="dashboard-body">
<a class="skip-link" href="#quote-main">Skip to estimate</a>
<main class="staff-page workspace" id="quote-main">
<header class="staff-heading"><div><p class="eyebrow"><?= h($device['branch_name']) ?> · <?= h(
     $device['item_code'],
 ) ?></p><h1>Repair estimate</h1><p><?= h(dashboardDeviceLabel($device)) ?> · <?= h(
     $device['ticket_code'],
 ) ?></p></div><a class="secondary-btn" href="<?= h($deviceUrl) ?>">Back to device</a></header>
<?php
$activeStep = $expired ? 1 : match ($latest['quote_status'] ?? '') {
    'DRAFT' => 2,
    'AWAITING_CUSTOMER' => 3,
    'APPROVED' => 4,
    default => 1,
};
?>
<ol class="quote-steps" aria-label="Estimate workflow">
<?php foreach ([1 => 'Prepare draft', 2 => 'Share estimate', 3 => 'Record response', 4 => 'Start repair'] as $step => $label): ?>
    <li <?= $step === $activeStep ? 'aria-current="step"' : '' ?>><?= $step ?>. <?= h($label) ?></li>
<?php endforeach; ?>
</ol>
<?php if ($saved): ?><p class="staff-notice" role="status"><?= h($saved) ?></p><?php endif; ?>
<?php if (
    $error
): ?><div class="field-error" role="alert"><strong>Check this before continuing</strong><p><?= h(
    $error,
) ?></p><a href="?id=<?= $id ?>&amp;return=<?= h(
    urlencode($returnQuery),
) ?>">Reload current quote</a></div><?php endif; ?>
<section class="dashboard-card">
<div class="section-heading"><h2>Current estimate</h2><span class="workspace-status"><?= h(
    $expired
        ? 'Response period expired'
        : str_replace('_', ' ', $latest['quote_status'] ?? 'NOT PREPARED'),
) ?></span></div>
<?php if (
    !$latest
): ?><p>No estimate yet. Begin diagnosis, then describe the work and proposed charges.</p><?php else: ?>
<p>Revision <?= (int) $latest['version_no'] ?> · Prepared <?= h(workspaceDate($latest['created_at'])) ?></p>
<?php if ($latest['expires_at']): ?>
<p>Response due <?= h(workspaceDate($latest['expires_at'])) ?></p>
<?php endif; ?>
<dl class="quote-breakdown"><?php foreach ($items as $item): ?><div><dt><?= h(
    $item['description'],
) ?><small><?= h($item['item_type']) ?> · Qty <?= h($item['quantity']) ?> × <?= h(
     $latest['currency'],
 ) ?> <?= h($item['unit_price']) ?></small></dt><dd><?= h($latest['currency']) ?> <?= h(
     number_format((float) $item['line_total'], 2),
 ) ?></dd></div><?php endforeach; ?></dl>
<p class="quote-total">Estimated total <strong><?= h($latest['currency']) ?> <?= h(
     number_format((float) $latest['total_amount'], 2),
 ) ?></strong></p><p>This estimate is not a bill or payment receipt. Parts charges do not reserve stock.</p>
<?php endif; ?></section>
<?php if (!$canChange): ?>
<section class="dashboard-card"><h2>Estimate is read-only</h2><p>Start diagnosis before preparing a quote. Once repair has started or the ticket is closed, estimates stay read-only; changes need a separate change-order workflow.</p></section>
<?php elseif ($review): ?>
<section class="dashboard-card"><h2>Review before saving</h2>
<?php if ($values['action'] === 'draft'):
    $preview = quoteValues($values); ?>
<p>This creates a new draft revision. It replaces the current estimate and requires fresh approval. Earlier decisions stay in history.</p>
<dl class="quote-breakdown"><?php foreach ($preview['items'] as $item):
    if ($item['description'] === '') {
        continue;
    } ?><div><dt><?= h($item['description']) ?></dt><dd>PHP <?= h(
    $item['amount'],
) ?></dd></div><?php
endforeach; ?></dl><p class="quote-total">Estimated total <strong>PHP <?= h(
    $preview['total'],
) ?></strong></p>
<?php
elseif ($values['action'] === 'share'): ?><p>Mark revision <?= (int) $latest[
    'version_no'
] ?> as shared for <?= h($latest['currency']) ?> <?= h(
     $latest['total_amount'],
 ) ?>. The response period is seven days. This does not send a message.</p>
<?php else: ?><dl class="staff-review"><div><dt>Response</dt><dd><?= h(
    $values['decision'],
) ?></dd></div><div><dt>Customer / representative</dt><dd><?= h(
    $values['customer_name'],
) ?></dd></div><div><dt>Conversation reference</dt><dd><?= h(
    $values['reference'],
) ?></dd></div><div><dt>Estimate being decided</dt><dd>Revision <?= (int) $latest[
    'version_no'
] ?> · <?= h($latest['currency']) ?> <?= h(
     $latest['total_amount'],
 ) ?></dd></div></dl><p>A rejection does not cancel the repair or release the device.</p><?php endif; ?>
<form method="post"><?php quoteFormFields(
    $formKey,
); ?><div class="staff-actions"><button class="secondary-btn" name="intent" value="edit">Go back</button><button class="logout-btn" name="intent" value="save">Confirm and save</button></div></form></section>
<?php else: ?>
<?php if (
    $latest &&
    $latest['quote_status'] === 'APPROVED'
): ?><section class="dashboard-card next-action"><h2>Approved — ready for the technician</h2><p>The assigned technician can start repair from the device screen when the device is at the branch.</p><a class="secondary-btn" href="<?= h(
    $deviceUrl,
) ?>">Open repair progress</a></section><?php endif; ?>
<?php if (
    $latest &&
    $latest['quote_status'] === 'DRAFT' &&
    $canPrepare
): ?><section class="dashboard-card"><h2>Share this estimate</h2><p>Explain the work and total to the customer in person or through your branch's communication process.</p><form method="post"><?php quoteFormFields(
    $formKey,
    'share',
); ?><label class="confirmation-check"><input type="checkbox" name="confirm" value="1" required> I shared this exact revision and total with the customer.</label><button class="logout-btn" name="intent" value="review">Review sharing</button></form></section><?php endif; ?>
<?php if (
    $latest &&
    $latest['quote_status'] === 'AWAITING_CUSTOMER' &&
    !$expired
): ?><section class="dashboard-card"><h2>Customer response</h2>
<?php if (
    !$canRecord
): ?><p>An administrator, manager or intake agent must record the customer's response. Technicians cannot approve their own estimates.</p><?php else: ?>
<form method="post" class="quote-form"><?php quoteFormFields($formKey, 'decision'); ?>
<label>Response<select name="decision" required><option value="">Choose response</option><?php foreach (
    ['APPROVED' => 'Customer approved', 'DECLINED' => 'Customer declined']
    as $value => $label
): ?><option value="<?= $value ?>" <?= ($values['decision'] ?? '') === $value
    ? 'selected'
    : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Customer or authorized representative<input name="customer_name" maxlength="160" required value="<?= h(
    $values['customer_name'] ?? '',
) ?>"></label>
<label class="staff-wide">Conversation reference<textarea name="reference" maxlength="1000" rows="2" required placeholder="Phone call or counter conversation; include date and agreed revision."><?= h(
    $values['reference'] ?? '',
) ?></textarea></label>
<label class="confirmation-check staff-wide"><input type="checkbox" name="confirm" value="1" required> I received this response from the customer and checked the revision and total.</label><div class="staff-wide"><button class="logout-btn" name="intent" value="review">Review response</button></div></form>
<?php endif; ?></section><?php endif; ?>
<?php if ($canPrepare): ?><details class="dashboard-card quote-editor" <?= !$latest ||
($values['action'] ?? '') === 'draft'
    ? 'open'
    : '' ?>><summary><?= $latest
    ? 'Prepare a revised estimate'
    : 'Prepare estimate' ?></summary><p>All amounts are PHP. Describe the diagnosis and work briefly. Optional parts and other charges are combined estimates, not stock reservations. A new draft needs fresh customer approval.</p>
<form method="post" class="quote-form"><?php quoteFormFields($formKey, 'draft'); ?>
<?php foreach (
    [
        'labor' => 'Diagnosis / work and labor',
        'parts' => 'Parts estimate (optional)',
        'other' => 'Other charge (optional)',
    ]
    as $key => $label
): ?>
<label><?= h(
    $label,
) ?><textarea name="<?= $key ?>_description" maxlength="300" rows="2" <?= $key === 'labor'
    ? 'required'
    : '' ?>><?= h($values[$key . '_description'] ?? '') ?></textarea></label><label><?= ucfirst(
    $key,
) ?> amount (PHP)<input name="<?= $key ?>_amount" type="number" min="0" max="9999999.99" step="0.01" inputmode="decimal" required value="<?= h(
    $values[$key . '_amount'] ?? '0.00',
) ?>"></label>
<?php endforeach; ?><div class="staff-wide"><button class="logout-btn" name="intent" value="review">Review draft</button></div></form></details><?php endif; ?>
<?php endif; ?>
<section class="dashboard-card"><h2>Revision history</h2><p>Showing <?= $history[
    'from'
] ?>–<?= $history['to'] ?> of <?= $history[
     'total'
 ] ?> · 10 per page. Earlier responses stay recorded.</p><p class="table-scroll-hint">Swipe or scroll sideways to see all columns.</p>
<?php if (
    !$history['rows']
): ?><p>No revisions yet.</p><?php else: ?><div class="staff-table-wrap" tabindex="0" aria-label="Quote revisions, scroll sideways for more columns"><table class="staff-table workspace-table"><thead><tr><th>Revision</th><th>Status</th><th>Recorded response</th><th class="numeric">Estimate</th><th>Prepared (PH)</th></tr></thead><tbody><?php foreach (
    $history['rows']
    as $row
): ?><tr><td><?= (int) $row['version_no'] ?></td><td><?= h(
    str_replace('_', ' ', $row['quote_status']),
) ?></td><td><?= h($row['decision'] ?? 'Not recorded') ?></td><td class="numeric"><?= h(
    $row['currency'],
) ?> <?= h($row['total_amount']) ?></td><td><?= h(
    workspaceDate($row['created_at']),
) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<nav class="workspace-pagination" aria-label="Quote history pages"><span>Page <?= $history[
    'page'
] ?> of <?= $history['pages'] ?></span><?php foreach (
    ['Previous' => $history['page'] - 1, 'Next' => $history['page'] + 1]
    as $label => $number
):
    if ($number < 1 || $number > $history['pages']) {
        continue;
    } ?><a class="secondary-btn" href="?<?= h(
    http_build_query(['id' => $id, 'return' => $returnQuery, 'page' => $number]),
) ?>"><?= $label ?></a><?php
endforeach; ?></nav></section>
</main></body></html>
