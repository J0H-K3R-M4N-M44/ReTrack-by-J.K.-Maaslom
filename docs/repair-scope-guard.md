# Default-off, restrict-only device repair authorization

**Reviewed:** 22 November 2026. Draft feature branch only. This is a safe, **deny-only preflight** — not permission to transfer or repair one sibling device at another branch.

## Server-owned feature flag

In ignored `config/config.php`, set:

    'repair_scope_mode' => 'legacy',   // default; no change to current behavior

For isolated, fully migrated test environments only, operators may choose:

    'repair_scope_mode' => 'restrict',

- **legacy:** preserves the original ticket branch and assigned-technician scope.
- **restrict:** adds a strict **AND** condition requiring a non-null device `repair_branch_id` that equals the ticket `accountable_branch_id` and refers to an active branch. The actor must remain active; all original role and technician restrictions still apply.
- **Unknown value:** fails closed. It cannot be enabled by an HTTP form, query parameter or cookie.

**Restrict does not grant receiving-branch access.** A mismatched device is blocked from operational repair access on *both* sides pending a future approved transfer mechanism.

## Boundary and data map

| Area | Restrict behavior |
| --- | --- |
| Staff device list/detail, technician progress and assignment | Original scope AND matching active repair branch, checked again under existing transaction locks |
| Quote preparation/decisions | `quoteDevice()` calls guarded `consoleDevice()`; `quoteSave()` checks again inside its transaction |
| Stock reservation/installation/return/quarantine | `stockChange()` locks and checks guarded `consoleDevice()` before any stock write, preserving branch/quote/custody gates |
| Staff repair reports | Device metrics use the same guarded device scope |
| Administrator dashboard | Device repair queue and repair KPI use branch-alignment restriction; ticket/customer/draft totals remain ticket-scoped |
| Billing/refunds/pickup | **Always ticket scoped.** The accountable branch retains cashier permissions and requires whole-ticket return readiness |
| Physical custody | Courier dispatch or receipt is independent; neither constitutes repair authorization |

Existing `consoleProposedRepairScope()` is intentionally not activated for live operations. No partial-ticket acceptance endpoint, quote repricing approval, customer branch consent, transfer-fee policy or mixed-branch concurrency control has been added.

## Tests and observed evidence

`tests/repair-scope-gate.php` proves that legacy SQL remains equivalent to the old scope, that restrict is a narrowing predicate, that technician assignment persists, and that finance is ticket-scoped.

`tests/repair-guard-integration.php` runs only against a fresh `repairtrack_test_guard` MySQL schema with server-owned restrict mode. It constructs a fictional two-device mixed-branch ticket, verifies denial of unauthorized read/write, quote and stock actions, verifies source-branch ticket finance, tests deactivation of user/branch, and deletes its fixture. The corresponding CI job is in `.github/workflows/repair-scope-guard.yml`.

- [Restrict-only integration test, run 44992407822](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44992407822): **passed** on code `37f6e3f4`.
- [Disposable MySQL integrity run 44992407876](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44992407876): **passed** at the same code checkpoint.
- Browser/payment and staff-activity checks are separately recorded in [testing](testing-and-verification.md); verify their results on the final reviewed code.

## Do not enable on a persistent installation yet

The mode can intentionally deny existing repairs when a device branch is NULL or divergent. A production rollout requires backed-up and restored evidence, completed backfill, review of each denied issued device, complete role/browser coverage and explicit approval. Disabling the flag returns to legacy access, but it does **not** reconcile a real partial ownership conflict.

[Authorization audit](authorization-cutover-audit.md) · [Release status](release-status.md) · [MySQL tests](mysql-integrity-round.md).
