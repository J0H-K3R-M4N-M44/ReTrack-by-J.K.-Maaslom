# RepairTrack: what is still missing

PHP `main` includes final billing, counter-cash payment and pickup, audited paid-bill corrections/refunds, whole-ticket cancellation checkout, and an initial branch-stock pilot. The remaining work below concerns PHP workflows.

## Implemented foundation

Shared login and password recovery; branch-scoped staff accounts; reviewed intake with device/problem/damage menus; draft editing; evidence-backed ticket issuance; admin summaries; manager technician assignment; assigned technician queues and private notes; limited repair-stage updates; read-only courier manifests and viewer status; status-filtered queues; private customer tracking; staff-issued/replaced access codes; separate customer updates and private technician notes; custody dispatch and receiving with role/branch checks; catalog-part holds, installation checks, manager-confirmed reusable-part returns, and manager-only damaged-stock quarantine and cycle-count adjustment controls.

## Work still needed, in practical order

| Priority | Missing workflow | Why it matters |
| --- | --- | --- |
| 1 | Customer self-approval, proof attachments and change orders | Staff can now prepare/revise estimates and record customer decisions. Public self-approval and changes after repair starts remain unfinished. |
| 2 | Stock disposition and cross-branch accounting | Usable-part returns, damaged shelf/installed-part quarantine and verified usable counts are implemented in this pilot. Quarantine disposition (scrap/release), second-person approval for large adjustments, mixed-location tickets and physical parts-transfer reconciliation still need dedicated workflows. |
| 3 | COD and financial reversal review | Whole-ticket cancellation with an agreed diagnostic fee, paid-bill corrections and cash refunds are implemented. COD, bill void/reissue, customer refund evidence, and cash-drawer reconciliation need further safeguards. |
| 4 | Transfers across branch repair ownership | Device dispatch/receiving and explicit whole-ticket manager acceptance are implemented for early-stage repairs. Partial-ticket ownership, courier exception recovery, customer-confirmed branch changes and destination stock procurement outside the existing part catalog need additional workflows. |
| 5 | QR convenience and richer customer actions | Private-code tracking now works. QR generation/scanning and customer approval/request actions remain. Receipt numbers alone never open a repair. |
| 6 | Customer consultation and requests | Technician/customer messages, upgrades, cancellations and approval responses need real storage, permissions and audited decisions. |
| 7 | Notifications and usable history screens | Current audit/status records are not a full notification inbox or searchable admin history. Add role/device scope, read state and grouped delivery. SMS/email repair alerts remain unimplemented. |
| 8 | Branch and store operations UI | Branch/location setup and technician skills/availability management are incomplete. |
| 9 | Diagnosis, demand and historical workload analysis | Staff reports cover 7/30/90-day repair milestones, intake-to-return time, gross counter collections and outstanding peso balances. Damage causes, upgrade demand and workload attribution at the time of reassignment need reliable structured records. |
| 10 | Deployment and recovery verification | Validate on the target MySQL version, configure actual mail delivery/HTTPS, test backups/restores and concurrent edits, and establish retention and monitoring. Local test success is not production certification. |

## Recommended next milestone

Consolidate **branch-stock custody and accounting correctness** before introducing automated messaging. Next address quarantined-part disposition, second-person inventory adjustment approval, partial-ticket ownership limits, and independent stock-transfer reconciliation. Preserve existing bills, original installation movements and custody records. Public customer self-approval, COD and live notification delivery remain unfinished.

Use `php tools/migrate.php --apply` only after backups and a disposable migration rehearsal. Existing databases may need the bill-corrections, staff-activity, stock-reservations, and stock-quarantine migrations; fresh schema scripts contain these tables. No migration or deployment has been performed in production.

## Demo separation

Demo-System contains the standalone prototype and the expanded demo seeder. Simplified-System is the PHP application integration branch. Small original SQL fixtures may remain there for regression tests; the new presentation dataset is owned by Demo-System.

## Reusable-part return policy (pilot)

This capability is limited to a **physically removed, inspected and reusable** catalog part on an open ticket, still in repair and before a final bill. Only ADMIN/MANAGER can return it, from the device's accountable branch. A 10–500 character reason and explicit physical-restock confirmation are mandatory. The original TICKET_USE transaction is retained; a distinct positive RETURN movement references the original movement ID and records the operator and reason. Reinstallation requires a new reservation and fresh TICKET_USE record; testing, billing and transfer are refused while no active installation exists.

This is **not** a process for damaged parts, quarantined parts, exchanges after billing, discounts or refund settlements. There is no automatic return to the shelf for an unverified or unsellable part. Physical verification and real cash policies still require operational controls.

## Destination branch repair acceptance (pilot)

Physical transfer/receipt does **not** silently change repair responsibility. For a whole-ticket handoff, the sending branch must release every unused source-branch reservation; an approved but uninstalled catalog part may travel only when **every device on that ticket is dispatched in the same manifest**. After the entire manifest is received and every ticket device is physically at its destination, an assigned destination ADMIN/MANAGER must explicitly accept the ticket, confirm responsibility and provide a 10–500 character reason. Only open, unbilled early-stage repairs qualify.

Acceptance preserves the intake branch, original estimates, movement and custody events. It updates the ticket's accountable branch, clears former technician assignments, and creates an audit event with original branch and assignments. The receiving branch can then assign a local technician and reserve an approved catalog part from its **own** inventory. It does not silently move physical inventory, issue a new quote or notify the customer.

Tickets partially transferred, already billed, actively being repaired, in unresolved custody exception, or with active part reservations cannot be accepted by this workflow. A separate workflow would be required for partial-ticket work ownership, disputed returns, price changes, and audited inter-branch parts transfers.

## Damaged stock quarantine and verified cycle counts (pilot)

The inventory page is available only to active ADMIN/MANAGER staff assigned to the selected active branch. Shelf quarantine requires a physical damage description, a positive quantity (up to three decimal places), explicit separation confirmation, an unchanged usable-shelf snapshot and a current inventory movement watermark. Existing repair reservations remain protected: usable on-hand cannot be reduced below already-held stock. The action writes a negative ADJUSTMENT movement and a `stock_quarantines` record tied to it; the removed stock is never automatically made available again.

A verified shelf count records the **usable on-hand** quantity, excluding quarantined parts. Positive or negative differences use an immutable ADJUSTMENT ledger entry, before/after audit evidence and the same branch, reason, confirmation, reservation-floor and stale-request validation. An unchanged count is rejected to avoid fake movements. Count corrections here are manager-recorded; separate independent approval thresholds and periodic count sign-off remain future work.

A damaged part physically removed from an in-progress repair may be quarantined by the accountable-branch ADMIN/MANAGER before billing, with a reason and explicit isolation confirmation. Its original issue history remains, but the quarantined installation is no longer considered active; no positive usable-stock movement is created because the issue already reduced on-hand. Replacement requires another reservation and installation before testing/billing. Duplicate quarantine, technician attempts, and bill-stage changes are refused.

Quarantine records are intentionally **held** until a dedicated disposal or inspected-release decision is implemented. This pilot does not support unauthorized resale, automatic write-off reconciliation, cross-branch damaged-part shipping, cash refunds or post-billing reversals. To upgrade an existing database, back up and rehearse locally before running `php tools/migrate.php --apply`; the added table is `database/stock_quarantine.sql`.
