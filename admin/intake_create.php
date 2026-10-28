<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
startAppSession();
$admin = requireAdmin();
$pdo = getPDO();

function intakeH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$branchQuery = $pdo->prepare(
    'SELECT b.branch_id, b.branch_code, b.branch_name, a.is_default
     FROM user_branch_assignments a
     JOIN branches b ON b.branch_id = a.branch_id
     WHERE a.user_id = ? AND b.is_active = 1
     ORDER BY b.branch_code'
);
$branchQuery->execute([$admin['user_id']]);
$branches = $branchQuery->fetchAll();

$defaultBranchId = '';
foreach ($branches as $branch) {
    if ((int) $branch['is_default'] === 1) {
        $defaultBranchId = (string) $branch['branch_id'];
        break;
    }
}
if ($defaultBranchId === '' && $branches !== []) {
    $defaultBranchId = (string) $branches[0]['branch_id'];
}

$values = [
    'intake_branch_id' => $defaultBranchId,
    'arrival_method' => 'WALK_IN',
    'customer_id' => '',
    'customer_search' => '',
    'customer_name' => '',
    'contact_type' => 'PHONE',
    'contact_value' => '',
    'device_type' => '',
    'make' => '',
    'model' => '',
    'serial_number' => '',
    'reported_symptoms' => '',
    'condition_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($_GET['customer_search'] ?? null)) {
    $values['customer_search'] = trim($_GET['customer_search']);
}

$customerResults = [];
if (mb_strlen($values['customer_search']) >= 2) {
    $searchTerm = '%' . mb_strtolower($values['customer_search'], 'UTF-8') . '%';
    $customerQuery = $pdo->prepare(
        "SELECT c.customer_id, c.full_name,
                MAX(CASE WHEN cc.is_primary = 1 THEN cc.contact_value END) AS primary_contact
         FROM customers c
         LEFT JOIN customer_contacts cc ON cc.customer_id = c.customer_id
         WHERE c.normalized_name LIKE ?
            OR LOWER(cc.contact_value) LIKE ?
            OR LOWER(cc.normalized_value) LIKE ?
         GROUP BY c.customer_id, c.full_name, c.updated_at
         ORDER BY c.updated_at DESC
         LIMIT 20"
    );
    $customerQuery->execute([$searchTerm, $searchTerm, $searchTerm]);
    $customerResults = $customerQuery->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $posted = $_POST[$key] ?? $default;
        $values[$key] = is_string($posted) ? trim($posted) : $default;
    }

    if (!csrfIsValid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'The form expired. Reload this page and try again.';
    }

    $branchId = ctype_digit($values['intake_branch_id']) ? (int) $values['intake_branch_id'] : 0;
    $branchIds = array_map(static fn ($branch) => (int) $branch['branch_id'], $branches);

    if (!in_array($branchId, $branchIds, true)) {
        $errors[] = 'Choose one of your assigned active branches.';
    }

    if (!in_array($values['arrival_method'], ['WALK_IN', 'MAIL'], true)) {
        $errors[] = 'Choose walk-in or mail.';
    }

    $existingCustomerId = ctype_digit($values['customer_id']) ? (int) $values['customer_id'] : 0;
    if ($existingCustomerId > 0) {
        $customerCheck = $pdo->prepare('SELECT customer_id FROM customers WHERE customer_id = ?');
        $customerCheck->execute([$existingCustomerId]);
        if (!$customerCheck->fetchColumn()) {
            $errors[] = 'Choose a customer from the search results.';
        }
    } else {
        if ($values['customer_name'] === '' || mb_strlen($values['customer_name']) > 160) {
            $errors[] = 'Enter the customer name (up to 160 characters).';
        } else {
            $cleanName = preg_replace('/\\s+/', ' ', $values['customer_name']);
            $normalizedName = mb_strtolower((string) $cleanName, 'UTF-8');
            $sameNameCheck = $pdo->prepare('SELECT customer_id FROM customers WHERE normalized_name = ? LIMIT 1');
            $sameNameCheck->execute([$normalizedName]);

            if ($sameNameCheck->fetchColumn() && !isset($_POST['confirm_different_person'])) {
                $errors[] = 'A customer with this name already exists. Search and select that customer, or confirm this is a different person.';
            }
        }

        if (!in_array($values['contact_type'], ['PHONE', 'EMAIL'], true) || $values['contact_value'] === '') {
            $errors[] = 'Enter a customer phone number or email address.';
        } elseif ($values['contact_type'] === 'EMAIL' && !filter_var($values['contact_value'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } elseif (mb_strlen($values['contact_value']) > 190) {
            $errors[] = 'The contact value is too long.';
        }
    }

    if ($values['device_type'] === '' || mb_strlen($values['device_type']) > 80) {
        $errors[] = 'Enter a device type (up to 80 characters).';
    }

    if (trim($values['make'] . $values['model']) === '') {
        $errors[] = 'Enter at least a make or model.';
    }

    if ($values['reported_symptoms'] === '') {
        $errors[] = 'Describe the reported problem.';
    }

    if ($values['condition_notes'] === '') {
        $errors[] = 'Record the device condition.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            if ($existingCustomerId > 0) {
                $customerId = $existingCustomerId;
            } else {
                $name = preg_replace('/\s+/', ' ', $values['customer_name']);
                $normalizedName = mb_strtolower((string) $name, 'UTF-8');

                $customerInsert = $pdo->prepare(
                    'INSERT INTO customers (full_name, normalized_name) VALUES (?, ?)'
                );
                $customerInsert->execute([$name, $normalizedName]);
                $customerId = (int) $pdo->lastInsertId();

                $contact = $values['contact_value'];
                $normalizedContact = $values['contact_type'] === 'EMAIL'
                    ? strtolower($contact)
                    : preg_replace('/[^0-9+]/', '', $contact);

                $contactInsert = $pdo->prepare(
                    'INSERT INTO customer_contacts
                        (customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications)
                     VALUES (?, ?, ?, ?, 1, 1)'
                );
                $contactInsert->execute([
                    $customerId,
                    $values['contact_type'],
                    $contact,
                    $normalizedContact,
                ]);
            }

            $ticketInsert = $pdo->prepare(
                "INSERT INTO repair_tickets
                    (record_stage, customer_id, intake_branch_id, accepted_by_user_id, arrival_method)
                 VALUES ('DRAFT', ?, ?, ?, ?)"
            );
            $ticketInsert->execute([
                $customerId,
                $branchId,
                $admin['user_id'],
                $values['arrival_method'],
            ]);
            $ticketId = (int) $pdo->lastInsertId();

            $deviceInsert = $pdo->prepare(
                "INSERT INTO ticket_devices
                    (ticket_id, child_sequence, device_type_snapshot, make_snapshot, model_snapshot,
                     serial_number_snapshot, reported_symptoms, condition_notes, repair_status)
                 VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'DRAFT')"
            );
            $deviceInsert->execute([
                $ticketId,
                $values['device_type'],
                $values['make'],
                $values['model'],
                $values['serial_number'] !== '' ? $values['serial_number'] : null,
                $values['reported_symptoms'],
                $values['condition_notes'],
            ]);

            $audit = $pdo->prepare(
                "INSERT INTO audit_events
                    (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at)
                 VALUES ('USER', ?, 'repair_ticket', ?, 'DRAFT_CREATED', ?, UTC_TIMESTAMP())"
            );
            $audit->execute([
                $admin['user_id'],
                $ticketId,
                json_encode([
                    'intake_branch_id' => $branchId,
                    'arrival_method' => $values['arrival_method'],
                ], JSON_THROW_ON_ERROR),
            ]);

            $pdo->commit();

            header('Location: dashboard.php?tab=workbench&created=1');
            exit;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RepairTrack draft intake error: ' . $error->getMessage());
            $errors[] = 'The draft could not be saved. Check the database and try again.';
        }
    }
}
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
                    <span><strong><?php echo intakeH($admin['full_name']); ?></strong><small>Administrator</small></span>
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
                            <?php foreach ($errors as $error): ?><p><?php echo intakeH($error); ?></p><?php endforeach; ?>
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
                            <li class="intake-step-marker" data-step-marker><span>2</span><label>Device</label></li>
                            <li class="intake-step-connector" aria-hidden="true"></li>
                            <li class="intake-step-marker" data-step-marker><span>3</span><label>Review</label></li>
                        </ol>

                        <section class="intake-search-panel" aria-labelledby="customer-search-title">
                            <p id="customer-search-title"><strong>Returning customer?</strong> Search first so you do not create a duplicate.</p>
                            <form class="intake-search" method="get" action="intake_create.php">
                                <label for="customer-search">Name, phone, or email
                                    <input id="customer-search" name="customer_search" value="<?php echo intakeH($values['customer_search']); ?>" autocomplete="off" placeholder="Enter at least 2 characters">
                                </label>
                                <button class="secondary-btn" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                            </form>
                            <?php if ($values['customer_search'] !== ''): ?>
                                <p class="form-help" style="margin:10px 0 0">
                                    <?php echo count($customerResults) ? count($customerResults) . ' matching customer(s) found. Choose one below, or start a new customer record.' : 'No matches found. Start a new customer record below.'; ?>
                                </p>
                            <?php endif; ?>
                        </section>

                        <form class="intake-form" method="post" action="intake_create.php" data-intake-wizard novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo intakeH(csrfToken()); ?>">
                            <input type="hidden" name="customer_search" value="<?php echo intakeH($values['customer_search']); ?>">

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">Who is bringing in the device?</h2>
                                <p>Use a matching customer record when one appears. Otherwise, create a new customer with one reliable way to contact them.</p>

                                <label class="form-wide" for="intake-branch">Service branch
                                    <select id="intake-branch" name="intake_branch_id" required>
                                        <option value="">Choose a branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo $values['intake_branch_id'] === (string) $branch['branch_id'] ? 'selected' : ''; ?>>
                                                <?php echo intakeH($branch['branch_code'] . ' — ' . $branch['branch_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="form-wide" for="customer-id">Customer record
                                    <select id="customer-id" name="customer_id">
                                        <option value="">Create a new customer</option>
                                        <?php foreach ($customerResults as $customer): ?>
                                            <option value="<?php echo (int) $customer['customer_id']; ?>" <?php echo $values['customer_id'] === (string) $customer['customer_id'] ? 'selected' : ''; ?>>
                                                <?php echo intakeH($customer['full_name'] . ($customer['primary_contact'] ? ' — ' . $customer['primary_contact'] : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="form-help">If you selected a match, you can continue to the device details.</span>
                                </label>

                                <div class="customer-create-fields form-wide" data-new-customer-fields>
                                    <div class="intake-form">
                                        <label for="customer-name">Customer name
                                            <input id="customer-name" name="customer_name" maxlength="160" autocomplete="name" value="<?php echo intakeH($values['customer_name']); ?>" data-new-customer-required>
                                        </label>
                                        <label for="contact-type">Best contact method
                                            <select id="contact-type" name="contact_type">
                                                <option value="PHONE" <?php echo $values['contact_type'] === 'PHONE' ? 'selected' : ''; ?>>Phone</option>
                                                <option value="EMAIL" <?php echo $values['contact_type'] === 'EMAIL' ? 'selected' : ''; ?>>Email</option>
                                            </select>
                                        </label>
                                        <label class="form-wide" for="contact-value">Phone number or email
                                            <input id="contact-value" name="contact_value" maxlength="190" value="<?php echo intakeH($values['contact_value']); ?>" placeholder="09xx xxx xxxx" data-new-customer-required>
                                        </label>
                                    </div>
                                </div>

                                <?php if ($values['customer_search'] !== '' && count($customerResults) === 0): ?>
                                    <div class="intake-help-box form-wide">No customer matched that search. Continue with the new customer fields above.</div>
                                <?php endif; ?>

                                <div class="form-wide">
                                    <div class="intake-subheading">How did the device arrive?</div>
                                    <label for="arrival-method">Arrival method
                                        <select id="arrival-method" name="arrival_method" required>
                                            <option value="WALK_IN" <?php echo $values['arrival_method'] === 'WALK_IN' ? 'selected' : ''; ?>>Brought to the branch</option>
                                            <option value="MAIL" <?php echo $values['arrival_method'] === 'MAIL' ? 'selected' : ''; ?>>Mailed to the branch</option>
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
                                        <?php foreach (['Laptop', 'Desktop computer', 'All-in-one computer', 'Tablet', 'Other'] as $deviceType): ?>
                                            <option value="<?php echo intakeH($deviceType); ?>" <?php echo $values['device_type'] === $deviceType ? 'selected' : ''; ?>><?php echo intakeH($deviceType); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="device-make">Brand / make
                                    <input id="device-make" name="make" maxlength="100" value="<?php echo intakeH($values['make']); ?>" placeholder="For example, Lenovo">
                                </label>
                                <label for="device-model">Model
                                    <input id="device-model" name="model" maxlength="160" value="<?php echo intakeH($values['model']); ?>" placeholder="For example, ThinkPad T14">
                                </label>
                                <label for="serial-number">Serial number <span class="form-optional">optional</span>
                                    <input id="serial-number" name="serial_number" maxlength="190" value="<?php echo intakeH($values['serial_number']); ?>" autocomplete="off">
                                </label>
                                <div class="intake-help-box form-wide">Enter a brand, a model, or both. If the customer cannot find the serial number, leave it blank.</div>
                                <div class="intake-step-actions form-wide">
                                    <button class="secondary-btn" type="button" data-step-back><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                                    <button class="logout-btn" type="button" data-step-next>Continue to issue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </div>
                            </fieldset>

                            <fieldset class="intake-step" data-intake-step>
                                <h2 tabindex="-1">What should the repair team know?</h2>
                                <p>Record the customer's description and the device's visible condition. A technician will diagnose the cause later.</p>
                                <label class="form-wide" for="reported-symptoms">Problem reported by customer
                                    <textarea id="reported-symptoms" name="reported_symptoms" rows="4" required maxlength="3000" placeholder="Example: The laptop shuts down after a few minutes."><?php echo intakeH($values['reported_symptoms']); ?></textarea>
                                    <span class="form-help">Use the customer's words. Do not enter a diagnosis here.</span>
                                </label>
                                <label class="form-wide" for="condition-notes">Visible condition at check-in
                                    <textarea id="condition-notes" name="condition_notes" rows="3" required maxlength="2000" placeholder="Example: Small dent near the left hinge; screen has no visible cracks."><?php echo intakeH($values['condition_notes']); ?></textarea>
                                    <span class="form-help">Include visible damage or note “no visible damage”. Ask the customer to confirm this description.</span>
                                </label>

                                <div class="intake-review form-wide" data-intake-review aria-label="Intake summary">
                                    <div><dt>Customer</dt><dd data-review-customer>Not entered</dd></div>
                                    <div><dt>Contact</dt><dd data-review-contact>Not entered</dd></div>
                                    <div><dt>Branch</dt><dd data-review-branch>Not selected</dd></div>
                                    <div><dt>Device</dt><dd data-review-device>Not entered</dd></div>
                                    <div class="review-wide"><dt>Reported problem</dt><dd data-review-problem>Not entered</dd></div>
                                </div>
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
