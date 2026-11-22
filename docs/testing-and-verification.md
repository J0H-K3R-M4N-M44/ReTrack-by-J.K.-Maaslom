# Test and verification register

**Last reviewed:** 22 November 2026. Passing a suite is evidence about that suite, commit and environment—**not** proof that every branch, browser, deployment, or customer case passes. PR #55 remains draft and unmerged.

## Environments and distinctions

- `main` baseline: `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b`, contains prior integrated milestones.
- `Partial-Ticket-Ownership`: adds database foundation, a read-only ownership display, guarded scope prototypes, negative tests and dedicated disposable MySQL suite.
- **Observed pass**: GitHub-hosted [MySQL integrity run 44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) on commit `738bfada` passed all eight stages. Earlier [run 44989776748](https://github.com/J0H-K3R-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989776748) is **not** authoritative; use the correct repository and exact run URL in PR #55. Each later docs/code commit must have its own run.
- **Not established by that pass**: independently successful payment-browser and staff-activity workflow runs at the final feature HEAD, deployed database migration, production recovery, complete permission cutover, real SMS, accounting integration or partial-ticket acceptance.

## Test matrix

| Concern | Script / workflow | Expected evidence |
| --- | --- | --- |
| Fresh schema and migration status/apply/recovery | `tests/migrations.php`, `tools/migrate.php` | Pending markers, refusal/cancel, lock, rerun, recovery |
| MySQL integrity & regression preservation | `tests/mysql-integrity-round.sh`, `tests/mysql-integrity.php`, `.github/workflows/mysql-integrity.yml` | 17 invariants, 19 full-table snapshots, FK/index, negative tests, cleanup |
| Read-only ownership and scope separation | `tests/device-ownership-view.php`, `tests/scope-separation.php` | Correct labels, no ticket-code echo, role scope boundaries |
| Mixed-branch device-only isolation (simulated) | `tests/payment-pickup.php --scope-isolation` | Sibling repair isolation, ticket cash ownership, inactive/revoked denies |
| Whole-ticket transfer, inventory and refunds | `tests/payment-pickup.php --stock-pilot`, `tests/payment-pickup.php`, `tests/checkout-concurrency.php` | Consistent assignments, custody, quote/stock and cash state |
| Inventory stocktakes, cost provenance and loss recovery | `tests/stocktaking.php`, `tests/part-acquisition-costs.php`, `tests/parts-recovery.php` | Independent approval and preserved stock evidence |
| Intake/issued receipt and evidence | `tests/ticket-issuance.php`, `tests/admin-intake.php` | Required gates, stored evidence, draft/issued separation |
| Staff roles/reports/history | `tests/staff-consoles.php`, `tests/staff-management.php`, `tests/staff-reports.php`, `tests/staff-activity.php` | Role/branch isolation, event/report consistency |
| Public code and restricted tracking | `tests/public-tracking.php`, `tests/public-browser.mjs` | Invalid token/PIN refusal, safe outputs |
| Browser layout/keyboard and workflow | `tests/*-browser.mjs` and `.github/workflows/payment-pickup.yml`, `.github/workflows/staff-activity.yml` | Real page interactions; inspect exact failed/passed job rather than counting test files |

These names describe available test sources. The matrix is **not** a claim that every script was executed in the latest round.

## Dedicated MySQL round

The feature branch's [detailed guide](mysql-integrity-round.md) specifies eight stages. The runner requires all of:

1. A clean checkout with **no existing `config/config.php`**.
2. Temporary local MySQL instance and test-only credentials.
3. `REPAIRTRACK_INTEGRITY_TEST=1` opt-in plus `MYSQL_PWD` for the isolated test server.
4. A newly generated `repairtrack_test_integrity_*` database. **Never** supply a production DB account.

~~~bash
REPAIRTRACK_INTEGRITY_TEST=1 MYSQL_PWD=temporary_test_password bash tests/mysql-integrity-round.sh
~~~

Negative tests need their opt-in even when run separately. They are intended to leave no changes after rollback, but connect them **only** to disposable fixtures. The shell script deletes its generated database and temporary config on exit.

## Minimum release verification

- [ ] CI checks green **at the final commit**, including the dedicated integrity workflow and relevant staff/payment workflows.
- [ ] Fresh MySQL schema, nonempty upgrade rehearsal, idempotence and exact row/ledger snapshot comparison.
- [ ] Denied-role/forged-branch/CSRF/stale-form tests and two-operator transfer/cash/stock concurrency.
- [ ] Real Chrome tests at 320, 390, 768 and 1440px plus keyboard/reduced motion/no-JavaScript paths.
- [ ] Backup **and restore** rehearsal for database and private evidence under selected target MySQL version.
- [ ] Review of open security policy: consent for branch reassignment, approved quote changes, cost responsibility, customer data retention.
- [ ] Distinct deployment approval; no direct merge to `main` or persistent migration before it.

## Logging results

For any new run, record: UTC timestamp, commit SHA, tool versions, disposable DB name (not password), job/run URL, script results, scope limitations and whether code changed afterward. A failed test is a **blocker**, not a reason to remove an assertion.

[Security](security-and-privacy.md) · [Roadmap](release-status.md) · [MySQL detail](mysql-integrity-round.md).
