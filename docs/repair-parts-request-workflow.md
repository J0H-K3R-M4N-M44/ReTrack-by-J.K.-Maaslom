# RepairTrack — internal repair parts request workflow

**Development:** `Repair-Parts-Request-Workflow`, based on `MVP-Stabilization` commit `23261715`. This draft integration is not approved for deployment. The corresponding `repair_part_requests` table and migration originated on the stabilization branch; the workflow adds server-side actions, role-specific pages, tests, and navigation.

## Purpose and boundaries

Assigned technicians may document a real repair's needed part, quantity, and reason. Managers or administrators with an assignment to the repair's accountable branch may acknowledge, decline, or resolve that request. This is an **internal needs register** only. **Acknowledged does not mean purchased**, **resolved does not mean installed**, and neither action represents customer authorization, a quote, a hold, a purchase order, an inventory movement, cash handling, or an automatic repair stage change.

Only issued tickets with an open case and device state `DIAGNOSING`, `AWAITING_PARTS`, or `IN_PROGRESS` allow creation. Technician ownership of the device is rechecked under a lock at submission. Managers can only review requests whose original `branch_id` still matches the accountable repair branch they are authorized to manage. Changes to whole-ticket ownership do not silently move historic requests to a different branch.

## Workflow and code

1. Technician opens an assigned repair, then **Open part requests**, enters a part description (3–160 characters), positive quantity (up to 9,999.999), and reason (10–500 characters), then reviews and confirms.
2. Manager or administrator sees the assigned-branch request register, opens a request, and records **Acknowledge** (optionally with a note) or **Decline** (10–500 character note required).
3. An acknowledged request may be **Resolved** with a substantive note. Declined and resolved requests remain recorded, and cannot be silently reopened or overwritten.
4. Status writes require an expiring one-use form, session-bound CSRF token, current active role and branch, a row lock, request version check and atomic audit entry. A repeated or stale decision is rejected.
5. Staff can search no customer records from this feature. The tracking endpoint, payment and inventory code are unchanged.

**Files:** `app/parts/requests.php` (state transitions, scoping, transactions), `admin/part_requests.php` (review forms and CSRF), `views/parts/requests.php` (responsive UI), `views/console/device.php` and `views/workspace/navigation.php` (links), `tests/repair-part-requests.php`, `tests/repair-part-requests-browser.mjs` (disposable MySQL and Chrome regression). `database/repair_part_requests.sql` is pre-existing on the base branch; no new migration file has been invented.

## Regression and human acceptance

The branch-specific `.github/workflows/mvp-stabilization.yml` quote matrix executes PHP lint, role/branch denials, invalid amounts, optimistic concurrency, audit rollback, successful review/resolve and Chrome technician/manager interactions. The existing public tracking, quote and single-ticket billing/restore journeys must still pass. Record **exact run and commit SHA**, and correct any failure before review.

Human UAT is still required: use fictional technician and manager accounts; submit a request, verify only authorized roles see it, decline one, acknowledge and resolve another, repeat a stale form, and check no stock/quote/transfer/payments change. Reassign the technician and move a **whole** ticket in a disposable test to confirm the original branch does not leak its historic request to a new, unrelated branch. Complete Windows/XAMPP and hosted security checks separately.

## Known limitation

This first implementation intentionally does not contain procurement vendor details, purchase approvals, physical delivery evidence, stock transfers/reservations, customer alerts or per-part QR. It does not automatically unblock a repair in `AWAITING_PARTS`; a technician must follow the existing quote, stock and repair-stage workflow. Do not substitute the request status for inventory evidence.

**No approval to merge or deploy** is implied by passing CI.
