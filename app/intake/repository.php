<?php
declare(strict_types=1);
require_once __DIR__ . '/../web.php';

// SQL lives here. Change table mappings here when the data model changes.
function intakeBranches(PDO $pdo, int $userId): array
{
    $branchQuery = $pdo->prepare(
        'SELECT b.branch_id, b.branch_code, b.branch_name, a.is_default
         FROM user_branch_assignments a
         JOIN branches b ON b.branch_id = a.branch_id
         WHERE a.user_id = ? AND b.is_active = 1
         ORDER BY b.branch_code',
    );
    $branchQuery->execute([$userId]);
    return $branchQuery->fetchAll();
}

function intakeSearchCustomers(PDO $pdo, string $term): array
{
    if (mb_strlen($term) < 2) {
        return [];
    }
    $searchTerm = likePattern($term);
    $digits = preg_replace('/[^0-9]/', '', $term);
    $phoneTerm =
        preg_match('/^[+0-9() -]+$/', $term) && strlen($digits) >= 3 ? '%' . $digits . '%' : null;
    $customerQuery = $pdo->prepare(
        "SELECT c.customer_id, c.full_name,
                MAX(CASE WHEN cc.is_primary = 1 THEN cc.contact_value END) AS primary_contact
         FROM customers c
         LEFT JOIN customer_contacts cc ON cc.customer_id = c.customer_id
         WHERE c.normalized_name LIKE ? ESCAPE '!'
            OR LOWER(cc.contact_value) LIKE ? ESCAPE '!'
            OR LOWER(cc.normalized_value) LIKE ? ESCAPE '!'
            OR (cc.contact_type = 'PHONE' AND REPLACE(cc.normalized_value, '+', '') LIKE ?)
         GROUP BY c.customer_id, c.full_name, c.updated_at
         ORDER BY c.updated_at DESC
         LIMIT 20",
    );
    $customerQuery->execute([$searchTerm, $searchTerm, $searchTerm, $phoneTerm]);
    return $customerQuery->fetchAll();
}

// The controller validates first. Keep customer, ticket, device and audit atomic.
function intakeSaveDraft(PDO $pdo, array $values, int $userId): int
{
    $branchId = (int) $values['intake_branch_id'];
    try {
        // Keep the related inserts together; a failure rolls the whole draft back.
        $pdo->beginTransaction();
        intakeLockActor($pdo, $userId);

        $errors = intakeValidate($pdo, $values, intakeBranches($pdo, $userId));
        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }
        $customerId = intakeFindOrCreateCustomer($pdo, $values);

        // This remains a draft; tracking codes are assigned by a later issuance step.
        $ticketInsert = $pdo->prepare(
            "INSERT INTO repair_tickets
                (record_stage, customer_id, intake_branch_id, accepted_by_user_id, arrival_method)
             VALUES ('DRAFT', ?, ?, ?, ?)",
        );
        $ticketInsert->execute([$customerId, $branchId, $userId, $values['arrival_method']]);
        $ticketId = (int) $pdo->lastInsertId();

        $deviceInsert = $pdo->prepare(
            "INSERT INTO ticket_devices
                (ticket_id, child_sequence, device_type_snapshot, make_snapshot, model_snapshot,
                 serial_number_snapshot, reported_symptoms, condition_notes, repair_status)
             VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'DRAFT')",
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
             VALUES ('USER', ?, 'repair_ticket', ?, 'DRAFT_CREATED', ?, UTC_TIMESTAMP())",
        );
        $audit->execute([
            $userId,
            $ticketId,
            json_encode(
                [
                    'intake_branch_id' => $branchId,
                    'arrival_method' => $values['arrival_method'],
                ],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $pdo->commit();

        return $ticketId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error; // The controller displays a safe message, not database details.
    }
}

// Restore a selected customer if it falls outside the current search results.
function intakeSelectedCustomer(PDO $pdo, int $customerId): ?array
{
    $query = $pdo->prepare(
        'SELECT customer_id, full_name, NULL AS primary_contact FROM customers WHERE customer_id = ?',
    );
    $query->execute([$customerId]);
    return $query->fetch() ?: null;
}

// Call only inside the ticket transaction; existing customer details stay unchanged.
function intakeFindOrCreateCustomer(PDO $pdo, array $values): int
{
    $existingCustomerId = (int) $values['customer_id'];
    // Reuse a selected customer, or create a customer and contact together.
    if ($existingCustomerId > 0) {
        $customerId = $existingCustomerId;
    } else {
        $name = preg_replace('/\s+/', ' ', $values['customer_name']);
        $normalizedName = mb_strtolower((string) $name, 'UTF-8');

        $customerInsert = $pdo->prepare(
            'INSERT INTO customers (full_name, normalized_name) VALUES (?, ?)',
        );
        $customerInsert->execute([$name, $normalizedName]);
        $customerId = (int) $pdo->lastInsertId();

        $contact = $values['contact_value'];
        $normalizedContact =
            $values['contact_type'] === 'EMAIL'
                ? strtolower($contact)
                : preg_replace('/[^0-9+]/', '', $contact);

        $contactInsert = $pdo->prepare(
            'INSERT INTO customer_contacts
            (customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications)
         VALUES (?, ?, ?, ?, 1, 1)',
        );
        $contactInsert->execute([
            $customerId,
            $values['contact_type'],
            $contact,
            $normalizedContact,
        ]);
    }

    return $customerId;
}

function intakeLockActor(PDO $pdo, int $userId): void
{
    $query = $pdo->prepare(
        "SELECT user_id FROM users WHERE user_id = ? AND is_active = 1 AND role IN ('ADMIN', 'MANAGER', 'INTAKE_AGENT') FOR UPDATE",
    );
    $query->execute([$userId]);
    if (!$query->fetchColumn()) {
        throw new DomainException('An active intake staff member must save this draft.');
    }
}
