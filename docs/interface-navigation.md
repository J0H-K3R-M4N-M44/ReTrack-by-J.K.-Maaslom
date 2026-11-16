# Interface navigation update — 16 November 2026

Built from main on the Interface-Navigation branch. No database migration or permission changes are required.

## What changes

- Administrators can hide/show the desktop navigation panel. The preference is remembered in this browser when local storage is available.
- On screens up to 760 pixels wide, admin and staff navigation opens as a full-screen menu. The same links are moved into a native dialog; there is no duplicate sidebar to maintain.
- Close navigation or press Escape to return to the opener. Tab and Shift+Tab stay inside the open menu. Links remain visible in an ordinary layout without JavaScript.
- The administrator uses an account disclosure for Public home and POST-protected logout, matching staff consoles. Escape closes it; clicking outside also dismisses it.
- Search/status filters remain visible above the work list. Empty filtered lists offer Show all records; an empty unfiltered section explains that no scoped records exist. Empty drafts offer an intake shortcut.
- Admin overview has intake and repair-queue actions instead of the old Under construction badge. The recent-work list is correctly described as recently updated devices, not a notification or audit feed.
- Metric-card decorations stay inside the cards, fixing mobile horizontal overflow while preserving analytics tooltips. Mobile navigation text and keyboard focus are clearer.

## Code map

`js/navigation.js` handles the optional navigation enhancement and account dismissal. `css/navigation.css` covers the menu, admin account and mobile fallback. Existing role and branch checks still decide which links and rows are rendered. `css/workspace.css` adds active filter chips and table keyboard focus.

The native dialog supplies modal semantics; the script keeps keyboard focus inside and restores it on close. Browser storage failure does not stop navigation. On desktop, the staff section tabs remain visible; only the admin's side panel has a saved hide/show preference.

## Verification

Run the existing staff-console/public/workspace tests on a disposable `repairtrack_test_*` database. Run `tests/interface.php` to prepare its administrator fixture, then `tests/interface-browser.mjs` with the local test environment variables described in staff-workspace.md. The fixture changes the seeded test administrator's credentials and refuses non-test databases.

Checks cover desktop preference persistence, mobile links at 320/390 pixels, keyboard focus and Escape, account dismissal, navigation across pages and screen widths, active/empty filters and no-JavaScript navigation. Screenshots are local test outputs and are not committed.

This update does not add notifications, settings themes, payment recording or final handover workflows. Those remain separate milestones.
