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

Review the customer page at 320px, 390px and desktop widths. Check multi-device tickets, timeline labels and timestamps, branch contact, estimate presentation, empty history and long customer-visible messages. Keep customer-facing language separate from staff workflow terms.

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
