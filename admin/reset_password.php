<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/password_reset.php';
startAppSession();

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$csrf = csrfToken();
$token = is_string($_POST['token'] ?? ($_GET['token'] ?? null))
    ? (string) ($_POST['token'] ?? $_GET['token'])
    : '';
$tokenIsValid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password']
        : '';

    if (!csrfIsValid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $message = 'This form expired. Reload the page and try again.';
    } elseif (!$tokenIsValid) {
        $message = 'This reset link is invalid or has expired. Request a new one.';
    } elseif (strlen($password) < 12) {
        $message = 'Choose a password with at least 12 characters.';
    } elseif (!hash_equals($password, $confirmPassword)) {
        $message = 'The passwords do not match.';
    } else {
        try {
            $success = completeAdminPasswordReset($token, $password);
            $message = $success
                ? 'Your password has been updated. You can now sign in.'
                : 'This reset link is invalid or has expired. Request a new one.';
        } catch (Throwable $exception) {
            error_log('RepairTrack password reset completion failed: ' . $exception->getMessage());
            $message = 'We could not update the password right now. Please try again later.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $tokenIsValid) {
    // Do not reveal whether a token is valid until the form is submitted.
    // The same safe form response is shown for valid and invalid tokens.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a New Password | RepairTrack</title>
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
                    <h2>Choose a new password</h2>
                    <p class="reset-copy">Use at least 12 characters. This link works once and expires after 30 minutes.</p>

                    <?php if ($message !== ''): ?>
                        <div class="reset-box" role="<?php echo $success ? 'status' : 'alert'; ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                        <form action="reset_password.php" method="POST" autocomplete="on">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(
                                $csrf,
                                ENT_QUOTES,
                                'UTF-8',
                            ); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars(
                                $token,
                                ENT_QUOTES,
                                'UTF-8',
                            ); ?>">

                            <label class="field-label" for="password">New password</label>
                            <div class="input-wrap">
                                <input type="password" id="password" name="password" autocomplete="new-password" minlength="12" required>
                            </div>

                            <label class="field-label" for="confirm-password">Confirm new password</label>
                            <div class="input-wrap">
                                <input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" minlength="12" required>
                            </div>

                            <button type="submit" class="login-btn">Update password</button>
                        </form>
                    <?php endif; ?>

                    <p class="reset-copy"><a href="login.php">Back to login</a></p>
                </section>
            </div>
        </div>
    </main>
    <script src="../js/trans.js"></script>
</body>
</html>
