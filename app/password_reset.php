<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/emailjs.php';

/**
 * Send a generic recovery email when an active staff email is found.
 * The raw token only appears in the email; the database stores its SHA-256 hash.
 */
function requestAdminPasswordReset(string $email): void
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Recovery configuration is missing.');
    }

    $config = $configOverride ?? require $configFile;
    $appUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
    if (
        !filter_var($appUrl, FILTER_VALIDATE_URL) ||
        !in_array(parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)
    ) {
        throw new RuntimeException('Recovery needs a valid app_url.');
    }

    $pdo = $pdoOverride ?? getPDO();
    $findUser = $pdo->prepare(
        "SELECT user_id, full_name FROM users
         WHERE email = ? AND is_active = 1
         LIMIT 1",
    );
    $findUser->execute([$email]);
    $user = $findUser->fetch();

    if (!$user) {
        return;
    }

    $userId = (int) $user['user_id'];
    $recentRequest = $pdo->prepare(
        'SELECT reset_token_id
         FROM password_reset_tokens
         WHERE user_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 MINUTE
         LIMIT 1',
    );
    $recentRequest->execute([$userId]);

    if ($recentRequest->fetch()) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $now = gmdate('Y-m-d H:i:s');
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 1800);

    $pdo->beginTransaction();
    try {
        $deleteOldTokens = $pdo->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL',
        );
        $deleteOldTokens->execute([$userId]);

        $insertToken = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?)',
        );
        $insertToken->execute([$userId, $tokenHash, $expiresAt, $now]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $resetUrl = $appUrl . '/admin/reset_password.php?token=' . rawurlencode($token);
    try {
        emailJsSendTemplate($config, 'password_reset', [
            'to_email' => $email,
            'to_name' => (string) ($user['full_name'] ?? 'RepairTrack staff member'),
            'subject' => 'RepairTrack account password reset',
            'reset_url' => $resetUrl,
            'expires_minutes' => 30,
        ], $testTransport);
    } catch (Throwable $error) {
        // Keep the public response generic. An unsuccessful handoff invalidates
        // the token so no unusable reset credential remains active.
        $deleteToken = $pdo->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ?',
        );
        $deleteToken->execute([$userId, $tokenHash]);
        error_log('RepairTrack password recovery email could not be sent: ' . $error->getMessage());
    }
}

/** Change the password only when an unused, unexpired token is submitted. */
function completeAdminPasswordReset(string $token, string $newPassword): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || !passwordIsAcceptable($newPassword)) {
        return false;
    }

    $pdo = $pdoOverride ?? getPDO();
    $tokenHash = hash('sha256', $token);

    $pdo->beginTransaction();
    try {
        $findToken = $pdo->prepare(
            "SELECT prt.reset_token_id, prt.user_id
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.user_id = prt.user_id
             WHERE prt.token_hash = ?
               AND prt.used_at IS NULL
               AND prt.expires_at > UTC_TIMESTAMP()
               AND u.is_active = 1
             LIMIT 1
             FOR UPDATE",
        );
        $findToken->execute([$tokenHash]);
        $reset = $findToken->fetch();

        if (!$reset) {
            $pdo->rollBack();
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassword = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $updatePassword->execute([$passwordHash, (int) $reset['user_id']]);

        $useTokens = $pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND used_at IS NULL',
        );
        $useTokens->execute([(int) $reset['user_id']]);

        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
