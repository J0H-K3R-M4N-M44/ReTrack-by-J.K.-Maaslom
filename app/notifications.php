<?php
declare(strict_types=1);

require_once __DIR__ . '/emailjs.php';
require_once __DIR__ . '/dashboard_data.php';

/**
 * Customer email and staff inbox have different recipient rules and storage.
 * Never place private technician notes or customer contact details in staff alerts.
 */
function notificationCustomerEvent(PDO $pdo, int $ticketId, int $deviceId, int $statusEventId): void
{
    $query = $pdo->prepare(
        "SELECT t.customer_id, t.ticket_code, c.full_name AS customer_name,
            d.device_type_snapshot, d.make_snapshot, d.model_snapshot,
            e.to_status, e.public_summary, e.is_customer_visible,
            b.branch_name, b.phone AS branch_phone
         FROM ticket_status_events e
         JOIN ticket_devices d ON d.ticket_device_id = e.ticket_device_id
         JOIN repair_tickets t ON t.ticket_id = d.ticket_id
         JOIN customers c ON c.customer_id = t.customer_id
         JOIN branches b ON b.branch_id = t.accountable_branch_id
         WHERE e.status_event_id = ? AND t.ticket_id = ? AND d.ticket_device_id = ?
         LIMIT 1",
    );
    $query->execute([$statusEventId, $ticketId, $deviceId]);
    $event = $query->fetch();

    if (!$event || !(int) $event['is_customer_visible']) {
        return;
    }

    // Only an explicitly preferred customer email receives operational updates.
    $contactQuery = $pdo->prepare(
        "SELECT contact_id, contact_value
         FROM customer_contacts
         WHERE customer_id = ? AND contact_type = 'EMAIL'
           AND preferred_for_notifications = 1
         ORDER BY is_primary DESC, contact_id ASC LIMIT 1",
    );
    $contactQuery->execute([(int) $event['customer_id']]);
    $contact = $contactQuery->fetch();
    if (!$contact || !filter_var($contact['contact_value'], FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $deviceLabel = trim(implode(' ', array_filter([
        $event['device_type_snapshot'],
        $event['make_snapshot'],
        $event['model_snapshot'],
    ])));
    $statusLabel = ucfirst(strtolower(str_replace('_', ' ', (string) $event['to_status'])));
    $summary = trim((string) ($event['public_summary'] ?? ''));
    if ($summary === '') {
        $summary = 'Your repair status has been updated.';
    }

    $params = [
        'to_email' => (string) $contact['contact_value'],
        'to_name' => (string) $event['customer_name'],
        'subject' => 'RepairTrack update for ' . $event['ticket_code'],
        'customer_name' => (string) $event['customer_name'],
        'ticket_code' => (string) $event['ticket_code'],
        'device_label' => $deviceLabel,
        'status' => $statusLabel,
        'message' => $summary,
        'branch_name' => (string) $event['branch_name'],
        'branch_phone' => (string) ($event['branch_phone'] ?? ''),
    ];
    $dedupe = hash('sha256', 'customer-email:' . $statusEventId . ':' . $contact['contact_id']);

    $insert = $pdo->prepare(
        "INSERT IGNORE INTO customer_email_outbox
         (customer_id, ticket_id, ticket_device_id, status_event_id, recipient_email,
          template_key, template_params, dedupe_key, delivery_status, next_attempt_at,
          created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'customer_update', ?, ?, 'QUEUED',
                 UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
    );
    $insert->execute([
        (int) $event['customer_id'],
        $ticketId,
        $deviceId,
        $statusEventId,
        (string) $contact['contact_value'],
        json_encode($params, JSON_THROW_ON_ERROR),
        $dedupe,
    ]);
}

/**
 * Queue a customer payment receipt notice. The payment data is read from the
 * committed bill/payment records and is never copied into the staff inbox.
 */
function notificationCustomerPayment(
    PDO $pdo,
    int $ticketId,
    int $paymentId,
    string $amount,
    string $remainingBalance,
): void {
    $query = $pdo->prepare(
        "SELECT t.customer_id, t.ticket_code, c.full_name AS customer_name,
            b.branch_name
         FROM repair_tickets t
         JOIN customers c ON c.customer_id = t.customer_id
         JOIN branches b ON b.branch_id = t.accountable_branch_id
         WHERE t.ticket_id = ? LIMIT 1",
    );
    $query->execute([$ticketId]);
    $ticket = $query->fetch();
    if (!$ticket) {
        return;
    }

    $contactQuery = $pdo->prepare(
        "SELECT contact_id, contact_value FROM customer_contacts
         WHERE customer_id = ? AND contact_type = 'EMAIL'
           AND preferred_for_notifications = 1
         ORDER BY is_primary DESC, contact_id ASC LIMIT 1",
    );
    $contactQuery->execute([(int) $ticket['customer_id']]);
    $contact = $contactQuery->fetch();
    if (!$contact || !filter_var($contact['contact_value'], FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $params = [
        'to_email' => (string) $contact['contact_value'],
        'to_name' => (string) $ticket['customer_name'],
        'subject' => 'RepairTrack payment received for ' . $ticket['ticket_code'],
        'customer_name' => (string) $ticket['customer_name'],
        'ticket_code' => (string) $ticket['ticket_code'],
        'payment_amount' => $amount,
        'remaining_balance' => $remainingBalance,
        'branch_name' => (string) $ticket['branch_name'],
    ];
    $dedupe = hash('sha256', 'customer-payment:' . $paymentId . ':' . $contact['contact_id']);
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO customer_email_outbox
         (customer_id, ticket_id, recipient_email, template_key, template_params,
          dedupe_key, delivery_status, next_attempt_at, created_at, updated_at)
         VALUES (?, ?, ?, 'customer_payment', ?, ?, 'QUEUED',
                 UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
    );
    $insert->execute([
        (int) $ticket['customer_id'],
        $ticketId,
        (string) $contact['contact_value'],
        json_encode($params, JSON_THROW_ON_ERROR),
        $dedupe,
    ]);
}

/** Explicit role policy for each staff alert category. VIEWER is intentionally read-only. */
function notificationStaffRoles(string $eventType): array
{
    return match ($eventType) {
        'TICKET_ISSUED' => ['ADMIN', 'MANAGER', 'INTAKE_AGENT'],
        'QUOTE_SHARED' => ['ADMIN', 'MANAGER', 'INTAKE_AGENT'],
        'QUOTE_DECISION' => ['ADMIN', 'MANAGER', 'TECHNICIAN', 'INTAKE_AGENT'],
        'REPAIR_STATUS' => ['ADMIN', 'MANAGER'],
        'READY_FOR_PICKUP' => ['ADMIN', 'MANAGER', 'CASHIER'],
        'REPAIR_CLOSED' => ['ADMIN', 'MANAGER', 'INTAKE_AGENT', 'CASHIER'],
        'DEVICE_ASSIGNED' => ['TECHNICIAN'],
        'PAYMENT_RECORDED', 'BILL_ISSUED' => ['ADMIN', 'MANAGER', 'CASHIER'],
        'DEVICE_TRANSFER' => ['ADMIN', 'MANAGER', 'COURIER'],
        default => [],
    };
}

/**
 * Persist one inbox record per authorized staff user. Admin is global; every
 * branch role needs an active assignment to the ticket's accountable branch.
 */
function notificationStaffEvent(
    PDO $pdo,
    int $ticketId,
    ?int $deviceId,
    int $branchId,
    string $eventType,
    string $title,
    string $message,
    string $targetPath,
    ?int $onlyUserId = null,
    string $eventKey = '',
): void {
    $roles = notificationStaffRoles($eventType);
    if (!$roles) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT u.user_id, u.email, u.full_name FROM users u
        WHERE u.is_active = 1 AND u.role IN ($placeholders)";
    $params = $roles;

    if ($onlyUserId !== null) {
        $sql .= ' AND u.user_id = ?';
        $params[] = $onlyUserId;
    }
    if ($eventType === 'QUOTE_DECISION' && $deviceId !== null) {
        // The assigned technician gets the decision; other technicians do not.
        $sql .= " AND (u.role <> 'TECHNICIAN' OR u.user_id = (
            SELECT assigned_technician_id FROM ticket_devices WHERE ticket_device_id = ?
        ))";
        $params[] = $deviceId;
    }
    $sql .= " AND (u.role = 'ADMIN' OR EXISTS (
        SELECT 1 FROM user_branch_assignments a
        JOIN branches b ON b.branch_id = a.branch_id
        WHERE a.user_id = u.user_id AND a.branch_id = ? AND b.is_active = 1
    )) ORDER BY u.user_id";
    $params[] = $branchId;

    $recipients = $pdo->prepare($sql);
    $recipients->execute($params);
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO staff_notifications
         (user_id, branch_id, ticket_id, ticket_device_id, event_type,
          title, message, target_path, dedupe_key, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
    );

    $staffEmailConfig = [];
    $configPath = __DIR__ . '/../config/config.php';
    if (is_file($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $staffEmailConfig = $loaded;
        }
    }
    $sendStaffEmail = ($staffEmailConfig['emailjs_staff_notifications_enabled'] ?? false) === true &&
        emailJsTemplateReady($staffEmailConfig, 'staff_alert');
    $branchQuery = $pdo->prepare('SELECT branch_name FROM branches WHERE branch_id = ?');
    $branchQuery->execute([$branchId]);
    $branchName = (string) ($branchQuery->fetchColumn() ?: 'RepairTrack branch');
    $ticketQuery = $pdo->prepare('SELECT ticket_code FROM repair_tickets WHERE ticket_id = ?');
    $ticketQuery->execute([$ticketId]);
    $ticketCode = (string) ($ticketQuery->fetchColumn() ?: 'Repair ticket');

    $insertEmail = $sendStaffEmail ? $pdo->prepare(
        "INSERT IGNORE INTO staff_email_outbox
         (user_id, staff_notification_id, branch_id, ticket_id, ticket_device_id,
          event_type, recipient_email, template_key, template_params, dedupe_key,
          delivery_status, next_attempt_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'staff_alert', ?, ?, 'QUEUED',
                 UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
    ) : null;

    foreach ($recipients->fetchAll(PDO::FETCH_ASSOC) as $recipient) {
        $recipientId = (int) $recipient['user_id'];
        $dedupe = hash('sha256', implode(':', [
            'staff',
            $eventType,
            $ticketId,
            $deviceId ?? 0,
            $eventKey !== '' ? $eventKey : $eventType,
            $recipientId,
        ]));
        $insert->execute([
            $recipientId,
            $branchId,
            $ticketId,
            $deviceId,
            $eventType,
            $title,
            $message,
            $targetPath,
            $dedupe,
        ]);

        if ($insertEmail) {
            $notificationQuery = $pdo->prepare(
                'SELECT staff_notification_id FROM staff_notifications WHERE dedupe_key = ? LIMIT 1',
            );
            $notificationQuery->execute([$dedupe]);
            $notificationId = (int) $notificationQuery->fetchColumn();
            $emailDedupe = hash('sha256', 'staff-email:' . $dedupe);
            $emailParams = [
                'to_email' => (string) $recipient['email'],
                'to_name' => (string) $recipient['full_name'],
                'subject' => 'RepairTrack: ' . $title,
                'event_type' => $eventType,
                'title' => $title,
                'message' => $message,
                'ticket_code' => $ticketCode,
                'branch_name' => $branchName,
                'target_url' => rtrim((string) ($staffEmailConfig['app_url'] ?? ''), '/') .
                    '/admin/' . ltrim($targetPath, '/'),
            ];
            $insertEmail->execute([
                $recipientId,
                $notificationId > 0 ? $notificationId : null,
                $branchId,
                $ticketId,
                $deviceId,
                $eventType,
                (string) $recipient['email'],
                json_encode($emailParams, JSON_THROW_ON_ERROR),
                $emailDedupe,
            ]);
        }
    }
}

/** Route from the immutable status event, not a client-posted role or branch. */
function notificationAfterStatusEvent(PDO $pdo, int $deviceId, int $statusEventId): void
{
    $query = $pdo->prepare(
        "SELECT e.status_event_id, e.from_status, e.to_status, e.reason_code,
            e.public_summary, e.is_customer_visible,
            d.ticket_device_id, d.assigned_technician_id,
            t.ticket_id, t.ticket_code, t.accountable_branch_id
         FROM ticket_status_events e
         JOIN ticket_devices d ON d.ticket_device_id = e.ticket_device_id
         JOIN repair_tickets t ON t.ticket_id = d.ticket_id
         WHERE e.status_event_id = ? AND d.ticket_device_id = ?",
    );
    $query->execute([$statusEventId, $deviceId]);
    $event = $query->fetch();
    if (!$event) {
        return;
    }

    $ticketId = (int) $event['ticket_id'];
    $branchId = (int) $event['accountable_branch_id'];
    if ((int) $event['is_customer_visible']) {
        notificationCustomerEvent($pdo, $ticketId, $deviceId, $statusEventId);
    }

    $from = (string) ($event['from_status'] ?? '');
    $to = (string) $event['to_status'];
    $reason = (string) ($event['reason_code'] ?? '');
    $target = 'device.php?id=' . $deviceId;
    $eventKey = (string) $statusEventId;

    if ($reason === 'INTAKE_ISSUED' || $from === 'DRAFT') {
        notificationStaffEvent(
            $pdo, $ticketId, $deviceId, $branchId, 'TICKET_ISSUED',
            'New repair ticket',
            'Ticket ' . $event['ticket_code'] . ' has been received for assessment.',
            'staff_home.php?view=queue',
            null,
            $eventKey,
        );
        return;
    }

    if ($to === 'AWAITING_CUSTOMER_APPROVAL') {
        notificationStaffEvent(
            $pdo, $ticketId, $deviceId, $branchId, 'QUOTE_SHARED',
            'Estimate shared with customer',
            'Ticket ' . $event['ticket_code'] . ' is waiting for a customer decision.',
            $target,
            null,
            $eventKey,
        );
        return;
    }

    if ($from === 'AWAITING_CUSTOMER_APPROVAL' && $to === 'DIAGNOSING') {
        notificationStaffEvent(
            $pdo, $ticketId, $deviceId, $branchId, 'QUOTE_DECISION',
            'Customer decision recorded',
            'A customer decision was recorded for ticket ' . $event['ticket_code'] . '.',
            $target,
            null,
            $eventKey,
        );
        return;
    }

    if ($to === 'READY_FOR_PICKUP') {
        notificationStaffEvent(
            $pdo, $ticketId, $deviceId, $branchId, 'READY_FOR_PICKUP',
            'Device ready for pickup',
            'A device on ticket ' . $event['ticket_code'] . ' is ready for the cash desk.',
            'staff_home.php?view=cash',
            null,
            $eventKey,
        );
        return;
    }

    if (in_array($to, ['COMPLETED', 'CANCELLED'], true)) {
        notificationStaffEvent(
            $pdo, $ticketId, $deviceId, $branchId, 'REPAIR_CLOSED',
            'Repair work closed',
            'A device on ticket ' . $event['ticket_code'] . ' has been closed.',
            $target,
            null,
            $eventKey,
        );
        return;
    }

    notificationStaffEvent(
        $pdo, $ticketId, $deviceId, $branchId, 'REPAIR_STATUS',
        'Repair status updated',
        'A repair stage changed for ticket ' . $event['ticket_code'] . '.',
        $target,
    );
}

function notificationUnreadCount(PDO $pdo, int $userId): int
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM staff_notifications WHERE user_id = ? AND read_at IS NULL',
    );
    $query->execute([$userId]);
    return (int) $query->fetchColumn();
}

function notificationPage(PDO $pdo, int $userId, string $page = '1'): array
{
    return dashboardPage(
        $pdo,
        'SELECT staff_notification_id, event_type, title, message, target_path,
            read_at, created_at
         FROM staff_notifications WHERE user_id = ?',
        [$userId],
        'created_at DESC, staff_notification_id DESC',
        $page,
    );
}

function notificationMarkRead(PDO $pdo, int $userId, int $notificationId): void
{
    $query = $pdo->prepare(
        'UPDATE staff_notifications SET read_at = UTC_TIMESTAMP()
         WHERE staff_notification_id = ? AND user_id = ? AND read_at IS NULL',
    );
    $query->execute([$notificationId, $userId]);
}

function notificationSendDue(PDO $pdo, array $config, int $limit = 10): array
{
    $limit = max(1, min(20, $limit));
    $sent = 0;
    $failed = 0;

    // Recover a worker interrupted after claiming a message.
    $pdo->exec(
        "UPDATE customer_email_outbox SET delivery_status = 'QUEUED',
            next_attempt_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
         WHERE delivery_status = 'SENDING'
           AND updated_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE",
    );

    for ($i = 0; $i < $limit; $i++) {
        $pdo->beginTransaction();
        try {
            $query = $pdo->query(
                "SELECT customer_email_id, template_key, template_params, attempt_count
                 FROM customer_email_outbox
                 WHERE delivery_status = 'QUEUED' AND next_attempt_at <= UTC_TIMESTAMP()
                 ORDER BY next_attempt_at, customer_email_id
                 LIMIT 1 FOR UPDATE SKIP LOCKED",
            );
            $row = $query->fetch();
            if (!$row) {
                $pdo->commit();
                break;
            }

            $attempt = (int) $row['attempt_count'] + 1;
            $claim = $pdo->prepare(
                "UPDATE customer_email_outbox SET delivery_status = 'SENDING',
                    attempt_count = ?, updated_at = UTC_TIMESTAMP()
                 WHERE customer_email_id = ?",
            );
            $claim->execute([$attempt, (int) $row['customer_email_id']]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        try {
            $params = json_decode((string) $row['template_params'], true, 32, JSON_THROW_ON_ERROR);
            emailJsSendTemplate($config, (string) $row['template_key'], $params);
            $update = $pdo->prepare(
                "UPDATE customer_email_outbox SET delivery_status = 'SENT',
                    sent_at = UTC_TIMESTAMP(), last_error = NULL,
                    updated_at = UTC_TIMESTAMP()
                 WHERE customer_email_id = ? AND delivery_status = 'SENDING'",
            );
            $update->execute([(int) $row['customer_email_id']]);
            $sent++;
        } catch (Throwable $error) {
            $delayMinutes = min(60, 2 ** min($attempt, 5));
            $nextStatus = $attempt >= 5 ? 'FAILED' : 'QUEUED';
            $update = $pdo->prepare(
                "UPDATE customer_email_outbox SET delivery_status = ?,
                    next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE),
                    last_error = ?, updated_at = UTC_TIMESTAMP()
                 WHERE customer_email_id = ? AND delivery_status = 'SENDING'",
            );
            $update->execute([
                $nextStatus,
                $delayMinutes,
                mb_substr($error->getMessage(), 0, 500),
                (int) $row['customer_email_id'],
            ]);
            $failed++;
            error_log('RepairTrack customer email delivery failed: ' . $error->getMessage());
        }
    }

    return ['sent' => $sent, 'failed' => $failed];
}


function notificationSendStaffDue(PDO $pdo, array $config, int $limit = 10): array
{
    if (($config['emailjs_staff_notifications_enabled'] ?? false) !== true ||
        !emailJsTemplateReady($config, 'staff_alert')) {
        return ['sent' => 0, 'failed' => 0];
    }

    $limit = max(1, min(20, $limit));
    $sent = 0;
    $failed = 0;

    $pdo->exec(
        "UPDATE staff_email_outbox SET delivery_status = 'QUEUED',
            next_attempt_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
         WHERE delivery_status = 'SENDING'
           AND updated_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE",
    );

    for ($i = 0; $i < $limit; $i++) {
        $pdo->beginTransaction();
        try {
            $query = $pdo->query(
                "SELECT staff_email_id, template_params, attempt_count
                 FROM staff_email_outbox
                 WHERE delivery_status = 'QUEUED' AND next_attempt_at <= UTC_TIMESTAMP()
                 ORDER BY next_attempt_at, staff_email_id
                 LIMIT 1 FOR UPDATE SKIP LOCKED",
            );
            $row = $query->fetch();
            if (!$row) {
                $pdo->commit();
                break;
            }

            $attempt = (int) $row['attempt_count'] + 1;
            $claim = $pdo->prepare(
                "UPDATE staff_email_outbox SET delivery_status = 'SENDING',
                    attempt_count = ?, updated_at = UTC_TIMESTAMP()
                 WHERE staff_email_id = ?",
            );
            $claim->execute([$attempt, (int) $row['staff_email_id']]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        try {
            $params = json_decode((string) $row['template_params'], true, 32, JSON_THROW_ON_ERROR);
            emailJsSendTemplate($config, 'staff_alert', $params);
            $update = $pdo->prepare(
                "UPDATE staff_email_outbox SET delivery_status = 'SENT',
                    sent_at = UTC_TIMESTAMP(), last_error = NULL,
                    updated_at = UTC_TIMESTAMP()
                 WHERE staff_email_id = ? AND delivery_status = 'SENDING'",
            );
            $update->execute([(int) $row['staff_email_id']]);
            $sent++;
        } catch (Throwable $error) {
            $delayMinutes = min(60, 2 ** min($attempt, 5));
            $nextStatus = $attempt >= 5 ? 'FAILED' : 'QUEUED';
            $update = $pdo->prepare(
                "UPDATE staff_email_outbox SET delivery_status = ?,
                    next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE),
                    last_error = ?, updated_at = UTC_TIMESTAMP()
                 WHERE staff_email_id = ? AND delivery_status = 'SENDING'",
            );
            $update->execute([
                $nextStatus,
                $delayMinutes,
                mb_substr($error->getMessage(), 0, 500),
                (int) $row['staff_email_id'],
            ]);
            $failed++;
            error_log('RepairTrack staff email delivery failed: ' . $error->getMessage());
        }
    }

    return ['sent' => $sent, 'failed' => $failed];
}
