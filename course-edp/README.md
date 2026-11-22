# RepairTrack EDP — Isolated academic database (draft v1)

This folder contains a **new, smaller schema** for a fictional classroom copy of RepairTrack. It is **not** an upgrade to the existing application database. The legacy application in `app/`, `admin/`, and `views/` still expects its original tables: **do not point it at `repairtrack_edp` yet**.

## Scope

- 22 tables: people/roles, Sogod demo branch, device and defect catalogs, tickets and repair status events, quotations, bills and cashier payments, note records, and private image metadata.
- Predefined statuses and a transition catalog are enforced by foreign keys. A future PHP handler must **also** check actor permissions, quote approval, technician capacity, payment eligibility, and transition rules transactionally.
- The public **tracking code** is 5 hexadecimal characters plus a hashed four-digit phone-suffix PIN. It is distinct from the **Ticket Code** used by cashiers. Short credentials require throttling and careful server-side disclosure controls.
- `repair_started_at` stores first repair start, in UTC. Application code must set it only on first legal entry to `IN_PROGRESS` and display it as local Philippine time.
- `media_assets` stores *metadata and private file identifiers*. Never use paths as public URLs, and never put customer photos in public document root. The schema has no image BLOBs.
- `notes` is structured text storage, **not** the Module 3 Notepad/file-watcher integration; that needs an actual file open/save/monitor flow in a later milestone.
- No real customer, staff, bank, payment, tracking, or image data should be copied from any deployed instance.

## Safest execution: GitHub Actions

The branch-scoped `.github/workflows/edp-schema.yml` starts a disposable MySQL service on an isolated runner, imports these SQL files, and runs integrity checks with fictional data. This avoids access to a real RepairTrack database. It does **not** run the existing PHP app against the new schema.

## Optional local execution (throwaway MySQL instance only)

Requires MySQL 8.0.16+ (CHECK constraints) and PHP with `pdo_mysql`. Use a **separate test installation** or a dedicated user restricted to the `repairtrack_edp` schema. Stop if `repairtrack_edp` already contains tables or real data; the SQL is intentionally not an in-place migration and has no table DROPs.

```sh
# Inspects the SQL source, makes no database connections:
php course-edp/tests/static-schema-check.php

# Creates ONLY `repairtrack_edp` (not `repairtrack`) and fails rather than
# silently replacing previously created tables:
mysql -u root -p < course-edp/schema.sql
mysql -u root -p < course-edp/seed_reference.sql

# Run against the separate test instance; set auth via your local environment.
export REPAIRTRACK_EDP_TEST_DB=repairtrack_edp
export REPAIRTRACK_EDP_TEST_HOST=127.0.0.1
export REPAIRTRACK_EDP_TEST_USER=your_restricted_test_user
# MYSQL_PWD is read by CLI tools. Prefer interactive local credentials.
php course-edp/tests/db-integrity.php
```

The PHP integration test rejects the wrong database name, begins a transaction, tests that invalid data is rejected, and rolls all fictional fixture writes back. The CI runner database itself is disposable.

## Schema-change procedure

1. Change this folder's schema and seed first, not `database/schema.sql` or the existing migration runner.
2. Run source checks and disposable MySQL tests. Preserve existing `repairtrack` and its backups.
3. Port one PHP module at a time to this schema with its own tests. Never assume the legacy app already works on the new database.
4. Verify Sogod prototype behavior end-to-end before considering academic release.

The SQL intentionally doesn't implement Web Notepad file monitoring, external REST APIs, authorization, role-specific workflows, cash settlement limits or state-machine triggers. Those are application responsibilities in later phases.
