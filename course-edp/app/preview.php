<?php
declare(strict_types=1);

/** Isolated academic web shell. Never include the legacy app/db.php or app/auth.php. */
require_once __DIR__ . '/intake.php';

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
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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

function edpPreviewSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_edp_preview');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function edpPreviewCsrf(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function edpPreviewCsrfValid(mixed $token): bool
{
    return is_string($token) && $token !== '' && isset($_SESSION['csrf']) &&
        is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function edpPreviewCredentialFingerprint(array $user): string
{
    return hash('sha256', $user['password_hash'] . '|' . $user['role_code'] . '|' . $user['email']);
}

/** Check live account state on every protected request, not just at login. */
function edpPreviewCurrentStaff(PDO $pdo): ?array
{
    $id = (int)($_SESSION['edp_staff_id'] ?? 0);
    if (!$id || !is_string($_SESSION['edp_staff_fingerprint'] ?? null)) return null;
    $q = $pdo->prepare('SELECT user_id,full_name,email,role_code,password_hash,is_active FROM users WHERE user_id=? LIMIT 1');
    $q->execute([$id]);
    $user = $q->fetch();
    if (!$user || (int)$user['is_active'] !== 1 ||
        !in_array($user['role_code'], ['ADMIN','MANAGER','INTAKE_AGENT'], true) ||
        !hash_equals($_SESSION['edp_staff_fingerprint'], edpPreviewCredentialFingerprint($user))) {
        unset($_SESSION['edp_staff_id'], $_SESSION['edp_staff_fingerprint']);
        return null;
    }
    $branches = $pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b
        ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $branches->execute([$id]);
    if (!$branches->fetchColumn()) {
        unset($_SESSION['edp_staff_id'], $_SESSION['edp_staff_fingerprint']);
        return null;
    }
    unset($user['password_hash']);
    return $user;
}

/** File-based IP + email throttling, only for one local academic preview server. */
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

function edpPreviewLogin(PDO $pdo, string $email, string $password, string $ip): bool
{
    $email = strtolower(trim($email));
    if (!edpPreviewReserveAttempt($email, $ip)) {
        http_response_code(429);
        header('Retry-After: 900');
        throw new DomainException('Too many sign-in attempts. Try again later.');
    }
    $stmt = $pdo->prepare('SELECT user_id,full_name,email,role_code,password_hash,is_active FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user ||
        (int)$user['is_active'] !== 1 || !in_array($user['role_code'], ['ADMIN','MANAGER','INTAKE_AGENT'], true)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND b.is_active=1 LIMIT 1');
    $stmt->execute([$user['user_id']]);
    if (!$stmt->fetchColumn()) return false;
    session_regenerate_id(true);
    $_SESSION = [
        'edp_staff_id' => (int)$user['user_id'],
        'edp_staff_fingerprint' => edpPreviewCredentialFingerprint($user),
        'csrf' => bin2hex(random_bytes(32)),
        'last_activity' => time(),
    ];
    return true;
}

function edpPreviewBranches(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT b.branch_id,b.branch_name FROM branches b
        JOIN user_branch_assignments a ON a.branch_id=b.branch_id
        WHERE a.user_id=? AND b.is_active=1 ORDER BY b.branch_name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Draft list is scoped to the author; manager/admin can access team drafts by URL. */
function edpPreviewDrafts(PDO $pdo, int $userId): array
{
    $q = $pdo->prepare("SELECT t.ticket_id,t.intake_at,b.branch_name,COUNT(d.ticket_device_id) AS device_count
        FROM repair_tickets t JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.branch_id=t.branch_id AND a.user_id=?
        LEFT JOIN ticket_devices d ON d.ticket_id=t.ticket_id
        WHERE t.created_by_user_id=? AND t.ticket_state='DRAFT'
        GROUP BY t.ticket_id,t.intake_at,b.branch_name ORDER BY t.intake_at DESC,t.ticket_id DESC LIMIT 50");
    $q->execute([$userId,$userId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function edpPreviewDraft(PDO $pdo, int $staffId, int $draftId): ?array
{
    $stmt = $pdo->prepare("SELECT t.ticket_id,t.branch_id,t.ticket_state,t.created_by_user_id,b.branch_name
        FROM repair_tickets t
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.branch_id=t.branch_id AND a.user_id=?
        JOIN users u ON u.user_id=a.user_id AND u.is_active=1
        WHERE t.ticket_id=? AND t.ticket_state='DRAFT'
        AND (t.created_by_user_id=? OR u.role_code IN ('ADMIN','MANAGER'))");
    $stmt->execute([$staffId,$draftId,$staffId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) return null;
    $stmt = $pdo->prepare('SELECT d.ticket_device_id,d.sequence_no,d.device_type_code,d.make_snapshot,
        d.model_snapshot,d.reported_symptoms,COALESCE(f.defect_code,\'\') AS defect_code
        FROM ticket_devices d LEFT JOIN ticket_defects f ON f.ticket_device_id=d.ticket_device_id
        WHERE d.ticket_id=? ORDER BY d.sequence_no');
    $stmt->execute([$draftId]);
    $ticket['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $ticket;
}

/** Existing customers are searchable only if seen at this branch previously. */
function edpPreviewCustomerMatches(PDO $pdo, int $branchId, string $needle): array
{
    $needle = trim($needle);
    if (strlen($needle) < 2 || strlen($needle) > 80) return [];
    $needle = str_replace(['!','%','_'], ['!!','!%','!_'], $needle);
    $stmt = $pdo->prepare("SELECT c.customer_id,c.full_name,RIGHT(c.phone,4) AS phone_suffix
        FROM customers c WHERE (c.full_name LIKE ? ESCAPE '!' OR c.phone LIKE ? ESCAPE '!')
        AND EXISTS (SELECT 1 FROM repair_tickets t WHERE t.customer_id=c.customer_id
            AND t.branch_id=? AND t.ticket_state NOT IN ('DRAFT','ABANDONED'))
        ORDER BY c.full_name LIMIT 20");
    $stmt->execute(['%'.$needle.'%','%'.$needle.'%',$branchId]);
    return $stmt->fetchAll();
}

/** Reject browser-selected customer IDs outside the staff member's ticket branch. */
function edpPreviewVerifyExistingCustomer(PDO $pdo, int $customerId, int $branchId, string $confirmedPhone): void
{
    $stmt = $pdo->prepare("SELECT c.phone FROM customers c WHERE c.customer_id=?
        AND EXISTS (SELECT 1 FROM repair_tickets t WHERE t.customer_id=c.customer_id
            AND t.branch_id=? AND t.ticket_state NOT IN ('DRAFT','ABANDONED'))");
    $stmt->execute([$customerId,$branchId]);
    $registeredPhone = $stmt->fetchColumn();
    if (!is_string($registeredPhone) || !preg_match('/^[0-9]{4,15}$/D', $confirmedPhone) ||
        !hash_equals($registeredPhone, $confirmedPhone)) {
        throw new DomainException('Confirm the full recorded phone number for this returning customer.');
    }
}

/** Prompt staff to reuse a record with this number instead of creating duplicates. */
function edpPreviewRejectDuplicateCustomer(PDO $pdo, int $branchId, string $phone): void
{
    $q = $pdo->prepare("SELECT 1 FROM customers c WHERE c.phone=? AND
        EXISTS (SELECT 1 FROM repair_tickets t WHERE t.customer_id=c.customer_id
            AND t.branch_id=? AND t.ticket_state NOT IN ('DRAFT','ABANDONED')) LIMIT 1");
    $q->execute([$phone,$branchId]);
    if ($q->fetchColumn()) throw new DomainException('This phone has a branch record. Search for the returning customer.');
}


/** Convert a PHP ini upload size (e.g. 6M) to bytes, not decimal megabytes. */
function edpPreviewIniBytes(string $value): int
{
    $value = trim($value);
    if (!preg_match('/^([0-9]+)\s*([KMG]?)$/iD',$value,$m)) return 0;
    $multiple = match (strtoupper($m[2])) { 'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1 };
    return (int)$m[1]*$multiple;
}

function edpPreviewUploadCapacity(): int
{
    return min(edpPreviewIniBytes((string)ini_get('upload_max_filesize')),
        edpPreviewIniBytes((string)ini_get('post_max_size')));
}

/** Upload only a verified image to a server-generated private identifier. */
function edpPreviewStageSignature(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
        throw new DomainException('Upload a signed intake image (PNG, JPEG or WebP).');
    }
    $size = filesize($upload['tmp_name']);
    if ($size === false || $size < 1 || $size > 5242880) {
        throw new DomainException('The signature image must be 5 MiB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $types = ['image/png'=>'.png','image/jpeg'=>'.jpg','image/webp'=>'.webp'];
    $dimensions = @getimagesize($upload['tmp_name']);
    if (!isset($types[$mime]) || !is_array($dimensions) ||
        (int)$dimensions[0] > 4096 || (int)$dimensions[1] > 4096) {
        throw new DomainException('Choose a valid PNG, JPEG or WebP image of reasonable dimensions.');
    }
    $root = edpPreviewPrivateRoot();
    $folder = $root . '/signed';
    if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {
        throw new RuntimeException('Unable to prepare signed-image storage.');
    }
    $relative = 'signed/' . bin2hex(random_bytes(20)) . $types[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $root . '/' . $relative)) {
        throw new RuntimeException('Unable to save signed image.');
    }
    chmod($root . '/' . $relative, 0600);
    return $relative;
}

function edpPreviewCleanStage(string $relative): void
{
    if (preg_match('~^signed/[0-9a-f]{40}\.(?:png|jpg|webp)$~D', $relative)) {
        @unlink(edpPreviewPrivateRoot() . '/' . $relative);
    }
}

function edpPreviewEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
