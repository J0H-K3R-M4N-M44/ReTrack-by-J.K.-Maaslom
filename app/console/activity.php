<?php
declare(strict_types=1);

require_once __DIR__ . '/repository.php';

function activityGroups(string $role): array
{
    return match ($role) {
        'ADMIN', 'MANAGER' => ['repairs', 'payments', 'pickups'],
        'INTAKE_AGENT' => ['repairs', 'pickups'],
        'TECHNICIAN' => ['repairs'],
        'CASHIER' => ['payments', 'pickups'],
        default => [],
    };
}

// Both the read action and the list use this query so their access rules stay together.
function activitySource(array $user, string $group): array
{
    if (!in_array($group, activityGroups($user['role']), true)) {
        throw new DomainException('Activity category unavailable.');
    }
    [$scope, $params] = consoleScope($user);
    $devices = ' FROM repair_tickets t JOIN ticket_devices d ON d.ticket_id = t.ticket_id ';
    if ($group === 'repairs') {
        $sql = "SELECT e.status_event_id AS event_id, e.created_at AS event_at, t.ticket_code,
            d.item_code AS reference, e.to_status AS detail $devices
            JOIN ticket_status_events e ON e.ticket_device_id = d.ticket_device_id
            WHERE $scope AND (e.from_status IS NULL OR e.from_status <> e.to_status)";
        if ($user['role'] !== 'ADMIN') {
            $sql .= " AND e.actor_type <> 'CUSTOMER'";
        }
    } elseif ($group === 'pickups') {
        $sql = "SELECT d.ticket_device_id AS event_id, d.return_handover_at AS event_at,
            t.ticket_code, d.item_code AS reference, 'Handed over' AS detail $devices
            WHERE $scope AND d.return_handover_at IS NOT NULL";
    } else {
        // Payments belong to bills. Joining devices here would repeat a multi-device bill.
        $sql = "SELECT p.payment_id AS event_id, p.received_at AS event_at, t.ticket_code,
            b.bill_number AS reference, CONCAT(b.currency, ' ', p.amount) AS detail
            FROM repair_tickets t JOIN repair_bills b ON b.ticket_id = t.ticket_id
            JOIN repair_bill_payments p ON p.bill_id = b.bill_id WHERE $scope";
    }
    return [$sql, $params];
}

// Count only the events this person can open, using their own read receipts.
function activityUnreadCounts(PDO $pdo, array $user): array
{
    $queries = [];
    $values = [];
    foreach (activityGroups($user['role']) as $group) {
        [$source, $params] = activitySource($user, $group);
        $queries[] = "SELECT ? AS category, COUNT(*) AS unread FROM ($source) s
            WHERE NOT EXISTS (SELECT 1 FROM staff_activity_reads r
                WHERE r.event_id = s.event_id AND r.event_group = ? AND r.user_id = ?)";
        array_push($values, $group, ...$params);
        $values[] = $group;
        $values[] = $user['user_id'];
    }
    if (!$queries) {
        return [];
    }
    $statement = $pdo->prepare(implode(' UNION ALL ', $queries));
    $statement->execute($values);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

function activityMarkRead(PDO $pdo, array $user, string $group, string $id): void
{
    [$source, $params] = activitySource($user, $group);
    $mark = $pdo->prepare("INSERT INTO staff_activity_reads (user_id, event_group, event_id)
        SELECT ?, ?, s.event_id FROM ($source) s WHERE s.event_id = ?
        ON DUPLICATE KEY UPDATE event_id = s.event_id");
    $mark->execute([$user['user_id'], $group, ...$params, $id]);
}
