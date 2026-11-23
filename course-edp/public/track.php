<?php
declare(strict_types=1);

/** Public customer read-only tracker. The staff preview has a DIFFERENT session cookie. */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/tracking.php';
edpPreviewHeaders();

function edpPortalStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_edp_customer');
    session_set_cookie_params([
        'lifetime'=>0, 'path'=>'/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'=>true, 'samesite'=>'Lax',
    ]);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 900) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function edpPortalCsrf(): string
{
    if (!is_string($_SESSION['csrf'] ?? null)) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function edpPortalCsrfValid(mixed $value): bool
{
    return is_string($value) && $value !== '' && is_string($_SESSION['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'], $value);
}

/** Per-IP and per-code limits, using locked private files on ONE local server. */
function edpPortalReserveAttempt(string $root, string $ip, string $code): bool
{
    $folder = $root . '/portal-throttle';
    if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
        throw new RuntimeException('Cannot initialize tracking attempt limits.');
    }
    foreach (['ip:' . $ip => 30, 'code:' . $code => 8] as $identity => $limit) {
        $path = $folder . '/' . hash('sha256', $identity) . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) throw new RuntimeException('Cannot read tracking attempt limits.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock tracking attempts.');
            $record = json_decode(stream_get_contents($handle) ?: '', true);
            if (!is_array($record) || (int)($record['until'] ?? 0) <= time()) {
                $record = ['count' => 0, 'until' => time() + 900];
            }
            if ((int)$record['count'] >= $limit) return false;
            $record['count']++;
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, json_encode($record, JSON_THROW_ON_ERROR)) === false
                || !fflush($handle)) throw new RuntimeException('Cannot record tracking attempt.');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    return true;
}

function edpPortalLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $cookie['path'], 'domain' => $cookie['domain'],
            'secure' => $cookie['secure'], 'httponly' => $cookie['httponly'], 'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

edpPortalStartSession();
$error = '';
$ticket = null;
$pdo = null;
try {
    $pdo = edpPreviewDb(); // hard-coded academic DB name, never the legacy database
    $activeId = (int)($_SESSION['portal_ticket_id'] ?? 0);
    $fingerprint = $_SESSION['portal_fingerprint'] ?? null;
    if ($activeId > 0 && (!is_string($fingerprint) || !edpTrackingSessionValid($pdo, $activeId, $fingerprint))) {
        unset($_SESSION['portal_ticket_id'], $_SESSION['portal_fingerprint']);
        $activeId = 0;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!edpPortalCsrfValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            throw new DomainException('The form expired. Reload this page and try again.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'logout') {
            edpPortalLogout();
            header('Location: track.php', true, 303);
            exit;
        }
        if ($action !== 'login') {
            http_response_code(400);
            throw new DomainException('Invalid tracking request.');
        }
        $code = $_POST['portal_code'] ?? '';
        $pin = $_POST['portal_pin'] ?? '';
        if (!is_string($code) || !is_string($pin)) {
            http_response_code(401);
            throw new DomainException('Invalid tracking code or PIN.');
        }
        // No PINs or user-input tracking credentials in request URLs or logs.
        // Bound attempts before forming a private throttle key from untrusted input.
        $normalizedCode = strlen($code) <= 64 ? strtoupper(trim($code)) : 'INVALID';
        if (!edpPortalReserveAttempt(edpPreviewPrivateRoot(), $_SERVER['REMOTE_ADDR'] ?? 'unknown', $normalizedCode)) {
            http_response_code(429);
            header('Retry-After: 900');
            throw new DomainException('Too many attempts. Please try again later.');
        }
        $match = edpTrackingAuthenticate($pdo, $code, $pin);
        if (!$match) {
            http_response_code(401);
            throw new DomainException('Invalid tracking code or PIN.');
        }
        session_regenerate_id(true);
        $_SESSION = [
            'portal_ticket_id' => $match['ticket_id'],
            'portal_fingerprint' => $match['credential_fingerprint'],
            'csrf' => bin2hex(random_bytes(32)),
            'last_activity' => time(),
        ];
        header('Location: track.php', true, 303);
        exit;
    }
    if ($activeId > 0) {
        $ticket = edpTrackingView($pdo, $activeId);
        if (!$ticket) unset($_SESSION['portal_ticket_id'], $_SESSION['portal_fingerprint']);
    }
} catch (DomainException $e) {
    $error = $e->getMessage();
    if (http_response_code() < 400) http_response_code(422);
    if ($activeId > 0 && $pdo instanceof PDO && edpTrackingSessionValid($pdo, $activeId,
        (string)($_SESSION['portal_fingerprint'] ?? ''))) {
        $ticket = edpTrackingView($pdo, $activeId);
    }
} catch (Throwable $e) {
    // Do not reveal SQL configuration, the customer PIN, paths, or PHP stack traces.
    error_log('RepairTrack EDP tracking service temporarily unavailable: ' . get_class($e));
    http_response_code(503);
    $error = 'Tracking is temporarily unavailable. Please try again later.';
}

$escape = static fn(mixed $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = edpPortalCsrf();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Track your repair | RepairTrack Academic</title>
<link rel="stylesheet" href="assets/track.css">
</head>
<body>
<header class="topbar"><a class="brand" href="track.php"><span aria-hidden="true">⬡</span> RepairTrack</a><span class="tag">Customer tracking · Classroom preview</span></header>
<main class="shell">
<?php if ($ticket): ?>
<section class="hero" aria-label="Your repair ticket">
<div><p class="eyebrow">Your repair</p><h1>Repair progress</h1><p>Latest recorded updates for this repair. Times are shown in Philippine time.</p></div>
<form method="post" action="track.php"><input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Sign out</button></form>
</section>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<div class="summary" role="group" aria-label="Ticket summary">
<div><small>Ticket Code</small><strong><?= $escape($ticket['ticket_code']) ?></strong></div>
<div><small>Branch</small><strong><?= $escape($ticket['branch_name']) ?></strong></div>
<div><small>Case</small><strong><?= $escape(ucfirst(strtolower($ticket['ticket_state']))) ?></strong></div>
<div><small>Received</small><strong><?= $escape(edpTrackingManilaTime($ticket['issued_at'])) ?></strong></div>
</div>
<?php foreach ($ticket['devices'] as $i => $device): ?>
<article class="device">
<div class="device-head"><div><p class="eyebrow">Device <?= $i + 1 ?></p><h2><?= $escape($device['type_label'] ?? $device['item_code']) ?><?= $device['model_snapshot'] ? ' · '.$escape($device['model_snapshot']) : '' ?></h2><p class="device-id"><?= $escape($device['item_code']) ?></p></div><span class="status"><?= $escape($device['status_label']) ?></span></div>
<div class="facts"><div><small>Current location</small><strong><?php $custody = $device['custody_state']; echo $escape(match ($custody) { 'AT_LOCATION' => 'At '.$ticket['branch_name'], 'WITH_CUSTOMER'=>'With customer', 'IN_TRANSIT'=>'In transit', default=>'Location pending confirmation' }); ?></strong></div><div><small>Repair started</small><strong><?= $escape(edpTrackingManilaTime($device['repair_started_at'])) ?></strong></div></div>
<?php if (is_array($device['quote'])): ?>
<div class="quote"><p class="eyebrow">Most recent customer quotation</p><div class="quote-content"><span><?= $escape(match ($device['quote']['quote_status']) { 'APPROVED'=>'Approved', 'DECLINED'=>'Declined', default=>'Awaiting customer decision' }) ?></span><strong>₱<?= $escape(number_format((float)$device['quote']['total_amount'],2)) ?></strong></div><p class="hint">Estimate only. No online payment is collected here.</p></div>
<?php endif; ?>
<section class="events" aria-label="Repair timeline for device <?= $i + 1 ?>"><h3>Repair timeline</h3>
<?php if (!$device['timeline']): ?><p class="muted">No customer updates recorded yet.</p><?php else: ?><ol class="timeline">
<?php foreach ($device['timeline'] as $event): ?><li><div class="line"><strong><?= $escape($event['status_label']) ?></strong><time><?= $escape(edpTrackingManilaTime($event['created_at'])) ?></time></div><?php if ($event['public_summary'] !== null && trim($event['public_summary']) !== ''): ?><p><?= $escape($event['public_summary']) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?>
</section></article>
<?php endforeach; ?>
<p class="footnote">Only customer-visible updates appear here. If information seems incorrect, contact the repair branch and provide the Ticket Code.</p>
<?php else: ?>
<section class="login"><p class="eyebrow">RepairTrack · Customer access</p><h1>Track your repaired device</h1><p>Enter the five-character tracking code on your receipt and the four-digit PIN from the end of your recorded phone number.</p>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<form method="post" action="track.php" class="login-form">
<input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>"><input type="hidden" name="action" value="login">
<label for="portal-code">Tracking code</label><input id="portal-code" name="portal_code" type="text" minlength="5" maxlength="5" pattern="[A-Fa-f0-9]{5}" placeholder="A1B2C" autocapitalize="characters" autocomplete="off" required>
<label for="portal-pin">Portal PIN</label><input id="portal-pin" name="portal_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" minlength="4" autocomplete="off" placeholder="4 digits" required>
<button class="primary" type="submit">View repair progress</button></form>
<p class="hint">Your code and PIN protect your repair information. Avoid sharing them with anyone else.</p>
</section>
<?php endif; ?>
</main><footer>Independent academic demonstration. No payments, uploads, or messages are accepted in the customer portal.</footer>
</body></html>
