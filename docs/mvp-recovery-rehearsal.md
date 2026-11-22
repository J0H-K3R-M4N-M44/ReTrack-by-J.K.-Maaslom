# RepairTrack MVP — backup and recovery rehearsal

**Date:** 22 November 2026. **Status:** DISPOSABLE CI REHEARSAL VERIFIED; production disaster-recovery readiness is NOT certified.

## What was restored

The job `single-ticket-journey` in `.github/workflows/mvp-stabilization.yml` creates an entire fictional repair lifecycle in `repairtrack_test_journey`. With a server-controlled test-only setting, genuine issued PNG evidence remains in the runner's temporary private directory until restore verification.

`tests/mvp-backup-restore.sh` then:

1. Requires `GITHUB_ACTIONS=true`, the literal disposable-runner confirmation variable, an exact source database name and a **nonexistent** destination database. It refuses an existing destination, symlinks and missing private folders.
2. Dumps the source using `mysqldump --single-transaction` with schema, table data, triggers, routines and events; copies the **separate** intake and stock evidence directories into a temporary, nonpublished backup.
3. Creates `repairtrack_test_restore` as a **new** database, imports SQL and copies both private directories into separate restored locations.
4. Compares the SQL base-table names and row counts (47 tables in the first passing run), the closed ticket, `WITH_CUSTOMER` custody, the two counter-cash payments totaling PHP 375, and all referenced intake signature/photo filenames.
5. Computes and compares SHA-256 manifests of the original and restored private files. The stock directory includes a valid **unlinked fictional** image fixture; the DB association of inventory investigation evidence is tested separately by the stock evidence regression.
6. Removes the restored disposable database, temporary backup and inventory fixture. Source data is never overwritten. **No database dump or private image is uploaded as a CI artifact.**

**Observed pass:** [GitHub Actions 44996590305](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996590305), on code checkpoint `edf7f462`: source-to-restored 47 base tables, customer custody, settled bill, DB-linked intake files and byte-identical private directories. The exact latest branch-head CI must be checked separately after later changes.

## What this does not prove

- Recovery of a real, live `repairtrack` database or Windows/XAMPP installation.
- Consistency of a running production system while database transactions and files are changing concurrently. Live backups require a coordinated quiet period/snapshot strategy.
- Production recovery time and recovery point objectives (RTO/RPO), backup encryption, remote/offsite delivery, retention policy, operator permissions, or database/media corruption recovery.
- A linked inventory investigation evidence attachment in this **one-ticket** fixture; only its private directory copy/hash is checked here. The separate stock evidence suite covers linked records.
- Password hashes or device evidence without the original server-held configuration and `intake_hmac_key`. The test config uses a fictional static key; **real secrets must not be included in repository artifacts**.

## Operational recovery checklist (not yet executed)

| Stage | Operator acceptance requirement |
| --- | --- |
| Preparation | Obtain authorization, identify maintenance/quiet window, verify the selected shop branch/system and exact recovery target; never test on a live original |
| SQL backup | Capture complete MySQL schema and data with a consistent transaction snapshot, account for concurrent writes and MySQL version |
| Private files | Back up both configured `intake_storage_path` and `inventory_evidence_path` securely; preserve filenames and access controls |
| Configuration | Protect `config/config.php`, including original `intake_hmac_key`, separately as secret material; never commit or share it |
| Protection | Encrypt backup at rest/in transit; verify retention, offsite copy, access logging and private backup permissions |
| Dry-run restore | Restore SQL to a **new isolated schema** and files to **new private paths** outside `htdocs`; leave source untouched |
| Validation | Reconcile counts, bills, custody, repair events, signatures/photos and real inventory evidence records; verify authorized views and denied access |
| Sign-off | Record operator, timestamps, full command/log reference without passwords, restore duration, acceptable data-loss window and explicit approval |

**Never** paste real credentials into CI or reuse the test script's disposable database names for a production installation. This CI script intentionally refuses to run outside its controlled environment.

Related: [Manual user acceptance](mvp-uat-checklist.md) · [End-to-end test report](mvp-test-report.md) · [MVP scope](mvp-stabilization.md).
