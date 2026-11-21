<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

function migrationHasTable(PDO $pdo, string $table): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    );
    $query->execute([$table]);
    return (int) $query->fetchColumn() === 1;
}
function migrationHasColumn(PDO $pdo, string $table, string $column): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    );
    $query->execute([$table, $column]);
    return (int) $query->fetchColumn() === 1;
}
function migrationPreflight(PDO $pdo): void
{
    foreach (
        ['users', 'repair_tickets', 'ticket_devices', 'ticket_status_events', 'repair_bills']
        as $table
    ) {
        if (!migrationHasTable($pdo, $table)) {
            throw new RuntimeException(
                "Missing $table. Select an existing RepairTrack database in config/config.php. This runner does not install a fresh database.",
            );
        }
    }
}
function migrationPending(PDO $pdo): array
{
    $pending = [];
    if (!migrationHasTable($pdo, 'password_reset_tokens')) {
        $pending[] = 'password_reset.sql';
    }
    $missing =
        !migrationHasColumn($pdo, 'ticket_devices', 'repair_started_at') ||
        !migrationHasColumn($pdo, 'repair_tickets', 'portal_code') ||
        !migrationHasColumn($pdo, 'repair_tickets', 'portal_pin_hash');
    $role = $pdo
        ->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'",
        )
        ->fetchColumn();
    $missing = $missing || !str_contains((string) $role, "'CASHIER'");
    $unique = $pdo
        ->query(
            "SELECT COUNT(*) FROM (SELECT INDEX_NAME FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair_tickets'
        GROUP BY INDEX_NAME HAVING MAX(NON_UNIQUE) = 0 AND COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'portal_code' AND MAX(SUB_PART) IS NULL) indexes_found",
        )
        ->fetchColumn();
    $missing = $missing || (int) $unique === 0;
    if (!$missing) {
        // Also recover an interrupted historical backfill, without changing known dates.
        $missing = (bool) $pdo
            ->query(
                "SELECT EXISTS(SELECT 1 FROM ticket_devices d JOIN ticket_status_events e ON e.ticket_device_id = d.ticket_device_id
            WHERE d.repair_started_at IS NULL AND e.to_status = 'IN_PROGRESS' AND (e.from_status IS NULL OR e.from_status <> 'IN_PROGRESS'))",
            )
            ->fetchColumn();
    }
    if ($missing) {
        $pending[] = 'staff_workspace.sql';
    }
    if (!migrationHasTable($pdo, 'repair_bill_payments')) {
        $pending[] = 'payment_handover.sql';
    }
    if (!migrationHasTable($pdo, 'staff_activity_reads')) {
        $pending[] = 'staff_activity.sql';
    }
    return $pending;
}
function migrationPrintStatus(array $pending): void
{
    foreach (
        ['password_reset.sql', 'staff_workspace.sql', 'payment_handover.sql', 'staff_activity.sql']
        as $filename
    ) {
        echo (in_array($filename, $pending, true) ? '[pending] ' : '[ready]   ') . $filename . "\n";
    }
}

// These are the trusted, line-delimited project migrations, not arbitrary uploads.
// DELIMITER is a Workbench command, so strip it and send each SQL statement to PDO.
function migrationRunFile(PDO $pdo, string $path): void
{
    $lines = file($path);
    if ($lines === false) {
        throw new RuntimeException('Cannot read migration: ' . basename($path));
    }
    $delimiter = ';';
    $statement = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $match)) {
            if (trim($statement) !== '') {
                throw new RuntimeException('Unexpected DELIMITER inside a statement.');
            }
            $delimiter = $match[1];
            continue;
        }
        $statement .= $line;
        if (str_ends_with(rtrim($statement), $delimiter)) {
            $sql = substr(rtrim($statement), 0, -strlen($delimiter));
            $query = $pdo->query($sql);
            // CALL may return several empty result sets; drain them before the next query.
            if ($query) {
                while ($query->nextRowset()) {
                }
                $query->closeCursor();
            }
            $statement = '';
        }
    }
    if (trim($statement) !== '') {
        throw new RuntimeException('Unterminated SQL in ' . basename($path));
    }
}
