# Staff consoles and quick intake

## Current roles

| Role | Console and allowed actions |
| --- | --- |
| Administrator | Existing admin dashboard, staff management, intake and issuance; Repair operations opens branch queues, technician assignment and internal repair history. |
| Manager | Assigned-branch repair queue, technician assignment, intake drafts and issuance. No staff-account administration or technician private notes. |
| Intake agent | Assigned-branch queue, new intake, draft editing, evidence checks and ticket issuance. |
| Technician | Only assigned devices within active assigned branches. Review stage changes and save private work notes. |
| Courier | Read-only item-level transfer manifests for assigned origin/destination branches, with route, destination storage and handover condition notes. |
| Viewer | Read-only device status and location for assigned branches. No customer contact, intake descriptions, signatures or private notes. |

There is no Cashier role in the current schema. The courier manifest is branch-wide because the existing transfer schema has no personal courier-assignment field. Transfer creation and receipt confirmation remain pending; this page does not claim to complete a handover.

## Screen layout and workflow

All consoles use the same readable PHP view and small shared stylesheet. Desktop: role header and account menu, primary work action, branch list, status totals, searchable paginated queue. Mobile: cards stack and wide tables scroll inside their own container. Account/logout lives in the expandable account menu.

Manager: open device → select a branch technician → review → confirm.
Technician: open assigned device → select an allowed stage and/or enter a private note → review → confirm.
Intake: choose customer → choose device/problem/condition → review with customer → save draft → evidence checks → issue.

## Quick intake menus

Device types include laptop, desktop, all-in-one, tablet, phone, monitor, printer, game console and Other. Other requires a short device name.

Reported problems and visible conditions are separate checkbox groups. Multiple choices are allowed. Notes are optional unless Other is selected. No visible damage must be selected by itself. Staff can still type a description without choosing presets, so unusual cases and old records remain supported.

Menus are observations, not technician diagnoses. Review and confirmation are still required; device menus do not bypass evidence, signature or duplicate checks.

No schema migration is needed. Checked labels are saved in existing reported_symptoms and condition_notes fields using a readable Selected/Details format. Editing restores the choices; older free-text notes stay intact. The shared lists live in app/intake/rules.php. Adding a future choice is a small code change rather than another table.

## Access and repair rules

Every list and device action checks active branch assignment. Technician views additionally check assigned_technician_id. Posted roles and branch IDs do not grant access. Intake endpoints allow only ADMIN, MANAGER and INTAKE_AGENT; write services repeat authorization inside their transaction.

Private technician notes appear only to their author and administrators. Reassigned technicians cannot read the earlier technician's notes. Managers, intake agents, couriers and viewers do not receive them. Saving progress does not send notifications or publish customer-facing updates.

Allowed repair paths are deliberately limited:
- Received → Diagnosing.
- Diagnosing → Awaiting customer approval, Awaiting parts or In repair.
- Awaiting approval / parts → In repair.
- In repair → Awaiting parts or Testing.
- Testing → In repair or Ready for pickup.

Starting In repair requires the latest quote to be approved, except a return from Testing. Devices must be physically at a location for a stage change. Quote preparation/approval UI is still pending. Completion, cancellation and customer handover are excluded because they require their own evidence and payment rules. A private note can be saved without changing stage.

Forms use CSRF protection, expiring one-use review keys, version checks, prepared queries and transactional status/history/audit updates. Failed writes roll back. Older displayed forms cannot silently overwrite newer progress.

## Admin optimization

The admin dashboard now scopes queue, customer ticket totals, drafts and metrics to assigned active branches. The customer roster includes customers with tickets in that scope; the intake customer matcher remains the shared customer registry used to avoid duplicates.

Grouped status counts are reused for the active-repair metric and report, and draft/today-issued totals share one aggregate query. Summary queries fall from five to three. Paginated lists keep their count and page queries. This is a reduction in redundant work, not a production speed benchmark.

The former analytics placeholder now shows actual status totals and links to repair operations. No new framework, table or database column was introduced.

## Code map

- app/console/repository.php: scoped reads, role checks, assignment and repair transactions.
- admin/staff_home.php + views/console/home.php: role-specific console.
- admin/device.php + views/console/device.php: reviewed device actions and private history.
- app/intake/rules.php: menus, round-trip conversion and validation.
- app/dashboard_data.php: scoped summaries and pagination.
- tests/staff-consoles.php: permission, integrity and menu tests.
- tests/consoles-browser.mjs: real role navigation and form tests.

## Testing

Run against a disposable repairtrack_test_ database only. Existing intake, issuance and staff-account regression suites remain relevant. The new backend test creates role fixtures consumed by the new browser test. It checks cross-branch access, unauthorized writes, stale updates, rollback, quote gating, Other descriptions, contradictory condition choices and legacy-note preservation. The browser suite covers all five staff consoles, private-note visibility, assignment, one-use review, CSRF, mobile width and no-JavaScript intake.

Validation on 2026-11-13: PHP syntax, all four database suites and all five Chromium browser suites passed using PHP 8.4 and MariaDB 11.8. A final focused rerun of the console database/browser suite also passed after the mobile layout adjustment. Tests covered both JavaScript and no-JavaScript intake. Live MySQL and production deployment were not tested.
