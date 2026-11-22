# Architecture and code map

**Reviewed:** 22 November 2026, `Partial-Ticket-Ownership`; compare with `main` before deploying any feature-branch file.

## Runtime shape

A server-rendered PHP application with MySQL/InnoDB through PDO, separate public pages, small JavaScript enhancements, role-specific staff pages, and command-line migrations. There is **no required production Node/React backend**; browser JavaScript tests use Node/Playwright separately.

~~~text
Public: index.php / track.php       Staff: admin/*.php
          |                                    |
    app/public/tracking.php            app/auth.php + app/web.php
          |                                    |
          +---------- app/db.php --------------+
                           |
                       PDO/MySQL
                           |
          repair_tickets -> ticket_devices
               |                |       |
          repair_bills     repair_quotes  custody/events
               |                |       |
          payments/changes   parts & stock ledger
~~~

This is a logical map, not a formal entity-relationship diagram; see [database reference](database-reference.md).

## Module layout

| Path | Responsibilities |
| --- | --- |
| `index.php`, `track.php`, `views/public/` | Public homepage/tracking; only customer-approved fields |
| `admin/login.php`, `admin/logout.php`, `admin/forgot_password.php`, `admin/reset_password.php` | Sign-in/out, password recovery |
| `admin/dashboard.php`, `admin/staff_home.php`, `admin/device.php`, `admin/reports.php` | Dashboard, queues, device actions, reports |
| `admin/intake_create.php`, `admin/intake_issue.php`, `admin/ticket.php` | Draft intake, issuance, ticket/receipt actions |
| `admin/quote.php`, `admin/cash.php`, `admin/transfers.php` | Estimates, bill/pickup and physical device transfer UI |
| `admin/inventory.php`, `admin/parts_transfers.php`, `admin/stocktaking.php`, `admin/acquisition_costs.php` | Stock actions, parts manifests, physical count and verified cost views |
| `app/auth.php`, `app/web.php`, `app/login_throttle.php` | Session/role checks, request/CSRF utilities and login throttling |
| `app/intake/` | Validation, persistence, evidence, issuance and duplicate rules |
| `app/console/` | Work queue/detail, technician assignment, repair stage/events, history, activity, reports and device custody |
| `app/quotes/repository.php` | Quote versions, consent records and estimate permissions |
| `app/payments/repository.php` | Ticket-level cashier settlement, corrections/refunds and return gates |
| `app/inventory/` | Stock reservations, quarantines/adjustments, physical parts transfers, losses/recoveries, evidence, valuation, supplier costs and stocktakes |
| `app/staff/repository.php` | Branch-scoped staff creation, activation and access changes |
| `app/public/tracking.php` | Safe customer-status queries, code + PIN checking |
| `views/`, `css/`, `js/` | HTML templates, responsive styles and progressive enhancement |
| `database/`, `tools/` | Fresh schema, additive migrations, local setup, seeding, CLI maintenance |
| `tests/`, `.github/workflows/` | PHP integration, negative and concurrency cases, Playwright/browser regression and CI |

## Web request and mutation boundaries

1. A page calls the server-side login/role guard; session and branch access are **not** read from a posted role.
2. Read queries use constrained SQL scopes, named selections and bound parameters.
3. A mutation validates CSRF and input format, then **locks and re-fetches** the actor/affected entities inside a database transaction.
4. Writes check stale versions, business transition rules, custody/quote/stock/billing gates, and record an event or audit row before commit.
5. The page redirects/shows a result **only after success**. Errors leave prior state unchanged where the operation is transactional.

MySQL DDL migrations are an exception: they can commit implicitly and **cannot** promise transaction rollback; see [migration guide](migration-runner.md).

## Independent authority and location

- `repair_tickets.accountable_branch_id`: ticket contract, customer-facing service responsibility, cashier, refund and counter handover authority.
- `ticket_devices.physical_location_id` + `custody_state`: last confirmed physical location and possession. Physical movement is logged in `device_transfers` and `device_custody_events`.
- `ticket_devices.assigned_technician_id`: individual technician work assignment.
- `ticket_devices.repair_branch_id`: **feature-branch-only** proposed repair authority; currently populated for issued devices but NOT used to grant new access.

**Do not replace the global ticket scope with the device scope.** On the feature branch, `app/console/scopes.php` contains legacy device, proposed device-repair and ticket-finance scope builders. The proposed repair scope is intentionally unused by live handlers. Cash reports retain ticket-based authority. [Security audit](authorization-cutover-audit.md).

## Event and financial accounting boundaries

- Repair status events / customer summaries are not financial invoices.
- Custody handover events are not ownership approval events.
- Reservations are not consumption; installations/returns use inventory transactions and preserve original movements.
- Cash payments and approved corrections/refunds are append-only historical records; loss-valuation approval and stocktaking sign-off are **not** general-ledger postings.
- A partial transfer of one sibling device must not silently reassign its ticket's cashier or all sibling repairs.

## Time and interface conventions

Database events are stored with UTC handling; staff reporting and human-facing calendar periods use Asia/Manila. Browser UI uses responsive views with local JavaScript enhancements, but authorization and validation must work server-side. Staff reports offer 7/30/90-day views; tables generally paginate. The source still requires real browser review for accessibility, touch targets, and target deployment environments.

For detailed source-level responsibility, see [code style](code-style.md), [staff consoles](staff-consoles.md), and [database reference](database-reference.md).
