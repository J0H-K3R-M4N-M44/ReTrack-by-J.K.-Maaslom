# RepairTrack — Windows/XAMPP acceptance and recovery handoff

**Prepared:** 22 November 2026. **Updated:** 26 November 2026. **Status:** INSTRUCTIONS ONLY — no Windows/XAMPP test or real-user sign-off has been performed. This document complements the proven [disposable Linux SQL/evidence restore](mvp-recovery-rehearsal.md) and the [34-case manual UAT checklist](mvp-uat-checklist.md).

**Candidate to test:** branch `MVP-Role-Overviews-2026-11-26`, commit `5a750799522c594214c90ba2002ce71684b10761`, draft [PR #62](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/62). Its parent visual baseline is `MVP-UX-Polish-2026-11-25` at `1ce32caf9a84581958a271cdc4b151b04e19c375`. The final candidate's four-job MVP CI run passed, but that is not local operator acceptance.

**Scope for this manual pass:** confirm the role-specific default overviews for Manager, Intake Agent, Technician, Courier, Cashier and Viewer; confirm Admin remains on its existing operations overview; check collapsed navigation labels and overview metric help on real pointer, keyboard and touch input. Keep the shell as the already reviewed screenshot baseline. Do not add layout redesign requests to this acceptance round unless an actual defect blocks a task.

## 1. Prepare an isolated fictional installation

Use only a specifically authorized, disposable XAMPP setup with fictional device, contact and receipt records. Record the exact tested branch and commit (for this UX pass, the PR #61 head on `MVP-UX-Polish-2026-11-25`), PHP and MySQL/MariaDB versions, Windows version, browser, operator and test start time.

1. Make a **new** web folder under `htdocs`, separate from any working project. Check out the exact candidate commit above into that new folder (or copy a clean export of that commit); do not test a moving branch head or reuse real customer data. Record the actual checked-out SHA with `git rev-parse HEAD`.
2. Create writable private directories **outside** `htdocs`, e.g. `C:\repairtrack-private-test\intake` and `C:\repairtrack-private-test\inventory`. Keep access restricted to the test operator and PHP/Apache account. Confirm that browsing these paths by HTTP is impossible.
3. Create a **new** database such as `repairtrack_test_operator`, and a test-only user. The project's `database/schema.sql` contains `CREATE DATABASE` and `USE repairtrack` statements: review and rewrite *both* to target only the new test database. Never execute the untouched schema against an existing shop database.
4. Copy `config/config.example.php` to ignored `config/config.php`. Set the fictional database, configured private evidence directories and the exact local `app_url`. Generate a fresh private `intake_hmac_key` and keep it secure. Do not commit or screenshot it.
5. Start Apache/MySQL locally, open the test URL and complete the basic login, customer tracking and new-intake smoke checks. Record errors without passwords, tracking PINs or private images.

## 2. Human acceptance and accessibility

Execute [UAT-01 through UAT-34](mvp-uat-checklist.md). For this milestone, do not skip UAT-30 through UAT-34: they exercise the new role landings, role/branch metric semantics, collapsed icon labels, contextual metric help and theme consistency. Use appropriate role-based accounts and a separate anonymous customer session. For each scenario, record role, test machine/browser, actual result, status (Not run / Pass / Fail / Blocked), redacted screenshot/issue ID, tester and retest result. A CI green check does not count as a human sign-off.

Test touch screens at 320/360/390/768/1440 CSS pixels, keyboard-only Tab/Shift-Tab/Enter/Escape, no-JavaScript where supported, and at least one real screen reader (NVDA on Windows or VoiceOver on mobile). For this phase, verify that collapsed navigation labels do not become the only way to identify a link: the accessible name must remain available to the screen reader even when the visual label is hidden. Include staff mobile More menu, no-JavaScript fallback, customer code/PIN, signed issuance, protected quote/cash forms, and sign-out/back-button behavior.

**Block acceptance** if identity/branch checks can be bypassed, customer notes leak, issuance bypasses signed evidence, money/stock ledgers disagree, unpaid pickup succeeds, private evidence becomes public, or keyboard access prevents a critical task.

## 3. Recovery rehearsal — only with a new destination

Before backing up, stop fictional shop activity for a maintenance window: a MySQL consistent snapshot alone does **not** synchronize uploads to independent private evidence folders while uploads continue.

- Create a new access-controlled backup folder outside the web root. Use a reviewed, version-appropriate `mysqldump` command with `--single-transaction --quick --routines --triggers --events --hex-blob` and an interactive test-only account. Avoid putting database passwords on command lines or in scripts. Validate nonempty output and encoding.
- Copy both private evidence folders separately; record SHA-256 hashes of each file with a PowerShell command such as `Get-FileHash -Algorithm SHA256 <file>`. Preserve filenames. Keep backups private and encrypted. Back up the original secret HMAC key separately under authorized secret-handling procedures.
- Confirm that the proposed restore database **does not exist** before attempting anything destructive. Create a uniquely named *new* restore schema, inspect the dump for embedded `CREATE DATABASE` or `USE` statements and rewrite them to that schema before import. **Never restore on top of the source**.
- Restore the private files to **new** directories outside `htdocs`, and configure a separate copy of the application to use the restored database and folders. Protect the copy of the HMAC key rather than publishing it.
- Compare source and restored table names/counts, issued and closed tickets, paid bill totals, ticket status events and custody, referenced signature/condition-photo paths, inventory evidence references and SHA-256 hashes. Test HTTP denial of direct private file access.
- Re-run a customer tracking code/PIN check and role-isolated staff access against only the restored environment, then record the actual elapsed time, errors and approved recovery objectives.

The completed Linux CI rehearsal verified **47 tables, two cash entries totaling PHP 375, customer custody and byte-identical private file directories**. It does not prove that an arbitrary real Windows machine or actively used production database can be restored.

## 4. Human decision register

| Gate | Evidence to collect | Current status |
| --- | --- | --- |
| UAT sign-off | Actual UAT-01..25 results, named testers and retested blockers | **Not run** |
| Staff/screen-reader accessibility | Keyboard and screen-reader test notes and screenshots | **Not run** |
| Windows/XAMPP recovery | Operator log without secrets, SHA-256 comparison, SQL/finance/custody reconciliation, restore duration | **Not run** |
| Merge into `main` | Independent review, current CI, explicit approval for PR #56 only | **Pending** |
| Production deployment | Privacy/security, restore policy and maintenance approval | **Not approved** |

See [MVP test report](mvp-test-report.md), [accessibility review](mvp-accessibility-review.md), [recovery evidence](mvp-recovery-rehearsal.md), and [PR #56](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/56). The optional partial-device transfer [PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55) remains deferred.
