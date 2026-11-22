# Local installation, configuration and safe upgrades

**Scope:** local XAMPP/Apache + PHP + MySQL development. GitHub commits and PRs never migrate an existing database for you. The current source includes feature-branch-only schema changes, so **do not point this branch at a production database**.

## Prerequisites

- PHP 8.1+ (project tests have used PHP 8.4); the CLI and Apache versions/configuration should agree.
- MySQL 8.0.16+ or a compatible MySQL/MariaDB environment with enforced foreign keys and CHECK constraints; this is **not** proof every target version was tested.
- PHP extensions: `pdo_mysql`, `mbstring`, `ctype`, `fileinfo` and normal session/JSON support.
- Local Apache/XAMPP or a PHP development server; Git Bash on Windows for bundled shell setup scripts.
- Writable **private** folders for intake photos/forms and stock investigation evidence, located outside `htdocs` and outside the repository.

## Fresh local database

1. Clone the repository, confirm branch and `git status`, and copy `config/config.example.php` to ignored `config/config.php`. Set the local host/database/user/password, app URL, private storage paths and an unguessable stable `intake_hmac_key` (32 random bytes expressed as hex). Do not commit the config.
2. Start MySQL and confirm PHP CLI has `pdo_mysql`; e.g. `php -m`.
3. Use a **throwaway/local** database with a backup plan. For a fresh local build, use the guarded setup script; it imports `database/schema.sql`. Do not run the setup script over an installation that contains valuable data.
4. To add fictional training records, run the seed form. Seeds are **not** normal production migrations and are not safe to repeatedly apply.

~~~bash
bash tools/setup-db.sh           # fresh local schema; confirm config and target DB first
bash tools/setup-db.sh --seed    # fresh schema plus fictional simulation, not a live shop
php tools/create_admin.php      # interactive local administrator creation
~~~

On Windows with Git Bash, the setup wrapper prefers `/c/xampp/php/php.exe` when present; otherwise it uses PHP on PATH. The actual database selection comes from your **ignored** configuration. Verify it before importing or seeding. Login is under `admin/login.php`. Do not distribute generated seed passwords.

## Existing local database upgrade

**Never** run setup/seed as a substitute for an incremental migration. For a current, backed-up existing database:

~~~bash
php tools/migrate.php --help
php tools/migrate.php --status
php tools/migrate.php --dry-run
~~~

Status is read-only. Inspect the database name, pending scripts, schema version and source branch. Back up database **and evidence files**; verify the backup can be restored to an isolated database; suspend staff writes; rehearse on a disposable database. Only after explicit review:

~~~bash
php tools/migrate.php --apply       # type exact database name to confirm
~~~

`--apply --yes` is for controlled unattended **test** or explicitly reviewed maintenance operations. It bypasses the name prompt; do not add it casually to production scripts. The migration runner's pending detection is incomplete as a universal schema auditor.

## Feature-branch ownership upgrade

`database/device_repair_branches.sql` adds a nullable indexed FK-backed `ticket_devices.repair_branch_id` and backfills missing issued rows without changing original update timestamps. It is **not merged into main**, not a repair authorization cutover, and has not been deployed to a persistent shop database. The isolated test process is described in [MySQL integrity round](mysql-integrity-round.md). Run `tools/check_repair_branch_integrity.php` only on a correctly migrated test environment.

## Verify the local app

From a disposable environment, run the relevant PHP and browser tests listed in [testing](testing-and-verification.md). Check staff roles, deliberate bad-input cases, public code/PIN denial, device/custody handover, stock reservations, cash refunds and desktop/mobile layout. Read CI results for the exact commit; a local page opening successfully is not sufficient.

## Common errors

| Symptom | Check |
| --- | --- |
| `could not find driver` | Enable PHP `pdo_mysql` in CLI and Apache configurations |
| Database login fails | Confirm host, port, user, password, active server and correct configured DB |
| Migration “pending” after apply | Inspect exact missing table, index, FK or incomplete data backfill; do not manually mark ready |
| Evidence preview fails | Check private directory ownership, configured path and authorized PHP access; do not move images into web root |
| Customer reset link never arrives | Configure an actual mail relay; local email support may not be enabled |
| Wrong branch data visible | Stop rollout and audit active `user_branch_assignments` and scope query; do not work around this by widening the filter |
| Prototype appears to have more features | The standalone demo has simulated content; only PHP/MySQL records are authoritative |

## Destructive work and recovery

MySQL `ALTER TABLE` and routines can commit outside application transactions. A failed migration may be **partially applied**. Do not assume a simple SQL `ROLLBACK` restores schema. Record the first error, pause writes, preserve logs and take a fresh snapshot of the failed state, then compare to the verified backup. Restore or forward-fix only after a disposable rehearsal. Do not reverse `repair_branch_id` after mixed ownership is in use without an explicit data reconciliation plan.

[Migration reference](migration-runner.md) · [Operations](operations-runbook.md) · [Security](security-and-privacy.md).
