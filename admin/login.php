<?php
// ========================================
// SESSION & AUTH CHECK
// ========================================

session_start();

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
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
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
                <div class="login-panel">
                    <h2>Login</h2>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-box">Invalid username or password.</div>
                    <?php endif; ?>

                    <form id="admin-login-form" action="process_login.php" method="POST" autocomplete="off">
                        
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

                        <!-- Remember & Forgot -->
                        <div class="meta-row">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" value="1">
                                <span>Remember me</span>
                            </label>
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="login-btn">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- ========================================
         SCRIPTS
         ======================================== -->
    
    <script src="https://kit.fontawesome.com/19c0f829b8.js" crossorigin="anonymous"></script>
    <script src="../js/login.js"></script>
    <script src="../js/login-transition.js"></script>
</body>
</html>
