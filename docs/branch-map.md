# Branch map and code provenance

**Current review:** 22 November 2026. This page replaces the November 8–15 consolidation branch plan as a *navigation* reference; the original merge history remains available in Git and [the historical comparison](current-vs-simplified.md).

## Branches that matter today

| Branch/ref | Role | Status at this checkpoint |
| --- | --- | --- |
| `main` at `f817154f2e5f5ac04daf8bf0c5b3dd0167e17f0b` | Last checked integrated PHP application baseline | Has auth, intake, customer tracking, repair consoles, billing/cash, whole-ticket device logistics, stock pilots and stocktaking |
| `Partial-Ticket-Ownership` | Active feature/documentation branch | Draft [PR #55](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/pull/55); **not merged** |
| `Demo-System` | Separate interactive demonstration and presentation data | Simulated actions are not automatically backend features |
| `Google-Maps-Tracking` | Earlier demo maps/alerts concepts | Not a verified live branch-location/GPS integration |
| `Simplified-System`, `Staff-Workspace`, `Device-Handovers`, `Branches-Logistics`, `Repair-Operations` | Earlier development sources | Previous comparison showed their useful incorporated work was already present on `main`; do not wholesale merge outdated files |

Other historical names (including `PHP-Bill-Corrections`, `PHP-Stock-Pilot`, `Interface-Navigation` and feature-prefixed branches) may still exist in Git. **Recompare against the latest main head before any reuse; no cleanup/deletion is authorized by this document.**

## Why not merge the old category branches?

The November 22 comparison found old modules behind current integrated PHP/SQL features, or diverged from `main`. Cherry-picking a whole historical branch can reintroduce outdated permission, stock, billing or presentation logic. A review must inspect a **specific file or commit** first, reimplement safely against current main, and run the affected tests. This is especially true for browser maps and prototype notification code.

## Current work branch

PR #55 adds only: an additive per-device branch field, compatibility with whole-ticket acceptance, read-only ownership labels, separate proposed authorization scopes, negative tests, migration recovery and MySQL integrity CI. Its proposed per-device repair scope is **not used for operational actions**. A receiving branch can handle physical custody without gaining ticket billing authority. See [ownership plan](partial-ticket-ownership.md) and [security audit](authorization-cutover-audit.md).

## Safe Git procedure

~~~bash
git fetch origin
git status
git branch --show-current
git log --oneline --decorate -8
git diff --stat origin/main..origin/Partial-Ticket-Ownership
~~~

Before any merge, check latest refs again, verify PR checks, review every changed SQL/PHP authorization path, and **do not** force push, delete branches or run a migration as a side effect. `main` and local database state remain unchanged simply because documentation was updated.

[Documentation index](README.md) · [Release status](release-status.md) · [Historical cleanup reference](branch-cleanup.md).
