# Staff workspace

This branch extends the PHP application. Main is unchanged. It uses the prototype's compact dark interface, with one selected work list instead of repeated tables.

## Upgrade an existing installation

Back up the database. For an existing installation, run `php tools/migrate.php` and then `php tools/migrate.php --apply`. The runner applies the staff workspace and counter-cash migrations safely. Fresh installations include both in `database/schema.sql`.

Set `technician_active_limit` in your local `config/config.php` if needed; the default is 5. Keep this file untracked. Existing ticket references and old tracking links are preserved.

## Daily workflow

- All staff use the same sign-in form. Their account role and branch assignments select the available console. Show password and Caps Lock feedback help with entry.
- Managers assign devices. Each technician may hold five active devices by default, across branches. Completed/cancelled devices and closed tickets no longer count. The server checks capacity while locking the technician record; disabling a full technician in the dropdown is only a convenience.
- Technicians work from their assigned queue. The first transition into In progress records the repair start time. Pausing and resuming does not overwrite it. Adding a note does not invent a start time. Quote approval rules still apply.
- The status board groups the current page of devices by repair stage. It is not a total of every matching device. Queue and board show the same filtered data in different layouts.
- History contains completed/cancelled devices and closed tickets. Records remain stored; this is not deletion. Couriers have separate active and historical manifest views.
- Intake agents and managers can open Saved drafts or start intake. Only the selected section loads its list.
- Cashiers can search issued/void bills by ticket reference, five-character tracking code or bill number. They can record counter cash and complete pickup after the full balance is paid. Administrators/managers can also access these actions; only they can issue the final bill.

## Dates, tables and analytics

Repair start timestamps are stored in UTC and displayed in Philippine time. The migration backfills only genuine first In progress status events. Missing dates say Not recorded.

Tables and history lists use ten rows per page, visible result counts, search and navigation. Numeric amounts align right. Search and selected view remain in pagination links. Mobile tables scroll within their container.

Open **Reports** from the workspace menu or **Reports and records** from the admin dashboard. Choose 7, 30 or 90 Philippine calendar days and, for a multi-branch account, a branch. Each report includes today. Chart bars show exact figures on hover, focus or tap. The daily table shows up to ten dates per page, newest first. Its links keep the selected period and branch.

| Account | During the selected period | Current snapshot |
| --- | --- | --- |
| Technician | First repair starts, entries into testing and transitions to ready for pickup on devices currently assigned to the technician. A second test run counts again. Adding a note at the same stage has no effect. | Active assigned devices by stage. |
| Cashier | Gross peso counter collections across the selected branches, the number of payment records and devices handed back to customers. Split payments count individually. A later bill void leaves its recorded receipts in gross cash. | Remaining peso balances on issued bills for open tickets, after all recorded payments. |
| Manager or administrator | Devices on newly issued tickets, first repair starts and recorded customer returns. Average intake-to-return time includes each returned device with a valid pair of timestamps, including intake before the selected period. | All issued devices by current status. |

Reports use the ticket's current accountable branch. Reassignment changes which branch or technician can see the device's history. An absent start or handover timestamp contributes no event. A database error displays a retry link. The existing schema and migrations supply all reporting data.

## Customer access

Staff issue an uppercase five-character hexadecimal code and a PIN derived from the last four digits of the customer's recorded phone number. The PIN is hashed; the code is indexed uniquely and collisions are retried. Existing receipt references are unchanged. Existing 64-character private codes continue working until replaced.

Replacement revokes both old access formats. The PIN is a snapshot when issued: changing a phone record does not silently change it; issue replacement access. Invalid/missing phone records must be corrected before issuance. Code and IP attempt limits use the existing single-server limiter. Customer sessions expire after thirty minutes and recheck access on each request.

A phone suffix is predictable, so this is a convenience credential with limited security. The portal exposes only repair progress and public updates, never phone numbers, private work notes, serial numbers or payment details. Use HTTPS in deployment; agree on retention and stronger credentials before widening the information shown.

## Verification

Use only a disposable `repairtrack_test_*` database. Import the original demo schema and seed, then apply the migration twice to check upgrades and repeatability. Configure the test database locally. Run these in order:

1. `php tests/staff-consoles.php`
2. `php tests/public-tracking.php`
3. With a local PHP server, run `tests/consoles-browser.mjs`, `tests/public-browser.mjs`, and `tests/home-browser.mjs` using Node.
4. `php tests/staff-workspace.php`
5. `node tests/workspace-browser.mjs`

Browser scripts use Playwright, `CHROME_PATH`, `REPAIRTRACK_TEST_URL` and `REPAIRTRACK_TEST_SITE`. Fixture scripts change data; recreate the disposable database before repeating. Do not deploy generated fixture files or screenshots.

On a disposable schema, run `php tests/staff-reports.php` for report calculations and scope. For browser checks, save the output of `php tests/staff-reports.php --browser-fixture` outside the web root, set `REPAIRTRACK_REPORT_FIXTURE` to its path and `REPAIRTRACK_TEST_URL` to the local site, then run `node tests/reports-browser.mjs`.

Counter cash is recorded as append-only payment rows. COD, online payments, refunds, bill voiding, and digital signatures are not part of this milestone. See `system-gaps.md` for the remaining work. Validate the migration on the target MySQL installation before deploying.

## Debugging pass: navigation, mobile details and timezones

- Device links carry a small allowlisted list context. Back to console restores the selected view, search, status and page, including after review/save or history navigation. External return URLs are never accepted.
- Mobile analytics details anchor to the full date row, preventing the wrapped-row tooltips from extending outside the screen. Table views explain horizontal scrolling on small screens.
- Database connections and the migration explicitly use UTC. This keeps TIMESTAMP history consistent with UTC DATETIME repair-start values and Philippine-time display. Already populated start dates are not rewritten by the migration.
- A history entry with no internal note says No private note; it no longer falsely claims a stage change occurred.

Regression coverage includes returning from a searched history result, rejected return parameters, connection timezone, and bounds of every date tooltip at 320, 390 and 700 pixels.
