# RepairTrack

RepairTrack is a PHP and MySQL learning project for tracking computer repairs across one or more store branches. The current `Dashboard` screen keeps the existing dark interface and uses database-backed staff login with administrator-only management.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0.16 or newer
- PHP extensions `pdo_mysql`, `mbstring`, `ctype` and `fileinfo`
- XAMPP or another local Apache + PHP + MySQL setup

## Local setup

1. In MySQL Workbench, open and run `database/schema.sql`. It creates the `repairtrack` database and starter tables. It uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables.
2. Copy `config/config.example.php` to `config/config.php`.
3. Edit `config/config.php` for your local MySQL host, port, database, username, and password. The local password belongs only in your untracked `config/config.php`.
4. From the project folder, run `php tools/create_admin.php`. Enter an administrator name, email, and password of at least 12 characters. The command stores a password hash and assigns the account to active branches; it does not create a default login.
5. Start Apache and MySQL, then open `http://localhost/ReTrack-by-J.K.-Maaslom/admin/login.php` (adjust the folder name to match your XAMPP `htdocs` directory).

If PDO reports that the MySQL driver is missing, enable `pdo_mysql` in the PHP configuration used by Apache and the command line.

## Account password recovery

For an existing database, run `database/password_reset.sql` once in MySQL Workbench. Set `app_url` in `config/config.php` to the full site URL, including the project folder. Set `mail_from` to an address accepted by the mail server, then configure PHP `mail()` (or the server's local mail relay) to deliver messages.

The login page's **Forgot Password?** link emails a one-use reset link. The token is stored as a hash, expires after 30 minutes, and can only be used once. The new password must contain at least 12 Unicode characters and fit within 72 UTF-8 bytes. The request page uses the same response whether or not an active account matches the email.

If you use XAMPP locally, PHP mail delivery is not enabled automatically. Configure a mail relay before expecting reset emails to arrive. Keep the real sender and database settings only in the ignored `config/config.php`; never commit credentials or reset links.

## Database connection

`app/db.php` creates a PDO connection using a MySQL DSN and prepared statements. Keep your real `config/config.php` out of Git; only the example template is committed.

## Current scope

- Shared staff login with administrator-only protected screens against the `users` table using `password_verify()`
- Session ID renewal on login, CSRF protection for login, logout and password recovery, and idle-session expiry
- Single-use, expiring account password reset links
- A database-backed dashboard summary and working dashboard section navigation
- A searchable repair workbench with numbered pages, pending intake drafts, and current status
- An administrator intake workflow for customer matching, device details, server-side review, and reopening/editing single-device drafts
- A customer roster with ticket totals, plus a report summary grouped by repair status
- A public landing page with the existing MP4 hero background, scroll navigation, responsive sections, and reduced-motion support
- A role-based page wireframe for customer, counter officer, technician, and branch administrator workflows

Single-device ticket issuance now captures accessories, a condition photo, customer-signed paperwork and a branch storage location. Quote approval screens, parts, billing, transfer handovers and repair completion remain to be built. This is a local learning build, not a hardened public service. Do not expose it to the internet or use real customer data until access controls, audit handling, HTTPS, backups, and recovery workflows are complete.

## Development branches

Use [the branch map](docs/branch-map.md) to choose the feature category. Tested category work merges into `Simplified-System`; `main` is unchanged. See [consolidation checks](docs/consolidation-checks.md) for fixes, tests and limitations.

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
