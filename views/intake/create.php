<?php
// Presentation only: values and permissions are prepared by the controller.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New repair intake | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/intake-wizard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body page-fade">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="Staff navigation">
            <div>
                <a class="dashboard-brand" href="dashboard.php" style="color:inherit;text-decoration:none">
                    <span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                    <span>RepairTrack <strong>Staff</strong></span>
                </a>
                <nav class="dashboard-nav">
                    <a class="nav-item" href="dashboard.php?tab=workbench">
                        <i class="nav-icon fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span>Back to workbench</span>
                    </a>
                </nav>
            </div>
            <div class="sidebar-footer"><span class="status-dot" aria-hidden="true"></span><span>New intake</span></div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <div class="eyebrow">Counter workflow</div>
                    <h1>New repair intake</h1>
                </div>
                <div class="dashboard-user">
                    <span class="welcome-seal" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <span><strong><?php echo intakeH(
                        $admin['full_name'],
                    ); ?></strong><small>Administrator</small></span>
                </div>
            </header>

            <div class="dashboard-content">
                <section class="dashboard-card intake-card intake-wizard-card">
                    <div class="card-heading intake-wizard-heading">
                        <div>
                            <div class="eyebrow">Fast check-in</div>
                            <h2>Capture the essentials first</h2>
                        </div>
                        <span class="intake-step-count" data-step-count>Step 1 of 3</span>
                    </div>
                    <p class="intake-note">Ask the customer for one contact method, the device, and what they noticed. A saved draft can be reviewed and completed by staff before the ticket is issued.</p>

                    <?php if ($errors !== []): ?>
                        <div class="intake-errors" role="alert" tabindex="-1">
                            <strong>Review these items before saving:</strong>
                            <?php foreach ($errors as $error): ?><p><?php echo intakeH(
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
                        <ol class="intake-stepper" aria-label="Intake progress">
                            <li class="intake-step-marker is-current" data-step-marker><span>1</span><label>Customer</label></li>
                            <li class="intake-step-connector" aria-hidden="true"></li>
                            <li class="intake-step-marker" data-step-marker><span>2</span><label>Device &amp; problem</label></li>
                            <li class="intake-step-connector" aria-hidden="true"></li>
                            <li class="intake-step-marker" data-step-marker><span>3</span><label>Review</label></li>
                        </ol>

                        <section class="intake-search-panel" aria-labelledby="customer-search-title">
                            <p id="customer-search-title"><strong>Returning customer?</strong> Search first so you do not create a duplicate.</p>
                            <form class="intake-search" method="get" action="intake_create.php">
                                <label for="customer-search">Name, phone, or email
                                    <input id="customer-search" name="customer_search" value="<?php echo intakeH(
                                        $values['customer_search'],
                                    ); ?>" autocomplete="off" placeholder="Enter at least 2 characters">
                                </label>
                                <button class="secondary-btn" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                            </form>
                            <?php if ($values['customer_search'] !== ''): ?>
                                <p class="form-help" style="margin:10px 0 0">
                                    <?php echo count($customerResults)
                                        ? count($customerResults) .
                                            ' matching customer(s) found. Choose one below, or start a new customer record.'
                                        : 'No matches found. Start a new customer record below.'; ?>
                                </p>
                            <?php endif; ?>
                        </section>

                        <form class="intake-form" method="post" action="intake_create.php" data-intake-wizard novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo intakeH(
                                csrfToken(),
                            ); ?>">
                            <input type="hidden" name="customer_search" value="<?php echo intakeH(
                                $values['customer_search'],
                            ); ?>">

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">Who is bringing in the device?</h2>
                                <p>Use a matching customer record when one appears. Otherwise, create a new customer with one reliable way to contact them.</p>

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
                                                <?php echo intakeH(
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
                                                <?php echo intakeH(
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
                                    <div class="intake-form">
                                        <label for="customer-name">Customer name
                                            <input id="customer-name" name="customer_name" maxlength="160" autocomplete="name" value="<?php echo intakeH(
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
                                            <input id="contact-value" name="contact_value" maxlength="190" value="<?php echo intakeH(
                                                $values['contact_value'],
                                            ); ?>" placeholder="09xx xxx xxxx" data-new-customer-required>
                                        </label>
                                    </div>
                                    <label class="form-wide" for="different-person">
                                        <span><input type="checkbox" id="different-person" name="confirm_different_person" value="1" <?php echo $values[
                                            'confirm_different_person'
                                        ] === '1'
                                            ? 'checked'
                                            : ''; ?>> I checked the matches; this is a different person with the same name.</span>
                                    </label>
                                </div>

                                <?php if (
                                    $values['customer_search'] !== '' &&
                                    count($customerResults) === 0
                                ): ?>
                                    <div class="intake-help-box form-wide">No customer matched that search. Continue with the new customer fields above.</div>
                                <?php endif; ?>

                                <div class="form-wide">
                                    <div class="intake-subheading">How did the device arrive?</div>
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
                                    <button class="logout-btn" type="button" data-step-next>Continue to device <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">What device needs service?</h2>
                                <p>Enter what you can identify. The serial number is optional; never guess or make one up.</p>
                                <label for="device-type">Device type
                                    <select id="device-type" name="device_type" required>
                                        <option value="">Choose a type</option>
                                        <?php foreach (intakeDeviceTypes() as $deviceType): ?>
                                            <option value="<?php echo intakeH(
                                                $deviceType,
                                            ); ?>" <?php echo $values['device_type'] === $deviceType
    ? 'selected'
    : ''; ?>><?php echo intakeH($deviceType); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="device-make">Brand / make
                                    <input id="device-make" name="make" maxlength="100" value="<?php echo intakeH(
                                        $values['make'],
                                    ); ?>" placeholder="For example, Lenovo">
                                </label>
                                <label for="device-model">Model
                                    <input id="device-model" name="model" maxlength="160" value="<?php echo intakeH(
                                        $values['model'],
                                    ); ?>" placeholder="For example, ThinkPad T14">
                                </label>
                                <label for="serial-number">Serial number <span class="form-optional">optional</span>
                                    <input id="serial-number" name="serial_number" maxlength="190" value="<?php echo intakeH(
                                        $values['serial_number'],
                                    ); ?>" autocomplete="off">
                                </label>
                                <div class="intake-help-box form-wide">Enter a brand, a model, or both. If the customer cannot find the serial number, leave it blank.</div>
                                <label class="form-wide" for="reported-symptoms">Problem reported by customer
                                    <textarea id="reported-symptoms" name="reported_symptoms" rows="4" required maxlength="3000" placeholder="Example: The laptop shuts down after a few minutes."><?php echo intakeH(
                                        $values['reported_symptoms'],
                                    ); ?></textarea>
                                    <span class="form-help">Use the customer's words. Do not enter a diagnosis here.</span>
                                </label>
                                <label class="form-wide" for="condition-notes">Visible condition at check-in
                                    <textarea id="condition-notes" name="condition_notes" rows="3" required maxlength="2000" placeholder="Example: Small dent near the left hinge; screen has no visible cracks."><?php echo intakeH(
                                        $values['condition_notes'],
                                    ); ?></textarea>
                                    <span class="form-help">Include visible damage or note “no visible damage”. Ask the customer to confirm this description.</span>
                                </label>

                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" type="button" data-step-back><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                                    <button class="logout-btn" type="button" data-step-next>Review details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">Review with the customer</h2>
                                <p>Check these details together. Use Back to correct them before saving a draft.</p>
                                <dl class="intake-review form-wide" data-intake-review aria-label="Intake summary">
                                    <div><dt>Customer</dt><dd data-review-customer>Not entered</dd></div>
                                    <div><dt>Contact</dt><dd data-review-contact>Not entered</dd></div>
                                    <div><dt>Branch</dt><dd data-review-branch>Not selected</dd></div>
                                    <div><dt>Device</dt><dd data-review-device>Not entered</dd></div>
                                    <div class="review-wide"><dt>Reported problem</dt><dd data-review-problem>Not entered</dd></div>
                                    <div class="review-wide"><dt>Visible condition</dt><dd data-review-condition>Not entered</dd></div>
                                    <div><dt>Serial number</dt><dd data-review-serial>Not supplied</dd></div>
                                    <div><dt>Arrival</dt><dd data-review-arrival>Not selected</dd></div>
                                </dl>
                                <div class="intake-help-box form-wide">Saving creates a draft for staff review. The ticket reference is created after the required condition, accessory, and consent checks are completed.</div>
                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" type="button" data-step-back><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                                    <div class="form-actions">
                                        <a class="secondary-btn" href="dashboard.php?tab=workbench">Cancel</a>
                                        <button class="logout-btn" type="submit">Save intake draft</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    <script src="../js/intake-wizard.js"></script>
    <script src="../js/trans.js"></script>
</body>
</html>
