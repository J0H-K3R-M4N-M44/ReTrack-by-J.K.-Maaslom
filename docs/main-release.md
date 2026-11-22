# Main release — 15 November 2026

> **Historical release note (15 November 2026).** This documents the earlier `Database-Migrations` integration checkpoint, **not** the complete application status on 22 November. Later `main` commits implemented the bounded cash/payment, physical device transfers, stock return/quarantine, parts loss/recovery, supplier-cost and physical stocktaking workflows. Statements below describing those as “not completed” must be read **as of 15 November**, not as current limitations. Use [current release status](release-status.md), [system overview](system-overview.md) and [branch map](branch-map.md) for the latest source. This is not a live deployment announcement.

Main incorporates the application history through Database-Migrations (`aea76fe80042e85434c36c7dc64dfde885f8f7a1`). Its source includes Simplified-System, Customer-Portal-and-Consoles, Staff-Workspace, Quote-and-Approval and the older application-category branches. No main-only changes are discarded. Demo-System and Google-Maps-Tracking remain separate; their prototype behavior is not advertised as working PHP functionality.

## Included

- Shared staff login/recovery, branch-scoped accounts and activation.
- Reviewed intake, draft editing, evidence and ticket issuance.
- Staff queues, status board, history, ten-row pagination and search.
- Technician capacity, first repair start timestamps and repair-start trends.
- Private customer tracking with replaceable short code/PIN access and legacy-code support.
- Quote drafts, revisions, staff-recorded customer decisions and approval-to-repair handoff.
- Read-only cashier bill lookup and command-line database upgrades.
- Shared interface, accessibility and mobile-layout fixes.

## Update your local copy

Save or commit local changes first, then run:

```bash
git fetch origin
git switch main
git pull --ff-only origin main
```

Keep your ignored `config/config.php`. Back up the configured database and pause application writes. In Git Bash with XAMPP:

```bash
/c/xampp/php/php.exe tools/migrate.php
/c/xampp/php/php.exe tools/migrate.php --apply
```

Confirm the database name. No demo seed import is required. If your local main has its own commits and the pull refuses to fast-forward, inspect them instead of resetting or force-pulling. See [migration instructions](migration-runner.md).

This GitHub update does not change your local or hosted database automatically. A fresh installation still follows the README schema/config/admin setup.

## Verification and remaining scope

The release check uses a disposable database and covers PHP syntax, migration status/apply/rerun/recovery, staff permission boundaries, intake, assignment, repair stages, public tracking, quote approval, and desktop/mobile/no-JavaScript flows. Testing uses PHP 8.4 and MariaDB 11.8; confirm deployment on your target XAMPP/MySQL version.

Payment recording, stock operations, final handover/closure, customer self-approval, automated repair alerts and live mapping are not completed production workflows. See [system gaps](system-gaps.md). Branch names such as Payments-Inventory do not imply those workflows are complete. No branches are deleted by this integration.
