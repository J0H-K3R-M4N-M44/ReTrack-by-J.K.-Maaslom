<?php
declare(strict_types=1);

// Loaded by the guarded disposable-database fixture in payment-pickup.php.
if (PHP_SAPI !== 'cli' || !isset($staff, $pdo)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/intake/issuance.php';
require_once __DIR__ . '/checkout-concurrency.php';

[$manager, $cashier, , $otherManager, $technician] = $staff;
$part = insertFixture($pdo, 'INSERT INTO parts (sku,part_name) VALUES (?,?)', [$run,'Pilot SSD']);
$neighbor = insertFixture($pdo, 'INSERT INTO branches (branch_code,branch_name) VALUES (?,?)', [$run.'B','Other shop']);
stockRun($pdo, 'INSERT INTO branch_inventory (branch_id,part_id,quantity_on_hand) VALUES (?,?,1),(?,?,4)',
    [$branch,$part,$neighbor,$part]);

function pilotProgress(PDO $pdo, array $tech, int $id, string $status): void
{
    consoleSave($pdo, $tech['user_id'], $id, consoleVersion(consoleDevice($pdo, $tech, $id)),
        ['action'=>'progress','status'=>$status,'note'=>'Pilot bench check']);
}

$jobs = [];
foreach ([$manager, $otherManager] as $operator) {
    $staffId = $operator['user_id'];
    $draftValues = array_replace(intakeDefaults(intakeBranches($pdo, $staffId)), [
        'customer_id'=>(string) $customer, 'device_type'=>'Laptop', 'make'=>'Pilot', 'model'=>'Notebook',
        'serial_number'=>bin2hex(random_bytes(8)), 'reported_symptoms'=>'Storage failure', 'condition_notes'=>'Checked at counter',
    ]);
    $ticketId = intakeSaveDraft($pdo, $draftValues, $staffId);
    $draft = intakeIssueDraft($pdo, $ticketId, $staffId);
    $id = (int) $draft['ticket_device_id'];
    $files = [];
    foreach (['photo','signed_form'] as $kind) {
        $name = bin2hex(random_bytes(24)) . '.png';
        $path = intakeEvidencePath($name);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5FoAAAAASUVORK5CYII='));
        $files[$kind] = ['name'=>$name] + intakeInspectImage($path);
    }
    intakeIssueTicket($pdo, $ticketId, $staffId, intakeDraftVersion($draft), intakeIssueValues([
        'location_id'=>(string) $location, 'terms_version'=>'Pilot', 'checklist_confirmed'=>'1',
        'signature_confirmed'=>'1', 'duplicate_confirmed'=>'1','charger'=>'1',
    ]), $files);
    consoleSave($pdo, $staffId, $id, consoleVersion(consoleDevice($pdo, $operator, $id)),
        ['action'=>'assign','technician'=>(string) $technician['user_id']]);
    pilotProgress($pdo, $technician, $id, 'DIAGNOSING');
    foreach (['draft'=>['labor_description'=>'Diagnosis','labor_amount'=>'350','part_id'=>(string) $part,'parts_amount'=>'2400'],
        'share'=>['confirm'=>'1'], 'decision'=>['decision'=>'APPROVED','customer_name'=>'Pilot customer',
            'reference'=>'At the counter','confirm'=>'1']] as $step=>$values) {
        quoteSave($pdo, $staffId, $id, quoteVersion(quoteLatest($pdo, $id)), $step, $values);
    }
    $itemId = stockRun($pdo, 'SELECT quote_item_id FROM repair_quote_items WHERE quote_id=? AND part_id=?',
        [quoteLatest($pdo,$id)['quote_id'],$part])->fetchColumn();
    $jobs[] = $operator + ['target_id'=>$id,'item_id'=>(string) $itemId,'ticket'=>$ticketId];
}

$results = raceCheckout('reserve', $jobs, 0, '');
$winner = array_search('ok', $results, true);
expectResult($winner !== false && $results[1-$winner] === 'Not enough unreserved stock at this branch.',
    'Expected exactly one successful reservation: ' . implode(' / ', $results));
$held = $jobs[$winner];
$waiting = $jobs[1-$winner];
$cashTicket = fn(array $job) => cashTicketVersion(cashPageTicket($pdo,$manager,$job['ticket']));
$cancelBill = cashIssueFinalBill($pdo,$manager['user_id'],$held['ticket'],$cashTicket($held),
    ['reason'=>'Customer approved at counter','confirm'=>'yes','amount'=>'350']);
expectResult(cashBillDetails($pdo,$cashier,$cancelBill)['total_amount'] === '350.00','Incorrect cancellation fee.');
stockChange($pdo,$manager['user_id'],$waiting['target_id'],(int) $waiting['item_id'],0,'reserve');
$id = $waiting['target_id'];
$firstHold = (int) stockRun($pdo,'SELECT reservation_id FROM stock_reservations WHERE quote_item_id=?',
    [$waiting['item_id']])->fetchColumn();
stockChange($pdo,$manager['user_id'],$id,(int) $waiting['item_id'],$firstHold,'release');
stockChange($pdo,$manager['user_id'],$id,(int) $waiting['item_id'],0,'reserve');
expectRefusal(fn() => stockChange($pdo,$manager['user_id'],$id,(int) $waiting['item_id'],$firstHold,'release'), 'Stock changed');
pilotProgress($pdo,$technician,$id,'IN_PROGRESS');
expectRefusal(fn() => pilotProgress($pdo,$technician,$id,'TESTING'), 'Record installation');
$holdId = (int) stockRun($pdo,'SELECT reservation_id FROM stock_reservations WHERE quote_item_id=?',[$waiting['item_id']])->fetchColumn();
$install = fn() => stockChange($pdo,$technician['user_id'],$id,(int) $waiting['item_id'],$holdId,'install');
$install();
expectRefusal($install,'Stock changed');
pilotProgress($pdo,$technician,$id,'TESTING');
pilotProgress($pdo,$technician,$id,'READY_FOR_PICKUP');
$bill = cashIssueFinalBill($pdo,$manager['user_id'],$waiting['ticket'],$cashTicket($waiting));
foreach ([$bill=>'2750.00',$cancelBill=>'350.00'] as $billId=>$due) {
    $record = cashBillDetails($pdo,$cashier,$billId);
    expectResult($record['total_amount'] === $due,'Incorrect bill.');
    cashRecordPayment($pdo,$cashier['user_id'],$billId,$record['version'],$due,bin2hex(random_bytes(32)));
    cashCompleteHandover($pdo,$cashier['user_id'],$billId,cashBillDetails($pdo,$cashier,$billId)['version'],
        'Pilot customer','CUSTOMER','yes');
    expectResult(cashBillDetails($pdo,$cashier,$billId)['case_state'] === 'CLOSED','Ticket still open.');
}
foreach ([$branch=>'0.000',$neighbor=>'4.000'] as $branchId=>$expected) {
    expectResult(stockRun($pdo,'SELECT quantity_on_hand FROM branch_inventory WHERE branch_id=? AND part_id=?',
        [$branchId,$part])->fetchColumn() === $expected,'Unexpected shelf count.');
}
echo "PASS: intake to handover for two jobs, last-SSD race, cancellation release, installation and exact bills.\n";