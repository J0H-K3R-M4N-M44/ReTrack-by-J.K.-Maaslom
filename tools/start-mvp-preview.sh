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
if (!is_array($c)) {
    fwrite(STDERR, "BLOCK: Missing PHP configuration.\n"); exit(2);
}
foreach (["host" => "127.0.0.1", "database" => "repairtrack_test_operator",
           "username" => "repairtrack_rc_app"] as $field => $expected) {
    if (($c[$field] ?? null) !== $expected) {
        fwrite(STDERR, "BLOCK: Wrong test database " . $field . ".\n"); exit(2);
    }
}
$u = parse_url((string) ($c["app_url"] ?? ""));
if (!is_array($u) || ($u["scheme"] ?? "") !== "http" ||
    !in_array(strtolower((string) ($u["host"] ?? "")), ["127.0.0.1", "localhost"], true) ||
    isset($u["user"]) || isset($u["pass"])) {
    fwrite(STDERR, "BLOCK: app_url must use local loopback HTTP.\n"); exit(2);
}
if (($c["app_url"] ?? "") !== "http://127.0.0.1:8742") {
    echo "WARN: app_url differs from the preview address; links may need the local URL adjusted.\n";
}
echo "Isolated test database configuration matched.\n";
'
set +e
"$PHP" tools/local_acceptance_preflight.php
preflight_status=$?
set -e
if [ "$preflight_status" -ne 0 ] && [ "$preflight_status" -ne 3 ]; then
  echo "BLOCK: Preflight failed." >&2; exit 2
fi
if [ "$preflight_status" -eq 3 ]; then
  echo "WARN: Local HTTP boundary still requires browser verification."
fi
echo "Starting local preview at http://127.0.0.1:8742/"
echo "Press Ctrl+C to stop. Never forward or publish this port."
exec "$PHP" -S 127.0.0.1:8742 -t . tools/preview_router.php
