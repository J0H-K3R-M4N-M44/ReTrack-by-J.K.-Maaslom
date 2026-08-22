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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body class="dashboard-body page-fade">
    <!-- ========================================
         ADMIN DASHBOARD
         ======================================== -->

    <div class="loader-overlay" aria-live="polite">
        <div class="loader-shell">
            <div class="loader-ring"></div>
            <div class="loader-matrix">
                <div class="transfer-metric"></div>
            </div>
            <div class="transfer-status">Ending session</div>
        </div>
    </div>

    <div class="dashboard-container">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></h1>
        <p>You are logged in as the admin.</p>
        <a href="logout.php" class="logout-btn" id="logout-link">Logout</a>
    </div>

    <script type="module" src="../js/page-transition.js"></script>
</body>
</html>
