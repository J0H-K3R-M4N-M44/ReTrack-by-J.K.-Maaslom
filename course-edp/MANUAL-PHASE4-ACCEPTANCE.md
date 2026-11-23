# RepairTrack — Phase 4 manual HCI and EDP acceptance record

**Branch:** `EDP-Course-MVP`  
**Purpose:** record checks which headless PHP/MySQL/Chromium and .NET build jobs cannot certify.  
**Environment:** isolated `course-edp/` classroom app with fictional records; no deployed system, real customer data, production database, or live private media.  
**Status at creation:** **NOT EXECUTED BY A HUMAN**. Blank results are not passes.  
**Supporting automated baseline:** see [CURRENT-STATE.md](CURRENT-STATE.md), [POPULATED-HCI-REVIEW.md](POPULATED-HCI-REVIEW.md), and the exact-commit GitHub Actions run noted in each.

## Record before beginning

| Field | Actual observation |
| --- | --- |
| Tester / role | _Not recorded_ |
| Date and time (Asia/Manila) | _Not recorded_ |
| Git commit / branch | _Not recorded_ |
| OS, browser and versions | _Not recorded_ |
| Device and physical viewport | _Not recorded_ |
| Database reset and fictional fixture confirmation | _Not recorded_ |
| Result evidence location (non-sensitive screenshots) | _Not recorded_ |

Use **PASS**, **FAIL**, **BLOCKED**, or **NOT RUN** for each scenario. A PASS needs observed steps and evidence; CI screenshots alone cannot establish physical-device or assistive-technology acceptance.

## HCI acceptance scenarios

| ID | Human action / evidence required | Pass criteria | Result |
| --- | --- | --- | --- |
| HCI-01 | Open intake/manager/technician/cashier and customer portal on a physical phone at portrait and landscape sizes | Text and controls readable without whole-page horizontal scroll; forms, error messages and main action visible | NOT RUN |
| HCI-02 | Set browser zoom to **200%** at a typical desktop viewport, then retry at **400%** for reflow observation | Required fields and actions remain accessible; scroll behavior documented; no overlapping elements that hide content | NOT RUN |
| HCI-03 | Use **Tab**, **Shift+Tab**, **Enter**, **Space**, and native form widgets on each role's login and authenticated workflow | Focus is visible and sequential; no keyboard traps; forms submit or block invalid input correctly | NOT RUN |
| HCI-04 | Test intake draft with one and several devices, empty required fields, edit, abandon and issuance | Human tester can correct errors without losing already-entered data; receipt data is understandable | NOT RUN |
| HCI-05 | Manager: locate pending quote; try missing decision, missing reference, unchecked attestation, valid demonstration | Clear required validation; no accidental approval; decision semantics communicate staff attestation, not independent consent | NOT RUN |
| HCI-06 | Technician: open assigned repair, update diagnosis and legal statuses, observe repair-start time and history | Status, start time, transitions and private/public distinction are understandable; errors can be recovered | NOT RUN |
| HCI-07 | Cashier: search Ticket Code, issue a fictional bill, record partial cash and attempt an overpayment | Balance is readable; error explanation recoverable; successful payment yields a receipt, unpaid pickup stays unavailable | NOT RUN |
| HCI-08 | Customer: code/PIN, pending quote, completed two-device timeline and sign-out | Only customer-safe data visible; status/location/estimate distinct from bill; long timelines remain understandable | NOT RUN |
| HCI-09 | With a screen reader (NVDA/Windows or VoiceOver/macOS/iOS), traverse headings, labelled controls, alerts and tables | Meaningful names, roles and descriptions are announced; controls work without pointer; statuses aren't color-only | NOT RUN |
| HCI-10 | Test touch target sizes and spacing on a physical phone, including checkbox and dense cashier rows | Taps reliably hit intended targets; dense rows and receipts remain operable | NOT RUN |
| HCI-11 | Test screen reader announcements and focus after failed cashier payment or malformed customer login | Error is perceivable, focus path remains logical, user can correct and retry | NOT RUN |
| HCI-12 | Ask a first-time tester to find archived manager detail and summarize a two-device timeline | Record observed errors and timings; distinguish discovery/length problems from technical bugs | NOT RUN |

## EDP course-lab acceptance scenarios

| ID | Human action / evidence required | Pass criteria | Result |
| --- | --- | --- | --- |
| EDP-01 | In a supported local browser, open an actual UTF-8 `.txt`, edit, save and reopen | Exact edited text persists on disk; user gesture and permission prompts documented | NOT RUN |
| EDP-02 | Grant a local folder permission, then create/rename/delete sample `.txt` files; repeat in an unsupported browser | File-change UI behaves within declared browser limitations; permission-denied/unsupported fallback is comprehensible | NOT RUN |
| EDP-03 | On a **real Windows .NET 8** machine, run `labs/NotepadCompanion/` and use OpenFileDialog/SaveFileDialog | Native dialogs work; saved content is recovered; no test file outside a disposable folder is altered | NOT RUN |
| EDP-04 | In the same WinForms companion, watch a disposable folder, create/rename/delete an actual `.txt` | `FileSystemWatcher` events visibly appear; repeat operations, note OS-specific behavior | NOT RUN |
| EDP-05 | Load the public Philippine holiday reference over a real permitted internet connection; then test offline | Successful JSON is rendered without introducing shop-hours claims; offline/error state is nonblocking and readable | NOT RUN |

## Accessibility and interaction notes

Capture for each failure: **scenario ID, reproduction steps, expected result, observed result, browser/OS and commit, screenshot/recording reference, severity, owner, retest result**. Do not capture portal PINs, passwords, actual customer phone numbers, real signatures, or live private records. If a browser screenshot contains fictional credentials, redact them before sharing.

## Acceptance gates

- **Automated acceptance:** requires an exact-SHA green CI run and explicit evidence that refused operations did not change the database. CI green does **not** mark the human scenarios above complete.
- **Manual Phase 4 HCI:** remains **OPEN** until relevant HCI scenarios have dated, recorded operator results; blocked steps must be listed honestly.
- **Manual EDP demonstration:** remains **OPEN** until Windows file monitoring and real browser/API use are executed, not merely compiled or mocked.
- **Public deployment and legacy migration:** separate **HOLD** decisions; never inferred from this course checklist.

**Final reviewer / date / decision:** _Not signed._
