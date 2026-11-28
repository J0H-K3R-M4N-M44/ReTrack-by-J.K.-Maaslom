# RepairTrack

RepairTrack is a PHP and MySQL learning project for tracking computer repairs across one or more store branches. The current `Dashboard` screen keeps the existing dark interface and uses database-backed staff login with administrator-only management.

## Main release — 15 November 2026

Main now includes the tested simplified application, staff workspaces, customer tracking, quotes and approval, and the migration runner. See [release and upgrade notes](docs/main-release.md). Demo-System and Google-Maps-Tracking remain separate.

## Staff workspace update

For an existing database, back it up and run `php tools/migrate.php --apply` to add the staff workspace and cash-payment table. See [staff workflow and upgrade instructions](docs/staff-workspace.md) for role-specific queues, workload limits, history, cash collection and handover. Fresh schemas already include the changes.

## Quote and approval update

From a device, choose **Open estimate** to prepare a quote and record the customer response. See [the workflow, permissions and tests](docs/quote-and-approval.md). This branch includes Staff-Workspace and needs no further schema migration.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0.16 or newer
- PHP extensions `pdo_mysql`, `mbstring`, `ctype` and `fileinfo`
- XAMPP or another local Apache + PHP + MySQL setup

## Automatic database upgrades

For an existing database, run `php tools/migrate.php` to see pending upgrades, then `php tools/migrate.php --apply` after backing up. The runner uses your local configuration and asks you to confirm the database name. See [XAMPP instructions and recovery notes](docs/migration-runner.md).

## Local setup

1. Start MySQL. Copy `config/config.example.php` to `config/config.php` if you have no local config. Set the database to **repairtrack** and enter your local MySQL connection details.
2. From Git Bash inside the repository, run `bash tools/setup-db.sh --seed` for the schema and a fictional shop with 24 repair cases. Use `bash tools/setup-db.sh` for schema only.
3. Save the generated staff password printed after commit. Sign in as `technician@sim.example.test`, `cashier@sim.example.test`, `manager@sim.example.test` or `intake_agent@sim.example.test`. The simulation occupies the reserved branch code SIM-SOGOD. A repeat seed stops before adding more records.
4. To create your own administrator after seeding, run `/c/xampp/php/php.exe tools/create_admin.php`. Start Apache or your usual PHP server and open `admin/login.php`.

If PDO reports that the MySQL driver is missing, enable `pdo_mysql` in the PHP configuration used by Apache and the command line.

## Account password recovery

For an existing database, run `database/migrations/password_reset.sql` once in MySQL Workbench. Set `app_url` in `config/config.php` to the full site URL, including the project folder. Set `mail_from` to an address accepted by the mail server, then configure PHP `mail()` (or the server's local mail relay) to deliver messages.

The login page's **Forgot Password?** link emails a one-use reset link. The token is stored as a hash, expires after 30 minutes, and can only be used once. The new password must contain at least 12 Unicode characters and fit within 72 UTF-8 bytes. The request page uses the same response whether or not an active account matches the email.

If you use XAMPP locally, PHP mail delivery is not enabled automatically. Configure a mail relay before expecting reset emails to arrive. Keep the real sender and database settings only in the ignored `config/config.php`; never commit credentials or reset links.

## Database connection

`app/db.php` creates a PDO connection using a MySQL DSN and prepared statements. Keep your real `config/config.php` out of Git; only the example template is committed.

## Payments and customer pickup on Payments-Inventory

Managers can issue one final bill for a ticket after every device is ready and has a current approved estimate. Cashiers can record counter cash, then confirm who received the devices and that the device/accessory check happened. The ticket closes after handover. This feature records no COD, online payment, refund, tax, discount, or digital signature.

## Current scope

- Shared staff login with administrator-only protected screens against the `users` table using `password_verify()`
- Session ID renewal on login, CSRF protection for login, logout and password recovery, and idle-session expiry
- Single-use, expiring account password reset links
- A database-backed dashboard summary and working dashboard section navigation
- A searchable repair workbench with numbered pages, pending intake drafts, and current status
- A branch-scoped staff intake workflow for customer matching, device details, and reviewing and editing drafts of up to five devices
- A customer roster with ticket totals
- [Staff reports](docs/staff-workspace.md#dates-tables-and-analytics) for technician milestones, cashier collections and branch operations, with 7/30/90-day filters and daily figures
- A public landing page with the existing MP4 hero background, scroll navigation, responsive sections, and reduced-motion support
- A role-based page wireframe for customer, counter officer, technician, and branch administrator workflows

Ticket issuance captures an accessory checklist, condition photo and storage location for each device, with one signed intake form covering the ticket. Staff-recorded quote approval and counter-cash customer pickup are available on Payments-Inventory. Parts operations, COD delivery, refunds and public self-approval remain to be built. This is a local learning build, not a hardened public service. Do not expose it to the internet or use real customer data until access controls, audit handling, HTTPS, backups, and recovery workflows are complete.

## Development branches

Use [the branch map](docs/branch-map.md) to choose the feature category. The tested application through `Database-Migrations` is now in `main`. Start new work from the updated main branch; older feature branches remain historical work areas. See [consolidation checks](docs/consolidation-checks.md) for fixes, tests and limitations.

## Sample database and integrity tests

See [Database demo setup](docs/database-demo.md) for a separate `repairtrack_demo` database with 963 fictional rows across all 33 existing tables. Import setup, seed, read-only checks and constraint tests in the documented order. Your existing `repairtrack` data is not overwritten. The `Database-Testing` branch owns these fixtures and database checks.

## Administrator intake milestone

See [the implementation and test notes](docs/admin-milestone.md) for draft review/edit rules, pagination, login limits, test commands and remaining features. No table or column changes are required.

See [Ticket issuance setup and workflow](docs/ticket-issuance.md) before issuing a draft. It requires a private image folder and a one-time local key in your ignored configuration file.




## Staff management

Administrators can add, edit and deactivate staff with branch-scoped permissions and a reviewed activation-link workflow. See [staff account setup and limits](docs/staff-management.md). No database migration is required. See [staff consoles and quick intake](docs/staff-consoles.md) for the available role-specific tools and their limits.

See [home content notes](docs/home-content.md) for the preparation guide, FAQ and current-versus-planned feature explanations.

## Demo separation

The standalone prototype and expanded classroom seeder now live on [Demo-System](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/tree/Demo-System). This branch keeps the working PHP application and original SQL regression fixtures. See [remaining workflows](docs/system-gaps.md) and [pending legacy branch cleanup](docs/branch-cleanup.md).

## Customer portal and console improvements

Private customer tracking, revocable access codes, customer-safe technician updates and queue filters now reuse the existing database. See [the workflow and tests](docs/customer-tracking.md). Existing databases should use the migration runner described above.
