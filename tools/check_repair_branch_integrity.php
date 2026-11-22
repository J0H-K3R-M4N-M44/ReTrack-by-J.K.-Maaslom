<?php
declare(strict_types=1);

// Read-only rollout gate. Do not expose ticket/device identifiers to command logs.
// Phase 1 expects all issued devices to mirror the ticket's accountable branch.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/migration_support.php';
try {
    $pdo = getPDO();
    migrationPreflight($pdo);
    if (!migrationHasColumn($pdo, 'ticket_devices', 'repair_branch_id')) {
        throw new RuntimeException('Run the additive device repair-branch migration first.');
    }
    $sql = "SELECT COUNT(*) AS issued,
        COALESCE(SUM(d.repair_branch_id IS NULL),0) AS unassigned,
        COALESCE(SUM(d.repair_branch_id IS NOT NULL AND
            d.repair_branch_id <> t.accountable_branch_id),0) AS divergent
        FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
        WHERE t.record_stage='ISSUED'";
    $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    printf(
        "Device repair-branch readiness: %d issued, %d missing, %d divergent.\n",
        (int) $result['issued'],
        (int) $result['unassigned'],
        (int) $result['divergent'],
    );
    if ((int) $result['unassigned'] > 0 || (int) $result['divergent'] > 0 ||
        in_array('device_repair_branches.sql', migrationPending($pdo), true)) {
        fwrite(STDERR,
            "BLOCKED: repair-branch data needs review. No ownership cutover is enabled.\n");
        exit(1);
    }
    echo "PASS: all issued devices match their ticket repair branch. Partial ownership remains disabled.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Readiness check failed: " . $error->getMessage() . "\n");
    exit(1);
}
