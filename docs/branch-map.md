# RepairTrack branch map

Updated: 2026-11-13. All development stays off `main`.

`Simplified-System` is the integration branch. The category branches below start from the same tested, consolidated application. They are work areas for future changes, not separate applications or completed backend implementations. Existing features depend on shared authentication, database and UI files, so those files remain available in every branch.

| Branch | Feature ownership | Existing sources consolidated | Main files |
| --- | --- | --- | --- |
| `Code-Readability-Cleanup` | Shared formatting, comments and code guide | `feature/code-readability-cleanup`, `feature/beginner-readable-code` | `.editorconfig`, `.prettierrc.json`, `docs/code-style.md` |
| `Access-Accounts` | Login, logout, password recovery, staff access | Access portion of `feature/simple-access-activity` | `app/auth.php`, `app/password_reset.php`, auth pages in `admin/`, `js/login.js`, `js/trans.js` |
| `Home-Content` | Public homepage content, preparation guidance and FAQs | Current public landing page | `index.php`, `css/landing.css`, `js/landing.js` |
| `Intake-Tracking` | Customers, intake drafts, device tracking | `feature/simple-intake-tracking` | `app/intake/`, `admin/intake_create.php`, `views/intake/`, `js/intake-wizard.js` |
| `Repair-Operations` | Diagnostics, assignments, repair stages, parts requests, testing | Repair portion of `feature/simple-repair-custody` | Workflow functions in `demo-prototype/app.js`; PHP implementation remains planned |
| `Branches-Logistics` | Branch locations, transfers, pickup, delivery and custody | `feature/sogod-branch-maps`; custody portion of `feature/simple-repair-custody` | `demo-prototype/branch-maps.js`, logistics and transfer functions in `demo-prototype/app.js` |
| `Payments-Inventory` | Quotes, bills, cash, refunds, COD and stock | `feature/simple-quotes-cash` | Finance, quote and stock functions in `demo-prototype/app.js` |
| `Notifications-History` | Alerts, conversation visibility and activity history | `feature/repair-alert-delivery`; activity portion of `feature/simple-access-activity` | `demo-prototype/ticket-alerts.js`, `demo-prototype/demo-email.php`, history and message functions in `demo-prototype/app.js` |
| `Dashboards-Reports` | Staff overview, summaries and reporting | `feature/simple-reports` | `app/dashboard_data.php`, `admin/dashboard.php`, demo report functions |
| `Simplified-System` | Integrates tested category work and shared foundation | All above plus `feature/simple-foundation` and the prior `System-Consolidation` baseline | Whole application; `docs/current-vs-simplified.md` |

## What the audit found

The five `feature/simple-*` branches other than foundation had the same commit as `Simplified-System` and `System-Consolidation`. They contained no separate feature implementation to extract. The maps commit was already an ancestor of alerts, and alerts was already an ancestor of the latest readability cleanup. Re-merging those ancestors would not add any code.

The earlier readability branch diverged by one formatting commit. Its 24 changed PHP/JavaScript files were checked against their common ancestor and had equivalent parsed logic. Where its formatting conflicts with the newer cleanup, the newer cleanup wins. The separate foundation branch contributes the current-versus-planned-system document. The consolidation commit preserves both divergent branches in its parent history.

The original `feature/` branches are legacy references, retained until they can be removed using an authenticated branch-management interface. Use the names in the table for new work. `System-Consolidation` remains a historical baseline. `main` remains unchanged.

## Working rules

1. Choose the category for the behavior being changed. Shared files may serve more than one category.
2. Use the settings from `Code-Readability-Cleanup`: four spaces in PHP; two in JavaScript, CSS and HTML; expanded function bodies and useful comments.
3. Test the changed workflow and format the affected files before merging into `Simplified-System`.
4. Bring tested shared changes back into category branches as needed. Avoid maintaining different copies of authentication or the schema.
5. Review database changes separately. This consolidation does not change tables, columns or stored data.

The current PHP backend supports shared staff authentication, branch-scoped administrator staff management, dashboard queries, one-device draft intake and ticket issuance. The fuller repair, cash and logistics workflows remain demonstrations. A category name does not mean its production backend is finished.



## Staff consoles and quick intake update

The shared tested update belongs to Dashboards-Reports (staff consoles/admin summaries), Repair-Operations (assignments/progress) and Intake-Tracking (menu-driven intake and intake-role permissions). These category branches share this integration point because the permissions and console links depend on the same code. Main remains unchanged. See [staff consoles](staff-consoles.md).
