<?php
// ========================================
// SESSION & AUTH CHECK
// ========================================

session_start();
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="page-fade">
    <!-- ========================================
         BACKGROUND & OVERLAYS
         ======================================== -->
    
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="../assets/login-bg.mp4" type="video/mp4">
    </video>
    
    <div class="video-overlay"></div>

    <!-- Loading/Transfer Animation -->
    <div class="loader-overlay" aria-live="polite">
        <div class="loader-shell">
            <div class="loader-ring"></div>
            <div class="loader-matrix">
                <div class="transfer-metric"></div>
            </div>
            <div class="transfer-status">Establishing secure link</div>
        </div>
    </div>

    <!-- ========================================
         MAIN LOGIN CONTENT
         ======================================== -->
    
    <div class="login-scene">
        <div class="login-shell">
            <!-- Brand Header -->
            <div class="brand-wrap">
                <div class="brand-tag">Computer Repair Admin</div>
                <h1 class="brand-title">RepairTrack</h1>
            </div>

            <!-- Form Container -->
            <div class="login-content">
                <div class="login-panel auth-view" id="login-view">
                    <h2>Login</h2>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-box">Invalid username or password.</div>
                    <?php endif; ?>

                    <form id="admin-login-form" action="process_login.php" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- Username Field -->
                        <label class="field-label" for="username">Username</label>
                        <div class="input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Username">
                        </div>

                        <!-- Password Field -->
                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password">
                        </div>

                        <!-- Password recovery -->
                        <div class="meta-row">
                            <a href="forgot_password.php" data-auth-view="recovery">Forgot Password?</a>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="login-btn">Login</button>
                    </form>
                </div>

                <div class="login-panel reset-panel auth-view" id="recovery-view" hidden>
                    <h2>Reset Password</h2>
                    <p class="reset-copy">
                        This is a demo admin account. Use the default credentials below to login.
                    </p>

                    <div class="reset-box">
                        <p><strong>Username:</strong> admin</p>
                        <p><strong>Password:</strong> 123</p>
                    </div>

                    <a href="login.php" class="login-btn" data-auth-view="login">
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- ========================================
         SCRIPTS
         ======================================== -->
    
    <script src="https://kit.fontawesome.com/19c0f829b8.js" crossorigin="anonymous"></script>
    <script type="module" src="../js/page-transition.js"></script>
    <script src="../js/login.js"></script>
</body>
</html>
