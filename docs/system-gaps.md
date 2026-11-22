# RepairTrack: what is still missing

PHP `main` includes final billing, counter-cash payment and pickup, audited paid-bill corrections/refunds, whole-ticket cancellation checkout, and an initial branch-stock pilot. The remaining work below concerns PHP workflows.

## Implemented foundation

Shared login and password recovery; branch-scoped staff accounts; reviewed intake with device/problem/damage menus; draft editing; evidence-backed ticket issuance; admin summaries; manager technician assignment; assigned technician queues and private notes; limited repair-stage updates; read-only courier manifests and viewer status; status-filtered queues; private customer tracking; staff-issued/replaced access codes; separate customer updates and private technician notes; custody dispatch and receiving with role/branch checks; catalog-part holds, installation checks, manager-confirmed reusable-part returns, and manager-only damaged-stock quarantine and cycle-count adjustment controls.

## Work still needed, in practical order

| Priority | Missing workflow | Why it matters |
| --- | --- | --- |
| 1 | Customer self-approval, proof attachments and change orders | Staff can now prepare/revise estimates and record customer decisions. Public self-approval and changes after repair starts remain unfinished. |
| 2 | Stock disposition and cross-branch accounting | Usable-part returns, damaged shelf/installed-part quarantine and verified usable counts are implemented in this pilot. Quarantine disposition and second-person review for significant count corrections are staged in this milestone. Value-based thresholds and mixed-location ticket handling still need separate controls; dedicated physical parts transfer reconciliation is included in this milestone. |
| 3 | COD and financial reversal review | Whole-ticket cancellation with an agreed diagnostic fee, paid-bill corrections and cash refunds are implemented. COD, bill void/reissue, customer refund evidence, and cash-drawer reconciliation need further safeguards. |
| 4 | Transfers across branch repair ownership | Device dispatch/receiving and explicit whole-ticket manager acceptance are implemented for early-stage repairs. Partial-ticket ownership, courier exception recovery, customer-confirmed branch changes and destination stock procurement outside the existing part catalog need additional workflows. |
| 5 | QR convenience and richer customer actions | Private-code tracking now works. QR generation/scanning and customer approval/request actions remain. Receipt numbers alone never open a repair. |
| 6 | Customer consultation and requests | Technician/customer messages, upgrades, cancellations and approval responses need real storage, permissions and audited decisions. |
| 7 | Notifications and usable history screens | Current audit/status records are not a full notification inbox or searchable admin history. Add role/device scope, read state and grouped delivery. SMS/email repair alerts remain unimplemented. |
| 8 | Branch and store operations UI | Branch/location setup and technician skills/availability management are incomplete. |
| 9 | Diagnosis, demand and historical workload analysis | Staff reports cover 7/30/90-day repair milestones, intake-to-return time, gross counter collections and outstanding peso balances. Damage causes, upgrade demand and workload attribution at the time of reassignment need reliable structured records. |
| 10 | Deployment and recovery verification | Validate on the target MySQL version, configure actual mail delivery/HTTPS, test backups/restores and concurrent edits, and establish retention and monitoring. Local test success is not production certification. |

## Recommended next milestone

Consolidate **branch-stock custody and accounting correctness** before introducing automated messaging. After independently approved quarantine disposition, count corrections, physical parts transfers and formal shortage closure, address secure evidence uploads, valuation-based financial loss review, stocktaking sign-off and partial-ticket ownership. Preserve existing bills, original installation movements and custody records. Public customer self-approval, COD and live notification delivery remain unfinished.

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

Quarantines are held until a separate assigned manager approves inspected release or permanent scrap. A released item returns only to usable branch stock, never automatically to a device. A scrapped item remains outside usable stock. Partial dispositions, unauthorized resale, damaged-part shipping, cash refunds and post-billing repair reversals remain out of scope. To upgrade an existing database, back up and rehearse locally before running `php tools/migrate.php --apply`; the added table is `database/stock_quarantine.sql`.

## Quarantine disposition and count review (dual control)

An active ADMIN/MANAGER assigned to the branch may approve **RELEASE** or **SCRAP** for a complete quarantined quantity. The approving user must be different from the person who recorded the quarantine. Both decisions require confirmation and a 10–500 character reason; a unique database key prevents duplicate dispositions. A release creates one positive usable-stock ADJUSTMENT, without reactivating any historical device installation. Scrap adds no stock because the quarantine had already isolated the item from usable inventory.

A verified physical count difference of at least **5.000 units**, or smaller differences which together with absolute count adjustments in the same branch/SKU within 24 hours reach 5.000 units, creates a pending review **without adjusting inventory**. A different authorized manager approves or rejects it. The original requester can cancel but cannot approve or reject. Approval re-locks the shelf and confirms the on-hand quantity, reserved quantity and latest movement are unchanged before posting exactly one adjustment. Rejected or cancelled requests stay in review history and do not change stock.

The five-unit rule is a pilot quantity threshold, **not** a high-value financial limit. Expensive one-unit parts require additional price-aware authorization in future work. Physical inspection and evidence retention remain operational obligations. Back up and rehearse `database/stock_approvals.sql` using a disposable database before any live upgrade.

## Physical parts transfers and receiving (pilot)

Parts transfers are **not** device custody transfers. An assigned active ADMIN/MANAGER may dispatch a manifest containing 1–20 distinct active catalog SKUs, with each positive quantity specified to three decimals. Source shelf stock is locked and debited only if the entire shipment fits within **unreserved usable stock**. Dispatch creates a durable transfer header, part lines, negative TRANSFER_OUT movements, shipment note and audit event. Previously quarantined parts never count as usable stock.

A different active ADMIN/MANAGER assigned to the destination branch records each **physical receipt**. Each receipt posts only the newly inspected quantity to the receiving branch's usable inventory, along with a positive TRANSFER_IN ledger entry. An item may be partially received, and additional deliveries may be recorded later. The system rejects duplicate/stale quantities, receiving over the dispatched amount, receipt by the dispatching manager, unauthorized branch or role access, and missing confirmation. Partial quantities and reported missing packages remain `EXCEPTION` until physically completed. A missing report **never** credits the destination stock. Once all items are fully received, the manifest becomes `RECEIVED`.

Items remain traceable through their transfer item ID and original movement history; signed per-branch stock movements reconcile with total dispatched and physically received amounts. Existing transfer tables are used, **so this milestone adds no migration**. Formal shortage investigation and independent operational-loss approval are provided in this milestone. There is still no automatic financial write-off, compensation, post-approval recovered-stock flow, proof-of-delivery file upload, refund, or cancellation of an already dispatched manifest. These controls do not replace physical handover paperwork or recipient inspection.

## Formal missing-part loss investigation (pilot)

An **EXCEPTION** transfer item may be investigated as a formal loss **only after** the receiving manager has recorded a `PARTS_MISSING` event. The receiving branch's assigned ADMIN/MANAGER must document the physically unreceived quantity, a 20–500 character investigation, an 8–160 character reference to its *privately retained* incident/inspection evidence, and an explicit confirmation. The reference does not upload an attachment. Pending review freezes new receipts for that item so quantities cannot silently change during the decision.

A **different** ADMIN/MANAGER assigned to the **sending** branch, who is **neither the reporter nor the dispatch initiator**, must approve or reject the loss with an explanation and confirmation. Rejected cases remain in immutable history; a corrected case may be submitted and late-arriving physical parts may still be received. Approved cases mark only the unreceived remainder as `LOSS_CLOSED`; received units keep their original `TRANSFER_IN` evidence. When all manifest lines are either physically received or approved losses, the header becomes `LOSS_CLOSED` but its `received_at` remains empty: the manifest was operationally closed, not fully physically received. The loss-case review timestamp records that decision. Duplicate approvals, unauthorized submissions, excess quantities and late ordinary receipts after final approval are refused.

**Operational closure is not a new stock transaction or a financial write-off.** Source stock was already debited by `TRANSFER_OUT` at dispatch, and missing stock was never credited to the destination; approving a case must not double-debit, backfill or erase either balance. The review and original missing event remain linked in `stock_transfer_loss_cases`, with origin/destination scope, decisions and timestamps preserved. Ordinary late receipts after loss approval remain prohibited; a separate **independently approved recovery** workflow now records physically recovered parts and their new stock movements. Cost valuation, reimbursement/insurance, private file evidence storage and retention controls remain separate work.

**Database upgrade:** this milestone adds `database/stock_transfer_losses.sql` for one case-history table and `LOSS_CLOSED` values in two existing transfer-status enums. Rehearse an incremental migration and restoration against disposable MySQL before upgrading real records. The live preview or production database must not be silently changed.

## Post-approved-loss physical parts recovery (pilot)

The **original approved loss remains immutable** and continues to identify the missing-parts report, original quantity, approval and case evidence. The destination branch's active ADMIN/MANAGER can report that some or all of those parts were subsequently physically found. They must count the recovered quantity, retain an 8–160 character reference to privately held inspection evidence, describe condition and secure holding location (20–500 characters), and confirm the parts are kept **outside usable inventory while pending review**. Quantity may never exceed the unrecovered approved loss; multiple approved partial recoveries are supported sequentially. Duplicate pending submissions and stale receipts are rejected.

A **different assigned ADMIN/MANAGER at the source branch**, who neither dispatched the shipment nor submitted the original loss or recovery, must independently approve or reject each recovery. Rejection keeps the original stock balances unchanged, records a decision and permits a corrected report. Approval, under transfer/stock locks, adds only that recovered quantity to usable stock at the **destination** through one distinct positive `TRANSFER_IN` and increases the physically received quantity on the original transfer line. The old `TRANSFER_OUT`, original `stock_transfer_loss_cases` approval and historical audit events remain intact. For partial recovery, the item and fully accounted-for manifest remain `LOSS_CLOSED`, with the unreceived portion still excluded from stock. After all lost pieces are physically accounted for, item and manifest may return to `RECEIVED`, and a genuine full-physical-receipt timestamp is recorded.

`stock_transfer_recoveries` is a new append-only decision record with a unique pending request per case and a unique linked inventory movement for each approved recovery. The rollout does **not** implement a file-upload evidence repository, valuation, insurance claim, reimbursement, or financial write-off. The source/destination holding inspection is a physical responsibility: a written reference is not itself proof that a recovered part is safe for resale. Broken or unsafe items must not be approved for usable stock. Rehearse `database/stock_transfer_recoveries.sql` against disposable MySQL and restore test data before applying it to any real deployment.
