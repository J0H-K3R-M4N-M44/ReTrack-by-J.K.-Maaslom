<?php
declare(strict_types=1);

// Source-only sanity check; deliberately makes no database connections.
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$seed = file_get_contents(__DIR__ . '/../seed_reference.sql');
if (!is_string($schema) || !is_string($seed)) {
    throw new RuntimeException('Cannot read academic SQL files.');
}

preg_match_all('/\bCREATE TABLE\s+([a-z_]+)\s*\(/i', $schema, $matches);
$tables = $matches[1];
if (count($tables) !== 22 || count(array_unique($tables)) !== 22) {
    throw new RuntimeException('Expected exactly 22 unique classroom tables.');
}
if (!preg_match('/\bUSE\s+repairtrack_edp\s*;/i', $schema)
    || !preg_match('/\bUSE\s+repairtrack_edp\s*;/i', $seed)) {
    throw new RuntimeException('Both scripts must explicitly select the classroom database.');
}
if (preg_match('/^\s*(?:DROP|ALTER|TRUNCATE|DELETE|REPLACE|UPDATE)\b/im', $schema)) {
    throw new RuntimeException('Schema must not contain destructive data or schema changes.');
}

preg_match_all('/\bREFERENCES\s+([a-z_]+)\s*\(/i', $schema, $references);
foreach ($references[1] as $table) {
    if (!in_array($table, $tables, true)) {
        throw new RuntimeException("Foreign key references unknown table: $table");
    }
}

foreach (['repair_started_at', 'portal_code CHAR(5)', 'portal_pin_hash',
    'UNIQUE KEY uq_portal_code', 'UNIQUE KEY uq_payment_request_key',
    'CHECK (amount > 0)', 'ticket_status_events', 'media_assets'] as $required) {
    if (!str_contains($schema, $required)) {
        throw new RuntimeException("Required integrity element missing: $required");
    }
}
foreach (['IN_PROGRESS', 'READY_FOR_PICKUP', 'SOGOD-DEMO', 'OTHER', 'CASHIER'] as $required) {
    if (!str_contains($seed, $required)) {
        throw new RuntimeException("Required reference entry missing: $required");
    }
}

echo 'PASS: 22 tables, self-contained FKs, no destructive schema commands, expected constraints and catalogs.\n';
