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

## Phase 3, slice A — isolated catalogs and intake issuance

New files (not public endpoints):

- `app/catalogs.php` — read-only lists of active device types and defects, all repair statuses, and branch-restricted receiving locations.
- `app/intake.php` — create a device-first draft and issue it after selecting an existing customer, verifying a server-managed private signature image, and confirming intake staff/branch permissions. The issue transaction creates saved customer device records, per-device labels, a status history entry for each device and an audit event. It generates a unique 5-hex portal code and separately generates an internal ticket code; PIN is `password_hash()` of the last four recorded phone digits and is **not returned**.
- `tests/intake-flow.php` — fictional two-device intake, invalid customer and restricted-role tests, private signature validation, initial event history and transaction rollback checks. Its temporary image is created outside the public webroot.

**Security boundary:** the signature path must come from server-owned upload staging after authenticated staff selection, not directly from a browser-provided filename. The functions do not check sessions/CSRF or provide public uploads; wire those later in a guarded controller with per-file ownership records. This example assumes the prerecorded intake signature is an image; additional signed-document formats need a separate validation policy.

## Phase 3, slice C — intake recovery, defect choices and negative HTTP tests

- The preview now includes **My Drafts** (up to 50 recent authored drafts), an edit form for all existing devices, and an audited **Abandon** action. Abandonment uses `ABANDONED` to distinguish a never-issued draft from a cancelled repair. It preserves the draft record, while no public code is created.
- Create-draft submits a random 64-character request key (`repair_tickets.create_request_key` UNIQUE), so retries return the same draft rather than duplicating tickets. The key is not a substitute for CSRF or authentication.
- Intake selects active `defect_types`, including `OTHER`; choices are saved to `ticket_defects`. Reported symptoms remain free text. Existing drafts can change their type, defect and reported symptoms before issue; the current form edits existing device rows only, not device counts.
- Returning customer reuse requires re-entering the **full recorded phone number** for the selected customer. An existing branch phone is flagged before issuing a duplicate *new* customer. Shared family phone numbers are not globally unique.
- The staff checkbox explicitly records **staff attestation** of classroom terms. File MIME and content checks do not cryptographically or visually prove a real signature. Preview terms are `CLASSROOM-1` and must be replaced with final approved terms before deployment.
- The preview refuses non-loopback web requests. It revalidates staff accounts and active branch membership on protected requests. Its local CI server sets `upload_max_filesize=6M` and `post_max_size=7M` to test the app's 5 MiB limit; actual installations must check their PHP and web-server limits.
- Expanded HTTP checks cover idempotent create POSTs, draft resume/edit/abandon, wrong-phone confirmation, duplicate contacts, oversized and empty uploads, cross-branch draft requests and disabled staff sessions. These tests remain synthetic and are *not* substitute for a real Chrome mobile/accessibility review.

## What is deliberately NOT complete

This is the **database foundation and isolated reference actions**, not a wired-up website, complete onboarding and issuance service, file upload/streaming code, COD collection or courier delivery, password reset UX, external REST integration, browser-driven EDP exercises, or human acceptance testing. The Sogod prototype's workflow remains the reference. No claim of full PHP compatibility should be made based on these database tests.

## Verification (disposable MySQL only)

Requirements: MySQL **8.0.16+** (enforced `CHECK` constraints), PHP CLI with `pdo_mysql`. Use an isolated test server and a restricted database account. MySQL datetime values use UTC; set the SQL session `time_zone` to `+00:00`. The schema's `CREATE TABLE` statements have no `DROP` or destructive migration, and will fail on a second run rather than erase existing data.

```sh
php -l course-edp/app/actions.php
php -l course-edp/app/catalogs.php
php -l course-edp/app/intake.php
php -l course-edp/tests/intake-flow.php
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
php course-edp/tests/intake-flow.php
php course-edp/tests/db-concurrency.php
```

The branch-specific `.github/workflows/edp-schema.yml` uses a disposable MySQL GitHub-hosted runner. It covers FK ownership rejection, invalid transition histories, technician authorization, quote approval before start, first-start retention, device-first intake drafts, itemized bills, guarded pickup, counterfeit asset paths, cashier authorization, overpayments, retries, and two independent MySQL connections racing to pay the same bill. It does **not** test the existing website's session/auth endpoints or real files.

## Next steps before any PHP migration

1. Verify CI against the exact commit and adjust SQL if needed (without altering the legacy schema).
2. Preserve `main`, the old database and the original `database/schema.sql`.
3. Test the isolated catalog and intake service against fictional records, then build authenticated and CSRF-protected web controllers. The intake domain functions are not public entry points.
4. Port one selected PHP path at a time, with browser regression and privacy tests, instead of swapping the entire database connection.
5. Validate visual parity with the previously presented Sogod prototype and obtain local acceptance before classroom release.

## Remaining boundaries before website integration

The simplified schema is not a drop-in replacement for the original database. The illustrated service functions are **not** web endpoints and do not verify CSRF, browser sessions, rate limits, or actual file contents. All code paths changing repair status and handover must use guarded transactional actions; raw SQL can bypass those business rules. Keep PHP connection privileges restricted and review direct-write paths during migration. The accepted Sogod prototype remains the acceptance baseline. This schema does **not** automatically implement real-time tracking, external REST/JSON integration, or Notepad file monitoring.

The current pickup service assumes the bill-item sum equals the bill total (no separate tax/discount in this small academic exercise), and handles **counter pickup only**. Do not use it for COD or device transfers without a dedicated collection/custody workflow.

The seeded Sogod business is fictional; images remain in private storage with records in `media_assets`.


## Phase 3, slice B — standalone authenticated web intake

This branch now provides an **isolated local preview** under `course-edp/public/`, not a replacement for the legacy website. It uses `course-edp/app/preview.php` to load the **academic** database only, establish a separate staff session, check live account state and assigned branch, enforce CSRF, and limit repeated login attempts. The browser form supports up to three devices per draft, a new customer or branch-scoped returning customer, a server-generated private signed-image path, and a one-time receipt. `course-edp/tests/web-smoke.py` exercises the actual HTTP forms, uploads and logout.

**Local preview requirements:** PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, sessions and file uploads; MySQL 8.0.16+ with the **disposable academic schema already installed**; a restricted database account granted only to `repairtrack_edp`; and a **private directory outside the web document root**. Configure environment variables (never commit real credentials):

```sh
export REPAIRTRACK_EDP_DB=repairtrack_edp
export REPAIRTRACK_EDP_HOST=127.0.0.1
export REPAIRTRACK_EDP_USER=your_restricted_academic_user
export REPAIRTRACK_EDP_PASSWORD='local-only-secret'
export REPAIRTRACK_EDP_PRIVATE_ROOT='/absolute/path/outside/webroot/edp-private'
php -S 127.0.0.1:8743 -t course-edp/public
# Open http://127.0.0.1:8743/ (localhost ONLY)
```

Do **not** run `php -S` from the repository root: that would expose `course-edp/schema.sql`, tests and application source as web files. For testing, generate an isolated fictional intake staff account using `course-edp/tests/web-fixture.php` **only** on a disposable test database with `REPAIRTRACK_EDP_TEST_DB=repairtrack_edp`. It is a test fixture, not production account provisioning; avoid running it on databases that contain any real customer data. The preview intentionally does not provide real signup or password resets.

The preview includes: private-session staff login and role/branch checks; CSRF on all form POSTs; protected sign-out; server-validated device categories; device-first draft creation; a branch-restricted lookup for returning customers; new customer registration within the issuance transaction; actual PNG/JPEG/WebP validation and protected upload staging; transactionally recorded issued devices, initial status events, and signature metadata; and a receipt which is removed from the session after first display. It never reveals the PIN, and it does not allow serving stored customer images from this preview.

**Known limitations:** This is classroom intake workflow only, not a complete browser system. There is no customer portal, repair board, status UI, finance UI, file-download endpoint, generic account management, async JavaScript event demonstrations, public maps, Notepad integration, or external REST API yet. Image upload consent checkbox records a staff confirmation, not cryptographic proof of the customer's signature. Staff login throttling uses a local protected-file counter suitable only for a single-instance localhost demonstration. Sessions require HTTPS and a correctly configured web server before any nonlocal use. The browser form displays three device rows, although the domain service accepts up to 25. Customer email and phone numbers in tests must be fictional. The use of `COUNTER_CASH` remains the only enabled academic payment service; COD has not been implemented.

**Verification:** The existing `.github/workflows/edp-schema.yml` now also serves `course-edp/public/` on localhost and runs `tests/web-smoke.py` after the disposable MySQL domain tests. The HTTP test verifies login/CSRF/role denial, a two-device draft, invalid image rejection, issue confirmation, duplicate issue rejection, receipt non-replay and logout. No deployed site or original database is accessed.


## Phase 3, slice C — customer tracking portal

The independent public route `course-edp/public/track.php` now provides **read-only** customer access. It uses the *same isolated academic database* as intake but a **different session cookie** from staff login. The customer enters the 5-hex portal code from the receipt and a four-digit PIN equal to the last four digits of the recorded phone. The server verifies the stored `password_hash()`, never logs input credentials, rotates session IDs on success, and expires customer sessions after 15 minutes of inactivity. CSRF is checked for both sign-in and sign-out.

Customer records are built with a **strict projection** in `app/tracking.php` and show only Ticket Code, branch, overall case state, device type/model/item code, current status, coarse custody location, first repair-start time, customer-visible status events, and **the latest sent** quote in approved/awaiting/declined states. UTC events are displayed in `Asia/Manila`. Private staff notes, raw phone numbers, names, private file paths/images, draft quotations, user identities, and financial payment records are not selected or returned. This is NOT a public asset download service or an online payment portal.

The public endpoint's login attempt limits use private `flock()`-guarded per-IP and per-code counters on **one local demonstration server** (30 per IP and 8 per code per 15 minutes). Valid sessions are invalidated if portal credentials are changed or the ticket ceases to be eligible. This simple local limiter is not suitable for high-traffic public deployment; a distributed server would require shared storage, monitoring, reverse-proxy-aware IP handling and stronger anti-automation policies. The five-hex code and four-digit PIN are deliberately short for the Sogod classroom prototype, so rate limits and private-session checks are mandatory even for testing.

Use the previously documented localhost command with `-t course-edp/public`, then open `/track.php`. The intake staff sign-in page provides a link to the customer route. Do NOT expose the preview publicly or assume that it has been through a production security audit. It does not yet include public chat, customer change requests, live GPS, online payments, SMS, real-time push updates, or image previews. These remain separate milestones.

The disposable GitHub Actions job now runs the extended `tests/web-smoke.py`: an issued 2-device ticket, fictional technician status transitions and first-start timestamp, customer PIN validation, public status timeline, quote allowlisting, hidden private notes and unsent quotes, logout, CSRF, and rate limiting. Existing MySQL, staff intake, authorization and financial concurrency tests continue to run.


## Phase 3, slice D — technician workspace (isolated)

- `app/bootstrap.php` contains the shared local-only security headers, academic PDO connection, storage-path validation, and staff login throttling. The customer tracking route now loads this bootstrap without loading the intake module. The `repairtrack` production schema and legacy website remain unchanged.
- `public/technician.php` is an independently authenticated, CSRF-protected technician console using a distinct `repairtrack_edp_technician` cookie. Disabled users and staff without active branch membership lose access on the next request. Only `TECHNICIAN` role may sign in.
- `app/technician.php` provides a 10-row paginated assigned queue and history, branch-scoped detail and status timeline, staff-only diagnosis, and a simple **unsent** quotation draft. Status choices come from allowed transitions and live quotation prerequisites. All writes use `edpInTransaction` with per-record locks and domain role/branch checks. The technician cannot accept a customer quote, approve a quote, issue a bill, or collect payments.
- `app/actions.php` additionally forbids `AWAITING_CUSTOMER_APPROVAL` unless the current quotation has actually been marked `AWAITING_CUSTOMER` with a send timestamp. Starting repair still requires an approved current quotation, sets the first UTC repair-start DateTime only once, and records a customer-safe timeline event.
- The technician route displays first-start DateTime in Philippine time. Its native HTML forms work without JavaScript; `public/assets/technician.js` demonstrates event delegation and a `CustomEvent` observer for status-selection feedback. The CSP permits only first-party JavaScript files, not inline scripts.
- The existing manager assignment service is tested by the CLI fixture but has **no manager-facing assignment UI yet**. The quote approval in `tests/web-fixture.php tech-approve` is **a synthetic test action only**; do not represent it as a working customer or manager approval interface.
- `tests/technician-flow.php` runs rollback-only domain tests, while `tests/web-smoke.py` now exercises real localhost HTTP login/role isolation, queue, legal transitions, diagnosis, draft quote, first start, customer timeline, and session revocation. Test-only account creation is CLI guarded and never a web sign-up.
- Still outstanding: technician-to-customer chat, real images, parts request workflow, manager assignment and quotation-send/approval pages, cashier UI, full EDP course demonstrations, desktop/mobile human acceptance, and non-local deployment hardening.
