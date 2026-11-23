<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/actions.php';

/** Separate manager/admin session. This preview does not reuse a technician or customer cookie. */
function edpManagerStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('repairtrack_edp_manager');
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
function edpManagerCsrf(): string
{
    if (!is_string($_SESSION['csrf'] ?? null)) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function edpManagerCsrfValid(mixed $token): bool
{
    return is_string($token) && $token !== '' && is_string($_SESSION['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'], $token);
}
function edpManagerLogout(): void
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
function edpManagerCurrent(PDO $pdo): ?array
{
    $id = (int)($_SESSION['edp_manager_id'] ?? 0);
    $fingerprint = $_SESSION['edp_manager_fingerprint'] ?? null;
    if ($id < 1 || !is_string($fingerprint)) return null;
    $q = $pdo->prepare('SELECT user_id,full_name,email,password_hash,role_code,is_active FROM users WHERE user_id=?');
    $q->execute([$id]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['is_active'] !== 1
        || !in_array($user['role_code'], ['ADMIN','MANAGER'], true)
        || !hash_equals($fingerprint, edpPreviewCredentialFingerprint($user))) {
        unset($_SESSION['edp_manager_id'], $_SESSION['edp_manager_fingerprint']);
        return null;
    }
    $q = $pdo->prepare('SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $q->execute([$id]);
    if (!$q->fetchColumn()) {
        unset($_SESSION['edp_manager_id'], $_SESSION['edp_manager_fingerprint']);
        return null;
    }
    unset($user['password_hash']);
    return $user;
}
function edpManagerLogin(PDO $pdo, string $email, string $password, string $ip): bool
{
    $email = strtolower(trim($email));
    if (!edpPreviewReserveAttempt($email, $ip)) {
        http_response_code(429);
        header('Retry-After: 900');
        throw new DomainException('Too many sign-in attempts. Try again later.');
    }
    $q = $pdo->prepare('SELECT user_id,full_name,email,password_hash,role_code,is_active FROM users WHERE email=? LIMIT 1');
    $q->execute([$email]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user
        || (int)$user['is_active'] !== 1 || !in_array($user['role_code'], ['MANAGER','ADMIN'], true)) return false;
    $q = $pdo->prepare('SELECT 1 FROM user_branch_assignments a JOIN branches b
        ON b.branch_id=a.branch_id AND b.is_active=1 WHERE a.user_id=? LIMIT 1');
    $q->execute([(int)$user['user_id']]);
    if (!$q->fetchColumn()) return false;
    session_regenerate_id(true);
    $_SESSION = [
        'edp_manager_id' => (int)$user['user_id'],
        'edp_manager_fingerprint' => edpPreviewCredentialFingerprint($user),
        'csrf' => bin2hex(random_bytes(32)), 'last_activity' => time(),
    ];
    return true;
}

/** All branch reads are scoped to an active manager/admin at that exact branch. */
function edpManagerBranches(PDO $pdo, int $actorId): array
{
    $q = $pdo->prepare("SELECT b.branch_id,b.branch_name FROM branches b
        JOIN user_branch_assignments a ON a.branch_id=b.branch_id AND a.user_id=?
        JOIN users u ON u.user_id=a.user_id AND u.is_active=1
        WHERE b.is_active=1 AND u.role_code IN ('ADMIN','MANAGER') ORDER BY b.branch_name");
    $q->execute([$actorId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function edpManagerCheckBranch(PDO $pdo, int $actorId, int $branchId): void
{
    foreach (edpManagerBranches($pdo, $actorId) as $branch) {
        if ((int)$branch['branch_id'] === $branchId) return;
    }
    throw new DomainException('This branch is not assigned to your account.');
}
/** Simple paginated assignment and approval queue: ten devices per page. */
function edpManagerQueue(PDO $pdo, int $actorId, int $branchId, string $filter='unassigned', int $page=1): array
{
    edpManagerCheckBranch($pdo, $actorId, $branchId);
    if (!in_array($filter, ['unassigned','active','approval'], true) || $page < 1 || $page > 10000) {
        throw new DomainException('Choose a valid manager queue.');
    }
    $clause = match ($filter) {
        'unassigned' => 'd.assigned_technician_id IS NULL',
        'approval' => "q.quote_status IN ('DRAFT','AWAITING_CUSTOMER')",
        default => 'd.assigned_technician_id IS NOT NULL',
    };
    $base = " FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        LEFT JOIN users u ON u.user_id=d.assigned_technician_id
        LEFT JOIN repair_quotes q ON q.quote_id=(SELECT q2.quote_id FROM repair_quotes q2
            WHERE q2.ticket_device_id=d.ticket_device_id ORDER BY q2.version_no DESC LIMIT 1)
        WHERE t.branch_id=? AND t.ticket_state='OPEN'
          AND d.status_code NOT IN ('DRAFT','COMPLETED','CANCELLED') AND {$clause}";
    $q = $pdo->prepare('SELECT d.ticket_device_id,d.item_code,d.status_code,t.ticket_code,
        d.assigned_technician_id,u.full_name AS technician_name,q.quote_status,q.total_amount'.$base.
        ' ORDER BY d.updated_at DESC,d.ticket_device_id DESC LIMIT 10 OFFSET ?');
    $q->bindValue(1, $branchId, PDO::PARAM_INT);
    $q->bindValue(2, ($page - 1) * 10, PDO::PARAM_INT);
    $q->execute();
    $count = $pdo->prepare('SELECT COUNT(*)'.$base);
    $count->execute([$branchId]);
    $total = (int)$count->fetchColumn();
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC), 'total'=>$total,'page'=>$page,
        'pages'=>max(1,(int)ceil($total/10)), 'filter'=>$filter];
}
/** Show safe staff names and actual active queue size; hide customer contact details. */
function edpManagerTechnicians(PDO $pdo, int $actorId, int $branchId): array
{
    edpManagerCheckBranch($pdo,$actorId,$branchId);
    $q = $pdo->prepare("SELECT u.user_id,u.full_name,
        (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
         WHERE d.assigned_technician_id=u.user_id AND t.ticket_state='OPEN'
         AND d.status_code NOT IN ('DRAFT','COMPLETED','CANCELLED')) AS active_count
        FROM users u JOIN user_branch_assignments a ON a.user_id=u.user_id
        WHERE a.branch_id=? AND u.is_active=1 AND u.role_code='TECHNICIAN'
        ORDER BY u.full_name");
    $q->execute([$branchId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function edpManagerDevice(PDO $pdo, int $actorId, int $deviceId): ?array
{
    if ($deviceId < 1) return null;
    $q = $pdo->prepare("SELECT d.ticket_device_id,d.item_code,d.ticket_id,d.status_code,
        d.assigned_technician_id,d.reported_symptoms,d.diagnosis,d.custody_state,
        t.ticket_code,t.ticket_state,t.branch_id,b.branch_name,u.full_name AS technician_name
        FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        JOIN branches b ON b.branch_id=t.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.user_id=? AND a.branch_id=t.branch_id
        JOIN users actor ON actor.user_id=a.user_id AND actor.is_active=1
            AND actor.role_code IN ('ADMIN','MANAGER')
        LEFT JOIN users u ON u.user_id=d.assigned_technician_id
        WHERE d.ticket_device_id=? LIMIT 1");
    $q->execute([$actorId,$deviceId]);
    $device = $q->fetch(PDO::FETCH_ASSOC);
    if (!$device) return null;
    $q = $pdo->prepare('SELECT quote_id,version_no,quote_status,labor_amount,parts_amount,other_amount,
        total_amount,prepared_by_user_id,sent_at,expires_at,decision_at
        FROM repair_quotes WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1');
    $q->execute([$deviceId]);
    $device['quote']=$q->fetch(PDO::FETCH_ASSOC)?:null;
    return $device;
}

/** Assign/reassign with the same serialized domain action the technician queue uses. */
function edpManagerAssign(PDO $pdo, int $actorId, int $deviceId, int $technicianId): void
{
    edpInTransaction($pdo,function() use ($pdo,$actorId,$deviceId,$technicianId): void {
        $q=$pdo->prepare('SELECT d.assigned_technician_id,t.branch_id,t.ticket_state
            FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $q->execute([$deviceId]);$device=$q->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['ticket_state']!=='OPEN') {
            throw new DomainException('Repair unavailable for assignment.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$device['branch_id'],['MANAGER','ADMIN']);
        if ((int)$device['assigned_technician_id']===$technicianId) return;
        // Nested action uses its own unique savepoint; all changes remain atomic.
        edpAssignTechnician($pdo,$actorId,$deviceId,$technicianId,5);
        $q=$pdo->prepare('SELECT quote_id,quote_status FROM repair_quotes
            WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$deviceId]);$quote=$q->fetch(PDO::FETCH_ASSOC);
        if ($quote && $quote['quote_status']==='DRAFT') {
            $pdo->prepare("UPDATE repair_quotes SET quote_status='SUPERSEDED' WHERE quote_id=?")
                ->execute([(int)$quote['quote_id']]);
            $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
                VALUES (?,'repair_quotes',?,'UNSENT_QUOTE_SUPERSEDED_ON_REASSIGNMENT')")
                ->execute([$actorId,(int)$quote['quote_id']]);
        }
    });
}

/** Publish ONLY the current technician-prepared draft, then advance repair to customer approval. */
function edpManagerSendQuote(PDO $pdo, int $actorId, int $deviceId): void
{
    edpInTransaction($pdo,function() use ($pdo,$actorId,$deviceId): void {
        $q = $pdo->prepare("SELECT d.ticket_device_id,d.assigned_technician_id,d.status_code,
            d.custody_state,d.diagnosis,t.branch_id,t.ticket_state
            FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE");
        $q->execute([$deviceId]);
        $device = $q->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['ticket_state']!=='OPEN' || $device['status_code']!=='DIAGNOSING'
            || $device['custody_state']!=='AT_LOCATION' || trim((string)$device['diagnosis'])===''
            || $device['assigned_technician_id']===null) {
            throw new DomainException('A diagnosed, assigned repair in Diagnosing is required.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$device['branch_id'],['ADMIN','MANAGER']);
        edpActiveBranchStaff($pdo,(int)$device['assigned_technician_id'],(int)$device['branch_id'],['TECHNICIAN']);
        $q = $pdo->prepare('SELECT quote_id,quote_status,total_amount,prepared_by_user_id
            FROM repair_quotes WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$deviceId]);
        $quote=$q->fetch(PDO::FETCH_ASSOC);
        if (!$quote || $quote['quote_status']!=='DRAFT' || edpCents((string)$quote['total_amount'])<=0
            || (int)$quote['prepared_by_user_id']!==(int)$device['assigned_technician_id']) {
            throw new DomainException('Only the current assigned technician draft may be sent.');
        }
        $q=$pdo->prepare("SELECT 1 FROM allowed_status_transitions
            WHERE from_status='DIAGNOSING' AND to_status='AWAITING_CUSTOMER_APPROVAL'");
        $q->execute();
        if (!$q->fetchColumn()) throw new DomainException('This transition is not configured.');
        $pdo->prepare("UPDATE repair_quotes SET quote_status='AWAITING_CUSTOMER',
            sent_at=UTC_TIMESTAMP(),expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY)
            WHERE quote_id=?")->execute([(int)$quote['quote_id']]);
        $pdo->prepare("UPDATE ticket_devices SET status_code='AWAITING_CUSTOMER_APPROVAL'
            WHERE ticket_device_id=?")->execute([$deviceId]);
        $pdo->prepare("INSERT INTO ticket_status_events
            (ticket_device_id,from_status,to_status,actor_user_id,public_summary,is_customer_visible,created_at)
            VALUES (?,'DIAGNOSING','AWAITING_CUSTOMER_APPROVAL',?,
            'Quotation sent for customer review',1,UTC_TIMESTAMP())")
            ->execute([$deviceId,$actorId]);
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_quotes',?,'MANAGER_QUOTE_SENT')")
            ->execute([$actorId,(int)$quote['quote_id']]);
    });
}

/** Record an *offline*, staff-attested customer decision, NOT customer portal authentication. */
function edpManagerQuoteDecision(PDO $pdo, int $actorId, int $deviceId, string $decision,
    string $channel, string $reference, bool $attested): void
{
    if (!in_array($decision,['APPROVED','DECLINED'],true)
        || !in_array($channel,['IN_PERSON','PHONE_CALL','WRITTEN'],true)
        || !$attested || strlen(trim($reference))<8 || strlen(trim($reference))>160) {
        throw new DomainException('Record the confirmed customer decision, channel and reference.');
    }
    $reference=trim($reference);
    edpInTransaction($pdo,function() use ($pdo,$actorId,$deviceId,$decision,$channel,$reference): void {
        $q=$pdo->prepare('SELECT d.status_code,d.custody_state,t.ticket_state,t.branch_id
            FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $q->execute([$deviceId]);$device=$q->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['ticket_state']!=='OPEN' || $device['custody_state']!=='AT_LOCATION'
            || $device['status_code']!=='AWAITING_CUSTOMER_APPROVAL') {
            throw new DomainException('This repair is not awaiting a customer decision.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$device['branch_id'],['ADMIN','MANAGER']);
        $q=$pdo->prepare('SELECT quote_id,quote_status,sent_at,expires_at FROM repair_quotes
            WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$deviceId]);$quote=$q->fetch(PDO::FETCH_ASSOC);
        if (!$quote || $quote['quote_status']!=='AWAITING_CUSTOMER' || $quote['sent_at']===null) {
            throw new DomainException('No customer-visible pending quotation is available.');
        }
        // Do not accept approvals for quotes whose deadline has passed.
        if ($decision==='APPROVED' && ($quote['expires_at']===null
            || new DateTimeImmutable($quote['expires_at'],new DateTimeZone('UTC'))
               <= new DateTimeImmutable('now',new DateTimeZone('UTC')))) {
            throw new DomainException('This quotation has expired and cannot be approved.');
        }
        $pdo->prepare('UPDATE repair_quotes SET quote_status=?,decision_recorded_by=?,
            decision_at=UTC_TIMESTAMP() WHERE quote_id=?')
            ->execute([$decision,$actorId,(int)$quote['quote_id']]);
        if ($decision==='DECLINED') {
            edpManagerReturnToDiagnosis($pdo,$actorId,$deviceId,'Customer declined the quotation');
        }
        $details=json_encode(['source'=>'STAFF_ATTESTED_OFFLINE','decision'=>$decision,
            'channel'=>$channel,'reference'=>$reference],JSON_THROW_ON_ERROR);
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code,details_json)
            VALUES (?,'repair_quotes',?,'OFFLINE_CUSTOMER_DECISION',?)")
            ->execute([$actorId,(int)$quote['quote_id'],$details]);
    });
}
/** Expired quotes return to diagnosis, allowing a revised technician estimate. */
function edpManagerExpireQuote(PDO $pdo, int $actorId, int $deviceId): void
{
    edpInTransaction($pdo,function() use ($pdo,$actorId,$deviceId): void {
        $q=$pdo->prepare('SELECT d.status_code,d.custody_state,t.branch_id,t.ticket_state
            FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
            WHERE d.ticket_device_id=? FOR UPDATE');
        $q->execute([$deviceId]);$device=$q->fetch(PDO::FETCH_ASSOC);
        if (!$device || $device['status_code']!=='AWAITING_CUSTOMER_APPROVAL'
            || $device['ticket_state']!=='OPEN' || $device['custody_state']!=='AT_LOCATION') {
            throw new DomainException('Repair is not awaiting customer approval.');
        }
        edpActiveBranchStaff($pdo,$actorId,(int)$device['branch_id'],['ADMIN','MANAGER']);
        $q=$pdo->prepare('SELECT quote_id,quote_status,expires_at FROM repair_quotes
            WHERE ticket_device_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
        $q->execute([$deviceId]);$quote=$q->fetch(PDO::FETCH_ASSOC);
        if (!$quote || $quote['quote_status']!=='AWAITING_CUSTOMER' || $quote['expires_at']===null
            || new DateTimeImmutable($quote['expires_at'],new DateTimeZone('UTC'))
                > new DateTimeImmutable('now',new DateTimeZone('UTC'))) {
            throw new DomainException('The current quotation has not expired.');
        }
        $pdo->prepare("UPDATE repair_quotes SET quote_status='EXPIRED' WHERE quote_id=?")
            ->execute([(int)$quote['quote_id']]);
        edpManagerReturnToDiagnosis($pdo,$actorId,$deviceId,'Customer quotation expired');
        $pdo->prepare("INSERT INTO audit_events(actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_quotes',?,'CUSTOMER_QUOTE_EXPIRED')")
            ->execute([$actorId,(int)$quote['quote_id']]);
    });
}
function edpManagerReturnToDiagnosis(PDO $pdo, int $actorId, int $deviceId, string $summary): void
{
    $q=$pdo->prepare("SELECT 1 FROM allowed_status_transitions
        WHERE from_status='AWAITING_CUSTOMER_APPROVAL' AND to_status='DIAGNOSING'");
    $q->execute();
    if (!$q->fetchColumn()) throw new DomainException('Return-to-diagnosis transition is missing.');
    $pdo->prepare("UPDATE ticket_devices SET status_code='DIAGNOSING' WHERE ticket_device_id=?")
        ->execute([$deviceId]);
    $pdo->prepare("INSERT INTO ticket_status_events
        (ticket_device_id,from_status,to_status,actor_user_id,public_summary,is_customer_visible,created_at)
        VALUES (?,'AWAITING_CUSTOMER_APPROVAL','DIAGNOSING',?,?,1,UTC_TIMESTAMP())")
        ->execute([$deviceId,$actorId,$summary]);
}
