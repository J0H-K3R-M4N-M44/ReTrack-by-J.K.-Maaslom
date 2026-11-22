<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($device['item_code']) ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css"><link rel="stylesheet" href="../css/workspace.css">
</head>
<body class="dashboard-body">
<main class="staff-page">
    <header class="staff-heading">
        <div><div class="eyebrow"><?= h($device['branch_name']) ?></div><h1><?= h(
    $device['item_code'],
) ?></h1><p><?= h(dashboardDeviceLabel($device)) ?></p></div>
        <a class="secondary-btn" href="staff_home.php?<?= h(
            $returnQuery,
        ) ?>#work-list">Back to console</a>
    </header>
    <?php if ($saved): ?><p class="staff-notice" role="status">Device updated.</p><?php endif; ?>
    <?php if ($error): ?><p role="alert" class="field-error"><?= h(
    $error,
) ?> <a href="?id=<?= $id ?>&amp;return=<?= h(
    urlencode($returnQuery),
) ?>">Reload device</a></p><?php endif; ?>
    <section class="dashboard-card">
        <h2>Repair details</h2>
        <?php if (in_array($user['role'], ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)): ?>
            <p><a class="secondary-btn" href="ticket.php?id=<?= (int) $device[
                'ticket_id'
            ] ?>">Receipt and customer tracking access</a></p>
        <?php endif; ?>
        <dl class="staff-review">
            <div><dt>Repair started (Philippine time)</dt><dd><?= h(
                workspaceDate($device['repair_started_at']),
            ) ?></dd></div>
            <div><dt>Status</dt><dd><?= h(
                dashboardStatusLabel($device['repair_status']),
            ) ?></dd></div>
            <div><dt>Location / custody</dt><dd><?= h(
                ($device['location_name'] ?: 'Not recorded') .
                    ' · ' .
                    str_replace('_', ' ', $device['custody_state']),
            ) ?></dd></div>
            <?php if ($user['role'] !== 'VIEWER'): ?>
            <div><dt>Reported problem</dt><dd><?= h($device['reported_symptoms']) ?></dd></div>
            <div><dt>Condition at intake</dt><dd><?= h($device['condition_notes']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>
    <?php if (in_array($user['role'], ['ADMIN', 'MANAGER', 'TECHNICIAN'], true)): ?>
    <section class="dashboard-card next-action"><h2>Parts needed for this repair</h2>
    <p>Submit or review internal part requests without changing the customer quote or branch stock.</p>
    <a class="secondary-btn" href="part_requests.php?device_id=<?= (int) $id ?>">Open part requests</a></section>
    <?php endif; ?>
    <?php if (quoteCanPrepare($user) || quoteCanRecord($user)): ?>
    <section class="dashboard-card next-action"><div class="section-heading"><h2>Estimate and approval</h2><span class="workspace-status"><?= h(
        str_replace('_', ' ', $quote['quote_status'] ?? 'NOT PREPARED'),
    ) ?></span></div>
    <p><?= $quote && $quote['quote_status'] === 'APPROVED'
        ? 'The current estimate is approved. The technician can continue with the permitted repair steps.'
        : 'Review the proposed work and charges before repair starts. A new revision requires fresh customer approval.' ?></p>
    <a class="secondary-btn" href="quote.php?id=<?= $id ?>&amp;return=<?= h(
    urlencode($returnQuery),
) ?>">Open estimate</a></section>
    <?php endif; ?>
    <?php if (
        in_array($user['role'], ['ADMIN', 'MANAGER'], true) &&
        $device['case_state'] === 'OPEN'
    ): ?>
    <section class="dashboard-card next-action">
        <h2>Final bill and customer pickup</h2>
        <p>Review billing, an agreed whole-ticket cancellation, or customer handover.</p>
        <a class="secondary-btn" href="cash.php?ticket_id=<?= (int) $device['ticket_id'] ?>">Open billing and handover</a>
    </section>
    <?php endif; ?>
    <?php
    $canAssign = in_array($user['role'], ['ADMIN', 'MANAGER'], true);
    $canProgress = $user['role'] === 'TECHNICIAN';
    $open =
        $device['case_state'] === 'OPEN' &&
        !in_array($device['repair_status'], ['COMPLETED', 'CANCELLED'], true);
    ?>
    <?php if (($canAssign || $canProgress) && $open): ?>
    <section class="dashboard-card">
        <h2><?= $review
            ? 'Review device change'
            : ($canAssign
                ? 'Assign technician'
                : 'Record repair progress') ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
            <?php if ($review): ?>
                <dl class="staff-review">
                    <?php if ($values['action'] === 'assign'): ?>
                    <div><dt>Technician</dt><dd><?php
                    $name = 'Invalid technician — go back and choose again';
                    foreach ($technicians as $tech) {
                        if ((string) $tech['user_id'] === $values['technician']) {
                            $name = $tech['full_name'];
                        }
                    }
                    echo h($name);
                    ?></dd></div>
                    <?php else: ?>
                    <div><dt>Repair stage</dt><dd><?= h(
                        dashboardStatusLabel($values['status']),
                    ) ?></dd></div>
                    <div><dt>Private note</dt><dd><?= h($values['note'] ?: 'No note') ?></dd></div>
                    <div><dt>Customer-visible update</dt><dd><?= h(
                        $values['public_message'] ?: 'No new message',
                    ) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <div class="staff-actions"><button name="intent" value="edit" class="secondary-btn">Go back</button><button name="intent" value="save" class="logout-btn">Confirm change</button></div>
            <?php elseif ($canAssign): ?>
                <input type="hidden" name="action" value="assign">
                <label for="technician">Active technician at this branch
                    <select id="technician" name="technician" required>
                        <option value="">Choose technician</option>
                        <?php foreach ($technicians as $tech): ?>
                        <option <?= (int) $tech['active_count'] >= technicianActiveLimit() &&
                        (int) $device['assigned_technician_id'] !== (int) $tech['user_id']
                            ? 'disabled'
                            : '' ?> value="<?= (int) $tech['user_id'] ?>" <?= $values[
    'technician'
] === (string) $tech['user_id']
    ? 'selected'
    : '' ?>><?= h($tech['full_name']) ?> · <?= (int) $tech[
     'active_count'
 ] ?>/<?= technicianActiveLimit() ?> active</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p>Choose an active technician in this branch. The limit is <?= technicianActiveLimit() ?> active devices across all branches. Closed, completed and cancelled work does not count.</p>
                <button name="intent" value="review" class="logout-btn">Review assignment</button>
            <?php else: ?>
                <input type="hidden" name="action" value="progress">
                <label for="repair-status">Repair stage
                    <select id="repair-status" name="status">
                    <?php foreach (
                        [$device['repair_status'], ...consoleNextStatuses($device['repair_status'])]
                        as $status
                    ): ?>
                        <option value="<?= h($status) ?>" <?= $values['status'] === $status
    ? 'selected'
    : '' ?>><?= h(dashboardStatusLabel($status)) ?></option>
                    <?php endforeach; ?>
                    </select>
                </label>
                <label for="repair-note">Private work note <span>(optional for a stage change)</span>
                    <textarea id="repair-note" name="note" rows="4" maxlength="2000"><?= h(
                        $values['note'],
                    ) ?></textarea>
                </label>
                <label for="public-message">Customer-visible update (optional)
                    <textarea id="public-message" name="public_message" rows="3" maxlength="1000"><?= h(
                        $values['public_message'],
                    ) ?></textarea>
                </label>
                <p>Customer updates and stage changes appear in private tracking. Never include passwords, private diagnostics or other customers' information.</p>
                <p>Only you and administrators can see the private work note. Starting repair requires an approved quote. Use Open estimate for the quote workflow; customer handover remains a separate step.</p>
                <button name="intent" value="review" class="logout-btn">Review progress</button>
            <?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['ADMIN', 'TECHNICIAN'], true)): ?>
    <section class="dashboard-card">
        <h2><?= $user['role'] === 'ADMIN' ? 'Internal repair history' : 'Your repair notes' ?></h2>
        <p>Showing <?= $eventPage['from'] ?>–<?= $eventPage['to'] ?> of <?= $eventPage[
     'total'
 ] ?> entries. Private notes stay internal. Customer updates are published separately; no SMS or email is sent.</p>
        <form method="get" class="console-search"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= h(
    $returnQuery,
) ?>"><label for="history-search">Find a note or update<input id="history-search" name="history_q" maxlength="100" value="<?= h(
    requestText($_GET, 'history_q'),
) ?>"></label><button class="secondary-btn">Search history</button></form>
        <?php if (!$events): ?><p>No entries available.</p><?php endif; ?>
        <?php foreach ($events as $event): ?>
            <article class="console-event"><strong><?= h(
                dashboardStatusLabel($event['to_status']),
            ) ?></strong> · <time><?= h(
    workspaceDate($event['created_at']),
) ?> PH time</time><p><?= h($event['internal_note'] ?: 'No private note') ?></p><?php if (
    $event['public_summary']
): ?><p><strong>Customer update:</strong> <?= h(
    $event['public_summary'],
) ?></p><?php endif; ?></article>
        <?php endforeach; ?>
        <nav class="staff-actions" aria-label="History pages">
        <?php foreach (
            ['Previous' => $eventPage['page'] - 1, 'Next' => $eventPage['page'] + 1]
            as $label => $number
        ):
            if ($number < 1 || $number > $eventPage['pages']) {
                continue;
            } ?>
            <a class="secondary-btn" href="?<?= h(
                http_build_query([
                    'id' => $id,
                    'return' => $returnQuery,
                    'history_q' => requestText($_GET, 'history_q'),
                    'history_page' => $number,
                ]),
            ) ?>"><?= h($label) ?></a>
        <?php
        endforeach; ?></nav>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
