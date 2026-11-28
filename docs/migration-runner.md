# Run database upgrades with PHP

The runner upgrades an existing RepairTrack database using your local `config/config.php`. It is command-line only; opening it in a browser returns 404. It never imports demo seeds, recreates the database, creates accounts, or pulls/switches Git branches.

## XAMPP / Git Bash

Start MySQL. Open Git Bash in the repository folder and check the database settings in `config/config.php`.

```bash
cd /c/xampp/htdocs/qwert
/c/xampp/php/php.exe tools/migrate.php
```

This shows the configured database, server version and pending steps without changing database contents. Back up through MySQL Workbench and pause application writes. Then run:

```bash
/c/xampp/php/php.exe tools/migrate.php --apply
```

Type the exact database name when prompted. If PHP is already in PATH, use `php` instead of `/c/xampp/php/php.exe`.

The runner applies the supported pending migrations in dependency order from `database/migrations/`:

1. `password_reset.sql` — password reset token storage.
2. `staff_workspace.sql` — repair start time/backfill, tracking code/PIN fields and CASHIER role.
3. `payment_handover.sql` — counter-cash payment history.
4. `staff_activity.sql` — per-user activity read receipts.
5. `bill_corrections.sql` — bill correction/refund records.
6. `stock_reservations.sql` — quote-item stock reservations.
7. `stock_quarantine.sql` — damaged/isolated stock records.
8. `stock_approvals.sql` — quarantine disposition and stock-count reviews.
9. `stock_transfer_losses.sql` — transfer loss cases and loss status values.
10. `stock_transfer_recoveries.sql` — recovered transfer stock records.
11. `stock_transfer_evidence.sql` — private evidence metadata.
12. `stock_transfer_loss_valuations.sql` — historical loss valuation records.
13. `part_acquisition_cost_records.sql` — reviewed acquisition-cost evidence and valuation link.
14. `inventory_stocktaking.sql` — monthly physical stocktake records.
15. `repair_part_requests.sql` — technician-to-manager parts requests.
16. `notifications_emailjs.sql` — customer email consent timestamp, separate staff inbox and customer/staff email outboxes.

It checks the resulting schema and reports success. Already-applied steps are skipped; manual Workbench upgrades are recognized too. No new migration-history table is required. Quote-and-Approval uses existing tables.

## Options

| Command | Behavior |
| --- | --- |
| `php tools/migrate.php` | Read-only status |
| `php tools/migrate.php --status` | Same status check |
| `php tools/migrate.php --dry-run` | Same status check; no SQL applied |
| `php tools/migrate.php --apply` | Confirm database name, then upgrade |
| `php tools/migrate.php --apply --yes` | Explicit unattended upgrade; back up first |
| `php tools/migrate.php --help` | Usage without connecting |

Status checks and successful/cancelled runs exit with code 0. Invalid options or failed upgrades exit with code 1. A status check returning 0 does not mean no migrations are pending; read its output.

## Failure recovery

MySQL DDL commits immediately. The runner does not promise all-or-nothing rollback and cannot create a backup itself. If an upgrade stops, read the error, correct the issue and rerun. Existing values are preserved; repair-start backfill fills only missing dates. The unique index can now be repaired even when its column already exists. Duplicate tracking codes must be reviewed manually; the runner does not delete or overwrite conflicting data.

Only one runner can upgrade the same database at a time using a MySQL advisory lock. That lock does not pause normal application requests. The database account needs the privileges required by the supplied scripts, including CREATE/ALTER and CREATE ROUTINE/ALTER ROUTINE/EXECUTE for the workspace procedure. A missing core schema stops the runner; fresh installations still use `database/schema.sql`.

The statement reader supports the 16 bundled migration files, including the DELIMITER commands used by the workspace migration. It is not a general SQL importer. Future migrations need an explicit pending-state check and an entry in the ordered list. Status checks verify these supported upgrade markers, not every possible schema modification.

## Regression test

`tests/migrations.php` is destructive to its disposable fixture schema and refuses database names outside `repairtrack_test_*`. It checks status-only behavior, cancelled confirmation, concurrent-run protection, upgrade, repeated execution, missing-index recovery, invalid flags and preserved account/ticket counts. Never point test configuration at your real database.
