# RepairTrack EDP — isolated academic database (corrected v2)

This folder contains a **standalone 22-table academic schema**. It is **not an upgrade to the existing RepairTrack database or application**. The PHP site under `app/`, `admin/`, and `views/` still expects the historical schema: **never point it to `repairtrack_edp` until migration and end-to-end tests are complete**.

## What's included

- Five staff roles, branches and branch membership; device/defect/status reference catalogs; accepted transition pairs; Sogod **fictional** classroom branch and locations.
- Draft tickets may exist before customers are selected. A ticket cannot be issued without a customer, internal Ticket Code and issue time. Device entries require a known customer and must match both their ticket's customer and the saved device's customer.
- Composite foreign keys prevent a ticket device from being assigned to a different ticket branch, a different-branch storage location or a technician not assigned to the responsible branch. **PHP must still verify technician role, activity, and capacity.**
- Customer **portal code** is a distinct 5-character hexadecimal identifier and uses the hash of a 4-digit phone-suffix PIN. The internal **Ticket Code** is separate and used by the cashier. Codes must be securely generated with uniqueness retry and portal reads must be throttled. The schema cannot provide those application protections by itself.
- Repair statuses and valid transition pairs are predefined in MySQL. The isolated `app/actions.php` demonstrates row-locked technician updates, recorded status history and setting `repair_started_at` only on first authorized entry into `IN_PROGRESS`. Changing fields through raw SQL would bypass the service; production DB credentials should not permit arbitrary bypasses.
- Quotes maintain versioned totals and decision evidence. Draft bills can have no bill number. The isolated cash action locks the bill, confirms its issued state, checks branch/role and remaining balance, handles retry keys, and refuses overpayment.
- `COUNTER_CASH` is supported by the illustrative payment action. **COD is reserved as a catalog option, not enabled in the UI or service** until proof-of-collection workflow is specified and tested; no electronic payments.
- Image bytes are in **private file storage**. `media_assets` has an authorized customer reference and optional consistent ticket/device references, plus constrained opaque relative path, MIME, size and checksum. PHP must authorize access to each individual asset and verify its actual file content. Cross-customer ownership is rejected by FKs.
- `notes` records text and ticket/device ownership. The course's separate Notepad *open/save/file monitor* integration is still outstanding and cannot be satisfied merely by the notes table.

## What is deliberately NOT complete

This is the **database foundation and isolated reference actions**, not a wired-up website, complete onboarding and issuance service, file upload/streaming code, COD collection, pickup/delivery module, password reset UX, external REST integration, browser-driven EDP exercises, or human acceptance testing. The Sogod prototype's workflow remains the reference. No claim of full PHP compatibility should be made based on these database tests.

## Verification (disposable MySQL only)

Requirements: MySQL **8.0.16+** (enforced `CHECK` constraints), PHP CLI with `pdo_mysql`. Use an isolated test server and a restricted database account. MySQL datetime values use UTC; set the SQL session `time_zone` to `+00:00`. The schema's `CREATE TABLE` statements have no `DROP` or destructive migration, and will fail on a second run rather than erase existing data.

```sh
php -l course-edp/app/actions.php
php -l course-edp/tests/db-integrity.php
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
```

The branch-specific `.github/workflows/edp-schema.yml` uses a disposable MySQL GitHub-hosted runner. It covers FK ownership rejection, invalid transition histories, technician authorization, quote approval before start, first-start immutability, draft bills, counterfeit asset paths, cashier authorization, overpayments and payment retries. It does **not** test the existing website's session/auth endpoints or real files.

## Next steps before any PHP migration

1. Verify CI against the exact commit and adjust SQL if needed (without altering the legacy schema).
2. Preserve `main`, the old database and the original `database/schema.sql`.
3. Implement safe ticket issuance with generated portal credentials, legitimate role scopes, and consistent first status event.
4. Port one selected PHP path at a time, with browser regression and privacy tests, instead of swapping the entire database connection.
5. Validate visual parity with the previously presented Sogod prototype and obtain local acceptance before classroom release.
