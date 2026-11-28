# RepairTrack — UI/UX consistency and workflow refinement

**Phase:** UI/UX Consistency and Workflow Refinement  
**Working branch:** `MVP-UX-Polish-2026-11-25`  
**Review basis:** current branch source, existing mobile browser screenshots, shared staff/public styles, and the current Playwright regression suites  
**Scope:** staff workspace and public customer tracking. This is a focused refinement, not a redesign.

## Design direction

Keep the existing RepairTrack visual identity, PHP views, CSS architecture, role permissions and repair workflow. Improve the consistency of existing screens before adding new visual patterns. The interface should remain understandable and maintainable as a student project.

Use existing semantic colors and theme tokens consistently. Prefer predictable spacing, visible labels, clear status wording, readable data density and responsive layouts. Do not add features that the system does not support.

## Screen audit observations

| Area | Evidence and assessment | Priority |
| --- | --- | --- |
| Staff queue — theme consistency | The November 25 mobile preview showed a dark search control against the light Linen workspace. The legacy `.staff-page input:not(...)` selector was more specific than the original queue-only theme override. This made the control look unrelated to the selected theme. | High |
| Staff workspace — keyboard focus | The shared shell had focus styling in some components, but the focus color was inherited from the legacy amber palette rather than the selected workspace theme. A shared theme-aware focus indicator is more consistent across Ember, Tide and Linen. | High |
| Customer tracking — narrow navigation | The 320px preview showed the final navigation label clipped at the right edge. Horizontal scrolling technically preserved access, but hid part of a link label in the initial view. | High |
| Customer tracking — status language | The current stage and next-step message now distinguish a customer decision from a parts wait. This is good customer-centered wording. Preserve the separation between current stage, custody, estimate and next step. | Keep |
| Customer tracking — estimate and capability boundaries | The portal clearly says the estimate is not a bill or payment, staff record customer decisions, and updates are not live GPS. Keep these explanations; do not imply self-approval, online payment, chat, supplier tracking or an ETA. | Keep |
| Staff queue — mobile records | Stacked labelled cards are appropriate for reading individual repair records on narrow screens. Keep the desktop table for comparing consistent fields. | Keep |
| Staff queue — ordering | Oldest-last-update-first is useful as an attention aid, not a priority score or SLA. Keep the explanatory note. | Keep |
| Mobile fixed dock | The page reserves bottom space for the fixed dock. Automated screenshots do not establish that the dock is comfortable during real one-handed use or that it never obscures content during scrolling. | Human check |
| Multi-device tracking | Each device currently has its own stage and next-step heading. This keeps information tied to the correct device, although repeated headings may feel long on multi-device tickets. Do not combine devices unless the resulting grouping remains unambiguous. | Medium |

## Implemented refinement batch 1

1. Staff text inputs, selects and textareas inside the themed workspace now use `--rt-text`, `--rt-panel`, `--rt-line` and `--rt-muted`. The new selector deliberately outranks the legacy dark form defaults; it does not alter older staff pages outside the themed workspace.
2. Staff keyboard focus now uses the selected theme's accent token.
3. Customer navigation uses a smaller secondary-navigation label size and tighter gaps on phones. The horizontally scrollable fallback remains, but all three current links should fit at 320px and 390px.
4. Browser regression coverage checks the customer navigation for clipping and checks the real staff queue search/filter computed colors in the Linen theme.
5. The shared workspace design stylesheet is included in the existing workflow's path trigger so future changes continue to run the established regression gate.

## Next small implementation groups

### Batch 2 — shared staff controls

Audit actual intake, device, estimate, cashier and staff-management forms for consistent labels, helper text, required-field indication, validation placement, button hierarchy, disabled-state explanation and confirmation behavior. Change only components with demonstrated inconsistencies. Preserve server-side validation and existing post/redirect behavior.

### Batch 3 — public tracking hierarchy

**Implemented; automated verification pending.** Group each device's information under a numbered device heading, improve estimate and long-message readability, and clarify the global newest-first timeline with a device identifier and machine-readable Philippine-time timestamp. Keep branch phone contact tap-to-call, with a clear fallback when no number is configured.

### Batch 4 — responsive operational views

Review queue, status board, history, cash desk and activity views at phone, tablet and desktop widths. Check fixed-dock obstruction, table/card reading order, filters, pagination, empty/error states and keyboard operation.

### Batch 5 — accessibility and consistency acceptance

Run keyboard-only checks across the real staff forms and public portal; verify visible focus in each theme, 200% zoom/reflow, contrast, screen-reader labels/announcements and dialog focus behavior. Human UAT and screen-reader review remain separate from CI.

## Acceptance rules

- Work only on the existing UX branch and draft PR #61.
- Keep `main` untouched; do not merge or mark the PR ready based on automated checks alone.
- Make one small, reviewable group at a time.
- Add a regression assertion for each repeatable UI behavior changed.
- Run the existing workflow and inspect screenshots after each visual group.
- Do not change database schema, permissions, customer decision rules, financial/custody behavior or public capabilities as part of a visual refinement.
- Keep manual UAT, Windows/XAMPP recovery, screen-reader review and hosted verification as explicit gates.

**Current limitation:** this is a source and screenshot audit of the reviewed staff queue and public tracking screens, not a complete visual inspection of every page in every role. Items marked for later review are not certified as passing. Automated browser checks are not human usability acceptance or WCAG conformance certification.


## Batch 2 — shared staff form consistency

**Audit finding:** the inspected forms already use visible labels, grouped fieldsets, review-before-save steps and explicit confirmation for important cash/access actions. The inconsistency was at the control level: legacy staff pages, estimate/cash pages and the custom intake wizard did not share one minimum field size, text-area rhythm or consistent invalid/disabled feedback.

**Implemented:**
- Standardized staff text inputs, selects and textareas to a minimum 44px control height; textareas retain a larger readable minimum. Checkbox/radio controls keep their separate native sizing.
- Standardized visible focus, invalid-field borders and disabled-control feedback for staff forms.
- Applied the same field sizing and invalid/disabled states to the standalone intake wizard.
- Increased intake helper text slightly for comfortable reading without changing the help content.
- Added browser checks for intake customer/device steps, quote-editor fields, technician progress fields and cashier amount entry at phone widths.
- Enabled the existing payment/pickup and staff-activity workflows on the active UX branch so the form changes are exercised in their existing isolated environments.

**Behavior preserved:** no controller, PHP validation, POST intent, CSRF token, review token, database query, quote approval, technician assignment, payment, inventory or custody code was changed. Native and server-side validation remain authoritative.

**Batch 2 verification:** PASS on isolated CI. The MVP dashboard/quote/public/full-lifecycle workflow passed at code commit `8834636` ([run #45629176424](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45629176424)); the payment/pickup workflow passed at the same code commit ([run #45629176391](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45629176391)); staff-activity, mobile navigation/accessibility, seeded reports/migrations, intake search and issuance browser checks passed after updating two stale assertions in `tests/activity-browser.mjs` to match the current category-count header design ([run #45629643938](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45629643938)). The two commits after `8834636` changed only that test file; the production CSS and forms were unchanged. This is automated verification, not human usability or screen-reader acceptance.


## Batch 3 — customer portal hierarchy

**Audit basis:** the current public tracking view, public tracking repository function, customer CSS, and existing PHP/Playwright suites. The controller's customer data query already limits returned device columns and filters the timeline to customer-visible events. The UI refinement therefore keeps the public data contract unchanged.

**Implemented:**
- Added a clearly named **Devices on this ticket** section. Each device now has a distinct numbered heading and keeps its own current stage, recorded location/handover state, current shared estimate, next step and approved public summary together.
- Kept the repair timeline globally newest-first across the ticket. Each event now has a stronger heading, an explicit device item code and a machine-readable `datetime` value carrying the Philippine `+08:00` offset. The visible time remains in Philippine local time.
- Improved estimate hierarchy without changing its meaning: current response state/revision and estimated total are easier to scan, while the page still states that an estimate is not a bill, receipt or balance due and cannot be approved or paid here.
- Improved wrapping and line spacing for long customer-visible messages while retaining line breaks and HTML escaping.
- Kept branch phone contact tap-to-call and gave the link a branch-specific accessible name. The existing explanatory fallback remains when a phone number is absent.
- Extended the isolated PHP fixture with a second synthetic device and long public update. Browser assertions cover multi-device grouping, timeline/device association, timestamp validity and offset, no private-note leakage, read-only device details, branch contact fallback/link behavior, and no horizontal overflow at 320, 390, 768 and 1440 pixels.

**Behavior deliberately unchanged:** no public repository/query permission changes, no customer self-approval, chat, SMS/email, GPS, ETA/SLA, supplier tracking, COD or online payments. Device details remain read-only. The timeline remains a saved staff-update feed, not live tracking. No schema, controller, estimate-decision, payment or custody code was changed.

**Batch 3 verification:** the code and regression assertions are committed. The branch push workflow result and screenshot artifact still need inspection; automated verification must not be marked passed until those results are available. Human UAT and screen-reader acceptance remain separate gates.


## Batch 4 — responsive operational views: status-board theme slice

**Implemented on the UX branch:**
- Replaced the status board's fixed dark lane/card surfaces with the existing workspace theme tokens. Lane surfaces use the panel token; cards use the soft-surface token; borders, primary text and secondary lane counts use the corresponding line/text/muted tokens. This fixes the contrast mismatch when Linen is selected without introducing a new visual system.
- Extended the existing isolated manager browser journey to inspect the actual board in Linen at 320, 390, 768 and 1440 CSS pixels, verify the computed lane/card colors and text, and capture `mvp-board-mobile.png` for visual review.
- Added the board screenshot to the existing MVP workflow artifact upload. No new workflow or CI service was introduced.

**Scope and remaining review:** this is the first focused Batch 4 slice, not completion of the full responsive audit. Queue, activity and cashier flows already have focused mobile checks in their existing suites; status-board rendering now has a targeted theme/overflow regression. Still to inspect manually are fixed-dock obstruction, board reading order with assistive technology, and the complete history/cash-list experience across roles. UAT-26 covers the status-board theme and viewport checks. Human screen-reader and Windows/XAMPP acceptance remain separate release gates.


## Batch 4 — remaining responsive operational views

**Fixed mobile navigation**
- Replaced the implicit column-auto-flow override with four explicit, equal-width dock columns. Dock labels can wrap inside their own target instead of widening the page or being clipped.
- Capped the mobile More panel using the viewport height minus space for the dock and safe-area inset. This keeps the page menu above the fixed dock on short phone screens.
- Added browser assertions at 360×900 and 320×568 for fixed positioning, viewport-bottom placement, four target sizes, label fit, final-content clearance, menu/dock separation, and Escape focus return. Role-specific menu destinations are still checked for Admin, Intake, Technician, Courier and Viewer.

**Repair history and cashier search**
- Added a cancelled synthetic device to the disposable public-browser fixture so the real Repair history route has a deterministic row. The fixture contains no real customer data and does not alter product schema or permissions.
- The public browser test checks history at 320, 390, 768 and 1440px: row/card transformation, matching visible field labels, table/row/cell/header roles and no page overflow. It captures `mvp-history-mobile.png`.
- The payment browser test checks the actual cashier bill search result at 360 and 320px, verifies every card label maps to its table column, preserves semantic roles, captures a cashier cash-list screenshot and then continues through the unchanged payment and pickup flow.
- The PHP staff-workspace regression now uses two synthetic closed records with different update times and asserts that History remains newest-first.

**Status-board reading order**
- Each status lane is a named group whose accessible name comes from its visible status heading.
- Each repair article is named by a real `h4` heading containing the device link. The heading is the first item in each card's DOM reading order; details follow it.
- Browser assertions check heading/name relationships and source order; mobile layout checks check that lane order remains visually consistent. This is structural regression coverage, not a substitute for an NVDA/VoiceOver walkthrough.

**Release boundary:** the changes affect only presentation, semantic labels and synthetic test fixtures. They do not change roles, branch scope, repair decisions, cash, custody, database schema or deployment. UAT-27 through UAT-29 remain unexecuted by human testers.
