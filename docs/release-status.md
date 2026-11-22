# Release status, decisions and next milestones

**As of 22 November 2026.** The repository contains integrated `main` and an ongoing isolated `Partial-Ticket-Ownership` feature branch. This page is a **checkpoint**, not a release announcement.

## Exact known references

| Ref | Verified position | What it means |
| --- | --- | --- |
| `main` | `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b` | Last checked integrated baseline for this documentation pass; mature PHP work and stocktaking pilots already present |
| `Partial-Ticket-Ownership` | Documentation started after `738bfadaacc22bda9c1ab2b1b7e28a418a203f3e` | Development branch for additive device repair branch and scope preparation; documentation commits advance HEAD |
| [Draft PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55) | Open, draft, unmerged | **Not** deployment approval |
| [MySQL integrity run 44991532074](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991532074) | Passed on `59065079` | Eight-stage disposable MySQL integrity and migration test; not production validation |
| [PHP shop browser/payment run 44991528241](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991528241) | **Both jobs passed** on `59065079` | Read-only ownership role/viewport isolation **and** existing payment browser tests |
| [Staff activity run 44991375736](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44991375736) | Passed on `d2d190ad` | PHP activity, browser inbox, reports, migration and intake regression |

Always re-query branch heads and checks when taking an action later; a document can never make a moving ref immutable.

## Integrated capability ledger

1. Foundations: PDO login/activation/recovery, active branch-scoped roles, admin management, intake and signed-evidence ticket issuance.
2. Customer-safe tracking: short code/PIN and legacy secret token, status/custody timeline, selective public notes.
3. Staff repair operations: assignment, transitions, private notes, searchable work queues, reports.
4. Quote, final bill and counter settlement: staff-recorded approval, cash receipts, partial payment, paid-bill adjustments/refunds, whole-ticket cancellation checkout and counter handover.
5. Device physical custody: authorized dispatch, receipt/exception, whole-ticket destination acceptance (not partial-ticket ownership).
6. Inventory pilots: stock reservations, installation/returns, quarantine and count adjustments with dual review, physical parts transfers, loss investigation/recovery, supplier-cost evidence, independently approved exposure estimates, signed physical stocktakes.
7. Database testing: migrations and disposable MySQL integrity tests on the feature branch; a successful run was observed for its prior checkpoint.

This is **code/CI status**, not evidence of rollout to live stores.

## Active PR #55

Implemented on the unmerged feature branch:

- Additive `ticket_devices.repair_branch_id` with FK/index, safe backfill and new issuance defaults.
- Whole-ticket acceptance aligns the device field and refuses existing mixed/unverified ownership.
- Read-only repair versus ticket branch labels after authorization.
- Separate *proposed* repair-device scope and ticket-finance scope; the proposed scope is **not yet activated**.
- Tests for branch isolation and revocation, 17 MySQL integrity conditions, 19 historical-table snapshots, migration recovery and negative FK/rollback cases.
- Documentation consolidation of implementation, operations, limitations and evidence.
- Dedicated authenticated Chrome regression for read-only branch labels, role denials, escaped untrusted branch names, phone/tablet/desktop widths and no-JS; tests run in separate isolated jobs so production login throttling remains unchanged.

**Not in PR #55:** a partial-ticket transfer/acceptance endpoint, approved mixed-branch cash/stock authorization cutover, external GPS or customer chat, deployment scripts for live databases, or permission to merge.

## Open decisions and release gates

| Priority | Blocker | Required proof |
| --- | --- | --- |
| 0 | Documentation and CI at new HEAD | Documentation consistency, no broken internal links, dedicated workflow and affected suites pass |
| 1 | Role and sibling-data isolation | All PHP read/write/quote/stock/report entry points reviewed under distinct device/ticket scopes; negative and concurrency tests |
| 2 | Customer branch-change and quote policy | Explicit origin/destination approvals, customer consent policy, part/cost repricing and transfer-fee rules |
| 3 | Database recovery on target environment | Backup + restore including private evidence; nonempty migration rehearsal on target MySQL/XAMPP |
| 4 | Browser accessibility and regressions | Authorized staff views and denied paths at phone/tablet/desktop, keyboard and reduced-motion checks |
| 5 | Rollout plan | Feature OFF by default, staged pilot, telemetry/audit, forward-fix, independent review, explicit merge/deploy decision |

## Unfinished product scope

Customer self-approval and revision after started work, two-way technician/customer messaging, customer requests, automated repair SMS/email delivery, full COD/delivery remittance, live tracking/maps, reliable purchasing/GL integration, centralized monitoring and production operations are **not completed**. Historical demo UI may depict them without a PHP/MySQL implementation.

## Next safe work

1. **Completed preflight:** documentation consolidation and repeatable MySQL, staff-activity, payment and isolated browser ownership tests; reference the exact CI runs above. No live permissions were changed.
2. **Restrict-only gate completed in a draft branch:** a server-owned default-legacy flag can deny unmatched device repair, quote and inventory operations; admin device queues and staff repair reports follow the restriction. It does **not** grant receiving-branch authority. [Guard guide](repair-scope-guard.md). The full approved mixed-branch cutover, consent and concurrency remain a later milestone.
3. Obtain a reviewed origin/destination manager approval, customer-consent and quote repricing policy; only then implement manager-accepted **partial** repair transfer with immutable audit events.
4. Validate full suites again at the final code HEAD, then independently rehearse backups/restores and real browser/deployment conditions.
5. Merge or deploy only after a separate review decision; `main` and production remain unchanged.

[Documentation index](README.md) · [Security audit](authorization-cutover-audit.md) · [MySQL tests](mysql-integrity-round.md) · [Remaining gaps](system-gaps.md).
