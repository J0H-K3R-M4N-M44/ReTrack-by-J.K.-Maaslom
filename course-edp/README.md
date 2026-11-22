# RepairTrack EDP — isolated academic database (corrected v3)

This folder contains a **standalone 23-table academic schema**. It is **not an upgrade to the existing RepairTrack database or application**. The PHP site under `app/`, `admin/`, and `views/` still expects the historical schema: **never point it to `repairtrack_edp` until migration and end-to-end tests are complete**.

## What's included

- Five staff roles, branches and branch membership; device/defect/status reference catalogs; accepted transition pairs; Sogod **fictional** classroom branch and locations.
- Draft tickets may start **device-first**, with a stored category and symptoms before identifying the customer. Issued device records require an owning customer, saved customer device, per-device item code and category. Issued tickets require customer, separate Ticket Code/portal code, four-digit PIN hash, issue time and a protected intake-signature record. Customer and branch ownership must match by composite foreign keys.
- Composite foreign keys prevent a ticket device from being assigned to a different ticket branch, a different-branch storage location or a technician not assigned to the responsible branch. **PHP must still verify technician role, activity, and capacity.**
- Customer **portal code** is a distinct 5-character hexadecimal identifier and uses the hash of a 4-digit phone-suffix PIN. The internal **Ticket Code** is separate and used by the cashier. Codes must be securely generated with uniqueness retry and portal reads must be throttled. The schema cannot provide those application protections by itself.
- Repair statuses and valid transition pairs are predefined in MySQL. The isolated `app/actions.php` demonstrates row-locked technician updates, recorded status history and setting `repair_started_at` only on first authorized entry into `IN_PROGRESS`. Changing fields through raw SQL would bypass the service; production DB credentials should not permit arbitrary bypasses.
- Quotes maintain versioned totals, sent/expiry dates and decision evidence. The small `repair_bill_items` table preserves per-device line amounts; it is not an inventory system. Draft bills can have no bill number. At most one bill may be ISSUED for a ticket at a time. The isolated cash action locks the ticket and current bill in a documented order, uses **locking reads** for existing payments even if the caller has an older transaction snapshot, handles retry keys, and refuses overpayment. A two-connection test exercises this behavior. The pickup action checks full settlement and readiness of every device, records per-device custody and completion events, and closes the ticket atomically.
- `COUNTER_CASH` is supported by the illustrative payment action. **COD is reserved as a catalog option, not enabled in the UI or service** until proof-of-collection workflow is specified and tested; no electronic payments.
- Image bytes are in **private file storage**. `media_assets` has an authorized customer reference and optional consistent ticket/device references, plus constrained opaque relative path, MIME, size and checksum. PHP must authorize access to each individual asset and verify its actual file content. Cross-customer ownership is rejected by FKs.
- `notes` records text and ticket/device ownership. The course's separate Notepad *open/save/file monitor* integration is still outstanding and cannot be satisfied merely by the notes table.

## What is deliberately NOT complete

This is the **database foundation and isolated reference actions**, not a wired-up website, complete onboarding and issuance service, file upload/streaming code, COD collection or courier delivery, password reset UX, external REST integration, browser-driven EDP exercises, or human acceptance testing. The Sogod prototype's workflow remains the reference. No claim of full PHP compatibility should be made based on these database tests.

## Verification (disposable MySQL only)

Requirements: MySQL **8.0.16+** (enforced `CHECK` constraints), PHP CLI with `pdo_mysql`. Use an isolated test server and a restricted database account. MySQL datetime values use UTC; set the SQL session `time_zone` to `+00:00`. The schema's `CREATE TABLE` statements have no `DROP` or destructive migration, and will fail on a second run rather than erase existing data.

```sh
php -l course-edp/app/actions.php
php -l course-edp/tests/db-integrity.php
php -l course-edp/tests/db-concurrency.php
php course-edp/tests/static-schema-check.php

# ONLY on a fresh disposable MySQL instance without existing repairtrack_edp data:
mysql -u root -p < course-edp/schema.sql
mysql -u root -p < course-edp/seed_reference.sql

# The test requires an explicit academic DB name. The fixture wraps writes
# in a transaction and rolls all fictional data back afterwards.
export REPAIRTRACK_EDP_TEST_DB=repairtrack_edp
export REPAIRTRACK_EDP_TEST_HOST=127.0.0.1
export REPAIRTRACK_EDP_TEST_USER=your_restricted_test_user
php course-edp/tests/db-integrity.php
php course-edp/tests/db-concurrency.php
```

The branch-specific `.github/workflows/edp-schema.yml` uses a disposable MySQL GitHub-hosted runner. It covers FK ownership rejection, invalid transition histories, technician authorization, quote approval before start, first-start retention, device-first intake drafts, itemized bills, guarded pickup, counterfeit asset paths, cashier authorization, overpayments, retries, and two independent MySQL connections racing to pay the same bill. It does **not** test the existing website's session/auth endpoints or real files.

## Next steps before any PHP migration

1. Verify CI against the exact commit and adjust SQL if needed (without altering the legacy schema).
2. Preserve `main`, the old database and the original `database/schema.sql`.
3. Implement safe ticket issuance with generated portal credentials, legitimate role scopes, and consistent first status event.
4. Port one selected PHP path at a time, with browser regression and privacy tests, instead of swapping the entire database connection.
5. Validate visual parity with the previously presented Sogod prototype and obtain local acceptance before classroom release.

## Remaining boundaries before website integration

The simplified schema is not a drop-in replacement for the original database. The illustrated service functions are **not** web endpoints and do not verify CSRF, browser sessions, rate limits, or actual file contents. All code paths changing repair status and handover must use guarded transactional actions; raw SQL can bypass those business rules. Keep PHP connection privileges restricted and review direct-write paths during migration. The accepted Sogod prototype remains the acceptance baseline. This schema does **not** automatically implement real-time tracking, external REST/JSON integration, or Notepad file monitoring.

The current pickup service assumes the bill-item sum equals the bill total (no separate tax/discount in this small academic exercise), and handles **counter pickup only**. Do not use it for COD or device transfers without a dedicated collection/custody workflow.

The seeded Sogod business is fictional; images remain in private storage with records in `media_assets`.
