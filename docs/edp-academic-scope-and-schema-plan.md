# RepairTrack EDP — Phase 0: Requirements and reduced database design

**Status:** design baseline only. **Do not apply schema changes or run seeders.**  
**Source checkpoint:** `MVP-Release-Candidate-2026-11-22` at `cedd5e4bb28570f36d68761309eac40e1f02a78f`.  
**Target:** independently configured database `repairtrack_edp`; original `repairtrack` database remains unchanged.  
**Date:** 2026-11-22.

## Purpose and non-negotiable acceptance

Student Event-Driven Programming application; Sogod prototype is the workflow reference. Preserve intake, assigned technician, first repair-start DateTime, status board and timeline, date/count analytics hover and focus, aligned tables, cashier lookup by **Ticket Code**, private customer tracking, quote/approval recording, counter-cash payments and unpaid-pickup guard. The public **portal code** is a separate 5-character hexadecimal token with a hashed PIN derived from the final 4 digits of the recorded phone number. Internal `ticket_code` is a distinct identifier, currently `RT-` plus hexadecimal digits.

The course manuals additionally require event-driven listener/handler demonstrations; propagating events, observer/callbacks, asynchronous operations, project-integrated Notepad open/save/file monitoring, an **external REST/JSON** integration with errors/timeouts, and debugging/testing evidence. Notepad monitoring is not automatically provided by the SQL `notes` table.

No enterprise asset valuation, multi-stage stock transfer/loss accounting, stocktaking, special per-device branch ownership, advanced acquisition-cost registers, or unnecessary release automation in the course navigation. Preserve core privacy/security controls.

## Existing schema inventory

The reviewed base file `database/schema.sql` contains **48 CREATE TABLE definitions** (plus auxiliary migrations). Its current PHP modules have extensive dependencies on current table names. Never drop those tables in place. Build new code paths against a separate academic schema, using staged integration and a dedicated config. The existing `tools/migrate.php` scans database migrations, so **keep the academic schema out of the existing `database/` migration directory** until the migration logic is explicitly separated.

## Target: 22 small, explainable tables (proposed, not final migration)

| Group | Table | Core fields and role |
|---|---|---|
| Identity | `branches` | code/name/address; one accountable branch per ticket |
| Identity | `roles` | code/name; predefined role catalog, no free-text role |
| Identity | `users` | name, login, password_hash, role FK, enabled flag |
| Identity | `user_branch_assignments` | user FK + branch FK, composite uniqueness |
| Identity | `password_reset_tokens` | hashed one-use tokens and expiry if password recovery remains enabled |
| Catalogs | `device_types` | code/label, active and display order; must support Other |
| Catalogs | `defect_types` | code/label, active and display order; must support Other |
| Catalogs | `repair_statuses` | code/label/sort; fixed controlled catalog |
| Catalogs | `allowed_status_transitions` | from-status FK / to-status FK with unique pair; server validates role and prerequisites |
| Catalogs | `storage_locations` | branch FK, code/label; optional simplified bench or storage location |
| Repairs | `customers` | name, normalized phone, optional email; replaces separate contacts for academic MVP |
| Repairs | `customer_devices` | customer FK, device-type FK, make/model/serial; optional serial |
| Repairs | `repair_tickets` | customer FK, branch FK, internal ticket code, portal_code, PIN hash, received/closed timestamps |
| Repairs | `ticket_devices` | ticket FK, device FK, technician FK, status FK, symptoms, diagnosis, first repair_started_at |
| Repairs | `ticket_defects` | device FK + defect FK, unique pair; optional details for Other |
| Repairs | `ticket_status_events` | device FK, old/new status FKs, actor FK, timestamp and approved-safe public summary |
| Money | `repair_quotes` | device FK, version, parts/labor/other/total, decision/status; unique device/version |
| Money | `repair_bills` | ticket FK, unique bill number, total, status |
| Money | `repair_bill_payments` | bill FK, positive amount, approved payment method, cashier FK, receipt datetime, idempotency key |
| Assets | `media_assets` | customer/ticket/device association, category, private path, MIME, size, hash, uploader |
| Assets | `notes` | ticket or device FK, author FK, text, updated_at; file open/save/watch integration is separate |
| Integrity | `audit_events` | actor, entity, action, safe details, timestamp; not exposed to customers |

**Count: 22 proposed tables.** Adjust only after testing. Catalogs hold predefined **categories**. PHP still enforces multi-step workflow permissions, customer disclosure, valid status transitions, amount calculations, and payment-before-pickup; a foreign key alone cannot enforce those conditions.

### Mapping from existing tables

**Keep with compatible refactoring:** `branches`, `users`, `password_reset_tokens`, `user_branch_assignments`, `customers`, `customer_devices`, `storage_locations`, `repair_tickets`, `ticket_devices`, `ticket_status_events`, `repair_quotes`, `repair_bills`, `repair_bill_payments`, `audit_events`.

**Combine/replace carefully:** `customer_contacts` => `customers` phone/email; `ticket_device_photos` and protected attachment metadata => `media_assets`; `ticket_accessory_items` and intake acknowledgments => constrained device/ticket fields or small structured checklist; `repair_quote_items` => basic quote labor/parts/other breakdown; `repair_quote_decisions` => quote decision fields and audit; `repair_bill_items` => bill total + breakdown appropriate for a single classroom repair; `ticket_correction_notes`, `ticket_operational_events`, `mock_notification_log` => core status/audit events when necessary. Retain original history while validating any conversion.

**Defer from new academic schema:** `device_transfers`, `device_transfer_items`, `device_custody_events` if whole-ticket transfer is not in the graded demo; `parts`, `branch_inventory`, `inventory_transfers`, `inventory_transfer_items`, `inventory_transactions`, `ticket_parts_used`, `repair_part_requests`, `stock_reservations`, `stock_quarantines`, `stock_quarantine_dispositions`, `stock_count_reviews`, `stock_transfer_loss_cases`, `stock_transfer_recoveries`, `stock_transfer_evidence`, `part_acquisition_cost_records`, `stock_transfer_loss_valuations`, `inventory_stocktakes`, `inventory_stocktake_lines`, `staff_activity_reads`. No deletion from the original schema. If Sogod prototype demands physical interbranch transfer, reintroduce a minimal workflow before sign-off instead of forcing it into loose text.

## DB integrity and security rules

- Use InnoDB FKs, UNIQUE constraints for login, ticket, portal, bill, and status transition identities; CHECK for positive payments/nonnegative amounts; indexes on branch, status, ticket, and customer IDs.
- MySQL `CHECK` support/version must be verified before using it as a relied-upon enforcement mechanism. Client-side validation is advisory; PHP repeats validation for every POST.
- Use `roles`, `device_types`, `defect_types`, `repair_statuses` catalogs for allowed values; preserve `Other` plus a free-text diagnosis so genuine faults remain reportable.
- Keep a complete append-only public-safe status timeline. On the first legal transition to `IN_PROGRESS`, set `repair_started_at` once in a transaction and do not reset it on later edits.
- A 5-hex code has only 1,048,576 combinations. Generate it with a cryptographically secure random source, enforce UNIQUE with collision retry, hash the PIN, throttle attempts, and avoid exposing detailed customer data on failed authentication. Never treat the short code alone as authentication.
- Password hashes and reset tokens are never stored in plaintext. Keep role/branch checks server-side, including customer-safe media authorization.
- **Recommended file handling:** store actual compressed images in a protected directory outside the web root; `media_assets` stores only paths and metadata. If the academic course specifically needs DB BLOBs, add a separately capped `MEDIUMBLOB` variant after evaluating demo DB size and backups. Never place customer files into a publicly browsable folder.
- No paid pickup or completion without verified settlement/approved exception. Store money as `DECIMAL`; prevent duplicate payment POSTs with an idempotency key and a database transaction. No digital payments.
- Record financial and status history; do not delete records merely to make the table count smaller. If a prior ledger feature is removed, map the safety invariant to the new code.

## Implementation gates

**Gate 0 — agreed scope:** verify Sogod presentation requirements, roles, transfer/parts expectations, quote and payment rules against screens and tests. This document is a design, not acceptance proof.

**Gate 1 — baseline and copy:** make a disposable `repairtrack_edp` test DB and source branch only. Export/backup old schema. Preserve origin.

**Gate 2 — isolated DDL:** create a new versioned SQL script under `course-edp/` rather than the existing `database/` auto-migration folder. Test creation with a disposable MySQL version that enforces CHECK constraints.

**Gate 3 — catalog & fictional seed:** insert approved statuses, roles, devices, defects, Sogod branches and purely fictional tickets and photos. Test foreign keys, uniqueness, invalid inputs, transitions and collision handling.

**Gate 4 — PHP parity:** adapt intake, login, staff roles, repair/status, quotes, payment/pickup, tracking and assets behind the academic database config; no feature claimed complete without browser and SQL tests.

**Gate 5 — EDP additions:** add Notepad open/save/file monitoring (with an actual watcher equivalent agreed for the web stack), external REST/JSON integration, observer/async demonstrations.

**Gate 6 — release evidence:** manual UAT, 5+ unit tests on a chosen feature, debugger screenshots, bug report, UI review, data privacy and deployment security review.

## Initial verified paths

- Schema: `database/schema.sql` — 48 base table definitions.
- Ticket issuing: `app/intake/issuance.php`.
- Repair start: `app/console/repository.php` — `repair_started_at` on first IN_PROGRESS transition.
- Staff cash search: `app/console/workspace.php`.
- Public portal security: `app/public/tracking.php`.
- Sogod prototype demo reference: `Demo-Repair-Review`; UI navigation reference: `Interface-Navigation`; maps reference: `Google-Maps-Tracking`.
- Existing automated checks: `tests/` and draft PR #59; tests are not a substitute for on-site UAT.

**This file intentionally changes no application code, schema, or runtime database.**
