# Counter payments, corrections and customer handover

**Current implementation summary (22 November 2026).** This replaces the older narrow “cash-only without refunds” text. Bounded PHP workflows for paid-bill correction, refunds, cancellation checkout and stock-aware final billing have been integrated into `main`; that does **not** imply general accounting, COD or production approval.

## Ticket-level financial ownership

`repair_tickets.accountable_branch_id` determines which active assigned branch can issue/review the ticket's financial records. **Not** the physical location, courier receipt, or proposed `ticket_devices.repair_branch_id`. These rules are rechecked in `app/payments/repository.php` transactions. A multi-device ticket yields **one ticket-level final bill**.

## Checkout sequence

1. Manager/ADMIN loads an issued, open ticket within their assigned branch. All devices must be eligible, at the ticket's accountable branch, with approved current quotes; tracked parts and custody constraints are checked.
2. They confirm the final itemized bill. An existing issued bill is reused instead of duplicating it.
3. CASHIER (and eligible staff) records physical counter cash; partial payments are supported and stale requests/overpayments refused. Bill payment history persists in `repair_bill_payments`.
4. Outstanding balance and refund-due checks block handover. Staff records recipient and condition confirmation, and the system records custody/status/audit events and closes eligible ticket/device records.
5. A cancellation checkout is a separate verified path for a customer-agreed diagnostic fee, not a shortcut that erases the quote or part movements.
6. Paid-bill corrections/refunds go through separately audited write paths in `repair_bill_changes`; original receipts are not overwritten. Wrong role, stale review and duplicate refund attempts are rejected.

Read current PHP in `cashIssueFinalBill`, `cashRecordPayment`, `cashRecordBillChange`, `cashHandoverBlockers` and `cashCompleteHandover` (functions in `app/payments/repository.php`) for exact action conditions.

## Limits

- **Supported:** counter-cash records in PHP; approved bill corrections/refunds as bounded shop records; whole-ticket return after checks.
- **Not a completed service:** delivery COD collection/remittance, online/card/e-wallet payment, comprehensive tax reporting, cash-drawer audit, GL postings, insurance settlements or regulatory receipt certification.
- **Not permitted:** giving the receiving *repair* branch cash/refund power because it receives one sibling device, bypassing balance/refund gates, rewriting old payment/quote rows, or processing a real customer transaction in a demo database.

## Schema and tests

`repair_bills`, `repair_bill_items`, `repair_bill_payments`, `repair_bill_changes` link charges, cash and audited adjustments. Existing databases use [migration runner](migration-runner.md), after backups; `database/payment_handover.sql` and `database/bill_corrections.sql` cover legacy upgrades. Tests include `tests/payment-pickup.php`, `tests/checkout-concurrency.php` and `tests/payment-browser.mjs`. The dedicated feature-branch MySQL integrity round also snapshots finance-related rows but does not replace all browser or deployment verification.

[System overview](system-overview.md) · [Security](security-and-privacy.md) · [Remaining gaps](system-gaps.md).
