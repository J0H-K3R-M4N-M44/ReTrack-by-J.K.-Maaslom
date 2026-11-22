<?php
declare(strict_types=1);

// CLI only. No non-test database can be inspected or mutated by this script.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../tools/migration_support.php';

function integrityAssert(bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException($reason);
}
function integrityGuard(PDO $pdo): void {
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    integrityAssert((bool)preg_match('/^repairtrack_test_[A-Za-z0-9_]+$/D', $db),
        'Refusing a non-disposable database.');
    integrityAssert((int)$pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1,
        'Foreign key checks are disabled.');
}
function integritySchema(PDO $pdo): array {
    $problems = [];
    $column = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices'
        AND COLUMN_NAME='repair_branch_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$column || !str_contains(strtolower((string)$column['COLUMN_TYPE']), 'bigint unsigned')
        || $column['IS_NULLABLE'] !== 'YES') $problems['repair_branch_column'] = 1;
    $index = $pdo->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
        FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='ticket_devices' AND INDEX_NAME='idx_td_repair_branch'")->fetchColumn();
    if ($index !== 'repair_branch_id,repair_status') $problems['repair_branch_index'] = 1;
    $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices'
        AND CONSTRAINT_NAME='fk_td_repair_branch' AND COLUMN_NAME='repair_branch_id'
        AND REFERENCED_TABLE_NAME='branches' AND REFERENCED_COLUMN_NAME='branch_id'")->fetchColumn();
    if ((int)$fk !== 1) $problems['repair_branch_fk'] = 1;
    return $problems;
}
function integrityRows(PDO $pdo): array {
    $checks = [
        'issued_missing_branch' => "SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t
            ON t.ticket_id=d.ticket_id WHERE t.record_stage='ISSUED' AND d.repair_branch_id IS NULL",
        'issued_mixed_branch' => "SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t
            ON t.ticket_id=d.ticket_id WHERE t.record_stage='ISSUED'
            AND d.repair_branch_id<>t.accountable_branch_id",
        'draft_has_branch' => "SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t
            ON t.ticket_id=d.ticket_id WHERE t.record_stage='DRAFT' AND d.repair_branch_id IS NOT NULL",
        'orphan_device_ticket' => "SELECT COUNT(*) FROM ticket_devices d LEFT JOIN repair_tickets t
            ON t.ticket_id=d.ticket_id WHERE t.ticket_id IS NULL",
        'orphan_repair_branch' => "SELECT COUNT(*) FROM ticket_devices d LEFT JOIN branches b
            ON b.branch_id=d.repair_branch_id WHERE d.repair_branch_id IS NOT NULL AND b.branch_id IS NULL",
        'orphan_quote_device' => "SELECT COUNT(*) FROM repair_quotes q LEFT JOIN ticket_devices d
            ON d.ticket_device_id=q.ticket_device_id WHERE d.ticket_device_id IS NULL",
        'orphan_bill_ticket' => "SELECT COUNT(*) FROM repair_bills b LEFT JOIN repair_tickets t
            ON t.ticket_id=b.ticket_id WHERE t.ticket_id IS NULL",
        'bill_item_wrong_ticket' => "SELECT COUNT(*) FROM repair_bill_items i
            JOIN repair_bills b ON b.bill_id=i.bill_id JOIN ticket_devices d
            ON d.ticket_device_id=i.ticket_device_id WHERE d.ticket_id<>b.ticket_id",
        'orphan_payment_bill' => "SELECT COUNT(*) FROM repair_bill_payments p
            LEFT JOIN repair_bills b ON b.bill_id=p.bill_id WHERE b.bill_id IS NULL",
        'invalid_cash_payment' => "SELECT COUNT(*) FROM repair_bill_payments WHERE amount<=0",
        'negative_stock' => "SELECT COUNT(*) FROM branch_inventory
            WHERE quantity_on_hand<0 OR reorder_level<0",
        'invalid_hold' => "SELECT COUNT(*) FROM stock_reservations r
            JOIN repair_quote_items i ON i.quote_item_id=r.quote_item_id
            WHERE r.quantity<=0 OR r.part_id<>i.part_id OR i.tracks_branch_stock<>1",
        'hold_missing_shelf' => "SELECT COUNT(*) FROM stock_reservations r LEFT JOIN branch_inventory s
            ON s.branch_id=r.branch_id AND s.part_id=r.part_id WHERE s.part_id IS NULL",
        'orphan_custody_device' => "SELECT COUNT(*) FROM device_custody_events e
            LEFT JOIN ticket_devices d ON d.ticket_device_id=e.ticket_device_id
            WHERE d.ticket_device_id IS NULL",
        'orphan_transfer_device' => "SELECT COUNT(*) FROM device_transfer_items i
            LEFT JOIN ticket_devices d ON d.ticket_device_id=i.ticket_device_id
            WHERE d.ticket_device_id IS NULL",
        'returned_still_located' => "SELECT COUNT(*) FROM ticket_devices
            WHERE custody_state='WITH_CUSTOMER' AND physical_location_id IS NOT NULL",
        'orphan_stocktake_line' => "SELECT COUNT(*) FROM inventory_stocktake_lines l
            LEFT JOIN inventory_stocktakes s ON s.stocktake_id=l.stocktake_id
            WHERE s.stocktake_id IS NULL",
    ];
    $issues = [];
    foreach ($checks as $label => $query) {
        $n = (int)$pdo->query($query)->fetchColumn();
        if ($n) $issues[$label] = $n;
    }
    return $issues;
}
function integritySnapshot(PDO $pdo): array {
    $tables = [
        'repair_tickets', 'ticket_devices', 'ticket_status_events', 'repair_quotes',
        'repair_quote_items', 'repair_quote_decisions', 'repair_bills', 'repair_bill_items',
        'repair_bill_payments', 'repair_bill_changes', 'branch_inventory',
        'stock_reservations', 'inventory_transactions', 'device_transfers',
        'device_transfer_items', 'device_custody_events', 'inventory_stocktakes',
        'inventory_stocktake_lines', 'audit_events',
    ];
    $columns = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
    $primary = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY'
        ORDER BY ORDINAL_POSITION");
    $output = [];
    $quote = static fn(string $part): string => chr(96) . str_replace(chr(96), chr(96).chr(96), $part) . chr(96);
    foreach ($tables as $table) {
        $columns->execute([$table]);
        $fields = $columns->fetchAll(PDO::FETCH_COLUMN);
        integrityAssert($fields !== [], 'Missing snapshot table: ' . $table);
        if ($table === 'ticket_devices') {
            // The additive column is removed and recreated during the upgrade rehearsal.
            $fields = array_values(array_diff($fields, ['repair_branch_id']));
        }
        $primary->execute([$table]);
        $pk = $primary->fetchAll(PDO::FETCH_COLUMN);
        integrityAssert($pk !== [], 'Missing primary key on ' . $table);
        $stmt = $pdo->query('SELECT '.implode(',', array_map($quote, $fields)).' FROM '.$quote($table)
            .' ORDER BY '.implode(',', array_map($quote, $pk)));
        $hash = hash_init('sha256');
        $count = 0;
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
            $count++;
        }
        $stmt->closeCursor();
        $output[$table] = ['rows'=>$count, 'sha256'=>hash_final($hash)];
    }
    return $output;
}
function integrityNegative(PDO $pdo): void {
    integrityAssert(integritySchema($pdo) === [] && integrityRows($pdo) === [],
        'Fix existing integrity violations before negative tests.');
    $sample = $pdo->query("SELECT d.ticket_device_id, t.accountable_branch_id
        FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.record_stage='ISSUED' ORDER BY d.ticket_device_id LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    integrityAssert((bool)$sample, 'At least one issued device is needed.');
    $id = (int)$sample['ticket_device_id'];
    $original = (int)$sample['accountable_branch_id'];
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE ticket_devices SET repair_branch_id=? WHERE ticket_device_id=?');
        $update->execute([null, $id]);
        integrityAssert((integrityRows($pdo)['issued_missing_branch'] ?? 0) === 1,
            'Missing issued branch was not detected.');
        $update->execute([$original, $id]);
        $create = $pdo->prepare('INSERT INTO branches(branch_code,branch_name) VALUES (?,?)');
        $create->execute(['CHK'.strtoupper(bin2hex(random_bytes(7))), 'Temporary integrity branch']);
        $different = (int)$pdo->lastInsertId();
        $update->execute([$different, $id]);
        integrityAssert((integrityRows($pdo)['issued_mixed_branch'] ?? 0) === 1,
            'Mixed branch assignment was not detected.');
        $update->execute([$original, $id]);
        try {
            $update->execute([PHP_INT_MAX, $id]);
            throw new RuntimeException('Foreign key accepted an unknown branch.');
        } catch (PDOException $e) {
            integrityAssert($e->getCode() === '23000', 'Unexpected SQL failure instead of FK rejection.');
        }
        integrityAssert(integrityRows($pdo) === [], 'Negative fixture left database inconsistencies.');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    integrityAssert(integrityRows($pdo) === [], 'Negative fixture escaped its rollback.');
}
try {
    $mode = $argv[1] ?? '--check';
    integrityAssert(count($argv) <= 2 && in_array($mode,
        ['--check', '--snapshot', '--negative'], true), 'Use --check, --snapshot or --negative.');
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    integrityGuard($pdo);
    if ($mode === '--snapshot') {
        echo json_encode(integritySnapshot($pdo), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    integrityAssert((int)$pdo->query("SELECT COUNT(*) FROM repair_tickets
        WHERE record_stage='ISSUED'")->fetchColumn() > 0,
        'Test database has no issued devices (vacuous run).');
    $errors = integritySchema($pdo) + integrityRows($pdo);
    integrityAssert($errors === [], 'Violations: '.json_encode($errors, JSON_THROW_ON_ERROR));
    integrityAssert(!in_array('device_repair_branches.sql', migrationPending($pdo), true),
        'Device repair-branch migration pending.');
    if ($mode === '--negative') {
        integrityAssert(getenv('REPAIRTRACK_INTEGRITY_TEST') === '1',
            'Negative tests require explicit REPAIRTRACK_INTEGRITY_TEST=1 opt-in.');
        integrityNegative($pdo);
        echo "PASS: negative ownership/FK checks and complete transaction rollback.\n";
    } else {
        echo "PASS: schema metadata, 17 relational/stock/billing/custody checks, and issued owners.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: MySQL integrity round: '.$error->getMessage()."\n");
    exit(1);
}
