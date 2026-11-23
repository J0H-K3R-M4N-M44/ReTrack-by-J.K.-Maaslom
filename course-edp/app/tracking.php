<?php
declare(strict_types=1);

/**
 * Customer-safe, READ-ONLY portal queries for the independent academic schema.
 * This file deliberately never loads the staff session or exposes media paths.
 */

/** Check both credentials, with a dummy hash on absent/malformed codes. */
function edpTrackingAuthenticate(PDO $pdo, string $code, string $pin): ?array
{
    $code = strtoupper(trim($code));
    $formatOK = preg_match('/^[0-9A-F]{5}$/D', $code) === 1
        && preg_match('/^[0-9]{4}$/D', $pin) === 1;
    $row = null;
    if (preg_match('/^[0-9A-F]{5}$/D', $code) === 1) {
        $query = $pdo->prepare("SELECT ticket_id, portal_code, portal_pin_hash
            FROM repair_tickets WHERE portal_code = ?
              AND ticket_state IN ('OPEN','CLOSED','CANCELLED') LIMIT 1");
        $query->execute([$code]);
        $row = $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $validPin = password_verify($pin, $row['portal_pin_hash'] ?? $dummyHash);
    if (!$formatOK || !$row || !$validPin) {
        return null;
    }
    return [
        'ticket_id' => (int)$row['ticket_id'],
        'credential_fingerprint' => edpTrackingFingerprint($row),
    ];
}

function edpTrackingFingerprint(array $row): string
{
    return hash('sha256', (string)$row['portal_code'] . "\0" . (string)$row['portal_pin_hash']);
}

/** Invalidate an authenticated session if credentials or ticket eligibility change. */
function edpTrackingSessionValid(PDO $pdo, int $ticketId, string $fingerprint): bool
{
    if ($ticketId < 1 || !preg_match('/^[0-9a-f]{64}$/D', $fingerprint)) return false;
    $query = $pdo->prepare("SELECT portal_code, portal_pin_hash FROM repair_tickets
        WHERE ticket_id = ? AND ticket_state IN ('OPEN','CLOSED','CANCELLED') LIMIT 1");
    $query->execute([$ticketId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return is_array($row) && is_string($row['portal_code']) && is_string($row['portal_pin_hash'])
        && hash_equals($fingerprint, edpTrackingFingerprint($row));
}

/**
 * Return a strict allowlist of customer-visible information.
 * Staff notes, customer identity/contact, technician identity, serial numbers,
 * quote drafts, stored media paths and payment records are never selected.
 */
function edpTrackingView(PDO $pdo, int $ticketId): ?array
{
    $header = $pdo->prepare("SELECT t.ticket_id, t.ticket_code, t.ticket_state, t.issued_at,
             b.branch_name FROM repair_tickets t
             JOIN branches b ON b.branch_id = t.branch_id
             WHERE t.ticket_id = ? AND t.ticket_state IN ('OPEN','CLOSED','CANCELLED')
               AND t.portal_code IS NOT NULL LIMIT 1");
    $header->execute([$ticketId]);
    $ticket = $header->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) return null;

    $devices = $pdo->prepare("SELECT d.ticket_device_id, d.item_code, dt.label AS type_label,
            d.make_snapshot, d.model_snapshot, d.status_code, rs.label AS status_label,
            d.custody_state, d.repair_started_at
        FROM ticket_devices d
        JOIN repair_statuses rs ON rs.status_code = d.status_code
        LEFT JOIN device_types dt ON dt.device_type_code = d.device_type_code
        WHERE d.ticket_id = ? AND d.status_code <> 'DRAFT'
        ORDER BY d.sequence_no, d.ticket_device_id");
    $devices->execute([$ticketId]);
    $ticket['devices'] = [];

    $events = $pdo->prepare("SELECT e.to_status, rs.label AS status_label, e.public_summary, e.created_at
        FROM ticket_status_events e
        JOIN repair_statuses rs ON rs.status_code = e.to_status
        WHERE e.ticket_device_id = ? AND e.is_customer_visible = 1
        ORDER BY e.created_at, e.status_event_id");
    $quotes = $pdo->prepare('SELECT quote_status, total_amount, sent_at, expires_at
        FROM repair_quotes WHERE ticket_device_id = ? ORDER BY version_no DESC LIMIT 1');

    foreach ($devices->fetchAll(PDO::FETCH_ASSOC) as $device) {
        $id = (int)$device['ticket_device_id'];
        unset($device['ticket_device_id']); // not a customer credential or public identifier
        $events->execute([$id]);
        $device['timeline'] = $events->fetchAll(PDO::FETCH_ASSOC);
        $quotes->execute([$id]);
        $quote = $quotes->fetch(PDO::FETCH_ASSOC);
        // Only the newest quotation, after it is actually sent to the customer.
        $device['quote'] = $quote && $quote['sent_at'] !== null
            && in_array($quote['quote_status'], ['AWAITING_CUSTOMER','APPROVED','DECLINED'], true)
                ? $quote : null;
        $ticket['devices'][] = $device;
    }
    unset($ticket['ticket_id']);
    return $ticket;
}

/** Store UTC; display Manila local dates on the customer page. */
function edpTrackingManilaTime(?string $utc): string
{
    if ($utc === null || trim($utc) === '') return 'Not yet recorded';
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Manila'))
        ->format('M j, Y, g:i A');
}
