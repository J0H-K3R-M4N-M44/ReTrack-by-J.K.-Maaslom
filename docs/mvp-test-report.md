# RepairTrack MVP — customer-to-pickup test report

**As of:** 22 November 2026. **Scope:** PHP `main` plus focused UI stabilization in `MVP-Stabilization`. **Environment:** independent GitHub-hosted Ubuntu/Chrome runners, disposable `repairtrack_test_*` MySQL databases, fictional users/records and temporary private evidence. No customer or production data.

## Result and CI evidence

**All targeted jobs passed.** The latest tested application commit is `2821057e`, including focused accessibility checks. The staff/intake and payment/checkout suites were rerun at this code checkpoint.

| Area | Verification | Outcome |
| --- | --- | --- |
| One newly created ticket, full server journey | [MVP workflow run 44996054209](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996054209), `single-ticket-journey` | PASS |
| Quote preparation and staff-recorded approval in Chrome | Same run, `quote-and-tracking-browser (quotes)` | PASS |
| Private customer tracking, no-JavaScript/browser layouts | Same run, `quote-and-tracking-browser (public)` | PASS |
| Admin overview, error states and responsive layouts | Same run, `dashboard` | PASS |
| Cashier, payment/pickup, stock and checkout concurrency regressions | [PHP shop workflow run 44996050302](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996050302) | PASS |
| Intake/issuance, staff isolation, schema migrations, reports and device handovers | [Staff activity run 44996050309](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996050309) | PASS |

## Single-ticket end-to-end acceptance (service layer)

`tests/mvp-single-ticket-journey.php` exercises **one fictional ticket** across module boundaries:

1. Manager creates a draft and cannot issue without the signed acknowledgment. Issuance persists condition evidence and checklist records.
2. Staff shares a private short tracking code secured by the customer contact PIN. The receipt number and an incorrect PIN cannot open the portal.
3. Manager assigns a technician. A branch viewer can read, but cannot modify, device progress. Repair start without an approved quote is rejected.
4. Manager prepares and shares a PHP 375.00 labor-only estimate, then records customer approval. Technician progresses through IN_PROGRESS, TESTING and READY_FOR_PICKUP.
5. Customer tracking reflects the update without private technician notes.
6. Manager issues a final bill. Cashier cannot hand over before full settlement: PHP 150.00 partial payment leaves PHP 225.00 due, then final counter cash clears the balance.
7. Permitted handover closes the ticket, and the same private customer portal displays custody WITH_CUSTOMER. Intake gate records remain present.

The script is CLI-only and refuses any database except `repairtrack_test_journey`. CI initializes the disposable schema and synthetic image evidence. This test does **not** simulate online customer self-approval, card payment or third-party delivery.

## Browser and regression boundaries

Existing regression suites also exercise escaped customer/quote content, stale/replayed submissions, CSRF and unauthorized operations, invalid intake evidence, no-JavaScript fallback, role/branch isolation, multi-device pickup readiness, paid-bill correction/refund controls, parts ledger and migration rehearsal. The overview browser checks 320/390/768/1440px and an injected, temporary dashboard query error.

The first newly scheduled public browser check timed out when a no-JavaScript user attempted to activate the animated **View repair** button. Removing the unnecessary 240ms entry animation from `css/public.css` made the tracking form stationary; the unchanged browser test passed in [run 44996054209](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996054209). Public tracking credentials and authorization were not changed.

## Additional accessibility and recovery verification

- **PASS:** [Run 44996725360](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725360) with all four jobs green at `2821057e`. The public browser job now checks keyboard skip focus, correctly labelled tracking inputs, 44px interaction height, reduced motion, no-JavaScript support and 320–1440px overflow.
- **PASS:** [Staff/intake run 44996725335](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725335) and [payment/pickup run 44996725374](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725374), same application checkpoint.
- **PASS:** [Isolated SQL + private evidence restore run 44996590305](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996590305), 47 base tables with matching counts, PHP 375.00 cash ledger, closed ticket, customer custody, database-linked intake files and matching private-directory hashes. It is a CI-only rehearsal, not a production backup/restore.
- **PREPARED, NOT EXECUTED BY PEOPLE:** [Manual UAT checklist](mvp-uat-checklist.md) includes 20 role-specific acceptance cases with actual-result and approval fields.
- Scope and gaps: [Accessibility review](mvp-accessibility-review.md) · [Recovery rehearsal](mvp-recovery-rehearsal.md).

## Limitations and next safety gates

- A complete **one-ticket PHP service-layer journey** passed. The browser flows were independently tested, not as one continuous browser session switching between every staff/customer role.
- Not certified: production deployment or restore of an actual persistent installation, manual stakeholder acceptance, load testing, full accessibility audit, external integration or security penetration testing.
- Still excluded: customer self-approval, secure chat, COD remittance, payment gateways, SMS/email, GPS and partial-device repair ownership.

**Next practical work:** execute the prepared UAT with actual role testers, complete a screen-reader/keyboard/contrast review across staff consoles, and rehearse an authorized restore on a representative XAMPP deployment with a safe maintenance window. No need to expand roles or partial branch transfers.

[Stabilization scope](mvp-stabilization.md) · [Test workflow](../.github/workflows/mvp-stabilization.yml) · [Single-ticket script](../tests/mvp-single-ticket-journey.php) · [Deferred optional PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55).
