# Partial-ticket repair ownership: gated implementation plan

Status: PHASES 1–2 IMPLEMENTED; PHASE 3 SCOPE PREPARATION ONLY — 22 November 2026. Additive schema, issuance, whole-ticket compatibility, migration tests, and an administrator/manager-only read-only ownership display committed. **Not deployed, not merged, and no partial-ticket acceptance permitted.** Database/CI integration results remain unverified.
Base: main @ f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b.
Scope: allow one device in a multi-device ticket to be repaired by another branch **after a confirmed physical transfer and independently audited acceptance**, without moving the financial ownership of the whole ticket.

## Dedicated MySQL integrity rehearsal (scripts added, execution pending)

- `tests/mysql-integrity.php` checks 17 named relational, payment, inventory, custody, and device-branch invariants, verifies repair-branch index/FK metadata, writes no private data to output, and supports rollback-only negative tests with explicit opt-in.
- `tests/mysql-integrity-round.sh` creates a randomized disposable database and temporary config in a clean checkout, runs real workflow fixtures, snapshots 19 historical tables, removes and reapplies the additive column, tests rerun idempotence and rollback, then executes interrupted-migration recovery and the final SQL audit.
- `tests/sql/repair_branch_integrity.sql` provides a read-only Workbench view. `.github/workflows/mysql-integrity.yml` runs the dedicated round on the feature branch and PRs.
- `tests/repair-scope-isolation.php` now rolls back all synthetic mixed-ownership rows before subsequent integrity checks.
- Local staged PHP/Bash syntax checks passed. **No MySQL server or PDO MySQL extension is available in this execution environment; the database tests and GitHub Actions result have not been observed as passing.** Remain draft/unmerged. See `docs/mysql-integrity-round.md`.

## Current verified behavior

- A repair ticket has one accountable_branch_id in repair_tickets. Physical device location and custody are already separate in ticket_devices.
- app/console/repository.php scopes staff device access through the ticket's accountable branch. Quotes, stock reservations, reports, dashboard and payment paths also use ticket-level accountability.
- app/console/transfers.php supports physical dispatch/receipt and whole-ticket destination acceptance. Custody receipt alone never grants repair authority.
- Current acceptance moves accountable_branch_id for the entire ticket and clears the ticket's technician assignments. tests/stock-pilot.php confirms that partial manifests cannot be accepted as whole-ticket repair handoffs.
- app/payments/repository.php issues and settles ticket-level bills at the accountable branch and checks physical return constraints. This policy must remain intact during the pilot.
- No repair_branch_id field or partial ownership feature exists on main.

## Phase 1 implementation and verification checkpoint

Feature branch: `Partial-Ticket-Ownership`. `main` is unchanged.

- `database/schema.sql` and rerunnable `database/device_repair_branches.sql`: indexed, FK-backed, nullable `ticket_devices.repair_branch_id`, backfilled only where missing on issued tickets; drafts remain unassigned. Historical `updated_at` is preserved.
- `app/intake/issuance.php`: issue every device with repair branch equal to the ticket's intake/accountable branch.
- `app/console/transfers.php`: legacy whole-ticket acceptance continues to clear technician assignments and now also synchronizes every accepted device's repair branch.
- `tools/simulation_data.php`: use the new column in generated fixtures.
- `tools/migration_support.php` / `tools/migrate.php`: detect missing column, index, FK or interrupted backfill; require an explicit operator-run migration and backups.
- `tests/repair-branch-migration.php`, `tests/stock-pilot.php`, `tests/ticket-issuance.php`: regression checks for interrupted/partial migrations and device branch alignment, including multi-device issuance and whole-ticket acceptance.
- `tools/check_repair_branch_integrity.php`: **read-only** readiness gate, reporting only aggregated counts; refuses missing or divergent issued repair branches in Phase 1.
- `.github/workflows/payment-pickup.yml`: enables the isolated branch, lints changed PHP and rehearses migration on an existing disposable test fixture, including a missing-column upgrade and repeat migration.

**Required verification before progressing:** observe a successful GitHub Actions run for the final branch HEAD (source review alone is not proof), or reproduce the entire workflow on a disposable MySQL installation. Run `php tools/migrate.php --status` before `--apply`, with backup and application writes paused. Verify `php tools/check_repair_branch_integrity.php` after migration. No live database was migrated here; no tests were executed in the local container because MySQL/PDO MySQL are unavailable.

The added column is nullable during the transitional phase because older fixture inserts and pre-issue drafts must remain compatible. Do **not** enable device-level authorization from its value yet. A future permission cutover will add strict invariants and validation for issued rows before activating mixed ownership.

## Phase 2: read-only ownership display

- `app/console/repository.php` fetches the repair branch's name with a **LEFT JOIN after the existing ticket-based scope**. The existing `consoleScope()` and `consoleSave()` authorization logic are unchanged.
- `app/console/ownership.php` derives display-only labels for an already-authorized device row. It is not a permission resolver and must not be called on unscoped rows.
- `admin/device.php` passes those details to `views/console/device.php`, where **only ADMIN/MANAGER** see ticket/billing and repair branches; missing or unexpectedly different assignments show a review warning, not an approval.
- `tests/device-ownership-view.php` covers aligned, missing and divergent branches, plus refusal to leak the ticket identifier in labels. The existing test workflow lints these files and runs the pure helper tests.
- No extra customer fields, device list rows, public portal changes, staff data scopes, cash privileges, or ownership write actions were introduced.

**Required before Phase 3:** confirm CI/check runs, rehearse the MySQL schema, review all query scope/authorization entry points, then implement device-level permissions and stock-branch rules together with exhaustive negative tests. The existing UI must not be interpreted as enabling mixed-branch repair operations.

## Phase 3 scope preparation — no operational cutover

- `app/console/scopes.php` now exposes three distinct, fixed-alias SQL builders: `consoleCurrentDeviceScope` (original ticket/technician behavior), `consoleProposedRepairScope` (uninvoked future device branch operation), and `consoleTicketFinanceScope` (ticket accountable branch only).
- `app/console/repository.php:consoleScope` delegates to **unchanged legacy logic**, so existing repair consoles still do not authorize cross-branch device work.
- `app/console/reports.php:loadStaffReport` calls the explicit **ticket-finance scope for CASHIER**, while current device reports stay legacy-scoped. The cash scope checks active users and branches and never references a device alias.
- `app/console/transfers.php:acceptTransferredRepair` now rejects a whole-ticket handoff if *any* device has a missing or different repair branch than the expected source. This prevents overwriting future independently owned siblings.
- `tests/scope-separation.php` asserts the scope contract and rejects non-financial/non-repair roles without needing a database.
- `tests/repair-scope-isolation.php`, run by `php tests/payment-pickup.php --scope-isolation` **only on disposable MySQL**, simulates one two-device ticket: source keeps ticket finance, proposed receiving repair visibility is restricted to the receiving device, and old live device access remains denied to receiving staff.
- `tests/stock-pilot.php` now explicitly initializes direct-insert fixture branch ownership and tests mixed-ownership rejection before normal whole-ticket acceptance.
- GitHub Actions lints the new modules, runs the pure scope test, and adds the disposable-MySQL two-device isolation test.

**Release gate remains BLOCKED:** no run result has been confirmed for the final HEAD, and the proposed repair builder is intentionally **not wired to HTTP handlers**. No partial-ticket acceptance service, role permissions change, quote/stock branch cutover, migration on persistent databases, or merge into `main` has occurred.

## Earlier branch reuse decision

- Simplified-System, Staff-Workspace, Device-Handovers, Branches-Logistics and Repair-Operations: fully behind main; do not merge.
- PHP-Bill-Corrections-Integration and PHP-Stock-Cancellation-Integration: fully behind main; retain main's integrated and tested workflows.
- Branch-Repair-Acceptance: diverged (10 commits ahead, 11 behind as inspected); core acceptance function and stock-pilot tests are already on main. Do not wholesale merge or replay old schema.
- Interface-Navigation: diverged (1 ahead, 85 behind). Keep its accessibility/navigation tests and layout ideas as reference for a separate interface milestone; reimplement against current main if needed.
- Google-Maps-Tracking: diverged (6 ahead, 85 behind). Demo contains documented branch-map and simulated alert behaviors, not a production PHP custody/GPS feature. Reuse concepts only after privacy, location accuracy and current-branch integration review.
- PHP-Bill-Corrections and PHP-Stock-Pilot: old divergent prototypes, superseded by integrated billing/stock paths on main. Avoid cherry-picking historical payment or stock logic.

Comparisons were made against main on the date above. Re-check heads before any later cherry-pick.

## Proposed ownership model (no changes committed yet)

1. Keep repair_tickets.accountable_branch_id as the **ticket-level cashier, customer contract and billing branch** for mixed-location tickets. It must not silently follow a partial custody movement.
2. Introduce ticket_devices.repair_branch_id as the device-level authorized repair branch; nullable only for pre-issue drafts, populated from the ticket's accountable branch at issuance and backfilled for existing issued devices in a controlled migration.
3. Keep ticket_devices.physical_location_id and custody_state as facts about physical custody. Their values must never independently change repair_branch_id.
4. Existing whole-ticket acceptance will, once the new model is active, move both the ticket-level branch and each device's repair branch together; no retroactive rewrite of original custody, stock or audit events.
5. Partial acceptance will change **only the selected device's repair_branch_id** and remove its former technician assignment, leaving sibling devices, ticket-level billing authority, intake branch and original financial records unchanged.
6. For a first narrow pilot, restrict acceptance to an issued/open/unbilled device, in an early repair stage, physically received at the destination on an identified manifest, with no unresolved transfer exception, no active reservation, no active installation or unresolved branch-stock obligation. If an approved tracked part would require new destination pricing, refuse acceptance pending a new customer-approved quote workflow. Do not silently substitute inventory or amounts.
7. Require explicit sending-branch manager approval and receiving-branch ADMIN/MANAGER confirmation, with reason (10–500 characters), manifest/device/ticket identifiers, expected branch and a stale-version guard. A courier may handle physical custody but may not accept repair ownership.
8. All changes must be atomic, with a single immutable audit event containing the before/after branch, manifest ID, the actors and reason. Do not embed customer contact details in audit JSON.

## Permission boundaries to update together

- Device list/detail, technician assignment, stage changes and private notes: authorize against device repair_branch_id and an active user assignment. A technician may work only on a specifically assigned device and never on a sibling solely because it shares a ticket.
- Quote preparation, approval recording, catalog selection, holds, installation and returns: choose the **device repair branch** for operational stock, but preserve original quote and inventory movements. Re-approval is mandatory when price/part choice changes.
- Whole-ticket cashier payment, cancellation, pickup and bill corrections: continue to authorize against repair_tickets.accountable_branch_id. The current billing gate requiring devices to return to the accountable branch stays in force; no cross-branch payout or COD behavior is added.
- Ticket-wide pages, dashboards, searches, reports, notifications and public portal: audit each query for unintended sibling data disclosure. Public tracking may show a safe per-device last-confirmed branch and state but not an invented live courier position or internal notes.
- Whole-ticket transfers: preserve existing behavior; do not permit mixed ownership to use the legacy all-ticket acceptance route without an explicit reconciliation check.

## Safe implementation sequence

Phase 0 (this document): current-code inventory, branch reuse decision and security invariants. No application writes.

Phase 1 (additive, inert): add indexed FK-backed repair_branch_id to the fresh schema and a repeatable existing-database migration, safely backfill issued devices, make intake issuance populate the field, and add schema/backfill/rollback-readiness tests. Keep every authorization check on the old model until verified.

Phase 2 (read-only): add a shared device-branch resolver and read-only ownership indicators; test against existing single-branch and whole-ticket flows without exposing unauthorized sibling details. No partial acceptance action yet.

Phase 3 (permission cutover): move device-scoped authorization, assignment, quotes and stock as a reviewed unit. Keep payments strictly ticket-scoped. Add a feature flag defaulting to OFF; test every role's denial and allowed paths on disposable MySQL.

Phase 4 (controlled write): implement origin approval, destination acceptance, optimistic version matching, ordered transactional locks (compatible with the existing manifest -> ticket -> devices order), audit history and a guarded UI. Start with a single early-stage device with no outstanding part or billing obligations.

Phase 5 (release): run the full payment/pickup, stock, handover, console, tracking and migration suites; run two-operator concurrency checks; complete role-by-role Chrome/mobile review on 320/390/768/1440 widths; test feature OFF; review production migration and backups. Merge only after passing CI and independent review.

## Required negative and concurrency tests

- Outsider branch, cashier, technician and courier attempts to approve or accept repair ownership are rejected; no DB mutations.
- Partial manifest in transit, missing item, exception, wrong receiving branch, wrong device, wrong original branch, stale version or reused acceptance are rejected.
- Two simultaneous acceptances of the same device yield one winner and one clean refusal; no double audit or broken ticket state.
- Mixed-branch siblings remain invisible to unauthorized repair staff, including through ticket search, direct URL and private notes.
- Technician assignment capacity and activity history remain correct after transfer; old assignment cannot update progress.
- Approved tracked part, active hold, installed part, already billed, active repair, ready-for-pickup and completed cases cannot be reassigned by the pilot.
- Quotes and stock movements never migrate implicitly between shelves; original returns and transactions are preserved.
- Cashier at the ticket's accountable branch retains authorized billing rights; destination repair staff acquire no cash privileges. Final bill and pickup refuse until the existing physical-return conditions are satisfied.
- Existing whole-ticket acceptance, billing, refund, stocktaking, part loss and physical custody tests still pass with the feature disabled and enabled.
- Re-running the migration is idempotent and preserves row counts, historical events, receipt totals and stock quantities.

## Release safeguards and unresolved policy questions

- Current .github/workflows/payment-pickup.yml only triggers on named branches and selected paths. Add this development branch and all new migration/test paths when implementation starts; documentation-only commits do not constitute test verification.
- Existing deployment must not run migrations without a backup, version-checked dry run, disposable-database rehearsal and an explicit rollback/forward-fix procedure. A schema downgrade after mixed branch ownership has been used cannot safely collapse multiple device branches back into one ticket branch.
- Pending business policy: whether a customer must explicitly consent to a branch change, how quote revisions are approved, who pays transfer costs, and how disputes or courier exceptions are handled. Until decided, do not enable live partial ownership or claim location is real-time.
- Production status: NOT DEPLOYMENT READY. Use fictional records and non-production databases until all release gates pass.
