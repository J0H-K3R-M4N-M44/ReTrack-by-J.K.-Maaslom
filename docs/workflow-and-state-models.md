# Workflow diagrams and independent state tracks

**As reviewed:** 22 November 2026. These conceptual diagrams simplify the active PHP state constraints for teaching and review. They do **not** replace the actual enums/guards in `database/schema.sql` or server transactions. See [requirements](requirements-and-traceability.md) and [architecture](architecture.md).

## Ticket lifecycle: from intake to return

~~~mermaid
flowchart TD
  A[Staff logs in within assigned branch] --> B[Search or create customer]
  B --> C[Save draft with device details]
  C --> D{Issue evidence complete?}
  D -- No --> C
  D -- Yes --> E[Issue ticket and device item codes]
  E --> F[Technician diagnoses assigned device]
  F --> G[Prepare / share estimate]
  G --> H{Staff records customer approval?}
  H -- No / pending --> G
  H -- Approved --> I[Work and stock actions under locks]
  I --> J[Testing and QA]
  J -- Rework --> I
  J -- Passed --> K[Ready for pickup]
  K --> L{All devices and custody eligible?}
  L -- No --> K
  L -- Yes --> M[Manager issues ticket final bill]
  M --> N[Cashier records authorized counter cash]
  N --> O{Balance/refund gates clear?}
  O -- No --> N
  O -- Yes --> P[Confirm recipient, device check and handover]
  P --> Q[Close ticket and preserve events]
~~~

Cancellation is a separately guarded checkout; “declined quote” does not automatically mean “cancelled” and “in transit” does not mean “received.”

## Repair-stage transitions (technician-safe subset)

~~~mermaid
stateDiagram-v2
  [*] --> RECEIVED
  RECEIVED --> DIAGNOSING
  DIAGNOSING --> AWAITING_CUSTOMER_APPROVAL
  DIAGNOSING --> AWAITING_PARTS
  DIAGNOSING --> IN_PROGRESS: quote approved
  AWAITING_CUSTOMER_APPROVAL --> IN_PROGRESS: quote approved
  AWAITING_PARTS --> IN_PROGRESS: quote approved
  IN_PROGRESS --> AWAITING_PARTS
  IN_PROGRESS --> TESTING
  TESTING --> IN_PROGRESS: rework
  TESTING --> READY_FOR_PICKUP: test passed
  READY_FOR_PICKUP --> COMPLETED: cashier-controlled handover
~~~

Only relevant `consoleNextStatuses()` transitions are shown. The application's progress action has additional checks for custody, stock installation, roles, case state and stale versions. `CANCELLED` is reached by a guarded cancellation workflow, not a free technician stage option.

## Physical custody is not repair authority

~~~mermaid
flowchart LR
  A[AT_LOCATION at origin] -->|Authorized dispatch| B[IN_TRANSIT]
  B -->|Confirmed receipt| C[AT_LOCATION at destination]
  B -->|Missing / unresolved| D[EXCEPTION or UNKNOWN]
  D -->|Resolved manifest receipt| C
  C --> E{Whole-ticket repair acceptance?}
  E -- No --> F[Only custody location changed]
  E -- Complete manifest, early stage, manager acceptance --> G[Ticket accountable branch changes]
~~~

Whole-ticket acceptance reassigns the ticket contract/repair responsibility and clears prior technician assignments, subject to inventory and billing guards. On the feature branch it also aligns every device's `repair_branch_id`. Partial-ticket ownership is **not implemented**; a courier receipt cannot grant it.

## Ticket finance remains separate

~~~text
repair_tickets.accountable_branch_id
    -> final bill permission
    -> cash collection / bill correction or refund
    -> all-device paid and at-accountable-branch handover
ticket_devices.repair_branch_id [draft PR field]
    -> proposed future authorization for one device's repair
    X no automatic cash, customer-contract or sibling access
ticket_devices.physical_location_id + custody_state
    -> last confirmed custody, not permission
~~~

## Status vocabulary for reviewers

| Track | Example code states or rows | Important distinction |
| --- | --- | --- |
| Intake | `record_stage = DRAFT / ISSUED / ABANDONED` | Draft is not an issued public repair |
| Repair | `repair_status` values including diagnosing, awaiting parts, testing and ready | Stage is not physical location or payment |
| Custody | `AT_LOCATION / IN_TRANSIT / UNKNOWN / WITH_CUSTOMER` plus custody events | Last confirmed location, not GPS |
| Estimate | `DRAFT / AWAITING_CUSTOMER / APPROVED / DECLINED / EXPIRED / SUPERSEDED` | Staff-recorded decision is not customer self-signature |
| Bill | `DRAFT / ISSUED / VOIDED` with payment/correction rows | Issued is not fully paid |
| Physical stocktake | `OPEN / PENDING / SIGNED_OFF / CANCELLED` | Sign-off does not change shelf stock or post GL journal |

[Functional requirements](requirements-and-traceability.md) · [Source mapping](architecture.md) · [Security audit](authorization-cutover-audit.md).
