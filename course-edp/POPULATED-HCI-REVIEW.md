# Populated-workflow HCI checkpoint — RepairTrack EDP

**Date:** 2026-11-23 (Asia/Manila)  
**Isolated branch:** `EDP-Course-MVP`  
**Implementation SHA:** `8bcc33496390494207b0ca3e310e091c51634116`  
**Exact-SHA CI:** [run #45138179914](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45138179914), **success** for both `schema-integrity` and `windows-notepad-build`  
**Screenshot evidence:** [GitHub Actions artifact](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45138179914/artifacts/14800452542) (48 PNG files, artifact retention 14 days)

## What was actually tested

The end-to-end `tests/full-journey-http.py` issues a **fictional** two-device ticket through live localhost PHP form submissions, then completes assignment, diagnosis, quotation publication, manager staff-attested offline approval, repair progress, cashier billing, two cash payments and final counter pickup. No test code synthesizes approved quotations, payments or status events directly in SQL.

The test optionally emits a `0600`, runner-temp-only, synthetic-credential handoff. `tests/browser-hci.py` reuses those authorized staff accounts and existing sessions, avoiding extra staff sign-in attempts against the legitimate single-IP throttle. The handoff is never stored under `public/` or uploaded in the screenshot artifact and is removed by CI's EXIT trap.

Real headless Chromium at **375 / 768 / 1366 px** checks page-wide horizontal overflow and missing labels on required controls, and captures:
- intake / manager / technician / cashier sign-in and home views;
- customer tracking sign-in and authenticated EDP lab;
- populated technician device detail **and completed history**;
- branch-authorized manager's **approved quotation in read-only closed repair detail**;
- populated cashier ticket/bill/payment history and an actual staff-authorized cash receipt;
- customer completed two-device repair timeline, with private diagnosis and cashier receipt text absent.

**Evidence type:** browser DOM/render and screenshot tests against a disposable MySQL-backed PHP app. This is *not* real hardware testing, independent customer consent, or a production security audit.

## Defects found and corrected

1. **Unnecessary login attempts in test:** A first version added new sessions after the existing HTTP and browser sign-ins, exceeding the existing IP login throttle. Changed browser acceptance to reuse authenticated journey staff sessions; the security limit remains unchanged.
2. **Manager historical detail inaccessible:** The manager detail query excluded closed tickets. Now the branch-authorized manager can read the closed repair detail and approved quote, but cannot reassign/send/reapprove via forms. Tests verify an attempted direct assignment POST is rejected, and another-branch manager receives a non-revealing 404. The manager *queue* still excludes closed repairs; a discoverable archive navigation is not implemented.
3. **Actual tablet overflow:** Populated technician detail measured **796 px document width at 768 px viewport**. The layout now stacks detail and timeline panels below 900 px and wraps long values. The same automated viewport assertion passes at 375, 768 and 1366 px.
4. **Obsolete quotation hint:** The technician detail previously said manager approval would be connected in a future milestone. It now accurately describes manager publication and staff-attested offline decisions.

## Visual spot-check (not exhaustive usability sign-off)

After the passing workflow, the actual captured PNGs were inspected for the populated 768 px technician detail and the 375 px manager detail, cashier ticket, cash receipt and customer timeline. The flows show consistent dark/amber styling, a visible repair-start field, quote amounts, itemized bill, payment receipts and a customer-safe timeline. No obvious whole-page clipping was seen in those samples.

**Remaining experience gaps:**
- Two completed devices with seven events each produce a **~3,462-pixel** full-page capture at 375 px; an optional collapsible timeline or clear progress summary should be user-tested before changing historical visibility.
- Cashier data tables and long receipt identifiers are dense at 375 px. Their content is accessible, but touch/reading comfort needs actual phone verification.
- The archived manager detail is reachable by authorized direct link, not a discoverable history queue.
- At this original checkpoint, pending quote and payment-in-progress states were HTTP-tested but not screenshotted. **The Phase 4 intermediate-state extension below now covers both in real Chromium.**
- No 200% browser zoom, keyboard-only traversal, physical device, screen reader, screen magnifier or independent user task study has been completed.
- The Windows `FileSystemWatcher` was built in CI, **not** manually demonstrated; live external REST connectivity has not been shown.

## Acceptance decision

**PASS:** exact-SHA CI and the stated fictional populated Chromium screen assertions.  
**CONDITIONAL:** visual layout for tested screenshots only; broader accessibility and phone usability remain pending.  
**HOLD:** public deployment, customer-authorized billing, legacy 23-table migration, COD, courier, refunds and real-data use.

Follow-up: user-test a collapsible history/summary treatment without discarding audit events, add manager archive discovery if within course scope, and perform manual accessibility checks. Pending/partial-payment browser coverage is addressed in the extension below. Retain the 23-table schema and branch isolation.


## Phase 4 extension — pending quotation and partially paid repair

**Implementation commit:** 3c5dda490b4270419b06d9d47280e95e4bc25721  
**Passing exact-SHA CI:** [run #45159895368](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45159895368), both schema-integrity and windows-notepad-build successful.  
**Updated screenshot evidence:** [57-PNG artifact #14810083448](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45159895368/artifacts/14810083448) (14-day retention).

The **existing** two-device completed ticket, authentic role logins, MySQL constraints, concurrency tests and original browser screenshots remain in place. The full-journey-http.py test now creates two **additional single-device fictional tickets through real application forms**, using its already authenticated intake/manager/technician/cashier sessions. Its read-only SQL inspector confirms the resulting states. It never directly inserts fake statuses, approvals, invoices or payments.

- **Pending quotation ticket:** intake creates a single device; manager assigns; technician diagnoses and drafts a ₱300 quotation; manager publishes it. No approval or invoice follows. The inspector confirms AWAITING_CUSTOMER_APPROVAL, latest quotation AWAITING_CUSTOMER, a send timestamp and no bill. A second-branch manager is denied. Chromium captures the manager's active offline-decision form and the authenticated customer's **pending ₱300 estimate** at 375, 768 and 1366 px. Customer-facing content does not reveal synthetic internal diagnosis, private contact data or receipts.
- **Partial-payment ticket:** separate intake, assignment, technician quotation, manager **staff-attested offline** approval and technician progress through IN_PROGRESS, TESTING and READY_FOR_PICKUP, then a genuine ₱300 bill and ₱120 counter-cash payment. The balance is **₱180**. The cashier sees one receipt, the outstanding amount, a blocked pickup notice and no pickup button. A direct pickup form POST with the correct recorded phone and staff attestation is rejected with HTTP 422. Read-only SQL confirms the open ticket, device at the branch, issued bill and one payment. Chromium captures the populated payment desk at all three widths.

Chromium also asserts no page-wide horizontal overflow or missing labels on required controls at these viewport widths. The extension adds **9 PNGs**, for **57** evidence images in total.

**Evidence limitations:** headless browser assertions and screenshots with fictional records do not replace independent visual review of every image; staff-recorded approval is not customer-authenticated consent. Actual phones, screen readers, 200% zoom, physical file monitoring, live third-party REST and secure hosted deployment remain outside this gate.

**Decision:** **PASS** for two intermediate-state automated cases at the exact tested source commit; **CONDITIONAL** for human HCI accessibility and business consent; **HOLD** for production deployment, original-site merge and real customer data.


## Phase 4 follow-up — safe fixture and keyboard recovery

**Implementation checkpoint:** `098abf740187a272b86916f52dc84c4e3d73ac35`  
**Exact-source CI:** [run #45161776895](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45161776895), both jobs passed.  
**60-PNG screenshot set:** [artifact #14812570303](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45161776895/artifacts/14812570303), expires December 7, 2026.

### Safer disposable fixture

The CLI fixture now refuses use without the explicit `REPAIRTRACK_EDP_CONFIRM_FRESH=I_CONFIRM_NEW_DISPOSABLE_REPAIRTRACK_EDP` opt-in, local loopback DB host and exact `repairtrack_edp` environment values. Its `preflight` and `create` paths independently require empty operational tables and only the fictional `SOGOD-DEMO` branch, without changing the 23-table application schema. Python also refuses to start without the opt-in and asks the PHP preflight to inspect the actual DB before issuing any HTTP requests. CI explicitly tests refusal **without confirmation** and refusal **after the fictional journey populates the database**. This is a defense against accidental reuse, not a substitute for operator review of database credentials or a guarantee about all possible localhost configurations.

### Authenticated keyboard and error recovery

- **Manager pending quotation:** Chromium follows native Tab order from decision selector to confirmation channel, reference field, staff-attestation checkbox and decision submit. An Enter press with missing required fields cannot submit; the quote stays pending. The form can then be made client-valid **without executing a customer-decision POST**. No customer consent was simulated by this keyboard test.
- **Partially paid cashier:** Chromium enters a validly formatted overpayment of **₱180.01** against **₱180.00** remaining and submits with Enter. The actual PHP server returns **HTTP 422**. An alert explains the overpayment, the single previously recorded receipt and the remaining balance remain unchanged, and pickup stays blocked. The user can edit the value to **₱180.00** and Tab back to the 44-pixel payment button, without recording another payment.
- The prior completed, pending and partial-cash screenshots remain tested at 375, 768 and 1366 CSS pixels. The recovery-error screenshot adds **3 images**, taking the artifact from 57 to **60 PNGs**.

### Remaining gate

These are automated synthetic tests, not a human-submitted manager decision or a real financial transaction. A physically operated phone, full keyboard workflows, 200% zoom, screen reader, native Windows file watcher and live/offline REST acceptance have **not** been demonstrated. Use [MANUAL-PHASE4-ACCEPTANCE.md](MANUAL-PHASE4-ACCEPTANCE.md) for dated observed evidence.

**Decision:** **PASS** for exact-source automated Phase 4 extensions; **OPEN** for manual HCI/EDP sign-off; **HOLD** public deployment, customer consent certification and legacy merge.


## 2026-11-25 follow-up — persisted-state verification after rejected overpayment

**Test-hardening commits:** [`675718d`](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/commit/675718dfe71245ad125f30e624e1b38df985f3dc) expands the fresh-fixture guard; [`3c4902b`](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/commit/3c4902b2461162c9d8d1107f9cf591c593a7f4b7) adds the browser-to-database regression assertion; [`c03aa62`](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/commit/c03aa62ea5f68ef450a88e97c9b5b7214e5824df) makes the static schema check reject any unclassified table. These commits change test support only; application PHP, schema and UI are untouched.

- After Chromium submits **₱180.01** against the **₱180.00** remaining balance and receives HTTP 422, the browser test now invokes the existing CLI inspector in read-only mode.
- It asserts the bill remains **₱300.00**, exactly one payment row remains (**₱120.00**), and the computed outstanding balance remains **₱180.00**.
- It also asserts the same ticket remains **OPEN** and its single device remains **READY_FOR_PICKUP** with **AT_LOCATION** custody. This closes the prior evidence gap where the UI was checked but the database was not re-read after the rejected browser POST.
- The disposable-database guard now explicitly counts `ticket_defects` and `password_reset_tokens` as well as the other operational tables. The 23-table schema remains unchanged; the six reference/catalog tables remain seeded, and the single `SOGOD-DEMO` branch is checked separately.
- `static-schema-check.php` now parses the schema, fixture guard and seed file to prove every schema table is classified exactly once as guarded operational data, the separately checked `branches` table, or one of the six seeded reference catalogs. A future new table that is not classified will fail the source-only CI check.

**Local checks:** `php -l` passed for the updated fixture and updated static schema checker. The fixture-list extraction was exercised against the PHP regex, and a source inventory confirmed 23/23 schema tables are classified with no missing, extra or duplicate entries. The new Python helper/assertion block passed an isolated syntax parse and exact-decimal arithmetic check. **Exact-SHA GitHub Actions for `c03aa62` is still pending verification**; the previously green run #45162227479 covers only the preceding `46d4be2` commit and must not be treated as proof for these changes.

**Decision:** test hardening is implemented, but the new automated acceptance gate remains **PENDING** until the exact-source MySQL/Chromium and Windows build workflow is confirmed green. Manual phone, zoom, assistive-technology, native Windows watcher and live/offline REST checks remain open as recorded in [MANUAL-PHASE4-ACCEPTANCE.md](MANUAL-PHASE4-ACCEPTANCE.md).
