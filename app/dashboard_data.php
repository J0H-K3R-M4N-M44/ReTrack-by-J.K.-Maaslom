<?php
declare(strict_types=1);

/**
 * Read the data shown by the admin dashboard.
 * Keeping these queries together makes the page easier to maintain.
 */
function loadDashboardData(PDO $pdo): array
{
    $metrics = [];

    $metrics['activeRepairs'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM ticket_devices
         WHERE repair_status IN (
             'RECEIVED', 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL',
             'AWAITING_PARTS', 'IN_PROGRESS', 'TESTING', 'READY_FOR_PICKUP'
         )"
    )->fetchColumn();

    $metrics['technicians'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'TECHNICIAN' AND is_active = 1"
    )->fetchColumn();

    $metrics['pendingIntake'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_tickets WHERE record_stage = 'DRAFT'"
    )->fetchColumn();

    $manila = new DateTimeZone('Asia/Manila');
    $utc = new DateTimeZone('UTC');
    $today = (new DateTimeImmutable('now', $manila))->setTime(0, 0);
    $fromUtc = $today->setTimezone($utc)->format('Y-m-d H:i:s');
    $toUtc = $today->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');

    $issuedToday = $pdo->prepare(
        "SELECT COUNT(*) FROM repair_tickets
         WHERE record_stage = 'ISSUED' AND issued_at >= ? AND issued_at < ?"
    );
    $issuedToday->execute([$fromUtc, $toUtc]);
    $metrics['ticketsIssuedToday'] = (int) $issuedToday->fetchColumn();

    $queue = $pdo->query(
        "SELECT d.item_code, d.device_type_snapshot, d.make_snapshot, d.model_snapshot,
                d.repair_status, d.updated_at, t.ticket_code, c.full_name AS customer_name
         FROM ticket_devices d
         JOIN repair_tickets t ON t.ticket_id = d.ticket_id
         LEFT JOIN customers c ON c.customer_id = t.customer_id
         WHERE t.record_stage = 'ISSUED'
         ORDER BY d.updated_at DESC, d.ticket_device_id DESC
         LIMIT 12"
    )->fetchAll();

    $customers = $pdo->query(
        "SELECT c.customer_id, c.full_name, c.updated_at,
                COUNT(DISTINCT t.ticket_id) AS ticket_count
         FROM customers c
         LEFT JOIN repair_tickets t ON t.customer_id = c.customer_id
         GROUP BY c.customer_id, c.full_name, c.updated_at
         ORDER BY c.updated_at DESC
         LIMIT 12"
    )->fetchAll();

    $statusCounts = $pdo->query(
        "SELECT d.repair_status, COUNT(*) AS total
         FROM ticket_devices d
         JOIN repair_tickets t ON t.ticket_id = d.ticket_id
         WHERE t.record_stage = 'ISSUED'
         GROUP BY d.repair_status
         ORDER BY d.repair_status"
    )->fetchAll();

    return [
        'metrics' => $metrics,
        'queue' => $queue,
        'customers' => $customers,
        'status_counts' => $statusCounts,
    ];
}

function dashboardDeviceLabel(array $device): string
{
    $parts = array_filter([
        $device['make_snapshot'] ?? '',
        $device['model_snapshot'] ?? '',
    ], static fn ($value) => is_string($value) && trim($value) !== '');

    $label = trim(implode(' ', $parts));

    if ($label === '') {
        return (string) ($device['device_type_snapshot'] ?? 'Device');
    }

    return $label;
}

function dashboardStatusLabel(string $status): string
{
    $labels = [
        'RECEIVED' => 'Received',
        'DIAGNOSING' => 'Diagnosing',
        'AWAITING_CUSTOMER_APPROVAL' => 'Awaiting customer approval',
        'AWAITING_PARTS' => 'Awaiting parts',
        'IN_PROGRESS' => 'In repair',
        'TESTING' => 'Testing',
        'READY_FOR_PICKUP' => 'Ready for pickup',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
        'DRAFT' => 'Draft',
    ];

    return $labels[$status] ?? ucwords(strtolower(str_replace('_', ' ', $status)));
}
