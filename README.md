# RepairTrack

RepairTrack is a PHP and MySQL learning project for tracking computer repairs across one or more store branches. The current `Dashboard` screen keeps the existing dark interface and uses database-backed administrator login.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0.16 or newer
- PHP extensions `pdo_mysql` and `mbstring`
- XAMPP or another local Apache + PHP + MySQL setup

## Local setup

1. In MySQL Workbench, open and run `database/schema.sql`. It creates the `repairtrack` database and starter tables. It uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables.
2. Copy `config/config.example.php` to `config/config.php`.
3. Edit `config/config.php` for your local MySQL host, port, database, username, and password. The local password belongs only in your untracked `config/config.php`.
4. From the project folder, run `php tools/create_admin.php`. Enter an administrator name, email, and password of at least 12 characters. The command stores a password hash and assigns the account to active branches; it does not create a default login.
5. Start Apache and MySQL, then open `http://localhost/ReTrack-by-J.K.-Maaslom/admin/login.php` (adjust the folder name to match your XAMPP `htdocs` directory).

If PDO reports that the MySQL driver is missing, enable `pdo_mysql` in the PHP configuration used by Apache and the command line.

## Admin password recovery

For an existing database, run `database/password_reset.sql` once in MySQL Workbench. Set `app_url` in `config/config.php` to the full site URL, including the project folder. Set `mail_from` to an address accepted by the mail server, then configure PHP `mail()` (or the server's local mail relay) to deliver messages.

The login page's **Forgot Password?** link emails a one-use reset link. The token is stored as a hash, expires after 30 minutes, and can only be used once. The new password must contain at least 12 characters. The request page uses the same response whether or not an active admin account matches the email.

If you use XAMPP locally, PHP mail delivery is not enabled automatically. Configure a mail relay before expecting reset emails to arrive. Keep the real sender and database settings only in the ignored `config/config.php`; never commit credentials or reset links.

## Database connection

`app/db.php` creates a PDO connection using a MySQL DSN and prepared statements. Keep your real `config/config.php` out of Git; only the example template is committed.

## Current scope

- Administrator login against the `users` table using `password_verify()`
- Session ID renewal on login, CSRF protection for login and password recovery, and idle-session expiry
- Single-use, expiring administrator password reset links
- A database-backed dashboard summary and working dashboard section navigation
- A repair workbench showing the latest 12 issued device records, pending intake drafts, and current status
- A three-step staff intake wizard for customer matching, device details, and a reviewable draft
- A customer roster with ticket totals, plus a report summary grouped by repair status
- A public landing page with the existing MP4 hero background, scroll navigation, responsive sections, and reduced-motion support
- A role-based page wireframe for customer, counter officer, technician, and branch administrator workflows

Draft editing and issue gates (photos, accessory checklist, signatures, and accountable branch), quotes, parts, billing, and technician workflows remain to be built. This is a local learning build, not a hardened public service. Do not expose it to the internet or use real customer data until access controls, audit handling, HTTPS, backups, and recovery workflows are complete.
