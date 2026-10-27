<?php
require_once __DIR__ . '/../app/auth.php';
startAppSession();

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$csrf = csrfToken();
$errorCode = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';
$errorMessage = 'Invalid email or password.';

if ($errorCode === 'db') {
    $errorMessage = 'Database connection failed. Check config/config.php and confirm MySQL is running.';
} elseif ($errorCode === 'csrf') {
    $errorMessage = 'The login form expired. Reload the page and try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/alerts.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="page-fade">
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="../assets/login-bg.mp4" type="video/mp4">
    </video>
    
    <div class="video-overlay"></div>

    <div class="login-scene">
        <div class="login-shell">
            <div class="brand-wrap">
                <div class="brand-tag">Computer Repair Admin</div>
                <h1 class="brand-title">RepairTrack</h1>
            </div>

            <div class="login-content">
                <div class="login-panel" id="login-view">
                    <h2>Login</h2>

                    <div class="error-box" id="message-area" data-tone="<?php echo isset($_GET['error']) ? 'error' : 'info'; ?>"<?php echo isset($_GET['error']) ? '' : ' hidden'; ?>><?php echo isset($_GET['error']) ? htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') : ''; ?></div>

                    <form id="admin-login-form" action="dashboard.php" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                        <label class="field-label" for="email">Email</label>
                        <div class="input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="email" id="email" name="email" placeholder="Email" autocomplete="username" required>
                        </div>

                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                        </div>

                        <div class="meta-row">
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>

                        <button type="submit" class="login-btn">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/19c0f829b8.js" crossorigin="anonymous"></script>
    <script src="../js/notif.js"></script>
    <script src="../js/trans.js"></script>
    <script src="../js/login.js"></script>
</body>
</html>
