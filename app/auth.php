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

// Look up an active admin, verify the password hash, then start their signed-in session.
function loginAdmin(string $email, string $password): bool
{
    $email = strtolower(trim($email));

    $statement = getPDO()->prepare(
        "SELECT user_id, full_name, password_hash
         FROM users
         WHERE email = ? AND role = 'ADMIN' AND is_active = 1
         LIMIT 1",
    );
    $statement->execute([$email]);
    $user = $statement->fetch();

    // Unknown accounts still perform a bcrypt check to reduce timing differences.
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $passwordMatches = password_verify($password, $user['password_hash'] ?? $dummyHash);
    if (!$user || !$passwordMatches) {
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['admin_user_id'] = (int) $user['user_id'];
    $_SESSION['admin_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    $updateLogin = getPDO()->prepare(
        'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE user_id = ?',
    );
    $updateLogin->execute([$user['user_id']]);

    return true;
}

// Recheck the database so disabled accounts cannot keep using an old session.
function currentAdmin(): ?array
{
    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $statement = getPDO()->prepare(
        "SELECT user_id, full_name, email, role
         FROM users
         WHERE user_id = ? AND role = 'ADMIN' AND is_active = 1
         LIMIT 1",
    );
    $statement->execute([(int) $_SESSION['admin_user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        logoutAdmin();
        return null;
    }

    $_SESSION['admin_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    return $user;
}

function requireAdmin(): array
{
    try {
        $user = currentAdmin();
    } catch (Throwable $error) {
        error_log('RepairTrack session check: ' . $error->getMessage());
        unavailablePage('Account access is temporarily unavailable. Please try again later.');
    }

    if ($user === null) {
        header('Location: login.php');
        exit();
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
