<?php
declare(strict_types=1);

// Source-only guard: never connects to MySQL and never modifies an existing DB.
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$seed = file_get_contents(__DIR__ . '/../seed_reference.sql');
$actions = file_get_contents(__DIR__ . '/../app/actions.php');
$fixture = file_get_contents(__DIR__ . '/full-journey-fixture.php');
if (!is_string($schema) || !is_string($seed) || !is_string($actions) || !is_string($fixture)) {
    throw new RuntimeException('Academic SQL or isolated domain action source missing.');
}

preg_match_all('/\bCREATE TABLE\s+([a-z_]+)\s*\(/i', $schema, $matches);
$tables = $matches[1];
if (count($tables) !== 23 || count(array_unique($tables)) !== 23) {
    throw new RuntimeException('Expected exactly 23 unique classroom tables.');
}

// Every schema table must be classified: operational tables are guarded by
// the disposable fixture, branches are checked separately, and only the
// explicit reference catalogs may contain seeded rows.
if (!preg_match('/foreach\s*\(\s*\[(.*?)\]\s+as\s+\$table\s*\)/s', $fixture, $guardMatch)) {
    throw new RuntimeException('Disposable fixture operational-table guard is missing.');
}
preg_match_all("/'([a-z_]+)'/", $guardMatch[1], $guardMatches);
$guardTables = $guardMatches[1];
$referenceTables = [
    'roles', 'device_types', 'defect_types', 'repair_statuses',
    'allowed_status_transitions', 'storage_locations',
];
$classifiedTables = array_merge($guardTables, ['branches'], $referenceTables);
if (count($classifiedTables) !== count(array_unique($classifiedTables))) {
    throw new RuntimeException('A schema table is classified more than once in the disposable fixture guard.');
}
$missingTables = array_values(array_diff($tables, $classifiedTables));
$unknownTables = array_values(array_diff($classifiedTables, $tables));
if ($missingTables || $unknownTables) {
    throw new RuntimeException('Disposable fixture guard table coverage mismatch. Missing: '
        . implode(', ', $missingTables) . '; unknown: ' . implode(', ', $unknownTables));
}
if (!str_contains($fixture, 'SELECT branch_code FROM branches')
    || !str_contains($fixture, "['SOGOD-DEMO']")) {
    throw new RuntimeException('Disposable fixture must separately require the single SOGOD-DEMO branch.');
}
preg_match_all('/\bINSERT\s+INTO\s+([a-z_]+)/i', $seed, $seededMatches);
$seededTables = array_unique($seededMatches[1]);
foreach ($referenceTables as $table) {
    if (!in_array($table, $seededTables, true)) {
        throw new RuntimeException("Reference catalog is not explicitly seeded: $table");
    }
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
echo "PASS: 23 tables, exhaustive fixture guard, customer/branch ownership FKs, guarded actions, fictional seed, and no destructive SQL.\n";
