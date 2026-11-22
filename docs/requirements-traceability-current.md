# RepairTrack — current MVP requirements traceability

**Checkpoint:** 22 November 2026. **Working branch:** `MVP-Stabilization` (draft PR #56). **Authority:** source files on this branch, exact commit CI, approved MVP policy. This is an academic/test build, not a production compliance statement.

Status key: **Implemented/tested** means source plus cited automated coverage, not human acceptance; **Partial** means some workflow exists but its acceptance condition remains unmet; **Deferred** is outside the frozen MVP; **Unverified** needs a real operator/host or manual test. Each item below maps to a code owner and acceptance evidence; no feature is marked complete solely because it was discussed.

## Frozen policy and operational traceability

| ID | Requirement / MVP decision | Status at inspection | Source of truth | Acceptance evidence and remaining gate |
| --- | --- | --- | --- | --- |
| R01 | Staff sign-in, active-user checks, session invalidation, CSRF, role isolation | Implemented/tested; host unverified | `app/auth.php`, `admin/login.php`, `app/console/repository.php` | Staff regression, negative cross-role cases; manual deployment session/cookie review |
| R02 | Branch-scoped intake for one ticket and up to five devices, evidence, checklist and signed acknowledgment | Implemented/tested | `app/intake/*`, `admin/intake_*.php` | `tests/mvp-single-ticket-journey.php`, `tests/ticket-issuance.php`, private-file restore |
| R03 | Technician assignment, notes, diagnosis, stage rules and customer-safe updates | Implemented/tested | `app/console/repository.php`, `admin/device.php` | `tests/staff-consoles.php`, `tests/quotes.php`, `tests/mvp-single-ticket-journey.php` |
| R04 | Versioned per-device estimate, staff sharing and staff-recorded decision before repair | Implemented/tested; **not** customer consent proof | `app/quotes/repository.php`, `admin/quote.php` | `tests/quotes.php`, `tests/quotes-browser.mjs`; UAT-07 |
| R05 | Private customer tracking by revocable code plus PIN; receipt alone rejected | Implemented/tested; entropy and hosting risk | `track.php`, `app/public/tracking.php` | `tests/public-tracking.php`, `tests/public-browser.mjs`; UAT-09/10 |
| R06 | Current customer-visible estimate and device-specific next action | **New implementation, awaiting latest CI/human review** | `app/public/tracking.php`, `views/public/tracking.php` | New privacy/expiry/revision cases in `tests/public-tracking.php`, browser tracking checks; no customer write access |
| R07 | Counter cash only, partial settlement, no unpaid handover, final return | Implemented/tested | `app/payments/repository.php`, `admin/cash.php` | `tests/payment-pickup.php`, `tests/mvp-single-ticket-journey.php`; UAT-11/13 |
| R08 | Physical custody and whole-ticket repair branch acceptance, no implicit transfer of responsibility | Implemented/tested for supported states | `app/console/transfers.php`, `app/inventory/transfers.php` | `tests/device-handovers.php`; UAT-14; unusual transfer recovery needs manual review |
| R09 | Inventory reservations, installation, returns, quarantine, adjustment review and transfer evidence | Implemented/tested in tested paths; operational reconciliation requires acceptance | `app/inventory/*`, `admin/inventory.php` | Payment/stock MySQL CI; physical count and personnel authorization remain real-world gates |
| R10 | Role-scoped staff activity, summaries and reporting | Implemented, partial product depth | `app/console/activity.php`, `app/console/reports.php` | Staff activity CI; full notifications/damage trends not present |
| R11 | Branch management UI; technician skills/availability; parts request/approval workflow | Partial/missing | `app/staff/repository.php`, `app/inventory/*`; inspect before planning writes | Define minimal staff UAT and only implement a blocker after owner/policy review |
| R12 | Account recovery and delivery | Partial / host unverified | `app/password_reset.php`, `admin/forgot_password.php` | Token behavior in source; real `mail()` delivery not verified |
| R13 | Mobile keyboard access, no-JS essentials, readable state/error responses | Automated smoke tests only | `tests/mvp-accessibility-browser.mjs`, `tests/mvp-staff-accessibility.mjs` | Manual WCAG/AT, contrast, errors and responsive UAT-17/19 outstanding |
| R14 | Backup AND restore of SQL and private intake/inventory evidence | CI tested, target platform unverified | `tests/mvp-backup-restore.sh`, `docs/mvp-recovery-rehearsal.md` | 47-table disposable rehearsal passed; Windows/XAMPP operator run unsigned |
| R15 | Authorized hosted-pilot build identification, source/config exposure checks, secrets, TLS and recovery | **Unverified / P0** | `docs/mvp-deployment-security-register.md` | No production approval; validate only with host owner authorization |

## Scope decisions requiring no implementation yet

One accountable branch per ticket; independent physical custody; whole-ticket acceptance only. Partial-device ownership is deferred in **draft PR #55**. No card/e-wallet payments. Cash at counter is implemented; COD collection/settlement requires its own reviewed workflow. Customer self-approval, two-way messaging, cancellation/change orders, QR generation, SMS/email notifications, live maps/GPS and advanced analytics are **deferred**, not silently assumed completed. The longer product backlog must remain separate from MVP acceptance.

## Customer estimate design guardrails

Only the newest per-device revision can be considered. Staff-only `DRAFT` and `SUPERSEDED` are never displayed. A revision must have `sent_at` and be `AWAITING_CUSTOMER`, `APPROVED` or `DECLINED` before its limited total, version, and response status can be shown. Expired waiting periods must not imply approval. Do not expose `quote_id`, prepared user, customer conversation reference or private evidence. A quote is **not** a final bill, payment receipt or payable balance. Existing code/PIN revalidation is the only portal entry; this milestone adds no public mutation endpoint.

## Release acceptance and change discipline

1. Follow `docs/mvp-deployment-security-register.md` to classify P0 exposure, pilot build, host and secrets with **evidence**, without unsafe live probing.
2. Verify these rows against changed source and exact HEAD CI; add negative evidence for estimates, permissions, cross-ticket access, stale revisions and missing-data errors.
3. Execute 20 manual scenarios in `docs/mvp-uat-checklist.md` with fictional accounts, recorded actual results and named approvers.
4. Rehearse private-file/DB restore on a representative isolated Windows/XAMPP install. No production migration, merge or deployment is authorized by this document.
5. Update this table after tests; never substitute “test added” for “test passed.”

**Baseline evidence:** `docs/mvp-test-report.md` and `docs/mvp-windows-acceptance-handoff.md`. PR #56 historical CI is tied to specific source SHAs, not automatically to future commits.
