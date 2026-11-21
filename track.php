<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/public/tracking.php';
startAppSession();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$repair = null;
$error = '';
$errorField = '';
$enteredCode = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            http_response_code(403);
            $error = 'This form expired. Please enter your tracking code again.';
        } elseif (requestText($_POST, 'action') === 'close') {
            unset($_SESSION['public_tracking']);
            header('Location: track.php', true, 303);
            exit();
        } else {
            $enteredCode = substr(trim(requestText($_POST, 'tracking_code')), 0, 65);
            $token = strtolower($enteredCode);
            $pin = trim(requestText($_POST, 'portal_pin'));
            $shortCode = preg_match('/^[a-f0-9]{5}$/D', $token);
            $address = 'tracking:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            unset($_SESSION['public_tracking']);

            // Format errors can be corrected before spending a verification attempt.
            if (!$shortCode && !preg_match('/^[a-f0-9]{64}$/D', $token)) {
                $errorField = 'tracking-code';
                $error = 'Enter the five-character tracking code from your branch, using 0–9 and A–F.';
            } elseif ($shortCode && !preg_match('/^[0-9]{4}$/D', $pin)) {
                $errorField = 'portal-pin';
                $error = 'Enter the four-digit PIN for this tracking code.';
            } elseif (!reserveLoginAttempt('portal-code:' . hash('sha256', strtoupper($token)), $address)) {
                http_response_code(429);
                $error = 'Too many attempts. Please try again in 15 minutes.';
            } else {
                $repair = publicRepair(getPDO(), $token, $pin);
                if ($repair) {
                    $_SESSION['public_tracking'] = [
                        'token' => $token,
                        'pin' => $pin,
                        'expires' => time() + 1800,
                    ];
                    header('Location: track.php', true, 303);
                    exit();
                }
                $error =
                    'Tracking access could not be verified. Check your code and PIN or ask the branch for a new one.';
            }
        }
    } elseif (isset($_SESSION['public_tracking'])) {
        $access = $_SESSION['public_tracking'];
        if ($access['expires'] > time()) {
            // Revalidate every request: replacing a code also revokes open sessions.
            $repair = publicRepair(
                getPDO(),
                $access['token'],
                $access['pin'] ?? '',
                requestText($_GET, 'history_page', '1'),
            );
        }
        if (!$repair) {
            unset($_SESSION['public_tracking']);
            $error = 'Tracking access expired or was replaced. Enter your current private code.';
        }
    }
} catch (Throwable $problem) {
    error_log('RepairTrack public tracking unavailable: ' . get_class($problem));
    http_response_code(503);
    $error = 'Repair updates are temporarily unavailable. Please try again or contact your branch.';
}
require __DIR__ . '/views/public/tracking.php';
