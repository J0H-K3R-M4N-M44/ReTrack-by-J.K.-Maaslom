# Public home content

The home page now explains the visit from check-in through assessment and collection, lists what customers should prepare, describes team responsibilities, and answers common questions using native expandable FAQ elements.

The page distinguishes current administrator intake, ticket reference and staff-account capabilities from planned public tracking, quotes, billing and role-specific tools. It does not invent operating hours, shop contact details or repair turnaround promises.

The dark and amber visual style remains. Cards stack on mobile; navigation and FAQs remain usable without JavaScript. The video source loads only when JavaScript runs and neither reduced motion nor data saving is requested. Otherwise the poster remains visible. This avoids an unnecessary large background download in those cases; no broad performance benchmark is claimed.

The content follows the same clear labels and progressive disclosure principles recorded in [staff management research](staff-management.md). Validate it against the actual branch's policies before presenting it as public business information.

The independent `tests/home-browser.mjs` checks mobile width, no-JavaScript navigation and FAQ interaction, plus no MP4 request under reduced motion.
