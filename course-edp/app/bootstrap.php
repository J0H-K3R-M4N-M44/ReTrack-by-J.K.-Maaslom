<?php
declare(strict_types=1);

/** Shared local-only academic bootstrap; no staff or customer domain modules. */
function edpPreviewHeaders(): void
{
    // Academic preview is intentionally local-only, even if a server is misbound.
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Local academic preview only.');
    }
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}

function edpPreviewPrivateRoot(): string
{
    $configured = getenv('REPAIRTRACK_EDP_PRIVATE_ROOT');
    $absolute = is_string($configured) && (str_starts_with($configured, '/') ||
        (strlen($configured) >= 3 && ctype_alpha($configured[0]) && $configured[1] === ':' &&
            in_array($configured[2], ['/', chr(92)], true)));
    if (!$absolute) {
        throw new RuntimeException('Set an absolute REPAIRTRACK_EDP_PRIVATE_ROOT outside the public directory.');
    }
    if (!is_dir($configured) && !mkdir($configured, 0700, true) && !is_dir($configured)) {
        throw new RuntimeException('Cannot create private academic storage.');
    }
    $root = realpath($configured);
    $public = realpath(__DIR__ . '/../public');
    if ($root === false || $public === false || $root === $public || str_starts_with($root, $public . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Private academic storage must not be within the public web directory.');
    }
    return $root;
}

function edpPreviewDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (getenv('REPAIRTRACK_EDP_DB') !== 'repairtrack_edp') {
        throw new RuntimeException('Academic preview refuses to connect unless REPAIRTRACK_EDP_DB=repairtrack_edp.');
    }
    $user = getenv('REPAIRTRACK_EDP_USER');
    if (!is_string($user) || $user === '') {
        throw new RuntimeException('Use a dedicated REPAIRTRACK_EDP_USER for academic preview.');
    }
    $host = getenv('REPAIRTRACK_EDP_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('REPAIRTRACK_EDP_PORT') ?: 3306);
    if ($port < 1 || $port > 65535 || !preg_match('/^[a-zA-Z0-9.:-]+$/D', $host)) {
        throw new RuntimeException('Invalid academic database host or port.');
    }
    $pdo = new PDO("mysql:host={$host};port={$port};dbname=repairtrack_edp;charset=utf8mb4",
        $user, getenv('REPAIRTRACK_EDP_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}


/** Shared login throttle across academic staff workspaces (single server). */
function edpPreviewCredentialFingerprint(array $user): string
{
    return hash('sha256', $user['password_hash'] . '|' . $user['role_code'] . '|' . $user['email']);
}


function edpPreviewReserveAttempt(string $identity, string $ip): bool
{
    $root = edpPreviewPrivateRoot() . '/auth-throttle';
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Cannot prepare academic login throttling.');
    }
    foreach (['ip:' . $ip => 20, 'email:' . strtolower($identity) => 5] as $key => $limit) {
        $handle = fopen($root . '/' . hash('sha256', $key) . '.json', 'c+');
        if (!$handle) throw new RuntimeException('Cannot open academic login throttle.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock academic login throttle.');
            $record = json_decode(stream_get_contents($handle) ?: '', true);
            if (!is_array($record) || (int)($record['until'] ?? 0) <= time()) {
                $record = ['count' => 0, 'until' => time() + 900];
            }
            if ((int)$record['count'] >= $limit) return false;
            $record['count']++;
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, json_encode($record, JSON_THROW_ON_ERROR)) === false || !fflush($handle)) {
                throw new RuntimeException('Cannot update academic login throttle.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    return true;
}

