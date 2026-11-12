<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($device['item_code']) ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
</head>
<body class="dashboard-body">
<main class="staff-page">
    <header class="staff-heading">
        <div><div class="eyebrow"><?= h($device['branch_name']) ?></div><h1><?= h(
    $device['item_code'],
) ?></h1><p><?= h(dashboardDeviceLabel($device)) ?></p></div>
        <a class="secondary-btn" href="staff_home.php">Back to console</a>
    </header>
    <?php if ($saved): ?><p class="staff-notice" role="status">Device updated.</p><?php endif; ?>
    <?php if ($error): ?><p role="alert" class="field-error"><?= h(
    $error,
) ?> <a href="?id=<?= $id ?>">Reload device</a></p><?php endif; ?>
    <section class="dashboard-card">
        <h2>Repair details</h2>
        <dl class="staff-review">
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
                    <?php endif; ?>
                </dl>
                <div class="staff-actions"><button name="intent" value="edit" class="secondary-btn">Go back</button><button name="intent" value="save" class="logout-btn">Confirm change</button></div>
            <?php elseif ($canAssign): ?>
                <input type="hidden" name="action" value="assign">
                <label for="technician">Active technician at this branch
                    <select id="technician" name="technician" required>
                        <option value="">Choose technician</option>
                        <?php foreach ($technicians as $tech): ?>
                        <option value="<?= (int) $tech['user_id'] ?>" <?= $values['technician'] ===
(string) $tech['user_id']
    ? 'selected'
    : '' ?>><?= h($tech['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p>Assignments are limited to active technicians assigned to this branch.</p>
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
                <p>Only you and administrators can see this note. Starting repair requires an approved quote; quote approval and customer handover are not performed here.</p>
                <button name="intent" value="review" class="logout-btn">Review progress</button>
            <?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['ADMIN', 'TECHNICIAN'], true)): ?>
    <section class="dashboard-card">
        <h2><?= $user['role'] === 'ADMIN' ? 'Internal repair history' : 'Your repair notes' ?></h2>
        <p>Latest 20 entries. This view does not publish notes or send notifications.</p>
        <?php if (!$events): ?><p>No entries available.</p><?php endif; ?>
        <?php foreach ($events as $event): ?>
            <article class="console-event"><strong><?= h(
                dashboardStatusLabel($event['to_status']),
            ) ?></strong> · <time><?= h($event['created_at']) ?> UTC</time><p><?= h(
     $event['internal_note'] ?: 'Stage updated',
 ) ?></p></article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
