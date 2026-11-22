# RepairTrack — MySQL integrity test round

**Status: eight-stage disposable MySQL integration suite PASSED in GitHub Actions run 44989776748 on commit 83e43d5 (22 Nov 2026).**
Branch: Partial-Ticket-Ownership. This work must not be merged or deployed until
the integration checks are verified.

## Test stages

1. Fresh schema and fixtures: create an unpredictable one-time test database,
   then exercise payment, cancellation, stock, whole-ticket transfer and branch isolation.
2. Schema metadata: indexed and foreign-key-backed unsigned repair_branch_id;
   foreign-key enforcement must be enabled.
3. Data integrity: 17 relational checks covering ticket/device ownership, draft
   separation, quote and bill links, payments, stock, reservations, custody
   events, and stocktake references.
4. Snapshot: hash every row in 19 operational tables, ordered by primary key.
   Only the intentionally removed/readded repair_branch_id is excluded.
   Snapshots contain row counts and hashes, not names or contact details.
5. Upgrade rehearsal: remove the new ownership column, index and FK on the
   disposable database, rerun the migration, compare all snapshots.
6. Rerun/idempotence: the second migration must not change those snapshots.
7. Rollback-only negative tests: null ownership, wrong-branch ownership and
   invalid foreign key must be rejected/detected and leave rows unchanged.
8. Interrupted-migration recovery: restore missing index/FK, preserve timestamps
   and complete the final read-only branch integrity checker.

The two-branch isolation test also runs inside a rollback transaction, so
temporary mixed ownership does not contaminate later test stages.

## Running the script

GitHub workflow: .github/workflows/mysql-integrity.yml. In a clean checkout,
on a disposable local MySQL server, with PHP 8.2+, pdo_mysql, mbstring and fileinfo:

    REPAIRTRACK_INTEGRITY_TEST=1 MYSQL_PWD=YOUR_TEMPORARY_TEST_PASSWORD \
      bash tests/mysql-integrity-round.sh

The runner refuses a checkout containing config/config.php, refuses missing
opt-in and test credentials, creates a randomized repairtrack_test_integrity_*
database, and drops only that generated database on exit. It installs a temporary
PHP database config and removes it even on error. It never intentionally connects
to a deployed production database. **Do not supply production credentials.**

Individual tests (only when a disposable test DB is already configured):

    php tests/mysql-integrity.php --check
    php tests/mysql-integrity.php --snapshot
    REPAIRTRACK_INTEGRITY_TEST=1 php tests/mysql-integrity.php --negative
    php tests/repair-branch-migration.php

For manual read-only Workbench inspection, select a disposable test schema and
run tests/sql/repair_branch_integrity.sql. Every violations value should be zero
and each schema-found value should be one.

## Stop conditions and limitations

Any unexpected metadata, invalid branch, orphan record, cross-ticket billing
linkage, stock problem, or snapshot difference fails the round. Negative
operations must rollback. The runner checks MySQL constraints and data integrity,
not every front-end behavior, cross-branch concurrency, customer consent, or
authorization cutover. These remain separate release gates.

**Verification record (22 Nov 2026):** Local staged PHP/Bash syntax passed.
This working environment has no local MySQL server or pdo_mysql, but the
**GitHub-hosted MySQL run 44989776748 passed all eight stages** at commit
83e43d5: real fixture workflows, 17 integrity checks, snapshots of 19 tables,
old-column migration rehearsal, idempotence, foreign-key/ownership negative tests,
rollback, interrupted migration recovery and final integrity gate.
The dedicated workflow runs again on every feature-branch commit, so confirm
its successful result on the final reviewed HEAD. Browser UI, full authorization
cutover, production load and real-database deployment remain unverified.
