# Role-based user guide (local/staging)

**Version context:** PHP `main` plus documented *read-only* feature-branch differences on 22 November 2026. Screens and actions described here require a configured local database and assigned, active staff accounts. This guide does not provide real passwords or actual shop policies.

## Sign in and find your workspace

1. Open `admin/login.php` in your local project URL.
2. Sign in with an **active** account. The system assigns pages from your role and active branch memberships, not a role you choose in the browser.
3. Use your workspace links/search. Use the account menu to log out. When the session expires, sign in again; never share the account with a colleague.

Invalid logins, unassigned branches and stale pages are not reasons to bypass access checks. Administrators manage staff via `admin/staff.php`; they cannot delegate ADMIN by creating an ordinary staff account.

## Intake agent / manager: new repair

1. Open **New intake** (`admin/intake_create.php`). Search for an existing customer before adding one.
2. Enter device type, identifiers if available, symptoms and observed physical condition. You can save a reviewed draft with multiple devices.
3. Open **Ticket issuance** (`admin/intake_issue.php`) and complete each device's accessory checklist, condition photo, physical storage location and signed intake acknowledgment.
4. Confirm the review. A successful issue allocates ticket/item identifiers, creates status/custody history and a private customer tracking path. Missing mandatory evidence keeps the record as a draft.
5. Provide the customer their permitted code/PIN instructions through the shop's agreed private channel. Never publish tracking PINs in an unrestricted chat or screenshot.

## Manager / admin: assign and review a repair

1. Open the repair queue in `admin/staff_home.php`, select an issued device inside your assigned branch, and inspect its current stage/location.
2. Assign an active technician in the branch, observing the per-technician active workload limit (default five).
3. For estimates, open `admin/quote.php` and review diagnosis, itemized work/parts, current version and total. Staff may record a customer's response; do **not** claim the customer self-approved online.
4. When all eligible devices are ready at the accountable branch, open the cash desk/bill workflow in `admin/cash.php`. Check all ticket-wide conditions rather than just one device.
5. On the feature branch only, device detail may show **Repair branch** and **Ticket/billing branch** as *read-only* diagnostics. A mismatch is a review warning, never permission for cross-branch work.

## Technician: assigned device

1. Open only your assigned devices in `admin/staff_home.php`.
2. Record the permitted next repair stage and private work notes on `admin/device.php`. Current quote approval and confirmed custody may block entry into repair.
3. If parts are needed, follow the approved catalog/stock process; do not invent a reservation or silently change an approved estimate.
4. If a device is in transit, contact the authorized receiving staff; do not advance stage as if the device had already been received.
5. Reassignment removes access to the old task. Never use another staff account to work around an assignment.

## Cashier: issue/collect/return boundaries

1. Open `admin/cash.php` and locate the issued bill by permitted ticket/bill search.
2. Verify the current bill and remaining amount, then record **physical counter cash** actually received. Partial payments are allowed; an overpayment or stale form should be rejected.
3. Record pickup only after all devices, balance, refund and receipt gates clear. Confirm recipient and accessories/condition.
4. Use audited correction/refund tools only when authorized. Do not treat stock loss valuations or transfer receipts as customer payments. **COD remittance is not a complete workflow.**

## Courier / logistics: last confirmed custody

1. Open `admin/transfers.php` as an eligible assigned-branch staff account.
2. Use the manifest to confirm source/destination, item codes, storage location and condition.
3. Dispatch and receive/missing-item actions produce custody events; unresolved exceptions stay visible.
4. Whole-ticket repair acceptance is a separate MANAGER/ADMIN action after **all** devices are received and safety conditions hold. A courier cannot grant partial repair authority.

## Inventory and stock review

- `admin/inventory.php`: supervised on-hand, branch reservations, part installation/returns, quarantine and count adjustments.
- `admin/parts_transfers.php`: physical parts transfer/receipt, missing-part investigations and recoveries.
- `admin/acquisition_costs.php`: documented branch/SKU supplier acquisition costs and independent review.
- `admin/stocktaking.php`: open physical counts, count every SKU, submit, and receive independent ADMIN reconciliation/sign-off. Sign-off never adjusts stock by itself.
- Work from the physical evidence/approved workflow; typed supplier references are not proof of authenticity.

## Customer: view safe repair progress

Open `track.php` or the shop's private tracking page, enter the short code and PIN (or follow the supported secret link), and review only the public status and last confirmed custody. The portal does not reveal internal notes, accept self-approved repair changes, process card payments or offer a working private chat. For missing codes or discrepancies, contact the shop through its verified support process.

## When an action is refused

| Message | Meaning |
| --- | --- |
| Outside assigned branch / unavailable | Role, branch or object access is not permitted; ask an administrator to review assignment |
| Device changed / quote changed | Another actor saved first; reload and review instead of replaying an old form |
| Quote approval required | Do not start unapproved work |
| In transit / custody unresolved | Finish actual physical receipt or discrepancy investigation |
| Insufficient unreserved stock | Do not bypass inventory tracking or use a different branch's shelf silently |
| Outstanding bill/refund | Return to the authorized cashier review, do not force handover |

Training scenarios should use fictional records and a disposable database. [Operations](operations-runbook.md) · [Security](security-and-privacy.md) · [Feature status](system-overview.md).
