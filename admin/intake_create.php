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
    'SELECT b.branch_id, b.branch_code, b.branch_name
     FROM user_branch_assignments a
     JOIN branches b ON b.branch_id = a.branch_id
     WHERE a.user_id = ? AND b.is_active = 1
     ORDER BY b.branch_code'
);
$branchQuery->execute([$admin['user_id']]);
$branches = $branchQuery->fetchAll();

$values = [
    'intake_branch_id' => '',
    'arrival_method' => 'WALK_IN',
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

    if ($values['customer_name'] === '' || mb_strlen($values['customer_name']) > 160) {
        $errors[] = 'Enter the customer name (up to 160 characters).';
    }

    if (!in_array($values['contact_type'], ['PHONE', 'EMAIL'], true) || $values['contact_value'] === '') {
        $errors[] = 'Enter a customer phone number or email address.';
    } elseif ($values['contact_type'] === 'EMAIL' && !filter_var($values['contact_value'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (mb_strlen($values['contact_value']) > 190) {
        $errors[] = 'The contact value is too long.';
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
    <title>Start intake | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body page-fade">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="Admin navigation">
            <div>
                <div class="dashboard-brand">
                    <span class="brand-mark"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span>
                    <span>RepairTrack <strong>Admin</strong></span>
                </div>
                <nav class="dashboard-nav">
                    <a class="nav-item" href="dashboard.php">
                        <i class="nav-icon fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span>Back to dashboard</span>
                    </a>
                </nav>
            </div>
            <div class="sidebar-footer">
                <span class="status-dot" aria-hidden="true"></span>
                <span>Draft intake</span>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <div class="eyebrow">Repair intake</div>
                    <h1>Start a draft ticket</h1>
                </div>
                <div class="dashboard-user">
                    <span class="welcome-seal" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <span><strong><?php echo intakeH($admin['full_name']); ?></strong><small>Administrator</small></span>
                </div>
            </header>

            <div class="dashboard-content">
                <section class="dashboard-card intake-card">
                    <div class="card-heading">
                        <div>
                            <div class="eyebrow">Step 1 of intake</div>
                            <h2>Customer and device details</h2>
                        </div>
                        <i class="fa-solid fa-laptop-medical" aria-hidden="true"></i>
                    </div>

                    <p class="intake-note">This saves an unissued draft. Photos, accessory checklist, signatures, and accountable branch must be completed before a ticket can be issued.</p>

                    <?php if ($errors !== []): ?>
                        <div class="intake-errors" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo intakeH($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($branches === []): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-store" aria-hidden="true"></i>
                            <strong>No active branch is assigned</strong>
                            <span>Ask an administrator to assign this account to a branch.</span>
                        </div>
                    <?php else: ?>
                        <form class="intake-form" method="post" action="intake_create.php">
                            <input type="hidden" name="csrf_token" value="<?php echo intakeH(csrfToken()); ?>">

                            <label>
                                Intake branch
                                <select name="intake_branch_id" required>
                                    <option value="">Choose a branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo $values['intake_branch_id'] === (string) $branch['branch_id'] ? 'selected' : ''; ?>>
                                            <?php echo intakeH($branch['branch_code'] . ' — ' . $branch['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Arrival method
                                <select name="arrival_method" required>
                                    <option value="WALK_IN" <?php echo $values['arrival_method'] === 'WALK_IN' ? 'selected' : ''; ?>>Walk-in</option>
                                    <option value="MAIL" <?php echo $values['arrival_method'] === 'MAIL' ? 'selected' : ''; ?>>Mail</option>
                                </select>
                            </label>

                            <label>
                                Customer name
                                <input name="customer_name" maxlength="160" required value="<?php echo intakeH($values['customer_name']); ?>">
                            </label>

                            <label>
                                Contact type
                                <select name="contact_type" required>
                                    <option value="PHONE" <?php echo $values['contact_type'] === 'PHONE' ? 'selected' : ''; ?>>Phone</option>
                                    <option value="EMAIL" <?php echo $values['contact_type'] === 'EMAIL' ? 'selected' : ''; ?>>Email</option>
                                </select>
                            </label>

                            <label>
                                Phone or email
                                <input name="contact_value" maxlength="190" required value="<?php echo intakeH($values['contact_value']); ?>">
                            </label>

                            <label>
                                Device type
                                <input name="device_type" maxlength="80" placeholder="Laptop, desktop, tablet..." required value="<?php echo intakeH($values['device_type']); ?>">
                            </label>

                            <label>
                                Make
                                <input name="make" maxlength="100" value="<?php echo intakeH($values['make']); ?>">
                            </label>

                            <label>
                                Model
                                <input name="model" maxlength="160" value="<?php echo intakeH($values['model']); ?>">
                            </label>

                            <label>
                                Serial number <span class="form-optional">optional</span>
                                <input name="serial_number" maxlength="190" value="<?php echo intakeH($values['serial_number']); ?>">
                            </label>

                            <label class="form-wide">
                                Reported problem
                                <textarea name="reported_symptoms" rows="3" required><?php echo intakeH($values['reported_symptoms']); ?></textarea>
                            </label>

                            <label class="form-wide">
                                Physical condition
                                <textarea name="condition_notes" rows="3" required placeholder="Record visible damage or write “no visible damage”."><?php echo intakeH($values['condition_notes']); ?></textarea>
                            </label>

                            <div class="form-actions form-wide">
                                <a class="secondary-btn" href="dashboard.php?tab=workbench">Cancel</a>
                                <button class="logout-btn" type="submit">Save draft</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    <script src="../js/trans.js"></script>
</body>
</html>
