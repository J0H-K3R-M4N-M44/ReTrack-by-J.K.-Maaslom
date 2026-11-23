# RepairTrack EDP — Cross-role acceptance checkpoint

**Target:** isolated `EDP-Course-MVP` academic program (`repairtrack_edp`), not the legacy RepairTrack installation.

## What this gate actually proves

The automatic test `tests/full-journey-http.py` talks to the real localhost PHP endpoints, in five **separate cookie jars**, through the following sequence:

1. A fictional **intake officer** signs in, saves a two-device draft, retries the same request key, signs the classroom intake attestation, and issues the ticket using a randomly generated test-only phone. No repair ticket is preloaded.
2. A **manager** at the responsible branch views and assigns each device. A manager from a separate branch cannot read those devices.
3. The assigned **technician** begins diagnosis, saves a private technical diagnosis and a draft quotation for each device. Repair cannot begin at this point.
4. The manager **sends** the technician's real draft quotations. The **customer** signs in using the receipt portal code and four-digit phone suffix, sees two pending quotes, and cannot see private diagnosis. A manager records two **staff-attested offline** decisions.
5. The **technician** moves each device through `IN_PROGRESS → TESTING → READY_FOR_PICKUP`. The database's first-start DateTime is checked.
6. The **cashier** cannot issue a bill before the devices are ready. Once they are, the cashier issues the two-item bill, records two counter-cash payments, verifies retry idempotency, and cannot release a partially paid ticket. A recorded recipient phone/attestation is required for final pickup.
7. The **customer** sees both completed handovers. A **read-only** CLI inspector cross-checks the actual SQL ticket, seven status events per device, custody, first-start times, approved quotations, itemized bill, and payments.

All mutations to repair records in this acceptance test flow through **actual HTTP form submissions**. The supporting `full-journey-fixture.php create` command creates **fictional staff accounts and an unrelated second branch only**; it does not manufacture repair status transitions, quotations, payments, or tickets. The fixture's `inspect` command uses read-only SQL after verifying the ticket is a synthetic journey ticket.

## How to run

This is a **disposable database test only**. Requires MySQL 8.0.16+ and PHP `pdo_mysql`, with the academic schema/seed already installed, and the PHP built-in web server bound to `127.0.0.1:8743` with `-t course-edp/public`.

```sh
# CI handles the database and server startup; this illustrates the isolated setup.
export REPAIRTRACK_EDP_DB=repairtrack_edp
export REPAIRTRACK_EDP_TEST_DB=repairtrack_edp
export REPAIRTRACK_EDP_HOST=127.0.0.1
export REPAIRTRACK_EDP_USER=your_disposable_test_account
export REPAIRTRACK_EDP_PASSWORD=your_disposable_test_password
export REPAIRTRACK_EDP_PRIVATE_ROOT=/absolute/path/outside/webroot
export EDP_TEST_URL=http://127.0.0.1:8743

php -d upload_max_filesize=6M -d post_max_size=7M -S 127.0.0.1:8743 -t course-edp/public
# In a second shell with the same environment:
python3 course-edp/tests/full-journey-http.py
```

The GitHub Actions academic workflow runs this journey **and** the existing database, concurrency, and role-specific HTTP suites on a disposable runner. A passing run is evidence for that exact commit only; it is not a production deployment approval.

## Explicitly outside the gate

- Customer approval is **staff-attested offline**; this does not independently establish customer identity or consent.
- The backend is tested with GitHub-hosted MySQL and the localhost PHP server. Chrome/Firefox/mobile rendering, accessibility, keyboard controls, and actual touch interactions still require human or real-browser checks.
- Public HTTPS hosting, brute-force resistance in a distributed deployment, least-privilege database accounts, backup/restore, media download authorization, malicious traffic, and broad concurrency remain unverified.
- COD, courier delivery, refunds/corrections, SMS/email, chat/requests, manager analytics, inventory and external REST/Notepad EDP requirements are not implemented by this test.
- The **legacy website** still uses its original database contract. **Never switch its connection** to `repairtrack_edp` as a result of these tests.

## Gate conditions

**Accept for continued isolated academic development** only if the commit's CI passes both the full journey and the existing role-specific tests. **HOLD deployment/main merge** until browser acceptance, security, migration compatibility, and customer-approval evidence are addressed.

## Phase 4 extension

The independent HCI/EDP gate is documented in `EDP-LAB-ACCEPTANCE.md`. The original cross-role test described above is unchanged, and the new browser/EDP exercises are **separate** from repair transaction acceptance. Windows Forms `FileSystemWatcher` execution and live external API availability remain manual validation tasks.
