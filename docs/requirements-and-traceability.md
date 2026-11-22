# Requirements and traceability register

**Controlled snapshot:** 22 November 2026. This is a living specification of the PHP/MySQL project, not a signed customer contract or proof of deployment. **I** = integrated in `main`, **F** = implemented only in draft feature branch, **P** = planned/incomplete. A requirement marked I can still have deployment and integration risks.

## Stakeholders and business goals

- **Customer:** receive a transparent, safely limited status and confirmed handoff location for their repair without calling the shop repeatedly.
- **Intake and branch staff:** capture accurate device/evidence details, avoid duplicates, see work queues and branch custody.
- **Technician:** progress only assigned jobs and record diagnostic results without treating notes as customer approvals.
- **Cashier:** issue/collect/reconcile permitted ticket-level counter cash and prevent unpaid release.
- **Branch manager/admin:** assign staff, approve exceptions, review stock and investigate discrepancies with audit history.
- **Project maintainers:** keep code teachable, reproducible on disposable MySQL, and safe to migrate and review.

## Functional requirements

| ID | Status | Requirement and acceptance condition | Implementation / verification |
| --- | --- | --- | --- |
| FR-01 | I | Staff authentication uses active account, role, branch membership and time-limited session; invalid login does not enter a workspace | `app/auth.php`, `tests/login-regression.cjs` |
| FR-02 | I | ADMIN can create/change staff with assigned branches and guarded activation; unauthorized staff cannot grant ADMIN privileges | `app/staff/repository.php`, `tests/staff-management.php` |
| FR-03 | I | Intake stores customer and up to five devices, with validation and review; failure does not leave a partial ticket | `app/intake/`, `tests/admin-intake.php` |
| FR-04 | I | Ticket issuance requires custody location, condition evidence and signed acknowledgment; drafts cannot bypass the gate | `app/intake/issuance.php`, `tests/ticket-issuance.php` |
| FR-05 | I | Public tracking uses a valid private code/PIN or legacy token and exposes only customer-safe status/location updates | `app/public/tracking.php`, `tests/public-tracking.php` |
| FR-06 | I | Only assigned technician may progress allowed repair stages; stale revisions/unauthorized branches refuse writes | `app/console/repository.php`, `tests/staff-consoles.php` |
| FR-07 | I | Authorized staff prepare/revise estimates and record customer decisions; in-progress work needs approved quote | `app/quotes/repository.php`, `tests/quotes.php` |
| FR-08 | I | Branch-authorized managers issue final ticket bills; cashiers record counter cash, corrections/refunds are audited, and blocked handovers do not close the ticket | `app/payments/repository.php`, `tests/payment-pickup.php` |
| FR-09 | I | Physical transfer dispatch/receive updates manifest and custody history; whole-ticket repair acceptance is explicit and guarded | `app/console/transfers.php`, `tests/stock-pilot.php` |
| FR-10 | I | Catalog stock uses guarded reservation, installation, returns, quarantine and independent review; no silent negative stock | `app/inventory/`, `tests/stock-quarantine.php` |
| FR-11 | I | Parts transfer loss/recovery, supplier costs and stocktake sign-off preserve audit/provenance without posting a GL entry | `app/inventory/`, `tests/stocktaking.php`, `tests/part-acquisition-costs.php` |
| FR-12 | I | Staff activity/reports remain scoped and cover relevant repair/cash events with dated pagination | `app/console/reports.php`, `tests/staff-reports.php` |
| FR-13 | F | Add a nullable, FK-backed device repair branch; backfill issued rows, preserve timestamps and show labels read-only | `database/device_repair_branches.sql`, `tests/mysql-integrity.php` |
| FR-14 | **P** | Permit a single device in a multi-device ticket to change repair branch only after two-party approval, confirmed custody, and atomic audit | [Draft design](partial-ticket-ownership.md) |
| FR-15 | P | Customers can self-approve evidence-backed changes and submit scoped upgrade/cancel requests; no authorization by QR possession alone | [Open gaps](system-gaps.md) |
| FR-16 | P | Secure, role-limited two-way messaging and live external repair alerts, with retention/retry controls | [Open gaps](system-gaps.md) |
| FR-17 | P | COD settlement/delivery remittance, complete accounting journal/GL and live GPS are separate reviewed integrations | [Open gaps](system-gaps.md) |

## Nonfunctional requirements and measurable checks

| ID | Criterion | Evidence or release gate |
| --- | --- | --- |
| NFR-01 | **Authorization:** every protected read/write enforces active actor, branch and object scope; posted role is never permission | Negative PHP + browser role tests, public portal rejection, review of direct URLs |
| NFR-02 | **Integrity:** no orphan references, cross-ticket bill lines, invalid stock reservations or unacknowledged history rewrites | Enforced FKs/checks, 17-condition MySQL audit, 19-table deterministic snapshots |
| NFR-03 | **Concurrency:** stale saves/duplicate cash and stock movements refuse or serialize safely | Optimistic version hashes, row locks and two-operator tests |
| NFR-04 | **Privacy:** public query allowlists fields and evidence remains in private storage; secrets/contacts absent from logs/commits | Test public response, file access, log review |
| NFR-05 | **Usability:** reviewable forms, explicit errors, no-JavaScript recovery where supported, responsive phone/tablet/desktop | Browser widths 320/390/768/1440px, keyboard, reduced-motion, invalid input |
| NFR-06 | **Auditability:** status, custody, money, stock and independent approvals retain history | Inspect audit/status/ledger tables; original movements and paid receipts stay intact |
| NFR-07 | **Recoverability:** migrations never proceed on persistent data without backup + restore rehearsal; DDL partial failure has safe response | Disposable migration CI, operator runbook and actual restore trial |
| NFR-08 | **Clarity:** static demos and proposed features clearly separated from PHP implementation; documentation resolves to current branch | Doc index and scope status review |

No uptime SLA, throughput number, accessibility conformance certificate, banking certification or third-party provider success is implied by these criteria.

## Cross-cutting acceptance gates

- All protected POST actions use CSRF and server validation, irrespective of hidden UI controls.
- Device/custody/repair status, quote approval and final bill are **separate state tracks**; update one does not automatically prove another.
- Cash uses the ticket's accountable branch; stock and physical custody must not be confused with financial ownership.
- Customer consent and part price changes must be explicitly verified before the operational partial-branch feature is enabled.
- Bounded pilots are reviewed as pilots: supplier cost attestations are not cryptographic supplier validation and physical stocktaking is not a financial booking.

## Verification status

[Dedicated GitHub-hosted MySQL run 44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) passed at `738bfada`, but other broader checks and the future partial-permission cutover are distinct gates. Re-run at the final PR HEAD and record the result before promoting any requirement. [Testing register](testing-and-verification.md).
