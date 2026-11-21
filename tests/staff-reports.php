<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/console/reports.php';

function checkReport(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function reportFixtureInsert(PDO $pdo, string $sql, array $values): int
{
    $pdo->prepare($sql)->execute($values);
    return (int) $pdo->lastInsertId();
}

$pdo = getPDO();
checkReport(str_starts_with($pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_'),
    'Use a disposable repairtrack_test_ database.');
$forBrowser = in_array('--browser-fixture', $argv, true);
$run = bin2hex(random_bytes(4));
$password = bin2hex(random_bytes(16));
$hash = password_hash($password, PASSWORD_DEFAULT);
$period = staffReportPeriod([]);
$midnight = new DateTimeImmutable($period['fromUtc'], new DateTimeZone('UTC'));
$at = static fn(string $offset): string => $midnight->modify($offset)->format('Y-m-d H:i:s');
$pdo->beginTransaction();

try {
    $branches = [];
    foreach (['market' => 'Sogod Market', 'station' => 'Town Center', 'quiet' => 'Service Annex',
        'outside' => 'External Branch', 'inactive' => 'Closed Branch'] as $key => $name) {
        $branches[$key] = reportFixtureInsert($pdo,
            'INSERT INTO branches (branch_code, branch_name, is_active) VALUES (?, ?, ?)',
            [$run . $key, $name, (int) ($key !== 'inactive')]);
    }
    $staff = [];
    $emails = [];
    foreach (['admin' => 'ADMIN', 'manager' => 'MANAGER', 'tech' => 'TECHNICIAN', 'peer' => 'TECHNICIAN',
        'cash' => 'CASHIER', 'detached' => 'CASHIER', 'viewer' => 'VIEWER',
        'courier' => 'COURIER', 'intake' => 'INTAKE_AGENT'] as $key => $role) {
        $emails[$key] = "$run-$key@example.test";
        $id = reportFixtureInsert($pdo,
            'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            ['Report ' . $key, $emails[$key], $hash, $role]);
        $staff[$key] = ['user_id' => $id, 'role' => $role];
        if ($key !== 'detached') {
            foreach (['market', 'station', 'quiet', 'inactive'] as $branch) {
                $pdo->prepare('INSERT INTO user_branch_assignments (user_id, branch_id) VALUES (?, ?)')
                    ->execute([$id, $branches[$branch]]);
            }
        }
    }
    $customer = reportFixtureInsert($pdo, 'INSERT INTO customers (full_name, normalized_name) VALUES (?, ?)',
        ['Report fixture', 'report fixture']);
    $ticket = static function (string $branch, string $offset, string $state = 'OPEN')
        use ($pdo, $at, $run, $branches, $customer, $staff): int {
        $issued = $at($offset);
        $localDate = (new DateTimeImmutable($issued, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
        return reportFixtureInsert($pdo,
            "INSERT INTO repair_tickets (ticket_code, record_stage, case_state, customer_id, intake_branch_id,
             accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, issued_at, terms_accepted,
             terms_signature_path, terms_signed_at) VALUES (?, 'ISSUED', ?, ?, ?, ?, ?, 'WALK_IN', ?, ?, 1, 'fixture', ?)",
            [$run . bin2hex(random_bytes(3)), $state, $customer, $branches[$branch], $branches[$branch],
                $staff['manager']['user_id'], $localDate, $issued, $issued]);
    };
    $device = static function (int $id, int $seq, string $owner, ?string $start, ?string $end, string $status)
        use ($pdo, $at, $staff): int {
        return reportFixtureInsert($pdo,
            "INSERT INTO ticket_devices (ticket_id, child_sequence, item_code, device_type_snapshot,
             serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed,
             repair_status, assigned_technician_id, repair_started_at, return_handover_at, custody_state)
             VALUES (?, ?, ?, 'Laptop', ?, 'No power', 'Intake checked', 1, ?, ?, ?, ?, ?)",
            [$id, $seq, "R-$id-$seq", "R-$id-$seq", $status, $staff[$owner]['user_id'],
                $start === null ? null : $at($start), $end === null ? null : $at($end),
                $end === null ? 'AT_LOCATION' : 'WITH_CUSTOMER']);
    };
    $bill = static function (int $ticketId, string $amount, string $currency = 'PHP', string $status = 'ISSUED')
        use ($pdo, $at, $staff): int {
        return reportFixtureInsert($pdo,
            'INSERT INTO repair_bills (ticket_id, bill_number, bill_status, currency, total_amount,
             prepared_by_user_id, issued_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$ticketId, 'B-' . $ticketId, $status, $currency, $amount, $staff['manager']['user_id'], $at('+0 seconds')]);
    };
    $pay = static function (int $billId, string $amount, string $offset) use ($pdo, $at, $staff): void {
        $pdo->prepare('INSERT INTO repair_bill_payments (bill_id, amount, request_key, received_by_user_id,
            received_at) VALUES (?, ?, ?, ?, ?)')->execute([$billId, $amount, bin2hex(random_bytes(32)),
                $staff['cash']['user_id'], $at($offset)]);
    };

    $multi = $ticket('market', '+0 seconds', 'CLOSED');
    $first = $device($multi, 1, 'tech', '+1 day', '+3 days', 'COMPLETED');
    $second = $device($multi, 2, 'peer', '+1 day', '+3 days', 'COMPLETED');
    foreach ([[$first, 'IN_PROGRESS', 'TESTING'], [$first, 'TESTING', 'TESTING'],
        [$first, 'TESTING', 'IN_PROGRESS'], [$first, 'IN_PROGRESS', 'TESTING'],
        [$first, 'TESTING', 'READY_FOR_PICKUP'], [$first, 'READY_FOR_PICKUP', 'READY_FOR_PICKUP'],
        [$second, 'IN_PROGRESS', 'TESTING']] as [$deviceId, $from, $to]) {
        $pdo->prepare('INSERT INTO ticket_status_events (ticket_device_id, from_status, to_status, created_at)
            VALUES (?, ?, ?, ?)')->execute([$deviceId, $from, $to, $at('+2 days')]);
    }
    $multiBill = $bill($multi, '1000.10');
    $pay($multiBill, '420.05', '+1 day -1 second');
    $pay($multiBill, '580.05', '+1 day');
    $working = $ticket('market', '+4 days');
    $device($working, 1, 'tech', '+6 days', null, 'IN_PROGRESS');
    $older = $ticket('market', '-1 second');
    $device($older, 1, 'tech', '-1 second', null, 'READY_FOR_PICKUP');
    $pay($bill($older, '100.00'), '12.37', '-1 second');
    $otherOpen = $ticket('market', '-17 days');
    $device($otherOpen, 1, 'peer', null, null, 'RECEIVED');
    $bill($otherOpen, '2.37');
    $voided = $bill($ticket('market', '-15 days'), '2.00', 'PHP', 'VOIDED');
    $pay($voided, '0.10', '+5 days');
    $pay($voided, '1.90', '+7 days'); // Exactly midnight of the next reporting period.
    $pay($bill($ticket('market', '-4 days'), '300.00', 'USD'), '1.50', '+4 days');
    $bill($ticket('market', '-4 days'), '800.00', 'PHP', 'DRAFT');
    $station = $ticket('station', '+2 days');
    $device($station, 1, 'tech', '+4 days', null, 'READY_FOR_PICKUP');
    $pay($bill($station, '40.00'), '9.99', '+6 days');
    foreach (['inactive', 'outside'] as $key) {
        $hidden = $ticket($key, '+1 day', 'CLOSED');
        $device($hidden, 1, 'tech', '+2 days', '+4 days', 'COMPLETED');
        $pay($bill($hidden, '999.00'), '999.00', '+3 days');
    }
    $draft = reportFixtureInsert($pdo, "INSERT INTO repair_tickets (intake_branch_id, accountable_branch_id,
        accepted_by_user_id, arrival_method) VALUES (?, ?, ?, 'WALK_IN')",
        [$branches['market'], $branches['market'], $staff['manager']['user_id']]);
    $device($draft, 1, 'tech', '+2 days', null, 'DRAFT');

    $market = $branches['market'];
    $ops = loadStaffReport($pdo, $staff['admin'], $period, $market);
    checkReport($ops['totals'] === ['received' => 3, 'started' => 3, 'returned' => 2], 'Multi-device operations totals');
    checkReport($ops['averageHours'] == 72.0 && $ops['timedReturns'] === 2, 'Turnaround uses valid device returns');
    $pdo->exec('SAVEPOINT more_returns');
    $device($ticket('market', '-1 day', 'CLOSED'), 1, 'tech', null, '+0 seconds', 'COMPLETED');
    $device($ticket('market', '+3 days', 'CLOSED'), 1, 'tech', null, '+2 days', 'COMPLETED');
    $times = loadStaffReport($pdo, $staff['admin'], $period, $market);
    checkReport($times['averageHours'] == 56.0 && $times['timedReturns'] === 3 && $times['totals']['returned'] === 4,
        'Average weights devices, includes older intakes and excludes reversed date pairs');
    $pdo->exec('ROLLBACK TO SAVEPOINT more_returns');
    checkReport(loadStaffReport($pdo, $staff['manager'], $period, $market) === $ops, 'Manager and admin use the same branch rules');
    checkReport(loadStaffReport($pdo, $staff['manager'], $period, null)['totals'] ===
        ['received' => 4, 'started' => 4, 'returned' => 2], 'Only active assigned branches contribute');
    $tech = loadStaffReport($pdo, $staff['tech'], $period, $market);
    checkReport($tech['totals'] === ['started' => 2, 'tested' => 2, 'ready' => 1], 'Current technician scope, re-tests and notes');
    checkReport(array_sum(array_column($tech['statusCounts'], 'total')) === 2, 'Only active assigned workload');
    $pdo->prepare('UPDATE ticket_devices SET assigned_technician_id = ? WHERE ticket_device_id = ?')
        ->execute([$staff['peer']['user_id'], $first]);
    checkReport(loadStaffReport($pdo, $staff['tech'], $period, $market)['totals'] ===
        ['started' => 1, 'tested' => 0, 'ready' => 0], 'Reassignment immediately removes the previous device history');
    $pdo->prepare('UPDATE ticket_devices SET assigned_technician_id = ? WHERE ticket_device_id = ?')
        ->execute([$staff['tech']['user_id'], $first]);
    $cash = loadStaffReport($pdo, $staff['cash'], $period, $market);
    checkReport($cash['totals'] === ['cash_cents' => 100020, 'payments' => 3, 'returned' => 2], 'Exact gross receipts without joining devices or currencies');
    checkReport($cash['balance'] === ['bills' => 2, 'cents' => 9000], 'Current balances include old partial payments and unpaid bills');
    $firstDay = $period['start']->format('Y-m-d');
    $nextDay = $period['start']->modify('+1 day')->format('Y-m-d');
    checkReport($cash['daily'][$firstDay]['cash_cents'] === 42005 && $cash['daily'][$nextDay]['cash_cents'] === 58005,
        'Payments one second apart straddle Philippine midnight');
    checkReport(staffReportValue('cash_cents', 100020) === '₱1,000.20', 'Peso formatting preserves centavos');
    foreach (['7' => 7, '30' => 6, '90' => 6] as $days => $bars) {
        $range = staffReportPeriod(['days' => (string) $days]);
        $result = loadStaffReport($pdo, $staff['cash'], $range, $market);
        $series = staffReportChart($result['daily'], 'cash_cents');
        $lastPage = staffReportPage($result['daily'], ['page' => '999']);
        checkReport(count($result['daily']) === $days && count($series) === $bars, 'Range and chart bucket sizes');
        checkReport(array_sum(array_column($series, 'total')) === $result['totals']['cash_cents'], 'Chart reconciles to total');
        checkReport($lastPage['number'] === (int) ceil($days / 10) && count($lastPage['rows']) <= 10, 'Pagination clamps');
        checkReport(staffReportPage($result['daily'], ['page' => 'bad'])['number'] === 1, 'Invalid page fallback');
    }
    $year = staffReportPeriod(['days' => '7'], new DateTimeImmutable('2026-12-31 16:00:00+00:00'));
    checkReport($year['today']->format('Y-m-d') === '2027-01-01' && $year['untilUtc'] === '2027-01-01 16:00:00', 'Philippine year boundary');
    checkReport(staffReportPeriod(['days' => ['30']])['days'] === 7, 'Malformed period falls back safely');
    $grants = intakeBranches($pdo, $staff['tech']['user_id']);
    checkReport(staffReportBranch(['branch' => (string) $market], $grants) === $market, 'Assigned branch selection');
    foreach ([(string) $branches['outside'], (string) $branches['inactive'], ['1']] as $invalid) {
        try {
            staffReportBranch(['branch' => $invalid], $grants);
            throw new RuntimeException('Invalid branch selection accepted');
        } catch (DomainException $expected) {
            // Also check the underlying query in case a caller forgets to validate the selector.
        }
    }
    foreach (['outside', 'inactive', 'quiet'] as $key) {
        $empty = loadStaffReport($pdo, $staff['cash'], $period, $branches[$key]);
        checkReport(max($empty['totals']) === 0 && $empty['balance']['cents'] === 0, 'Empty or inaccessible scope');
    }
    foreach (['viewer', 'courier', 'intake'] as $key) {
        checkReport(!consoleCanViewReports($staff[$key]), 'Role has no report navigation');
        try {
            loadStaffReport($pdo, $staff[$key], $period, null);
            throw new RuntimeException('Unsupported role read report data');
        } catch (DomainException $expected) {
            // The repository refuses the same roles as the page.
        }
    }
    checkReport(array_column(consoleStartTrend($pdo, $staff['tech']), 'total') ===
        array_column(loadStaffReport($pdo, $staff['tech'], $period, null)['daily'], 'started'),
        'Queue and report agree on repair starts');

    if ($forBrowser) {
        $pdo->commit();
        echo json_encode(['emails' => $emails, 'password' => $password, 'branches' => $branches], JSON_THROW_ON_ERROR);
    } else {
        echo "PASS staff reports: roles, branches, milestones, gross cash, balances, date boundaries, trends and pagination.\n";
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
