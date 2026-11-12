<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css">
    <link rel="stylesheet" href="../css/console.css">
</head>
<body class="dashboard-body">
<main class="staff-page">
    <header class="staff-heading">
        <div>
            <div class="eyebrow">RepairTrack · <?= h(
                staffRoles()[$role] ?? 'Administrator',
            ) ?></div>
            <h1><?= h($title) ?></h1>
            <p><?= h($description) ?></p>
        </div>
        <details class="console-account">
            <summary><?= h($user['full_name']) ?> · Account</summary>
            <p><?= h($user['email']) ?></p>
            <a href="../index.php">Public home</a>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <button class="secondary-btn">Log out</button>
            </form>
        </details>
    </header>
    <?php if (
        $notice
    ): ?><p role="status" class="staff-notice">Intake draft saved.</p><?php endif; ?>
    <nav class="staff-actions console-navigation" aria-label="Workspace actions">
        <?php if (
            $role === 'ADMIN'
        ): ?><a class="secondary-btn" href="dashboard.php">Admin dashboard</a><?php endif; ?>
        <?php if (
            $canIntake
        ): ?><a class="logout-btn" href="intake_create.php">New device intake</a><a class="secondary-btn" href="#drafts">Saved drafts</a><?php endif; ?>
        <a class="secondary-btn" href="#work-list"><?= $isCourier
            ? 'Transfer manifest'
            : 'Repair queue' ?></a>
    </nav>
    <section class="dashboard-card console-branches">
        <h2>Your branches</h2>
        <?php if (
            !$branches
        ): ?><p>No active branch assigned. Ask an administrator to update your account.</p><?php endif; ?>
        <ul><?php foreach ($branches as $branch): ?><li><?= h(
    $branch['branch_name'],
) ?></li><?php endforeach; ?></ul>
        <?php if (
            $role === 'TECHNICIAN'
        ): ?><p>Only devices assigned to you appear here. Internal notes are visible to their author and administrators.</p><?php endif; ?>
        <?php if (
            $role === 'VIEWER'
        ): ?><p>Read-only access. Customer contacts, signed forms and internal notes are not shown.</p><?php endif; ?>
        <?php if (
            $isCourier
        ): ?><p>This is a branch manifest, not a personal delivery assignment. Transfer creation and receipt confirmation are not available yet; contact your manager for handover changes.</p><?php endif; ?>
    </section>
    <?php if (!$isCourier): ?>
    <section class="console-metrics" aria-label="Work status totals">
        <?php foreach ($data['counts'] as $count): ?>
        <article class="dashboard-card"><strong><?= (int) $count['total'] ?></strong><span><?= h(
    dashboardStatusLabel($count['repair_status']),
) ?></span></article>
        <?php endforeach; ?>
        <?php if (
            !$data['counts']
        ): ?><p>No issued devices in your current scope.</p><?php endif; ?>
    </section>
    <?php endif; ?>
    <section id="work-list" class="dashboard-card">
        <h2><?= $isCourier ? 'Branch transfer manifest' : 'Repair queue' ?></h2>
        <form class="console-search" method="get">
            <label for="queue-search"><?= $isCourier
                ? 'Transfer code'
                : 'Item code, ticket code or model' ?>
                <input id="queue-search" name="q" value="<?= h(
                    requestText($_GET, 'q'),
                ) ?>" maxlength="100">
            </label>
            <button class="secondary-btn">Search</button><a href="staff_home.php">Clear</a>
        </form>
        <p>Showing <?= $page['from'] ?>–<?= $page['to'] ?> of <?= $page['total'] ?></p>
        <div class="staff-table-wrap" tabindex="0" aria-label="Work list, scroll sideways for more columns">
        <table class="staff-table">
            <thead><tr>
            <?php foreach (
                $isCourier
                    ? [
                        'Transfer / item',
                        'Route',
                        'Destination location',
                        'Status',
                        'Condition notes',
                    ]
                    : ['Item / device', 'Branch', 'Technician', 'Status', 'Action']
                as $heading
            ): ?>
                <th scope="col"><?= h($heading) ?></th>
            <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $row): ?>
                <tr>
                <?php if ($isCourier): ?>
                    <td><?= h($row['transfer_code']) ?><br><?= h($row['item_code']) ?></td>
                    <td><?= h($row['origin'] . ' → ' . $row['destination']) ?></td>
                    <td><?= h($row['destination_location']) ?></td>
                    <td><?= h(str_replace('_', ' ', $row['item_status'])) ?></td>
                    <td>Sent: <?= h(
                        $row['sent_condition_note'] ?: 'Not recorded',
                    ) ?><br>Received: <?= h(
    $row['receipt_condition_note'] ?: 'Not recorded',
) ?></td>
                <?php else: ?>
                    <td><?= h($row['item_code']) ?><br><?= h(dashboardDeviceLabel($row)) ?></td>
                    <td><?= h($row['branch_name']) ?></td>
                    <td><?= h($row['technician'] ?: 'Unassigned') ?></td>
                    <td><?= h(dashboardStatusLabel($row['repair_status'])) ?></td>
                    <td><a href="device.php?id=<?= (int) $row[
                        'ticket_device_id'
                    ] ?>">Open device</a></td>
                <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (
                !$page['rows']
            ): ?><tr><td colspan="5">No matching records. Try a different search.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <nav class="staff-actions" aria-label="Work pages">
            <?php foreach (
                ['Previous' => $page['page'] - 1, 'Next' => $page['page'] + 1]
                as $label => $number
            ):
                if ($number < 1 || $number > $page['pages']) {
                    continue;
                } ?>
                <a class="secondary-btn" href="?<?= h(
                    http_build_query(['q' => requestText($_GET, 'q'), 'page' => $number]),
                ) ?>#work-list"><?= h($label) ?></a>
            <?php
            endforeach; ?>
        </nav>
    </section>
    <?php if ($drafts): ?>
    <section id="drafts" class="dashboard-card">
        <h2>Saved intake drafts</h2>
        <p>Showing <?= $drafts['from'] ?>–<?= $drafts['to'] ?> of <?= $drafts[
     'total'
 ] ?>. Review the device before issuing a ticket.</p>
        <ul class="console-drafts">
        <?php foreach ($drafts['rows'] as $draft): ?>
            <li><a href="intake_create.php?id=<?= (int) $draft[
                'ticket_id'
            ] ?>">Draft #<?= (int) $draft['ticket_id'] ?></a> · <?= h(
    $draft['customer_name'] ?? 'Customer not recorded',
) ?> · <?= h($draft['branch_name']) ?></li>
        <?php endforeach; ?>
        </ul>
        <?php if (!$drafts['rows']): ?><p>No pending drafts.</p><?php endif; ?>
        <nav class="staff-actions" aria-label="Draft pages">
        <?php foreach (
            ['Previous' => $drafts['page'] - 1, 'Next' => $drafts['page'] + 1]
            as $label => $number
        ):
            if ($number < 1 || $number > $drafts['pages']) {
                continue;
            } ?>
            <a class="secondary-btn" href="?draft_page=<?= $number ?>#drafts"><?= h($label) ?></a>
        <?php
        endforeach; ?>
        </nav>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
