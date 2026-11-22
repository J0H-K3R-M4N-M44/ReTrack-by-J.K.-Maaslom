# Test and verification register

**Last reviewed:** 22 November 2026. Passing a suite is evidence about that suite, commit and environment—**not** proof that every branch, browser, deployment, or customer case passes. PR #55 remains draft and unmerged.

## Environments and distinctions

- `main` baseline: `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b`, contains prior integrated milestones.
- `Partial-Ticket-Ownership`: adds database foundation, a read-only ownership display, guarded scope prototypes, negative tests and dedicated disposable MySQL suite.
- **Observed pass**: GitHub-hosted [MySQL integrity run 44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) on commit `738bfada` passed all eight stages. Earlier [run 44989776748](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989776748) also passed earlier, but run 44989851868 is the later documented passing checkpoint. Each later docs/code commit must have its own run.
- **New preflight evidence:** the [PHP shop workflow run 44991528241](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991528241) passed both isolated `ownership-browser` and original `payment-pickup` jobs at code commit `59065079`. The [staff activity run 44991375736](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991375736) passed its activity, reports, migration and intake tests at earlier code commit `d2d190ad`. The [MySQL integrity run 44991532074](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991532074) passed at `59065079` as well.
- **Still not established:** operational device-repair authorization cutover, actual partial-ticket transfer/acceptance, concurrent two-manager acceptance, persistent database migration/restore, production readiness, customer branch-consent policy, real messaging or accounting integration. Later commits must have their own relevant CI evidence.

## Test matrix

| Concern | Script / workflow | Expected evidence |
| --- | --- | --- |
| Fresh schema and migration status/apply/recovery | `tests/migrations.php`, `tools/migrate.php` | Pending markers, refusal/cancel, lock, rerun, recovery |
| MySQL integrity & regression preservation | `tests/mysql-integrity-round.sh`, `tests/mysql-integrity.php`, `.github/workflows/mysql-integrity.yml` | 17 invariants, 19 full-table snapshots, FK/index, negative tests, cleanup |
| Read-only ownership and scope separation | `tests/device-ownership-view.php`, `tests/scope-separation.php` | Correct labels, no ticket-code echo, role scope boundaries |
| Restrict-only repair/quote/stock guard | `tests/repair-scope-gate.php`, `tests/repair-guard-integration.php`, `.github/workflows/repair-scope-guard.yml` | Legacy mode equivalent, matched-branch-only repair access, denied quote/stock writes, source finance unchanged, admin device queue filtered; fictional fixture cleaned up |
| Authenticated ownership and role/browser isolation | `tests/ownership-browser-fixture.php`, `tests/ownership-browser.mjs`, `tests/ownership-browser-cleanup.php` in the separate `ownership-browser` CI job | 320/390/768/1440 widths, escaped branch label, aligned/divergent warnings, no-JS, origin finance, denied destination staff and sibling access |
| Mixed-branch device-only isolation (simulated) | `tests/payment-pickup.php --scope-isolation` | Sibling repair isolation, ticket cash ownership, inactive/revoked denies |
| Whole-ticket transfer, inventory and refunds | `tests/payment-pickup.php --stock-pilot`, `tests/payment-pickup.php`, `tests/checkout-concurrency.php` | Consistent assignments, custody, quote/stock and cash state |
| Inventory stocktakes, cost provenance and loss recovery | `tests/stocktaking.php`, `tests/part-acquisition-costs.php`, `tests/parts-recovery.php` | Independent approval and preserved stock evidence |
| Intake/issued receipt and evidence | `tests/ticket-issuance.php`, `tests/admin-intake.php` | Required gates, stored evidence, draft/issued separation |
| Staff roles/reports/history | `tests/staff-consoles.php`, `tests/staff-management.php`, `tests/staff-reports.php`, `tests/staff-activity.php` | Role/branch isolation, event/report consistency |
| Public code and restricted tracking | `tests/public-tracking.php`, `tests/public-browser.mjs` | Invalid token/PIN refusal, safe outputs |
| Browser layout/keyboard and workflow | `tests/*-browser.mjs` and `.github/workflows/payment-pickup.yml`, `.github/workflows/staff-activity.yml` | Real page interactions; inspect exact failed/passed job rather than counting test files |

These names describe available test sources. The matrix is **not** a claim that every script was executed in the latest round.

## Role/browser regression preflight (22 November 2026)

The synthetic ownership browser fixture creates **one extra test-only two-device ticket** with device branch differences **without a manager acceptance event**. It is intentionally a *hypothetical invalid operational state*: the current legacy ticket-scoped PHP code still denies destination staff entry even when the proposed device-branch field points there. Source ADMIN/MANAGER see read-only branch labels and a mismatch warning; TECHNICIAN/INTAKE_AGENT/VIEWER do not see those labels. Tests also verify HTML escaping, unaffected ticket finance scope, and that unauthorized cashier/courier accounts cannot open repair details.

The first combined browser run failed because its extra fixture shifted the old board's selected row. A cleanup script fixed that contamination, but the combined run then exceeded the **existing 20-attempt per-IP login limit**. Rather than weaken login protection, the workflow now uses **separate ephemeral GitHub-hosted jobs**, each with its own MySQL, PHP server and limiter state. On commit `59065079`, both `ownership-browser` and `payment-pickup` passed in run 44991528241.

These checks **do not** prove that the receiving branch may operate on the hypothetical remote device; that feature is intentionally OFF. A future real transfer must have separate authorization, quote/stock, origin/destination consent, and concurrency tests.

## Restrict-only authorization CI (default OFF)

`repair_scope_mode` defaults to `legacy`. A separate disposable MySQL test exercises `restrict` for mismatched two-device tickets, disallowed repairs, quotes, stock writes, and independent ticket-cash scope. The [guard CI run 44992407822](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44992407822) passed on `37f6e3f4`; this does **not** establish that any cross-branch device transfer is allowed. [Guard details](repair-scope-guard.md).

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
