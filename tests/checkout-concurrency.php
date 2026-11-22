<?php
declare(strict_types=1);

// Run with: php tests/payment-pickup.php --concurrency
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/web.php';

function runCheckoutWorker(): void
{
    require_once __DIR__ . '/../app/db.php';
    require_once __DIR__ . '/../app/payments/repository.php';
    $pdo = getPDO();
    if (!str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')) {
        throw new RuntimeException('Select a disposable test database.');
    }
    // The parent releases both connections only after each is ready.
    echo "ready\n";
    fflush(STDOUT);
    [$action, $actor, $id, $version] = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
    try {
        switch ($action) {
            case 'reserve':
                stockChange($pdo, $actor, $id, (int) $version, 0, 'reserve');
                break;
            case 'progress':
                consoleSave($pdo, $actor, $id, $version, [
                    'action' => 'progress', 'status' => 'READY_FOR_PICKUP', 'note' => 'Counter check complete.',
                ]);
                break;
            case 'issue':
                cashIssueFinalBill($pdo, $actor, $id, $version);
                break;
            case 'payment':
                cashRecordPayment($pdo, $actor, $id, $version, '100.00', bin2hex(random_bytes(32)));
                break;
            case 'handover':
                cashCompleteHandover($pdo, $actor, $id, $version, 'Test recipient', 'CUSTOMER', 'yes');
                break;
            default:
                throw new RuntimeException('Unexpected worker action.');
        }
        echo 'ok';
    } catch (DomainException $error) {
        echo $error->getMessage();
    }
}

function raceCheckout(string $action, array $staff, int $id, string $version): array
{
    $workers = [];
    try {
        foreach ($staff as $actor) {
            $process = proc_open([getenv('REPAIRTRACK_TEST_PHP') ?: PHP_BINARY, __FILE__, '--worker'],
                [['pipe', 'r'], ['pipe', 'w'], STDERR], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start a PHP test worker.');
            }
            $workers[] = [$process, $pipes, $actor];
        }
        foreach ($workers as [, $pipes]) {
            if (fgets($pipes[1]) !== "ready\n") {
                throw new RuntimeException('Worker did not connect to the test database.');
            }
        }
        foreach ($workers as [, $pipes, $actor]) {
            $args = [$action, $actor['user_id'], $actor['target_id'] ?? $id, $actor['item_id'] ?? $version];
            fwrite($pipes[0], json_encode($args, JSON_THROW_ON_ERROR) . "\n");
            fclose($pipes[0]);
        }
        $results = [];
        foreach ($workers as [, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }
}

function testCheckoutRaces(PDO $pdo, int $ticket, int $device, array $staff): void
{
    [$manager, $firstCashier, $secondCashier, $otherManager, $technician] = $staff;
    $pdo->prepare('UPDATE ticket_devices SET assigned_technician_id = ? WHERE ticket_device_id = ?')
        ->execute([$technician['user_id'], $device]);
    $version = consoleVersion(consoleDevice($pdo, $technician, $device));
    $results = raceCheckout('progress', [$technician, $technician], $device, $version);
    sort($results);
    expectResult($results === ['This device changed. Reload before saving.', 'ok'],
        'Both technician sessions saved the same review: ' . implode(' / ', $results));
    $events = $pdo->prepare('SELECT COUNT(*) FROM ticket_status_events WHERE ticket_device_id = ?');
    $events->execute([$device]);
    expectResult((int) $events->fetchColumn() === 1, 'Duplicate technician progress event.');
    $version = cashTicketVersion(cashPageTicket($pdo, $manager, $ticket));
    $results = raceCheckout('issue', [$manager, $otherManager], $ticket, $version);
    expectResult($results === ['ok', 'ok'], 'Both issuance requests must resolve to the existing bill.');
    $query = $pdo->prepare('SELECT bill_id FROM repair_bills WHERE ticket_id = ?');
    $query->execute([$ticket]);
    $bills = $query->fetchAll(PDO::FETCH_COLUMN);
    expectResult(count($bills) === 1, 'Managers created duplicate bills.');
    $bill = (int) $bills[0];
    foreach (['payment', 'handover'] as $action) {
        $record = cashBillDetails($pdo, $firstCashier, $bill);
        $results = raceCheckout($action, [$firstCashier, $secondCashier], $bill, $record['version']);
        $accepted = array_keys($results, 'ok', true);
        expectResult(count($accepted) === 1, "$action race: " . implode(' / ', $results));
        $refusal = $results[1 - $accepted[0]];
        expectResult(str_contains($refusal, 'bill changed') || str_contains($refusal, 'open ticket'),
            "$action returned an unexpected refusal: $refusal");
    }
    $record = cashBillDetails($pdo, $firstCashier, $bill);
    expectResult(count($record['payments']) === 1 && $record['amount_paid'] === '100.00'
        && $record['case_state'] === 'CLOSED', 'Unexpected final receipts or ticket state.');
    $query = $pdo->prepare("SELECT COUNT(*) FROM device_custody_events
        WHERE ticket_device_id = ? AND event_type = 'RETURNED_TO_CUSTOMER'");
    $query->execute([$device]);
    expectResult((int) $query->fetchColumn() === 1, 'Duplicate or missing custody event.');
    echo "PASS: one technician update, one bill, one payment and one handover under concurrent submissions.\n";
}

if (($argv[1] ?? '') === '--worker') {
    runCheckoutWorker();
}