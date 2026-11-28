# Disposable database simulation seeder

`tools/seed_primary_test.php` is a local CLI-only helper for adding the existing fictional Sogod simulation records to a disposable RepairTrack test database.

## Safety rules

- Use a database whose name begins with `repairtrack_test_`.
- Use a local MySQL host only: `localhost`, `127.0.0.1`, or `::1`. Remote hosts are rejected.
- Set that database in the local, ignored `config/config.php` before running the command.
- Import the current `database/schema.sql` and apply required migrations first.
- The command does not create, migrate, truncate, or drop databases.
- The command requires the exact `--confirm` argument.
- It verifies that the configured database matches the connected database.
- It checks for the tables used by the fixture before starting a transaction.
- The shared simulation fixture refuses to create a second `SIM-SOGOD` branch.
- A seeding error rolls back the transaction.
- The generated password is printed once to the local terminal. Store it only in a safe local note if needed; never commit it or reuse it outside the disposable database.

## Run

From the project root, using the PHP CLI configured for the test database:

```sh
php tools/seed_primary_test.php --confirm
```

The four synthetic accounts are:

- `manager@sim.example.test`
- `technician@sim.example.test`
- `intake_agent@sim.example.test`
- `cashier@sim.example.test`

They share the temporary password printed by the command.

## Verification

The existing `tests/seeded-workflow.php` test creates its own uniquely named disposable database, installs the current schema, invokes the same `seedSimulation()` fixture, and checks staff logins, activity, reports, public portal PIN validation, payment settlement, and pickup handover.

Run that test separately with a local MySQL account that can create and drop databases. It does not test the command's interactive confirmation or configuration guard; those are code-level safety checks.

## Not for production

Do not point this command at a live, hosted, shared, or ordinary development database. Do not adapt the old `Primary-Seed` script by changing its database name. This test-only command intentionally reuses the current simulation fixture instead of introducing a second set of demo records.
