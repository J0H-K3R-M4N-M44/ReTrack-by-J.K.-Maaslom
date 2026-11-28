# Staff storage locations — safe intake unblocker

**Branch:** `MVP-Stabilization`. **Scope:** existing branch bins and workbenches. **Status:** implemented on draft branch; use passing CI evidence for the exact commit and perform a local human acceptance round before release.

## Why this exists

Intake issuance requires a physical storage location of type `BRANCH_BIN` or `WORKBENCH` in its existing branch, but the PHP project previously had no staff-facing way to create one. For branches without a suitable active location, `views/intake/issue.php` displayed an error with no completion route. The new `admin/locations.php` gives authorized ADMIN/MANAGER staff a reviewed form for adding a location within **only** their active assigned branches.

This is *not* branch-creation, location transfer, reassigning ticket ownership, a parts ledger, or a substitute for physical inventory checks. There are no database migrations.

## Staff workflow

1. An administrator or manager opens **Storage locations** from their staff workspace or the Staff page; a missing-location warning on intake issuance also links there when the role permits.
2. Choose an already active, assigned branch, enter a readable name, a unique uppercase code (2–40 letters/numbers/hyphens), and select Branch bin or Workbench.
3. Review the selected branch and details, then explicitly confirm. A successful insert is recorded in `storage_locations` and `audit_events` in one database transaction; the result is included in the paginated assigned-branch list.
4. Return to issuance. The existing issuance logic only offers active bins/workbenches at the intake branch. The original signed terms, evidence, item identity and private storage rules are unchanged.

A manager cannot create a new branch or add a location for an unassigned/inactive branch, even by crafting a request. Intake agents, technicians, cashiers, couriers and viewers cannot access the setup page. Duplicate location codes per branch are refused; a failing audit insert rolls back the location.

## Automated tests and acceptance boundaries

- `tests/storage-locations.php` uses a disposable `repairtrack_test_*` database, after `tests/staff-consoles.php`, and tests validation, role/branch scope, duplicate denial, audit rollback and visibility to intake.
- `tests/storage-locations-browser.mjs` signs in as a fictional manager, reviews/creates a location, checks the roster, invalid CSRF and responsive widths. It runs in the quote/portal matrix of `.github/workflows/mvp-stabilization.yml`.
- Staff acceptance: simulate an *assigned branch with no active valid location* on a disposable database, observe the issuance warning, create a new bin with manager privileges, then successfully issue a fictional draft to that bin. Also test a branch-limited manager and an intake agent's restriction. No production data may be modified for this exercise.

## Explicitly not implemented

No edit/deactivate/rename of locations and no branch-creation action: each could strand existing device custody, invalidate physical handover records or affect stock accounting. Technician skill scheduling, procurement requests, financial reconciliation and COD settlement remain separate scoped work. Do not expand this narrow fix into those workflows until the current MVP security and human acceptance gates are satisfied.
