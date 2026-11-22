#!/usr/bin/env bash
# Disposable-only MySQL integrity rehearsal for RepairTrack.
set -euo pipefail
cd "$(dirname "$0")/.."

if ! printenv REPAIRTRACK_INTEGRITY_TEST | grep -Fxq 1; then
  echo 'Set REPAIRTRACK_INTEGRITY_TEST=1 for a disposable database rehearsal.' >&2
  exit 2
fi
if [[ -e config/config.php ]]; then
  echo 'Refusing to replace an existing config/config.php. Use a clean checkout.' >&2
  exit 2
fi
if [[ -z "$(printenv MYSQL_PWD 2>/dev/null || true)" ]]; then
  echo 'MYSQL_PWD must be supplied via the environment.' >&2
  exit 2
fi
command -v mysql >/dev/null
command -v php >/dev/null
php -r 'foreach (["pdo_mysql", "mbstring", "fileinfo"] as $e) { if (!extension_loaded($e)) exit(1); }'

suffix="$(php -r 'echo bin2hex(random_bytes(8));')"
db="repairtrack_test_integrity_$suffix"
[[ "$db" =~ ^repairtrack_test_integrity_[a-f0-9]{16}$ ]] || exit 2
scratch="$(mktemp -d)"
owned_config=0
cleanup() {
  code=$?
  trap - EXIT
  if [[ "$owned_config" == 1 ]]; then rm -f config/config.php; fi
  # Randomly generated and never chosen by the operator.
  mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS $db;" >/dev/null 2>&1 || true
  rm -rf -- "$scratch"
  exit "$code"
}
trap cleanup EXIT

# Rewrite ONLY the two installation headers and verify both before import.
sed -e "s/^CREATE DATABASE IF NOT EXISTS repairtrack$/CREATE DATABASE $db/" \
    -e "s/^USE repairtrack;$/USE $db;/" database/schema.sql > "$scratch/schema.sql"
grep -Fqx "CREATE DATABASE $db" "$scratch/schema.sql"
grep -Fqx "USE $db;" "$scratch/schema.sql"
if grep -Eq '^CREATE DATABASE IF NOT EXISTS repairtrack$|^USE repairtrack;$' "$scratch/schema.sql"; then
  echo 'Schema rewrite did not replace installation headers.' >&2
  exit 1
fi
mysql -h 127.0.0.1 -u root < "$scratch/schema.sql"
mkdir -p "$scratch/intake" "$scratch/inventory"
cat > config/config.php <<'PHP'
<?php
return [
    'host' => '127.0.0.1',
    'database' => getenv('REPAIRTRACK_TEST_DATABASE'),
    'username' => 'root',
    'password' => getenv('MYSQL_PWD'),
    'intake_storage_path' => getenv('REPAIRTRACK_TEST_SCRATCH') . '/intake',
    'inventory_evidence_path' => getenv('REPAIRTRACK_TEST_SCRATCH') . '/inventory',
    'intake_hmac_key' => str_repeat('a', 64),
];
PHP
owned_config=1
export REPAIRTRACK_TEST_DATABASE="$db" REPAIRTRACK_TEST_SCRATCH="$scratch"

echo '[1/8] Populate a fresh test schema with real workflow fixtures'
php tools/migrate.php --status
php tests/payment-pickup.php --scope-isolation
php tests/payment-pickup.php --stock-pilot
php tests/payment-pickup.php --cancel
php tests/payment-pickup.php
php tools/migrate.php --apply --yes

echo '[2/8] Verify ownership, FK metadata, billing, stock and custody'
php tests/mysql-integrity.php --check

echo '[3/8] Snapshot all historical records in 19 operational tables'
php tests/mysql-integrity.php --snapshot > "$scratch/before.json"

echo '[4/8] Simulate an old database missing the device repair-branch column'
mysql -h 127.0.0.1 -u root "$db" -e 'ALTER TABLE ticket_devices DROP FOREIGN KEY fk_td_repair_branch, DROP INDEX idx_td_repair_branch, DROP COLUMN repair_branch_id'
php tools/migrate.php --status
php tools/migrate.php --apply --yes
php tools/migrate.php --status

echo '[5/8] Verify migration preserved every original non-ownership field'
php tests/mysql-integrity.php --snapshot > "$scratch/after.json"
diff -u "$scratch/before.json" "$scratch/after.json"
php tests/mysql-integrity.php --check

echo '[6/8] Check migration rerun idempotence'
php tools/migrate.php --apply --yes
php tests/mysql-integrity.php --snapshot > "$scratch/rerun.json"
diff -u "$scratch/before.json" "$scratch/rerun.json"

echo '[7/8] Check invalid FK, null/mixed ownership and rollback'
php tests/mysql-integrity.php --negative
php tests/mysql-integrity.php --snapshot > "$scratch/negative.json"
diff -u "$scratch/before.json" "$scratch/negative.json"

echo '[8/8] Recover interrupted backfill/index/FK then audit again'
php tests/repair-branch-migration.php
php tests/mysql-integrity.php --check
php tools/check_repair_branch_integrity.php
printf 'PASS: disposable MySQL integrity round; test database and config removed on exit.\n'
