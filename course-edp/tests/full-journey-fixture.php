<?php
declare(strict_types=1);

/**
 * Disposable, CLI-only test support for a TRUE cross-role browser journey.
 * Creates staff accounts, NOT repair tickets, quotations, events, bills or payments.
 * The HTTP test creates all repair records through the real application forms.
 */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp'
    || getenv('REPAIRTRACK_EDP_DB') !== 'repairtrack_edp'
    || (getenv('REPAIRTRACK_EDP_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
    fwrite(STDERR, "Use a disposable local repairtrack_edp test database only.\n");
    exit(2);
}
require_once __DIR__ . '/../app/bootstrap.php';
$pdo = edpPreviewDb();
$command = $argv[1] ?? '';

if ($command === 'create') {
    $branch = (int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO' AND is_active=1")
        ->fetchColumn();
    if ($branch < 1) throw new RuntimeException('Fictional seed branch is missing');
    $suffix = bin2hex(random_bytes(7));
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO branches (branch_code,branch_name) VALUES (?,?)')
            ->execute(['JOURNEY-'.$suffix,'Fictional Other Journey Branch']);
        $otherBranch = (int)$pdo->lastInsertId();
        $add = $pdo->prepare('INSERT INTO users(full_name,email,password_hash,role_code) VALUES (?,?,?,?)');
        $membership = $pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)');
        $accounts = [];
        foreach ([
            'intake' => ['INTAKE_AGENT',$branch],
            'manager' => ['MANAGER',$branch],
            'technician' => ['TECHNICIAN',$branch],
            'cashier' => ['CASHIER',$branch],
            'outsider' => ['MANAGER',$otherBranch],
        ] as $name => [$role,$assignedBranch]) {
            $email = 'journey-'.$name.'-'.$suffix.'@example.test';
            $password = bin2hex(random_bytes(16));
            $add->execute(['Journey '.$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            $id = (int)$pdo->lastInsertId();
            $membership->execute([$id,$assignedBranch]);
            $accounts[$name] = ['id'=>$id,'email'=>$email,'password'=>$password];
        }
        $pdo->commit();
        echo json_encode(['branch_id'=>$branch,'other_branch_id'=>$otherBranch,'staff'=>$accounts], JSON_THROW_ON_ERROR);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

if ($command === 'inspect') {
    $code = strtoupper((string)($argv[2] ?? ''));
    if (!preg_match('/^RT-[0-9A-F]{12}$/D',$code)) throw new DomainException('Invalid fictional internal ticket code');
    $q = $pdo->prepare('SELECT t.ticket_id,t.ticket_state,t.branch_id,t.customer_id,t.closed_at,
        t.terms_signature_path,u.email AS intake_email,c.full_name AS customer_name
        FROM repair_tickets t JOIN users u ON u.user_id=t.created_by_user_id
        JOIN customers c ON c.customer_id=t.customer_id WHERE t.ticket_code=? LIMIT 1');
    $q->execute([$code]);
    $ticket = $q->fetch(PDO::FETCH_ASSOC);
    if (!$ticket || !preg_match('/^journey-intake-[a-f0-9]{14}@example[.]test$/D',$ticket['intake_email'])
        || $ticket['customer_name'] !== 'Journey Fictional Customer') {
        throw new DomainException('Only a ticket created by this HTTP journey may be inspected');
    }
    $q = $pdo->prepare("SELECT d.ticket_device_id,d.item_code,d.status_code,d.assigned_technician_id,
        d.repair_started_at,d.custody_state,d.return_handover_at,
        (SELECT q.quote_status FROM repair_quotes q WHERE q.ticket_device_id=d.ticket_device_id
            ORDER BY q.version_no DESC LIMIT 1) AS quote_status,
        (SELECT q.sent_at FROM repair_quotes q WHERE q.ticket_device_id=d.ticket_device_id
            ORDER BY q.version_no DESC LIMIT 1) AS quote_sent_at,
        (SELECT COUNT(*) FROM ticket_status_events e WHERE e.ticket_device_id=d.ticket_device_id) AS event_count
        FROM ticket_devices d WHERE d.ticket_id=? ORDER BY d.sequence_no");
    $q->execute([(int)$ticket['ticket_id']]);
    $devices = $q->fetchAll(PDO::FETCH_ASSOC);
    $q = $pdo->prepare('SELECT bill_id,bill_number,bill_status,total_amount FROM repair_bills
        WHERE ticket_id=? ORDER BY version_no DESC LIMIT 1');
    $q->execute([(int)$ticket['ticket_id']]);
    $bill = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    $payments = [];
    $billItems = [];
    if ($bill) {
        $q = $pdo->prepare('SELECT amount,request_key FROM repair_bill_payments WHERE bill_id=? ORDER BY payment_id');
        $q->execute([(int)$bill['bill_id']]);
        $payments = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = $pdo->prepare('SELECT ticket_device_id,amount FROM repair_bill_items WHERE bill_id=? ORDER BY bill_item_id');
        $q->execute([(int)$bill['bill_id']]);
        $billItems = $q->fetchAll(PDO::FETCH_ASSOC);
    }
    $q = $pdo->prepare('SELECT action_code,COUNT(*) AS count FROM audit_events WHERE
         (entity_type=\'repair_ticket\' AND entity_id=?) OR
         (entity_type=\'ticket_device\' AND entity_id IN
            (SELECT ticket_device_id FROM ticket_devices WHERE ticket_id=?))
         GROUP BY action_code');
    $q->execute([(int)$ticket['ticket_id'],(int)$ticket['ticket_id']]);
    $audit = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ticket'=>$ticket,'devices'=>$devices,'bill'=>$bill,
        'payments'=>$payments,'bill_items'=>$billItems,'audit'=>$audit],JSON_THROW_ON_ERROR);
    exit;
}

throw new DomainException('Use create or inspect with a fictional internal Ticket Code.');
