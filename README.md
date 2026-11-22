# RepairTrack

RepairTrack is a **PHP + MySQL multi-branch computer repair tracking learning project**. It provides staff intake, repair status/work queues, customer-safe tracking, confirmed custody records, quote approvals, ticket billing, counter cash/pickup, and bounded inventory workflows. It is **not production-certified** and must not be used with real customer data without an independent deployment, security and recovery review.

## Documentation

**Start with [the documentation index](docs/README.md).** It contains the current [system overview](docs/system-overview.md), [architecture](docs/architecture.md), [database reference](docs/database-reference.md), [setup](docs/local-setup.md), [security](docs/security-and-privacy.md), [tests](docs/testing-and-verification.md), [operations](docs/operations-runbook.md) and [release status](docs/release-status.md).

Old milestone files remain in `docs/` for history; read their dated status before using them as current implementation requirements.

## Local quick start

Requirements: PHP 8.1+ with `pdo_mysql`, `mbstring`, `ctype` and `fileinfo`; MySQL 8.0.16+ (or verified compatible server); local Apache/XAMPP. Use an isolated local database and **fictional** records only.

~~~bash
# Copy and edit the ignored config/config.php from config/config.example.php.
bash tools/setup-db.sh             # fresh local schema; inspect database target first
bash tools/setup-db.sh --seed      # optional fictional sample data
php tools/create_admin.php        # create a local admin account
php tools/migrate.php --status    # read-only status on an existing database
~~~

Read [local setup](docs/local-setup.md) *before* importing an existing database, and back up both MySQL data and private evidence. The upgrade process is not an all-or-nothing SQL transaction.

## Current branch state — 22 November 2026

- `main` at `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b` is the last checked integrated application baseline. This is not a live-site version declaration.
- `Partial-Ticket-Ownership` / [draft PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55) adds an **unmerged** per-device repair branch field, read-only diagnostics and separate *proposed* scopes. **No partial-ticket transfer permission is activated.**
- [Dedicated disposable MySQL integrity run 44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) passed at `738bfada`; broader browser/permission/deployment gates remain separate.
- Customer chat, self-approved quote changes, full COD/delivery remittance, live GPS/maps and automated repair SMS/email **are not complete production functionality**. The separately maintained demo may simulate them.

See [release and remaining work](docs/release-status.md), [gaps](docs/system-gaps.md) and [branch map](docs/branch-map.md). No real data, branch merge or deployment is performed by this documentation update.
