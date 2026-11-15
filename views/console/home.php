<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
    <link rel="stylesheet" href="../css/workspace.css">
</head>
<body class="dashboard-body">
<a class="skip-link" href="#work-list">Skip to work list</a>
<main class="staff-page workspace">
    <header class="staff-heading">
        <div><p class="eyebrow">RepairTrack · <?= h(
            staffRoles()[$role] ?? 'Administrator',
        ) ?></p><h1><?= h($title) ?></h1><p><?= h($description) ?></p></div>
        <details class="console-account"><summary><?= h(
            $user['full_name'],
        ) ?> · Account</summary><p><?= h(
     $user['email'],
 ) ?></p><a href="../index.php">Public home</a><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(
    csrfToken(),
) ?>"><button class="secondary-btn">Log out</button></form></details>
    </header>
    <?php if (
        $notice
    ): ?><p role="status" class="staff-notice">Intake draft saved.</p><?php endif; ?>
    <div class="workspace-context">
        <p><?= h(
            implode(' · ', array_column($branches, 'branch_name')) ?:
            'No active branch assigned — ask an administrator for access.',
        ) ?></p>
        <?php if (
            $activeLoad !== null
        ): ?><p><strong><?= $activeLoad ?> / <?= technicianActiveLimit() ?></strong> active assigned devices. Waiting and ready-for-pickup devices count until the repair is closed.</p><?php endif; ?>
        <?php if (
            $role === 'VIEWER'
        ): ?><p>Read-only access. Customer contacts and private notes are hidden.</p><?php endif; ?>
        <?php if (
            $isCourier
        ): ?><p>Branch manifests only. Confirm handover changes with your manager; dispatch and receipt actions are not available here.</p><?php endif; ?>
    </div>
    <nav class="workspace-tabs" aria-label="Workspace sections">
        <?php if ($role !== 'CASHIER'): ?>
            <a href="?view=queue" <?= $view === 'queue'
                ? 'aria-current="page"'
                : '' ?>><?= $isCourier ? 'Open transfers' : 'Active queue' ?></a>
            <?php if (!$isCourier): ?><a href="?view=board" <?= $view === 'board'
    ? 'aria-current="page"'
    : '' ?>>Status board</a><?php endif; ?>
            <a href="?view=history" <?= $view === 'history'
                ? 'aria-current="page"'
                : '' ?>>History</a>
        <?php endif; ?>
        <?php if ($canIntake): ?><a href="?view=drafts" <?= $view === 'drafts'
    ? 'aria-current="page"'
    : '' ?>>Saved drafts</a><a href="intake_create.php">New device intake</a><?php endif; ?>
        <?php if ($canCash): ?><a href="?view=cash" <?= $view === 'cash'
    ? 'aria-current="page"'
    : '' ?>>Cash desk</a><?php endif; ?>
        <?php if ($role === 'ADMIN'): ?><a href="dashboard.php">Admin dashboard</a><?php endif; ?>
    </nav>
    <?php if ($trend && $view !== 'history'): ?>
    <section class="workspace-trends" aria-labelledby="trend-heading">
        <h2 id="trend-heading">Repairs started · last 7 days</h2><p>Philippine time · your assigned scope. Hover, focus or tap a date for its count.</p>
        <div class="trend-days">
        <?php foreach ($trend as $index => $day): ?>
            <details class="trend-point"><summary aria-describedby="trend-<?= $index ?>"><span><?= h(
    $day['label'],
) ?></span><strong><?= $day[
    'total'
] ?></strong></summary><span class="trend-tooltip" id="trend-<?= $index ?>" role="tooltip"><?= h(
    $day['date'],
) ?> · <?= $day['total'] ?> repairs started</span></details>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <section id="work-list" class="dashboard-card workspace-list">
        <?php $listTitle = match ($view) {
            'cash' => 'Cash desk bill lookup',
            'drafts' => 'Saved intake drafts',
            'board' => 'Status board',
            'history' => $isCourier ? 'Transfer history' : 'Repair history',
            default => $isCourier ? 'Branch transfer manifest' : 'Repair queue',
        }; ?>
        <h2><?= h($listTitle) ?></h2>
        <?php if (
            $view === 'history'
        ): ?><p>Finished, cancelled or closed records stay here for reference. Nothing is deleted.</p><?php endif; ?>
        <?php if (
            $view === 'cash'
        ): ?><p>Issued and voided bills only. Amounts are bill totals, not cash received. Payment recording and receipt issuance are not implemented yet.</p><?php endif; ?>
        <form class="console-search" method="get">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <label for="queue-search"><?= h(
                match ($view) {
                    'cash' => 'Ticket code, tracking code or bill number',
                    'drafts' => 'Customer name or draft number',
                    default => $isCourier ? 'Transfer code' : 'Item code, ticket code or model',
                },
            ) ?><input id="queue-search" type="search" name="q" value="<?= h(
    requestText($_GET, 'q'),
) ?>" maxlength="100"></label>
            <?php if (
                $data
            ): ?><label for="queue-status">Repair status<select id="queue-status" name="status"><option value="">All statuses</option><?php foreach (
    consoleStatuses()
    as $status
): ?><option value="<?= h($status) ?>" <?= consoleStatusFilter($_GET) === $status
    ? 'selected'
    : '' ?>><?= h(
    dashboardStatusLabel($status),
) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <button class="secondary-btn">Search</button><a href="?view=<?= h(
                $view,
            ) ?>">Clear filters</a>
        </form>
        <p class="workspace-count">Showing <?= $page['from'] ?>–<?= $page['to'] ?> of <?= $page[
     'total'
 ] ?> · maximum 10 per page</p>
        <?php if (!$page['rows']): ?>
            <div class="workspace-empty"><h3>No matching records</h3><p>Try clearing your filters or searching with a shorter term.</p></div>
        <?php elseif ($view === 'board'): ?>
            <p>Cards show this page of filtered results. Active queue shows the same records as a table.</p>
            <div class="status-board">
            <?php foreach (consoleStatuses() as $status):

                $cards = array_filter($page['rows'], fn($row) => $row['repair_status'] === $status);
                if (!$cards) {
                    continue;
                }
                ?>
                <section class="board-lane"><h3><span class="workspace-status status-<?= h(
                    strtolower($status),
                ) ?>"><?= h(dashboardStatusLabel($status)) ?></span><small><?= count(
    $cards,
) ?> on this page</small></h3>
                <?php foreach (
                    $cards
                    as $row
                ): ?><article class="board-card"><a href="device.php?id=<?= (int) $row[
    'ticket_device_id'
] ?>&amp;return=<?= h(urlencode($returnQuery)) ?>"><?= h($row['item_code']) ?></a><p><?= h(
    dashboardDeviceLabel($row),
) ?></p><p><?= h($row['technician'] ?: 'Needs assignment') ?></p><small>Repair started: <?= h(
    workspaceDate($row['repair_started_at']),
) ?></small></article><?php endforeach; ?>
                </section>
            <?php
            endforeach; ?>
            </div>
        <?php else: ?>
            <?php if ($view === 'drafts'): ?><div id="drafts"><?php endif; ?>
            <p class="table-scroll-hint">Swipe or scroll sideways to see all columns.</p>
            <div class="staff-table-wrap" tabindex="0" aria-label="<?= h(
                $listTitle,
            ) ?>, scroll sideways for more columns">
            <table class="staff-table workspace-table"><caption class="sr-only"><?= h(
                $listTitle,
            ) ?></caption>
                <thead><tr>
                    <?php
                    $columns = match (true) {
                        $view === 'cash' => [
                            'Ticket / tracking code',
                            'Bill',
                            'Branch',
                            'Issued (PH time)',
                            'Status',
                            'Bill total',
                        ],
                        $view === 'drafts' => [
                            'Draft',
                            'Customer',
                            'Branch',
                            'Created (PH time)',
                            'Action',
                        ],
                        $isCourier => [
                            'Transfer / item',
                            'Route',
                            'Destination',
                            'Status',
                            'Dispatched (PH time)',
                        ],
                        default => [
                            'Item / device',
                            'Branch',
                            'Technician',
                            'Status',
                            'Repair started (PH time)',
                            'Action',
                        ],
                    };
                    foreach ($columns as $column): ?><th scope="col" class="<?= $column ===
'Bill total'
    ? 'numeric'
    : '' ?>"><?= h($column) ?></th><?php endforeach;
                    ?>
                </tr></thead><tbody>
                <?php foreach ($page['rows'] as $row): ?><tr>
                    <?php if ($view === 'cash'): ?>
                        <td><?= h($row['ticket_code']) ?><small><?= h(
    $row['portal_code'] ?: 'No short tracking code',
) ?></small></td><td><?= h($row['bill_number']) ?></td><td><?= h(
    $row['branch_name'],
) ?></td><td><?= h(workspaceDate($row['issued_at'])) ?></td><td><?= h(
    ucfirst(strtolower($row['bill_status'])),
) ?></td><td class="numeric"><?= h($row['currency']) ?> <?= number_format(
     (float) $row['total_amount'],
     2,
 ) ?></td>
                    <?php elseif ($view === 'drafts'): ?>
                        <td>#<?= (int) $row['ticket_id'] ?></td><td><?= h(
    $row['customer_name'] ?? 'Not recorded',
) ?></td><td><?= h($row['branch_name']) ?></td><td><?= h(
    workspaceDate($row['created_at']),
) ?></td><td><a href="intake_create.php?id=<?= (int) $row['ticket_id'] ?>">Open draft</a></td>
                    <?php elseif ($isCourier): ?>
                        <td><?= h($row['transfer_code']) ?><small><?= h(
    $row['item_code'],
) ?></small></td><td><?= h($row['origin'] . ' → ' . $row['destination']) ?></td><td><?= h(
    $row['destination_location'],
) ?></td><td><span class="workspace-status"><?= h(
    str_replace('_', ' ', $row['item_status']),
) ?></span></td><td><?= h(workspaceDate($row['dispatched_at'])) ?></td>
                    <?php else: ?>
                        <td><?= h($row['item_code']) ?><small><?= h(
    dashboardDeviceLabel($row),
) ?></small></td><td><?= h($row['branch_name']) ?></td><td><?= h(
    $row['technician'] ?: 'Unassigned',
) ?></td><td><span class="workspace-status status-<?= h(
    strtolower($row['repair_status']),
) ?>"><?= h(dashboardStatusLabel($row['repair_status'])) ?></span></td><td><?= h(
    workspaceDate($row['repair_started_at']),
) ?></td><td><a href="device.php?id=<?= (int) $row['ticket_device_id'] ?>&amp;return=<?= h(
    urlencode($returnQuery),
) ?>">Open device</a></td>
                    <?php endif; ?>
                </tr><?php endforeach; ?>
                </tbody></table>
            </div>
            <?php if ($view === 'drafts'): ?></div><?php endif; ?>
        <?php endif; ?>
        <nav class="workspace-pagination" aria-label="Work pages"><span>Page <?= $page[
            'page'
        ] ?> of <?= $page['pages'] ?></span>
            <?php foreach (
                ['Previous' => $page['page'] - 1, 'Next' => $page['page'] + 1]
                as $label => $number
            ):
                if ($number < 1 || $number > $page['pages']) {
                    continue;
                } ?>
                <a class="secondary-btn" href="<?= h(
                    consolePageUrl(
                        $_GET,
                        $view === 'drafts' ? 'draft_page' : 'page',
                        $number,
                        'work-list',
                    ),
                ) ?>"><?= h($label) ?></a>
            <?php
            endforeach; ?>
        </nav>
    </section>
</main><script src="../js/workspace.js"></script>
</body></html>
