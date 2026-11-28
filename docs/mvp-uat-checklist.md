# RepairTrack MVP — manual user acceptance checklist

**Prepared:** 22 November 2026. **Updated:** 26 November 2026. **Status:** READY FOR HUMAN TESTING, **NOT** user-approved. These are test instructions, not claims that real customers or staff accepted the product.

## Environment and evidence

Use a **fictional-only** clean XAMPP/PHP/MySQL environment running the reviewed commit `5a750799522c594214c90ba2002ce71684b10761` from branch `MVP-Role-Overviews-2026-11-26` (PR #62), with a new database restored from a tested disposable seed, private intake evidence and stock evidence folders **outside** htdocs, and role-limited test accounts. Keep this installation separate from the working project and every production or real shop database. Do not enter real customer contacts, passwords, signatures or payment details. Keep the approved requirements, version/commit, PHP/MySQL versions, device/browser, date and tester role in the run log.

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
| UAT-21 | Manager / Intake staff | Prepare fictional open repairs with different last-update times; inspect Queue and then History | Queue places the oldest last update first and shows its Philippine-time timestamp; History remains newest-first; the order is an attention aid, not an ETA, SLA or automatic priority |
| UAT-22 | Staff | Open the repair queue at 320px and 390px; inspect several records and use an available row action | Each record becomes a readable labelled card; device, branch, technician, status, repair start, last update and action remain identifiable; no page-level or queue-level horizontal scrolling hides content |
| UAT-23 | Manager | Open technician assignment options with fictional technicians below capacity, at capacity and already assigned to the current device; include active repairs waiting for a customer, parts or pickup | Options show active workload and remaining slots; full technicians are unavailable for new assignments, the current technician remains selectable for a no-op, and server-side capacity validation still rejects an over-capacity assignment |
| UAT-24 | Customer / Staff | Open fictional public tracking records in AWAITING_CUSTOMER_APPROVAL and AWAITING_PARTS; test a configured branch phone and a branch without a phone | The two stages and next steps are clearly different; the customer is told to contact the branch for a decision or parts estimate; no supplier tracking or ETA is implied; customer decisions remain staff-recorded; phone is tap-to-call when configured and the fallback is explicit otherwise |
| UAT-25 | Customer | Use public tracking navigation at 320px and 390px with touch and keyboard | Navigation stays in one horizontally scrollable row; every destination remains reachable, targets are at least 44px high, and the page itself does not overflow horizontally |
| UAT-26 | Manager | Select the Linen theme and open the status board at 320px, 390px, 768px and 1440px | Lane and card surfaces use the selected theme's panel/soft colors with readable primary, secondary and border contrast; all status cards remain visible without page-level horizontal overflow |
| UAT-27 | Staff | At 320×568 and 360×900, use the fixed dock and open More; scroll to the bottom of a long workspace page | Four dock targets remain visible and at least 44px high; page content ends above the dock; the More panel stays above it; Escape closes More and returns keyboard focus to the menu trigger |
| UAT-28 | Cashier / Manager | Search a fictional bill and inspect Repair history and Cash desk results at 320px, 390px, 768px and desktop | At phone widths, each record becomes a labelled card with matching field names and actions; at wider widths, table columns remain readable; history remains newest-first and no page-level horizontal overflow occurs |
| UAT-29 | Staff / Screen-reader tester | Navigate the Status board by headings and article names, then compare visual order at 320px and desktop | Board is announced as grouped by status; each lane is named by its status heading; every repair card is named by its device link; the spoken and visual order match |


| UAT-30 | All staff roles | Sign in separately as Manager, Intake Agent, Technician, Courier, Cashier and Viewer; open the default landing page. Also sign in as Admin. | Each non-admin role opens its expected task-focused overview (Branch overview, Intake overview, My repair bench, Transfer overview, Cash desk overview, Branch snapshot). Admin retains the existing Operations overview. Role shell and theme remain consistent; no role sees another role's overview by using browser Back or a stale link. |
| UAT-31 | Manager / Intake Agent / Technician / Courier / Cashier / Viewer | Compare the overview counts and preview records to the same role's authorized queue, drafts, transfer or cash-desk page. Use synthetic records across two branches and at least two technicians. | Manager and Viewer figures stay within assigned branch scope; Technician counts only devices assigned to that account; Intake Agent draft total is personal to the signed-in account; Courier totals use assigned-branch transfers; Cashier count is non-draft bills attached to issued repair tickets, not cash collected or total due. Viewer remains read-only and shows no private notes or customer contacts. |
| UAT-32 | Staff / Keyboard tester | Collapse the desktop side rail; move the pointer over icons, then Tab to each icon link. Inspect the accessible name with a screen reader. Press Escape while a label is open. Expand the rail and repeat. | Icon labels appear without changing link destinations or role access. Keyboard focus can reach every link and its accessible name remains meaningful. Escape dismisses the floating label. The native title fallback returns after dismissal, and no duplicate tooltip bubbles obstruct the page. On touch screens, no destination depends on hover. |
| UAT-33 | Staff / Keyboard and touch tester | On each overview, open at least two metric help tips using mouse, keyboard focus, click/tap and Escape; move focus/pointer away. Repeat at 320px and 390px and with one non-default theme. | Explanations match the metric's meaning and limitations. The tip opens through the input method used, stays within the viewport, closes on Escape or leaving its interaction area, and does not cover the metric value or primary action. Touch does not require hover. |
| UAT-34 | Staff | Switch between Ember, Tide and Linen themes and the available spacing preference while visiting all six role overviews; reload and navigate to queue, cash desk or transfer pages. | New overview cards, metric help, action links and icons follow the selected workspace tokens and spacing. Text, borders and focus indicators remain distinguishable. Existing shell layout and navigation remain the baseline; no independent redesign or horizontal page overflow appears. |

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

**Next operator instructions:** [Windows/XAMPP handoff](mvp-windows-acceptance-handoff.md). The UAT result cells above are intentionally blank. This checklist now contains 34 scenarios; do not mark any as passed until a human tester executes them on the stated build.

**Automated support:** [MVP test report](mvp-test-report.md); [recovery rehearsal](mvp-recovery-rehearsal.md). Automated CI passing is supporting evidence only, not UAT sign-off. Defer optional partial-device branch transfers per [MVP scope](mvp-stabilization.md).
