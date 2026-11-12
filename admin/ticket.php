<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
startAppSession();
$admin = requireRoles(['ADMIN', 'MANAGER', 'INTAKE_AGENT']);
header('Cache-Control: no-store, private');
$id = requestText($_GET, 'id');
if (!ctype_digit($id)) {
    unavailablePage('Choose a ticket from the workbench.', 400);
}
try {
    $query = getPDO()
        ->prepare("SELECT t.ticket_id, t.ticket_code, t.intake_date, t.terms_version, t.issued_at, c.full_name,
        b.branch_name, d.item_code, d.device_type_snapshot, d.make_snapshot, d.model_snapshot, d.serial_number_snapshot,
        d.alternative_identifiers, d.reported_symptoms, d.condition_notes, d.repair_status, l.location_name
        FROM repair_tickets t JOIN customers c ON c.customer_id = t.customer_id
        JOIN branches b ON b.branch_id = t.accountable_branch_id AND b.is_active = 1
        JOIN user_branch_assignments a ON a.branch_id = b.branch_id AND a.user_id = ?
        JOIN ticket_devices d ON d.ticket_id = t.ticket_id
        LEFT JOIN storage_locations l ON l.location_id = d.physical_location_id
        WHERE t.ticket_id = ? AND t.record_stage = 'ISSUED' ORDER BY d.child_sequence");
    $query->execute([(int) $admin['user_id'], (int) $id]);
    $devices = $query->fetchAll();
    if (!$devices) {
        unavailablePage('This ticket is unavailable or belongs to an unassigned branch.', 404);
    }
    $ticket = $devices[0];
    $accessoryQuery = getPDO()->prepare(
        'SELECT a.accessory_name, a.received, a.notes, d.item_code FROM ticket_accessory_items a JOIN ticket_devices d ON d.ticket_device_id = a.ticket_device_id WHERE d.ticket_id = ? ORDER BY d.child_sequence, a.accessory_item_id',
    );
    $accessoryQuery->execute([(int) $id]);
    $accessories = $accessoryQuery->fetchAll();
} catch (Throwable $error) {
    error_log('RepairTrack ticket: ' . $error->getMessage());
    unavailablePage('The ticket could not be loaded. Please try again later.');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h(
    $ticket['ticket_code'],
) ?> | RepairTrack</title><link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/issuance.css"></head>
<body class="dashboard-body"><main class="issuance-page">
<header class="issuance-heading"><div><div class="eyebrow">Issued repair intake</div><h1><?= h(
    $ticket['ticket_code'],
) ?></h1><p><?= h($ticket['full_name']) ?> · <?= h($ticket['branch_name']) ?> · <?= h(
     $ticket['intake_date'],
 ) ?></p></div><a class="secondary-btn no-print" href="<?= h(
    workbenchHome(),
) ?>">Back to workbench</a></header>
<p>This reference identifies the repair intake. It is not a payment receipt. Use your browser's Print command for a copy. Public online tracking is not available in this version.</p>
<?php foreach ($devices as $device): ?><section class="dashboard-card ticket-receipt"><h2><?= h(
    $device['item_code'],
) ?></h2><dl class="receipt-details">
<?php foreach (
    [
        'device_type_snapshot' => 'Device type',
        'make_snapshot' => 'Brand',
        'model_snapshot' => 'Model',
        'serial_number_snapshot' => 'Serial number',
        'alternative_identifiers' => 'Other identifiers',
        'reported_symptoms' => 'Reported problem',
        'condition_notes' => 'Condition at intake',
        'repair_status' => 'Current status',
        'location_name' => 'Current storage location',
    ]
    as $key => $label
): ?><div><dt><?= h($label) ?></dt><dd><?= h(
    $device[$key] ?: 'Not recorded',
) ?></dd></div><?php endforeach; ?>
</dl></section><?php endforeach; ?>
<section class="dashboard-card ticket-receipt"><h2>Accessories at intake</h2><ul><?php foreach (
    $accessories
    as $accessory
): ?><li><?= h($accessory['item_code'] . ' · ' . $accessory['accessory_name']) ?> — <?= ($accessory[
     'received'
 ]
     ? 'Received'
     : 'Not received') .
     ($accessory['notes']
         ? ': ' . h($accessory['notes'])
         : '') ?></li><?php endforeach; ?></ul><p>Signed terms version: <?= h(
    $ticket['terms_version'],
) ?></p><div class="issuance-actions no-print"><a class="secondary-btn" href="intake_evidence.php?ticket=<?= (int) $id ?>&amp;kind=photo">View condition photo</a><a class="secondary-btn" href="intake_evidence.php?ticket=<?= (int) $id ?>&amp;kind=signed_form">View signed intake form</a></div></section>
</main></body></html>

