# System overview

**As reviewed:** 22 November 2026. Scope: PHP/MySQL RepairTrack, `main` at `f817154f` and unmerged `Partial-Ticket-Ownership` draft PR #55. This document describes the software in the repository, **not** a deployed shop service.

## Purpose

RepairTrack tracks customer-owned devices through intake, repair, confirmed physical custody, quotes, parts use, settlement and return across assigned repair-shop branches. Its principal design rule is to show **what has actually been recorded** rather than invent live locations, approvals or payments.

## Actors

| Actor | Primary activities | Limits |
| --- | --- | --- |
| Customer/public portal | Code + PIN (or existing legacy secret token) to view safe device status, last confirmed custody, published updates | Not a staff session; no internal notes, private evidence or arbitrary ticket lookup |
| ADMIN | Staff accounts and branch-scoped operations, intake, assignments, reviews, selected stock/finance actions | Must satisfy active branch assignments and independent-review rules; not a blanket bypass |
| MANAGER | Assigned-branch intake, repair management, final bills, and permitted stock decisions | Cannot automatically override independent ADMIN reviews |
| INTAKE_AGENT | Customer matching, draft intake, evidence-backed issuance, allowed recorded quote decisions | Cannot act as cashier or assigned technician |
| TECHNICIAN | Assigned device stages, work notes and permitted quote preparation | No cash permissions; assignment and branch checks |
| CASHIER | Ticket/bill search, counter-cash collection, authorized return/handover | Cannot take repair authority from custody or edit technician work |
| COURIER | Assigned-branch physical transfer dispatch/receipt where permitted; transfer visibility | A receipt does not grant repair ownership or financial rights |
| VIEWER | Restricted repair status/queue visibility | No private customer evidence, contacts, notes or writes |

Actual permissions come from `app/auth.php`, `app/console/repository.php`, `app/staff/repository.php` and each transactional module, **not** this table alone.

## Recorded journey

1. **Intake:** branch-assigned staff find/create the customer, collect device and condition details, and save a draft (up to five devices per ticket in the reviewed UI).
2. **Issue:** staff complete accessories, condition photos, signed intake acknowledgment, physical storage and duplication checks. Issuance creates ticket/item identifiers and customer tracking access.
3. **Diagnose and quote:** authorized staff record diagnosis and an itemized estimate. Staff can record a customer decision; repair-start is blocked without an approved current quote.
4. **Repair and stock:** assigned technicians advance permitted stages; catalog parts are reserved, installed or returned using branch stock checks and audit trails.
5. **Custody:** physical dispatch, receiving, exceptions and branch handovers produce manifest/custody records. These are separate from repair authorization.
6. **Billing/return:** an authorized manager/admin issues the ticket-level bill only after required readiness and physical-return checks. Cashier records counter cash and completes permitted pickup after balance checks.
7. **Closure and reporting:** the ticket/device state closes with custody and audit history. Staff reports summarize repair events, cash and handovers with 7/30/90-day periods.

These are **distinct state machines**. A repair can be in progress while physically in transit, but the current repair-stage update gate refuses progress changes when custody is not confirmed at a location. “Ready” is not “paid”; “received” is not “repair ownership accepted.”

## Functional status at this checkpoint

| Area | Current status | Primary sources |
| --- | --- | --- |
| Login, password reset, active branch roles | Implemented in `main` | `app/auth.php`, `app/password_reset.php`, `app/staff/repository.php` |
| Customer intake, drafts, evidence-backed issuance | Implemented in `main` | `app/intake/`, `admin/intake_issue.php` |
| Staff work queues, assigned repair stages, internal notes, reports | Implemented in `main` | `app/console/`, `admin/staff_home.php`, `admin/reports.php` |
| Private public status/tracking | Implemented in `main` | `app/public/tracking.php`, `track.php` |
| Quote preparation and staff-recorded approval | Implemented in `main` | `app/quotes/repository.php` |
| Counter cash, final ticket bill, cancellation checkout, paid-bill corrections/refunds, pickup | Implemented as bounded PHP workflows in `main` | `app/payments/repository.php` |
| Whole-ticket physical device transfers and explicit destination repair acceptance | Implemented with restrictions in `main` | `app/console/transfers.php` |
| Catalog stock, reservations, returns, quarantine, counts, physical parts transfer and incident investigations | Implemented as bounded PHP pilots in `main` | `app/inventory/` |
| Independently reviewed supplier costs, loss exposure valuations, physical stocktaking sign-off | Bounded PHP pilots in `main`; not GL posting | `app/inventory/acquisition_costs.php`, `app/inventory/valuation.php`, `app/inventory/stocktaking.php` |
| Device-level branch field, safe whole-ticket alignment, read-only branch labels, scope prototypes | Feature branch only; additive schema/CI verified | `database/device_repair_branches.sql`, `app/console/ownership.php`, `app/console/scopes.php` |
| **Partial-ticket repair ownership transfer** | **Not enabled** | [Design and release gates](partial-ticket-ownership.md) |
| Customer self-approval, two-way chat/request system, production SMS/email repair alerts, COD fulfillment, GPS/live maps | **Unfinished or demo-only** | [Remaining gaps](system-gaps.md) |
| Deployment hardening, disaster recovery, external accounting | **Not verified as production services** | [Security](security-and-privacy.md), [Release](release-status.md) |

## Scope and non-goals

This project deliberately uses readable PHP, PDO and MySQL rather than a large service framework. Local/staged integration testing **does not** establish production security, correctness under arbitrary load, financial-regulatory compliance, or reliable real-world supplier documentation. Mock notifications and maps in prototypes are not live providers.

## Navigation

[Architecture](architecture.md) · [Database](database-reference.md) · [Security](security-and-privacy.md) · [Operations](operations-runbook.md) · [Release gates](release-status.md).
