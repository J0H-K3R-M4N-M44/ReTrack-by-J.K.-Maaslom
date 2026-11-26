<?php
declare(strict_types=1);

// Run with XAMPP's CLI PHP. Only this invocation's newly created database receives writes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/public/tracking.php';
require_once __DIR__ . '/../app/payments/repository.php';
require_once __DIR__ . '/../app/console/reports.php';
require_once __DIR__ . '/../app/console/activity.php';
require_once __DIR__ . '/../app/notifications.php';
require_once __DIR__ . '/../tools/migration_support.php';
require_once __DIR__ . '/../tools/simulation_data.php';


function testNotificationWorkflows(PDO $pdo): void
{
    $ticket = $pdo->query(
        "SELECT t.ticket_id, t.customer_id, t.accountable_branch_id, t.ticket_code,
            d.ticket_device_id, d.repair_status, d.assigned_technician_id
         FROM repair_tickets t JOIN ticket_devices d ON d.ticket_id = t.ticket_id
         WHERE t.ticket_code = 'SIM-RT-001' ORDER BY d.child_sequence LIMIT 1",
    )->fetch();
    checkWorkflow((bool) $ticket, 'Notification fixture ticket missing.');

    $branchId = (int) $ticket['accountable_branch_id'];
    $deviceId = (int) $ticket['ticket_device_id'];
    $ticketId = (int) $ticket['ticket_id'];
    $assignedTechId = (int) $ticket['assigned_technician_id'];
    $managerId = (int) $pdo->query("SELECT user_id FROM users WHERE email='manager@sim.example.test'")->fetchColumn();
    $intakeId = (int) $pdo->query("SELECT user_id FROM users WHERE email='intake_agent@sim.example.test'")->fetchColumn();
    $cashierId = (int) $pdo->query("SELECT user_id FROM users WHERE email='cashier@sim.example.test'")->fetchColumn();

    $adminId = seedRow($pdo, 'users', [
        'full_name' => 'Notification Test Administrator',
        'email' => 'notification-admin@example.test',
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role' => 'ADMIN',
    ]);
    $otherTechnicianId = seedRow($pdo, 'users', [
        'full_name' => 'Other Branch Technician',
        'email' => 'notification-other-tech@example.test',
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role' => 'TECHNICIAN',
    ]);
    seedRow($pdo, 'user_branch_assignments', [
        'user_id' => $otherTechnicianId,
        'branch_id' => $branchId,
    ]);
    $otherBranchId = seedRow($pdo, 'branches', [
        'branch_code' => 'NOTIFY-OTHER',
        'branch_name' => 'Notification Test Other Branch',
    ]);
    $otherManagerId = seedRow($pdo, 'users', [
        'full_name' => 'Other Branch Manager',
        'email' => 'notification-other-manager@example.test',
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role' => 'MANAGER',
    ]);
    seedRow($pdo, 'user_branch_assignments', [
        'user_id' => $otherManagerId,
        'branch_id' => $otherBranchId,
    ]);
    $viewerId = seedRow($pdo, 'users', [
        'full_name' => 'Read Only Viewer',
        'email' => 'notification-viewer@example.test',
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role' => 'VIEWER',
    ]);
    seedRow($pdo, 'user_branch_assignments', [
        'user_id' => $viewerId,
        'branch_id' => $branchId,
    ]);

    notificationStaffEvent(
        $pdo,
        $ticketId,
        $deviceId,
        $branchId,
        'QUOTE_DECISION',
        'Customer decision recorded',
        'A synthetic customer decision was recorded.',
        'device.php?id=' . $deviceId,
        null,
        'test-quote-decision-1',
    );

    $recipientsQuery = $pdo->prepare(
        "SELECT user_id FROM staff_notifications
         WHERE ticket_id = ? AND event_type = 'QUOTE_DECISION'
         ORDER BY user_id",
    );
    $recipientsQuery->execute([$ticketId]);
    $recipients = array_map('intval', $recipientsQuery->fetchAll(PDO::FETCH_COLUMN));
    sort($recipients);
    $expected = [$adminId, $managerId, $intakeId, $assignedTechId];
    sort($expected);
    checkWorkflow($recipients === $expected, 'Quote decision notification crossed a role or branch boundary.');
    checkWorkflow(!in_array($otherTechnicianId, $recipients, true), 'Unassigned technician received a quote alert.');
    checkWorkflow(!in_array($otherManagerId, $recipients, true), 'Other branch manager received a quote alert.');
    checkWorkflow(!in_array($cashierId, $recipients, true), 'Cashier received a technician/customer decision alert.');
    checkWorkflow(!in_array($viewerId, $recipients, true), 'Read-only viewer received an operational alert.');

    $managerNoticeQuery = $pdo->prepare(
        "SELECT staff_notification_id FROM staff_notifications
         WHERE user_id = ? AND ticket_id = ? AND event_type = 'QUOTE_DECISION' LIMIT 1",
    );
    $managerNoticeQuery->execute([$managerId, $ticketId]);
    $managerNoticeId = (int) $managerNoticeQuery->fetchColumn();
    checkWorkflow(notificationUnreadCount($pdo, $managerId) === 1, 'Staff unread count is not recipient-specific.');
    checkWorkflow(notificationUnreadCount($pdo, $otherTechnicianId) === 0, 'Other technician has an unrelated unread alert.');
    notificationMarkRead($pdo, $cashierId, $managerNoticeId);
    $stillUnread = $pdo->prepare('SELECT read_at FROM staff_notifications WHERE staff_notification_id = ?');
    $stillUnread->execute([$managerNoticeId]);
    checkWorkflow($stillUnread->fetchColumn() === null, 'Another staff member marked a notification read.');
    notificationMarkRead($pdo, $managerId, $managerNoticeId);
    checkWorkflow(notificationUnreadCount($pdo, $managerId) === 0, 'Recipient could not mark their notification read.');

    // Existing preference flags are not enough: an explicit consent timestamp is required.
    $contactId = seedRow($pdo, 'customer_contacts', [
        'customer_id' => (int) $ticket['customer_id'],
        'contact_type' => 'EMAIL',
        'contact_value' => 'notification-customer@example.test',
        'normalized_value' => 'notification-customer@example.test',
        'is_primary' => 0,
        'preferred_for_notifications' => 1,
        'email_updates_consent_at' => null,
    ]);
    $insertEvent = $pdo->prepare(
        "INSERT INTO ticket_status_events
         (ticket_device_id, from_status, to_status, actor_type, actor_user_id,
          internal_note, public_summary, is_customer_visible, created_at)
         VALUES (?, ?, 'DIAGNOSING', 'USER', ?, ?, ?, ?, UTC_TIMESTAMP())",
    );
    $insertEvent->execute([
        $deviceId,
        $ticket['repair_status'],
        $adminId,
        'PRIVATE_NOTIFICATION_TEST_NOTE',
        'Synthetic public repair update',
        1,
    ]);
    $noConsentEventId = (int) $pdo->lastInsertId();
    notificationCustomerEvent($pdo, $ticketId, $deviceId, $noConsentEventId);
    $outboxCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM customer_email_outbox WHERE status_event_id = ' . $noConsentEventId,
    )->fetchColumn();
    checkWorkflow($outboxCount === 0, 'Customer email queued without explicit permission.');

    $pdo->prepare(
        'UPDATE customer_contacts SET email_updates_consent_at = UTC_TIMESTAMP() WHERE contact_id = ?',
    )->execute([$contactId]);
    $insertEvent->execute([
        $deviceId,
        'DIAGNOSING',
        $adminId,
        'PRIVATE_NOTIFICATION_TEST_NOTE',
        'Synthetic public repair update after consent',
        1,
    ]);
    $consentedEventId = (int) $pdo->lastInsertId();
    notificationCustomerEvent($pdo, $ticketId, $deviceId, $consentedEventId);
    $mailQuery = $pdo->prepare(
        'SELECT recipient_email, template_params FROM customer_email_outbox WHERE status_event_id = ?',
    );
    $mailQuery->execute([$consentedEventId]);
    $mail = $mailQuery->fetch();
    checkWorkflow((bool) $mail, 'Customer email was not queued after explicit consent.');
    checkWorkflow(
        $mail['recipient_email'] === 'notification-customer@example.test',
        'Customer email was routed to the wrong contact.',
    );
    checkWorkflow(
        str_contains((string) $mail['template_params'], 'Synthetic public repair update after consent') &&
            !str_contains((string) $mail['template_params'], 'PRIVATE_NOTIFICATION_TEST_NOTE'),
        'Customer email template contains private notes or omits the public message.',
    );

    $insertEvent->execute([
        $deviceId,
        'DIAGNOSING',
        $adminId,
        'PRIVATE_NOTIFICATION_TEST_NOTE',
        'This is not customer-visible',
        0,
    ]);
    $privateEventId = (int) $pdo->lastInsertId();
    notificationCustomerEvent($pdo, $ticketId, $deviceId, $privateEventId);
    $privateOutboxCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM customer_email_outbox WHERE status_event_id = ' . $privateEventId,
    )->fetchColumn();
    checkWorkflow($privateOutboxCount === 0, 'Internal-only event created a customer email.');
}

function checkWorkflow(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException($message);
    }
}

$database = 'repairtrack_test_' . bin2hex(random_bytes(8));
$created = false;
$file = null;
$result = 0;
try {
    startAppSession();
    $pdo = getPDO();
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo->exec("USE `$database`");
    // The SQL export has its own database selection for Workbench imports.
    // Redirect both installation statements to this run's new schema.
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $schema = str_replace(
        ['CREATE DATABASE IF NOT EXISTS repairtrack', 'USE repairtrack;'],
        ["CREATE DATABASE IF NOT EXISTS `$database`", "USE `$database`;"],
        $schema,
        $replacements,
    );
    checkWorkflow($replacements === 2, 'Schema installation header changed. Review test setup.');
    $file = tempnam(sys_get_temp_dir(), 'rt-schema-');
    checkWorkflow($file !== false && file_put_contents($file, $schema) !== false, 'Cannot stage schema.');
    migrationRunFile($pdo, $file);
    checkWorkflow($pdo->query('SELECT DATABASE()')->fetchColumn() === $database, 'Wrong test database.');
    // Fresh schema must be complete without needing the incremental migration runner.
    checkWorkflow(migrationPending($pdo) === [], 'Fresh schema left a pending migration.');
    foreach (['inventory_transfers'=>'status','inventory_transfer_items'=>'item_status'] as $table=>$column) {
        $query = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $query->execute([$table,$column]);
        checkWorkflow(str_contains((string)$query->fetchColumn(),"'LOSS_CLOSED'"),
            'Inventory transfer is missing operational loss closure status.');
    }
    foreach (['device_transfers'=>'status','device_transfer_items'=>'item_status'] as $table=>$column) {
        $query = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $query->execute([$table,$column]);
        checkWorkflow(!str_contains((string)$query->fetchColumn(),"'LOSS_CLOSED'"),
            'Device custody transfers must not acquire inventory-loss statuses.');
    }

    $pdo->exec('DROP TABLE staff_activity_reads');
    checkWorkflow(migrationPending($pdo) === ['staff_activity.sql'], 'Unexpected migration status.');
    migrationRunFile($pdo, __DIR__ . '/../database/migrations/staff_activity.sql');
    checkWorkflow(migrationPending($pdo) === [], 'Activity migration remains pending.');
    $pdo->beginTransaction();
    $password = seedSimulation($pdo);
    $pdo->commit();

    testNotificationWorkflows($pdo);

    foreach (['MANAGER', 'TECHNICIAN', 'INTAKE_AGENT', 'CASHIER'] as $role) {
        $email = strtolower($role) . '@sim.example.test';
        checkWorkflow(loginAccount($email, $password), "Login failed for $role.");
        $user = currentAccount();
        checkWorkflow($user !== null && $user['role'] === $role, "Session role mismatch for $role.");
        foreach (activityGroups($role) as $group) {
            [$source, $parameters] = activitySource($user, $group);
            $query = $pdo->prepare($source);
            $query->execute($parameters);
            $event = $query->fetch();
            checkWorkflow((bool) $event, "Missing seeded $group activity for $role.");
            $query->closeCursor();
            // A repeated click should leave exactly one read receipt for this user.
            $id = (string) $event['event_id'];
            activityMarkRead($pdo, $user, $group, $id);
            activityMarkRead($pdo, $user, $group, $id);
            $seen = $pdo->prepare('SELECT COUNT(*) FROM staff_activity_reads
                WHERE user_id = ? AND event_group = ? AND event_id = ?');
            $seen->execute([$user['user_id'], $group, $id]);
            checkWorkflow((int) $seen->fetchColumn() === 1, "Read receipt mismatch for $role.");

        }
        if (consoleCanViewReports($user)) {
            $report = loadStaffReport($pdo, $user, staffReportPeriod([]), null);
            checkWorkflow(count($report['daily']) === 7, "Incomplete weekly report for $role.");
        }
    }

    // The last account signed in is the cashier. Follow the seeded partial payment to pickup.
    $ticket = $pdo->query("SELECT t.portal_code, b.bill_id FROM repair_tickets t
        JOIN repair_bills b ON b.ticket_id = t.ticket_id WHERE t.ticket_code = 'SIM-RT-005'")->fetch();
    checkWorkflow((bool) $ticket, 'Missing seeded pickup ticket.');
    $portal = $ticket['portal_code'];
    $billId = (int) $ticket['bill_id'];
    $actor = (int) $user['user_id'];
    checkWorkflow(publicRepair($pdo, $portal, '0000') === null, 'Incorrect PIN opened the portal.');
    $repair = publicRepair($pdo, $portal, '1004');
    checkWorkflow(($repair['devices'][0]['repair_status'] ?? '') === 'READY_FOR_PICKUP', 'Portal status mismatch.');
    $bill = cashBillDetails($pdo, $user, $billId);
    checkWorkflow($bill['balance_cents'] > 0 && count($bill['payments']) === 1, 'Missing partial payment.');
    try {
        cashCompleteHandover($pdo, $actor, $billId, $bill['version'], 'Rosa Reyes', 'CUSTOMER', 'yes');
        throw new RuntimeException('Unpaid pickup was accepted.');
    } catch (DomainException $error) {
        checkWorkflow(str_contains($error->getMessage(), 'full bill balance'), $error->getMessage());
    }
    cashRecordPayment($pdo, $actor, $billId, $bill['version'], cashAmountText($bill['balance_cents']), bin2hex(random_bytes(32)));
    $paid = cashBillDetails($pdo, $user, $billId);
    checkWorkflow($paid['balance_cents'] === 0 && count($paid['payments']) === 2, 'Settlement mismatch.');
    cashCompleteHandover($pdo, $actor, $billId, $paid['version'], 'Rosa Reyes', 'CUSTOMER', 'yes');
    $returned = publicRepair($pdo, $portal, '1004');
    checkWorkflow(($returned['devices'][0]['custody_state'] ?? '') === 'WITH_CUSTOMER', 'Portal missed the handover.');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    $result = 1;
} finally {
    if (is_string($file) && is_file($file)) {
        unlink($file);
    }
    if ($created) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec("DROP DATABASE `$database`");
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
if ($result === 0) {
    echo "PASS: migration, role-scoped notifications, customer consent, four staff logins, activity, reports, PIN, settlement and pickup.\n";
}
exit($result);
