<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css"><link rel="stylesheet" href="../css/console.css">
    <link rel="stylesheet" href="../css/workspace.css">
    <link rel="stylesheet" href="../css/workspace-design.css">
    <script src="../js/workspace-design.js" defer></script>
</head>
<body class="dashboard-body rt-layout">
<?php
$workspaceUser = $user;
$currentSection = $view;
require __DIR__ . '/../workspace/navigation.php';
?>
<main id="rt-main" class="staff-page workspace rt-content" tabindex="-1">
    <header class="staff-heading rt-page-head">
        <div><p class="eyebrow">RepairTrack · <?= h(staffRoles()[$role] ?? 'Administrator') ?></p>
            <h1><?= h($title) ?></h1><p><?= h($description) ?></p></div>
        <?php if ($canIntake): ?><a class="primary-btn" href="intake_create.php">New intake</a><?php endif; ?>
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
        ): ?><p><a href="transfers.php">Open device handovers</a> to dispatch devices or confirm receipt.</p><?php endif; ?>
    </div>
    <section id="work-list" class="dashboard-card workspace-list rt-card">
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
        ): ?><p>Search a bill to record counter cash or complete a paid customer pickup. COD and online payments are not available.</p><?php endif; ?>
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
        <?php if ($view === 'queue' && !$isCourier): ?><p class="workspace-queue-guidance">Oldest last update appears first to help staff find repairs that may need attention. No automatic deadline or ETA is implied.</p><?php endif; ?>
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
                <?php foreach ($cards as $row): ?>
                    <article class="board-card">
                        <a href="device.php?id=<?= (int) $row['ticket_device_id'] ?>&amp;return=<?= h(urlencode($returnQuery)) ?>">
                            <?= h($row['item_code']) ?>
                        </a>
                        <p><?= h(dashboardDeviceLabel($row)) ?></p>
                        <p><?= h($row['technician'] ?: 'Needs assignment') ?></p>
                        <details class="board-preview">
                            <summary>Repair details<span class="sr-only"> for <?= h($row['item_code']) ?></span></summary>
                            <dl>
                                <dt>Ticket</dt><dd><?= h($row['ticket_code']) ?></dd>
                                <dt>Branch</dt><dd><?= h($row['branch_name']) ?></dd>
                                <dt>Custody</dt><dd><?= h(ucfirst(strtolower(str_replace('_', ' ', $row['custody_state'])))) ?></dd>
                                <dt>Repair started</dt><dd><?= h(workspaceDate($row['repair_started_at'])) ?></dd>
                            </dl>
                        </details>
                    </article>
                <?php endforeach; ?>
                </section>
            <?php
            endforeach; ?>
            </div>
        <?php else: ?>
            <?php if ($view === 'drafts'): ?><div id="drafts"><?php endif; ?>
            <p class="table-scroll-hint">On smaller screens, each record is shown as a stacked card.</p>
            <div class="staff-table-wrap" tabindex="0" aria-label="<?= h($listTitle) ?> records">
            <table class="staff-table workspace-table" role="table"><caption class="sr-only"><?= h(
                $listTitle,
            ) ?></caption>
                <thead role="rowgroup"><tr role="row">
                    <?php
                    $columns = match (true) {
                        $view === 'cash' => [
                            'Ticket / tracking code',
                            'Bill',
                            'Branch',
                            'Issued (PH time)',
                            'Status',
                            'Bill total',
                            'Cash received',
                            'Balance',
                            'Action',
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
                            'Last updated (PH time)',
                            'Action',
                        ],
                    };
                    foreach ($columns as $column): ?><th scope="col" role="columnheader" class="<?= in_array($column, [
    'Bill total',
    'Cash received',
    'Balance',
], true)
    ? 'numeric'
    : '' ?>"><?= h($column) ?></th><?php endforeach;
                    ?>
                </tr></thead><tbody role="rowgroup">
                <?php foreach ($page['rows'] as $row): ?><tr role="row">
                    <?php if ($view === 'cash'): ?>
                        <td role="cell" data-label="Ticket / tracking code"><?= h($row['ticket_code']) ?><small><?= h(
    $row['portal_code'] ?: 'No short tracking code',
) ?></small></td><td data-label="Bill"><?= h($row['bill_number']) ?></td><td data-label="Branch"><?= h(
    $row['branch_name'],
) ?></td><td data-label="Issued (PH time)"><?= h(workspaceDate($row['issued_at'])) ?></td><td data-label="Status"><?= h(
    ucfirst(strtolower($row['bill_status'])),
) ?></td><td class="numeric" data-label="Bill total"><?= h($row['currency']) ?> <?= number_format(
     (float) $row['total_amount'],
     2,
 ) ?></td><td class="numeric" data-label="Cash received"><?= h($row['currency']) ?> <?= number_format(
     (float) $row['amount_paid'],
     2,
 ) ?></td><td class="numeric" data-label="Balance"><?php $difference = (float) $row['total_amount'] - (float) $row['amount_paid']; ?>
    <?= $difference < 0 ? 'Refund due · ' : '' ?><?= h($row['currency']) ?> <?= number_format(abs($difference), 2) ?></td><td data-label="Action"><a href="cash.php?bill_id=<?= (int) $row['bill_id'] ?>">Open cash record</a></td>
                    <?php elseif ($view === 'drafts'): ?>
                        <td data-label="Draft">#<?= (int) $row['ticket_id'] ?></td><td data-label="Customer"><?= h(
    $row['customer_name'] ?? 'Not recorded',
) ?></td><td data-label="Branch"><?= h($row['branch_name']) ?></td><td data-label="Created (PH time)"><?= h(
    workspaceDate($row['created_at']),
) ?></td><td data-label="Action"><a href="intake_create.php?id=<?= (int) $row['ticket_id'] ?>">Open draft</a></td>
                    <?php elseif ($isCourier): ?>
                        <td data-label="Transfer / item"><?= h($row['transfer_code']) ?><small><?= h(
    $row['item_code'],
) ?></small></td><td data-label="Route"><?= h($row['origin'] . ' → ' . $row['destination']) ?></td><td data-label="Destination"><?= h(
    $row['destination_location'],
) ?></td><td data-label="Status"><span class="workspace-status"><?= h(
    str_replace('_', ' ', $row['item_status']),
) ?></span></td><td data-label="Dispatched (PH time)"><?= h(workspaceDate($row['dispatched_at'])) ?></td>
                    <?php else: ?>
                        <td data-label="Item / device"><?= h($row['item_code']) ?><small><?= h(
    dashboardDeviceLabel($row),
) ?></small></td><td data-label="Branch"><?= h($row['branch_name']) ?></td><td data-label="Technician"><?= h(
    $row['technician'] ?: 'Unassigned',
) ?></td><td data-label="Status"><span class="workspace-status status-<?= h(
    strtolower($row['repair_status']),
) ?>"><?= h(dashboardStatusLabel($row['repair_status'])) ?></span></td><td data-label="Repair started (PH time)"><?= h(
    workspaceDate($row['repair_started_at']),
) ?></td><td data-label="Last updated (PH time)"><?= h(
    workspaceDate($row['updated_at']),
) ?></td><td data-label="Action"><a href="device.php?id=<?= (int) $row['ticket_device_id'] ?>&amp;return=<?= h(
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
</main><script src="../js/workspace.js"></script>
</body></html>
