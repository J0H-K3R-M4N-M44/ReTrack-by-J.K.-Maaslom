<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Issue intake draft | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/issuance.css">
</head>
<body class="dashboard-body">
<main class="issuance-page">
    <header class="issuance-heading">
        <div><div class="eyebrow">Staff · Intake</div><h1><?= $review
            ? 'Confirm ticket issuance'
            : 'Complete intake checks' ?></h1><p>Draft #<?= $ticketId ?> · <?= h(
     $draft['customer_name'],
 ) ?></p></div>
        <a class="secondary-btn" href="intake_create.php?id=<?= $ticketId ?>">Back to draft</a>
    </header>
    <?php if ($errors): ?><div class="intake-errors" role="alert"><?php foreach (
    $errors
    as $error
): ?><p><?= h($error) ?></p><?php endforeach; ?></div><?php endif; ?>
    <div class="issuance-grid">
        <section class="dashboard-card">
            <h2>Saved intake details</h2>
            <dl class="receipt-details">
                <div><dt>Customer</dt><dd><?= h($draft['customer_name']) ?></dd></div>
                <div><dt>Device</dt><dd><?= h(
                    trim($draft['device_type'] . ' · ' . $draft['make'] . ' ' . $draft['model']),
                ) ?></dd></div>
                <div><dt>Serial number</dt><dd><?= h(
                    $draft['serial_number'] ?: 'Not available',
                ) ?></dd></div>
                <div><dt>Reported problem</dt><dd><?= h($draft['reported_symptoms']) ?></dd></div>
                <div><dt>Visible condition</dt><dd><?= h($draft['condition_notes']) ?></dd></div>
            </dl>
            <p>Issuing changes this draft to <strong>Received</strong> and creates its ticket reference. These intake details and evidence cannot be edited through the draft screen afterward.</p>
        </section>
        <section class="dashboard-card">
            <?php if ($review): ?>
                <h2>Check before issuing</h2>
                <dl class="receipt-details">
                    <div><dt>Storage location</dt><dd><?php foreach ($locations as $location) {
                        if ((string) $location['location_id'] === $values['location_id']) {
                            echo h($location['location_name']);
                        }
                    } ?></dd></div>
                    <div><dt>Other device identifiers</dt><dd><?= h(
                        $values['alternative_identifiers'] ?: 'Serial number recorded',
                    ) ?></dd></div>
                    <div><dt>Accessories received</dt><dd><?php
                    $received = [];
                    foreach (
                        [
                            'charger' => 'Charger / adapter',
                            'bag' => 'Bag / case',
                            'cable' => 'Other cable',
                        ]
                        as $key => $label
                    ) {
                        if ($values[$key] === '1') {
                            $received[] = $label;
                        }
                    }
                    if ($values['other_accessories'] !== '') {
                        $received[] = $values['other_accessories'];
                    }
                    echo h($received ? implode(', ', $received) : 'None');
                    ?></dd></div>
                    <div><dt>Signed terms version</dt><dd><?= h(
                        $values['terms_version'],
                    ) ?></dd></div>
                </dl>
                <?php foreach (array_slice($draft['devices'], 1) as $index => $device): ?>
                    <?php $details = $values['extra_devices'][$index]; ?>
                    <h3>Device <?= $index + 2 ?> · <?= h(trim($device['make'] . ' ' . $device['model'])) ?></h3>
                    <p><?= h($device['reported_symptoms']) ?> · <?= h($device['condition_notes']) ?></p>
                    <p>Storage: <?= h(array_column($locations, 'location_name', 'location_id')[$details['location_id']] ?? '') ?></p>
                    <p>Identifiers: <?= h($details['alternative_identifiers'] ?: ($device['serial_number'] ?: 'None')) ?></p>
                    <p>Accessories: <?= h(implode(', ', array_filter([
                        $details['charger'] === '1' ? 'Charger' : '',
                        $details['bag'] === '1' ? 'Bag' : '',
                        $details['cable'] === '1' ? 'Cable' : '',
                        $details['other_accessories'],
                    ])) ?: 'None') ?></p>
                    <figure class="evidence-previews">
                        <img src="intake_evidence.php?form=<?= h($formKey) ?>&amp;kind=photo_<?= $index + 2 ?>" alt="Condition photo for device <?= $index + 2 ?>">
                    </figure>
                <?php endforeach; ?>
                <div class="evidence-previews">
                    <figure><img src="intake_evidence.php?form=<?= h(
                        $formKey,
                    ) ?>&amp;kind=photo" alt="Uploaded overall condition"><figcaption>Overall condition</figcaption></figure>
                    <figure><img src="intake_evidence.php?form=<?= h(
                        $formKey,
                    ) ?>&amp;kind=signed_form" alt="Uploaded customer-signed intake form"><figcaption>Signed intake form</figcaption></figure>
                </div>
                <form method="post" action="intake_issue.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                    <label class="check-row"><input type="checkbox" name="confirm_issue" value="1" required> I reviewed the details and evidence with the customer. Issue this ticket.</label>
                    <div class="issuance-actions"><button class="secondary-btn" name="intent" value="edit" formnovalidate>Edit checks / replace images</button><button class="logout-btn" name="intent" value="issue">Issue ticket</button></div>
                </form>
            <?php else: ?>
                <h2>Remaining checks</h2>
                <?php if (
                    !$locations
                ): ?><p role="alert">No active storage location is available at this branch. Configure a branch bin or workbench before issuing.
<?php if (in_array($admin['role'], ['ADMIN','MANAGER'], true)): ?>
<a href="locations.php">Set up a branch location</a>
<?php else: ?>Ask your branch manager to create an active storage location.<?php endif; ?>
</p><?php else: ?>
                <form class="issuance-form" method="post" enctype="multipart/form-data" action="intake_issue.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                    <label>Where is the device stored?<select name="location_id" required><option value="">Choose a branch location</option><?php foreach (
                        $locations
                        as $location
                    ): ?><option value="<?= (int) $location['location_id'] ?>"<?= $values[
    'location_id'
] === (string) $location['location_id']
    ? ' selected'
    : '' ?>><?= h($location['location_name']) ?></option><?php endforeach; ?></select></label>
                    <label>Identifying marks or specifications<?= $draft['serial_number']
                        ? ' (optional)'
                        : ' (required without a serial number)' ?><textarea name="alternative_identifiers" rows="2" maxlength="1000"<?= $draft[
    'serial_number'
]
    ? ''
    : ' required' ?>><?= h($values['alternative_identifiers']) ?></textarea></label>
                    <fieldset><legend>Accessories received</legend>
                        <?php foreach (
                            [
                                'charger' => 'Charger / power adapter',
                                'bag' => 'Bag / case',
                                'cable' => 'Other cable',
                            ]
                            as $key => $label
                        ): ?><label class="check-row"><input type="checkbox" name="<?= h(
    $key,
) ?>" value="1"<?= $values[$key] === '1' ? ' checked' : '' ?>><?= h(
    $label,
) ?></label><?php endforeach; ?>
                        <label>Other accessories and quantities (optional)<input name="other_accessories" maxlength="500" value="<?= h(
                            $values['other_accessories'],
                        ) ?>"></label>
                        <label class="check-row"><input type="checkbox" name="checklist_confirmed" value="1" required<?= $values[
                            'checklist_confirmed'
                        ] === '1'
                            ? ' checked'
                            : '' ?>>I checked the list, including if no accessories were received.</label>
                    </fieldset>
                    <label>Overall-condition photo<input type="file" name="photo" accept="image/jpeg,image/png" required></label>
                    <?php foreach (array_slice($draft['devices'], 1) as $index => $device): ?>
                        <?php $extra = $values['extra_devices'][$index] ?? intakeIssueValues([]); ?>
                        <fieldset>
                            <legend>Device <?= $index + 2 ?> · <?= h(trim($device['make'] . ' ' . $device['model'])) ?></legend>
                            <p><?= h($device['reported_symptoms']) ?> · <?= h($device['condition_notes']) ?></p>
                            <label>Storage location
                                <select name="extra_devices[<?= $index ?>][location_id]" required>
                                    <option value="">Choose location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?= (int) $location['location_id'] ?>" <?= $extra['location_id'] === (string) $location['location_id'] ? 'selected' : '' ?>><?= h($location['location_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Other identifying marks
                                <textarea name="extra_devices[<?= $index ?>][alternative_identifiers]" maxlength="1000"><?= h($extra['alternative_identifiers']) ?></textarea>
                            </label>
                            <?php foreach (['charger' => 'Charger / adapter', 'bag' => 'Bag / case', 'cable' => 'Other cable'] as $key => $label): ?>
                                <label class="check-row"><input type="checkbox" name="extra_devices[<?= $index ?>][<?= h($key) ?>]" value="1" <?= $extra[$key] === '1' ? 'checked' : '' ?>><?= h($label) ?></label>
                            <?php endforeach; ?>
                            <label>Other accessories<input name="extra_devices[<?= $index ?>][other_accessories]" maxlength="500" value="<?= h($extra['other_accessories']) ?>"></label>
                            <label class="check-row"><input type="checkbox" name="extra_devices[<?= $index ?>][checklist_confirmed]" value="1" required <?= $extra['checklist_confirmed'] === '1' ? 'checked' : '' ?>>I checked the accessories for this device.</label>
                            <label>Overall-condition photo<input type="file" name="photo_<?= $index + 2 ?>" accept="image/jpeg,image/png" required></label>
                        </fieldset>
                    <?php endforeach; ?>
                    <label>Customer-signed intake form<input type="file" name="signed_form" accept="image/jpeg,image/png" required></label>
                    <p class="form-help">JPEG or PNG, up to 5 MB each. Use one legible signed form covering every device and the shop terms. Choose the images again after editing or a validation error.</p>
                    <label>Terms version on the signed form<input name="terms_version" maxlength="40" value="<?= h(
                        $values['terms_version'],
                    ) ?>" required placeholder="For example: Intake terms v1"></label>
                    <label class="check-row"><input type="checkbox" name="signature_confirmed" value="1" required<?= $values[
                        'signature_confirmed'
                    ] === '1'
                        ? ' checked'
                        : '' ?>>The named customer signed and confirmed the condition assessment and this version of the terms during this intake.</label>
                    <label class="check-row"><input type="checkbox" name="duplicate_confirmed" value="1" required<?= $values[
                        'duplicate_confirmed'
                    ] === '1'
                        ? ' checked'
                        : '' ?>>I checked the workbench for an existing intake for each device.</label>
                    <button class="logout-btn" name="intent" value="review">Review issuance</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>

