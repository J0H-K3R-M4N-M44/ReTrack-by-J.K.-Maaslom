<?php
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
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
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
                <div class="login-panel">
                    <h2>Login</h2>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-box">Invalid username or password.</div>
                    <?php endif; ?>

                    <form id="admin-login-form" action="process_login.php" method="POST" autocomplete="off">
                        <label class="field-label" for="username">Username</label>
                        <div class="input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Username" required>
                        </div>

                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>

                        <div class="meta-row">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" value="1">
                                <span>Remember me</span>
                            </label>
                            <a href="#">Forgot Password?</a>
                        </div>

                        <button type="submit" class="login-btn">Login</button>
                    </form>

                    <p class="register-text">Don't have an account? <a href="#">Register</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/19c0f829b8.js" crossorigin="anonymous"></script>
    <script src="../js/login.js"></script>
</body>
</html>
