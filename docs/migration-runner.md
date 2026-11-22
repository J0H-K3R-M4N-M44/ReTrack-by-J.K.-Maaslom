# Database migration runner — current operations

**Updated for the feature branch on 22 November 2026.** `tools/migrate.php` is a CLI-only updater of an *existing* configured RepairTrack database. It does **not** create a fresh schema, seed users, pull Git branches, or perform backups. Feature-branch migrations must not be applied to a live system without explicit review.

## Basic commands

~~~bash
php tools/migrate.php --help
php tools/migrate.php --status       # read-only
php tools/migrate.php --dry-run      # read-only status
php tools/migrate.php --apply        # prompts for exact configured database name
php tools/migrate.php --apply --yes  # only controlled disposable/approved maintenance
~~~

On Windows using XAMPP PHP, substitute `/c/xampp/php/php.exe` for `php`. Confirm `config/config.php` points to the database you intend to upgrade; never paste credentials into command logs.

## Supported incremental SQL steps (in runner order)

| Sequence | File | What it adds or repairs |
| --- | --- | --- |
| 1 | `database/password_reset.sql` | Hashed password-reset token table |
| 2 | `database/staff_workspace.sql` | Staff role, repair-start tracking, short public code/PIN |
| 3 | `database/payment_handover.sql` | Cash payments and handover prerequisites |
| 4 | `database/staff_activity.sql` | Activity read receipts |
| 5 | `database/bill_corrections.sql` | Correction/refund history |
| 6 | `database/stock_reservations.sql` | Catalog stock holds |
| 7 | `database/stock_quarantine.sql` | Quarantine records |
| 8 | `database/stock_approvals.sql` | Independent quarantine/count decisions |
| 9 | `database/stock_transfer_losses.sql` | Physical parts loss state and investigation |
| 10 | `database/stock_transfer_recoveries.sql` | Post-loss physical recovery |
| 11 | `database/stock_transfer_evidence.sql` | Private investigation attachments |
| 12 | `database/stock_transfer_loss_valuations.sql` | Independently approved loss exposure |
| 13 | `database/part_acquisition_cost_records.sql` | Verified supplier cost register and provenance |
| 14 | `database/inventory_stocktaking.sql` | Physical stocktake sessions and counted lines |
| 15 | `database/device_repair_branches.sql` | **Unmerged PR #55:** per-device repair branch FK/index and backfill |

`database/schema.sql` contains the fresh target structure, including the additive field only on the feature branch. The migration runner detects known pending conditions (missing tables, columns, indexes, enums, and limited data backfills). It does **not** perform an exhaustive diff against the 47-table schema.

## Required safeguards before applying

1. Confirm exact Git commit/branch and database selected in `config/config.php`; check status before doing anything.
2. Back up **relational data and private evidence folders together**; verify a restore into a disposable schema, not just a backup file's existence.
3. Pause application writes. The MySQL advisory migration lock excludes other migration runners, **not** concurrent staff requests.
4. Rehearse upgrades on populated fictional/test data and compare row counts, cash, inventory, custody and audit records.
5. Obtain explicit schema/permission approval; only then run `--apply`. Use a least-privilege service credential suitable for the operation.

DDL may **implicitly commit**, so a failed run may leave some tables/columns modified even if later SQL fails. Rerun only after diagnosing the failure; do not blindly undo a column that may have historical references.

## Failure recovery

- Capture the first SQL error and the exact `--status` report. Keep logs private and redact credentials and customer data.
- Check the affected `information_schema` tables/indexes/constraints and the source migration procedure.
- Restore a known-good backup or rehearse the incremental forward-fix on a **disposable** database.
- Recheck all integrity invariants after re-application. An invalid FK target or duplicate unique public code may need manual review; do not silently delete/edit customer records.
- Keep old whole-ticket custody, stock uses and bill histories intact. **Reverting mixed per-device ownership to one ticket branch is not a safe generic rollback.**

## Tests

- `tests/migrations.php`: status, cancellation, advisory lock, rerun, selected incomplete-constraint recovery.
- `tests/repair-branch-migration.php`: additive repair-branch backfill, FK/index recovery, timestamp preservation.
- `tests/mysql-integrity-round.sh` and `.github/workflows/mysql-integrity.yml`: eight-stage disposable MySQL rehearsal with 19-table snapshots.
- Confirmed dedicated run [44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) passed at `738bfada`, **not** a certification for future commits or production.

[Setup](local-setup.md) · [Database reference](database-reference.md) · [Verification](testing-and-verification.md).
