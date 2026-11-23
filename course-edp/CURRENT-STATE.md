# RepairTrack — current academic status and requirement traceability

**Snapshot:** 2026-11-23 (Asia/Manila)  
**Branch:** `EDP-Course-MVP`  
**Source baseline:** `bd89daa910c53e3bcc024501d210c2fbb51174f3`  
**Automated evidence:** [GitHub Actions #45131879049](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45131879049) — completed successfully for that SHA, both `schema-integrity` and `windows-notepad-build`.

This is a **versioned checkpoint**, not a claim that later commits automatically passed. Verify the latest branch HEAD and its matching CI run before citing it. The independent `course-edp/` app uses **23 MySQL tables** and fictional records. It does **not** replace legacy `app/`, `admin/`, `views/`, `database/schema.sql` or the deployed website. Do not point the legacy PHP site at `repairtrack_edp`.

## Status legend

- **Implemented / automated:** source exists and the named disposable-runner suite exercises the indicated behavior; only the asserted conditions are verified.
- **Partial:** a narrow workflow exists, but the complete requirement or real-world assurance is missing.
- **Not integrated:** legacy/prototype functionality must not be counted as implemented in the academic app.
- **Manual evidence pending:** build or synthetic test passed, but the required actual device/OS/external service behavior has not been demonstrated.

## Current academic feature inventory

| Area | Actual implementation / source | Evidence | Status and boundaries |
| --- | --- | --- | --- |
| Isolated schema and catalogs | `schema.sql`, `seed_reference.sql`, `app/catalogs.php` | `tests/static-schema-check.php`, `db-integrity.php` | **Implemented / automated**; not a legacy migration |
| Intake and receipt | `public/index.php`, `app/intake.php`, `app/preview.php` | `tests/intake-flow.php`, `web-smoke.py`, `full-journey-http.py` | **Implemented / automated** for local fictional workflow; signature is staff-attested, not independently authenticated |
| Customer tracking and timeline | `public/track.php`, `app/tracking.php` | `web-smoke.py`, `full-journey-http.py` | **Implemented / automated** for read-only projection; no live chat/requests/downloads |
| Technician queue, diagnosis, quote draft, repair status | `public/technician.php`, `app/technician.php`, `app/actions.php` | `technician-flow.php`, `web-smoke.py`, `full-journey-http.py` | **Implemented / automated** for assigned fictional records, legal transitions, branch and capacity checks |
| First repair-start DateTime | `app/actions.php` (first authorized `IN_PROGRESS`) | `db-integrity.php`, `technician-flow.php`, `full-journey-http.py` | **Implemented / automated**; UTC storage and Philippine-time UI |
| Manager assignment and quotation publication | `public/manager.php`, `app/manager.php` | `manager-flow.php`, `web-smoke.py`, `full-journey-http.py` | **Implemented / automated**; offline approval remains staff-attested only |
| Cashier search, itemized bill, cash payments, receipt, pickup | `public/cashier.php`, `app/cashier.php`, `app/actions.php` | `cashier-flow.php`, `db-concurrency.php`, `full-journey-http.py` | **Implemented / automated** for counter cash and guarded in-person handover; not fiscal-grade; no COD/courier/refunds |
| Cross-role repair lifecycle | Role-specific pages and domain modules | `ACCEPTANCE.md`, `full-journey-http.py` | **Implemented / automated** via real localhost form submissions; real customer identity/consent not proved |
| HCI viewport/screenshot acceptance | `tests/browser-hci.py`, first-party CSS | `EDP-LAB-ACCEPTANCE.md`, screenshot artifact in CI | **Partial**; headless Chromium at 375/768/1366 px, sign-in and role-home/empty-state coverage, not populated workflows or assistive technology |
| Browser Notepad and folder monitor | `public/lab.php`, `public/assets/lab.js` | `tests/browser-hci.py` | **Partial**; synthetic browser file-open/save exercised, real directory permission/event demo pending |
| Native WinForms file watcher | `labs/NotepadCompanion/` | `windows-notepad-build` CI job | **Manual evidence pending**; compilation passes, actual desktop Open/Save/Created/Renamed/Deleted events not proved |
| External REST/JSON | `app/holiday-api.php`, `public/holiday-api.php` | `tests/edp-lab.php`, browser mocked JSON | **Partial**; parser/UI tested, live endpoint and disconnected-state demonstrations pending |
| Admin account/branch management, dashboard and role analytics | No integrated academic full administration module | No matching academic acceptance suite | **Not integrated**; manager desk is not full admin |
| Customer chat, technician parts requests, customer upgrade/cancel requests, SMS/email | No integrated academic end-to-end implementation | None | **Not integrated** |
| COD, courier/delivery, refund/correction, secure photo/video retrieval | No complete academic end-to-end implementation | None | **Not integrated** |
| Original RepairTrack deployment and data migration | Legacy paths still use their own schema | No 48-to-23-table mapping or migration acceptance | **Not integrated**; do not merge/repoint |

## Sogod prototype feedback map

| Presented feedback | Academic evidence | Gap |
| --- | --- | --- |
| Technician repair-start DateTime | Domain first-start timestamp, technician UI, cross-role SQL/HTTP verification | Actual production-time acceptance remains pending |
| Hover analytics with date and numbers | No academic analytics module | **Not integrated** |
| Aligned columns/row data; refined status board | Mobile Chromium basic overflow/label assertions; manager scroll hint | Populated analytics/status boards and actual-phone HCI not accepted |
| Cashier lookup using internal Ticket Code | `public/cashier.php`; exact `RT-` + 12 hex separate from public code; HTTP smoke | Real cashier usability and legal receipt process not accepted |
| Public 5-hex tracking code + phone-suffix PIN | `public/track.php`; code/PIN verification, throttle, revocation tests | Short credentials and local file throttling unsuitable as sole public defense |
| Technician assignment and workload limits | Manager assignment action and technician capacity/branch checks | Workload analytics / management still separate |
| Customer consultation and request handling | No academic end-to-end feature | **Not integrated** |
| Media, device images and branch-location tracking | Protected intake signature metadata and coarse branch/custody tracking | No authorized customer image/video viewer, detailed geographic routing or media-management acceptance |
| Desktop/mobile responsive behavior | Chromium 375, 768, 1366 screenshots and limited label/overflow checks | Manual 200% zoom, keyboard, screen reader, actual device, and populated-flow checks pending |

## Risks that block public deployment

1. **Customer authorization:** Quote approval is recorded by manager/staff attestation, not independently verified in a customer-authenticated action. Billing depends on that record. Treat it as a classroom scenario only until a consent and dispute-evidence process is implemented.
2. **Identity and portal exposure:** A 20-bit public code and four-digit phone-suffix PIN require careful anti-enumeration, distributed limits and monitoring before exposure; pickup phone matching/attestation is not proof of proxy identity.
3. **Data-access boundary:** The customer portal now imports shared `app/bootstrap.php`, not staff `preview.php`, but its PDO still uses configured academic credentials without an independently evidenced SELECT-only MySQL account. Domain guards cannot substitute for least privilege.
4. **Storage and fiscal correction:** No production-ready asset retrieval/scan/ownership workflow; no verified tax/discount/refund/void/adjustment lifecycle.
5. **Legacy compatibility:** No demonstrated migration, rollback, backup rehearsal or legacy-site integration with the 23-table schema.
6. **Human acceptance:** Automated UI checks cover a subset of screens and sizes only. Windows FileSystemWatcher execution, live REST behavior, real-device and assistive-technology checks remain manual.

## Next acceptance actions in order

1. **P0 — Documentation baseline:** keep this checkpoint, `README.md`, `ACCEPTANCE.md` and `EDP-LAB-ACCEPTANCE.md` consistent; do not describe historical slice gaps as current state.
2. **P1 — Manual Module 3 demonstrations:** on actual Windows, exercise `OpenFileDialog`, `SaveFileDialog` and .NET `FileSystemWatcher` (create, rename, delete); on a supported browser test permissioned `.txt` folder monitoring and unsupported fallback; validate live and offline REST behavior. Attach dated observations/screenshots to assessment notes.
3. **P1 — Broader HCI gate:** test populated repair details, quotes, receipts and pickup screens at mobile/tablet/desktop, 200% zoom, keyboard, screen reader and touch-target sizes; log and reproduce defects.
4. **P1 before public use — Security and consent:** define customer-verifiable quote approval, least-privilege DB connection(s), public throttling/HTTPS and device-collection identity policy; add negative tests.
5. **P2 — Requirements closure:** explicitly scope admin analytics, customer messages/requests, parts/media, COD and refunds against assignment rubric; implement only approved features with role/privacy tests.
6. **P3 — Migration decision:** choose a standalone classroom deliverable or a separate, backed-up legacy integration project. No migration to `main` without schema mapping, rollback and end-to-end regression evidence.

**Gate:** GO for isolated academic iteration after exact-SHA CI; HOLD for original-site merge, public customer data, or claims of production-ready security/payment compliance.
