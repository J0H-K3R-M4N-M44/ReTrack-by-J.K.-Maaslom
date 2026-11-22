# Database reference and migration boundaries

**Current source reviewed:** `database/schema.sql` on `Partial-Ticket-Ownership`, 22 November 2026. It contains **47 CREATE TABLE IF NOT EXISTS statements**, including the feature-branch-only device repair field. The old `repairtrack_demo` fixture documented elsewhere covered **33 tables at the time of that snapshot**; it is not this schema's authoritative inventory.

## Storage and entities

The database targets MySQL/InnoDB, with foreign keys, unique indexes, explicit status enums and checks. Central relationship groups:

| Group | Key tables and concepts |
| --- | --- |
| Branch and staff | `branches`, `storage_locations`, `users`, `user_branch_assignments`, `password_reset_tokens` |
| Customer and intake | `customers`, `customer_contacts`, `customer_devices`, `repair_tickets`, `ticket_devices`, `ticket_accessory_items` |
| Intake evidence | `ticket_device_photos`, `ticket_device_acknowledgments`, `ticket_intake_gate_events`, `ticket_correction_notes` |
| Repair history | `ticket_status_events`, `ticket_operational_events`, `staff_activity_reads`, `audit_events` |
| Device custody | `device_transfers`, `device_transfer_items`, `device_custody_events` |
| Quotes and consent | `repair_quotes`, `repair_quote_items`, `repair_quote_decisions` |
| Billing and cash | `repair_bills`, `repair_bill_items`, `repair_bill_payments`, `repair_bill_changes` |
| Catalog and stock | `parts`, `branch_inventory`, `stock_reservations`, `inventory_transactions`, `ticket_parts_used` |
| Stock transfer/loss | `inventory_transfers`, `inventory_transfer_items`, `stock_transfer_loss_cases`, `stock_transfer_recoveries`, `stock_transfer_evidence` |
| Inventory decisions | `stock_quarantines`, `stock_quarantine_dispositions`, `stock_count_reviews` |
| Financial cost evidence | `part_acquisition_cost_records`, `stock_transfer_loss_valuations` |
| Physical stocktaking | `inventory_stocktakes`, `inventory_stocktake_lines` |
| Simulated notification | `mock_notification_log` (not live SMS/email delivery) |

See the actual `schema.sql` for every column, FK, check, enum and precision; this overview is not a substitute for the DDL.

## Core relationship diagram (conceptual)

~~~text
branches <- repair_tickets (intake_branch_id, accountable_branch_id)
users <-> user_branch_assignments -> branches
customers <- repair_tickets -> ticket_devices -> ticket_status_events
                           |                 -> repair_quotes -> repair_quote_items
                           |                                        -> stock_reservations
                           -> repair_bills -> repair_bill_items -> ticket_devices
                                           -> repair_bill_payments
                                           -> repair_bill_changes
ticket_devices -> device_custody_events
device_transfers -> device_transfer_items -> ticket_devices
branch_inventory <- stock_reservations; inventory_transactions record movements
~~~

This intentionally omits many linking tables for readability. The public code/PIN is not a staff authentication token.

## Device and ticket distinctions

- A ticket can hold multiple device rows; each has a `child_sequence` and issued `item_code`.
- `record_stage` is draft/issued/abandoned; `case_state` is open/closed; repair status, custody state and bill status are separate.
- `repair_tickets.accountable_branch_id` remains ticket-level billing and physical-return authority.
- On the feature branch, `ticket_devices.repair_branch_id` is nullable for drafts and initialized to the accountable/intake branch when issued. The one-time backfill sets **only missing issued devices**, preserving their `updated_at`. The new index is `idx_td_repair_branch (repair_branch_id, repair_status)` with FK `fk_td_repair_branch`.
- Existing **whole-ticket** acceptance aligns all devices with the destination repair branch and refuses any preexisting mixed/unverified assignment. This column does **not** enable partial-device transfer acceptance.
- Physical location (`physical_location_id`, `custody_state`) and technician assignment do **not** automatically grant repair or payment rights.

## Migration source of truth

Fresh installation: import `database/schema.sql` into a dedicated, correctly named local database. Existing installation: `tools/migrate.php` calls the pending checks in `tools/migration_support.php` and applies supported incremental SQL scripts. Current supported order:

1. `password_reset.sql`
2. `staff_workspace.sql`
3. `payment_handover.sql`
4. `staff_activity.sql`
5. `bill_corrections.sql`
6. `stock_reservations.sql`
7. `stock_quarantine.sql`
8. `stock_approvals.sql`
9. `stock_transfer_losses.sql`
10. `stock_transfer_recoveries.sql`
11. `stock_transfer_evidence.sql`
12. `stock_transfer_loss_valuations.sql`
13. `part_acquisition_cost_records.sql`
14. `inventory_stocktaking.sql`
15. `device_repair_branches.sql` **(feature branch only; do not migrate persistent systems yet)**

The runner detects known table/column/index/backfill markers; it is **not** a complete relational schema diff tool and does not validate every historical business event. It uses a MySQL advisory lock for concurrent migration runners, not for normal website writes.

## Integrity rules that matter

- Issued tickets have required identity/acknowledgment fields; all issued devices need ownership on the phase-1 branch, while drafts remain unassigned.
- A bill item that references a device must belong to the same ticket as its bill.
- Payment amounts are positive; cashier settlement is tied to an issued ticket bill, not the device's current repair branch.
- Stock on hand cannot be negative; stock reservations reference a branch/SKU shelf and approved quote item; catalog selling prices are **not** verified supplier purchase costs.
- Physical return and closure must agree with custody/ready/completed states; events are append-only historical evidence.
- Latest accepted branch assignment must not be derived merely from courier receipt.
- A signed physical stocktake proves reconciliation against the ledger at review time; it does not silently change shelf quantities or post a ledger expense.

## Verified, but narrowly

The feature branch's dedicated MySQL round [44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) passed on `738bfada`, exercising 17 named integrity conditions, 19-table before/after snapshots, idempotent migration, negative FK/ownership checks and interrupted recovery. This is **disposable test database evidence**, not production data validation. See [MySQL test guide](mysql-integrity-round.md) and [release status](release-status.md).
