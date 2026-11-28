# RepairTrack — Role Overviews and Icon Help

**Working branch:** `MVP-Role-Overviews-2026-11-26`  
**Visual reference:** the inspected staff-workspace screenshot batch from the 25–26 November UX pass  
**Scope:** role-specific staff landing content and contextual icon help. The shared workspace shell and its themes remain the visual baseline.

## Design guardrails

- Keep the current workspace shell, navigation dock, typography, spacing rhythm and Ember/Tide/Linen theme tokens.
- Admin continues to use the existing operations dashboard. Other staff roles start at a tailored overview inside the existing staff workspace.
- Reuse existing role- and branch-scoped repository functions. Overview counts and previews are not authorization checks; page controllers and write handlers remain authoritative.
- Do not introduce new schema, payment modes, customer permissions, repair states, automatic priority/ETA logic or new operational workflows.
- Use concise labels and explanatory help only where a count or icon could be misunderstood.

## Role landing pages

| Role | Overview focus | Useful destinations |
| --- | --- | --- |
| Manager | Open repairs, customer decisions, parts waits and ready-for-pickup devices | Intake, repair queue, saved drafts and cash desk |
| Intake Agent | Saved drafts, open issued repairs and received devices | New intake, saved drafts and repair queue |
| Technician | Assigned workload, remaining capacity and assigned work waiting for parts or customer decisions | My repair queue, status board and repair history |
| Courier | Open handovers and transfer history | Transfer manifest, device handovers and transfer history |
| Cashier | Count of non-draft bill records on issued repairs, with an immediate bill lookup | Cash desk and ticket/bill search |
| Viewer | Read-only branch repair totals and current stages | Repair queue, status board and history |

Manager, intake, technician and viewer repair summaries use the existing branch-scoped `consoleDevices()` query. Technician results remain restricted to devices assigned to that technician. Draft counts use `consoleDrafts()` and are personal to the signed-in user; cashier counts and lookup use `consoleCashDesk()`, which counts non-draft bills attached to issued tickets; courier summaries use the existing branch-assigned `consoleTransfers()` query. No customer contact details or private repair notes are added to the Viewer overview.

## Icon and help behavior

- Navigation icons now use distinct symbols for queue, history, activity, handovers, locations, inventory, parts transfers, stocktaking, costs and learning.
- Visible link text and explicit accessible names remain in the markup. The native `title` text remains a fallback.
- When the desktop sidebar is collapsed, hover or keyboard focus displays a small floating label beside the icon. It is decorative; the link's accessible name does not depend on the tooltip.
- Overview metric help uses native `details` disclosures. The explanation opens on touch/click, mouse hover or keyboard focus and closes on pointer leave, focus leave or Escape.
- The existing seven-day trend tooltip keeps its hover, focus, tap and Escape behavior.

## Verification

The phase extends the existing disposable-database and browser regression suites:

1. `php tests/staff-consoles.php`
2. `php tests/staff-workspace.php`
3. `node tests/workspace-browser.mjs` with `REPAIRTRACK_TEST_URL`, `REPAIRTRACK_TEST_SITE` and `CHROME_PATH` set to a local test server.

The browser suite covers each non-admin role, role-specific overview content, authorized destinations, manager queue/board/history, cashier bill lookup and denial of direct device access, help-tip pointer/keyboard/touch interaction, collapsed-nav labels and narrow layouts. Screenshots are captured using fictional fixture data.

## Acceptance boundaries

Automated CI and screenshot review do not replace human use. Still required before release are Windows/XAMPP operator testing, hands-on phone/touch use, a real screen-reader walkthrough, and hosted-environment verification. No WCAG conformance claim is made from automated browser checks alone.
