# Hosted pilot verification — read-only evidence and handoff

**Check date:** 22 November 2026. **Target:** `https://repairtrackpilot.infinityfree.me/admin/login.php?i=1`. **Scope:** nonintrusive public-page retrieval only. **Authorization boundary:** no credentials, login attempts, URL guessing, direct private-file requests, destructive requests, scans, production changes or exposure tests.

## What was actually observed

- A request to the public login page through the available web reader returned an inaccessible-resource error, with no usable HTTP status, headers or HTML for analysis.
- A separate public HTTPS request from the working container failed at DNS name resolution for `repairtrackpilot.infinityfree.me`. This may reflect tool networking restrictions and **does not establish that the real website is offline**.
- No host control panel, deployment manifest, server configuration, DNS panel, redacted access logs, production Git SHA or credentials were supplied to this session.
- The GitHub draft PR #56, `MVP-Stabilization`, is source under development; neither its latest tests nor the repository source can establish what the pilot actually serves.
- Root `.htaccess` was not tracked in the inspected source tree. This is a hosting configuration **risk hypothesis**, not evidence that support files are exposed.

**Conclusion:** SEC-01/02/03/04/05/06 remain **blocked or unverified**. Do not label deployment security as passed, infer exploitability or publish a production vulnerability claim. Do not merge/deploy based on this note.

## Host-owner evidence packet (all outputs redacted)

| Step | Authorized operator action on hosting dashboard / nonpublic admin channel | Evidence to record | Gate |
| --- | --- | --- | --- |
| HOST-01 | Identify the active document root, hosting account, deploy method, uploaded release and revision/SHA | Deployment source and exact version; no secret values | Required |
| HOST-02 | Inspect routing and directory listing protection from host configuration, not by exploratory web probing | Document root, public entry-point allowlist, deny rules and backups outside public root | Required |
| HOST-03 | Confirm `config/config.php`, any archives/SQL dumps, test scripts, private intake and stock evidence are not in publicly served storage | Redacted path mapping and filesystem access permissions, evidence folder location only | Required |
| HOST-04 | Review canonical HTTPS, HTTPS forwarding, session cookie flags, relevant headers and whether site uses a reverse proxy | Safe redacted headers, server/proxy flags, PHP version | Required |
| HOST-05 | Review whether rate-limit files are isolated, writable and effective on the actual host topology; review access logging, revocation and lockout recovery | Single vs multi-worker/multi-node topology, synthetic successful/denied test results without code/PIN | Required |
| HOST-06 | Verify MySQL server/version, configured least-privileged database account, server-side backups and separate private-file backup/restore readiness | Version and privilege summary only; no DSN/password | Required |
| HOST-07 | Confirm password recovery mail path, staff activation base URL and customer notification delivery policy on a fictional account only | Result timestamp, mail relay/provider, anonymized outcome | Pre-release |
| HOST-08 | Arrange isolated Windows/XAMPP acceptance and private-file SQL restoration with synthetic data | 20 UAT statuses, restore checksums, operator and sign-off | Pre-release |

### Evidence handling

Do not paste passwords, customer PINs, activation links, cookies, private filenames, full stack traces or production SQL dumps into chat, issues, documents or screenshots. Store any sensitive evidence in the owner's protected incident/operations records. Use only a sanitized summary to update `docs/mvp-deployment-security-register.md`.

The next live step requires actual control-panel or deployment-owner input. A web query alone cannot verify vhost/document root, HMAC key custody, filesystem exposure, or database recovery.
