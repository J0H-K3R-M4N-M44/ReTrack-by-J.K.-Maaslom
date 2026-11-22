# Windows evidence integrity check — synthetic test only

**Candidate:** `MVP-Release-Candidate-2026-11-22`. **Status:** utility for local operator checks; it is **not** a database backup, database restoration, browser security test or human acceptance sign-off. No real customer files are authorized.

## What the Windows utility checks

`tools/windows_evidence_compare.ps1` reads four existing folders in an operator-controlled, explicitly named `repairtrack-test-*` folder that is separate from the selected web document root:

- source fictional intake evidence and its separately restored copy
- source fictional inventory evidence and its separately restored copy

It fails closed if the confirmation is missing, folders overlap or are under a public document root, sources contain no files, or the relative file paths, counts or SHA-256 hashes differ. It rejects detected symbolic links and reparse points. The process is read-only: it does not create backups, overwrite files, connect to MySQL, make HTTP calls, read site passwords, or print any file names or checksums. **File path safety is an additional precaution and not a filesystem ACL audit.**

## Disposable Windows operator example

Only after creating a **separate fictional** source and manually restored directory pair on an isolated Windows computer, open PowerShell and run from the project folder:

```powershell
$acceptance = 'C:\repairtrack-test-uat01'
$webroot = 'C:\xampp\htdocs'

.\tools\windows_evidence_compare.ps1 `
  -TestRoot $acceptance `
  -DocumentRoot $webroot `
  -SourceIntake "$acceptance\intake-source" `
  -RestoredIntake "$acceptance\intake-restored" `
  -SourceInventory "$acceptance\inventory-source" `
  -RestoredInventory "$acceptance\inventory-restored" `
  -FictionalDataConfirmation 'I_CONFIRM_FICTIONAL_ONLY'
```

Source/restored directories must all be distinct and inside `$acceptance`, whereas `$webroot` must not contain or be contained by that test root. Never reuse `C:\repairtrack-private` production evidence folders. **Exit 0** means synthetic relative file names and hashes match; **exit 2** means blocked. The command does not create or populate directories.

The automatic Windows test (`tests/windows-evidence-compare.ps1`) creates its own temporary synthetic fixture, checks matching files, intentional corruption, missing/extra files, forbidden placements and missing confirmation, then removes the fixture. It does **not** require XAMPP, PHP or MySQL; a pass therefore must not be described as Windows/XAMPP restoration.

## Evidence record and remaining gates

Record the **exact release candidate commit, Windows/PowerShell version, tester, date, preflight exit code, file comparison exit code and independently secured evidence reference**. Do not put customer data, file names, private paths, hashes, screenshots containing secrets or database dumps into GitHub Actions artifacts or pull-request comments.

A qualified operator must still perform a separate **new-destination** SQL restore with real constraints and foreign keys intact, compare DB table names/row counts, cash journal, current custody, issued tickets and DB-linked evidence references, verify local Apache denial rules, and run 20 human UAT cases. See [Windows/XAMPP handoff](mvp-windows-acceptance-handoff.md), [recovery rehearsal](mvp-recovery-rehearsal.md), and [release candidate](mvp-release-candidate-handoff.md).

**Release gate:** No claims of site security, source-data consistency, recoverability or staff acceptance may be inferred solely from this file comparison.
