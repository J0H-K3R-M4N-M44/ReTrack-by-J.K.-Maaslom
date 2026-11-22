# Operations runbook (local/staging)

**Purpose:** consistent procedures for a developer or shop tester using **fictional** accounts and a backed-up local database. Do not interpret this as a deployed production operations handbook.

## Before each session

1. Confirm branch with `git branch --show-current` and clean work tree with `git status`.
2. Confirm `config/config.php` points to the intended database, and the private evidence directories exist outside the web root.
3. Read [release status](release-status.md): production migrations and partial-ticket repair acceptance are **not approved**.
4. Verify MySQL is running, the correct PHP has `pdo_mysql`, and sample credentials belong only to a test environment.

## Staff workflow

| Trigger | Action / expected gate |
| --- | --- |
| New customer/device | Assigned intake staff match customer, save draft, review accessories/condition/signature and issue receipt only when complete |
| Technician starts diagnosis | Assigned device only; repair-status transition rules and current quote approval before `IN_PROGRESS` |
| Parts needed | Prepare current catalog estimate; branch reservation, install, return or quarantine according to `app/inventory/` rules |
| Device moved physically | Authorized origin dispatch + destination receipt with manifest and custody-event history; do **not** interpret arrival as repair authority |
| Whole ticket changes repair branch | Check complete received manifest, early-stage status, no installed parts/holds or bill; manager acceptance is auditable |
| Bill requested | Assigned ticket authority verifies **every** device's readiness, approved quote and physical return to accountable branch |
| Customer pays or collects | Cashier records permitted counter cash; handover requires settlement and checks; final audit/closure recorded |
| Physical stock count | Branch ADMIN/MANAGER opens, counts and submits; a different authorized ADMIN signs off only after reconciliation |
| Supplier cost / loss estimate | Independent reviews and evidence; amounts are *exposure/provenance*, not a GL expense or customer invoice |

## Daily health checks

- Unassigned/stale repair queue, transfer `EXCEPTION` manifests, outstanding customer estimates, unbilled ready devices, unresolved cash refunds and stock reservations needing review.
- Stock transfers/loss recoveries, quarantine decisions, pending count reviews, and old open stocktakes.
- No unapproved foreign-key, migration, reconciliation or privacy warning should be hidden to keep the dashboard “green.”

## When operations fail

| Symptom | Safe response |
| --- | --- |
| `Outside assigned branch` or unauthorized role | Verify account activation, branch membership and entity scope; **do not** disable permission checks |
| `This device changed` / stale version | Reload current data, review both versions, resubmit only intentionally |
| In-transit device cannot progress | Finish physical manifest receipt or record exception; never set custody directly merely to proceed |
| Quote not approved | Obtain the allowed staff-recorded customer decision; never force repair start or edit an approved quote silently |
| Stock shortage or stale reservation | Recheck on-hand, holds and transfer/return movements; no negative stock writes |
| Bill/handover blocked | Check all devices, quote status, branch location, balance/refund due and final receipt record |
| Migration failure | Pause writes, capture exact error, preserve backup and evidence; compare `information_schema` and run rehearsal on disposable database |
| Role data leak or lost evidence | Restrict affected access immediately, preserve minimal audit trace, secure copies, inspect path permissions before restoring access |

## Command quick reference

~~~bash
php tools/migrate.php --status            # no writes
php tools/migrate.php --help
php tools/check_repair_branch_integrity.php   # only after branch schema installed
~~~

In a **disposable isolated** environment only:

~~~bash
REPAIRTRACK_INTEGRITY_TEST=1 MYSQL_PWD=temporary_test_password bash tests/mysql-integrity-round.sh
~~~

`tests/mysql-integrity.php --check` is read-only but still **refuses non-test DB names**. `--negative` additionally requires explicit opt-in and rolls back changes; it must not be used to diagnose production data. Consult [testing](testing-and-verification.md).

## Backup, restore and maintenance policy

Back up **both** relational data and private intake/inventory evidence, record schema version and branch SHA, and test restoration into a fresh isolated database. Pausing the web app matters: MySQL migration advisory locking does not block normal PHP requests. If an additive migration only partially applies, **do not blindly reverse SQL DDL**; follow [migration recovery](migration-runner.md).

There is no automatic SMS/courier/GPS monitoring, accounting posting, remote incident notifier or background scheduler promised by this runbook. Any real deployment needs a separately approved operations, retention and incident-response plan.

[Setup](local-setup.md) · [Security](security-and-privacy.md) · [Current feature status](system-overview.md).
