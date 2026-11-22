<?php
declare(strict_types=1);

// Loaded by the guarded disposable-database fixture in payment-pickup.php.
if (PHP_SAPI !== 'cli' || !isset($staff, $pdo)) {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/intake/issuance.php';
require_once __DIR__ . '/../app/console/transfers.php';
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
// A transfer must never leave an unused reservation stranded at its original branch.
$transitLocation = insertFixture($pdo,
    "INSERT INTO storage_locations (branch_id,location_code,location_name,location_type)
    VALUES (?,'PILOT-TRANSIT','Pilot transit','TRANSIT')", [$branch]);
$receivingLocation = insertFixture($pdo,
    "INSERT INTO storage_locations (branch_id,location_code,location_name,location_type)
    VALUES (?,'PILOT-RECV','Pilot receiving','BRANCH_BIN')", [$neighbor]);
$heldCode = stockRun($pdo, 'SELECT item_code FROM ticket_devices WHERE ticket_device_id = ?',
    [$held['target_id']])->fetchColumn();
expectRefusal(fn() => dispatchDevices($pdo, $manager['user_id'], $branch,
    $receivingLocation, (string) $heldCode), 'Release unused branch part reservations');
expectResult((int) stockRun($pdo, 'SELECT COUNT(*) FROM stock_reservations WHERE quote_item_id = ?',
    [$held['item_id']])->fetchColumn() === 1, 'Refused transfer removed the original reservation.');
// A released or never-reserved catalog item still requires installation before transfer.
$waitingCode = stockRun($pdo, 'SELECT item_code FROM ticket_devices WHERE ticket_device_id = ?',
    [$waiting['target_id']])->fetchColumn();
expectRefusal(fn() => dispatchDevices($pdo, $manager['user_id'], $branch,
    $receivingLocation, (string) $waitingCode), 'approved catalog part is not installed');
expectResult((int) stockRun($pdo, 'SELECT COUNT(*) FROM device_transfers WHERE from_branch_id = ?',
    [$branch])->fetchColumn() === 0, 'Refused stock transfer left a manifest behind.');
expectResult(stockRun($pdo, 'SELECT custody_state FROM ticket_devices WHERE ticket_device_id = ?',
    [$waiting['target_id']])->fetchColumn() === 'AT_LOCATION',
    'Refused stock transfer changed the device custody state.');

// Transfers without stock-tracked parts remain available, but receiving requires branch assignment.
$ordinaryCode = stockRun($pdo, 'SELECT item_code FROM ticket_devices WHERE ticket_device_id = ?',
    [$device])->fetchColumn();
$manifestCode = dispatchDevices($pdo, $manager['user_id'], $branch,
    $receivingLocation, (string) $ordinaryCode);
$manifestItem = (int) stockRun($pdo, 'SELECT x.device_transfer_item_id FROM device_transfer_items x
    JOIN device_transfers t ON t.device_transfer_id = x.device_transfer_id
    WHERE t.transfer_code = ?', [$manifestCode])->fetchColumn();
expectResult($manifestItem > 0, 'Ordinary device transfer did not create a manifest.');
expectRefusal(fn() => confirmTransferItem($pdo, $manager['user_id'], $manifestItem,
    'IN_TRANSIT', 'receive', 'Received at next branch'), 'assignment at the handling branch');
stockRun($pdo, 'INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?)',
    [$manager['user_id'], $neighbor]);
confirmTransferItem($pdo, $manager['user_id'], $manifestItem, 'IN_TRANSIT',
    'receive', 'Received at next branch');
$receivedDevice = stockRun($pdo, 'SELECT custody_state,physical_location_id FROM ticket_devices
    WHERE ticket_device_id = ?', [$device])->fetch();
expectResult($receivedDevice['custody_state'] === 'AT_LOCATION' &&
    (int) $receivedDevice['physical_location_id'] === $receivingLocation,
    'Ordinary device transfer was not received at the selected branch.');
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
// Return only a physically verified reusable part before testing; preserve all original movements.
$originalMovement = stockActiveInstallation($pdo, $id, (int) $waiting['item_id']);
expectResult($originalMovement > 0, 'The first installation was not recorded as active.');
expectRefusal(fn() => stockChange($pdo, $technician['user_id'], $id,
    (int) $waiting['item_id'], $originalMovement, 'return',
    'Inspected, clean, and reusable at the sending branch.', true), 'branch manager');
expectRefusal(fn() => stockChange($pdo, $manager['user_id'], $id,
    (int) $waiting['item_id'], $originalMovement, 'return',
    'Too short', true), 'reason of 10');
expectRefusal(fn() => stockChange($pdo, $manager['user_id'], $id,
    (int) $waiting['item_id'], $originalMovement, 'return',
    'Inspected, clean, and reusable at the sending branch.', false), 'physically removed');
stockChange($pdo, $manager['user_id'], $id, (int) $waiting['item_id'], $originalMovement,
    'return', 'Removed, inspected and confirmed serviceable before testing.', true);
expectResult(stockActiveInstallation($pdo, $id, (int) $waiting['item_id']) === 0,
    'Returned catalog part still treated as installed.');
expectResult(stockRun($pdo, 'SELECT quantity_on_hand FROM branch_inventory WHERE branch_id=? AND part_id=?',
    [$branch,$part])->fetchColumn() === '1.000', 'Usable part was not restocked.');
expectRefusal(fn() => stockChange($pdo, $manager['user_id'], $id,
    (int) $waiting['item_id'], $originalMovement, 'return',
    'Repeated attempt to restock the same part must be refused.', true), 'Stock changed');
expectRefusal(fn() => stockCheckInstalled($pdo, $id, (int) quoteLatest($pdo, $id)['quote_id']),
    'Record installation');
expectRefusal(fn() => dispatchDevices($pdo, $manager['user_id'], $branch,
    $receivingLocation, (string) $waitingCode), 'approved catalog part is not installed');
$reversals = stockRun($pdo, "SELECT inventory_transaction_id,quantity_delta,reason FROM inventory_transactions
    WHERE ticket_device_id=? AND transaction_type='RETURN' AND reference_code=?",
    [$id, 'RETURN-USE-' . $originalMovement])->fetchAll();
expectResult(count($reversals) === 1 && $reversals[0]['quantity_delta'] === '1.000' &&
    $reversals[0]['reason'] !== '', 'Return movement or audit reason missing.');
stockChange($pdo, $manager['user_id'], $id, (int) $waiting['item_id'], 0, 'reserve');
$replacementHold = (int) stockRun($pdo, 'SELECT reservation_id FROM stock_reservations WHERE quote_item_id=?',
    [$waiting['item_id']])->fetchColumn();
stockChange($pdo, $technician['user_id'], $id, (int) $waiting['item_id'], $replacementHold, 'install');
$replacementMovement = stockActiveInstallation($pdo, $id, (int) $waiting['item_id']);
expectResult($replacementMovement > $originalMovement,
    'New installation did not supersede the returned one.');
expectResult((int) stockRun($pdo, 'SELECT COUNT(*) FROM ticket_parts_used WHERE ticket_device_id=?',
    [$id])->fetchColumn() === 2, 'Installation history was lost after the return.');
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
echo "PASS: last-SSD reservation, reusable part return and reinstall, cancellation, transfer safety and exact bills.\n";