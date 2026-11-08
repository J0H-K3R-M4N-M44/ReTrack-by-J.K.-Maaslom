<?php
require_once __DIR__ . '/../app/auth.php';
startAppSession();
header('Cache-Control: no-store, private');

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
        http_response_code(403);
        $errorMessage = 'The login form expired. Reload the page and try again.';
    } else {
        try {
            $email = trim(requestText($_POST, 'email'));
            if (!reserveLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                http_response_code(429);
                header('Retry-After: 900');
                $errorMessage = 'Too many sign-in attempts. Please wait 15 minutes.';
            } elseif (loginAdmin($email, requestText($_POST, 'password'))) {
                header('Location: dashboard.php', true, 303);
                exit();
            } else {
                $errorMessage = 'Invalid email or password.';
            }
        } catch (Throwable $error) {
            error_log('RepairTrack login: ' . $error->getMessage());
            http_response_code(503);
            $errorMessage = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/alerts.css">
</head>
<body class="page-fade login-page">
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

                    <div class="error-box" id="message-area" role="alert"<?= $errorMessage === ''
                        ? ' hidden'
                        : '' ?>><?= h($errorMessage) ?></div>

                    <form id="admin-login-form" action="login.php" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

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

                        <div class="meta-row login-links">
                            <!-- A fixed home link also works when login is opened directly. -->
                            <a class="login-back-link" href="../index.php">
                                <span aria-hidden="true">&larr;</span> Back to home
                            </a>
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>

                        <button type="submit" class="login-btn">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/trans.js"></script>
    <script src="../js/login.js"></script>
</body>
</html>
