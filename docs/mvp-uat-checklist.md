# RepairTrack MVP — manual user acceptance checklist

**Prepared:** 22 November 2026. **Status:** READY FOR HUMAN TESTING, **NOT** user-approved. These are test instructions, not claims that real customers or staff accepted the product.

## Environment and evidence

Use a **fictional-only** clean XAMPP/PHP/MySQL environment based on `MVP-Stabilization`, with a database restored from a tested disposable seed, private intake evidence and stock evidence folders **outside** htdocs, and role-limited test accounts. Do not enter real customer contacts, passwords, signatures or payment details. Keep the approved requirements, version/commit, PHP/MySQL versions, device/browser, date and tester role in the run log.

**Mark each scenario:** Not run / Pass / Fail / Blocked / Not applicable. Capture observed result, any error, screenshot *without secrets*, bug link, and retest date. "Pass" requires observing the actual result, not merely reading a CI result.

## Staff and customer acceptance cases

| ID | Role | Task and tester action | Expected outcome |
| --- | --- | --- | --- |
| UAT-01 | Administrator | Log in, open overview, use New intake, Review repairs and Cash desk | Correct destinations, live metrics or explicit unavailable state; no unrelated tab content |
| UAT-02 | Intake staff | Search an existing customer; attempt empty name/invalid contact; correct and save draft | Errors are specific and recoverable; customer is not silently duplicated |
| UAT-03 | Intake staff | Create a device draft; attempt issuance without signed terms, photo or checks; then provide fictional evidence | Bad issue rejected without partial ticket; complete issue shows receipt and a stored device |
| UAT-04 | Manager | Assign technician; attempt assignment outside branch or above configured active limit | Eligible assignment succeeds, ineligible assignment denied with an explanation |
| UAT-05 | Technician | Diagnose device and enter customer update plus separate internal note | Manager sees appropriate details; customer sees only customer-facing status and message |
| UAT-06 | Technician | Attempt IN_PROGRESS before quote approval | System rejects start and preserves previous status |
| UAT-07 | Manager | Prepare, review, share and record an explicitly confirmed customer response to a fictional estimate | Correct peso amount, revision and approval audit; no automatic customer self-approval |
| UAT-08 | Technician | After approved quote, move through IN_PROGRESS, TESTING, READY_FOR_PICKUP | Allowed order and timestamp; customer timeline reflects visible stages |
| UAT-09 | Customer | Try receipt code as tracking secret, wrong PIN, then correct shared short code plus four-digit PIN | Invalid access rejected without leaking ticket details; valid code opens only that customer's repair |
| UAT-10 | Customer | Use tracking page, timeline, Close tracking, refresh and Back to public home | Readable device custody; no private notes, serial/contact or financial data leaks |
| UAT-11 | Cashier | Search by ticket code, open bill, record partial counter cash, attempt handover | Correct balance and receipt; handover blocked until fully paid |
| UAT-12 | Cashier | Collect remaining counter cash, confirm recipient and handover | Ticket closes, custody changes to customer, and portal shows returned device |
| UAT-13 | Manager / Cashier | Attempt a repeated receipt, stale form, second handover, and refund beyond amount due | No duplicate money or handover entries; understandable stale/denial message |
| UAT-14 | Courier / Manager | Use existing **whole-ticket** custody dispatch and receiving workflow on a fictional manifest | Custody and repair ownership are not confused; receiving staff cannot silently acquire billing rights |
| UAT-15 | Viewer / unrelated branch | Try direct deep links to customers, private evidence, quotes, bills and another branch's repair | Only authorized, scoped data visible; forbidden changes and direct links rejected |
| UAT-16 | Staff | Sign out and use browser Back to access a private view | Session is invalid and protected content is not reopened |
| UAT-17 | Customer / Staff | Re-run essential scenarios at 320, 390, 768 and 1440 CSS pixels, with and without JavaScript where supported | No page-level horizontal scrolling or hidden primary controls; key actions remain available |
| UAT-18 | Keyboard tester | Tab/Shift-Tab through tracking, login, quote and staff queue; activate buttons with Enter/Space | Visible focus, logical order, proper labels, no keyboard trap; skip link reaches main content |
| UAT-19 | Screen-reader tester | Navigate each page by landmarks/headings/labels; deliberately trigger validation errors | Status/error changes have meaningful announcements; descriptions and inputs are connected |
| UAT-20 | Staff | Disconnect database in an isolated test; revisit dashboard | Shows an unavailable state rather than misleading zero or a green connection indicator |

## Candidate-specific manual scenarios (additional, not substitutions)

These two scenarios supplement UAT-01 through UAT-20. They have **not been performed** and must be executed on the exact candidate commit with fictional users and evidence.

| ID | Role | Tester action | Acceptance condition |
| --- | --- | --- | --- |
| UAT-21 | Manager, intake agent, unrelated branch | In an isolated branch with no active bin/workbench, attempt fictional issuance; create a bin with assigned manager; retry issuance; attempt setup as intake agent and a manager from another branch | Issuance first blocks with actionable feedback, then newly created active bin is available; unassigned branch and unauthorized roles are denied; no ticket created by a failed attempt |
| UAT-22 | Assigned technician, manager, unrelated role | Submit an internal part need, acknowledge and resolve it, decline another request, then replay an old decision and inspect stock/quote/billing records | Correct role/branch visibility and request states; stale replay denied; **no procurement, inventory, payment, customer approval, or repair stage changed automatically** |

Windows operator addition: run the **read-only, fictional** comparison described in [Windows evidence integrity check](windows-evidence-integrity-check.md). Record the exact candidate SHA, Windows and PowerShell versions and a redacted evidence reference. It verifies relative names and SHA-256 only; the **SQL restoration and human checks above remain unsigned**.

## Run log (copy per scenario)

| Field | Tester entry |
| --- | --- |
| Scenario ID / test date | |
| Role / browser / viewport / assistive technology | |
| Build SHA and disposable database revision | |
| Expected and actual outcome | |
| Result (Not run / Pass / Fail / Blocked) | |
| Evidence screenshot / issue ID (redacted) | |
| Retest result and approver | |

## Decision gate

A release candidate is **blocked** if customer privacy/access control, incorrect cash balances, partial issuance, stock integrity, private evidence exposure or premature device release fails. Layout/focus issues affecting task completion must also be repaired. No "accepted" status may be recorded until named testers actually execute and approve these scenarios.

**Next operator instructions:** [Windows/XAMPP handoff](mvp-windows-acceptance-handoff.md). The UAT result cells above are intentionally blank.

**Automated support:** [MVP test report](mvp-test-report.md); [recovery rehearsal](mvp-recovery-rehearsal.md). Automated CI passing is supporting evidence only, not UAT sign-off. Defer optional partial-device branch transfers per [MVP scope](mvp-stabilization.md).
