<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/actions.php';

/** Isolated technician session: not an intake-staff or customer portal session. */
function edpTechStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_edp_technician');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function edpTechCsrf(): string
{
    if (!is_string($_SESSION['csrf'] ?? null)) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function edpTechCsrfValid(mixed $token): bool
{
    return is_string($token) && $token !== '' && is_string($_SESSION['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'], $token);
}

/** Require a live technician, enabled account and at least one active branch. */
function edpTechStaff(PDO $pdo): ?array
{
    $id = (int)($_SESSION['edp_tech_id'] ?? 0);
    $fingerprint = $_SESSION['edp_tech_fingerprint'] ?? null;
    if ($id < 1 || !is_string($fingerprint)) return null;
    $q = $pdo->prepare("SELECT user_id, full_name, email, password_hash, role_code, is_active
        FROM users WHERE user_id = ? LIMIT 1");
    $q->execute([$id]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['is_active'] !== 1 || $user['role_code'] !== 'TECHNICIAN'
        || !hash_equals($fingerprint, edpPreviewCredentialFingerprint($user))) {
        unset($_SESSION['edp_tech_id'],$_SESSION['edp_tech_fingerprint']);
        return null;
    }
    $q = $pdo->prepare('SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id AND b.is_active=1
        WHERE a.user_id=? LIMIT 1');
    $q->execute([$id]);
    if (!$q->fetchColumn()) {
        unset($_SESSION['edp_tech_id'],$_SESSION['edp_tech_fingerprint']);
        return null;
    }
    unset($user['password_hash']);
    return $user;
}

function edpTechLogin(PDO $pdo, string $email, string $password, string $ip): bool
{
    $email = strtolower(trim($email));
    if (!edpPreviewReserveAttempt($email,$ip)) {
        http_response_code(429);
        header('Retry-After: 900');
        throw new DomainException('Too many attempts. Please try again later.');
    }
    $q = $pdo->prepare('SELECT user_id,full_name,email,password_hash,role_code,is_active
        FROM users WHERE email=? LIMIT 1');
    $q->execute([$email]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password,$user['password_hash'] ?? $dummy) || !$user
        || (int)$user['is_active'] !== 1 || $user['role_code'] !== 'TECHNICIAN') return false;
    $q = $pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b
        ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $q->execute([(int)$user['user_id']]);
    if (!$q->fetchColumn()) return false;
    session_regenerate_id(true);
    $_SESSION = [
        'edp_tech_id' => (int)$user['user_id'],
        'edp_tech_fingerprint' => edpPreviewCredentialFingerprint($user),
        'csrf' => bin2hex(random_bytes(32)), 'last_activity' => time(),
    ];
    return true;
}
function edpTechLogout(): void
{
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $cookie=session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'=>time()-3600,'path'=>$cookie['path'],'domain'=>$cookie['domain'],
            'secure'=>$cookie['secure'],'httponly'=>$cookie['httponly'],'samesite'=>'Lax',
        ]);
    }
    session_destroy();
}

/** Recheck domain identity as well as browser session so CLI callers cannot bypass it. */
function edpTechEnsureActive(PDO $pdo, int $techId): void
{
    $q=$pdo->prepare("SELECT 1 FROM users u
        WHERE u.user_id=? AND u.is_active=1 AND u.role_code='TECHNICIAN'
        AND EXISTS(SELECT 1 FROM user_branch_assignments a JOIN branches b
          ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=u.user_id)");
    $q->execute([$techId]);
    if (!$q->fetchColumn()) throw new DomainException('Technician access is unavailable.');
}

/** Server-side paginated list, never includes another technician's repairs. */
function edpTechQueue(PDO $pdo, int $techId, string $view = 'active', int $page = 1): array
{
    edpTechEnsureActive($pdo,$techId);
    if (!in_array($view,['active','history'],true) || $page < 1 || $page > 10000) {
        throw new DomainException('Choose a valid queue view.');
    }
    $finished = $view === 'history';
    $statusClause = $finished ? "d.status_code IN ('COMPLETED','CANCELLED')"
        : "t.ticket_state='OPEN' AND d.status_code NOT IN ('DRAFT','COMPLETED','CANCELLED')";
    $q=$pdo->prepare("SELECT d.ticket_device_id,d.item_code,d.status_code,d.repair_started_at,
        d.updated_at,dt.label AS device_type,d.make_snapshot,d.model_snapshot,
        t.ticket_code,b.branch_name,rs.label AS status_label,
        (SELECT q.quote_status FROM repair_quotes q WHERE q.ticket_device_id=d.ticket_device_id
         ORDER BY q.version_no DESC LIMIT 1) AS quote_status
        FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.user_id=? AND a.branch_id=t.branch_id
        JOIN repair_statuses rs ON rs.status_code=d.status_code
        LEFT JOIN device_types dt ON dt.device_type_code=d.device_type_code
        WHERE d.assigned_technician_id=? AND {$statusClause}
        ORDER BY d.updated_at DESC,d.ticket_device_id DESC LIMIT 10 OFFSET ?");
    $q->bindValue(1,$techId,PDO::PARAM_INT);
    $q->bindValue(2,$techId,PDO::PARAM_INT);
    $q->bindValue(3,($page-1)*10,PDO::PARAM_INT);
    $q->execute();
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.user_id=? AND a.branch_id=t.branch_id
        WHERE d.assigned_technician_id=? AND {$statusClause}");
    $cnt->execute([$techId,$techId]);
    $total=(int)$cnt->fetchColumn();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/10))];
}

/** A technician may see exactly their own assigned repair at an active branch. */
function edpTechDevice(PDO $pdo, int $techId, int $deviceId): ?array
{
    edpTechEnsureActive($pdo,$techId);
    if ($deviceId < 1) return null;
    $q=$pdo->prepare("SELECT d.ticket_device_id,d.ticket_id,d.item_code,d.status_code,
        d.repair_started_at,d.updated_at,d.reported_symptoms,d.diagnosis,d.condition_notes,
        d.accessories,d.custody_state,d.make_snapshot,d.model_snapshot,d.serial_snapshot,
        t.ticket_code,t.ticket_state,t.branch_id,b.branch_name,
        dt.label AS device_type,rs.label AS status_label
        FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.user_id=? AND a.branch_id=t.branch_id
        JOIN repair_statuses rs ON rs.status_code=d.status_code
        LEFT JOIN device_types dt ON dt.device_type_code=d.device_type_code
        WHERE d.ticket_device_id=? AND d.assigned_technician_id=? LIMIT 1");
    $q->execute([$techId,$deviceId,$techId]);
    $device=$q->fetch(PDO::FETCH_ASSOC);
    if (!$device) return null;
    $q=$pdo->prepare('SELECT e.from_status,e.to_status,e.public_summary,e.internal_note,
        e.created_at FROM ticket_status_events e WHERE e.ticket_device_id=?
        ORDER BY e.created_at ASC,e.status_event_id ASC LIMIT 100');
    $q->execute([$deviceId]);
    $device['events']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT defect_code,details FROM ticket_defects WHERE ticket_device_id=?');
    $q->execute([$deviceId]);
    $device['defects']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT quote_id,quote_status,version_no,labor_amount,parts_amount,other_amount,
        total_amount,sent_at,decision_at FROM repair_quotes
        WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1');
    $q->execute([$deviceId]);
    $device['quote']=$q->fetch(PDO::FETCH_ASSOC) ?: null;
    return $device;
}

/** Derive the select options from the same database transition graph as the status service. */
function edpTechNextStatuses(PDO $pdo, array $device): array
{
    if ($device['ticket_state'] !== 'OPEN' || $device['custody_state'] !== 'AT_LOCATION') return [];
    $allowed = ['DIAGNOSING','AWAITING_CUSTOMER_APPROVAL','AWAITING_PARTS',
        'IN_PROGRESS','TESTING','READY_FOR_PICKUP'];
    $q=$pdo->prepare('SELECT r.status_code,r.label FROM allowed_status_transitions a
        JOIN repair_statuses r ON r.status_code=a.to_status
        WHERE a.from_status=? ORDER BY r.sort_order');
    $q->execute([$device['status_code']]);
    $quote=$device['quote'] ?? null;
    $result=[];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $status=$item['status_code'];
        if (!in_array($status,$allowed,true)) continue;
        if ($status === 'AWAITING_CUSTOMER_APPROVAL' &&
            (!$quote || $quote['quote_status'] !== 'AWAITING_CUSTOMER' || $quote['sent_at'] === null)) continue;
        if (in_array($status,['IN_PROGRESS','READY_FOR_PICKUP'],true) &&
            (!$quote || $quote['quote_status'] !== 'APPROVED')) continue;
        $result[]=$item;
    }
    return $result;
}

/** Internal diagnosis is never copied into a public timeline event. */
function edpTechSaveDiagnosis(PDO $pdo, int $techId, int $deviceId, string $diagnosis): void
{
    $diagnosis=trim($diagnosis);
    $length=function_exists('mb_strlen') ? mb_strlen($diagnosis) : strlen($diagnosis);
    if ($length < 10 || $length > 3000) throw new DomainException('Diagnosis must contain 10–3000 characters.');
    edpInTransaction($pdo,function () use ($pdo,$techId,$deviceId,$diagnosis): void {
        $q=$pdo->prepare('SELECT d.status_code,d.assigned_technician_id,d.custody_state,
            t.branch_id,t.ticket_state FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $q->execute([$deviceId]);$d=$q->fetch(PDO::FETCH_ASSOC);
        if (!$d || $d['ticket_state'] !== 'OPEN' || $d['status_code'] !== 'DIAGNOSING'
            || $d['custody_state'] !== 'AT_LOCATION' || (int)$d['assigned_technician_id'] !== $techId)
            throw new DomainException('Only the assigned diagnosing technician may save this diagnosis.');
        edpActiveBranchStaff($pdo,$techId,(int)$d['branch_id'],['TECHNICIAN']);
        $pdo->prepare('UPDATE ticket_devices SET diagnosis=? WHERE ticket_device_id=?')
            ->execute([$diagnosis,$deviceId]);
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'ticket_device',?,'DIAGNOSIS_SAVED')")->execute([$techId,$deviceId]);
    });
}

/** Technician may prepare a draft estimate, but cannot send or approve it. */
function edpTechSaveQuoteDraft(PDO $pdo, int $techId, int $deviceId,
    string $labor, string $parts, string $other): int
{
    $amounts=[edpCents($labor),edpCents($parts),edpCents($other)];
    $total=array_sum($amounts);
    if ($total <= 0 || $total > 999999999999) throw new DomainException('Enter a positive, valid quotation total.');
    $money=static fn(int $value): string => intdiv($value,100).'.'.str_pad((string)($value%100),2,'0',STR_PAD_LEFT);
    return edpInTransaction($pdo,function () use ($pdo,$techId,$deviceId,$amounts,$total,$money): int {
        $q=$pdo->prepare('SELECT d.assigned_technician_id,d.status_code,d.custody_state,d.diagnosis,
            t.branch_id,t.ticket_state FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $q->execute([$deviceId]);$d=$q->fetch(PDO::FETCH_ASSOC);
        if (!$d || $d['ticket_state'] !== 'OPEN' || $d['custody_state'] !== 'AT_LOCATION'
            || !in_array($d['status_code'],['DIAGNOSING','AWAITING_PARTS'],true)
            || (int)$d['assigned_technician_id'] !== $techId || trim((string)$d['diagnosis'])==='')
            throw new DomainException('An assigned, diagnosed repair is required for a quotation draft.');
        edpActiveBranchStaff($pdo,$techId,(int)$d['branch_id'],['TECHNICIAN']);
        $q=$pdo->prepare('SELECT quote_id,quote_status,version_no,prepared_by_user_id FROM repair_quotes
            WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$deviceId]);$existing=$q->fetch(PDO::FETCH_ASSOC);
        $values=[$money($amounts[0]),$money($amounts[1]),$money($amounts[2]),$money($total)];
        if ($existing && $existing['quote_status'] === 'DRAFT') {
            if ((int)$existing['prepared_by_user_id'] !== $techId)
                throw new DomainException('This quotation draft belongs to another staff member.');
            $q=$pdo->prepare('UPDATE repair_quotes SET labor_amount=?,parts_amount=?,other_amount=?,total_amount=?
                WHERE quote_id=?');
            $q->execute([...$values,(int)$existing['quote_id']]);
            $quoteId=(int)$existing['quote_id'];
        } elseif ($existing && !in_array($existing['quote_status'], ['DECLINED','EXPIRED','SUPERSEDED'], true)) {
            throw new DomainException('The existing quotation is already awaiting a decision or approved.');
        } else {
            $version=$existing ? ((int)$existing['version_no']+1) : 1;
            $q=$pdo->prepare("INSERT INTO repair_quotes
                (ticket_device_id,version_no,quote_status,labor_amount,parts_amount,other_amount,total_amount,prepared_by_user_id)
                VALUES (?,?,'DRAFT',?,?,?,?,?)");
            $q->execute([$deviceId,$version,...$values,$techId]);
            $quoteId=(int)$pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_quotes',?,'TECHNICIAN_QUOTE_DRAFTED')")->execute([$techId,$quoteId]);
        return $quoteId;
    });
}

function edpTechLocalTime(?string $utc): string
{
    if ($utc===null || $utc==='') return 'Not started';
    return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · g:i A');
}
