# Quote and approval workflow

> **Historical milestone specification.** The quote/revision and staff-recorded approval workflow described below was later integrated into `main`. Its original branch and “main unchanged” statements refer to that earlier milestone. **Current stock-aware estimates may link catalog parts, reservations, installation and returns**; the older sentence saying parts estimates never reserve stock is not a complete description of today's inventory pilot. Customer self-approval and post-start change orders are still unfinished. See [system overview](system-overview.md), [architecture](architecture.md) and [release status](release-status.md).

This branch builds on Staff-Workspace. Apply `database/staff_workspace.sql` if you have not already done so. This increment adds no tables or columns. Main and Simplified-System are unchanged.

## Daily use

1. Open a device and choose **Open estimate**. The device must have started diagnosis and must not have started repair.
2. A manager, administrator or assigned technician enters a short diagnosis/work description, labor amount, and optional combined parts/other charges in PHP. Review the calculated total, then save the draft.
3. Explain the exact revision and total to the customer using the branch's normal communication process. Check the confirmation, review, and mark it shared. This opens a seven-day response period; it sends no SMS or email.
4. An administrator, manager or intake agent records the customer's approval or rejection, customer/representative name, and conversation reference. They review the selected revision and total before confirming. Technicians cannot record customer approval.
5. Recording a response returns the device to Diagnosing. Approval permits the assigned technician to select In progress when the device is at the branch. Rejection keeps that action blocked; it never cancels the ticket or releases the device. The customer timeline receives a short update without the private conversation reference.

## Revisions and limits

A revised estimate is a new draft, not an edit to the old record. The previous quote is superseded; its decision remains in history. Fresh approval is required. Expired pending estimates need a new revision. A quote cannot be changed after repair starts, including while repair is paused. Change orders are a later workflow.

Amounts use integer centavos for calculations, then database decimal values. New estimates contain at most three summary lines, quantity one: labor, optional combined parts, and optional other charges. This is a simple estimate, not item-level procurement. Zero-cost work is allowed when described. Parts estimates do not reserve stock. Quotes are not bills, cash collection records or receipts.

The latest estimate is shown with ten revisions per history page. Historical statuses and decisions remain visible. The system does not yet provide customer self-approval, attachments as proof of consent, quote export, or a detailed historical-revision viewer. A staff-recorded response represents what the staff member reports receiving; it is not a verified customer signature.

## Interface changes

The device page shows quote status and a direct next action. Estimate forms span desktop width and stack on phones. A four-step guide, review screen, clear totals and success/error messages explain progress. Optional revisions are collapsed until needed. Shared staff screens have consistent card borders, spacing, 44-pixel action targets and aligned search controls. Dashboard data cards stay still on hover; opaque card surfaces avoid costly blur layers on mobile. The public tracking flow remains unchanged except for new customer-visible quote status events.

Form design references: [W3C form notifications](https://www.w3.org/WAI/tutorials/forms/notifications/) and [review/correction guidance](https://www.w3.org/WAI/WCAG21/Understanding/error-prevention-all.html).

## Safety and tests

Role/branch/technician assignments are checked again on writes. Device and quote rows are locked, stale versions rejected, and quote/items/status/audit changes committed together. CSRF and session review state protect form submissions; repeated confirmation cannot create a duplicate decision. Return navigation accepts known local list parameters only.

On a disposable `repairtrack_test_*` database, run `tests/staff-consoles.php` and `tests/public-tracking.php` first, then `tests/quotes.php`. The quote fixture deliberately resets its test device for browser testing; never run it against real data. Run `tests/quotes-browser.mjs` with Playwright and the same local environment variables documented in `staff-workspace.md`.

Tests cover exact totals, invalid amounts, role/branch boundaries, stale edits, expiry, declined/approved repair gates, immutable decisions, review/edit/save, replay and CSRF rejection, escaped text, no-JavaScript access and widths from 320 to 1440 pixels. Local tests do not replace deployment checks on your XAMPP/MySQL version.

## Next work

On `Payments-Inventory`, a manager can issue the final bill after every device is ready with an approved estimate. Cashiers record counter cash and confirm a paid counter pickup. COD, refunds, stock reservation, customer self-approval and change orders remain separate work.
