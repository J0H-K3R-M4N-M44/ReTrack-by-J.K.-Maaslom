<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/password_reset.php';
startAppSession();

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$csrf = csrfToken();
$status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $error = 'This form expired. Reload the page and try again.';
    } else {
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';

        try {
            requestAdminPasswordReset($email);
            // Keep the message the same whether or not an account exists.
            $status = 'If an active admin account matches that email, a reset link will arrive shortly.';
        } catch (Throwable $exception) {
            error_log('RepairTrack password reset request failed: ' . $exception->getMessage());
            $error = 'We could not process that request right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body class="page-fade">
    <video class="bg-video" autoplay muted loop playsinline aria-hidden="true">
        <source src="../assets/login-bg.mp4" type="video/mp4">
    </video>
    <div class="video-overlay" aria-hidden="true"></div>

    <main class="login-scene">
        <div class="login-shell">
            <div class="brand-wrap">
                <div class="brand-tag">Account Recovery</div>
                <h1 class="brand-title">RepairTrack</h1>
            </div>

            <div class="login-content">
                <section class="login-panel reset-panel">
                    <h2>Reset Password</h2>
                    <p class="reset-copy">Enter the email address for your admin account. We’ll send a one-time link if it matches an active account.</p>

                    <?php if ($status !== ''): ?>
                        <div class="reset-box" role="status"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif ($error !== ''): ?>
                        <div class="reset-box" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form action="forgot_password.php" method="POST" autocomplete="on">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <label class="field-label" for="email">Admin email</label>
                        <div class="input-wrap">
                            <input type="email" id="email" name="email" autocomplete="email" required>
                        </div>
                        <button type="submit" class="login-btn">Email reset link</button>
                    </form>

                    <p class="reset-copy"><a href="login.php">Back to login</a></p>
                </section>
            </div>
        </div>
    </main>
    <script src="../js/trans.js"></script>
</body>
</html>
