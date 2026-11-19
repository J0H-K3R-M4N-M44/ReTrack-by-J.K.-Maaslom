# Counter payment and pickup

This workflow completes the staff process after a repair is approved and tested. It uses the existing quote, bill, ticket, status-event, custody-event, and audit tables, plus one append-only `repair_bill_payments` table.

## Staff flow

1. A manager or administrator opens a ticket from a device that is ready for pickup.
2. The system checks that every device on the ticket is ready, stored at its assigned branch, and has a current approved estimate.
3. The manager reviews and issues one final bill for the ticket. Reopening the action returns the existing issued bill instead of creating a duplicate.
4. A cashier searches by ticket code, tracking code, or bill number and records the physical cash received. Partial payments are allowed; overpayments are rejected.
5. Once the remaining balance is zero, staff records who collected the device and confirms that the customer checked the device and accessories.
6. The system marks every device completed and in the customer's custody, records status and custody history, closes the ticket, and writes an audit event.

Every action is branch-scoped, requires an active staff account and CSRF token, and is checked again on the server. Review forms carry a one-use session token and record version. If the bill changes during review, staff sees current totals and must review again.

## Database upgrade

Fresh installations already include `repair_bill_payments` in `database/schema.sql` and `database/create_demo_database.sql`. Existing installations should be backed up, then upgraded with `php tools/migrate.php --apply`. The migration runner reports and applies `payment_handover.sql` along with any earlier pending staff migrations. It does not create a new database.

## Scope

Only counter cash is recorded. The workflow does not implement online payments, COD, refunds, void/reissue controls, tax, discounts, delivery handover, or digital signatures. Payment history is append-only; a correction process needs its own permission and audit rules before launch.
