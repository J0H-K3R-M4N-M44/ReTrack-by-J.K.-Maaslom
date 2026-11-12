# Database demo and integrity checks

This fixture inserts **963 fictional rows into all 33 existing tables**: the 32-table schema plus `password_reset_tokens`. It creates and uses **`repairtrack_demo`**, leaving the existing `repairtrack` database and its accounts untouched. No tables or columns have been added to the application schema.

## Files and import order

| File | Purpose |
| --- | --- |
| `database/create_demo_database.sql` | Creates the empty, separate demo database using a snapshot of the current schema and password-reset table. |
| `database/seed_demo.sql` | Inserts connected sample records in one transaction. Refuses to seed any nonempty table. |
| `database/check_demo_integrity.sql` | Read-only table counts and 141 relationship/business-consistency checks. |
| `database/test_demo_constraints.sql` | Nine intentionally invalid writes that must be rejected; all row changes are rolled back. |
| `docs/seed-counts.json` | Expected row counts immediately after a successful seed. |
| `assets/demo/` | Two clearly marked SVG placeholders referenced by photo and acknowledgment records. |

The schema snapshot excludes the source schema's three minimal demo-branch inserts so the seed can check for empty tables. Refresh it when `database/schema.sql` or `database/password_reset.sql` changes. The seed matches the current broad schema, not the proposed smaller rebuild.

## Run with XAMPP and Git Bash

1. Start MySQL in XAMPP.
2. Update the local repository, then open the MySQL client:

```bash
cd /c/xampp/htdocs/qwert
git switch Simplified-System
git pull --ff-only
/c/xampp/mysql/bin/mysql.exe -u root -p
```

Enter your local MySQL password. If your local root account has no password, press Enter at the password prompt.

3. At the `MariaDB>` or `mysql>` prompt, run these files in order:

```sql
SOURCE C:/xampp/htdocs/qwert/database/create_demo_database.sql;
SOURCE C:/xampp/htdocs/qwert/database/seed_demo.sql;
SOURCE C:/xampp/htdocs/qwert/database/check_demo_integrity.sql;
SOURCE C:/xampp/htdocs/qwert/database/test_demo_constraints.sql;
```

Run each command separately. Stop if a file reports an error. Do not use a client's `--force` option to ignore errors. The client account needs permission to create the demo database, tables and stored procedures. MySQL Workbench can also open and execute these SQL files in the same order.

The read-only report should show the expected counts and **141 PASS results with zero violations** immediately after seeding. Each constraint test should report PASS and the expected error number; the intentional errors are handled inside the test procedure.

## View the data in the PHP application

In your local, Git-ignored `config/config.php`, set:

```php
'database' => 'repairtrack_demo',
'app_url' => 'http://localhost/qwert',
```

Keep your existing database host, port, username and password. Start Apache and open `http://localhost/qwert/admin/login.php`.

**Local demo login only:**

- Email: `admin@example.test`
- Password: `DemoRepair2026!`

The password is stored as a PHP-compatible bcrypt hash. All eight fictional users share this documented test password; user 8 is inactive. The existing real login accepts only active ADMIN accounts, so other seeded roles do not imply completed staff authentication. The active demo administrator has access to all three demo branches. Do not use these public demo credentials for real accounts or a publicly hosted database.

Restore `'database' => 'repairtrack'` to return the application to your original data. This setting does not move or copy any records.

## Scenarios included

- Three fictional Sogod branches, eight users covering all six schema roles, and branch assignments.
- Thirty customers, thirty tickets, and thirty-one devices, including one multi-device ticket and one device identified without a serial number.
- Every repair status, three drafts, completed and cancelled cases, and histories matching the current status and custody location.
- Accessories, labeled evidence placeholders, simulated acknowledgments, issuance gates, and a correction note.
- Pending, approved, declined, expired and superseded quote scenarios; six issued bills with matching lines.
- Six parts, eighteen branch stock balances, twelve part-use records and one completed stock transfer with matching ledger entries.
- One in-transit device transfer and one received device transfer with custody history.
- Simulated notifications, audit records, and expired/used password-reset tokens. No messages are sent and no usable reset link is seeded.

The SVG evidence is labeled simulated, and the acknowledgment is explicitly not a real signature. Every contact uses `example.test`; there are no real customer details or SMS recipients.

## What integrity means here

Foreign keys check that referenced rows exist. UNIQUE constraints reject duplicate identifiers. CHECK constraints reject specific invalid values, such as negative inventory or incomplete issued tickets.

Some cross-table rules are **not enforced by the schema itself**. For example, a quote header can be changed without updating its line totals. The read-only report detects this mismatch, but the application must prevent it using validation and transactions. Other checks compare stock ledgers, bill totals, customer/device ownership, status chains, custody locations, transfer routes and simulated notification settings.

The negative tests cover duplicate branch codes, orphan contacts, negative stock, zero quote quantities, incomplete issued tickets, same-branch transfers, accepted acknowledgments without signatures, invalid roles and missing required names. They verify these rejection cases; they do not prove that every possible invalid database state is blocked.

## Repeat imports and rollback

The seed refuses a nonempty table rather than deleting records, resetting IDs or silently modifying your test work. On a seed error, its handler rolls back all row inserts and reports the error. A failed invocation may leave the helper procedure; the next run recreates it safely. The scripts never disable foreign key checks.

To start fresh, deliberately recreate **only the disposable `repairtrack_demo` database** after saving any test work you want to keep, then rerun setup and seed. This package does not automatically drop a database. Rollbacks can leave gaps in AUTO_INCREMENT sequences; that does not indicate missing committed data.

## Validation performed

Tested on MariaDB 10.11.14 and PHP 8.3.6:

- Empty database setup and all 963 inserts succeeded.
- All 141 integrity checks passed.
- All nine invalid-write tests were rejected with their expected errors.
- A second seed attempt was refused, with all table counts unchanged.
- An injected quote-total error was detected by the report, then rolled back.
- An injected failure immediately before COMMIT rolled back all 963 inserts across 33 tables.
- PHP verified the documented password against the seeded bcrypt hash.

Full PHP page flows and dashboard integration were not verified as part of this seed package. MySQL 8.0.16+ is the intended other target, but was not executed here. Older MySQL/MariaDB versions that ignore CHECK constraints are not suitable for this integrity test. The rejection tests should expose that limitation.

The real PHP application currently connects login, dashboard queries and draft intake. The seed does not implement the other demo workflows. In particular, the schema has bills but no dedicated payment/receipt table, so cash collection and COD payment integrity are outside these scripts. This is a functional fixture, not a performance/load test.
