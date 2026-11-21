#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.."

if [[ $# -gt 1 || "${1:-}" != "" && "${1:-}" != "--seed" ]]; then
  printf 'Usage: bash tools/setup-db.sh [--seed]\n' >&2
  exit 1
fi

if [[ -n "${REPAIRTRACK_PHP:-}" ]]; then
  php_bin="$REPAIRTRACK_PHP"
elif [[ -x /c/xampp/php/php.exe ]]; then
  php_bin=/c/xampp/php/php.exe
else
  php_bin=php
fi

if ! command -v "$php_bin" >/dev/null 2>&1; then
  printf 'PHP was not found. Set REPAIRTRACK_PHP to the path to your PHP executable.\n' >&2
  exit 1
fi

"$php_bin" tools/setup_db.php
if [[ "${1:-}" == "--seed" ]]; then
  "$php_bin" tools/seed.php
fi
