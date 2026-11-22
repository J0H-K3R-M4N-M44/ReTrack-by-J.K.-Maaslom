# RepairTrack — P0 deployment and security issue register

**As of:** 22 November 2026. This is a **source-level triage register**, not an intrusion finding, penetration test, or statement that the hosted pilot exposes data. No live negative testing was performed and no credentials are included. Owner/host administrator must authorize validation.

| ID | Priority | Source observation / risk hypothesis | Severity basis | Evidence needed and safe mitigation | Status |
| --- | --- | --- | --- | --- | --- |
| SEC-01 | P0 | Old hosted pilot revision is unknown and may differ from `main`/`MVP-Stabilization` | Cannot assign mitigations to the actual deployed build | Identify deployed revision from host dashboard, deployment manifest or read-only files; record hash and configuration without secrets. Freeze changes to pilot | Unverified |
| SEC-02 | P0 | Public repository has PHP project support folders (`config/`, `database/`, `tests/`, `docs/`, `tools/`) and no tracked root `.htaccess` access allowlist | Incorrect host routing/handlers could expose backups, SQL, raw support files or executable tools | Confirm web-root boundary with host owner; ideally serve only public entry points using an external web root and deny-by-default server rules; check access logs and permitted unauthenticated paths under authorization | Risk, not confirmed exposure |
| SEC-03 | P0 | Config file is ignored in Git, but actual hosted credential hygiene/secret history is unknown | Real secrets in served locations, copies, logs or stale previews could compromise data | Audit authorized hosting file manager, VCS history, old exports and incident logs offline; rotate implicated credentials only through an approved outage/recovery plan; use least-privileged DB account | Unverified |
| SEC-04 | P0 | Private intake/signature and stock evidence require storage outside the website root | Files include private customer and shop material | Verify actual absolute paths, PHP permissions, direct-URL denial, backup and restore. Never copy genuine files into CI or public docs | Host unverified |
| SEC-05 | P0 | `track.php` uses short 5-hex code plus 4-digit phone suffix; `reserveLoginAttempt` uses file-backed, single-host throttling | Public access depends on rate limiting, private PIN distribution and host session protection; risk escalates on a shared/multi-node deployment | Confirm server instance count, locked writable temp path, 429/logging, TLS, access-code revocation and customer-facing recovery policy; prefer stronger private tokens before real use | Known design limit, exploitation unverified |
| SEC-06 | P0 | Session cookie Secure depends on PHP HTTPS signal, and hosted proxy headers/configuration are unverified | Incorrect TLS termination can cause insecure session cookies | Confirm HTTPS/redirects/proxy settings, no-store responses, HSTS policy and session renewal on the target host with authorized review | Unverified |
| SEC-07 | P1 | Password recovery relies on PHP `mail()` and configured `app_url` | Delivery can fail or send wrong-site links; resetting account may be unreliable | Controlled fictional account delivery test; confirm HTTPS origin, token expiry and transport configuration; avoid exposing raw reset URL in logs | Partial/unverified |
| SEC-08 | P1 | MySQL recovery succeeded in isolated CI but not on Windows/XAMPP | Schema, charset, large-file and path/permission differences can break real restore | Run `docs/mvp-windows-acceptance-handoff.md` on fictional data, include private evidence hash comparison and restore stop conditions | Not executed |
| SEC-09 | P1 | Manual cross-role, accessibility and customer privacy UAT incomplete | Automated tests cannot establish practical usability or host operational safety | Execute UAT-01 to UAT-20 and sign-off real observations; prioritize CSRF, direct-link denials and money/stock concurrency | Not executed |

## Hard stop conditions

Do **not** deploy or merge a release candidate if an authorized audit identifies publicly served credential/config/backup/private files, cross-customer quote leakage, bypassable role gates, invalid cash/stock writes, or an unrepeatable backup/restore. Disable public access under the host owner's incident-response process if real exposure is discovered; preserve evidence without copying private data into reports.

## Authorized inspection procedure

1. Ask the host administrator for exact deployment revision, vhost/document root, PHP/MySQL versions, server topology and *redacted* configuration mapping. Do not retrieve passwords into ChatGPT or repository issues.
2. Review server routing and static-source exposure with an approved test plan and **synthetic** markers only; do not fuzz, brute-force or scan the live pilot. Separate “at risk” from actually reproducible exposure.
3. Check ignored `config/config.php`, private storage and old export directories using host-side read-only review; do not commit secrets. Record redacted evidence reference and named owner.
4. Reproduce concerns against a disposable isolated copy; add automated negative tests before changing session, auth, inventory, money or schema logic.
5. Close each register row only after remediation, repeatable verification and owner sign-off. Revalidate on the **deployed commit** rather than inferring safety from a development branch.

**Current boundary:** this register authorizes documentation and safe local testing only. It does not authorize changes to production files, customer data, migrations, account permissions or live traffic.
