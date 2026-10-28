# RepairTrack interface wireframe

This page map is the screen structure for the mock multi-branch repair tracker. Keep the current steel, copper, and gold palette and make each page work as a narrow mobile column before widening it for desktop.

## Customer path

`Landing → Check-in guidance → Counter intake → Ticket reference → Repair updates → Pickup`

| Page | Mobile structure | Desktop structure | Main action |
|---|---|---|---|
| Landing | Video hero, short value statement, two clear buttons; process cards stack vertically | Full hero video with overlay, sticky compact navigation, four process cards in a row | Read the process or open staff sign-in |
| Check-in guidance | Three short preparation bullets and branch contact | Two-column guidance and branch panel | Bring the computer to a branch |
| Repair updates | Ticket code and contact verification, then customer-safe status timeline | Ticket summary beside a vertical timeline | See the latest published update |
| Pickup | Ready status, branch and opening hours, bring-reference reminder | Pickup card plus repair summary | Confirm pickup with the branch |

Customer pages must only show an approved public status summary. They must never expose internal notes, staff-only identity information, passwords, serial data, or another customer's ticket.

## Counter officer path

`Staff sign-in → Branch workbench → New intake → Customer → Device → Review → Draft saved → Issue checks`

| Step | What the officer sees | Keep it simple |
|---|---|---|
| Customer | Find by name, phone, or email; choose a match or add a new customer; branch is preselected | Ask for only one reliable contact |
| Device | Device type, make/model, optional serial number | Use everyday labels and examples |
| Problem and review | Customer's own words, visible condition, arrival method, final review | Save a draft first; do not make the customer diagnose it |

The technician or intake officer adds the accessory checklist, photographs, and condition sign-off before the accountable officer issues the ticket. The customer confirms the recorded condition at the counter.

## Technician path

`Staff sign-in → My queue → Repair detail → Diagnosis → Work update → Test result → Ready for pickup`

On a phone, show the assigned queue as stacked repair cards with status and due/age information. Keep diagnosis notes and customer-facing updates in distinct fields. The technician can update work progress but should not alter the original intake record.

## Admin and branch lead path

`Staff sign-in → Overview → Branch filter → Intake review → Staff and branch setup → Reports`

The overview uses a small KPI row, an actionable intake queue, repair status totals, and recent activity. Branch selection stays visible in the page header. Intake review highlights missing evidence before the ticket can be issued; destructive actions require a confirmation and leave an audit event.

## Shared interaction rules

- Use a short, visible progress indicator for multi-step entry and keep entered values when moving back.
- On phones, use one column, large touch targets, no horizontal page scrolling, and a sticky bottom action row where it does not hide fields.
- Put errors beside the related field and summarize them at the top; never rely on color alone.
- Use progressive disclosure for rare or role-specific fields, such as optional identifiers and issue gates.
- Every navigation action has a clear destination, and every saved state has a confirmation.
