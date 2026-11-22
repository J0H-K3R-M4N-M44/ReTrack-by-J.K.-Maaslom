# Read-only SQL restoration reconciliation — isolated test environments

**Branch:** `MVP-Release-Candidate-2026-11-22`. **Release status:** not production-ready; never use this procedure on actual customer data or the hosted pilot.

## What the checker does

`tools/compare_test_restore.php` connects to **two already existing** local test-only MySQL schemas and compares their base table lists, column metadata, row counts and SHA-256-derived content fingerprints. It does not create or drop databases, run migrations, update a row, restore a dump, or display customer records, passwords, SQL errors, or private evidence filenames. It refuses:

- Any schema not starting with `repairtrack_test_`, an identical source and target, or a source not exactly matching the locally configured `config/config.php` test database.
- Database hosts other than loopback/localhost or a configured application URL that is not a local HTTP(S) URL.
- Unexpected table identifiers or any fixture table above 10,000 records.

The comparison is intended only for **small, quiet, disposable fictional databases**. For each row, it calculates a hash of the column name and typed value (including NULL), then compares sorted row hashes to detect changes even when the row count stays the same. It does not read or compare customer files, SQL indexes, triggers, foreign-key definitions, events or user privileges; successful output is evidence for this limited comparison, **not** comprehensive database recovery or production security.

## Operator invocation after manual isolated restore

Prepare the two fictional schemas yourself. Use a freshly restored target that did **not** exist prior to your restoration exercise; do not point any command at `repairtrack` or an existing operational schema. Set the ignored PHP configuration to your original **fictional** source schema, the local test-only database host, and a loopback `app_url`. This command contains no secrets:

```powershell
$env:REPAIRTRACK_DISPOSABLE_RECONCILE = 'I_CONFIRM_DISPOSABLE_ONLY'
C:\xampp\php\php.exe tools\compare_test_restore.php repairtrack_test_operator repairtrack_test_restored
Remove-Item Env:REPAIRTRACK_DISPOSABLE_RECONCILE
```

Exit 0 means the supported, small local SQL data comparison passed. Exit 2 means the comparison was blocked or failed; investigate privately. A quiet period or consistent snapshot is required to avoid false differences when the fictional source remains writable. Keep database passwords and private files outside reports.

## Automated proof and limitations

The existing disposable Linux restore job (`tests/mvp-backup-restore.sh`) runs the read-only comparison after independent SQL and file restoration. It then deliberately changes the **restored test copy only**, leaving table counts unchanged, and requires the comparison to reject the altered content. The altered restore is subsequently discarded. Exact CI SHA and the passing job must be recorded; merely committing a new test does not mean it passed.

Use the separate [Windows PowerShell evidence comparison](windows-evidence-integrity-check.md) for source/restored private files, and [Windows/XAMPP handoff](mvp-windows-acceptance-handoff.md) for actual operator-managed backup, restore, application/access checks and human sign-off.

**Still outstanding:** a real operator-led Windows/XAMPP restore, integrity of constraints/triggers/indexes, consistent cross-file/database recovery during live use, accessibility/UAT, and owner-approved hosting verification. Neither this checker nor CI authorizes live writes or deployment.
