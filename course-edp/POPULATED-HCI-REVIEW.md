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
- Actual *pending* quote decision screen, ready-before-pickup cash desk and payment-in-progress states were exercised in HTTP tests, **not** separately captured as populated browser screenshots in this checkpoint.
- No 200% browser zoom, keyboard-only traversal, physical device, screen reader, screen magnifier or independent user task study has been completed.
- The Windows `FileSystemWatcher` was built in CI, **not** manually demonstrated; live external REST connectivity has not been shown.

## Acceptance decision

**PASS:** exact-SHA CI and the stated fictional populated Chromium screen assertions.  
**CONDITIONAL:** visual layout for tested screenshots only; broader accessibility and phone usability remain pending.  
**HOLD:** public deployment, customer-authorized billing, legacy 23-table migration, COD, courier, refunds and real-data use.

Follow-up: user-test a collapsible history/summary treatment without discarding audit events, add manager archive discovery if within course scope, capture pending/partial-payment states, then perform manual accessibility checks. Retain the 23-table schema and branch isolation.
