<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (
        $session_token === '' ||
        $submitted_token === '' ||
        !hash_equals($session_token, $submitted_token)
    ) {
        header('Location: login.php?error=1');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== 'admin' || $password !== '123') {
        header('Location: login.php?error=1');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_active'] = time();
}

if (($_SESSION['admin_logged_in'] ?? false) !== true) {
    header('Location: login.php');
    exit;
}

$admin_username = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
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
                    <span class="system-status"><span class="status-dot" aria-hidden="true"></span>Workspace ready</span>
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
                        <p>The operational workspace is ready. Live repair, customer, and reporting data will appear here as those modules are connected.</p>
                    </div>
                    <div class="construction-mark" aria-label="Data modules under construction">
                        <i class="fa-solid fa-hammer" aria-hidden="true"></i>
                        <span>Under construction</span>
                    </div>
                </section>

                <section class="analytics-grid" aria-label="Analytics summary">
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Active repairs</span><i class="fa-solid fa-laptop-medical" aria-hidden="true"></i></div>
                        <strong class="metric-value" data-analytics-value="activeRepairs">--</strong>
                        <small data-analytics-note="activeRepairs">Awaiting repair data</small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Technicians on duty</span><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
                        <strong class="metric-value" data-analytics-value="technicians">--</strong>
                        <small data-analytics-note="technicians">Awaiting staff data</small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Pending intake</span><i class="fa-solid fa-clipboard-question" aria-hidden="true"></i></div>
                        <strong class="metric-value" data-analytics-value="pendingIntake">--</strong>
                        <small data-analytics-note="pendingIntake">Awaiting queue data</small>
                    </article>
                    <article class="dashboard-card metric-card">
                        <div class="card-heading"><span>Handovers today</span><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                        <strong class="metric-value" data-analytics-value="handovers">--</strong>
                        <small data-analytics-note="handovers">Awaiting receipt data</small>
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
                        <div class="empty-state"><i class="fa-solid fa-list-check" aria-hidden="true"></i><strong>Activity feed under construction</strong><span>Recent handovers and status changes will appear here.</span></div>
                    </article>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="workbench" hidden>
                    <div class="eyebrow">Operational hub</div>
                    <h2>Repair workbench</h2>
                    <div class="empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><strong>Workbench under construction</strong><span>Repair tickets and status columns will be connected here.</span></div>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="customers" hidden>
                    <div class="eyebrow">Records and contacts</div>
                    <h2>Customer roster</h2>
                    <div class="empty-state"><i class="fa-solid fa-users" aria-hidden="true"></i><strong>Customer module under construction</strong><span>Customer records will appear here when the data source is connected.</span></div>
                </section>

                <section class="dashboard-panel dashboard-card" data-dashboard-panel="reports" hidden>
                    <div class="eyebrow">Records and assets</div>
                    <h2>Reports and service records</h2>
                    <div class="empty-state"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i><strong>Reports module under construction</strong><span>Service receipts and export tools will appear here later.</span></div>
                </section>
            </div>
        </main>
    </div>

    <script src="../js/notif.js"></script>
    <script src="../js/trans.js"></script>
</body>
</html>