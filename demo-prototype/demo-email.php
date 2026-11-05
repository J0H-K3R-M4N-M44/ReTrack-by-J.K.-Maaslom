<?php
// Optional local presentation relay. The recipient is set on the server.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || !preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/D', $host)
    || !in_array($origin, ['http://' . $host, 'https://' . $host], true)
    || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}
$recipient = getenv('REPAIRTRACK_DEMO_EMAIL');
if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    http_response_code(503);
    exit;
}
$data = json_decode(file_get_contents('php://input', false, null, 0, 1024), true);
$ticket = $data['ticket'] ?? '';
$summary = $data['summary'] ?? '';
$updates = ['Your quotation is ready for review.'];
foreach (['Diagnosing','Awaiting approval','Awaiting parts','Repairing','Testing','Ready for pickup'] as $stage) {
    $updates[] = 'Repair stage updated to ' . $stage . '.';
}
if (!is_string($ticket) || !preg_match('/^RT-\d+$/D', $ticket) || !in_array($summary, $updates, true)) {
    http_response_code(422);
    exit;
}
$accepted = mail($recipient, 'RepairTrack demo update for ' . $ticket, $summary);
http_response_code($accepted ? 204 : 503);
