<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/repository.php';
startAppSession();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$token = requestText($_POST, 'token', requestText($_GET, 'token'));
$message = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
        http_response_code(403);
        $message = 'This form expired. Reload the activation link.';
    } elseif (!passwordIsAcceptable(requestText($_POST, 'password'))) {
        $message = 'Use at least 12 characters and at most 72 UTF-8 bytes.';
    } elseif (
        !hash_equals(requestText($_POST, 'password'), requestText($_POST, 'confirm_password'))
    ) {
        $message = 'The passwords do not match.';
    } else {
        try {
            if (
                !reserveLoginAttempt(
                    'activation:' . hash('sha256', $token),
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                )
            ) {
                http_response_code(429);
                $message = 'Too many attempts. Wait 15 minutes before trying again.';
            } else {
                $success = staffActivate(getPDO(), $token, requestText($_POST, 'password'));
                $message = $success
                    ? 'Your staff account is active. Sign in using your email and new password.'
                    : 'This link is invalid, expired, or already used. Ask your administrator for a new activation link.';
            }
        } catch (Throwable $error) {
            error_log('RepairTrack activation: ' . $error->getMessage());
            http_response_code(503);
            $message = 'Activation is temporarily unavailable. Please try again later.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activate staff account | RepairTrack</title><link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/login.css"></head>
<body><main class="login-scene"><div class="login-shell"><section class="login-panel"><h1>Activate your account</h1><p>Choose your own password. Your administrator does not need to know it. Activation links expire after 24 hours.</p>
<?php if ($message !== ''): ?><div class="reset-box" role="<?= $success
    ? 'status'
    : 'alert' ?>"><?= h($message) ?></div><?php endif; ?>
<?php if (
    !$success
): ?><form method="post" action="activate.php"><input type="hidden" name="csrf_token" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="token" value="<?= h(
    $token,
) ?>"><label class="field-label" for="password">New password</label><div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" required></div><label class="field-label" for="confirm-password">Confirm password</label><div class="input-wrap"><input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required></div><p>At least 12 characters; maximum 72 UTF-8 bytes.</p><button class="login-btn" type="submit">Activate account</button></form><?php endif; ?>
<p><a href="login.php">Go to staff sign in</a></p></section></div></main></body></html>
