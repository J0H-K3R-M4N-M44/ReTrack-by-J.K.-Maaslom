#!/usr/bin/env bash
# RepairTrack MVP — start the isolated local preview. No Apache setup.
set -euo pipefail
cd "$(dirname "$0")/.."

if command -v php.exe >/dev/null 2>&1; then
  PHP=php.exe
elif command -v php >/dev/null 2>&1; then
  PHP=php
else
  echo "PHP was not found. Open Git Bash with PHP on PATH." >&2
  exit 1
fi

# Retain only the safeguard that prevents accidentally using the real shop DB.
"$PHP" -r '
$c = is_file("config/config.php") ? require "config/config.php" : [];
if (($c["database"] ?? "") !== "repairtrack_test_operator") {
    fwrite(STDERR, "Set the isolated test database in config/config.php (repairtrack_test_operator).\n");
    exit(2);
}
'

echo "RepairTrack MVP: http://127.0.0.1:8742/"
echo "Local-only. Keep this terminal open; press Ctrl+C to stop."
exec "$PHP" -S 127.0.0.1:8742 -t . tools/preview_router.php
