# Staff accounts

Administrators open **Staff management** from the dashboard. Search, role and access filters help locate existing accounts; the directory shows 12 records per page and the full matching count.

## Add a person

1. Enter their name and email.
2. Choose Manager, Intake agent, Technician, Courier or Viewer.
3. Select their assigned branches and one home branch.
4. Review the details, then create the account.
5. Privately share the activation link shown once. The staff member chooses their own password, activates the account and signs in through the normal login page.

Accounts remain inactive until activation. Links expire after 24 hours and work once. **No invitation email is sent automatically.** Set `app_url` in your ignored configuration to the reachable site URL, including any project folder. A localhost address only works on that same computer. If a link is lost, manage the inactive account and generate a replacement; the previous unused link stops working.

## Screen layout

Desktop: heading and dashboard link → three-column identity/role form → branch checkboxes and home-branch radio buttons → review → searchable staff directory.

Mobile: the form becomes one column. The directory scrolls horizontally inside its own container. Forms and confirmation steps work without JavaScript; JavaScript adds copy-link and error-focus conveniences.

## Manage access

Use **Manage** to edit identity, role or branch assignments. Review before saving. Deactivate accounts instead of deleting them so repair and audit history remain intact. Deactivation invalidates existing sessions and outstanding links. An inactive account can receive a new activation link.

Only active administrators can manage staff, and they must have access to every branch assigned to the target account. Partial overlap is insufficient. New assignments must be active branches assigned to that administrator. The page cannot create or modify administrators; use the existing administrator CLI setup.

## What staff can use today

Active staff sign in to their role-specific console. Managers and intake agents can prepare and issue intake; technicians can update assigned repairs and save private notes. Couriers have a branch manifest and viewers have read-only status access. See [staff consoles](staff-consoles.md) for exact permissions and remaining features. There is no Cashier role in the existing schema.

## Database and implementation

No tables, columns or migrations were added. The feature reuses:

| Existing table | Purpose |
| --- | --- |
| users | Identity, role, password hash and active state |
| user_branch_assignments | Allowed branches and home branch |
| password_reset_tokens | Hashed, expiring activation tokens |
| audit_events | Account changes and activation |

Activation links begin with `staff_`; recovery links accept only the original 64 hexadecimal characters. Both store hashes, never raw tokens, and the endpoints enforce their own token format. Activation checks an inactive non-admin account and an active branch assignment. Replacement and deactivation revoke unused tokens.

Writes use transactions, prepared statements, CSRF checks, expiring one-use review forms and stale-edit detection. Permissions are checked again at save time. Sessions compare current role, email and password fingerprint on each protected request. Older sessions without those checks must sign in again after this update.

Directory filtering performs a count and a page query instead of loading every staff record. This is a query-design improvement, not a measured production performance claim.

## Source guide

- `app/staff/repository.php`: validation, branch scope and transactional account operations.
- `admin/staff.php`: request handling and review state.
- `views/staff/manage.php`: form, review and directory markup.
- `admin/activate.php`: staff password setup.
- `app/auth.php`: shared authentication and role gates.
- `admin/staff_home.php`: honest staff entry page.
- `tests/staff-management.php` and `tests/staff-browser.mjs`: disposable database and browser regression tests.

## Research behind the choices

- [OWASP authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): least privilege, deny by default and authorization on each request.
- [OWASP password recovery guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html): random, hashed, expiring single-use links; no automatic login after password setup.
- [GOV.UK check answers](https://design-system.service.gov.uk/patterns/check-answers/): review details before committing.
- [GOV.UK validation](https://design-system.service.gov.uk/patterns/validation/): retain entered values and show actionable errors.
- [W3C form labels](https://www.w3.org/WAI/tutorials/forms/labels/): explicit labels for inputs.
- [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php): use the built-in password hashing API.

## Validation performed

Verified on PHP 8.4 and MariaDB 11.8 using a disposable seeded database, with real Chromium HTTP/browser tests. PHP syntax and all three database suites passed (existing admin intake, ticket issuance, and new staff accounts). All four browser suites passed, including existing recovery/upload workflows and new account activation, unauthorized-role rejection, form replay/CSRF rejection, edit/back review, deactivation ending a session, mobile width and no-JavaScript operation.

Token expiry/reuse, activation-versus-recovery separation, transactional rollback, stale edits, pagination and cross-branch denial were tested. This run did not test production mail delivery or a live MySQL server; the local mail sink was used for existing recovery regression tests.
