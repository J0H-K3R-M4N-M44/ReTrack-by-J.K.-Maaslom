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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body page-fade">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="Administrator navigation">
            <div>
                <a class="dashboard-brand" href="dashboard.php" >
                    <span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                    <span>RepairTrack <strong>Admin</strong></span>
                </a>
                <nav class="dashboard-nav">
                    <a class="nav-item" href="dashboard.php?tab=workbench">
                        <i class="nav-icon fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span>Workbench</span>
                    </a>
                </nav>
            </div>
            <div class="sidebar-footer"><span class="status-dot" aria-hidden="true"></span><span>New intake</span></div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <div class="eyebrow">Administrator intake</div>
                    <h1><?= $draftId
                        ? 'Edit intake draft #' . $draftId
                        : 'New repair intake' ?></h1>
                </div>
                <div class="dashboard-user">
                    <span class="welcome-seal" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <span><strong><?php echo h(
                        $admin['full_name'],
                    ); ?></strong><small>Administrator</small></span>
                </div>
            </header>

            <div class="dashboard-content">
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
                                <?php foreach ($values as $key => $value): ?>
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
                            <form class="intake-search" method="get" action="intake_create.php"><input type="hidden" name="id" value="<?= $draftId ?>">
                                <label for="customer-search">Name, phone, or email
                                    <input id="customer-search" name="customer_search" value="<?php echo h(
                                        $values['customer_search'],
                                    ); ?>" autocomplete="off" placeholder="Enter at least 2 characters">
                                </label>
                                <button class="secondary-btn" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                            </form>
                            <?php if ($values['customer_search'] !== ''): ?>
                                <p class="form-help" >
                                    <?php echo count($customerResults)
                                        ? count($customerResults) .
                                            ' matching customer(s) shown (up to 20). Choose one, or narrow your search.'
                                        : 'No matches found. Start a new customer record below.'; ?>
                                </p>
                            <?php endif; ?>
                        </section>

                        <form class="intake-form" method="post" action="intake_create.php" data-intake-wizard novalidate><input type="hidden" name="form_key" value="<?= h(
                            $formKey,
                        ) ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo h(
                                csrfToken(),
                            ); ?>">
                            <input type="hidden" name="customer_search" value="<?php echo h(
                                $values['customer_search'],
                            ); ?>">

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">Who is bringing in the device?</h2>
                                <p>Select a returning customer or enter a new contact.</p>

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
                                    <a class="secondary-btn" href="dashboard.php?tab=workbench">Cancel</a>
                                    <button class="logout-btn" type="button" data-step-next hidden>Continue to device <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">What device needs service?</h2>
                                <p>Enter what you can identify. The serial number is optional; never guess or make one up.</p>
                                <label for="device-type">Device type
                                    <select id="device-type" name="device_type" required>
                                        <option value="">Choose a type</option>
                                        <?php foreach (intakeDeviceTypes() as $deviceType): ?>
                                            <option value="<?php echo h(
                                                $deviceType,
                                            ); ?>" <?php echo $values['device_type'] === $deviceType
    ? 'selected'
    : ''; ?>><?php echo h($deviceType); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="device-make">Brand / make
                                    <input id="device-make" name="make" maxlength="100" value="<?php echo h(
                                        $values['make'],
                                    ); ?>" placeholder="For example, Lenovo">
                                </label>
                                <label for="device-model">Model
                                    <input id="device-model" name="model" maxlength="160" value="<?php echo h(
                                        $values['model'],
                                    ); ?>" placeholder="For example, ThinkPad T14">
                                </label>
                                <label for="serial-number">Serial number <span class="form-optional">optional</span>
                                    <input id="serial-number" name="serial_number" maxlength="190" value="<?php echo h(
                                        $values['serial_number'],
                                    ); ?>" autocomplete="off">
                                </label>
                                <div class="intake-help-box form-wide">Enter a brand, a model, or both. If the customer cannot find the serial number, leave it blank.</div>
                                <label class="form-wide" for="reported-symptoms">Problem reported by customer
                                    <textarea id="reported-symptoms" name="reported_symptoms" rows="4" required maxlength="3000" placeholder="Example: The laptop shuts down after a few minutes."><?php echo h(
                                        $values['reported_symptoms'],
                                    ); ?></textarea>
                                    <span class="form-help">Use the customer's words. Do not enter a diagnosis here.</span>
                                </label>
                                <label class="form-wide" for="condition-notes">Visible condition at check-in
                                    <textarea id="condition-notes" name="condition_notes" rows="3" required maxlength="2000" placeholder="Example: Small dent near the left hinge; screen has no visible cracks."><?php echo h(
                                        $values['condition_notes'],
                                    ); ?></textarea>
                                    <span class="form-help">Include visible damage or note “no visible damage”. Ask the customer to confirm this description.</span>
                                </label>

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
    </div>
    <script src="../js/intake-wizard.js"></script>
    <script src="../js/trans.js"></script>
</body>
</html>
