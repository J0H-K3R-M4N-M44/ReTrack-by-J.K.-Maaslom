<?php
declare(strict_types=1);
require_once __DIR__ . '/web.php';

/**
 * Read the data shown by the admin dashboard.
 * Keeping these queries together makes the page easier to maintain.
 */
function loadDashboardData(PDO $pdo, array $input = [], ?int $userId = null): array
{
    $metrics = [];
    $search = likePattern(mb_substr(requestText($input, 'q'), 0, 190, 'UTF-8'));
    $pages = [];

    $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
    // The ID is an integer from the authenticated account, never a posted SQL fragment.
    $scope = "EXISTS (SELECT 1 FROM user_branch_assignments scope_a
        JOIN branches scope_b ON scope_b.branch_id = scope_a.branch_id
        WHERE scope_a.user_id = $userId AND scope_b.is_active = 1
        AND scope_a.branch_id = COALESCE(t.accountable_branch_id, t.intake_branch_id))";
    $statusCounts = $pdo
        ->query(
            "SELECT d.repair_status, COUNT(*) AS total
        FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        WHERE t.record_stage = 'ISSUED' AND $scope GROUP BY d.repair_status ORDER BY d.repair_status",
        )
        ->fetchAll();
    // Reuse grouped counts for metrics and reports instead of scanning devices twice.
    $metrics['activeRepairs'] = 0;
    foreach ($statusCounts as $count) {
        if (!in_array($count['repair_status'], ['DRAFT', 'COMPLETED', 'CANCELLED'], true)) {
            $metrics['activeRepairs'] += (int) $count['total'];
        }
    }
    $metrics['technicians'] = (int) $pdo
        ->query(
            "SELECT COUNT(*) FROM users u
        WHERE u.role = 'TECHNICIAN' AND u.is_active = 1 AND EXISTS (
        SELECT 1 FROM user_branch_assignments tech_a
        JOIN user_branch_assignments admin_a ON admin_a.branch_id = tech_a.branch_id
        JOIN branches b ON b.branch_id = tech_a.branch_id AND b.is_active = 1
        WHERE tech_a.user_id = u.user_id AND admin_a.user_id = $userId)",
        )
        ->fetchColumn();

    $manila = new DateTimeZone('Asia/Manila');
    $utc = new DateTimeZone('UTC');
    $today = (new DateTimeImmutable('now', $manila))->setTime(0, 0);
    $fromUtc = $today->setTimezone($utc)->format('Y-m-d H:i:s');
    $toUtc = $today->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');

    $ticketTotals = $pdo->prepare("SELECT
        COALESCE(SUM(t.record_stage = 'DRAFT'), 0) AS drafts,
        COALESCE(SUM(t.record_stage = 'ISSUED' AND t.issued_at >= ? AND t.issued_at < ?), 0) AS issued_today
        FROM repair_tickets t WHERE $scope");
    $ticketTotals->execute([$fromUtc, $toUtc]);
    $totals = $ticketTotals->fetch();
    $metrics['pendingIntake'] = (int) $totals['drafts'];
    $metrics['ticketsIssuedToday'] = (int) $totals['issued_today'];

    // Count and page the same query so the totals cannot silently disagree.
    $queueSql = "SELECT t.ticket_id, d.item_code, d.device_type_snapshot, d.make_snapshot, d.model_snapshot,
        d.repair_status, d.updated_at, t.ticket_code, c.full_name AS customer_name
        FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        LEFT JOIN customers c ON c.customer_id = t.customer_id
        WHERE t.record_stage = 'ISSUED' AND $scope
        AND (COALESCE(t.ticket_code, '') LIKE ? ESCAPE '!'
            OR COALESCE(c.full_name, '') LIKE ? ESCAPE '!'
            OR COALESCE(d.model_snapshot, '') LIKE ? ESCAPE '!')";
    $pages['queue'] = dashboardPage(
        $pdo,
        $queueSql,
        [$search, $search, $search],
        'updated_at DESC, item_code DESC',
        requestText($input, 'queue_page', '1'),
    );

    $customerSql = "SELECT c.customer_id, c.full_name, c.updated_at, COUNT(DISTINCT t.ticket_id) AS ticket_count
        FROM customers c
        JOIN repair_tickets t ON t.customer_id = c.customer_id AND $scope
        WHERE (c.full_name LIKE ? ESCAPE '!'
        OR EXISTS (SELECT 1 FROM customer_contacts cc WHERE cc.customer_id = c.customer_id
            AND cc.contact_value LIKE ? ESCAPE '!'))
        GROUP BY c.customer_id, c.full_name, c.updated_at";
    $pages['customers'] = dashboardPage(
        $pdo,
        $customerSql,
        [$search, $search],
        'updated_at DESC, customer_id DESC',
        requestText($input, 'customers_page', '1'),
    );

    $draftSql = "SELECT t.ticket_id, t.arrival_method, t.created_at, c.full_name AS customer_name, b.branch_code
        FROM repair_tickets t
        JOIN branches b ON b.branch_id = t.intake_branch_id
        LEFT JOIN customers c ON c.customer_id = t.customer_id
        WHERE t.record_stage = 'DRAFT' AND $scope
        AND (CAST(t.ticket_id AS CHAR) LIKE ? ESCAPE '!'
            OR COALESCE(c.full_name, '') LIKE ? ESCAPE '!')";
    $pages['drafts'] = dashboardPage(
        $pdo,
        $draftSql,
        [$search, $search],
        'created_at DESC, ticket_id DESC',
        requestText($input, 'drafts_page', '1'),
    );
    $queue = $pages['queue']['rows'];
    $customers = $pages['customers']['rows'];
    $drafts = $pages['drafts']['rows'];

    return [
        'pages' => $pages,
        'metrics' => $metrics,
        'queue' => $queue,
        'customers' => $customers,
        'drafts' => $drafts,
        'status_counts' => $statusCounts,
    ];
}

function dashboardDeviceLabel(array $device): string
{
    $parts = array_filter(
        [$device['make_snapshot'] ?? '', $device['model_snapshot'] ?? ''],
        static fn($value) => is_string($value) && trim($value) !== '',
    );

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

// SQL/order fragments come only from this file. User text is always bound.
function dashboardPage(
    PDO $pdo,
    string $sql,
    array $parameters,
    string $order,
    string $requestedPage,
): array {
    $count = $pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS records');
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 12));
    $page = ctype_digit($requestedPage) ? min($pages, max(1, (int) $requestedPage)) : 1;
    $offset = ($page - 1) * 12;
    $query = $pdo->prepare($sql . ' ORDER BY ' . $order . ' LIMIT 12 OFFSET ' . $offset);
    $query->execute($parameters);
    return [
        'rows' => $query->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'from' => $total ? $offset + 1 : 0,
        'to' => min($offset + 12, $total),
    ];
}
