# RepairTrack: initial workflow

## Purpose and scope
Customers should quickly learn what is happening, where their device was last confirmed, and what they need to do next. Staff should see a short queue and one clear next action.

This blueprint is the implementation target. The current application supports admin login, dashboard data and single-device draft intake only. Wireframe interactions are demonstrations, not live repairs, approvals, chat, payments or QR scanning.

## First working journey
1. Staff signs in and works within an assigned branch.
2. Customer: search before creating a record; collect a name and one contact method.
3. Device and problem: type, brand/model, optional serial, customer description and visible condition.
4. Review: show all entered details; Back retains values; Save draft records the draft atomically.
5. Issue review (next implementation): confirm accessories, physical location and approved acknowledgment/evidence rules. Incomplete records stay drafts. The applied schema still requires signature metadata; do not bypass it.
6. Issue: generate a separate public reference and random secret tracking credential, with a QR link. Store access credentials safely and support revocation.
7. Customer enters the code or opens the QR link to see a customer-safe status and custody timeline.
8. Staff records diagnosis, approved work, testing and handover. Cash collection and physical return are separate recorded actions.

## Screens and primary actions
| Screen | Main question | Primary action |
|---|---|---|
| Landing | Where is my repair? | Track device |
| Staff login | How do I access my workspace? | Sign in |
| Customer overview | What is happening and what is next? | Read latest update |
| Intake | What essentials must I capture? | Continue / Save draft |
| Technician queue | Which assigned device needs me? | Open repair |
| Branch overview | What needs attention in my branch? | Open queue item |
| Pickup request | Can the branch collect my device? | Send request for review |

## Roles and boundaries
- Customer: public tracking shows limited progress. Separately verified access is required for messages, quotations and requests. A QR code is a link, not identity proof.
- Intake/cashier: check-in, issue checks, cash collection and release, when authorized.
- Technician: assigned diagnosis, catalog-based quotes, parts requests, customer updates and testing. No recording cash as received.
- Branch admin: assigned-branch queues, technician assignment, exceptions, catalog/stock approvals and financial review.
- Owner: branch setup and explicitly authorized cross-branch reporting.

Roles beyond the existing ADMIN login require server permission checks before deployment. Hiding buttons is not authorization.

## Independent state tracks
| Track | Example states | Owner |
|---|---|---|
| Intake | Draft, Issued | Intake staff |
| Repair | Received, Diagnosing, Awaiting approval, Awaiting parts, Repairing, Testing, Ready, Returned | Assigned technician; authorized staff for release |
| Custody | At branch, In transit, With collector, Returned | Staff completing physical handoff |
| Payment | Unpaid, Part-paid, Paid, Refunded | Authorized cashier |
| Pickup | Requested, Confirmed, Collected, Failed, Cancelled | Dispatcher/collector |

These are user-facing terms, not instructions to replace existing database enums. Map labels to the schema in one workflow module. Returning after failed testing is allowed with a reason. Declining a quote does not automatically cancel or return a device.

## Later journeys
- Pickup: contact → device/problem → address and preferred time → review → staff confirms coverage/fee → collection evidence → branch receipt. A map pin alone never confirms coverage. An uncollected device is not Received.
- Repair: diagnose → catalog quote → customer approval → parts/work → tests → ready. New work requires a new quote version; accepted prices remain unchanged.
- Customer changes: request upgrade/customization/cancellation → staff reviews compatibility, completed work and disclosed charges → customer approves any revised quote → staff acts. Cancellation requests do not immediately change custody or erase charges.
- Cash: approved quote → itemized bill → physical cash received at counter or delivery → receipt → COD remittance if applicable. No online payment. Payment timing/deposit policy remains configurable and must be agreed before implementation.
- Chat: ticket conversation with assigned repair team; internal notes use a separate field. Begin with ordinary refresh/polling; background polling must not extend authenticated sessions.

## Responsive interaction contract
- Five public links: Home, About, FAQ, Contacts, Services; staff login is separate.
- Desktop: bounded reading width, two columns only when useful; sidebar and main can scroll independently.
- Tablet: collapse the sidebar before compressing forms. Mobile: one column and a navigation disclosure, with one main scrolling surface.
- Use visible labels, 44px minimum controls, text status labels, keyboard focus and reduced-motion support.
- Preserve values on Back and validation failures. Errors explain how to recover. Do not announce success before saving succeeds.
- A review page contains a summary, not new mandatory questions. Show condition, arrival method and serial as well as customer and device.
- Confirm destructive actions. Preserve saved drafts. Authenticated sessions expire after 60 minutes of meaningful inactivity; polling is excluded. Implement warning/activity rules as a dedicated feature.

## Feature branches and completion gates
| Branch | Scope | Gate |
|---|---|---|
| Database | Existing PDO login and draft foundation | Local database smoke test |
| Workflow-Wireframes | This blueprint and clickable responsive screens | Review phone, tablet and desktop journeys |
| Intake-Modules | Small commented functions and separate intake template | Same draft behavior; validation and transaction checks |
| Staff-Access | Roles, branch access, 60-minute session | Unauthorized access and expiry tests |
| Customer-Tracking | Issue checks, code/QR and safe timeline | No cross-ticket data exposure |
| Repair-Collaboration | Catalog quotes, parts, messages, requests | No unapproved work or price changes |
| Pickup-Cash | Logistics, cash receipts and reconciliation | Custody and cash totals reconcile |
| Reports | Recorded diagnosis/upgrade trends | Date ranges and sample counts |

One coherent commit per small feature when practical; one reviewable PR per branch. Do not merge automatically. Use brief summaries: Changed / Tested / Limitations.

## Keep the code teachable
Use plain PHP, PDO prepared statements, named functions and explicit arrays. Separate request handling, validation, database writes and HTML. Comments explain business rules and where to change them. Avoid frameworks, generic repository factories and clever abstractions. Keep secrets in ignored local configuration. Never remove authorization, escaping, CSRF or transactions for simplicity.
