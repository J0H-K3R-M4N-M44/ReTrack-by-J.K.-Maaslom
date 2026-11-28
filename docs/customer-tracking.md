# Customer tracking and console workflow

This increment is based on Simplified-System. It brings the prototype's customer entry point, service overview, repair status, custody and timeline into the PHP application. Data comes from MySQL, not browser demo storage. The staff workspace update requires `database/migrations/staff_workspace.sql`; see `staff-workspace.md`.

## Staff → customer → technician

1. An admin, manager or intake agent opens an issued receipt in an assigned branch. From a device screen, choose **Receipt and customer tracking access**.
2. Confirm replacement and create a private tracking code. Copy or print it for the customer. It is shown once; previous codes stop working. Stale sharing forms are rejected. Existing and seeded tickets use the same action.
3. The customer opens the public home page, chooses **Track your repair**, and enters the code and the last four digits of their recorded phone number as PIN (legacy long codes do not require a PIN). An RT receipt number alone does not grant access.
4. The customer sees model, current stage, branch-level custody, customer-visible history and branch phone when recorded. Contacts, serial numbers, shelf locations, private notes, signatures, photos and staff account details stay private.
5. The assigned technician adds a **Private work note**, a **Customer-visible update**, or a permitted stage change. The review screen separates the two messages. Customer messages and stage changes appear in tracking; private notes never do. No SMS/email is sent.
6. Customers refresh for new updates and choose **Close tracking** when finished. Tracking access lasts 30 minutes; replacing the code invalidates an existing customer session on its next request.

## Access and limits

New codes contain five hexadecimal characters and require a hashed four-digit phone-suffix PIN. Legacy 64-character codes remain supported until replaced. A server session temporarily holds the raw code for one-time staff display or customer access. Audit events record replacement without recording the code. Anyone holding a code can view that repair, so give it only to the customer or an authorized representative.

There is no automatic long-term code expiry yet: staff replacement revokes it. Production use needs HTTPS and an agreed retention/access policy. Preview links use fragments to keep codes out of HTTP URL logs. JavaScript clears the fragment and fills the form without submitting. Without JavaScript, paste the printed code. Tracking pages send no-store, no-referrer and noindex headers and load no third-party resources. POST forms use CSRF checks; an IP-based tracking attempt limit is separated from staff sign-in attempts. The existing file-based limiter is intended for one server.

Security design reference: [OWASP token and referrer guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html). Tracking codes are reusable access credentials, not single-use password reset tokens.

## Console changes

- Status filters retain role and branch scope.
- Status cards explicitly cover the whole assigned scope; queue filters apply to the list.
- Selected-view pagination preserves search and status.
- Keyboard focus, skip links, account-name wrapping and narrow-screen filters improve access.
- Courier manifests and viewer consoles remain read-only. Cashiers now have a separate read-only bill lookup.

## Code layout

| File | Responsibility |
| --- | --- |
| `index.php`, `css/landing.css`, `js/landing.js` | Customer entry, services, stationary header and mobile menu |
| `track.php` | Customer requests, session expiry and safe errors |
| `app/public/tracking.php` | Explicit safe queries, custody text and scoped code replacement |
| `views/public/tracking.php`, `css/public.css` | Responsive customer console |
| `js/public-tracking.js` | Optional private-link form filling |
| `admin/ticket.php` | Staff code creation and one-time display |
| `app/console/repository.php` | Filters, pagination and reviewed technician updates |

## Tests

Use a disposable database named `repairtrack_test_*` with the original demo schema/data and an untracked local config. Never run fixture tests against real customer data. Run `php tests/staff-consoles.php`, then `php tests/public-tracking.php`. They modify sample rows, so recreate the disposable database before rerunning.

For browser tests, install `playwright-core`, set `CHROME_PATH`, `REPAIRTRACK_TEST_URL` (localhost), and `REPAIRTRACK_TEST_SITE` (absolute project directory), then serve the project with PHP. Run `node tests/consoles-browser.mjs`, `node tests/public-browser.mjs`, and `node tests/home-browser.mjs`. Tests cover five staff roles, intake, private-note isolation, code replacement/revocation, branch and role checks, CSRF, escaped customer text, no-JavaScript forms, reduced motion and responsive layouts. Screenshots and generated JSON fixtures are local test output; do not commit or deploy them.

## Next work

Quote preparation/approval, parts operations, cashier/COD handling, transfer dispatch/receipt, customer chat/requests, QR scanning, automatic alerts and more complete operational trends still need their real workflows. Google Maps on the separate demo branch has not been copied into this PHP increment. Custody represents staff records, not live GPS. See `system-gaps.md`.
