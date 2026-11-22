# RepairTrack — Windows/XAMPP acceptance and recovery handoff

**Prepared:** 22 November 2026. **Status:** INSTRUCTIONS ONLY — no Windows/XAMPP test or real-user sign-off has been performed. This document complements the proven [disposable Linux SQL/evidence restore](mvp-recovery-rehearsal.md) and the [20-case manual UAT checklist](mvp-uat-checklist.md).

## 1. Prepare an isolated fictional installation

Use only a specifically authorized, disposable XAMPP setup with fictional device, contact and receipt records. Record the tested `MVP-Stabilization` commit, PHP and MySQL/MariaDB versions, Windows version, browser, operator and test start time.

1. Make a **new** web folder under `htdocs`, separate from any working project. Copy the reviewed application there; do not reuse real customer data.
2. Create writable private directories **outside** `htdocs`, e.g. `C:\repairtrack-private-test\intake` and `C:\repairtrack-private-test\inventory`. Keep access restricted to the test operator and PHP/Apache account. Confirm that browsing these paths by HTTP is impossible.
3. Create a **new** database such as `repairtrack_test_operator`, and a test-only user. The project's `database/schema.sql` contains `CREATE DATABASE` and `USE repairtrack` statements: review and rewrite *both* to target only the new test database. Never execute the untouched schema against an existing shop database.
4. Copy `config/config.example.php` to ignored `config/config.php`. Set the fictional database, configured private evidence directories and the exact local `app_url`. Generate a fresh private `intake_hmac_key` and keep it secure. Do not commit or screenshot it.
5. Start Apache/MySQL locally, open the test URL and complete the basic login, customer tracking and new-intake smoke checks. Record errors without passwords, tracking PINs or private images.

## 2. Human acceptance and accessibility

Execute [UAT-01 through UAT-20](mvp-uat-checklist.md). Use appropriate role-based accounts and a separate anonymous customer session. For each scenario, record role, test machine/browser, actual result, status (Not run / Pass / Fail / Blocked), redacted screenshot/issue ID, tester and retest result. A CI green check does not count as a human sign-off.

Test touch screens at 320/390/768/1440 CSS pixels, keyboard-only Tab/Shift-Tab/Enter/Escape, no-JavaScript where supported, and at least one real screen reader (NVDA on Windows or VoiceOver on mobile). Include staff mobile More menu, no-JavaScript fallback, customer code/PIN, signed issuance, protected quote/cash forms, and sign-out/back-button behavior.

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

### Candidate-specific read-only SQL and file comparisons

After completing a **manual, fictional new-destination SQL restore**, run the [read-only local SQL reconciliation checker](local-sql-restore-reconciliation.md) against the source and restored `repairtrack_test_*` schemas. It compares table lists, column definitions, row counts and content hashes; it never performs the restore and does not validate foreign-key/triggers/index metadata. Use the Windows file comparator for separate private evidence directories. Neither tool signs off an operator-led XAMPP recovery.

### Supplemental candidate-only file-integrity test

The candidate adds [a read-only Windows fictional evidence comparator](windows-evidence-integrity-check.md) and corresponding Windows-runner synthetic tests. It checks source/restored file names and SHA-256 hashes without copying, deleting or importing files. This is an optional **verification step after manually making a separate fictional restored directory**; it does not itself perform SQL backup/restore, prove Windows/XAMPP compatibility, or satisfy the 20 existing UAT cases. Execute additional [UAT-21/UAT-22](mvp-uat-checklist.md) for storage locations and parts-request roles.

## 4. Human decision register

| Gate | Evidence to collect | Current status |
| --- | --- | --- |
| UAT sign-off | Actual UAT-01..20 results, named testers and retested blockers | **Not run** |
| Staff/screen-reader accessibility | Keyboard and screen-reader test notes and screenshots | **Not run** |
| Windows/XAMPP recovery | Operator log without secrets, SHA-256 comparison, SQL/finance/custody reconciliation, restore duration | **Not run** |
| Merge into `main` | Independent review, current CI, explicit approval for PR #56 only | **Pending** |
| Production deployment | Privacy/security, restore policy and maintenance approval | **Not approved** |

See [MVP test report](mvp-test-report.md), [accessibility review](mvp-accessibility-review.md), [recovery evidence](mvp-recovery-rehearsal.md), and [PR #56](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/56). The optional partial-device transfer [PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55) remains deferred.
