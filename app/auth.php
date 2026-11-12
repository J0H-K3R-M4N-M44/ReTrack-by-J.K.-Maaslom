<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/web.php';
require_once __DIR__ . '/login_throttle.php';

// Start the staff session and expire it after 30 minutes without activity.
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_admin');

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['last_activity'] = time();
}

// Give forms a random token tied to this session to reject unrelated submissions.
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfIsValid(string $sentToken): bool
{
    return $sentToken !== '' &&
        isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $sentToken);
}

// Keep the admin-only helper for existing CLI/tests. The shared sign-in page
// uses loginAccount and sends non-admin accounts to their own workspace.
function loginAdmin(string $email, string $password): bool
{
    return loginAccount($email, $password, true);
}

function loginAccount(string $email, string $password, bool $adminOnly = false): bool
{
    $query = getPDO()->prepare(
        "SELECT user_id, full_name, email, role, password_hash FROM users
        WHERE email = ? AND is_active = 1" .
            ($adminOnly ? " AND role = 'ADMIN'" : '') .
            ' LIMIT 1',
    );
    $query->execute([strtolower(trim($email))]);
    $user = $query->fetch();
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $matches = password_verify($password, $user['password_hash'] ?? $dummyHash);
    if (!$user || !$matches) {
        return false;
    }
    $update = getPDO()->prepare(
        'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE user_id = ?',
    );
    $update->execute([$user['user_id']]);
    session_regenerate_id(true);
    // Clear old role, review and invitation state when the account changes.
    $_SESSION = [
        'user_id' => (int) $user['user_id'],
        'account_role' => $user['role'],
        'credential_version' => hash('sha256', $user['password_hash'] . '|' . $user['email']),
        'last_activity' => time(),
    ];
    if ($user['role'] === 'ADMIN') {
        $_SESSION['admin_user_id'] = (int) $user['user_id'];
        $_SESSION['admin_name'] = $user['full_name'];
    }
    return true;
}

function accountHome(): string
{
    return ($_SESSION['account_role'] ?? 'ADMIN') === 'ADMIN' ? 'dashboard.php' : 'staff_home.php';
}

// Fresh role/active checks and a password-hash fingerprint invalidate disabled
// accounts and sessions whose password or role has changed, without a new column.
function currentAccount(): ?array
{
    $id = (int) ($_SESSION['user_id'] ?? ($_SESSION['admin_user_id'] ?? 0));
    if (!$id) {
        return null;
    }
    $query = getPDO()->prepare(
        'SELECT user_id, full_name, email, role, password_hash FROM users WHERE user_id = ? AND is_active = 1 LIMIT 1',
    );
    $query->execute([$id]);
    $user = $query->fetch();
    if (
        !$user ||
        ($_SESSION['account_role'] ?? null) !== $user['role'] ||
        (!isset($_SESSION['credential_version']) ||
            !hash_equals(
                $_SESSION['credential_version'],
                hash('sha256', $user['password_hash'] . '|' . $user['email']),
            ))
    ) {
        logoutAdmin();
        return null;
    }
    unset($user['password_hash']);
    return $user;
}

function currentAdmin(): ?array
{
    $user = currentAccount();
    return $user && $user['role'] === 'ADMIN' ? $user : null;
}

function requireAccount(): array
{
    try {
        $user = currentAccount();
    } catch (Throwable $error) {
        error_log('RepairTrack account check: ' . $error->getMessage());
        unavailablePage('Account access is temporarily unavailable. Please try again later.');
    }
    if (!$user) {
        header('Location: login.php');
        exit();
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireAccount();
    if ($user['role'] !== 'ADMIN') {
        unavailablePage('This page is for administrators. Return to your staff workspace.', 403);
    }
    return $user;
}

function logoutAdmin(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
