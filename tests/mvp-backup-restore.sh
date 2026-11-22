#!/usr/bin/env bash
# CI-only destructive test: affects ONLY a fresh disposable restore database.
set -euo pipefail
[[ "\${REPAIRTRACK_DISPOSABLE_RESTORE:-}" == "I_CONFIRM_DISPOSABLE_CI" && "\${GITHUB_ACTIONS:-}" == "true" && -n "\${RUNNER_TEMP:-}" ]] ||
  { echo "Refusing backup/restore outside explicitly approved ephemeral CI." >&2; exit 2; }

source_db=repairtrack_test_journey
restore_db=repairtrack_test_restore
intake_source="$RUNNER_TEMP/journey-evidence"
stock_source="$RUNNER_TEMP/journey-stock"
mysql_cmd=(mysql -h 127.0.0.1 -u root -N -B)
query() { "\${mysql_cmd[@]}" "$1" -e "$2"; }
for directory in "$intake_source" "$stock_source"; do
  [[ -d "$directory" && ! -L "$directory" ]] || exit 2
  [[ -z "$(find "$directory" -type l -print -quit)" ]] ||
    { echo "Evidence symlinks are unsafe to restore." >&2; exit 2; }
done
[[ "$(query "$source_db" "SELECT COUNT(*) FROM repair_tickets WHERE record_stage='ISSUED' AND case_state='CLOSED'")" == "1" ]] ||
  { echo "Source must be the completed fictional journey." >&2; exit 2; }
[[ "$("\${mysql_cmd[@]}" -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$restore_db'")" == "0" ]] ||
  { echo "Destination already exists; refusing overwrite." >&2; exit 2; }

# Inventory directory fixture: a valid, UNLINKED fictional PNG. Database-linked
# stock evidence is exercised separately by the stock evidence regression suite.
fixture_name="$(printf 'a%.0s' {1..48}).png"
printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5FoAAAAASUVORK5CYII=' |
  base64 -d > "$stock_source/$fixture_name"

umask 077
work_dir="$(mktemp -d "$RUNNER_TEMP/rt-restore.XXXXXXXX")"
restore_created=0
cleanup() {
  if (( restore_created )); then
    "\${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS $restore_db" >/dev/null || true
  fi
  rm -rf -- "$work_dir"
  rm -f -- "$stock_source/$fixture_name"
}
trap cleanup EXIT
mkdir -p "$work_dir/backup/intake" "$work_dir/backup/stock" "$work_dir/restored/intake" "$work_dir/restored/stock"
mysqldump -h 127.0.0.1 -u root --single-transaction --quick \
  --routines --triggers --events --hex-blob --set-gtid-purged=OFF \
  "$source_db" > "$work_dir/backup/sql.sql"
test -s "$work_dir/backup/sql.sql"
cp -a "$intake_source/." "$work_dir/backup/intake/"
cp -a "$stock_source/." "$work_dir/backup/stock/"
manifest() {
  (cd "$1" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum)
}
manifest "$intake_source" > "$work_dir/intake.sha"
manifest "$stock_source" > "$work_dir/stock.sha"
test -s "$work_dir/intake.sha" && test -s "$work_dir/stock.sha" ||
  { echo "Missing evidence in disposable source." >&2; exit 1; }

"\${mysql_cmd[@]}" -e "CREATE DATABASE $restore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
restore_created=1
"\${mysql_cmd[@]}" "$restore_db" < "$work_dir/backup/sql.sql"
cp -a "$work_dir/backup/intake/." "$work_dir/restored/intake/"
cp -a "$work_dir/backup/stock/." "$work_dir/restored/stock/"
manifest "$work_dir/restored/intake" > "$work_dir/intake-restored.sha"
manifest "$work_dir/restored/stock" > "$work_dir/stock-restored.sha"
cmp -s "$work_dir/intake.sha" "$work_dir/intake-restored.sha" ||
  { echo "Intake evidence hash mismatch." >&2; exit 1; }
cmp -s "$work_dir/stock.sha" "$work_dir/stock-restored.sha" ||
  { echo "Inventory evidence hash mismatch." >&2; exit 1; }

source_tables="$(query "$source_db" "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source_db' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")"
target_tables="$(query "$restore_db" "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$restore_db' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")"
[[ -n "$source_tables" && "$source_tables" == "$target_tables" ]] ||
  { echo "Table inventory mismatch after restore." >&2; exit 1; }
count=0
while IFS= read -r table; do
  [[ "$table" =~ ^[A-Za-z0-9_]+$ ]] || exit 2
  [[ "$(query "$source_db" "SELECT COUNT(*) FROM $table")" == "$(query "$restore_db" "SELECT COUNT(*) FROM $table")" ]] ||
    { echo "Row count changed in $table" >&2; exit 1; }
  count=$((count+1))
done <<< "$source_tables"

for database in "$source_db" "$restore_db"; do
  [[ "$(query "$database" "SELECT COUNT(*) FROM repair_tickets WHERE case_state='CLOSED'")" == "1" ]] || exit 1
  [[ "$(query "$database" "SELECT COUNT(*) FROM ticket_devices WHERE custody_state='WITH_CUSTOMER'")" == "1" ]] || exit 1
  [[ "$(query "$database" "SELECT COUNT(*) FROM repair_bill_payments")" == "2" ]] || exit 1
  [[ "$(query "$database" "SELECT SUM(amount) FROM repair_bill_payments")" == "375.00" ]] || exit 1
done

{
  query "$restore_db" "SELECT terms_signature_path FROM repair_tickets WHERE record_stage='ISSUED' AND terms_signature_path IS NOT NULL"
  query "$restore_db" "SELECT stored_path FROM ticket_device_photos"
  query "$restore_db" "SELECT signature_path FROM ticket_device_acknowledgments"
} | sort -u > "$work_dir/refs"
test -s "$work_dir/refs" || { echo "No DB-linked evidence." >&2; exit 1; }
while IFS= read -r filename; do
  [[ "$filename" =~ ^[a-f0-9]{48}\.(png|jpg)$ ]] || exit 1
  [[ -f "$intake_source/$filename" && -f "$work_dir/restored/intake/$filename" ]] || exit 1
  cmp -s "$intake_source/$filename" "$work_dir/restored/intake/$filename" || exit 1
done < "$work_dir/refs"

echo "PASS: independent restored SQL ($count tables), closed ticket, PHP 375 ledger, returned custody, DB-linked intake evidence and exact private file hashes."
