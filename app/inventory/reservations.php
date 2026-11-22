<?php
declare(strict_types=1);

// A hold reduces available stock. Only installation changes physical stock on hand.
function stockRun(PDO $pdo, string $sql, array $params): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function stockCatalog(PDO $pdo, int $branch): array
{
    return stockRun($pdo, 'SELECT p.part_id, p.sku, p.part_name FROM parts p
        JOIN branch_inventory i ON i.part_id = p.part_id WHERE i.branch_id = ? AND p.is_active = 1
        ORDER BY p.part_name, p.sku', [$branch])->fetchAll();
}

function stockQuoteInput(PDO $pdo, int $branch, array $input): array
{
    $part = requestText($input, 'part_id');
    if ($part === '') {
        return $input;
    }
    foreach (stockCatalog($pdo, $branch) as $row) {
        if ((string) $row['part_id'] === $part) {
            $input['parts_description'] = $row['part_name'] . ' (' . $row['sku'] . ')';
            return $input;
        }
    }
    throw new DomainException('Choose an active catalog part at the device branch.');
}

function stockReleaseQuote(PDO $pdo, int $quoteId): void
{
    stockRun($pdo, 'DELETE r FROM stock_reservations r JOIN repair_quote_items i
        ON i.quote_item_id = r.quote_item_id WHERE i.quote_id = ?', [$quoteId]);
}

function stockChange(PDO $pdo, int $actorId, int $deviceId, int $itemId, int $expectedHold, string $action): void
{
    $pdo->beginTransaction();
    try {
        $actor = stockRun($pdo, 'SELECT user_id, role, is_active FROM users WHERE user_id = ? FOR UPDATE',
            [$actorId])->fetch();
        if (!$actor || !$actor['is_active'] || !in_array($actor['role'], ['ADMIN','MANAGER','TECHNICIAN'], true)) {
            throw new DomainException('This account cannot change branch stock.');
        }
        if ($actor['role'] === 'TECHNICIAN' && $action !== 'install') {
            throw new DomainException('Ask a branch manager to reserve or release stock.');
        }
        $device = consoleDevice($pdo, $actor, $deviceId, true);
        $branch = (int) $device['accountable_branch_id'];
        $location = stockRun($pdo, 'SELECT branch_id FROM storage_locations WHERE location_id = ?',
            [$device['physical_location_id']])->fetchColumn();
        $quote = quoteLatest($pdo, $deviceId, true);
        if ($device['case_state'] !== 'OPEN' || in_array($device['repair_status'], ['CANCELLED','COMPLETED'], true)
            || $device['custody_state'] !== 'AT_LOCATION' || (int) $location !== $branch
            || !$quote || $quote['quote_status'] !== 'APPROVED') {
            throw new DomainException('Use a current approved estimate for a device held at this branch.');
        }
        if (stockRun($pdo, "SELECT bill_id FROM repair_bills WHERE ticket_id = ? AND bill_status = 'ISSUED' FOR UPDATE",
            [$device['ticket_id']])->fetchColumn()) {
            throw new DomainException('The final bill has already been issued.');
        }
        $item = stockRun($pdo, "SELECT * FROM repair_quote_items WHERE quote_item_id = ? AND quote_id = ?
            AND tracks_branch_stock = 1 AND item_type = 'PART' AND part_id IS NOT NULL FOR UPDATE", [$itemId, $quote['quote_id']])->fetch();
        if (!$item) {
            throw new DomainException('The quoted part changed. Reopen the estimate.');
        }
        $part = (int) $item['part_id'];
        $quantity = $item['quantity'];
        $onHand = stockRun($pdo, 'SELECT quantity_on_hand FROM branch_inventory
            WHERE branch_id = ? AND part_id = ? FOR UPDATE', [$branch, $part])->fetchColumn();
        if ($onHand === false) {
            throw new DomainException('No stock record exists at this branch.');
        }
        // Lock the shelf first. Two devices competing for the final unit see the same holds in order.
        $holds = stockRun($pdo, 'SELECT reservation_id, quote_item_id, quantity FROM stock_reservations
            WHERE branch_id = ? AND part_id = ? FOR UPDATE', [$branch, $part])->fetchAll();
        $holdId = 0;
        $available = (int) round((float) $onHand * 1000);
        foreach ($holds as $hold) {
            $available -= (int) round((float) $hold['quantity'] * 1000);
            if ((int) $hold['quote_item_id'] === $itemId) {
                $holdId = (int) $hold['reservation_id'];
            }
        }
        $reference = 'QUOTE-ITEM-' . $itemId;
        if ($holdId !== $expectedHold || stockRun($pdo, "SELECT 1 FROM inventory_transactions
            WHERE ticket_device_id = ? AND reference_code = ? AND transaction_type = 'TICKET_USE'",
            [$deviceId, $reference])->fetchColumn()) {
            throw new DomainException('Stock changed. Reopen the estimate before continuing.');
        }
        if ($action === 'reserve' && !$holdId) {
            if ($available < (int) round((float) $quantity * 1000)) {
                throw new DomainException('Not enough unreserved stock at this branch.');
            }
            stockRun($pdo, 'INSERT INTO stock_reservations (quote_item_id,branch_id,part_id,quantity,reserved_by)
                VALUES (?,?,?,?,?)', [$itemId,$branch,$part,$quantity,$actorId]);
        } elseif ($holdId && in_array($action, ['release','install'], true)) {
            if ($action === 'install') {
                if ($device['repair_status'] !== 'IN_PROGRESS') {
                    throw new DomainException('Start the repair before recording installation.');
                }
                stockRun($pdo, 'UPDATE branch_inventory SET quantity_on_hand = quantity_on_hand - ?
                    WHERE branch_id = ? AND part_id = ?', [$quantity,$branch,$part]);
                stockRun($pdo, "INSERT INTO inventory_transactions (branch_id,part_id,transaction_type,quantity_delta,
                    ticket_device_id,reference_code,performed_by_user_id,created_at)
                    VALUES (?,?,'TICKET_USE',-?,?,?, ?,UTC_TIMESTAMP())", [$branch,$part,$quantity,$deviceId,$reference,$actorId]);
                $movement = $pdo->lastInsertId();
                stockRun($pdo, 'INSERT INTO ticket_parts_used (ticket_device_id,part_id,inventory_transaction_id,
                    quantity_used,added_by_user_id) VALUES (?,?,?,?,?)', [$deviceId,$part,$movement,$quantity,$actorId]);
            }
            stockRun($pdo, 'DELETE FROM stock_reservations WHERE reservation_id = ?', [$holdId]);
        } else {
            throw new DomainException('Choose an available stock action.');
        }
        stockRun($pdo, "INSERT INTO audit_events (actor_type,actor_user_id,entity_type,entity_id,action,after_data,created_at)
            VALUES ('USER',?,'quote_item',?,?,?,UTC_TIMESTAMP())", [$actorId,$itemId,'STOCK_' . strtoupper($action),
                json_encode(['branch_id'=>$branch,'part_id'=>$part,'quantity'=>$quantity], JSON_THROW_ON_ERROR)]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

// Only catalog lines created through the pilot require an installation record.
function stockCheckInstalled(PDO $pdo, int $deviceId, int $quoteId): void
{
    $missing = stockRun($pdo, "SELECT 1 FROM repair_quote_items i
        WHERE i.quote_id = ? AND i.tracks_branch_stock = 1 AND NOT EXISTS (
            SELECT 1 FROM inventory_transactions m WHERE m.ticket_device_id = ?
            AND m.transaction_type = 'TICKET_USE' AND m.reference_code = CONCAT('QUOTE-ITEM-', i.quote_item_id)
        ) LIMIT 1", [$quoteId, $deviceId])->fetchColumn();
    if ($missing) {
        throw new DomainException('Record installation of the approved catalog part before testing or billing.');
    }
}