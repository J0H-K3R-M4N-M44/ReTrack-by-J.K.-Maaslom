# Demo-System

This branch starts from the working Simplified-System version. It keeps both:
- The real PHP application with disposable database fixtures.
- demo-prototype/, the separate browser simulation. Its chats, payments and other simulated actions are not proof that those PHP features are implemented.

## New classroom seeder

In MySQL Workbench, run these files in order, once, against a fresh demo database:

1. database/create_demo_database.sql
2. database/seed_demo.sql — original all-table integrity fixtures.
3. database/seed_console_demo.sql — new role-scoped console scenarios.
4. database/check_console_demo.sql — read-only relationship and scenario checks.

The new stage adds 120 customers/tickets, resulting in **150 customers, 150 tickets, 151 devices and 17 accounts**. It retains original inventory, transfer, quote, bill and simulated-notification examples. There are reopenable drafts, unassigned received devices, assigned repairs across stages, approved/unapproved quotes, multiple device types and inactive staff.

The scripts explicitly use repairtrack_demo. They do not drop a database, truncate tables, disable foreign keys or alter the schema. The original seed requires empty tables; the extension requires baseline counts/identity and refuses a second application. These guards are not a substitute for using a fresh demo database. Never apply the extension to a demo you have already edited.

Do not run the old check_demo_integrity.sql row-count comparison after expansion; its expected counts describe the small original fixture. check_console_demo.sql retains its relationship/business checks and adds role-scope checks for this dataset.

## Connect the PHP app

Copy config/config.example.php to config/config.php. Set database to repairtrack_demo and enter your local MySQL settings. Set app_url to the actual localhost project URL. Create a private evidence directory outside htdocs and set intake_storage_path. Generate intake_hmac_key using the command in the example config.

In the VS Code terminal, from the project folder:

    php tools/prepare_demo_evidence.php

This installs two clearly marked synthetic PNGs into private storage, so seeded receipt/photo links work. It creates no accounts, sends no messages and refuses non-demo databases. Running it again is safe when the packaged files match. The .b64 files are text-encoded fixture images; PHP decodes them, with no extra package needed.

Use Apache/XAMPP for the PHP application. The standalone demo-prototype/index.html remains a browser simulation.

## Demo login accounts

All active accounts below use **DemoRepair2026!**. These are intentionally public demo credentials, never production accounts.

| Email | Role / scope |
| --- | --- |
| admin@example.test | Administrator, all three branches |
| admin.central@example.test | Administrator, Central only |
| manager.central@example.test | Manager, Central |
| manager.north@example.test | Manager, North |
| manager.hub@example.test | Manager, Hub |
| intake.central@example.test | Intake agent, Central |
| intake.north@example.test | Intake agent, North |
| tech.central@example.test | Technician, Central |
| tech.north@example.test | Technician, North |
| tech.hub@example.test | Technician, Hub |
| courier.central@example.test | Courier, Central |
| courier.hub@example.test | Courier, Hub |
| viewer.central@example.test | Viewer, Central |
| viewer.north@example.test | Viewer, North |

pending.tech@example.test and disabled.tech@example.test are inactive fixtures; they cannot sign in. demo.user8@example.test is the original inactive administrator fixture. No reusable live activation/reset token is published.

## Suggested demonstration

1. Global admin: compare branches, open Repair operations, assign a received device.
2. Central admin: verify North/Hub repairs are absent; manage Central staff.
3. Technician: open an assigned device, add a private note and review a permitted stage change. Some diagnosing records have approved quotes and some do not, demonstrating the approval gate.
4. Intake agent: add a customer and use device/problem/condition choices, including Other; review, save and reopen a draft.
5. Viewer: inspect status without editing or private notes.
6. Courier: read route and custody-condition rows. Transfer handover actions are still pending.
7. Global admin: inspect technician notes. Managers and other staff must not see them.

The older fixtures also include multi-device tickets, closed cases and transfer examples. Do not treat seeded approvals/signatures as genuine consent. Public tracking tokens are synthetic hashes, not working public access links.

## Reset

There is intentionally no one-click destructive reset. Keep a fresh local database snapshot after seeding, or explicitly remove only your disposable repairtrack_demo database in Workbench and import again. Never substitute your live repairtrack database.

Branch deletion is tracked separately in [legacy branch cleanup](branch-cleanup.md).

## Verification performed

Tested on PHP 8.4 and MariaDB 11.8 in an isolated local database. All relationship/business checks passed, along with rollback on an injected late failure, repeat-run refusal, 17 account login states, branch isolation, queue pagination, approved/unapproved quote examples and image metadata checks. Chromium opened all six role screens and both seeded evidence previews. Evidence installation was also run twice successfully. Target MySQL and production mail delivery were not tested.
