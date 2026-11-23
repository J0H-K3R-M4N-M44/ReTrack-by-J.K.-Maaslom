# RepairTrack EDP — isolated academic database (corrected v3)

This folder contains a **standalone 23-table academic schema**. It is **not an upgrade to the existing RepairTrack database or application**. The PHP site under `app/`, `admin/`, and `views/` still expects the historical schema: **never point it to `repairtrack_edp` until migration and end-to-end tests are complete**.


## Current branch status (updated through Technician Workspace slice D)

The current academic preview now has a local-only **staff intake route** (`public/index.php`), a separate **customer tracking route** (`public/track.php`), and a separate **Technician Workspace** (`public/technician.php`). The latter works with **assigned fictional repairs**; the manager assignment, quotation sending/customer approval, and cashier interfaces are **not** implemented yet. The 23-table academic database, intake web journey, customer timeline, and technician web journey are covered by disposable MySQL and localhost HTTP CI. The original legacy website remains an independent application and is **not** migrated. No public deployment is authorized.

Older section notes below describe what was missing at their respective milestone, and should not be read as the latest system-wide feature inventory.

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

**Still missing across the current academic implementation:** a unified legacy migration, complete admin and cashier UI, manager quotation approval, COD collection or courier delivery, password reset UX, external REST integration, full browser-driven EDP exercises, and human acceptance testing. The Sogod prototype's workflow remains the reference. No claim of full PHP compatibility should be made based on these database tests.

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

**Known limitations at slice B (historical):** At the original intake milestone, there was no customer portal or technician interface. Both now exist in separate academic routes (slices C and D). The full admin/cashier consoles, file-download endpoint, generic account management, complete asynchronous JavaScript demonstration, maps, Notepad integration, and external REST API remain outstanding. Image upload consent checkbox records a staff confirmation, not cryptographic proof of the customer's signature. Staff login throttling uses a local protected-file counter suitable only for a single-instance localhost demonstration. Sessions require HTTPS and a correctly configured web server before any nonlocal use. The browser form displays three device rows, although the domain service accepts up to 25. Customer email and phone numbers in tests must be fictional. The use of `COUNTER_CASH` remains the only enabled academic payment service; COD has not been implemented.

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
- **Historical at slice D:** The manager interface was not connected at that milestone. Slice E below now adds assignment and staff-attested offline customer decisions. There is still no independent customer-authenticated in-app quotation approval.
- `tests/technician-flow.php` runs rollback-only domain tests, while `tests/web-smoke.py` now exercises real localhost HTTP login/role isolation, queue, legal transitions, diagnosis, draft quote, first start, customer timeline, and session revocation. Test-only account creation is CLI guarded and never a web sign-up.
- Still outstanding after the technician milestone: technician-to-customer chat, real images, parts requests, cashier UI, actual customer-initiated quotation decision, full EDP course demonstrations, desktop/mobile human acceptance, and non-local deployment hardening. Manager assignment and offline approval are addressed in slice E.


## Phase 3, slice E — manager assignment, quote publication, and offline approval

- `public/manager.php` is an independent localhost-only, CSRF-protected manager/admin workspace. It uses a separate `repairtrack_edp_manager` session, verifies live account activity/branch membership, and exposes paginated, branch-scoped unassigned/assigned/quotation queues. It is **not** an admin/staff-management system and does not create production accounts.
- `app/manager.php` reuses the existing transactional `edpAssignTechnician()` capacity and branch checks; reassignments automatically **SUPERSEDE unsent drafts** from the previous technician so the newly assigned technician can prepare a new version. An already sent quotation is not rewritten by a reassignment.
- A manager may **publish** only the latest diagnosed, technician-prepared `DRAFT` quotation. Publishing records `sent_at` and a seven-day `expires_at`, sets the device to `AWAITING_CUSTOMER_APPROVAL`, emits a customer-visible status event and an audit event. This means **visible on the customer portal**, not that SMS or email was dispatched.
- A manager may record `APPROVED` or `DECLINED` **only as a staff-attested offline customer response**, after providing a contact channel and retained confirmation reference. The audit event explicitly marks the source as `STAFF_ATTESTED_OFFLINE` (not a cryptographically verified customer action). **Customers cannot approve through the portal yet.** Approval leaves the device waiting for the technician's legal `IN_PROGRESS` transition; expired quotations cannot be approved. Decline or actual expiry sends the device back to `DIAGNOSING` with customer-visible history; the new allowed transition is part of the **fresh disposable seed only**. Requotes increment `version_no`.
- `edpInTransaction()` uses unique savepoint names when composing domain actions so nested manager/technician services do not overwrite a caller's rollback point. There are still no database triggers guaranteeing status-event coherence when code bypasses the domain layer.
- `tests/manager-flow.php` exercises manager branch/role checks, assignment and reassignment, stale-draft supersession, quote publication, expiry, re-quote, offline approval, first repair start, audit history and public quotation projection. `tests/web-smoke.py` now performs the real manager HTTP send/approve journey instead of the old synthetic `tech-approve` fixture; it also checks CSRF, cross-branch denial and revoked sessions.

**Safety/remaining work:** All manager actions are classroom examples and assume a human verified offline consent. A checkbox and free-text reference are **not independent proof** of a customer's approval; production use needs a verifiable approval method and retention policy. The short public code/PIN and file-backed rate limits are not production-grade. No cashier browser UI, COD or courier collection, public approvals, chat, real-file downloads, or legacy schema migration is included. `main`, original `repairtrack` and deployed website remain unchanged.


## Phase 3, slice F — isolated Cashier Console and controlled pickup

- `public/cashier.php` is a **localhost-only** cashier/manager/admin desk with its **own** `repairtrack_edp_cashier` session. Login checks active staff account, assigned branch and password hash. Every POST requires a CSRF token; all record requests are branch-scoped. The web root must remain `course-edp/public`, and the database must be `repairtrack_edp`.
- The desk accepts only the internal `RT-` + 12-hex-character **Ticket Code**, **not** the five-character customer portal code. Exact lookup yields a branch-scoped ticket summary and its device statuses; private diagnostic notes, the customer's complete phone number and portal credentials are not returned.
- `app/cashier.php` issues a **single current itemized bill** only when every device is in `READY_FOR_PICKUP`, physically at the branch, and has a *latest sent and approved* quotation. The ticket is locked transactionally; the bill stores a per-device snapshot and its sum, with zero discounts/tax in this simplified classroom workflow. Repeating the issue request returns the same issued bill. It will not silently overwrite existing draft bills or conflicting issued versions.
- Confirmed **counter cash only** uses the existing `edpRecordCashPayment()` with row locks, idempotency request keys, exact integer-cent balance arithmetic and overpayment rejection. Cashier payments support installments and display a printable, *staff-authorized* payment receipt by payment ID. The receipt acknowledges cash collected, **not** customer approval or physical device collection. `public/assets/cashier.js` handles printing with first-party JavaScript and no inline handlers.
- Pickup is permitted only once the current bill is **fully paid** and all devices are `READY_FOR_PICKUP` with `AT_LOCATION` custody. The cashier must re-enter the full recorded phone and attest the actual customer counter handover. The verification is rechecked while locked; `edpConfirmPickup()` then records `COMPLETED` for every device, customer-visible events, `WITH_CUSTOMER` custody, handover timestamps, and `CLOSED` ticket state atomically. **This is a staff-attested classroom step**, not independent cryptographic identity proof, and it does not support proxy collection, courier delivery or COD.
- Tests: rollback-only `tests/cashier-flow.php` verifies multi-device invoice totals, repeated issue, role/branch restrictions, partial payments and retry/overrun protection, wrong-customer phone, pickup closure, and receipt authorization. The HTTP smoke test adds exact Ticket Code lookup, separate cashier login, CSRF, cross-branch denial, premature billing rejection, real cash receipts and customer-portal completion.
- **Remaining limitations:** manager offline approval is only staff-attested; there is no automated receipt email/SMS, no cashier refunds or bill correction UI, no COD, no real-browser visual/accessibility audit, no secure externally deployed environment or integrated legacy migration. The user must not rely on this local demo as a fiscal/legal receipt system.
