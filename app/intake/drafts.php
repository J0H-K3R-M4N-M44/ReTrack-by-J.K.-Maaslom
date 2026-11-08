<?php
declare(strict_types=1);

// Only single-device drafts assigned to this administrator can be edited here.
function intakeLoadDraft(PDO $pdo, int $ticketId, int $userId, bool $lock = false): array
{
    $query = $pdo->prepare(
        "SELECT t.ticket_id, t.customer_id, t.intake_branch_id, t.arrival_method,
        t.updated_at AS ticket_updated_at, d.ticket_device_id, d.updated_at AS device_updated_at,
        d.device_type_snapshot AS device_type, d.make_snapshot AS make, d.model_snapshot AS model,
        d.serial_number_snapshot AS serial_number, d.reported_symptoms, d.condition_notes
        FROM repair_tickets t
        JOIN ticket_devices d ON d.ticket_id = t.ticket_id
        JOIN branches b ON b.branch_id = t.intake_branch_id AND b.is_active = 1
        JOIN user_branch_assignments a ON a.branch_id = b.branch_id AND a.user_id = ?
        WHERE t.ticket_id = ? AND t.record_stage = 'DRAFT' AND d.repair_status = 'DRAFT'
        AND (SELECT COUNT(*) FROM ticket_devices child WHERE child.ticket_id = t.ticket_id) = 1" .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $query->execute([$userId, $ticketId]);
    $draft = $query->fetch();
    if (!$draft) {
        throw new DomainException(
            'This draft is unavailable, already issued, has multiple devices, or belongs to an unassigned branch.',
        );
    }
    return $draft;
}

function intakeDraftVersion(array $draft): string
{
    return hash('sha256', json_encode($draft, JSON_THROW_ON_ERROR));
}

function intakeUpdateDraft(
    PDO $pdo,
    int $ticketId,
    int $userId,
    string $version,
    array $values,
): void {
    $pdo->beginTransaction();
    try {
        $before = intakeLoadDraft($pdo, $ticketId, $userId, true);
        if (!hash_equals($version, intakeDraftVersion($before))) {
            throw new DomainException(
                'This draft changed in another tab. Reopen it before editing again.',
            );
        }
        if ((int) $values['intake_branch_id'] !== (int) $before['intake_branch_id']) {
            throw new DomainException('A saved draft must stay at its original branch.');
        }
        $errors = intakeValidate($pdo, $values, intakeBranches($pdo, $userId));
        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }
        $customerId = intakeFindOrCreateCustomer($pdo, $values);
        $ticket = $pdo->prepare(
            'UPDATE repair_tickets SET customer_id = ?, arrival_method = ? WHERE ticket_id = ?',
        );
        $ticket->execute([$customerId, $values['arrival_method'], $ticketId]);
        $device = $pdo->prepare('UPDATE ticket_devices SET customer_device_id = NULL,
            device_type_snapshot = ?, make_snapshot = ?, model_snapshot = ?, serial_number_snapshot = ?,
            reported_symptoms = ?, condition_notes = ? WHERE ticket_device_id = ?');
        $device->execute([
            $values['device_type'],
            $values['make'],
            $values['model'],
            $values['serial_number'] ?: null,
            $values['reported_symptoms'],
            $values['condition_notes'],
            $before['ticket_device_id'],
        ]);
        $after = intakeLoadDraft($pdo, $ticketId, $userId);
        $audit = $pdo->prepare("INSERT INTO audit_events
            (actor_type, actor_user_id, entity_type, entity_id, action, before_data, after_data, created_at)
            VALUES ('USER', ?, 'repair_ticket', ?, 'DRAFT_UPDATED', ?, ?, UTC_TIMESTAMP())");
        $audit->execute([
            $userId,
            $ticketId,
            json_encode($before, JSON_THROW_ON_ERROR),
            json_encode($after, JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
