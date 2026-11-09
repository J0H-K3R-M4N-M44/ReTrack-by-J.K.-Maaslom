# Administrator intake milestone

This milestone completes the administrator draft-intake workflow. It does not complete the whole repair-management system. No tables, columns, or live configuration files change.

## Workflow

1. An active administrator signs in. The server checks the password immediately. The browser only displays “Signing in…” during the request.
2. Open Repair workbench and search issued records or pending drafts. Lists display their totals and offer Previous/Next links in pages of 12. Dashboard summaries cover the whole system; they are not branch-filtered.
3. Start intake. Search for an existing customer first, or enter a name and one phone/email contact. Search displays up to 20 matches; narrow the search if necessary. Searching again reloads the form, so do this before typing unsaved device details.
4. Enter device type, brand/model, reported problem and visible condition. Serial number is optional. Only active branches assigned to the administrator can accept a draft.
5. Select Review details. PHP validates the fields and renders the review. Confirm the review, then save. Enter on the first JavaScript step advances to device details; it cannot save. Without JavaScript, both entry sections are visible and the same server review is required.
6. Open the draft link from the workbench to edit it. Existing customer contact details are not overwritten. Saved drafts stay at their original branch. Only single-device DRAFT records can be edited; issued records and multi-device drafts are rejected with an explanation.

Customer creation, contact creation, ticket/device changes and the audit entry share a transaction. A failed write rolls everything back. Each form has a session-bound, one-use key that expires after 30 minutes; up to 20 open forms are retained. Repeated submission is rejected. Editing locks the draft and compares its saved version with the database so an older form cannot overwrite a newer change. This protects repeated submissions of a form, not two independently created new-intake forms.

## File responsibilities

| Area | Files |
| --- | --- |
| Session, admin access and CSRF | `app/auth.php` |
| Local login attempt limits | `app/login_throttle.php` |
| Escaping, safe request text, search patterns, password rule | `app/web.php` |
| Login/recovery/logout HTTP handling | `admin/login.php`, `admin/forgot_password.php`, `admin/reset_password.php`, `admin/logout.php` |
| Intake field validation | `app/intake/rules.php` |
| Customer lookup and draft creation | `app/intake/repository.php` |
| Draft loading and transactional edits | `app/intake/drafts.php` |
| Review and save coordination | `admin/intake_create.php` |
| Intake HTML and optional step navigation | `views/intake/create.php`, `js/intake-wizard.js` |
| Dashboard queries, totals and pagination | `app/dashboard_data.php` |
| Dashboard links and rendering | `admin/dashboard.php` |

The HTML works without JavaScript. CSS `[hidden]` applies to every component, and page content is visible by default. The video is decorative, hidden for reduced motion, and paused when JavaScript is available. The obsolete notification event bus, fake login transitions and JavaScript dashboard router were removed.

## Account behavior

Logout is a CSRF-protected POST. GET logout links no longer end sessions. Login limits are five attempts per normalized email and 20 per client IP in 15 minutes, including successful attempts. Files are locked and stored in the PHP temporary directory outside the public site, with hashed identities. Storage failures reject login rather than bypass the limiter. This is a single-server limiter; a distributed deployment needs shared storage and a trusted proxy/IP policy. Expired counters reset on their next use; administrators can prune old files during maintenance.

Unknown accounts still perform a password-hash check to reduce timing differences. This is not a claim of perfectly equal response times. PHP counts password length in Unicode code points: at least 12 characters, no null bytes, and at most 72 UTF-8 bytes for bcrypt. Browser `minlength` was removed to avoid a different counting rule. The same PHP rule applies to reset and administrator creation.

Recovery requires `app_url`, `mail_from`, and a working PHP mail relay in the ignored `config/config.php`. Missing configuration displays an error. The generic public response says an email was requested and does not promise delivery or disclose account existence. A successful `mail()` call only confirms local handoff, not inbox delivery. No real credentials are added to the repository.

## Validation

Executed on an isolated PHP 8.3.6 / MariaDB 10.11.14 runtime with a disposable database and Chromium:

- PHP syntax checks for every PHP source and test.
- Active-admin authentication; wrong password, unknown, disabled and non-admin account rejection.
- Customer paging and literal `%`/`_` searches; formatted phone matching and phone validation.
- New draft, existing-customer reuse, draft edits, stale-version rejection, issued/unassigned draft rejection.
- Injected device-write failure rolls back the customer and ticket transaction.
- Unicode password limits, password reset and token reuse rejection.
- Account attempt limit and administrator creation CLI.
- Real browser login without an artificial delay; tab links and reload state; pagination.
- Enter navigation, server review, tampered review rejection, duplicate submission rejection, editing and mobile width.
- POST logout, CSRF failures, idle-session expiry, JavaScript-disabled login/intake/navigation.
- Injected dashboard query failure, missing recovery configuration, recovery email captured by a local sink, and reset through the browser.

Mail tests use a local sink and never contact an external provider. MySQL 8 and the user's Windows/XAMPP installation have not been exercised in this environment.

### Repeating tests

Use a disposable copy of the site and a fresh database named `repairtrack_test_admin`. In copies of `database/create_demo_database.sql` and `database/seed_demo.sql`, replace every `repairtrack_demo` with `repairtrack_test_admin`, then import them in that order. Point only the disposable site's ignored `config/config.php` at that database. Do not use live data.

Run `php tests/admin-intake.php` once against the fresh fixture. It deliberately inserts and edits records. Run `node tests/login-regression.cjs` from the site root for the small JavaScript regression check.

For `tests/admin-browser.mjs`, install `playwright-core`, provide `CHROME_PATH`, and serve the disposable PHP copy locally. Set `REPAIRTRACK_TEST_URL`, `REPAIRTRACK_TEST_SITE`, `REPAIRTRACK_SESSION_DIR` and `REPAIRTRACK_MAIL_LOG`. Configure PHP `session.save_path` to that disposable session directory and `sendmail_path` to a local mail-capture script. Create `cli-admin@example.test` with `php tools/create_admin.php` before the browser suite. Use the fixture password `DemoRepair2026!`. The browser suite temporarily modifies the disposable configuration and dashboard query file to test failures, restores those files in `finally` blocks, ages its test session, and writes a mobile screenshot. Recreate the disposable database before a full rerun.

## Remaining work

The follow-up [ticket issuance milestone](ticket-issuance.md) adds consent/signature uploads, accessory and evidence checks, and linked ticket receipts. Issued records and customer summaries remain read-only. Multi-device draft editing, branch transfers, technician/cashier access, assignments, repair-status changes, quotes, payments, inventory operations, notifications/history UI, and richer analytics remain outside this milestone. The prototype's screens do not imply that these PHP workflows are implemented.

The Access-Accounts, Intake-Tracking and Dashboards-Reports changes are grouped into separate commits, then synchronized to the tested integrated result on Simplified-System. Main is not changed. Other category branches retain their existing heads until their work needs the shared updates.

