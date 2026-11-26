<?php
// Presentation only: values and permissions are prepared by the controller.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $draftId
        ? 'Edit intake draft #' . $draftId
        : 'New repair intake' ?> | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/intake-wizard.css">
    <link rel="stylesheet" href="../css/workspace-design.css">
    <script src="../js/workspace-design.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body rt-layout page-fade">
    <?php
$workspaceUser = $admin;
$currentSection = 'drafts';
require __DIR__ . '/../workspace/navigation.php';
?>
<main id="rt-main" class="staff-page rt-content intake-page" tabindex="-1">
    <header class="staff-heading rt-page-head">
        <div>
            <p class="eyebrow">Device intake</p>
            <h1><?= $draftId ? 'Edit intake draft #' . $draftId : 'New repair intake' ?></h1>
            <p>Record the customer, device condition and intake details.</p>
        </div>
    </header>
    <div class="intake-content">
                <?php if (
                    $draftId > 0
                ): ?><p><a class="secondary-btn" href="intake_issue.php?id=<?= $draftId ?>">Complete checks and issue ticket</a> Save any draft edits first.</p><?php endif; ?>
                <section class="dashboard-card intake-card intake-wizard-card">
                    <div class="card-heading intake-wizard-heading">
                        <div>
                            <div class="eyebrow">Fast check-in</div>
                            <h2>Customer &amp; device</h2>
                        </div>
                        <span class="intake-step-count" data-step-count><?= $review
                            ? 'Step 3 of 3'
                            : 'Customer and device details' ?></span>
                    </div>
                    <p class="intake-note">Capture one contact method and the device details. Saving a draft does not issue a ticket.</p>

                    <?php if ($errors !== []): ?>
                        <div class="intake-errors" role="alert" tabindex="-1">
                            <strong>Review these items before saving:</strong>
                            <?php foreach ($errors as $error): ?><p><?php echo h(
    $error,
); ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($branches === []): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-store" aria-hidden="true"></i>
                            <strong>No active branch is assigned</strong>
                            <span>Ask an administrator to assign this account to an active branch.</span>
                        </div>
                    <?php else: ?>
                        <?php if ($review): ?>
                            <h2>Review with the customer</h2>
                            <p>Branch: <?php foreach ($branches as $branch) {
                                if ((string) $branch['branch_id'] === $values['intake_branch_id']) {
                                    echo h($branch['branch_name']);
                                }
                            } ?></p>
                            <dl class="intake-review">
                                <?php foreach ($values as $key => $value): ?>
                                    <?php if (
                                        $values['customer_id'] !== '' &&
                                        in_array(
                                            $key,
                                            ['customer_name', 'contact_type', 'contact_value'],
                                            true,
                                        )
                                    ) {
                                        continue;
                                    } ?>

                                    <?php if (
                                        in_array(
                                            $key,
                                            [
                                                'customer_search',
                                                'confirm_different_person',
                                                'customer_id',
                                                'intake_branch_id',
                                                'extra_devices',
                                            ],
                                            true,
                                        )
                                    ) {
                                        continue;
                                    } ?>
                                    <div><dt><?= h(
                                        ucwords(str_replace('_', ' ', $key)),
                                    ) ?></dt><dd><?= h($value ?: 'Not supplied') ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                            <h3><?= 1 + count($values['extra_devices']) ?> devices on this ticket</h3>
                            <?php foreach ($values['extra_devices'] as $index => $device): ?>
                                <h4>Device <?= $index + 2 ?></h4>
                                <dl class="intake-review">
                                    <?php foreach ($device as $key => $value): ?>
                                        <div><dt><?= h(ucwords(str_replace('_', ' ', $key))) ?></dt><dd><?= h($value ?: 'Not supplied') ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endforeach; ?>
                            <?php if ($values['customer_id'] !== ''): ?>
                                <p>Selected customer: <?= h(
                                    intakeSelectedCustomer($pdo, (int) $values['customer_id'])[
                                        'full_name'
                                    ] ?? '',
                                ) ?>. Existing contact details stay unchanged.</p>
                            <?php endif; ?>
                            <form class="intake-form" method="post" action="intake_create.php">
                                <input type="hidden" name="csrf_token" value="<?= h(
                                    csrfToken(),
                                ) ?>">
                                <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                                <?php foreach ($values['extra_devices'] as $index => $device): ?>
                                    <?php foreach ($device as $key => $value): ?>
                                        <input type="hidden" name="extra_devices[<?= $index ?>][<?= h($key) ?>]" value="<?= h($value) ?>">
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php foreach ($values as $key => $value): ?>
                                    <?php if (is_array($value)) continue; ?>
                                    <input type="hidden" name="<?= h($key) ?>" value="<?= h(
    $value,
) ?>">
                                <?php endforeach; ?>
                                <label class="form-wide"><input type="checkbox" name="confirm_review" value="1" required> I reviewed these details with the customer.</label>
                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" name="intent" value="edit" formnovalidate>Edit details</button>
                                    <button class="logout-btn" name="intent" value="save">Save intake draft</button>
                                </div>
                            </form>
                        <?php else: ?>
                        <ol class="intake-stepper" aria-label="Intake progress">
                            <li class="intake-step-marker is-current" data-step-marker><span>1</span><strong>Customer</strong></li>
                            <li class="intake-step-connector" aria-hidden="true"></li>
                            <li class="intake-step-marker" data-step-marker><span>2</span><strong>Device &amp; problem</strong></li>
                            <li class="intake-step-connector" aria-hidden="true"></li>
                            <li class="intake-step-marker" data-step-marker><span>3</span><strong>Review</strong></li>
                        </ol>

                        <section class="intake-search-panel" aria-labelledby="customer-search-title">
                            <p id="customer-search-title"><strong>Returning customer?</strong> Search first so you do not create a duplicate.</p>
                            <div class="intake-search">
                                <label for="customer-search">Name, phone, or email
                                    <input id="customer-search" form="intake-entry" name="customer_search" value="<?php echo h(
                                        $values['customer_search'],
                                    ); ?>" autocomplete="off" placeholder="Enter at least 2 characters">
                                </label>
                                <button class="secondary-btn" type="submit" form="intake-entry" name="intent" value="search-customer"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                            </div>
                            <?php if ($values['customer_search'] !== ''): ?>
                                <p class="form-help" >
                                    <?php echo mb_strlen($values['customer_search']) < 2
                                        ? 'Enter at least two characters to search.'
                                        : ($matchCount
                                        ? $matchCount .
                                            ' matching customer(s) shown (up to 10). Choose one, or narrow your search.'
                                        : 'No matching customers. Try a different name or contact.'); ?>
                                </p>
                            <?php endif; ?>
                        </section>

                        <form id="intake-entry" class="intake-form" method="post" action="intake_create.php" data-intake-wizard data-start-step="<?= $showDeviceStep ? 1 : 0 ?>" novalidate><input type="hidden" name="form_key" value="<?= h(
                            $formKey,
                        ) ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo h(
                                csrfToken(),
                            ); ?>">

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">Who is bringing in the device?</h2>
                                <p>Select a returning customer or enter a new contact.</p>

                                <?php if (count($branches) === 1 || $draftId > 0): ?>
                                    <?php $branchNames = array_column($branches, 'branch_name', 'branch_id'); ?>
                                    <p class="form-wide">Receiving branch <strong><?= h($branchNames[$values['intake_branch_id']] ?? '') ?></strong></p>
                                    <input type="hidden" name="intake_branch_id" value="<?= h($values['intake_branch_id']) ?>">
                                <?php else: ?>
                                <label class="form-wide" for="intake-branch">Service branch
                                    <select id="intake-branch" name="intake_branch_id" required>
                                        <option value="">Choose a branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo (int) $branch[
                                                'branch_id'
                                            ]; ?>" <?php echo $values['intake_branch_id'] ===
(string) $branch['branch_id']
    ? 'selected'
    : ''; ?>>
                                                <?php echo h(
                                                    $branch['branch_code'] .
                                                        ' — ' .
                                                        $branch['branch_name'],
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <?php endif; ?>
                                <label class="form-wide" for="customer-id">Customer record
                                    <select id="customer-id" name="customer_id">
                                        <option value="">Create a new customer</option>
                                        <?php foreach ($customerResults as $customer): ?>
                                            <option value="<?php echo (int) $customer[
                                                'customer_id'
                                            ]; ?>" <?php echo $values['customer_id'] ===
(string) $customer['customer_id']
    ? 'selected'
    : ''; ?>>
                                                <?php echo h(
                                                    $customer['full_name'] .
                                                        ($customer['primary_contact']
                                                            ? ' — ' . $customer['primary_contact']
                                                            : ''),
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="form-help">If you selected a match, you can continue to the device details.</span>
                                </label>

                                <div class="customer-create-fields form-wide" data-new-customer-fields>
                                    <div class="customer-fields-grid">
                                        <label for="customer-name">Customer name
                                            <input id="customer-name" name="customer_name" maxlength="160" autocomplete="name" value="<?php echo h(
                                                $values['customer_name'],
                                            ); ?>" data-new-customer-required>
                                        </label>
                                        <label for="contact-type">Best contact method
                                            <select id="contact-type" name="contact_type">
                                                <option value="PHONE" <?php echo $values[
                                                    'contact_type'
                                                ] === 'PHONE'
                                                    ? 'selected'
                                                    : ''; ?>>Phone</option>
                                                <option value="EMAIL" <?php echo $values[
                                                    'contact_type'
                                                ] === 'EMAIL'
                                                    ? 'selected'
                                                    : ''; ?>>Email</option>
                                            </select>
                                        </label>
                                        <label class="form-wide" for="contact-value">Phone number or email
                                            <input id="contact-value" name="contact_value" maxlength="190" value="<?php echo h(
                                                $values['contact_value'],
                                            ); ?>" placeholder="09xx xxx xxxx" data-new-customer-required>
                                        </label>
                                        <div class="form-wide">
                                            <input type="hidden" name="email_notifications_consent" value="0">
                                            <label for="email-notifications-consent">
                                                <input
                                                    type="checkbox"
                                                    id="email-notifications-consent"
                                                    name="email_notifications_consent"
                                                    value="1"
                                                    <?= $values['email_notifications_consent'] === '1' ? 'checked' : '' ?>
                                                >
                                                I have the customer's permission to email repair updates to this address.
                                            </label>
                                            <p class="form-help">Optional. Applies only when the contact method is Email. This does not sign the customer up for marketing.</p>
                                        </div>
                                    </div>
                                    <?php if (
                                        $duplicateFound ||
                                        $values['confirm_different_person'] === '1'
                                    ): ?>
                                    <label class="form-wide" for="different-person">
                                        <span><input type="checkbox" id="different-person" name="confirm_different_person" value="1" <?php echo $values[
                                            'confirm_different_person'
                                        ] === '1'
                                            ? 'checked'
                                            : ''; ?>> I checked the matches; this is a different person with the same name.</span>
                                    </label>
                                    <?php endif; ?>
                                </div>

                                <div class="form-wide">
                                    <div class="intake-subheading">Arrival</div>
                                    <label for="arrival-method">Arrival method
                                        <select id="arrival-method" name="arrival_method" required>
                                            <option value="WALK_IN" <?php echo $values[
                                                'arrival_method'
                                            ] === 'WALK_IN'
                                                ? 'selected'
                                                : ''; ?>>Brought to the branch</option>
                                            <option value="MAIL" <?php echo $values[
                                                'arrival_method'
                                            ] === 'MAIL'
                                                ? 'selected'
                                                : ''; ?>>Mailed to the branch</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="intake-step-actions form-wide">
                                    <a class="secondary-btn" href="<?= h(
                                        workbenchHome(),
                                    ) ?>">Cancel</a>
                                    <button class="logout-btn" type="button" data-step-next hidden>Continue to device <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">What device needs service?</h2>
                                <p>Enter what you can identify. The serial number is optional; never guess or make one up.</p>
                                <?php $devices = [intakeDeviceFields($values), ...$values['extra_devices']]; ?>
                                <?php foreach ($devices as $index => $device): ?>
                                    <?php require __DIR__ . '/device-fields.php'; ?>
                                <?php endforeach; ?>
                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" type="submit" name="intent" value="add-device"
                                        <?= count($devices) >= max(INTAKE_DEVICE_LIMIT, $savedDeviceCount) ? 'disabled' : '' ?>>
                                        Add another device
                                    </button>
                                </div>
                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" type="button" data-step-back hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                                    <button class="logout-btn" type="submit" name="intent" value="review">Review details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
</main>
    <script src="../js/intake-wizard.js"></script>
    <script src="../js/trans.js"></script>
</body>
</html>


