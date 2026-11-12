<?php
declare(strict_types=1);
require_once __DIR__ . '/../dashboard_data.php';
require_once __DIR__ . '/../intake/repository.php';

// Ticket scope is shared by lists, details and writes.
function consoleScope(array $user): array
{
    $sql = "t.record_stage = 'ISSUED' AND EXISTS (
        SELECT 1 FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id
        WHERE a.user_id = ? AND b.is_active = 1 AND a.branch_id = t.accountable_branch_id)";
    $params = [(int) $user['user_id']];
    if ($user['role'] === 'TECHNICIAN') {
        $sql .= ' AND d.assigned_technician_id = ?';
        $params[] = (int) $user['user_id'];
    }
    return [$sql, $params];
}

function consoleDevices(PDO $pdo, array $user, array $input): array
{
    [$scope, $params] = consoleScope($user);
    $q = likePattern(mb_substr(trim(requestText($input, 'q')), 0, 100));
    $sql = "SELECT d.ticket_device_id, d.item_code, d.device_type_snapshot, d.make_snapshot,
        d.model_snapshot, d.repair_status, d.updated_at, d.custody_state,
        t.ticket_code, b.branch_name, u.full_name AS technician
        FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id
        LEFT JOIN users u ON u.user_id = d.assigned_technician_id
        WHERE $scope AND (d.item_code LIKE ? ESCAPE '!' OR t.ticket_code LIKE ? ESCAPE '!'
        OR d.model_snapshot LIKE ? ESCAPE '!')";
    $page = dashboardPage(
        $pdo,
        $sql,
        [...$params, $q, $q, $q],
        'updated_at DESC, ticket_device_id DESC',
        requestText($input, 'page', '1'),
    );
    $count = $pdo->prepare("SELECT d.repair_status, COUNT(*) AS total FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id = d.ticket_id WHERE $scope GROUP BY d.repair_status");
    $count->execute($params);
    return ['page' => $page, 'counts' => $count->fetchAll()];
}

function consoleDevice(PDO $pdo, array $user, int $id, bool $lock = false): array
{
    if (
        !in_array($user['role'], ['ADMIN', 'MANAGER', 'TECHNICIAN', 'INTAKE_AGENT', 'VIEWER'], true)
    ) {
        throw new DomainException('This role cannot open repair details.');
    }
    [$scope, $params] = consoleScope($user);
    $query = $pdo->prepare(
        "SELECT d.*, t.accountable_branch_id, t.ticket_code, t.case_state,
        b.branch_name, l.location_name,
        (SELECT MAX(e.status_event_id) FROM ticket_status_events e WHERE e.ticket_device_id = d.ticket_device_id) AS latest_event
        FROM ticket_devices d
        JOIN repair_tickets t ON t.ticket_id = d.ticket_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        WHERE $scope AND d.ticket_device_id = ?" . ($lock ? ' FOR UPDATE' : ''),
    );
    $query->execute([...$params, $id]);
    $device = $query->fetch();
    if (!$device) {
        throw new DomainException('Device unavailable or outside your assigned work.');
    }
    return $device;
}

function consoleVersion(array $device): string
{
    return hash('sha256', json_encode($device, JSON_THROW_ON_ERROR));
}

function consoleTechnicians(PDO $pdo, int $branchId): array
{
    $query = $pdo->prepare("SELECT u.user_id, u.full_name FROM users u
        JOIN user_branch_assignments a ON a.user_id = u.user_id
        WHERE a.branch_id = ? AND u.role = 'TECHNICIAN' AND u.is_active = 1 ORDER BY u.full_name");
    $query->execute([$branchId]);
    return $query->fetchAll();
}

// Completion/cancellation need handover and billing rules, so they are not shortcuts here.
function consoleNextStatuses(string $current): array
{
    return match ($current) {
        'RECEIVED' => ['DIAGNOSING'],
        'DIAGNOSING' => ['AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'IN_PROGRESS'],
        'AWAITING_PARTS' => ['IN_PROGRESS'],
        'AWAITING_CUSTOMER_APPROVAL' => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['AWAITING_PARTS', 'TESTING'],
        'TESTING' => ['IN_PROGRESS', 'READY_FOR_PICKUP'],
        default => [],
    };
}

function consoleSave(PDO $pdo, int $actorId, int $id, string $version, array $input): void
{
    $pdo->beginTransaction();
    try {
        // Read a fresh account under a lock; never trust a posted role.
        $query = $pdo->prepare(
            'SELECT user_id, role, is_active FROM users WHERE user_id = ? FOR UPDATE',
        );
        $query->execute([$actorId]);
        $user = $query->fetch();
        if (!$user || !$user['is_active']) {
            throw new DomainException('Your account cannot make this change.');
        }
        $device = consoleDevice($pdo, $user, $id, true);
        if (!hash_equals(consoleVersion($device), $version)) {
            throw new DomainException('This device changed. Reload before saving.');
        }
        if (
            $device['case_state'] !== 'OPEN' ||
            in_array($device['repair_status'], ['COMPLETED', 'CANCELLED'], true)
        ) {
            throw new DomainException('This repair is closed.');
        }
        $action = requestText($input, 'action');
        if ($action === 'assign' && in_array($user['role'], ['ADMIN', 'MANAGER'], true)) {
            $raw = requestText($input, 'technician');
            if (!ctype_digit($raw)) {
                throw new DomainException('Choose a technician.');
            }
            $tech = $pdo->prepare("SELECT u.user_id FROM users u JOIN user_branch_assignments a ON a.user_id = u.user_id
                WHERE u.user_id = ? AND u.is_active = 1 AND u.role = 'TECHNICIAN' AND a.branch_id = ? FOR UPDATE");
            $tech->execute([(int) $raw, $device['accountable_branch_id']]);
            if (!$tech->fetchColumn()) {
                throw new DomainException('Choose an active technician assigned to this branch.');
            }
            $pdo->prepare(
                'UPDATE ticket_devices SET assigned_technician_id = ? WHERE ticket_device_id = ?',
            )->execute([(int) $raw, $id]);
            $details = ['assigned_technician_id' => (int) $raw];
        } elseif ($action === 'progress' && $user['role'] === 'TECHNICIAN') {
            $status = requestText($input, 'status');
            $note = trim(requestText($input, 'note'));
            if (mb_strlen($note) > 2000 || ($status === $device['repair_status'] && $note === '')) {
                throw new DomainException(
                    'Add a note of up to 2000 characters, or choose the next repair stage.',
                );
            }
            if (
                $status !== $device['repair_status'] &&
                !in_array($status, consoleNextStatuses($device['repair_status']), true)
            ) {
                throw new DomainException('Choose an allowed next repair stage.');
            }
            if (
                $status !== $device['repair_status'] &&
                $device['custody_state'] !== 'AT_LOCATION'
            ) {
                throw new DomainException(
                    'Repair stages cannot change while the device is away or in transit.',
                );
            }
            // This conservative gate avoids treating a technician click as customer consent.
            if ($status === 'IN_PROGRESS' && $device['repair_status'] !== 'TESTING') {
                $approval = $pdo->prepare(
                    'SELECT quote_status FROM repair_quotes WHERE ticket_device_id = ? ORDER BY version_no DESC LIMIT 1 FOR UPDATE',
                );
                $approval->execute([$id]);
                if ($approval->fetchColumn() !== 'APPROVED') {
                    throw new DomainException(
                        'An approved quote must be recorded before repair starts.',
                    );
                }
            }
            $pdo->prepare(
                'UPDATE ticket_devices SET repair_status = ? WHERE ticket_device_id = ?',
            )->execute([$status, $id]);
            $pdo->prepare(
                "INSERT INTO ticket_status_events
                (ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, is_customer_visible, created_at)
                VALUES (?, ?, ?, 'USER', ?, ?, 0, UTC_TIMESTAMP())",
            )->execute([$id, $device['repair_status'], $status, $actorId, $note ?: null]);
            $details = ['repair_status' => $status];
        } else {
            throw new DomainException('Your role cannot perform this action.');
        }
        $pdo->prepare(
            "INSERT INTO audit_events
            (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at)
            VALUES ('USER', ?, 'ticket_device', ?, ?, ?, UTC_TIMESTAMP())",
        )->execute([
            $actorId,
            $id,
            $action === 'assign' ? 'TECHNICIAN_ASSIGNED' : 'TECHNICIAN_PROGRESS',
            json_encode($details, JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function consoleTransfers(PDO $pdo, array $user, array $input): array
{
    $sql = "SELECT x.device_transfer_item_id, x.item_status, x.sent_condition_note,
        x.receipt_condition_note, t.transfer_code, t.dispatched_at,
        f.branch_name AS origin, b.branch_name AS destination, d.item_code, l.location_name AS destination_location
        FROM device_transfer_items x JOIN device_transfers t ON t.device_transfer_id = x.device_transfer_id
        JOIN branches f ON f.branch_id = t.from_branch_id JOIN branches b ON b.branch_id = t.to_branch_id
        JOIN ticket_devices d ON d.ticket_device_id = x.ticket_device_id
        JOIN storage_locations l ON l.location_id = x.to_location_id
        WHERE EXISTS (SELECT 1 FROM user_branch_assignments a JOIN branches active ON active.branch_id = a.branch_id
            WHERE a.user_id = ? AND active.is_active = 1 AND a.branch_id IN (t.from_branch_id, t.to_branch_id))
        AND t.transfer_code LIKE ? ESCAPE '!'";
    return dashboardPage(
        $pdo,
        $sql,
        [(int) $user['user_id'], likePattern(requestText($input, 'q'))],
        'dispatched_at DESC, device_transfer_item_id DESC',
        requestText($input, 'page', '1'),
    );
}

function consoleDrafts(PDO $pdo, int $userId, array $input): array
{
    return dashboardPage(
        $pdo,
        "SELECT t.ticket_id, t.created_at, c.full_name AS customer_name, b.branch_name
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.intake_branch_id
        LEFT JOIN customers c ON c.customer_id = t.customer_id
        WHERE t.record_stage = 'DRAFT' AND b.is_active = 1
        AND EXISTS (SELECT 1 FROM user_branch_assignments a WHERE a.user_id = ? AND a.branch_id = b.branch_id)",
        [$userId],
        'created_at DESC, ticket_id DESC',
        requestText($input, 'draft_page', '1'),
    );
}
