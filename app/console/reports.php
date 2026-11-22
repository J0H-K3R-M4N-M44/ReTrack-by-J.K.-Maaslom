<?php
declare(strict_types=1);

require_once __DIR__ . '/repository.php';

function staffReportDefinition(string $role): array
{
    return match ($role) {
        'TECHNICIAN' => [
            'title' => 'Technician report',
            'scope' => 'Milestones on devices currently assigned to you.',
            'metrics' => [
                'started' => ['Repairs started', 'First repair start per device.'],
                'tested' => ['Testing runs', 'Each time a device enters testing.'],
                'ready' => ['Marked ready', 'Transitions to ready for pickup.'],
            ],
        ],
        'CASHIER' => [
            'title' => 'Cash desk report',
            'scope' => 'Counter collections in pesos and device returns across your selected branches.',
            'metrics' => [
                'cash_cents' => ['Cash collected', 'Gross counter payments on peso bills.'],
                'payments' => ['Payments recorded', 'Each partial payment counts once.'],
                'returned' => ['Devices returned', 'Recorded handovers to customers.'],
            ],
        ],
        'ADMIN', 'MANAGER' => [
            'title' => 'Branch operations report',
            'scope' => 'Track incoming devices, repair starts and customer handovers at your selected branches.',
            'metrics' => [
                'received' => ['Devices received', 'Devices on newly issued tickets.'],
                'started' => ['Repairs started', 'First repair start per device.'],
                'returned' => ['Devices returned', 'Recorded handovers to customers.'],
            ],
        ],
        default => throw new DomainException('Reports are unavailable for this role.'),
    };
}

// A report day runs from midnight to midnight in the Philippines. SQL compares UTC timestamps.
function staffReportPeriod(array $input, ?DateTimeImmutable $now = null): array
{
    $selection = requestText($input, 'days');
    $days = in_array($selection, ['7', '30', '90'], true) ? (int) $selection : 7;
    $manila = new DateTimeZone('Asia/Manila');
    $today = ($now ?? new DateTimeImmutable('now'))->setTimezone($manila)->setTime(0, 0);
    $start = $today->modify('-' . ($days - 1) . ' days');
    $utc = new DateTimeZone('UTC');

    return [
        'days' => $days,
        'start' => $start,
        'today' => $today,
        'fromUtc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
        'untilUtc' => $today->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
    ];
}

function staffReportBranch(array $input, array $branches): ?int
{
    $selection = $input['branch'] ?? '';
    if ($selection === '') {
        return count($branches) === 1 ? (int) $branches[0]['branch_id'] : null;
    }
    foreach ($branches as $branch) {
        if ((string) $branch['branch_id'] === $selection) {
            return (int) $branch['branch_id'];
        }
    }
    // A revoked or mistyped branch link must never silently widen to all branches.
    throw new DomainException('This branch is unavailable for your account.');
}

function staffReportLink(int $days, ?int $branchId, int $page = 1): string
{
    $query = ['days' => $days];
    if ($branchId !== null) {
        $query['branch'] = $branchId;
    }
    if ($page > 1) {
        $query['page'] = $page;
    }
    return 'reports.php?' . http_build_query($query);
}

// Callers below supply fixed SQL columns and expressions. User choices are bound as values.
function staffReportDailyRows(
    PDO $pdo,
    string $source,
    string $timestamp,
    string $measures,
    array $parameters,
    array $period,
): array {
    $query = $pdo->prepare(
        "SELECT DATE(CONVERT_TZ($timestamp, '+00:00', '+08:00')) AS day, $measures
         $source AND $timestamp >= ? AND $timestamp < ? GROUP BY day",
    );
    $query->execute([...$parameters, $period['fromUtc'], $period['untilUtc']]);
    return $query->fetchAll();
}

function staffReportOpenBalances(PDO $pdo, string $scope, array $parameters): array
{
    // Each bill contributes its outstanding balance once, including bills with no payments.
    $query = $pdo->prepare(
        "SELECT COUNT(*) AS bills, COALESCE(SUM(balance_cents), 0) AS cents
         FROM (
             SELECT CAST(100 * (rb.total_amount - COALESCE((
                 SELECT SUM(p.amount) FROM repair_bill_payments p WHERE p.bill_id = rb.bill_id
             ), 0)) AS SIGNED) AS balance_cents
             FROM repair_bills rb JOIN repair_tickets t ON t.ticket_id = rb.ticket_id
             WHERE $scope AND t.case_state = 'OPEN' AND rb.bill_status = 'ISSUED' AND rb.currency = 'PHP'
         ) balances WHERE balance_cents > 0",
    );
    $query->execute($parameters);
    $row = $query->fetch();
    return ['bills' => (int) $row['bills'], 'cents' => (int) $row['cents']];
}

function loadStaffReport(PDO $pdo, array $user, array $period, ?int $branchId): array
{
    $role = $user['role'];
    $definition = staffReportDefinition($role);
    // Cash reports refer to tickets/bills and have no device (d) alias. Keeping
    // this separate prevents a later device-scope rollout from breaking money
    // queries or granting cash access through physical repair custody.
    [$scope, $parameters] = $role === 'CASHIER'
        ? consoleTicketFinanceScope($user)
        : consoleScope($user);
    if ($branchId !== null) {
        $scope .= ' AND t.accountable_branch_id = ?';
        $parameters[] = $branchId;
    }
    $devices = "FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id WHERE $scope";
    $groups = [];
    $statusCounts = [];
    $balance = null;
    $returnSeconds = 0;
    $timedReturns = 0;

    if ($role === 'CASHIER') {
        // The payment record owns the money. Joining it to the devices would multiply a multi-device bill.
        $payments = "FROM repair_bill_payments p
            JOIN repair_bills rb ON rb.bill_id = p.bill_id
            JOIN repair_tickets t ON t.ticket_id = rb.ticket_id
            WHERE $scope AND rb.currency = 'PHP' AND rb.bill_status IN ('ISSUED', 'VOIDED')
            AND p.payment_method = 'COUNTER_CASH'";
        $groups[] = staffReportDailyRows($pdo, $payments, 'p.received_at',
            'CAST(100 * SUM(p.amount) AS SIGNED) AS cash_cents, COUNT(*) AS payments', $parameters, $period);
        $balance = staffReportOpenBalances($pdo, $scope, $parameters);
    } else {
        $groups[] = consoleStartsByDay($pdo, $scope, $parameters, $period['fromUtc'], $period['untilUtc']);
        $statusSource = $devices;
        if ($role === 'TECHNICIAN') {
            $statusSource .= ' AND ' . consoleActiveSql();
        }
        $query = $pdo->prepare("SELECT d.repair_status, COUNT(*) AS total $statusSource
            GROUP BY d.repair_status ORDER BY d.repair_status");
        $query->execute($parameters);
        $statusCounts = $query->fetchAll();
    }

    if ($role === 'TECHNICIAN') {
        // Same-stage notes leave the stage unchanged. Re-entering testing starts a new test run.
        $events = "FROM ticket_status_events e
            JOIN ticket_devices d ON d.ticket_device_id = e.ticket_device_id
            JOIN repair_tickets t ON t.ticket_id = d.ticket_id
            WHERE $scope AND e.to_status IN ('TESTING', 'READY_FOR_PICKUP')
            AND (e.from_status IS NULL OR e.from_status <> e.to_status)";
        $groups[] = staffReportDailyRows($pdo, $events, 'e.created_at',
            "SUM(e.to_status = 'TESTING') AS tested, SUM(e.to_status = 'READY_FOR_PICKUP') AS ready",
            $parameters, $period);
    } else {
        $handoverMeasures = 'COUNT(*) AS returned';
        if ($role !== 'CASHIER') {
            $groups[] = staffReportDailyRows($pdo, $devices, 't.issued_at', 'COUNT(*) AS received', $parameters, $period);
            // Average over devices with both valid timestamps, even when intake was before this period.
            $handoverMeasures .= ', SUM(d.return_handover_at >= t.issued_at) AS timed_returns,
                SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, t.issued_at, d.return_handover_at))) AS return_seconds';
        }
        $groups[] = staffReportDailyRows($pdo, $devices, 'd.return_handover_at', $handoverMeasures, $parameters, $period);
    }

    // Fill every calendar day first so quiet dates stay visible in both the chart and the table.
    $totals = array_fill_keys(array_keys($definition['metrics']), 0);
    $daily = [];
    for ($date = $period['start']; $date <= $period['today']; $date = $date->modify('+1 day')) {
        $daily[$date->format('Y-m-d')] = $totals;
    }
    foreach ($groups as $rows) {
        foreach ($rows as $row) {
            foreach ($totals as $metric => $unused) {
                if (isset($row[$metric])) {
                    $value = (int) $row[$metric];
                    $daily[$row['day']][$metric] = $value;
                    $totals[$metric] += $value;
                }
            }
            $returnSeconds += (int) ($row['return_seconds'] ?? 0);
            $timedReturns += (int) ($row['timed_returns'] ?? 0);
        }
    }
    return [
        'daily' => $daily,
        'totals' => $totals,
        'statusCounts' => $statusCounts,
        'balance' => $balance,
        'timedReturns' => $timedReturns,
        'averageHours' => $timedReturns ? $returnSeconds / $timedReturns / 3600 : null,
    ];
}

function staffReportValue(string $metric, int $amount): string
{
    if ($metric === 'cash_cents') {
        return '₱' . number_format(intdiv($amount, 100)) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
    return number_format($amount);
}

function staffReportChart(array $daily, string $metric): array
{
    // At most seven selectable bars, even on the 90-day view.
    $daysPerBar = match (count($daily)) {
        30 => 5,
        90 => 15,
        default => 1,
    };
    $bars = [];
    foreach (array_chunk($daily, $daysPerBar, true) as $days) {
        $first = new DateTimeImmutable(array_key_first($days));
        $last = new DateTimeImmutable(array_key_last($days));
        $label = $first->format('M j');
        $date = $first->format('M j, Y');
        if ($first != $last) {
            $label .= '–' . $last->format($first->format('Y-m') === $last->format('Y-m') ? 'j' : 'M j');
            $date .= ' – ' . $last->format('M j, Y');
        }
        $bars[] = ['label' => $label, 'date' => $date, 'total' => array_sum(array_column($days, $metric))];
    }
    return $bars;
}

function staffReportPage(array $daily, array $input): array
{
    $pages = (int) ceil(count($daily) / 10);
    $requested = requestText($input, 'page', '1');
    $number = ctype_digit($requested) ? min($pages, max(1, (int) $requested)) : 1;
    $offset = ($number - 1) * 10;
    return [
        'number' => $number,
        'pages' => $pages,
        'rows' => array_slice(array_reverse($daily, true), $offset, 10, true),
    ];
}
