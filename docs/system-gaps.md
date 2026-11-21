# RepairTrack: what is still missing

PHP `main` includes final billing, counter-cash payment and paid counter pickup. The remaining work below concerns PHP workflows.

## Implemented foundation

Shared login and password recovery; branch-scoped staff accounts; reviewed intake with device/problem/damage menus; draft editing; evidence-backed ticket issuance; admin summaries; manager technician assignment; assigned technician queues and private notes; limited repair-stage updates; read-only courier manifests and viewer status; status-filtered queues; private customer tracking; staff-issued/replaced access codes; separate customer updates and private technician notes.

## Work still needed, in practical order

| Priority | Missing workflow | Why it matters |
| --- | --- | --- |
| 1 | Customer self-approval, proof attachments and change orders | Staff can now prepare/revise estimates and record customer decisions. Public self-approval and changes after repair starts remain unfinished. |
| 2 | Parts requests, reservation, issue/return and stock adjustments | Existing tables and demo rows do not implement stock operations. Prevent negative stock and record who approved each change. |
| 3 | COD and cancellation | Counter-cash payment, final bill and paid counter pickup work in PHP. COD delivery, bill void/reissue UI and cancellation need their own permissions and audit rules. |
| 4 | Transfer dispatch, receiving and exceptions | Courier screens currently display manifests only. A handover must update device location, transfer items and custody history together. |
| 5 | QR convenience and richer customer actions | Private-code tracking now works. QR generation/scanning and customer approval/request actions remain. Receipt numbers alone never open a repair. |
| 6 | Customer consultation and requests | Technician/customer messages, upgrades, cancellations and approval responses need real storage, permissions and audited decisions. |
| 7 | Notifications and usable history screens | Current audit/status records are not a full notification inbox or searchable admin history. Add role/device scope, read state and grouped delivery. SMS/email repair alerts remain unimplemented. |
| 8 | Branch and store operations UI | Branch/location setup and technician skills/availability management are incomplete. |
| 9 | Trends and operational reports | Status totals and seven-day repair-start counts exist; turnaround trends, damage categories, workload history and finance reports still need definitions and reliable data. |
| 10 | Deployment and recovery verification | Validate on the target MySQL version, configure actual mail delivery/HTTPS, test backups/restores and concurrent edits, and establish retention and monitoring. Local test success is not production certification. |

## Recommended next milestone

Finish **quote → approval → repair → testing → counter cash → customer pickup** for one branch before adding more analytics. The counter-cash branch uses one new `repair_bill_payments` table because the schema had no payment history. It reuses existing bill, status, audit and custody records. COD, stock operations, customer self-approval and notification delivery remain out of scope.

Use `php tools/migrate.php --apply` on an existing database to add the payment table. Fresh schema scripts include it. Keep new tables tied to durable records the current schema cannot safely represent.

## Demo separation

Demo-System contains the standalone prototype and the expanded demo seeder. Simplified-System is the PHP application integration branch. Small original SQL fixtures may remain there for regression tests; the new presentation dataset is owned by Demo-System.
