#!/usr/bin/env bash
# Launch only an isolated, already configured fictional RepairTrack test clone.
set -euo pipefail
cd "$(dirname "$0")/.."
if ! command -v php.exe >/dev/null 2>&1 && ! command -v php >/dev/null 2>&1; then
  echo "BLOCK: PHP executable unavailable." >&2; exit 2
fi
PHP=php
if command -v php.exe >/dev/null 2>&1; then PHP=php.exe; fi
"$PHP" -l tools/preview_router.php
"$PHP" -r '
$c = require "config/config.php";
if (!is_array($c) || ($c["host"] ?? null) !== "127.0.0.1" ||
    ($c["database"] ?? null) !== "repairtrack_test_operator" ||
    ($c["username"] ?? null) !== "repairtrack_rc_app" ||
    ($c["app_url"] ?? null) !== "http://127.0.0.1:8742") {
    fwrite(STDERR, "BLOCK: Preview config must match the isolated operator database and local URL.\n");
    exit(2);
}
echo "Isolated test configuration matched.\n";
'
"$PHP" tools/local_acceptance_preflight.php
echo "Starting local preview at http://127.0.0.1:8742/"
echo "Press Ctrl+C to stop. Never forward or publish this port."
exec "$PHP" -S 127.0.0.1:8742 -t . tools/preview_router.php
