# Partial-ticket repair ownership — authorization and accounting cutover audit

Reviewed: 22 November 2026, against `Partial-Ticket-Ownership` (Phase 2).
Status: **SCOPE PREPARATION COMMITTED / NO REPAIR PERMISSION CUTOVER**. This file is a security worklist, not a release approval.

## Core separation

**Do not globally replace `t.accountable_branch_id` with `d.repair_branch_id`.** These fields have different meanings:

- `repair_tickets.accountable_branch_id` remains the ticket's contract, cashier, settlement, and pickup authority.
- `ticket_devices.repair_branch_id` would be the specific device's authorized repair operator branch **only after** a separately approved security cutover.
- `ticket_devices.physical_location_id` and `custody_state` are factual custody; neither grants repair authorization.
- `user_branch_assignments` is a staff branch membership, **not** permission to view every customer, device, ticket, estimate or note at that location.
- `ticket_devices.assigned_technician_id` limits technician repair work further. Transfers must clear the former assignment atomically.

## Concrete current code paths and required decision

| Entry point | Current source of scope | Approved future behavior | Critical failure if changed carelessly |
| --- | --- | --- | --- |
| `app/console/repository.php:consoleScope`, `consoleDevices`, `consoleDevice` | Ticket accountable branch; extra assigned-tech check | Separate **device-repair scope** for device-specific queues and details; explicit scope for ticket-wide views | Cross-branch exposure of sibling devices, customer information, or private notes |
| `app/console/repository.php:consoleSave` | Locked actor and scoped device; version hash; role gate | Recheck device repair branch **inside the transaction**, keep technician assignment and stage approval checks | Stale staff can update a transferred repair |
| `app/console/repository.php:consoleTechnicians`, `admin/device.php` | Technician branch membership and ticket branch | Use the device's effective repair branch for local technician selection only after cutover | Technician assigned at the wrong shop |
| `app/quotes/repository.php:quoteDevice`, `quoteSave`, `admin/quote.php` | `consoleDevice` plus ticket branch for catalog | Authorize estimates on a specific device; customer consent and a new version for revised part/pricing | Unauthorized quote changes or silent customer price alteration |
| `app/inventory/reservations.php:stockChange` | Scoped device + ticket accountable branch + physical branch check | Require device repair branch, confirmed local custody, matching location stock and approved quote | Parts reserved or consumed from the wrong branch |
| `app/console/transfers.php:dispatchDevices`, `confirmTransferItem` | Physical branch roles and manifest receipt | Custody remains independent; all writes auditable; partial acceptance is a **new** manager-approved operation | A courier receipt grants repair authority |
| `app/console/transfers.php:acceptTransferredRepair` | Whole-ticket only; transaction/manifest and all-device checks | Retain for whole-ticket moves; fail when mixed repair ownership cannot be reconciled | Silent overwrite of a sibling's repair branch |
| `app/payments/repository.php:cashPageTicket*`, `cashIssueFinalBill`, `cashCompleteHandover` | Ticket accountable branch, all-device return to accountable location | **Remain ticket-scoped**; require all devices back and ready before one ticket bill/release | Receiving repair shop gains money/refund authority |
| `app/console/workspace.php:consoleCashDesk` | Ticket accountable branch | **Remain ticket-scoped** | Cross-branch cash visibility |
| `app/console/reports.php:loadStaffReport` | Reuses `consoleScope` for both device and money queries | Split device repair metrics from payment/ticket reporting; do not reuse device SQL in bill queries | **SQL error** (payment query has no `d` alias), leaked balance, double-counted bill |
| `app/dashboard_data.php:loadDashboardData` | Ticket accountable branch for drafts, devices and customers | Split intake, device-work, financial and customer scopes by role; avoid merging sibling rows | Mixed-branch exposure and incorrect counts |
| `app/public/tracking.php:publicRepair` | Tracking token/code + PIN and public device status | Preserve private-code authentication and safe fields; no internal notes/assigned staff in public response | Private repair information leaked to anyone tracking ticket |
| `admin/ticket.php` | Ticket accountable branch | Only designated ticket authority may manage receipt/customer tracking access | Repair staff can rotate a ticket's portal PIN |
| `app/console/ownership.php` and staff device view | Already-scoped read-only row | **Display only**. Never use the returned label, name or `review_required` flag as permission | Display state mistakenly becomes security boundary |

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

## Verification evidence — 22 November 2026

The stand-alone `app/console/scopes.php` + `tests/scope-separation.php` pair and `app/console/ownership.php` + `tests/device-ownership-view.php` pair were copied from the feature branch into an isolated temporary local PHP 8.4 CLI directory. Both PHP lint checks and both pure test scripts **passed** there. This confirms only the standalone helper contracts; it does **not** confirm MySQL query execution, migration, role-specific HTTP access, browser behavior, or release readiness.

GitHub commit status lookup returned no statuses and the available workflow-run lookup covers PR-triggered runs rather than all push-triggered jobs. **No successful GitHub Actions integration run has been verified** at the feature branch head. The PR remains draft, and `main` is unchanged.

## Dedicated MySQL integrity gate

An isolated eight-stage rehearsal is now scripted in `tests/mysql-integrity-round.sh` and `tests/mysql-integrity.php`, with its own GitHub workflow, read-only SQL companion and safety guard. It verifies referential/financial/stock/custody invariants, historical row snapshots before/after migration, negative FK and mixed-owner detection, transaction rollback, and interrupted migration recovery. This test suite **does not establish that partial-ticket authorization is enabled or safe for release**. MySQL execution remains unverified; see `docs/mysql-integrity-round.md`.

## Phase 3 gating design

1. Define **two explicit scope builders** rather than changing the old shared scope in place: a device-repair scope for approved device operations and a ticket/finance scope for billing and full-ticket records.
2. Inventory every `consoleScope` consumer before changing it. In particular, `loadStaffReport` mixes device and payment queries; it must use two separate SQL scopes.
3. Read-only scope preparation may be tested in isolation, but the cutover to real permissions and stock reservations must be atomic at the feature level, not piecemeal in production.
4. A receiving branch gets no permission merely because a device is physically received. A verified **repair ownership acceptance** event is necessary, with both manager approvals and a committed transfer manifest.
5. Ensure the receiving branch can only see the accepted device, not untransferred siblings. Ticket-level access remains with the accountable branch, and finance is always controlled by ticket scope.
6. A new ticket approval, quote revision and customer-consent policy must be settled before a moved device can be repaired using substituted parts or price changes.
7. Keep quote/part reservation changes under locked transactions and guard against two managers accepting/reassigning the same device at once.
8. No live feature flag, merge, deploy or data migration until all tests below pass on the same final commit.

## Required negative tests

- Two-device ticket: Branch A owns the contract and device 1; Branch B has been authorized for device 2. Branch B cannot read device 1's notes, ticket receipt, customer contacts or payment details, nor alter the tracking code.
- Branch B can perform only explicitly authorized device-2 actions, never ticket-wide cancellation/payment/pickup. Branch A retains cashier rights but has no repair rights over device 2 unless separately assigned.
- A courier records a physical receipt without gaining repair access; a technician assigned at the old shop loses work access immediately upon manager acceptance.
- A manager not assigned at both required branches cannot bypass origin/destination signoff.
- Wrong manifest, stale ownership value, missing custody, active hold, installed part, unresolved exception, revised quote without consent, in-progress/closed repair and issued final bill all refuse transfer without mutation.
- Two concurrent acceptances produce one audit event and one winning transition; old and new technician writes race safely.
- Public tracking continues to show only approved public fields and the last **confirmed** custody location; never imply GPS tracking.
- Device reports and cash reports produce correct figures on a mixed-branch multi-device ticket, with each bill counted once.
- Existing whole-ticket handoff, stock returns, inventory loss, payments, refunds and physical pickup remain unchanged with the partial-ownership feature disabled.

## Current review outcome

**Phases 1–2 and Phase 3 scope preparation may be reviewed. Operational Phase 3 remains blocked** pending verified integration CI, the disposable MySQL backfill rehearsal, an atomic authorization/stock cutover design, and all negative/concurrency tests. The new SQL scopes alone do not grant partial ownership. Preserve `main` untouched until those release gates are satisfied.
