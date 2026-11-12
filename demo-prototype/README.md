# RepairTrack complete demonstration

Open `index.html` in a modern browser, or use the hosted demo. Use the role selector to explore Public, Customer, Admin, Technician, Cashier and Collector workspaces. No account or real password is needed.

Tracking code: `DEMO-1042`. Separate demo PIN: `2468`.

Use **Wireframe view** to explain the structure, **Screen sizes** to preview phone/tablet/desktop, and **Reset demo** before another presentation. Changes stay in this browser's local storage, not in the real RepairTrack database.

## Suggested walkthrough
1. Start with Home, the MP4 hero, tracking code and pickup request.
2. Show the customer's independent device statuses, location, verified messages, requests and costs.
3. Switch to Technician. Open RT-1042, diagnose each device, and create catalog quotations.
4. Switch to Customer, verify Costs, and approve each quote.
5. Switch to Technician. Request any required part. Use Admin > Inventory to reserve stock, then record usage. Complete tests and mark each device ready.
6. Use Cashier to generate the bill, record cash and return devices. Use RT-1044 for a short pre-seeded payment example.
7. Use Admin for assignment, inventory, transfers, notifications and reports. Use Collector for confirmed pickups and handoffs.
8. Explain that pickup coverage is staff-confirmed, device location is confirmed custody, and COD needs cashier remittance confirmation.

## Modules and files
- `data.js`: seed records, catalog prices, state defaults.
- `app.js`: rendering helpers, screens, dialogs, workflow actions and form handlers.
- `styles.css`: design tokens, desktop/mobile layouts, print and wireframe styles.
- `responsive.html`: interactive device-size preview.
- `vendor/qrcode.js`: vendored MIT QR generator.

## Demonstration limits
This is a browser-only simulation, not production authentication or financial software. All records, PINs, receipts, customers and amounts are fictional. Roles are deliberately switchable. There is no live chat server, email/SMS, digital payment, real map/GPS, actual photo upload or legal signature. QR generation works; camera scanning depends on browser support, permission and HTTPS, with manual code entry as fallback. Camera operation was not tested. A local file QR cannot be opened on another device without hosting the demo.

The prototype captures cash at the final-bill stage, partial payments, refunds and COD remittance. Production policies for deposits, cancellation charges and signatures still need approval. The demo records a photo filename only. Catalog editing, date-range analytics and granular staff permissions are production extensions; sample prices and all-record reporting demonstrate those concepts.

## Verification
JavaScript syntax and DOM tests passed for 20 screens, multi-device approval/repair/testing/billing/return, stock reservation and usage, transfer custody, overpayment rejection and COD remittance. Browser checks confirmed tracking, diagnosis, quotes and shared customer decisions. Responsive preview showed no horizontal body overflow at phone, tablet and desktop sizes. No PHP or live database is used.
