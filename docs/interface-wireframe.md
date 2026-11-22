# RepairTrack wireframes

> **Static prototype only.** The clickable wireframes use fictional screens and do not authenticate or persist real device/cash records. The full PHP implementation and open gaps are in [system overview](system-overview.md). Do not use prototype login, tracking codes, mock chat or simulated pickup controls as evidence of live backend features.

Read [the workflow blueprint](workflow-blueprint.md) first. Open `docs/wireframes/index.html` in a browser for a clickable, responsive preview; no server or database is required.

The page selector previews landing, shared staff login, customer tracking, intake, technician queue, branch overview and pickup request. Use tracking code `DEMO-123`. All content is fictional and every action is a demonstration. No values are sent or persisted.

The initial intake is Customer → Device and problem → Review. Back retains entries. Review displays the captured information before draft confirmation. This prototype does not bypass the existing database issuance requirements.

## Review checklist
- At 375px, 768px and 1440px: no horizontal page overflow; readable labels and controls.
- Track the demo code and check Overview / Location / Messages / Requests / Costs.
- Complete intake, go Back, change the problem, and confirm the review updates.
- Try continuing without required fields; the browser should focus the missing input.
- Use keyboard Tab, Enter and the mobile navigation disclosure.
- Verify that pickup requests are pending confirmation and that sample actions never claim a real save.

## Replaceable boundaries
The blueprint owns workflow decisions. `wireframe.css` owns preview layout and design tokens. `wireframe.js` owns sample screens and interactions. Production PHP stays separate: copying a demo role selector or customer token into authentication is never appropriate.
