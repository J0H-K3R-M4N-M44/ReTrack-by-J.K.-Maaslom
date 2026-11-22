<?php
declare(strict_types=1);

// This is a reject-only authorization test, not a simulated approval endpoint.
// It MUST run against a newly initialized disposable repairtrack_test_guard.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/quotes/repository.php';
require_once __DIR__ . '/../app/payments/repository.php';

function guardCheck(bool $condition, string $case): void
{
    if (!$condition) {
        throw new RuntimeException($case);
    }
}
function guardDenied(callable $callback, string $case): void
{
    try {
        $callback();
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException('Unauthorized operation succeeded: ' . $case);
}
function guardInsert(PDO $pdo, string $query, array $parameters): int
{
    $pdo->prepare($query)->execute($parameters);
    return (int) $pdo->lastInsertId();
}
function guardVisible(PDO $pdo, array $user, int $ticket): array
{
    [$scope, $parameters] = consoleScope($user);
    $statement = $pdo->prepare("SELECT d.ticket_device_id FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.ticket_id=? AND $scope ORDER BY d.ticket_device_id");
    $statement->execute([$ticket, ...$parameters]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
guardCheck((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === 'repairtrack_test_guard',
    'Refusing any non-disposable repairtrack_test_guard database.');
guardCheck(consoleRepairGuardMode() === 'restrict',
    'This integration round requires the server-owned restrict flag.');
guardCheck((int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1,
    'Foreign-key enforcement is disabled.');
$fixtureCommitted = false;
$pdo->beginTransaction();
try {
    $seed = strtoupper(bin2hex(random_bytes(5)));
    $origin = guardInsert($pdo, 'INSERT INTO branches(branch_code,branch_name) VALUES (?,?)',
        [$seed . 'A', 'Guard fixture origin']);
    $destination = guardInsert($pdo, 'INSERT INTO branches(branch_code,branch_name) VALUES (?,?)',
        [$seed . 'B', 'Guard fixture receiver']);
    $originLocation = guardInsert($pdo,
        "INSERT INTO storage_locations(branch_id,location_code,location_name,location_type)
            VALUES (?,'GUARD-A','Fixture origin counter','WORKBENCH')", [$origin]);
    $receiverLocation = guardInsert($pdo,
        "INSERT INTO storage_locations(branch_id,location_code,location_name,location_type)
            VALUES (?,'GUARD-B','Fixture receiver counter','WORKBENCH')", [$destination]);

    $userAt = static function(string $role, int $branch) use ($pdo, $seed): array {
        $id = guardInsert($pdo,
            'INSERT INTO users(full_name,email,password_hash,role) VALUES (?,?,?,?)',
            ['Guard ' . $role, strtolower($seed) . '-' . $branch . '-' . strtolower($role)
                . '@example.test', 'unused-test-password', $role]);
        $pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES (?,?)')
            ->execute([$id, $branch]);
        return ['user_id' => $id, 'role' => $role];
    };
    $manager = $userAt('MANAGER', $origin);
    $receivingManager = $userAt('MANAGER', $destination);
    $originTechnician = $userAt('TECHNICIAN', $origin);
    $receivingTechnician = $userAt('TECHNICIAN', $destination);
    $viewer = $userAt('VIEWER', $origin);
    $originCashier = $userAt('CASHIER', $origin);
    $receivingCashier = $userAt('CASHIER', $destination);

    $customer = guardInsert($pdo,
        'INSERT INTO customers(full_name,normalized_name) VALUES (?,?)',
        ['Guard Test Customer', 'guard test customer']);
    $ticket = guardInsert($pdo,
        "INSERT INTO repair_tickets
            (ticket_code,record_stage,case_state,customer_id,intake_branch_id,
             accountable_branch_id,accepted_by_user_id,arrival_method,
             intake_date,issued_at,terms_accepted,terms_signature_path,terms_signed_at)
         VALUES (?,'ISSUED','OPEN',?,?,?,?,'WALK_IN',UTC_DATE(),
             UTC_TIMESTAMP(),1,'private-fixture',UTC_TIMESTAMP())",
        [$seed . '-T', $customer, $origin, $origin, $manager['user_id']]);

    $addDevice = static function(int $sequence, int $repairBranch, int $location)
        use ($pdo, $seed, $ticket, $originTechnician): int {
        return guardInsert($pdo,
            "INSERT INTO ticket_devices
                (ticket_id,child_sequence,item_code,device_type_snapshot,reported_symptoms,
                condition_notes,serial_number_snapshot,accessory_checklist_completed,
                repair_status,custody_state,physical_location_id,repair_branch_id,assigned_technician_id)
             VALUES (?, ?, ?,'Laptop','Battery intermittent','Fixture inspected',?,1,
                'DIAGNOSING','AT_LOCATION',?,?,?)",
            [$ticket, $sequence, $seed . '-D' . $sequence, $seed . '-S' . $sequence,
                $location, $repairBranch, $originTechnician['user_id']]);
    };
    $aligned = $addDevice(1, $origin, $originLocation);
    $mismatched = $addDevice(2, $destination, $receiverLocation);
    // Production mutation functions own their own transactions. Commit this
    // synthetic fixture first, then inspect writes independently. It is on a
    // disposable database and is removed in finally on all exit paths.
    $pdo->commit();
    $fixtureCommitted = true;

    // Existing permissions are never broadened by the guard.
    guardCheck(guardVisible($pdo, $manager, $ticket) === [$aligned],
        'Origin manager gained mismatched sibling repair access.');
    guardCheck(guardVisible($pdo, $originTechnician, $ticket) === [$aligned],
        'Assigned technician gained mismatched sibling repair access.');
    guardCheck(guardVisible($pdo, $viewer, $ticket) === [$aligned],
        'Viewer gained mismatched sibling detail access.');
    guardCheck(guardVisible($pdo, $receivingManager, $ticket) === [],
        'Receiving branch gained repair access without authorization.');
    guardCheck(guardVisible($pdo, $receivingTechnician, $ticket) === [],
        'Receiving technician gained repair access without acceptance.');

    $legacy = consoleRepairAccessScope($manager, 'legacy');
    $legacyStmt = $pdo->prepare("SELECT d.ticket_device_id FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.ticket_id=? AND {$legacy[0]} ORDER BY d.ticket_device_id");
    $legacyStmt->execute([$ticket, ...$legacy[1]]);
    guardCheck(array_map('intval', $legacyStmt->fetchAll(PDO::FETCH_COLUMN))
        === [$aligned, $mismatched], 'Legacy mode no longer preserves prior ticket access.');

    $details = consoleDevice($pdo, $manager, $aligned);
    guardCheck((int)$details['repair_branch_id'] === $origin, 'Aligned detail unavailable.');
    guardDenied(fn() => consoleDevice($pdo, $manager, $mismatched),
        'Origin manager opening mismatched device.');
    guardDenied(fn() => consoleDevice($pdo, $receivingManager, $mismatched),
        'Receiving manager opening device by physical possession.');
    guardDenied(fn() => consoleDevice($pdo, $originTechnician, $mismatched),
        'Origin technician opening mismatched assigned device.');
    guardDenied(fn() => quoteDevice($pdo, $manager, $mismatched),
        'Manager opening mismatched quote.');
    guardDenied(fn() => quoteSave($pdo, (int)$manager['user_id'], $mismatched,
        quoteVersion(null), 'draft',
        ['labor_description'=>'Unauthorized work','labor_amount'=>'120.00']),
        'Manager writing mismatched quote.');
    guardDenied(fn() => consoleSave($pdo, (int)$originTechnician['user_id'],
        $mismatched, 'unusable-version',
        ['action'=>'progress','status'=>'AWAITING_PARTS','note'=>'Unauthorized', 'public_message'=>'']),
        'Technician writing a repair event across branch mismatch.');
    guardDenied(fn() => stockChange($pdo, (int)$manager['user_id'], $mismatched,
        123, 0, 'reserve'), 'Manager reserving stock for mismatched device.');

    $ticketSummary = cashPageTicket($pdo, $manager, $ticket);
    guardCheck(count($ticketSummary['devices']) === 2,
        'Ticket-level financial authority lost the full contract and sibling records.');
    guardDenied(fn() => cashPageTicket($pdo, $receivingManager, $ticket),
        'Receiving manager gained ticket-wide cash privileges.');
    foreach ([$originCashier, $receivingCashier] as $cashier) {
        [$financeScope, $financeParameters] = consoleTicketFinanceScope($cashier);
        guardCheck(!str_contains($financeScope, 'd.'),
            'Financial access depends on an unavailable device alias.');
        $financeStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets t
            WHERE t.ticket_id=? AND $financeScope");
        $financeStmt->execute([$ticket, ...$financeParameters]);
        guardCheck((int)$financeStmt->fetchColumn() ===
            ($cashier['user_id'] === $originCashier['user_id'] ? 1 : 0),
            'Cash scope followed device branch instead of ticket branch.');
    }

    // Revocation and deactivation continue to take effect immediately.
    $pdo->prepare('UPDATE users SET is_active=0 WHERE user_id=?')
        ->execute([$manager['user_id']]);
    guardCheck(guardVisible($pdo, $manager, $ticket) === [],
        'Disabled manager retained device access in restrictive mode.');
    $pdo->prepare('UPDATE users SET is_active=1 WHERE user_id=?')
        ->execute([$manager['user_id']]);
    $pdo->prepare('UPDATE branches SET is_active=0 WHERE branch_id=?')
        ->execute([$origin]);
    guardCheck(guardVisible($pdo, $manager, $ticket) === [],
        'Inactive origin branch retained access.');
    $pdo->prepare('UPDATE branches SET is_active=1 WHERE branch_id=?')
        ->execute([$origin]);

    foreach ([
        'repair_quotes', 'repair_bill_payments', 'stock_reservations',
        'inventory_transactions', 'ticket_status_events', 'audit_events',
    ] as $table) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        guardCheck($count === 0,
            'Denied repair/quote/stock operation left persistent events in ' . $table);
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($fixtureCommitted) {
        // Delete only this invocation's random fictional fixture. The test
        // DB itself is ephemeral; never use this script for persistent data.
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ticket_devices WHERE ticket_id=?')
                ->execute([$ticket]);
            $pdo->prepare('DELETE FROM repair_tickets WHERE ticket_id=?')
                ->execute([$ticket]);
            $pdo->prepare('DELETE FROM customers WHERE customer_id=?')
                ->execute([$customer]);
            foreach ([$manager, $receivingManager, $originTechnician,
                $receivingTechnician, $viewer, $originCashier, $receivingCashier] as $account) {
                $pdo->prepare('DELETE FROM user_branch_assignments WHERE user_id=?')
                    ->execute([$account['user_id']]);
                $pdo->prepare('DELETE FROM users WHERE user_id=?')
                    ->execute([$account['user_id']]);
            }
            $pdo->prepare('DELETE FROM storage_locations WHERE location_id IN (?,?)')
                ->execute([$originLocation, $receiverLocation]);
            $pdo->prepare('DELETE FROM branches WHERE branch_id IN (?,?)')
                ->execute([$origin, $destination]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
echo "PASS: restrictive repair/quote/stock access, legacy preservation, ticket finance, revocations and cleanup.\n";
