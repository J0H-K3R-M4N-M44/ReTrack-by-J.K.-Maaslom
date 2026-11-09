<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/issuance.php';
startAppSession();
$admin = requireAdmin();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");

try {
    $kind = requestText($_GET, 'kind');
    if (!in_array($kind, ['photo', 'signed_form'], true)) {
        throw new DomainException('Unknown image.');
    }
    $formKey = requestText($_GET, 'form');
    if ($formKey !== '') {
        $state = $_SESSION['issue_forms'][$formKey] ?? null;
        if (!$state || $state['expires'] < time()) {
            throw new DomainException('This review expired.');
        }
        intakeIssueDraft(getPDO(), $state['ticket_id'], (int) $admin['user_id']);
        $name = $state['evidence'][$kind]['name'] ?? '';
    } else {
        $id = requestText($_GET, 'ticket');
        if (!ctype_digit($id)) {
            throw new DomainException('Choose a ticket.');
        }
        $query = getPDO()->prepare("SELECT t.terms_signature_path,
            (SELECT p.stored_path FROM ticket_device_photos p JOIN ticket_devices d ON d.ticket_device_id = p.ticket_device_id
             WHERE d.ticket_id = t.ticket_id AND p.photo_category = 'OVERALL_CONDITION' ORDER BY p.photo_id LIMIT 1) AS photo_path
            FROM repair_tickets t JOIN user_branch_assignments a ON a.branch_id = t.accountable_branch_id AND a.user_id = ?
            JOIN branches b ON b.branch_id = a.branch_id AND b.is_active = 1
            WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED'");
        $query->execute([(int) $admin['user_id'], (int) $id]);
        $ticket = $query->fetch();
        if (!$ticket) {
            throw new DomainException('This ticket is unavailable.');
        }
        $name =
            (string) ($kind === 'photo' ? $ticket['photo_path'] : $ticket['terms_signature_path']);
    }
    $path = intakeEvidencePath($name);
    if (!is_file($path) || is_link($path)) {
        throw new DomainException('This image is unavailable.');
    }
    $image = intakeInspectImage($path);
    header('Content-Type: ' . $image['mime']);
    header('Content-Length: ' . $image['bytes']);
    header(
        'Content-Disposition: inline; filename="' .
            $kind .
            ($image['mime'] === 'image/png' ? '.png' : '.jpg') .
            '"',
    );
    session_write_close();
    readfile($path);
} catch (DomainException $error) {
    http_response_code(404);
    echo 'Image unavailable.';
} catch (Throwable $error) {
    error_log('RepairTrack evidence read: ' . $error->getMessage());
    http_response_code(503);
    echo 'Image temporarily unavailable.';
}
