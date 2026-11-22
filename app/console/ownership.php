<?php
declare(strict_types=1);

/**
 * Read-only explanation for a device whose access has ALREADY been checked
 * by consoleDevice(). This helper must not be used for authorization.
 *
 * During the additive rollout the two branches should agree. Showing
 * a discrepancy is diagnostic only, never permission to operate at either
 * branch or evidence that physical custody has moved.
 */
function consoleOwnershipDetails(array $device): array
{
    $ticketId = (int) ($device['accountable_branch_id'] ?? 0);
    $repairId = (int) ($device['repair_branch_id'] ?? 0);
    $ticketName = trim((string) ($device['branch_name'] ?? ''));
    $repairName = trim((string) ($device['repair_branch_name'] ?? ''));

    $ticketVerified = $ticketId > 0 && $ticketName !== '';
    $repairVerified = $repairId > 0 && $repairName !== '';
    $aligned = $ticketVerified && $repairVerified && $ticketId === $repairId;

    return [
        // Labels are raw text from the scoped device result. Escape on output.
        'repair_branch' => $repairVerified ? $repairName : 'Not verified',
        'ticket_branch' => $ticketVerified ? $ticketName : 'Not verified',
        'review_required' => !$aligned,
        'message' => $aligned
            ? 'Both branches currently match. This display does not change permissions.'
            : 'Branch assignment needs staff review. Partial-ticket repair ownership is not enabled.',
    ];
}
