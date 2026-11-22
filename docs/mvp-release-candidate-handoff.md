# RepairTrack — integrated MVP release candidate (not deployed)

**Candidate branch:** `MVP-Release-Candidate-2026-11-22`, branched from tested parts-request implementation `c17a97ac`, which includes `MVP-Stabilization` at `23261715`. Source under review. **Do not merge, deploy, migrate production, or call this production-ready based on CI alone.**

## What this candidate includes

- Frozen **one accountable branch per ticket** policy; physical custody separate; optional partial-device branch ownership remains in untouched draft PR #55 and is not in this candidate.
- Staff roles and isolated branches, intake/evidence/issuance, technician assignments and stages, versioned quotes and staff-recorded customer decisions.
- Read-only code+PIN customer tracking with latest shared quotation guidance, cashier/partial cash, final bill and no-unpaid-pickup guardrails.
- Physical handover, branch inventory accounting/stock review and private signed/evidence storage with prior Linux CI backup-and-restore evidence.
- New scoped storage-bin/workbench setup to unblock issuance, plus internal technician-to-manager repair-part needs register with audited acknowledge/decline/resolve. Neither manager acknowledgment nor resolution purchases or installs a part.

## Release-candidate evidence (distinguish source tests from actual deployment)

| Gate | Automated evidence | Human/environment status |
| --- | --- | --- |
| Baseline `MVP-Stabilization` | 4-job MVP CI and staff/payment checks passed at `23261715` | No human acceptance |
| Internal parts request | 4-job MySQL/Chrome suite passed at implementation `b4f89e80` (run `45003704313`) | Draft PR #58; no live system |
| Apache defense-in-depth | Synthetic Apache rules passed in isolated run `45003063943` | Draft PR #57 **not included here**, requires actual host review |
| This candidate | Branch-specific full MySQL/Chrome workflow plus synthetic PHP preflight triggered; check exact latest commit before claiming green | Not executed on Windows/XAMPP |
| Hosted pilot | Prior public-web reader and DNS tools could not retrieve usable data; no server config obtained | **Blocked** |
| Manual UAT | 20 test instructions in `docs/mvp-uat-checklist.md` | **Not run** |
| SQL + private evidence recovery | Isolated Linux fixture rehearsal passed previously | **Windows/XAMPP unverified** |

## Run read-only local acceptance preflight

On a new **disposable** copy only, create a `repairtrack_test_*` schema and separate private intake and inventory evidence folders outside both project and document root. Set ignored `config/config.php` with fictional test-only settings including a local `app_url`, a separate 32-byte hexadecimal HMAC key and the two evidence paths. Then run:

```text
Windows PowerShell:
$env:REPAIRTRACK_DOCUMENT_ROOT = 'C:\xampp\htdocs'
C:\xampp\php\php.exe tools\local_acceptance_preflight.php

Linux:
REPAIRTRACK_DOCUMENT_ROOT=/var/www/html php tools/local_acceptance_preflight.php
```

Exit code **0** = local prerequisites checked; **2** = blocked; **3** = warning or missing host boundary. A successful preflight **does not** prove HTTP deny rules, session cookie integrity, installed extensions on Apache versus CLI, MySQL restore, user acceptance or production security.

The script reads local PHP config in its own process but does not print secret values or absolute private paths, connect to MySQL, make network requests, change files, migrate, or edit `main`. It requires a loopback local URL and a fictional `repairtrack_test_*` database as a guardrail. Use `php tests/local-acceptance-preflight.php` for synthetic self-tests.

## Additional SQL comparison evidence

The new [read-only SQL restoration reconciliation](local-sql-restore-reconciliation.md) checks actual row-content equality and rejects a deliberately altered restored fixture with unchanged row counts. This enhances Linux CI recovery evidence; it does not execute the physical Windows/XAMPP restore or certify a production snapshot.

## Windows-specific automated evidence

The candidate includes a PowerShell read-only comparator for **fictional** private intake/inventory evidence, with a Windows CI test for matching, corrupt, missing and extra files and folder isolation. [Instructions and limitations](windows-evidence-integrity-check.md). The synthetic Windows job does **not** execute PHP/MySQL, restore a database or constitute human/XAMPP acceptance. Always record the passing run and exact implementation commit; passing SHA must not be inferred from an earlier branch.

## Windows operator restore gate

[Guarded fictional Windows/XAMPP restore procedure](windows-xampp-fictional-restore.md) is available for a **separate, authorized operator**. Its scripted default is read-only dry-run. Windows CI exercises only dry-run and refusal logic with fake test-only executables; it does **not** run MySQL, PHP, Apache, a real XAMPP restore or any production check. Do not mark Windows/XAMPP restore complete without an operator-attested `-Apply` run on an isolated working MySQL server and independent reconciliation.

## Required operator gates before any promotion

1. [Hosted pilot read-only evidence](hosted-pilot-verification-2026-11-22.md): host owner supplies redacted exact deployment revision, document root/Apache rules, secret/file storage and HTTPS/session configuration. No intrusive live probing.
2. [Windows/XAMPP acceptance and recovery](mvp-windows-acceptance-handoff.md): a named operator executes SQL + private-file backup/restore into a **new**, test-only schema and private folders, reconciles table/cash/custody/evidence checksums, with no real customer data.
3. [20-case human UAT](mvp-uat-checklist.md): staff and customer testers sign off cross-branch denial, customer privacy, quote, cashier, inventory and accessibility; add scoped location creation and parts-request scenarios.
4. Security review of draft PR #57 against the real host architecture. Do not assume `.htaccess` protection if the host ignores its directives.
5. Only after all gates, an authorized owner decides if release should advance. Preserve PR #55 optional research and unmodified `main` until then.

## Stop conditions

Do not approve a candidate with unaudited access or cross-customer/branch leakage, private evidence in served paths, failed actual restore, invalid billing/stock movement, inability to reproduce tests at the exact commit, or a staging host with source disclosure. A green synthetic test is never a security approval.
