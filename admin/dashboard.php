<?php
// ========================================
// ADMIN DASHBOARD - PROTECTED PAGE
// ========================================

session_start();

// ========================================
// Authentication Check
// ========================================

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// ========================================
// Inactivity Timeout (30 minutes)
// ========================================

if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ========================================
// Update Activity Timestamp
// ========================================

$_SESSION['last_active'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | RepairTrack Admin</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/alerts.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="dashboard-body page-fade">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <div class="sidebar-brand">
                <span class="brand-mark">RT</span>
                <span><strong>Repair</strong>Track<small>ADMIN CONSOLE</small></span>
            </div>

            <nav class="dashboard-nav" aria-label="Dashboard navigation">
                <a href="#overview" class="is-active"><span class="nav-symbol">01</span> Overview</a>
                <a href="#queue"><span class="nav-symbol">02</span> Repair queue</a>
                <a href="#activity"><span class="nav-symbol">03</span> Recent activity</a>
            </nav>

            <div class="sidebar-status">
                <span class="status-light"></span>
                <div><strong>System online</strong><small>All services operational</small></div>
            </div>
            <a href="logout.php" class="logout-btn" id="logout-link"><span>↪</span> Log out</a>
        </aside>

        <div class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <p class="eyebrow">Operations / Overview</p>
                    <h1>Good to see you, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>.</h1>
                </div>
                <div class="header-date"><span class="status-light"></span><?php echo date('l, F j, Y'); ?></div>
            </header>

            <main class="dashboard-content">
                <section class="welcome-panel" id="overview">
                    <div class="welcome-copy">
                        <p class="eyebrow">The workbench at a glance</p>
                        <h2>Keep every repair moving.</h2>
                        <p>Monitor today’s workload, follow active jobs, and keep your service desk in step.</p>
                        <a href="#queue" class="primary-action">View repair queue <span>→</span></a>
                    </div>
                    <div class="welcome-art" aria-hidden="true"><img src="../assets/admin-repair-illustration.jpg" alt=""></div>
                </section>

                <section class="metric-grid" aria-label="Repair summary">
                    <article class="metric-card metric-featured"><span class="metric-label">Open repairs</span><strong>24</strong><span class="metric-note"><b>+8.4%</b> from last week</span></article>
                    <article class="metric-card"><span class="metric-label">Ready for pickup</span><strong>08</strong><span class="metric-note">Awaiting customer collection</span></article>
                    <article class="metric-card"><span class="metric-label">Completed today</span><strong>12</strong><span class="metric-note">Across all workstations</span></article>
                    <article class="metric-card"><span class="metric-label">Avg. turnaround</span><strong>2.4<span class="metric-unit"> days</span></strong><span class="metric-note">Within your 3-day target</span></article>
                </section>

                <section class="dashboard-columns" id="queue">
                    <div class="dashboard-panel queue-panel">
                        <div class="panel-heading"><div><p class="eyebrow">Live workload</p><h2>Repair queue</h2></div><span class="panel-count">24 active</span></div>
                        <div class="queue-list">
                            <div class="queue-row"><span class="device-icon">MB</span><div class="queue-detail"><strong>MacBook Pro 14-inch</strong><span>Screen replacement - RT-1048</span></div><span class="queue-stage stage-progress">In repair</span></div>
                            <div class="queue-row"><span class="device-icon">IP</span><div class="queue-detail"><strong>iPhone 13</strong><span>Battery service · RT-1046</span></div><span class="queue-stage stage-waiting">Diagnosing</span></div>
                            <div class="queue-row"><span class="device-icon">PS</span><div class="queue-detail"><strong>PlayStation 5</strong><span>HDMI port repair · RT-1041</span></div><span class="queue-stage stage-ready">Ready</span></div>
                        </div>
                        <a href="#activity" class="panel-link">See all active repairs <span>→</span></a>
                    </div>

                    <div class="dashboard-panel activity-panel" id="activity">
                        <div class="panel-heading"><div><p class="eyebrow">Latest updates</p><h2>Recent activity</h2></div></div>
                        <ul class="activity-list">
                            <li><span class="activity-dot dot-copper"></span><div><strong>Repair RT-1048 assigned</strong><span>2 minutes ago · Workshop A</span></div></li>
                            <li><span class="activity-dot dot-gold"></span><div><strong>Customer notified</strong><span>18 minutes ago · RT-1041 ready</span></div></li>
                            <li><span class="activity-dot dot-teal"></span><div><strong>Repair completed</strong><span>42 minutes ago · RT-1039</span></div></li>
                            <li><span class="activity-dot dot-muted"></span><div><strong>New ticket created</strong><span>1 hour ago · RT-1048</span></div></li>
                        </ul>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="../js/notif.js"></script>
    <script src="../js/trans.js"></script>
</body>
</html>
