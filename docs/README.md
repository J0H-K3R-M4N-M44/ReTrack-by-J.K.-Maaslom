# RepairTrack documentation index

**Recommended current starting point:** [MVP scope and stabilization](mvp-stabilization.md). The current branch is a reviewable MVP effort, **not a production release**.

## Current verification and handover

- [Current MVP requirements traceability](requirements-traceability-current.md): source/test/status/acceptance mapping and scope boundaries
- [Deployment security issue register](mvp-deployment-security-register.md): P0 hypotheses, host-verification gates and safe handling
- [Hosted-pilot verification record](hosted-pilot-verification-2026-11-22.md): read-only observation, network limitation and redacted host-owner evidence checklist
- [Storage location staff workflow](staff-storage-locations.md): assigned-branch bin/workbench setup, scope and acceptance checks

- [Full end-to-end regression report](mvp-test-report.md): one actual service-layer ticket plus independent browser journeys and broad CI evidence
- [Manual user acceptance checklist](mvp-uat-checklist.md): 20 executable staff/customer scenarios, **awaiting human sign-off**
- [Windows/XAMPP acceptance and recovery handoff](mvp-windows-acceptance-handoff.md): safe, operator-led next steps; **not yet executed**
- [Windows fictional evidence integrity check](windows-evidence-integrity-check.md): verified PowerShell file-hash comparison; **not a SQL restore**
- [Integrated release candidate handoff](mvp-release-candidate-handoff.md): current candidate integration and promotion blockers
- [Focused accessibility review](mvp-accessibility-review.md): verified public keyboard/mobile checks, with remaining screen-reader/contrast work
- [Database and private evidence recovery rehearsal](mvp-recovery-rehearsal.md): verified isolated restore and operational stop conditions

## Functional modules and existing guides

- [Staff consoles](staff-consoles.md), [staff workspace](staff-workspace.md), [ticket issuance](ticket-issuance.md)
- [Customer tracking](customer-tracking.md), [quoting](quote-and-approval.md), [billing and handover](payment-and-handover.md)
- [Schema demo](database-demo.md), [migration runner](migration-runner.md), [current system gaps](system-gaps.md)
- [Workflow blueprint](workflow-blueprint.md), [interface wireframes](wireframes/index.html)

These older documents describe features and intermediate milestones and may not reflect the very latest stabilized branch. Use the current CI report for tested claims. Optional partial-device repair ownership remains deferred in [draft PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55).
