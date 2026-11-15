<?php
declare(strict_types=1);

function technicianActiveLimit(): int
{
    $file = __DIR__ . '/../../config/config.php';
    $config = is_file($file) ? require $file : [];
    return max(1, min(50, (int) ($config['technician_active_limit'] ?? 5)));
}
function consoleActiveSql(): string
{
    return "t.case_state = 'OPEN' AND d.repair_status NOT IN ('COMPLETED', 'CANCELLED')";
}
function consoleView(array $user, array $input): string
{
    $allowed = match ($user['role']) {
        'CASHIER' => ['cash'],
        'COURIER' => ['queue', 'history'],
        default => ['queue', 'board', 'history'],
    };
    if (in_array($user['role'], ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)) {
        $allowed[] = 'drafts';
    }
    if (in_array($user['role'], ['ADMIN', 'MANAGER'], true)) {
        $allowed[] = 'cash';
    }
    $view = requestText($input, 'view');
    return in_array($view, $allowed, true) ? $view : $allowed[0];
}
function workspaceDate(?string $utc): string
{
    if (!$utc) {
        return 'Not recorded';
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Manila'))
        ->format('M j, Y · g:i a');
}
// consoleSave locks the technician row before counting, serializing assignments.
function technicianActiveCount(PDO $pdo, int $technicianId): int
{
    $query = $pdo->prepare(
        "SELECT COUNT(*) FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        WHERE d.assigned_technician_id = ? AND t.record_stage = 'ISSUED' AND " . consoleActiveSql(),
    );
    $query->execute([$technicianId]);
    return (int) $query->fetchColumn();
}
function consoleStartTrend(PDO $pdo, array $user): array
{
    [$scope, $params] = consoleScope($user);
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $from = $today->modify('-6 days');
    $query = $pdo->prepare("SELECT DATE(CONVERT_TZ(d.repair_started_at, '+00:00', '+08:00')) AS day,
        COUNT(*) AS total FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        WHERE $scope AND d.repair_started_at >= ? AND d.repair_started_at < ? GROUP BY day");
    $query->execute([
        ...$params,
        $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $today->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ]);
    $totals = array_column($query->fetchAll(), 'total', 'day');
    $days = [];
    for ($offset = 0; $offset < 7; $offset++) {
        $day = $from->modify('+' . $offset . ' days');
        $days[] = [
            'label' => $day->format('M j'),
            'date' => $day->format('F j, Y'),
            'total' => (int) ($totals[$day->format('Y-m-d')] ?? 0),
        ];
    }
    return $days;
}
// Bill lookup only: a bill is not proof of cash collection.
function consoleCashDesk(PDO $pdo, array $user, array $input): array
{
    if (!in_array($user['role'], ['ADMIN', 'MANAGER', 'CASHIER'], true)) {
        throw new DomainException('This role cannot open the cash desk.');
    }
    $term = likePattern(mb_substr(trim(requestText($input, 'q')), 0, 100));
    $sql = "SELECT rb.bill_id, rb.bill_number, rb.bill_status, rb.total_amount, rb.currency,
        rb.issued_at, t.ticket_code, t.portal_code, b.branch_name
        FROM repair_bills rb JOIN repair_tickets t ON t.ticket_id = rb.ticket_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id AND b.is_active = 1
        WHERE t.record_stage = 'ISSUED' AND rb.bill_status <> 'DRAFT'
        AND EXISTS (SELECT 1 FROM user_branch_assignments a WHERE a.user_id = ? AND a.branch_id = b.branch_id)
        AND (t.ticket_code LIKE ? ESCAPE '!' OR t.portal_code LIKE ? ESCAPE '!' OR rb.bill_number LIKE ? ESCAPE '!')";
    return dashboardPage(
        $pdo,
        $sql,
        [(int) $user['user_id'], $term, $term, $term],
        'issued_at DESC, bill_id DESC',
        requestText($input, 'page', '1'),
    );
}
