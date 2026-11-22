# Windows/XAMPP isolated fictional restore — operator procedure

**Scope:** proposed operator-run verification on a separate local XAMPP installation with **fictional data only**. **Status:** guarded PowerShell dry-run and rejection cases tested in GitHub Windows CI; an actual Windows MySQL/PHP restore has **not** been executed or signed off. See [Windows recovery handoff](mvp-windows-acceptance-handoff.md).

The utility `tools/windows_xampp_test_restore.ps1` is **dry-run by default**. It refuses real database names, reused target schemas, missing confirmation, overlapping evidence directories, existing destination folders and web-root overlap. It requires a test root named `repairtrack-test-*` separate from the project and document root; private MySQL option file and both source evidence folders must reside within this root. Only with `-Apply` does it contact **127.0.0.1** MySQL, generate a fresh SQL dump, create a new test schema, import it, copy fictional files to new directories and invoke the existing read-only SQL and SHA-256 comparators. It never drops either schema or deletes source evidence. On error, a newly created test target is retained for authorized inspection.

## Before any operator execution

Obtain authorization to use a **disposable local instance**. Record candidate commit, Windows version, XAMPP/PHP/MySQL/MariaDB versions and test operator. Use fictional accounts only. Isolate the application folder under `C:\xampp\htdocs` but keep its private intake and stock evidence in separate folders under a new `C:\repairtrack-test-uat01` test root, outside both web and application folders. Never reuse shop customer data, production passwords or other real keys.

Make sure the source test database `repairtrack_test_operator` has already been populated, is quiet for a consistent snapshot and is selected in ignored `config/config.php`, with a loopback `app_url`. Prepare an owner-controlled MySQL option file such as `C:\repairtrack-test-uat01\test-only.cnf` with only `[client]`, the test-only username and password. Protect it with Windows ACLs and **never attach it, quote its values or commit it**. Ensure the test user has permissions needed for a **new** database creation/import. This script executes `mysqldump` with transactional snapshot options, but cannot coordinate concurrent uploaded files; pause test activity.

## Run the non-destructive plan first

From the isolated release-candidate checkout in PowerShell, set an argument map using **only fictional paths**:

```powershell
$params = @{
  TestRoot = 'C:\repairtrack-test-uat01'
  DocumentRoot = 'C:\xampp\htdocs'
  SourceDatabase = 'repairtrack_test_operator'
  RestoreDatabase = 'repairtrack_test_newrestore'
  SourceIntake = 'C:\repairtrack-test-uat01\intake-source'
  SourceInventory = 'C:\repairtrack-test-uat01\stock-source'
  ClientBin = 'C:\xampp\mysql\bin'
  CredentialsFile = 'C:\repairtrack-test-uat01\test-only.cnf'
  PhpExecutable = 'C:\xampp\php\php.exe'
  FictionalDataConfirmation = 'I_CONFIRM_FICTIONAL_ONLY'
}
.\tools\windows_xampp_test_restore.ps1 @params
```

The dry run only checks paths, naming, client binary presence, and file prerequisites. **It makes no MySQL connection and does not certify the host.** After independent review and explicit operator authorization, the separate `-Apply` switch allows the *fictional* backup and restore:

```powershell
.\tools\windows_xampp_test_restore.ps1 @params -Apply
```

Do **not** repeat `-Apply` with the same restore name: the tool intentionally refuses an existing target. If an import fails, do not delete its evidence or retry blindly; record the failure and use a new target after correcting the test setup.

## Verification and limitations

The tool calls `tools/compare_test_restore.php` and `tools/windows_evidence_compare.ps1`. The SQL comparator requires the application config to select the identical fictional **source** database, a local PHP URL and local MySQL. It compares table lists, column definitions, row counts and fingerprints, but it does not fully compare every SQL index, trigger, constraint, privilege, or server setting. The PowerShell comparator checks separate original/restored evidence filenames and file hashes. Both tools' output excludes file names and record data.

**Still manually required**: check restored DB constraints and migration state; check login, branch scoping, cash and stock consistency, public customer code/PIN (fictional), Apache PHP handling, denied private direct-file URLs, error logging, accessibility and all 22 UAT scenarios. Have a real tester sign off restore duration, failures, mitigation and evidence retained under private control. Do not classify CI dry-run tests as a Windows/XAMPP recovery pass.

## CI

`.github/workflows/windows-evidence-integrity.yml` exercises only the path/name/confirmation guardrails, using fake executable placeholders. **No `-Apply` is passed; it never connects to MySQL or accesses the hosted pilot.** Do not treat its green status as SQL restoration evidence.

Release candidate PR #59 remains draft and unmerged pending actual operator/UAT and hosting security approvals.
