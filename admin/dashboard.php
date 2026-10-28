<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/dashboard_data.php';
startAppSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        header('Location: login.php?error=csrf');
        exit();
    }

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        if (loginAdmin($email, $password)) {
            header('Location: dashboard.php');
            exit();
        }

        header('Location: login.php?error=1');
        exit();
    } catch (Throwable $error) {
        error_log('RepairTrack login error: ' . $error->getMessage());
        header('Location: login.php?error=db');
        exit();
    }
}

$admin = requireAdmin();
$admin_username = htmlspecialchars($admin['full_name'], ENT_QUOTES, 'UTF-8');

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
];

try {
    $dashboardData = loadDashboardData(getPDO());
} catch (Throwable $error) {
    error_log('RepairTrack dashboard data error: ' . $error->getMessage());
    $metricsAvailable = false;
}

$metrics = $dashboardData['metrics'];
$repairQueue = $dashboardData['queue'];
$customers = $dashboardData['customers'];
$drafts = $dashboardData['drafts'];
$statusCounts = $dashboardData['status_counts'];
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
                    <button class="nav-item is-active" type="button" data-dashboard-tab="overview" aria-current="page">
                        <i class="nav-icon fa-solid fa-chart-line" aria-hidden="true"></i>
                        <span>Operations overview</span>
                    </button>
                    <button class="nav-item" type="button" data-dashboard-tab="workbench" aria-current="false">
                        <i class="nav-icon fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                        <span>Repair workbench</span>
                    </button>
                    <button class="nav-item" type="button" data-dashboard-tab="customers" aria-current="false">
                        <i class="nav-icon fa-solid fa-users" aria-hidden="true"></i>
                        <span>Customer roster</span>
                    </button>
                    <button class="nav-item" type="button" data-dashboard-tab="reports" aria-current="false">
                        <i class="nav-icon fa-solid fa-file-invoice" aria-hidden="true"></i>
                        <span>Reports and records</span>
                    </button>
                </nav>
            </div>

            <div class="sidebar-footer">
                <span class="status-dot" aria-hidden="true"></span>
                <span>System interface ready</span>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <div class="eyebrow">Computer repair operations</div>
                    <h1>Admin dashboard</h1>
                </div>
                <div class="dashboard-user">
                    <span class="system-status"><span class="status-dot" aria-hidden="true"></span>Database connected</span>
                    <span class="welcome-seal" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <span>
                        <strong><?php echo $admin_username; ?></strong>
                        <small>Administrator</small>
                    </span>
                    <a class="logout-btn" id="logout-link" href="logout.php">Log out</a>
                </div>
            </header>

            <div class="dashboard-content">
                <section class="welcome-panel" data-dashboard-panel="overview">
                    <div class="welcome-copy">
                        <div class="eyebrow">Workspace status</div>
                        <h2>Good day, <?php echo $admin_username; ?>.</h2>
                        <p>Your RepairTrack database is connected. Use the consoles to review the current service data.</p>
                    </div>
                    <div class="construction-mark" aria-label="Data modules under construction">
                        <i class="fa-solid fa-hammer" aria-hidden="true"></i>
                        <span>Under construction</span>
                    </div>
                </section>

                <section class="analytics-grid" aria-label="Analytics summary" data-dashboard-panel="overview">
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

                <section class="dashboard-grid" data-dashboard-panel="overview">
                    <article class="dashboard-card construction-card">
                        <div class="card-heading">
                            <div><div class="eyebrow">Repair flow</div><h2>Workbench analytics</h2></div>
                            <i class="fa-solid fa-chart-area" aria-hidden="true"></i>
                        </div>
                        <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Analytics module under construction</strong><span>Charts will appear when repair records are connected.</span></div>
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
                                        <dt><?php echo htmlspecialchars(
                                            (string) $item['item_code'],
                                            ENT_QUOTES,
                                            'UTF-8',
                                        ); ?><br><small><?php echo htmlspecialchars(
    dashboardDeviceLabel($item),
    ENT_QUOTES,
    'UTF-8',
); ?></small></dt>
                                        <dd><?php echo htmlspecialchars(
                                            dashboardStatusLabel((string) $item['repair_status']),
                                            ENT_QUOTES,
                                            'UTF-8',
                                        ); ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="workbench" hidden>
                    <div class="card-heading">
                        <div>
                            <div class="eyebrow">Operational hub</div>
                            <h2>Repair workbench</h2>
                        </div>
                        <a class="secondary-btn" href="intake_create.php">Start intake</a>
                    </div>
                    <?php if (isset($_GET['created'])): ?>
                        <p class="intake-success" role="status">Draft saved and added to pending intake.</p>
                    <?php endif; ?>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><i class="fa-solid fa-database" aria-hidden="true"></i><strong>Dashboard data unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($repairQueue === []): ?>
                        <div class="empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><strong>No repair tickets yet</strong><span>Issued devices will appear here when intake records are created.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($repairQueue as $item): ?>
                                <div>
                                    <dt>
                                        <?php echo htmlspecialchars(
                                            (string) $item['item_code'],
                                            ENT_QUOTES,
                                            'UTF-8',
                                        ); ?>
                                        <br><small><?php echo htmlspecialchars(
                                            dashboardDeviceLabel($item),
                                            ENT_QUOTES,
                                            'UTF-8',
                                        ); ?> · <?php echo htmlspecialchars(
     (string) ($item['customer_name'] ?? 'Customer not linked'),
     ENT_QUOTES,
     'UTF-8',
 ); ?></small>
                                    </dt>
                                    <dd><?php echo htmlspecialchars(
                                        dashboardStatusLabel((string) $item['repair_status']),
                                        ENT_QUOTES,
                                        'UTF-8',
                                    ); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <div class="eyebrow intake-subheading">Pending intake drafts</div>
                    <?php if (!$metricsAvailable): ?>
                        <div class="empty-state"><strong>Draft list unavailable</strong><span>Check the database connection and try again.</span></div>
                    <?php elseif ($drafts === []): ?>
                        <div class="empty-state"><strong>No pending intake drafts</strong><span>New drafts will appear here.</span></div>
                    <?php else: ?>
                        <dl class="status-list">
                            <?php foreach ($drafts as $draft): ?>
                                <div>
                                    <dt>Draft #<?php echo (int) $draft[
                                        'ticket_id'
                                    ]; ?><br><small><?php echo htmlspecialchars(
    (string) ($draft['customer_name'] ?? 'Customer not linked'),
    ENT_QUOTES,
    'UTF-8',
); ?> · <?php echo htmlspecialchars(
     (string) $draft['branch_code'],
     ENT_QUOTES,
     'UTF-8',
 ); ?></small></dt>
                                    <dd><?php echo htmlspecialchars(
                                        ucwords(
                                            strtolower(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $draft['arrival_method'],
                                                ),
                                            ),
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8',
                                    ); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="customers" hidden>
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
                                    <dt><?php echo htmlspecialchars(
                                        (string) $customer['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8',
                                    ); ?></dt>
                                    <dd><?php echo (int) $customer[
                                        'ticket_count'
                                    ]; ?> ticket(s)</dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="reports" hidden>
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
                                    <dt><?php echo htmlspecialchars(
                                        dashboardStatusLabel(
                                            (string) $statusCount['repair_status'],
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8',
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

    <script src="../js/notif.js"></script>
    <script src="../js/trans.js"></script>
    <script src="../js/dashboard.js"></script>
</body>
</html>