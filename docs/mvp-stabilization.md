# RepairTrack — MVP stabilization decision and first fix

**Date:** 22 November 2026. **Branch:** MVP-Stabilization, based directly on main.
This is an academic project stabilization checkpoint, not a production release.

## Decision: stop expanding partial-device transfers for the MVP

**One repair ticket has one accountable repair branch.** A whole-ticket repair
transfer may be accepted by an authorized manager when all devices are physically
received and the existing repair/stock/bill guards permit reassignment. Actual
physical custody is recorded independently and does not grant repair rights.

Partial-device branch ownership, independent sibling billing and cross-branch
quote/stock authorization are **optional and deferred**. Draft PR #55 is preserved
as test-backed future research, not merged or deployed. Keep its custom
repair_scope_mode feature flag and additive schema out of this MVP branch.

## First deliverable: operational dashboard UX

The previous administrator overview displayed a prominent "Under construction"
marker despite functional PHP queues and metrics. This milestone replaces that
misleading warning with functional New intake, Review repairs and Cash desk
shortcuts, improves keyboard focus and touch-target sizing, and labels a device
queue accurately rather than claiming it is a complete audit-event feed.

A genuinely failed dashboard query now uses an amber/unavailable indicator in
both the header and sidebar, with metric counts displayed as unavailable rather
than falsely reporting zero. The original server-side authorization and query
logic remain intact.

The disposable-MySQL Chrome test validates the dashboard, real links, error
state, no-JavaScript fallback, tab isolation and 320/390/768/1440px layouts.
Workflow: .github/workflows/mvp-stabilization.yml.

## Verified customer-to-pickup regression round

[Full test report](mvp-test-report.md) documents a complete single-ticket PHP lifecycle and
independent, real-browser tests for quote decisions, private customer tracking and dashboard
navigation. All four targeted jobs passed in [run 44996054209](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996054209).
The broader [payment/checkout](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996050302)
and [intake/staff](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996050309)
regressions also passed against the tested application commit.

A mobile tracking animation was removed after the original public browser suite could not
reliably activate **View repair** with JavaScript disabled. The rerun passed without changing
the tracking code/PIN rules. A one-ticket *browser* journey across every role remains separate
from the verified single-ticket *service-layer* test.

## Second checkpoint: accessibility, UAT planning and isolated recovery

- [Verified accessibility smoke test](mvp-accessibility-review.md): keyboard skip focus, input names/help, 44px customer targets, reduced motion, no-JavaScript interaction and small screens passed in [CI 44996725360](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725360). A full staff screen-reader/contrast audit is **not done**.
- [Twenty-scenario manual user-acceptance checklist](mvp-uat-checklist.md) prepared for real testers; **no human sign-off claimed**.
- [Disposable SQL + evidence recovery rehearsal](mvp-recovery-rehearsal.md) passed in [CI 44996590305](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996590305), including 47 database tables and hashed private files. **No production restore performed.**
- Broader [intake/staff](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725335) and [payment/pickup](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725374) suites also passed at the latest tested application checkpoint.

## Staff navigation refinement and local acceptance handoff

An additional automated staff keyboard test has passed, including mobile no-JavaScript destinations and Escape focus restoration. See the [accessibility report](mvp-accessibility-review.md). The [Windows/XAMPP handoff](mvp-windows-acceptance-handoff.md) now describes the operator-only acceptance and private-file restore procedure. Neither real staff UAT nor a local Windows restore has occurred.

## Practical MVP completion checklist

1. Verify mainline login, intake/issuance, technician workflow, public tracking,
   customer privacy, cashier/pickup and whole-ticket transfers in disposable tests.
2. Fix confirmed UX defects, including error feedback, mobile overflow, empty
   states, accessibility labels and keyboard interaction. Avoid large redesigns.
3. Prepare staff/customer user acceptance test cases with fictional data and
   explicit expected results.
4. Run backup **and restore** rehearsals (database and private evidence files).
5. Audit permission boundaries, existing schema migration readiness and test
   coverage, but do not invent more roles, payment rails, or approvals.
6. Update the final academic documentation with screenshots, diagrams and
   limitations. Promote only focused, green commits after review.

## Deliberately not in this stabilization milestone

No changes to PHP authorization, SQL schema, payments, customer PIN, inventory
ledger, physical custody, external mail, live location, or production deployment.
This branch does not contain PR #55's optional migration or restrict-only feature.

**Source of truth:** current mainline code and verified CI results. Never rely
on historical demo pages as evidence that an integration exists.
