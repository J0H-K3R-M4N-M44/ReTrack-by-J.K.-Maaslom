<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/dashboard_data.php';
startAppSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    unavailablePage('Open the dashboard using its link.', 405);
}
$tab = requestText($_GET, 'tab', 'overview');
if (!in_array($tab, ['overview', 'workbench', 'customers', 'reports'], true)) {
    $tab = 'overview';
}

$admin = requireAdmin();
$admin_username = h($admin['full_name']);

$metricsAvailable = true;
$dashboardData = [
    'metrics' => [
        'activeRepairs' => 0,
        'technicians' => 0,
        'pendingIntake' => 0,
        'ticketsIssuedToday' => 0,
    ],
    'queue' => [],
    'customers' => [],
    'drafts' => [],
    'status_counts' => [],
    'pages' => [],
];

try {
    $dashboardData = loadDashboardData(getPDO(), $_GET, (int) $admin['user_id']);
} catch (Throwable $error) {
    error_log('RepairTrack dashboard data error: ' . $error->getMessage());
    $metricsAvailable = false;
}

$metrics = $dashboardData['metrics'];
$repairQueue = $dashboardData['queue'];
$customers = $dashboardData['customers'];
$drafts = $dashboardData['drafts'];
$statusCounts = $dashboardData['status_counts'];

function renderDashboardPager(array $page, string $key, string $tab): void
{
    echo '<nav class="list-pager" aria-label="' . h($key) . ' pages">';
    echo '<span>Showing ' . $page['from'] . '–' . $page['to'] . ' of ' . $page['total'] . '</span>';
    foreach (['Previous' => $page['page'] - 1, 'Next' => $page['page'] + 1] as $label => $number) {
        if ($number < 1 || $number > $page['pages']) {
            continue;
        }
        $query = ['tab' => $tab, 'q' => requestText($_GET, 'q'), $key . '_page' => $number];
        echo '<a class="secondary-btn" href="?' .
            h(http_build_query($query)) .
            '">' .
            $label .
            '</a>';
    }
    echo '</nav>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RepairTrack Admin Dashboard</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body page-fade">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="Admin navigation">
            <div>
                <div class="dashboard-brand">
                    <span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                    <span>RepairTrack <strong>Admin</strong></span>
                </div>

                <nav class="dashboard-nav">
                    <a class="nav-item" href="staff_home.php">Repair operations</a>
                    <a class="nav-item" href="staff.php"><span class="nav-icon" aria-hidden="true">＋</span><span>Staff management</span></a>
                    <a class="nav-item<?= $tab === 'overview'
                        ? ' is-active'
                        : '' ?>" href="?tab=overview"<?= $tab === 'overview'
    ? ' aria-current="page"'
    : '' ?>>
                        <i class="nav-icon fa-solid fa-chart-line" aria-hidden="true"></i>
                        <span>Operations overview</span>
                    </a>
                    <a class="nav-item<?= $tab === 'workbench'
                        ? ' is-active'
                        : '' ?>" href="?tab=workbench"<?= $tab === 'workbench'
    ? ' aria-current="page"'
    : '' ?>>
                        <i class="nav-icon fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                        <span>Repair workbench</span>
                    </a>
                    <a class="nav-item<?= $tab === 'customers'
                        ? ' is-active'
                        : '' ?>" href="?tab=customers"<?= $tab === 'customers'
    ? ' aria-current="page"'
    : '' ?>>
                        <i class="nav-icon fa-solid fa-users" aria-hidden="true"></i>
                        <span>Customer roster</span>
                    </a>
                    <a class="nav-item<?= $tab === 'reports'
                        ? ' is-active'
                        : '' ?>" href="?tab=reports"<?= $tab === 'reports'
    ? ' aria-current="page"'
    : '' ?>>
                        <i class="nav-icon fa-solid fa-file-invoice" aria-hidden="true"></i>
                        <span>Reports and records</span>
                    </a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <span class="status-dot" aria-hidden="true"></span>
                <span><?= $metricsAvailable
                    ? 'Dashboard data loaded'
                    : 'Dashboard data unavailable' ?></span>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <div class="eyebrow">Computer repair operations</div>
                    <h1>Admin dashboard</h1>
                </div>
                <div class="dashboard-user">
                    <span class="system-status"><span class="status-dot" aria-hidden="true"></span><?= $metricsAvailable
                        ? 'Dashboard data loaded'
                        : 'Dashboard data unavailable' ?></span>
                    <span class="welcome-seal" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <span>
                        <strong><?php echo $admin_username; ?></strong>
                        <small>Administrator</small>
                    </span>
                    <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(
                        csrfToken(),
                    ) ?>"><button class="logout-btn" type="submit">Log out</button></form>
                </div>
            </header>

            <div class="dashboard-content">
                <?php if (in_array($tab, ['workbench', 'customers'], true)): ?>
                <form class="list-search" method="get">
                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                    <label for="list-search">Search <?= $tab === 'customers'
                        ? 'customers'
                        : 'tickets and drafts' ?></label>
                    <input id="list-search" name="q" value="<?= h(
                        requestText($_GET, 'q'),
                    ) ?>" maxlength="190">
                    <button class="secondary-btn" type="submit">Search</button>
                    <a href="?tab=<?= h($tab) ?>">Clear</a>
                </form>
                <?php endif; ?>
                <section class="welcome-panel" data-dashboard-panel="overview"<?= $tab !==
                'overview'
                    ? ' hidden'
                    : '' ?>>
                    <div class="welcome-copy">
                        <div class="eyebrow">Workspace status</div>
                        <h2>Good day, <?php echo $admin_username; ?>.</h2>
                        <p><?= $metricsAvailable
                            ? 'Review current service data using the workspace links.'
                            : 'Dashboard queries failed. Please try again later.' ?></p>
                    </div>
                    <div class="construction-mark" aria-label="Data modules under construction">
                        <i class="fa-solid fa-hammer" aria-hidden="true"></i>
                        <span>Under construction</span>
                    </div>
                </section>

                <section class="analytics-grid" aria-label="Analytics summary" data-dashboard-panel="overview"<?= $tab !==
                'overview'
                    ? ' hidden'
                    : '' ?>>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Active repairs</span><i class="fa-solid fa-laptop-medical" aria-hidden="true"></i></div>
                        <strong class="metric-value"><?php echo $metricsAvailable
                            ? number_format($metrics['activeRepairs'])
                            : '—'; ?></strong>
                        <small><?php echo $metricsAvailable
                            ? 'Not completed or cancelled'
                            : 'Database query unavailable'; ?></small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Active technicians</span><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
                        <strong class="metric-value"><?php echo $metricsAvailable
                            ? number_format($metrics['technicians'])
                            : '—'; ?></strong>
                        <small><?php echo $metricsAvailable
                            ? 'Enabled technician accounts'
                            : 'Database query unavailable'; ?></small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Pending intake</span><i class="fa-solid fa-clipboard-question" aria-hidden="true"></i></div>
                        <strong class="metric-value"><?php echo $metricsAvailable
                            ? number_format($metrics['pendingIntake'])
                            : '—'; ?></strong>
                        <small><?php echo $metricsAvailable
                            ? 'Draft tickets not yet issued'
                            : 'Database query unavailable'; ?></small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Tickets issued today</span><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                        <strong class="metric-value"><?php echo $metricsAvailable
                            ? number_format($metrics['ticketsIssuedToday'])
                            : '—'; ?></strong>
                        <small><?php echo $metricsAvailable
                            ? 'Issued today, Asia/Manila time'
                            : 'Database query unavailable'; ?></small>
                    </article>
                </section>

                <section class="dashboard-grid" data-dashboard-panel="overview"<?= $tab !==
                'overview'
                    ? ' hidden'
                    : '' ?>>
                    <article class="dashboard-card construction-card">
                        <div class="card-heading">
                            <div><div class="eyebrow">Repair flow</div><h2>Workbench analytics</h2></div>
                            <i class="fa-solid fa-chart-area" aria-hidden="true"></i>
                        </div>
                        <p>Totals cover your assigned active branches.</p>
                        <?php if ($metricsAvailable): ?>
                        <dl class="status-list">
                            <?php foreach ($statusCounts as $count): ?>
                            <div><dt><?= h(
                                dashboardStatusLabel($count['repair_status']),
                            ) ?></dt><dd><?= (int) $count['total'] ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                        <?php if (!$statusCounts): ?><p>No issued repairs yet.</p><?php endif; ?>
                        <a class="secondary-btn" href="staff_home.php">Assign and review repairs</a>
                        <?php else: ?><p>Repair totals are temporarily unavailable.</p><?php endif; ?>
                    </article>
                    <article class="dashboard-card construction-card">
                        <div class="card-heading">
                            <div><div class="eyebrow">Activity feed</div><h2>Recent operations</h2></div>
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        </div>
                        <?php if (!$metricsAvailable): ?>
                            <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Dashboard data unavailable</strong><span>Check the database connection and try again.</span></div>
                        <?php elseif ($repairQueue === []): ?>
                            <div class="empty-state"><i class="fa-solid fa-list-check" aria-hidden="true"></i><strong>No issued repair tickets yet</strong><span>Issued tickets will appear here as the workbench is used.</span></div>
                        <?php else: ?>
                            <dl class="status-list">
                                <?php foreach (array_slice($repairQueue, 0, 5) as $item): ?>
                                    <div>
                                        <dt><a href="ticket.php?id=<?= (int) $item[
                                            'ticket_id'
                                        ] ?>"><?= h(
    (string) $item['item_code'],
) ?></a><br><small><?php echo h(dashboardDeviceLabel($item)); ?></small></dt>
                                        <dd><?php echo h(
                                            dashboardStatusLabel((string) $item['repair_status']),
                                        ); ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="workbench"<?= $tab !==
                'workbench'
                    ? ' hidden'
                    : '' ?>>
                    <div class="card-heading">
                        <div>
                            <div class="eyebrow">Operational hub</div>
                            <h2>Repair workbench</h2>
                        </div>
                        <a class="secondary-btn" href="intake_create.php">Start intake</a>
                    </div>
                    <?php if (!empty($_SESSION['intake_saved'])):
                        unset($_SESSION['intake_saved']); ?>
                        <p class="intake-success" role="status">Draft saved and added to pending intake.</p>
                    <?php
                    endif; ?>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Dashboard data unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($repairQueue === []): ?>
                        <div class="empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><strong>No repair tickets yet</strong><span>Issued devices will appear here when intake records are created.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($repairQueue as $item): ?>
                                <div>
                                    <dt>
                                        <a href="ticket.php?id=<?= (int) $item[
                                            'ticket_id'
                                        ] ?>"><?= h((string) $item['item_code']) ?></a>
                                        <br><small><?php echo h(
                                            dashboardDeviceLabel($item),
                                        ); ?> · <?php echo h(
     (string) ($item['customer_name'] ?? 'Customer not linked'),
 ); ?></small>
                                    </dt>
                                    <dd><?php echo h(
                                        dashboardStatusLabel((string) $item['repair_status']),
                                    ); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <?php if ($metricsAvailable) {
                        renderDashboardPager(
                            $dashboardData['pages']['queue'],
                            'queue',
                            'workbench',
                        );
                    } ?>
                    <div class="eyebrow intake-subheading">Pending intake drafts</div>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><strong>Draft list unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($drafts === []): ?>
                        <div class="empty-state"><strong>No pending intake drafts</strong><span>New drafts will appear here.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($drafts as $draft): ?>
                                <div>
                                    <dt><a href="intake_create.php?id=<?= (int) $draft[
                                        'ticket_id'
                                    ] ?>">Draft #<?= (int) $draft[
    'ticket_id'
] ?></a><br><small><?php echo h(
    (string) ($draft['customer_name'] ?? 'Customer not linked'),
); ?> · <?php echo h((string) $draft['branch_code']); ?></small></dt>
                                    <dd><?php echo h(
                                        ucwords(
                                            strtolower(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $draft['arrival_method'],
                                                ),
                                            ),
                                        ),
                                    ); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($metricsAvailable) {
                        renderDashboardPager(
                            $dashboardData['pages']['drafts'],
                            'drafts',
                            'workbench',
                        );
                    } ?>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="customers"<?= $tab !==
                'customers'
                    ? ' hidden'
                    : '' ?>>
                    <div class="eyebrow">Records and contacts</div>
                    <h2>Customer roster</h2>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Customer data unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($customers === []): ?>
                        <div class="empty-state"><i class="fa-solid fa-users" aria-hidden="true"></i><strong>No customers yet</strong><span>Customer records will appear here after the first intake.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($customers as $customer): ?>
                                <div>
                                    <dt><?php echo h((string) $customer['full_name']); ?></dt>
                                    <dd><?php echo (int) $customer[
                                        'ticket_count'
                                    ]; ?> ticket(s)</dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($metricsAvailable) {
                        renderDashboardPager(
                            $dashboardData['pages']['customers'],
                            'customers',
                            'customers',
                        );
                    } ?>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="reports"<?= $tab !==
                'reports'
                    ? ' hidden'
                    : '' ?>>
                    <div class="eyebrow">Records and assets</div>
                    <h2>Reports and service records</h2>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Report data unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($statusCounts === []): ?>
                        <div class="empty-state"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i><strong>No status data yet</strong><span>Repair status totals will appear after the first ticket is issued.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($statusCounts as $statusCount): ?>
                                <div>
                                    <dt><?php echo h(
                                        dashboardStatusLabel(
                                            (string) $statusCount['repair_status'],
                                        ),
                                    ); ?></dt>
                                    <dd><?php echo (int) $statusCount['total']; ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="../js/trans.js"></script>
</body>
</html>


