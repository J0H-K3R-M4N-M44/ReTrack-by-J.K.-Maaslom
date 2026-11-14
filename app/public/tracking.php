<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../web.php';
require_once __DIR__ . '/../dashboard_data.php';

// Receipt numbers identify a repair; only a private random code grants access.
function publicRepair(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return null;
    }
    $query = $pdo->prepare("SELECT t.ticket_id, t.ticket_code, b.branch_name, b.phone
        FROM repair_tickets t JOIN branches b ON b.branch_id = t.accountable_branch_id
        WHERE t.tracking_token_hash = ? AND t.record_stage = 'ISSUED' LIMIT 1");
    $query->execute([hash('sha256', $token)]);
    $repair = $query->fetch();
    if (!$repair) {
        return null;
    }
    // List safe columns explicitly. Never return contacts, serials, evidence or private notes.
    $devices = $pdo->prepare("SELECT d.ticket_device_id, d.item_code,
        d.device_type_snapshot, d.make_snapshot, d.model_snapshot, d.repair_status,
        d.custody_state, d.public_summary, b.branch_name AS location_branch
        FROM ticket_devices d
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        LEFT JOIN branches b ON b.branch_id = l.branch_id
        WHERE d.ticket_id = ? ORDER BY d.child_sequence");
    $devices->execute([$repair['ticket_id']]);
    $repair['devices'] = $devices->fetchAll();
    $events = $pdo->prepare("SELECT e.to_status, e.public_summary, e.created_at, d.item_code
        FROM ticket_status_events e JOIN ticket_devices d ON d.ticket_device_id = e.ticket_device_id
        WHERE d.ticket_id = ? AND e.is_customer_visible = 1
        ORDER BY e.created_at DESC, e.status_event_id DESC LIMIT 51");
    $events->execute([$repair['ticket_id']]);
    $repair['events'] = $events->fetchAll();
    $repair['has_more_events'] = count($repair['events']) > 50;
    $repair['events'] = array_slice($repair['events'], 0, 50);
    return $repair;
}

function publicCustody(array $device): string
{
    $branch = $device['location_branch'] ?: 'the service branch';
    return match ($device['custody_state']) {
        'AT_LOCATION' => 'At ' . $branch,
        'IN_TRANSIT' => 'In transit · contact the branch for the next handoff',
        'WITH_CUSTOMER' => 'Returned to the customer',
        default => 'Location awaiting staff confirmation',
    };
}

// Recheck account, branch and version inside the transaction before replacing access.
function replaceTrackingCode(PDO $pdo, int $userId, int $ticketId, string $expectedHash): string
{
    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare('SELECT role, is_active FROM users WHERE user_id = ? FOR UPDATE');
        $account->execute([$userId]);
        $user = $account->fetch();
        if (
            !$user ||
            !$user['is_active'] ||
            !in_array($user['role'], ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)
        ) {
            throw new DomainException('Your account cannot share this repair.');
        }
        $query = $pdo->prepare("SELECT t.tracking_token_hash FROM repair_tickets t
            JOIN branches b ON b.branch_id = t.accountable_branch_id AND b.is_active = 1
            JOIN user_branch_assignments a ON a.branch_id = b.branch_id AND a.user_id = ?
            WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED' FOR UPDATE");
        $query->execute([$userId, $ticketId]);
        $ticket = $query->fetch();
        if (!$ticket || !hash_equals((string) $ticket['tracking_token_hash'], $expectedHash)) {
            throw new DomainException(
                'This ticket or tracking access changed. Reopen the receipt.',
            );
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            'UPDATE repair_tickets SET tracking_token_hash = ?, tracking_token_ciphertext = NULL WHERE ticket_id = ?',
        )->execute([hash('sha256', $token), $ticketId]);
        // Record the action, never the usable code.
        $pdo->prepare(
            "INSERT INTO audit_events (actor_type, actor_user_id, entity_type, entity_id, action, created_at)
            VALUES ('USER', ?, 'repair_ticket', ?, 'TRACKING_ACCESS_REPLACED', UTC_TIMESTAMP())",
        )->execute([$userId, $ticketId]);
        $pdo->commit();
        return $token;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
