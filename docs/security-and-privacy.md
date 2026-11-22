# Security and privacy boundaries

**Review date:** 22 November 2026. This is a practical code-boundary inventory, **not** a penetration-test report or production security certification. It distinguishes integrated `main` behavior from the draft `Partial-Ticket-Ownership` branch.

## Trust boundaries

- **Public session:** visitors use a short portal code plus customer PIN, or an existing longer secret tracking token, to view an allowlisted public summary. Receipt number alone is not authentication. No private technician notes, serials, contact details or evidence should be included in public queries.
- **Staff session:** users log in through `app/auth.php`, with server-side checks of role, active account and assigned active branches. PHP regenerates sessions on login, uses HTTP-only same-site session cookies and checks CSRF for writes.
- **Role versus branch versus object:** permission requires an allowed role **and** a relevant branch assignment; technician repair work additionally requires an assigned device. A courier's physical receipt does not grant repair or cash permission.
- **Evidence files:** intake photos, signed forms and stock investigation evidence use private storage outside the web root and authorized PHP read endpoints. Do not expose absolute paths or direct filesystem URLs.
- **Data layer:** PDO prepared statements, explicit transaction locks, optimistic version checks, foreign keys, audited status/stock/cash events. Never substitute a posted branch/role for the locked server-side actor.

## Current role boundaries (summary, not an exhaustive ACL)

| Role | Permitted area | Explicit non-permissions |
| --- | --- | --- |
| ADMIN | Assigned branches, staff management, intake, repairs, protected review/financial actions | No automatic bypass of dual-control, other-branch or future partial-ownership rules |
| MANAGER | Assigned-branch repair/intake, permitted stock review, final bill issuance | Independent ADMIN approvals may be required; no wholesale account administration |
| INTAKE_AGENT | Intake, issue, allowed recording of customer quote decisions | Cash collection, arbitrary repair progression and stock loss approval |
| TECHNICIAN | Specifically assigned devices, approved stage changes and quote preparation | Bill collection/refund, sibling repairs not assigned, account administration |
| CASHIER | Ticket-finance scope, counter-cash receipt and eligible handover | Technician stages, device repair authority, supplier-cost approval |
| COURIER | Authorized physical dispatch/receipt and transfer status | Repair branch reassignment, final bill authorization |
| VIEWER | Limited branch-scoped status | Customer contact, private notes, billing writes, evidence downloads |

The authoritative decision lives in PHP functions such as `requireRoles`, `consoleScope`, `consoleDevice`, `quoteSave`, `stockChange`, `cashIssueFinalBill` and their transactional checks; see [architecture](architecture.md).

## Customer access controls

`app/public/tracking.php` validates permitted code/token formats; the short-code path requires a four-digit PIN verified against a hash and uses a dummy hash for unknown codes. The endpoint selects customer-safe fields, not a joined whole-staff detail record. The portal should have no-store/no-referrer/noindex headers and must not embed third-party analytics that could disclose URLs. Token replacement revokes the prior access path. **Do not rely on a QR code as proof of identity**; it is a convenience link.

## Session and credential safety

- The actual implementation in `app/auth.php` expires idle staff sessions after **30 minutes**; any older design document that says 60 minutes is a historical proposal.
- Keep `config/config.php` ignored and private. Store HMAC keys, SMTP configuration, database passwords, signing secrets and temporary reset tokens outside version control. Never use common/shared demo credentials for real customers.
- Password reset tokens are one-time, time-limited and stored as hashes. Local PHP mail delivery needs an independently configured relay; a successful local request is not proof of email delivery.
- Deploy HTTPS, safe-cookie configuration, rate limiting appropriate for distributed traffic, least-privilege DB credentials, secure file storage, log redaction and backup encryption before any public exposure.

## Draft PR #55: no authorization promotion

`ticket_devices.repair_branch_id` exists **only** on the feature branch's additive schema and read-only display. `app/console/scopes.php` holds a *proposed* repair-branch scope; it is **not connected to operational HTTP requests**. Existing repair operations still rely on ticket/accountable branch plus technician assignment. Ticket cash/bill access must remain bound to `repair_tickets.accountable_branch_id`.

The current code refuses a whole-ticket handoff when device branch records are mixed or unresolved. **No endpoint exists to authorize only one sibling device at a different branch.** [Cutover audit](authorization-cutover-audit.md) lists all code paths requiring an atomic permission/stock transition and negative/concurrency tests.

## Residual risk register

| Risk | Required mitigation before production |
| --- | --- |
| Credential and account abuse | HTTPS, enforced password policy, secure mail relay, audit login/activation, review throttling and session settings |
| Cross-branch data exposure | Test every object query and mutation, especially sibling devices, notes, billing, and reports |
| Tracking-code disclosure | Rotation/revocation, no logs containing codes/PINs, strong secrets, targeted rate limiting |
| Evidence leaks | Private storage permission tests, path traversal/file-type checks, access logs and retention rules |
| Duplicate money/stock actions | InnoDB locking, nonce/version/replay safeguards, independent review and reconciliation |
| Data loss / partially applied migrations | Verified database and private-evidence backups, restore rehearsals, maintenance window, migration preflight |
| Audit or inventory interpretation | Preserve event history; loss valuations and stocktaking sign-off are not cash/GL entries |
| External services | Do not claim GPS, SMS, automated customer email, real-time notification or COD remittance where no provider/workflow is integrated |

## Incident handling principles

If a role appears to access an unauthorized ticket, **disable affected access and capture a minimal audit trail** before touching account/data records. If a migration fails, pause writes, preserve backups/logs, diagnose the current schema and only then rerun or restore. Never delete audit records to hide an inconsistency. See [operations runbook](operations-runbook.md).

[Current overview](system-overview.md) · [Test matrix](testing-and-verification.md) · [Release gate](release-status.md).
