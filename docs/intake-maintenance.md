# Changing the intake without rewriting the page

Read `workflow-blueprint.md` and open `wireframes/index.html` first.

| Change requested | Edit |
|---|---|
| Form arrangement, labels, help text | `views/intake/create.php` |
| Spacing, colors and responsive layout | `css/intake-wizard.css` |
| Step movement and review text | `js/intake-wizard.js` |
| Required fields, limits and choices | `app/intake/rules.php` |
| Table/column mapping and transactional writes | `app/intake/repository.php` |
| Request flow, redirects and error display | `admin/intake_create.php` |

The controller checks login and CSRF, reads known fields, validates them, saves a transaction, then loads the template. The repository uses ordinary PDO prepare/execute statements. There is no framework or dependency injection container.

Keep browser validation and server rules consistent. Changing an enum label does not change its stored value. A schema change needs a separate additive migration. Never put SQL, authorization decisions or secrets in a view or JavaScript. Do not use the wireframe role selector for real login.

This feature still saves one-device drafts. It does not issue tickets or implement customer access, new roles, pickup, chat or billing.

## Local checks before merging
1. Run PHP lint on the controller, both `app/intake/*.php` files and the view.
2. With the existing database, create a draft for a new customer and then an existing customer.
3. Submit invalid data after selecting an existing customer; its selection and all other values must remain.
4. Try a duplicate name; confirm a different person only after reviewing matches.
5. Tamper with branch ID and CSRF token; neither request may save.
6. Submit oversized fields directly; they must be rejected server-side.
7. Force a write failure in an isolated test database; no partial customer/ticket/device/audit rows may remain.
8. At phone/tablet/desktop widths, use Back, review condition/serial/arrival, and confirm the save redirects to the workbench.
