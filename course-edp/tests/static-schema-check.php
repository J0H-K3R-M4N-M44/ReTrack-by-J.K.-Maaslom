<?php
declare(strict_types=1);

// Source-only guard: never connects to MySQL and never modifies an existing DB.
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$seed = file_get_contents(__DIR__ . '/../seed_reference.sql');
$actions = file_get_contents(__DIR__ . '/../app/actions.php');
if (!is_string($schema) || !is_string($seed) || !is_string($actions)) {
    throw new RuntimeException('Academic SQL or isolated domain action source missing.');
}

preg_match_all('/\bCREATE TABLE\s+([a-z_]+)\s*\(/i', $schema, $matches);
$tables = $matches[1];
if (count($tables) !== 23 || count(array_unique($tables)) !== 23) {
    throw new RuntimeException('Expected exactly 23 unique classroom tables.');
}
if (!preg_match('/\bUSE\s+repairtrack_edp\s*;/i', $schema)
    || !preg_match('/\bUSE\s+repairtrack_edp\s*;/i', $seed)) {
    throw new RuntimeException('Both SQL files must explicitly select the disposable academic database.');
}
if (preg_match('/^\s*(?:DROP|ALTER|TRUNCATE|DELETE|REPLACE|UPDATE)\b/im', $schema)) {
    throw new RuntimeException('Academic schema must contain no destructive migrations.');
}

preg_match_all('/\bREFERENCES\s+([a-z_]+)\s*\(/i', $schema, $refs);
foreach ($refs[1] as $table) {
    if (!in_array($table, $tables, true)) {
        throw new RuntimeException("Foreign key references unknown table: $table");
    }
}

foreach ([
    'UNIQUE KEY uq_device_owner',
    'UNIQUE KEY uq_ticket_customer_pair',
    'UNIQUE KEY uq_ticket_branch_pair',
    'FOREIGN KEY (ticket_id, customer_id)',
    'FOREIGN KEY (customer_device_id, customer_id)',
    'FOREIGN KEY (ticket_id, branch_id)',
    'FOREIGN KEY (location_id, branch_id)',
    'FOREIGN KEY (ticket_device_id, ticket_id)',
    'repair_started_at', 'portal_code CHAR(5)', 'portal_pin_hash',
    'UNIQUE KEY uq_portal_code', 'UNIQUE KEY uq_payment_request_key',
    'CHECK (amount > 0)', 'ticket_status_events', 'media_assets',
    'chk_media_path', 'chk_ticket_state_fields', 'repair_bill_items',
    'uq_one_active_issued_bill', 'return_handover_at', 'item_code VARCHAR(32)',
] as $required) {
    if (!str_contains($schema, $required)) {
        throw new RuntimeException("Required schema element missing: $required");
    }
}
foreach (['DRAFT', 'IN_PROGRESS', 'READY_FOR_PICKUP', 'SOGOD-DEMO', 'OTHER', 'CASHIER'] as $needed) {
    if (!str_contains($seed, $needed)) {
        throw new RuntimeException("Missing reference entry: $needed");
    }
}
if (preg_match('/\bVALUES\s*\(1\s*,\s*\x27COUNTER\x27/i', $seed)) {
    throw new RuntimeException('Seed must resolve branch ID by stable branch code.');
}
foreach (['edpChangeRepairStatus', 'edpRecordCashPayment', 'edpAssignTechnician',
    'edpConfirmPickup', 'edpPaidCentsForUpdate', 'FOR UPDATE', 'SAVEPOINT',
    'allowed_status_transitions', 'APPROVED', 'COUNTER_CASH'] as $required) {
    if (!str_contains($actions, $required)) {
        throw new RuntimeException("Academic action safeguard missing: $required");
    }
}
echo "PASS: 23 tables, customer/branch ownership FKs, guarded actions, fictional seed, and no destructive SQL.\n";
