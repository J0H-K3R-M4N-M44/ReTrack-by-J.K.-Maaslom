# RepairTrack documentation

**Reference snapshot: 22 November 2026.** This index describes the source on `Partial-Ticket-Ownership` and distinguishes it from the integrated `main` branch. **This is a locally tested academic project, not a certified production service.**

Start here instead of reading the original milestone notes in chronological order. Original reports are preserved for history, but some were written before billing, inventory, and branch logistics were implemented.

## Read in order

| Guide | Use it for |
| --- | --- |
| [System overview](system-overview.md) | Purpose, users, current scope, workflow, and feature status |
| [Architecture](architecture.md) | PHP structure, request flow, permissions, state boundaries |
| [Requirements and traceability](requirements-and-traceability.md) | Functional/nonfunctional requirements with source and test links |
| [Workflow and state models](workflow-and-state-models.md) | Diagrammed intake, repair, custody, and cash lifecycles |
| [Role-based user guide](user-guide.md) | Instructions for customer, intake, technician, cashier, manager, courier and inventory staff |
| [Database reference](database-reference.md) | Current 47-table schema, relationship groups, and migration rules |
| [Security and privacy](security-and-privacy.md) | Roles, access boundaries, protected customer information, deployment risks |
| [Local setup and upgrade](local-setup.md) | XAMPP/PHP setup, fresh database, seed, backups, CLI migrations |
| [Testing and verification](testing-and-verification.md) | Repeatable tests, GitHub Actions, interpretation of pass/fail evidence |
| [Operations runbook](operations-runbook.md) | Normal operator tasks, safe diagnostics, recoveries, and stop conditions |
| [Release status and roadmap](release-status.md) | Verified baseline, outstanding gates, staged next work |

## Detailed feature specifications

- [Staff accounts](staff-management.md), [staff consoles](staff-consoles.md), [workspace](staff-workspace.md), [administrator intake milestone](admin-milestone.md)
- [Intake maintenance](intake-maintenance.md), [ticket issuance](ticket-issuance.md), [customer tracking](customer-tracking.md)
- [Quotes and staff-recorded approvals](quote-and-approval.md), [counter payments and handover](payment-and-handover.md)
- [Stock, logistics and remaining gaps](system-gaps.md)
- [Partial-ticket ownership design](partial-ticket-ownership.md), [restrict-only repair authorization](repair-scope-guard.md), [authorization cutover audit](authorization-cutover-audit.md), [MySQL integrity test round](mysql-integrity-round.md)
- [Migration runner details](migration-runner.md), [historical demo fixture](database-demo.md)

## Design, history and maintenance records

- [Workflow blueprint](workflow-blueprint.md), [static wireframe guide](interface-wireframe.md), [wireframes](wireframes/index.html), [public home content](home-content.md), [code style](code-style.md)
- [Branch map](branch-map.md), [last integrated release](main-release.md), [branch cleanup (historical)](branch-cleanup.md), [consolidation checks (historical)](consolidation-checks.md), [old simplified comparison](current-vs-simplified.md)
- [Seed counts](seed-counts.json) is a **historical demo fixture count**, not a current schema census.

## Status vocabulary

- **Implemented in main:** code integrated at `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b`, the verified `main` reference for this documentation update. This is not proof of live deployment.
- **Feature branch only:** changes committed on `Partial-Ticket-Ownership` and under draft [PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55), not merged or deployed.
- **Passed test:** a specific suite at a specific commit reported success. Example: GitHub-hosted MySQL integrity job [44989851868](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44989851868) passed on `738bfada`. A later commit requires its own result.
- **Pilot:** bounded PHP behavior, not a complete accounting, fulfillment, supplier, or external-messaging integration.
- **Demo only / planned:** wireframes, mock events, or work that does not have an authenticated server-side workflow.

## Documentation maintenance rules

1. Describe implementation from the **target branch** and name that branch when reporting a feature.
2. Never present an old milestone's “remaining work” as current without a status correction.
3. Keep money, physical custody, inventory, and repair authorization separate in diagrams and prose.
4. State the test command, commit, environment, and important **untested** areas; do not claim all checks pass because a PR is open or a partial suite succeeds.
5. Document schema migrations and backup/recovery requirements with every database feature. Do not commit passwords, real contact details, screenshots of customer evidence, or live tracking tokens.
6. Preserve historical documents; mark them **Historical snapshot** instead of silently rewriting past test results.

For public-facing project descriptions, say **multi-branch computer repair tracking with guarded staff workflows**. Do not advertise real-time GPS, online payments, automated SMS, customer chat/self-approval, complete COD handling, or production readiness.
