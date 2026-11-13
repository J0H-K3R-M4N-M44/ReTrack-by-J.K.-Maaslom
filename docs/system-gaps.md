# RepairTrack: what is still missing

Reviewed against the current PHP implementation on 2026-11-13. Prototype buttons and seeded records do not count as implemented features.

## Implemented foundation

Shared login and password recovery; branch-scoped staff accounts; reviewed intake with device/problem/damage menus; draft editing; evidence-backed ticket issuance; admin summaries; manager technician assignment; assigned technician queues and private notes; limited repair-stage updates; read-only courier manifests and viewer status.

## Work still needed, in practical order

| Priority | Missing workflow | Why it matters |
| --- | --- | --- |
| 1 | Quote preparation, revisions and customer approval UI | Technicians cannot start repair without an approved latest quote. Demo SQL currently provides approvals; normal staff need a real workflow. |
| 2 | Parts requests, reservation, issue/return and stock adjustments | Existing tables and demo rows do not implement stock operations. Prevent negative stock and record who approved each change. |
| 3 | Cashier/COD records, billing and final handover | Cashier is not an existing user role. Decide whether a manager handles this first. Completion/cancellation must synchronize payment rules, device custody and ticket closure. |
| 4 | Transfer dispatch, receiving and exceptions | Courier screens currently display manifests only. A handover must update device location, transfer items and custody history together. |
| 5 | Public code/QR tracking | Issued codes identify records, but there is no working customer tracking page. Expose only approved customer-facing data, never internal notes or contact lists. |
| 6 | Customer consultation and requests | Technician/customer messages, upgrades, cancellations and approval responses need real storage, permissions and audited decisions. |
| 7 | Notifications and usable history screens | Current audit/status records are not a full notification inbox or searchable admin history. Add role/device scope, read state and grouped delivery. SMS/email repair alerts remain unimplemented. |
| 8 | Branch and store operations UI | Branch/location setup and technician skills/availability management are incomplete. |
| 9 | Trends and operational reports | Status totals exist; turnaround trends, damage categories, workload history and finance reports still need definitions and reliable data. |
| 10 | Deployment and recovery verification | Validate on the target MySQL version, configure actual mail delivery/HTTPS, test backups/restores and concurrent edits, and establish retention and monitoring. Local test success is not production certification. |

## Recommended next milestone

Finish **quote → approval → repair → testing → customer handover** for one branch before adding more analytics or tables. Reuse the existing quotes, decisions, bills, status and custody tables. Keep payment limited to cashier/COD as planned.

No new schema was introduced for staff consoles or the new seeder. Do not add tables simply because an unfinished menu needs a screen.

## Demo separation

Demo-System contains the standalone prototype and the expanded demo seeder. Simplified-System is the PHP application integration branch. Small original SQL fixtures may remain there for regression tests; the new presentation dataset is owned by Demo-System. Main remains unchanged.
