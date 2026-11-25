# RepairTrack MVP — accessibility and usability checkpoint

**Updated:** 25 November 2026. **Status:** FOCUSED AUTOMATED CHECKS PASSED; human accessibility review is pending. No WCAG conformance claim.

## Changes in this checkpoint

| Area | Verified change |
| --- | --- |
| Customer tracking keyboard entry | The Skip to content link now focuses the main landmark (with `tabindex="-1"`) rather than simply changing the URL fragment |
| Customer form | Explicit `type="submit"` for View repair; code and PIN labels and their help descriptions are associated |
| Touch interactions | Public header/nav links, customer actions, details disclosure and timeline links have minimum 44px target heights |
| Motion | The unnecessary slide-in tracking form animation was removed; reduced-motion browsing stays stationary |
| Responsive | Tracking page verified at 320, 390, 768 and 1440 CSS pixels without page-level horizontal overflow |
| Existing admin overview | Previously fixed narrow-phone/tablet grid overflow, dashboard failure indicator, and misleading construction banner |
| Staff repair-status badges in Linen theme | Added dark, status-specific label colors on light surfaces; all nine status variants pass a Chrome-computed foreground/background contrast check at 4.5:1 or higher |

## Test method and evidence

`tests/mvp-accessibility-browser.mjs` runs in the isolated **public** browser matrix job before the existing customer tracking security and UI suite. Chrome performs keyboard-only Tab/Enter navigation to the skip link, verifies destination focus, field labels and help IDs, minimum input/interactive dimensions, reduced-motion state, empty required-field blocking, no-JavaScript rendering and viewport fit.

**PASS:** [GitHub Actions 44996725360](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725360), `quote-and-tracking-browser (public)` on code `2821057e`. Its log includes the accessibility PASS and the existing customer privacy/tracking browser PASS.

The same code checkpoint also passed [staff activity & intake](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725335) and [payment/pickup](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44996725374) suites. Screenshots use fictional accounts only.

## Light-theme status contrast — 25 November 2026

A screenshot review of the local fictional-data preview found that pale repair-status labels remained in place when the workspace switched to the light Linen theme. The labels were difficult to distinguish from the near-white panels. The stylesheet now supplies dark, status-specific foreground colors and matching light backgrounds for the nine repair-state badges (including diagnosis, parts wait, active repair, testing, ready, completed and cancelled).

The Chrome regression creates each status badge using the real workspace stylesheet, reads its computed foreground/background colors, and checks the WCAG text contrast formula. **PASS:** all nine badge combinations exceed the 4.5:1 normal-text threshold in the dashboard job of [MVP dashboard usability regression run 45590938947](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/45590938947). This is a focused automated check only; it is not a site-wide contrast audit or WCAG certification.

## Staff workspace keyboard/no-JavaScript checkpoint

A focused automated check now covers the **cashier staff workspace** as well as public tracking. On narrow screens, the ordinary More shortcut uses JavaScript, so a native link list is now rendered **only when JavaScript is disabled**, with the same role-authorized destinations and at least 44px tall links. The separate browser check verifies the keyboard-first skip link, menu search/filtered destinations, Escape dismissal with focus restored, and the no-JavaScript fallback at 320, 390 and 700px. It does not change server permissions or authorize new navigation destinations.

The staff-activity regression passed on [run 44997694205](https://github.com/J0H-K3R-M4N-M44/ReTrack-by-J.K.-Maaslom/actions/runs/44997694205) at implementation SHA `475c89c8`, including this new check.

## Not yet reviewed by people

These checks are **not** a full site-wide audit. Still required:

- A real screen-reader walk-through (e.g., NVDA on Windows, VoiceOver on mobile) of login, intake, staff repair, quoting, cash and public tracking; announcements, error summaries and dynamic content.
- Keyboard-only interactions with every staff modal, collapsible sidebar, dropdown, search filter, date picker and table; focus restoration after closing dialogs, no keyboard traps.
- Color contrast against WCAG AA, text zoom to 200% and reflow at 400%, color-independent status meaning and operating-system high-contrast preferences.
- Touch/one-handed device testing, including very small screens and long localized strings, plus comprehension of error and pickup instructions.
- Manual user acceptance scenarios and independent permission review, recorded in [UAT checklist](mvp-uat-checklist.md).

**Release implication:** Do not mark accessibility, UAT or production readiness as fully approved on this test alone. Defects affecting keyboard access, sensitive information, cash, or pickup are release blockers.

[Recovery rehearsal](mvp-recovery-rehearsal.md) · [Full regression report](mvp-test-report.md).
