# RepairTrack

RepairTrack is a PHP and MySQL learning project for tracking computer repairs across one or more store branches. The current \`Dashboard\` screen keeps the existing dark interface and uses database-backed administrator login.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0.16 or newer
- PHP extensions \`pdo_mysql\` and \`mbstring\`
- XAMPP or another local Apache + PHP + MySQL setup

## Local setup

1. In MySQL Workbench, open and run \`database/schema.sql\`. It creates the \`repairtrack\` database and the starter tables. It uses \`CREATE TABLE IF NOT EXISTS\` and does not drop existing tables.
2. Copy \`config/config.example.php\` to \`config/config.php\`.
3. Edit \`config/config.php\` for your local MySQL host, port, database, username, and password. The example defaults are \`localhost\`, \`3306\`, \`repairtrack\`, and \`root\`. The local password belongs only in your untracked \`config/config.php\`.
4. From the project folder, run \`php tools/create_admin.php\`. Enter an administrator name, email, and password of at least 12 characters. The command stores a password hash and assigns the account to active branches; it does not create a default login.
5. Start Apache and MySQL, then open \`http://localhost/ReTrack-by-J.K.-Maaslom/admin/login.php\` (adjust the folder name in the URL to match your XAMPP \`htdocs\` directory).

If PDO reports that the MySQL driver is missing, enable \`pdo_mysql\` in the PHP configuration used by Apache and the command line.

## Database connection

\`app/db.php\` creates a PDO connection using a MySQL DSN and prepared statements. The code uses named functions and a small config array so the connection flow is easy to follow. Keep your real \`config/config.php\` out of Git; only the example template is committed.

## Current scope

- Administrator login against the \`users\` table using \`password_verify()\`
- Session ID renewal on login, CSRF protection for the login form, and idle-session expiry
- A database-backed dashboard summary and working dashboard section navigation
- A repair workbench showing the latest 12 issued device records and their current status
- A customer roster with ticket totals, plus a report summary grouped by repair status
- The existing dashboard layout and CSS remain in place

Ticket intake and editing, quotes, parts, billing, and technician workflows are the next planned modules. This is a local learning build, not a hardened public service. Do not expose it to the internet or use real customer data until access controls, audit handling, HTTPS, backups, and recovery workflows are complete.
