# Release webroot guardrail — Apache review candidate

**Branch:** `Release-Webroot-Guardrails`, independently cut from tested `MVP-Stabilization` commit `3e905ff`. **Status:** proposed defense in depth for review, not merged and not applied to a host. The current `MVP-Stabilization` branch is independently changing; resolve any merge conflicts after current work stabilizes.

## What is actually protected

The root `.htaccess` blocks HTTP requests for code-support directories (`app`, `config`, `database`, `docs`, `tests`, `tools`, `views`, `storage`, `.git`, `.github`), dotfiles except `.well-known`, and common source, secret, backup and log extensions. It disables directory listing. Public entry points (`index.php`, `track.php`, `admin/*.php`) and static UI assets are not blocked. PHP `require`/filesystem access is unaffected. It adds no user-facing features, data modifications, DB changes or secrets.

**Preferred architecture:** deploy a distinct public document root with private code, configuration and customer evidence outside it; use server/vhost-level deny rules. The included `.htaccess` is a compatibility guard for older all-in-one Apache layouts, not a substitute.

## Local verification

Run `bash tests/webroot-guardrail.sh` on an isolated **Linux machine with Apache 2.4, mod_rewrite, curl, and permission to start a temporary localhost listener**. The script creates an ephemeral web root containing only synthetic marker files, starts Apache on localhost, asserts allowed and denied status codes and stops it. It does not contact the pilot, require MySQL, read actual configuration or modify production content. A corresponding branch-scoped GitHub Actions job runs the same test on Ubuntu.

The test does **not** execute PHP or demonstrate correct PHP handler configuration, TLS, cookies, session protection, filesystem permissions outside the public root, authentication, rate limiting or production server rules. It tests Apache `AllowOverride All`; shared hosts may disable or override relevant directives. No successful result proves production security.

## Mandatory authorized host review

1. Obtain deployment owner authorization and redacted host control panel settings: deployed revision, document root, Apache compatibility, rewrite module availability, `AllowOverride` support and whether per-directory `Options` directives are allowed.
2. Back up the existing redacted server configuration before attempting any change; check for existing host-managed `.htaccess` entries. **Do not overwrite an operational host file or activate the candidate blindly.**
3. Use a separate authorized **staging copy with fictional accounts only**. Confirm actual PHP executes rather than being served as source, normal public/admin resources load, error pages work, and synthetic forbidden marker files cannot be downloaded. Never use real customer evidence for a negative test.
4. Confirm actual private intake/signature/stock files and secrets are physically outside the served root; this rule does not move or back them up.
5. Have the host owner approve or reject integration, record a reversible deployment and rollback, then repeat validation against the exact deployed revision. A denial from a different environment is not a production verification.

## Failure and rollback criteria

If the rule produces a 500 error on the host, disables critical login, receipt, tracking, ACME verification or necessary public assets, revert the staging change immediately and fix the server compatibility issue offline. If actual code, credentials or private files are discoverable by HTTP, treat as a security incident under the owner's policy; preserve redacted evidence and do not disclose the files in chat or issues.

### Release boundary

No merge to `main` or `MVP-Stabilization`, no webserver changes and no production deployment are authorized by this documentation. Human UAT, Windows/XAMPP restore, and real host security evaluation remain separate release gates; see `docs/mvp-windows-acceptance-handoff.md` and `docs/mvp-deployment-security-register.md`.
