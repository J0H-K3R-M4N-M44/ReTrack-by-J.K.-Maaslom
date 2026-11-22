# RepairTrack: what is still missing

PHP `main` includes final billing, counter-cash payment and pickup, audited paid-bill corrections/refunds, whole-ticket cancellation checkout, and an initial branch-stock pilot. The remaining work below concerns PHP workflows.

## Implemented foundation

Shared login and password recovery; branch-scoped staff accounts; reviewed intake with device/problem/damage menus; draft editing; evidence-backed ticket issuance; admin summaries; manager technician assignment; assigned technician queues and private notes; limited repair-stage updates; read-only courier manifests and viewer status; status-filtered queues; private customer tracking; staff-issued/replaced access codes; separate customer updates and private technician notes; custody dispatch and receiving with role/branch checks; catalog-part holds, installation checks, and manager-confirmed returns of physically inspected reusable parts before testing and billing.

## Work still needed, in practical order

| Priority | Missing workflow | Why it matters |
| --- | --- | --- |
| 1 | Customer self-approval, proof attachments and change orders | Staff can now prepare/revise estimates and record customer decisions. Public self-approval and changes after repair starts remain unfinished. |
| 2 | Stock quarantine, adjustments, and cross-branch use | A manager can now reverse an installation into the original shelf if the part was physically removed and confirmed reusable. Damaged/unsellable returns, verified cycle counts, adjustments, and destination-branch reservations still need controls. Dispatch blocks uninstalled approved catalog parts. |
| 3 | COD and financial reversal review | Whole-ticket cancellation with an agreed diagnostic fee, paid-bill corrections and cash refunds are implemented. COD, bill void/reissue, customer refund evidence, and cash-drawer reconciliation need further safeguards. |
| 4 | Transfers across branch repair ownership | Dispatch, receiving and custody events are implemented. Cross-branch repair reassignment, destination-branch stock procurement and all transfer exception paths need end-to-end validation. |
| 5 | QR convenience and richer customer actions | Private-code tracking now works. QR generation/scanning and customer approval/request actions remain. Receipt numbers alone never open a repair. |
| 6 | Customer consultation and requests | Technician/customer messages, upgrades, cancellations and approval responses need real storage, permissions and audited decisions. |
| 7 | Notifications and usable history screens | Current audit/status records are not a full notification inbox or searchable admin history. Add role/device scope, read state and grouped delivery. SMS/email repair alerts remain unimplemented. |
| 8 | Branch and store operations UI | Branch/location setup and technician skills/availability management are incomplete. |
| 9 | Diagnosis, demand and historical workload analysis | Staff reports cover 7/30/90-day repair milestones, intake-to-return time, gross counter collections and outstanding peso balances. Damage causes, upgrade demand and workload attribution at the time of reassignment need reliable structured records. |
| 10 | Deployment and recovery verification | Validate on the target MySQL version, configure actual mail delivery/HTTPS, test backups/restores and concurrent edits, and establish retention and monitoring. Local test success is not production certification. |

## Recommended next milestone

Consolidate **branch-stock custody and accounting correctness** before introducing automated messaging. Next address damaged-part quarantine and cycle-count adjustments; then add explicit repair responsibility and inventory treatment after a cross-branch receipt. Preserve existing bills, original installation movements and custody records. Public customer self-approval, COD and live notification delivery remain unfinished.

Use `php tools/migrate.php --apply` only after backups and a disposable migration rehearsal. Existing databases may need the bill-corrections, staff-activity and stock-reservations migrations; fresh schema scripts contain these tables. No migration or deployment has been performed in production.

## Demo separation

Demo-System contains the standalone prototype and the expanded demo seeder. Simplified-System is the PHP application integration branch. Small original SQL fixtures may remain there for regression tests; the new presentation dataset is owned by Demo-System.

## Reusable-part return policy (pilot)

This capability is limited to a **physically removed, inspected and reusable** catalog part on an open ticket, still in repair and before a final bill. Only ADMIN/MANAGER can return it, from the device's accountable branch. A 10–500 character reason and explicit physical-restock confirmation are mandatory. The original TICKET_USE transaction is retained; a distinct positive RETURN movement references the original movement ID and records the operator and reason. Reinstallation requires a new reservation and fresh TICKET_USE record; testing, billing and transfer are refused while no active installation exists.

This is **not** a process for damaged parts, quarantined parts, exchanges after billing, discounts or refund settlements. There is no automatic return to the shelf for an unverified or unsellable part. Physical verification and real cash policies still require operational controls.
