# RepairTrack MVP — accessibility and usability checkpoint

**Date:** 22 November 2026. **Status:** FOCUSED AUTOMATED CHECKS PASSED; human accessibility review is pending. No WCAG conformance claim.

## Changes in this checkpoint

| Area | Verified change |
| --- | --- |
| Customer tracking keyboard entry | The Skip to content link now focuses the main landmark (with `tabindex="-1"`) rather than simply changing the URL fragment |
| Customer form | Explicit `type="submit"` for View repair; code and PIN labels and their help descriptions are associated |
| Touch interactions | Public header/nav links, customer actions, details disclosure and timeline links have minimum 44px target heights |
| Motion | The unnecessary slide-in tracking form animation was removed; reduced-motion browsing stays stationary |
| Responsive | Tracking page verified at 320, 390, 768 and 1440 CSS pixels without page-level horizontal overflow |
| Existing admin overview | Previously fixed narrow-phone/tablet grid overflow, dashboard failure indicator, and misleading construction banner |

## Test method and evidence

`tests/mvp-accessibility-browser.mjs` runs in the isolated **public** browser matrix job before the existing customer tracking security and UI suite. Chrome performs keyboard-only Tab/Enter navigation to the skip link, verifies destination focus, field labels and help IDs, minimum input/interactive dimensions, reduced-motion state, empty required-field blocking, no-JavaScript rendering and viewport fit.

**PASS:** [GitHub Actions 44996725360](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725360), `quote-and-tracking-browser (public)` on code `2821057e`. Its log includes the accessibility PASS and the existing customer privacy/tracking browser PASS.

The same code checkpoint also passed [staff activity & intake](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725335) and [payment/pickup](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725374) suites. Screenshots use fictional accounts only.

## Not yet reviewed by people

These checks are **not** a full site-wide audit. Still required:

- A real screen-reader walk-through (e.g., NVDA on Windows, VoiceOver on mobile) of login, intake, staff repair, quoting, cash and public tracking; announcements, error summaries and dynamic content.
- Keyboard-only interactions with every staff modal, collapsible sidebar, dropdown, search filter, date picker and table; focus restoration after closing dialogs, no keyboard traps.
- Color contrast against WCAG AA, text zoom to 200% and reflow at 400%, color-independent status meaning and operating-system high-contrast preferences.
- Touch/one-handed device testing, including very small screens and long localized strings, plus comprehension of error and pickup instructions.
- Manual user acceptance scenarios and independent permission review, recorded in [UAT checklist](mvp-uat-checklist.md).

**Release implication:** Do not mark accessibility, UAT or production readiness as fully approved on this test alone. Defects affecting keyboard access, sensitive information, cash, or pickup are release blockers.

[Recovery rehearsal](mvp-recovery-rehearsal.md) · [Full regression report](mvp-test-report.md).
