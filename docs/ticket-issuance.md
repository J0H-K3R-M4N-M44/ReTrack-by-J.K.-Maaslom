# Ticket issuance

## Workflow and wireframe

Draft → intake checks → confirmation → issued ticket receipt.

The draft editor links to an issuance page. On desktop, two columns place the saved customer/device summary beside the remaining intake checks. On mobile they stack. Required checks are a physical storage location, accessory checklist, an overall-condition photo, and an image of the customer-signed intake form covering condition and the shop's terms. If the draft has no serial number, record identifying details instead. The final confirmation shows the values and uploaded images before an Issue ticket button appears.

This first version accepts JPEG/PNG uploads of existing signed paperwork. It does not supply legal terms or draw signatures. Administrators must identify the actual terms version on that paperwork. It supports one device per draft and keeps the accountable branch equal to the intake branch.

## Local setup (no schema migration)

Keep your existing database settings. Add these two entries from `config/config.example.php` to your ignored `config/config.php`:

- `intake_storage_path`: create an absolute private folder such as `C:/repairtrack-private/intake`, outside both `htdocs` and the project directory. PHP needs read/write access. Uploads never go in the public assets folder.
- `intake_hmac_key`: generate 32 random bytes once using `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, then paste the 64 hexadecimal characters into this setting. Keep it private and stable; changing it changes duplicate fingerprints.

Enable PHP `fileinfo` alongside the existing extensions. Set `upload_max_filesize = 5M` and `post_max_size = 12M` or greater in the PHP configuration used by Apache, then restart Apache. Both JPEG/PNG images must be at most 5 MB, 20 megapixels and 6000 pixels on either side. File bytes and image metadata are checked; SVG, PDF and executable uploads are rejected. The system records an administrator's attestation that the actual signed form is legible and covers condition and terms. It does not recognize or legally verify a signature in an image.

Choose an active BRANCH_BIN or WORKBENCH location in the intake branch. The seed database provides example locations; no location-management UI is added here. Existing drafts with prior photos, accessories or acknowledgments are blocked for manual review rather than overwriting previous evidence.

## What changes when issued

A transaction locks the administrator, draft/customer and storage location, verifies the reviewed version, and then:

1. Assigns a random ticket code and device item code; sets ISSUED / OPEN and the Manila intake date.
2. Sets the accountable branch to the intake branch, device status to RECEIVED and custody to AT_LOCATION.
3. Records received/not-received accessories, overall-condition image metadata, signed condition acknowledgment and terms version.
4. Writes the nine intake gate results, the initial status event and an audit record.

Any failed database write rolls back all those records. Uploaded images are held privately during review; handled validation/write failures remove them. Only successful issuance keeps the referenced images. One-use, session-bound review keys expire in 30 minutes, and at most five open issuance forms are retained. The final POST uses server-stored reviewed values, so new POST fields cannot change the ticket silently. Draft/customer changes invalidate review; repeat issuance is rejected. Saving a draft separately before opening issuance avoids losing unsaved edits.

The duplicate fingerprint uses the normalized customer name, normalized serial (or device description/identifying marks), Manila intake date and the private HMAC key. A unique database index enforces that identity even if requests race. This is a same-day duplicate guard for records issued through this flow; it does not establish that differently described or older records refer to different physical devices. The administrator also checks existing workbench records.

The receipt is an authenticated, printable intake reference, not a bill. Public tracking is not implemented. A random tracking-token hash is reserved in the existing column, but no usable customer link is exposed; a later public-tracking feature must generate/deliver a fresh token. No plaintext token or encryption key is committed.

## Evidence access and cleanup

Private previews and saved evidence require an active administrator assigned to the ticket branch. Random stored filenames are never accepted directly from request parameters. Evidence responses are uncached and use fixed image content types, `nosniff`, and a restrictive content-security policy. Normal draft editing cannot modify issued evidence.

Expired reviews are cleaned on the next issuance request in that session. Closing the browser, losing a session, or a process crash can leave unreferenced uploads. Run `php tools/cleanup_intake_uploads.php` to preview images older than 24 hours that have no database references. Add `--apply` only to delete those orphaned images. The command preserves referenced evidence and recent review uploads. Back up the private folder together with the database and the local configuration key.

## File responsibilities

| File | Purpose |
| --- | --- |
| `app/intake/issuance.php` | Issue validation, duplicate fingerprint and database transaction |
| `app/intake/evidence.php` | Private paths, upload validation and temporary file cleanup |
| `admin/intake_issue.php` | Review state, CSRF checks and HTTP flow |
| `views/intake/issue.php` | Entry and confirmation screens, usable without JavaScript |
| `admin/ticket.php` | Read-only printable ticket receipt |
| `admin/intake_evidence.php` | Authorized image delivery |
| `tools/cleanup_intake_uploads.php` | Preview/delete abandoned, unreferenced images |

## Tests

`tests/ticket-issuance.php` runs only from CLI against a disposable `repairtrack_test_` database. It creates fictional drafts and test image evidence. `tests/ticket-issuance-browser.mjs` exercises real upload, review, issue, receipt and access flows with JavaScript disabled. Reuse the isolated setup documented in [admin milestone tests](admin-milestone.md), add the private folder/key settings, and set `REPAIRTRACK_PRIVATE_DIR` for the browser checks. Run the existing admin PHP tests, then the issuance PHP tests, then the browser suites. Test images are deliberately tiny fixtures, not customer signatures.

Remaining scope: multi-device issuance, electronic signature drawing, issue correction tools, public tracking, branch transfers, repair work, quotes, payments and inventory actions.

### Verified result

Passed on PHP 8.3.6, MariaDB 10.11.14 and Chromium using an isolated seeded test database:

- PHP syntax checks and the existing admin backend/browser regression suites.
- Required checks, invalid location, missing evidence, non-admin access and stale draft rejection.
- Full rollback after an injected late audit failure; successful ticket/device issuance and all nine gate records.
- Normalized same-day duplicate detection and repeat-issuance rejection.
- Real HTTP image uploads, rejection of executable text renamed as PNG, and cleanup of a partially uploaded pair.
- Expired-review cleanup, private preview access, denial of receipt/evidence access to an administrator from another branch.
- JavaScript-disabled review and issuance, printable receipt links, desktop/mobile layout and issued-draft edit protection.
- Cleanup dry-run followed by apply: abandoned old images removed; recent and database-referenced images preserved.

Windows/XAMPP and MySQL 8 have not been run here. Sample uploads in tests are fixtures; no real customer evidence or messages were used. Existing seed evidence paths are demonstrations and are not migrated into private storage by this feature.
