# SQL file guide

This directory contains the fresh-install schema and the incremental upgrade scripts.
Test-only SQL is kept separately under `tests/fixtures/demo/`.

## Fresh installation

- `schema.sql` — canonical schema for a new RepairTrack database. Use only with the documented fresh-install setup procedure.

## Existing-database upgrades

The following files are incremental migrations. They are not standalone setup scripts and must be applied only through the CLI migration runner (`php tools/migrate.php`), after a verified backup and confirmation of the target database.

- `password_reset.sql`
- `staff_workspace.sql`
- `payment_handover.sql`
- `staff_activity.sql`
- `bill_corrections.sql`
- `stock_reservations.sql`
- `stock_quarantine.sql`
- `stock_approvals.sql`
- `stock_transfer_losses.sql`
- `stock_transfer_recoveries.sql`
- `stock_transfer_evidence.sql`
- `stock_transfer_loss_valuations.sql`
- `part_acquisition_cost_records.sql`
- `inventory_stocktaking.sql`
- `repair_part_requests.sql`

The migration runner selects these scripts by inspecting the existing database. Do not rename, move, delete, or execute them manually without updating and testing the runner.

## Test/demo fixtures

These are intentionally outside this directory, under `tests/fixtures/demo/`:

- `create_demo_database.sql` — creates the historical demo fixture schema.
- `seed_demo.sql` — fictional demo records.
- `check_demo_integrity.sql` — checks fixture consistency.
- `test_demo_constraints.sql` — exercises fixture constraints.

They are test assets, not production installation or migration scripts. Keep them separate from real customer data and never point fixture scripts at a live database.

## Maintenance rule

A migration that is already represented in `schema.sql` may still be required to upgrade an older database. Treat it as active until the migration runner, supported upgrade paths, documentation, and regression tests are deliberately consolidated together.
