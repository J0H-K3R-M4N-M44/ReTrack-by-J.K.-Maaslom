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
