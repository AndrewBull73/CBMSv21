# CBMSv21 Budget Execution Module Design

## Purpose

This document defines the recommended design for the Budget Execution module in CBMSv21.

The design assumes:

- budget submission and budget execution use the same `dbo.tblVersions` master
- `VersionTypeID` distinguishes `SUBMISSION` and `EXECUTION`
- clients may keep multiple submission versions for one fiscal year
- clients usually keep one active execution version for one fiscal year
- an execution version must be linked back to the approved submission version it was opened from

## Design Principles

1. Execution must never overwrite the approved submission baseline directly.
2. Execution works from a controlled rollover of an approved submission version.
3. Budget authority changes and spending-control changes must be stored as execution transactions.
4. Client policy differences must be configurable where possible.
5. Budget movement, authority release, and funds control should be related but distinct.

## Functional Scope

The Budget Execution module should cover:

- version rollover into execution
- warrants
- supplementary budgets
- virements
- reallocations
- reservations
- request to incur expenditure
- commitments
- execution adjustments and reversals
- execution reporting and audit trail

## Core Business Concepts

### 1. Version Rollover

This is the controlled process that initializes an execution version from an approved submission version.

Key rule:

- execution cannot begin until rollover has created the opening execution baseline

### 2. Warrants

Warrants release spending authority into the fiscal year or into a lower-level spending context.

Typical use:

- release by ministry
- release by fund
- release by quarter or period

### 3. Supplementary Budgets

Supplementary budgets adjust the legal or approved budget during the execution cycle.

Typical use:

- add budget after a new appropriation decision
- reduce budget following a cut
- formal mid-year revised authority

Design note:

- single-line increases or reductions may be entered directly as supplementary lines
- broad or rule-based cuts should use a future `Budget Reduction Wizard` that generates negative supplementary lines

### 4. Reallocations

Recommended default rule:

- movement of budget authority within the same ministry

### 5. Virements

Recommended default rule:

- movement of budget authority across ministries

Client note:

- this distinction must remain policy-driven because client definitions vary

### 6. Reservations

Reservations earmark budget before commitment.

Typical use:

- hold part of available funds for an expected transaction
- protect budget from being consumed by another request before the formal commitment stage

### 7. Request To Incur Expenditure

RIE is the pre-spending approval instrument where clients use a formal authorization before obligation or procurement.

### 8. Commitments

Commitments formally consume budget authority and reduce available balance.

## Budget Control Model

The execution engine should distinguish these values:

- `Approved Budget Baseline`
- `Supplementary Adjustments`
- `Transfer Adjustments`
- `Current Authorized Budget`
- `Warrant Released Budget`
- `Reserved Budget`
- `Committed Budget`
- `Actual Expenditure`
- `Available Balance`

Recommended formulas:

`Current Authorized Budget = Approved Budget Baseline + Supplementaries +/- Virements/Reallocations`

`Available Balance = Current Authorized Budget - Active Reservations - Active Commitments - Actuals`

If client rules require release control:

`Released Available Balance = Warrant Released Budget - Active Reservations - Active Commitments - Actuals`

## Versioning Design

Budget Execution should use version rows in `dbo.tblVersions` with:

- `VersionTypeID = EXECUTION`
- `BaseFiscalYearID`
- `BaseVersionID`

Execution lineage rule:

- `BaseFiscalYearID + BaseVersionID` must point to the approved or published submission version used for rollover

Submission rule:

- many submission versions may exist for a fiscal year

Execution rule:

- one active execution version per fiscal year is the default operating assumption unless a client explicitly requires more

## Version Rollover Design

## Purpose

Rollover initializes the execution version before any execution transactions are posted.

## Preconditions

The source version must:

- belong to the same fiscal year unless the client explicitly allows cross-year carry design
- be `VersionTypeID = SUBMISSION`
- be approved or published
- contain the budget data required for execution

The target version must:

- be `VersionTypeID = EXECUTION`
- be active
- either be empty or be explicitly marked as safe to initialize

## Rollover Action

Recommended action name:

- `Initialize Execution Version`

Inputs:

- source submission fiscal year
- source submission version
- target execution fiscal year
- target execution version
- optional scope filters if phased rollout is ever needed

Rollover should:

1. validate that the source version is approved/published
2. validate that the target execution version is valid
3. set `BaseFiscalYearID` and `BaseVersionID` on the execution version
4. copy the approved budget baseline into execution opening balances
5. initialize execution control totals
6. create an audit record of the rollover run

## What Rollover Copies

Rollover should copy:

- approved budget amounts
- relevant control dimensions
- budget structure needed for execution reporting and balance checking
- any needed ceiling or release-control basis if the client uses that model

Rollover should not copy:

- draft submission history
- review comments
- working notes from submission
- rejected or superseded submission artifacts

## Re-Rollover Rule

Once an execution version has live activity such as:

- warrants
- supplementaries
- transfers
- reservations
- commitments
- actual expenditure

then blind re-copy should be blocked.

Later approved changes should enter execution as:

- supplementary budget adjustments
- or controlled delta updates

not as a full overwrite of the execution baseline.

## Recommended Screens

### Execution Version Setup

- execution version list
- execution version form
- rollover / initialize execution version
- rollover history

### Warrants

- warrant list
- warrant form
- warrant approval / review
- warrant lines

### Supplementary Budgets

- supplementary budget list
- supplementary budget form
- supplementary approval / review
- supplementary lines

### Budget Reduction Wizard

- reduction scope filter
- reduction method selection
- impacted line preview
- generated supplementary reduction batch
- reduction audit / run history

### Reallocations

- reallocation list
- reallocation form
- reallocation approval / review
- reallocation lines

### Virements

- virement list
- virement form
- virement approval / review
- virement lines

### Reservations

- reservation list
- reservation form
- reservation release / cancel

### RIE / Commitments

- RIE list
- RIE form
- commitment registration
- commitment adjustment / reversal

### Controls And Reporting

- execution balance inquiry
- funds availability inquiry
- transfer history
- budget movement summary
- warrant release summary

## Workflow Recommendation

The module should support workflow at transaction level rather than only at version level.

Recommended generic statuses:

- `DRAFT`
- `SUBMITTED`
- `UNDER_REVIEW`
- `APPROVED`
- `REJECTED`
- `POSTED`
- `CANCELLED`
- `REVERSED`

Version-level execution status can remain separate, for example:

- `OPEN`
- `ACTIVE`
- `SUSPENDED`
- `CLOSED`

## Data Design Recommendation

The module should use a shared execution transaction pattern where possible.

At minimum the design should include:

- execution version setup
- opening execution baseline
- transaction headers
- transaction lines
- balance snapshots or balance views
- transaction history / audit

Recommended transaction families:

- `WARRANT`
- `SUPPLEMENTARY`
- `REALLOCATION`
- `VIREMENT`
- `RESERVATION`
- `RIE`
- `COMMITMENT`
- `REVERSAL`

Recommended operational generators:

- `BUDGET_REDUCTION_WIZARD`
  generates negative supplementary lines from filtered execution balance lines

## Reporting Views

The module should support at least:

- approved budget baseline report
- current authorized budget report
- warrant release report
- supplementary budget report
- virement and reallocation report
- reservation register
- commitment register
- available balance inquiry
- execution trail by version

## Testing Implication

Before Budget Execution testing can begin, the environment needs:

1. at least one approved or published submission version
2. at least one execution version
3. a successful rollover from submission to execution
4. opening execution balances created

Without rollover:

- warrants cannot be tested properly
- supplementaries cannot be measured against a real baseline
- reservations and commitments cannot validate funds availability correctly

## Recommended Phase 1 Build Order

1. execution version selector and execution-version-aware context
2. execution version rollover
3. opening execution balance inquiry
4. warrants
5. supplementary budgets
6. virements and reallocations
7. reservations
8. RIE and commitments
9. reporting and audit refinements

## Summary Rule Set

- `Submission versions` prepare and approve the budget
- `Execution versions` operate the live budget
- `Rollover` creates the opening execution baseline
- `Supplementaries` change legal budget during execution
- `Budget Reduction Wizard` should generate negative supplementary lines for bulk or rule-based cuts
- `Virements and reallocations` move budget authority
- `Warrants` release authority for use
- `Reservations and commitments` consume available authority in stages
