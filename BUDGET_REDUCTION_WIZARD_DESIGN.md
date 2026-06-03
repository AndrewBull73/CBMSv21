# CBMSv21 Budget Reduction Wizard Design

## Purpose

This note defines the recommended design for a dedicated `Budget Reduction Wizard` in CBMSv21 Budget Execution.

The intent is to support planned and policy-driven budget cuts across:

- one specific execution budget line
- a filtered range of execution budget lines
- a whole class of budget lines such as travel, grants, or utilities
- combinations of dimensions such as ministry + economic item + program

## Why A Separate Function Is Needed

Negative supplementary lines already support reductions on existing execution lines.

That is sufficient for:

- one-off manual reductions
- small controlled adjustments

It is not sufficient for:

- large-scale budget cuts
- percentage-based cuts across many lines
- rule-driven reductions over filtered groups
- repeatable and auditable reduction exercises

Examples:

- reduce all travel costs by `10%`
- cut all goods and services for one ministry by `5%`
- reduce all projects in one sector by an absolute `1,500,000`
- reduce only selected lines inside a filtered result set

## Recommended Position In The Model

The reduction wizard should be an operational tool.

It should not become a separate final posting family that bypasses Supplementary Budgets.

Recommended rule:

- `Supplementary Budget` remains the legal or approved budget-adjustment instrument
- `Budget Reduction Wizard` becomes the generator that creates negative supplementary lines

This keeps:

- approval workflow consistent
- current authorized budget logic consistent
- audit trail simpler
- reporting aligned with existing supplementary reporting

## Conceptual Flow

1. User opens `Budget Reduction Wizard`
2. User defines the target scope
3. User chooses the reduction method
4. System previews affected execution balance lines
5. System calculates proposed reduction amounts
6. User reviews and confirms
7. System creates a draft `Supplementary Budget` header
8. System creates negative supplementary lines for each selected line
9. Normal supplementary approval workflow applies

## Scope Selection Model

The wizard should support filter criteria across execution balance lines.

Recommended dimensions:

- `DataObjectCode`
- `OrgUnitID`
- `SectorID`
- `ProgramID`
- `SubProgramID`
- `ProjectID`
- `ActivityID`
- `FundingTypeID`
- `FundingSourceID`
- `EconomicItemID`
- optional text search on `BidTitle`

Recommended selection behavior:

- allow one or more filters
- allow combinations of filters
- allow preview before generation
- allow manual deselection of specific returned lines before posting

## Reduction Methods

The wizard should support at least:

### 1. Percentage Reduction

Apply a percentage to every matched line.

Example:

- all travel lines reduced by `10%`

### 2. Absolute Amount Per Matched Line

Apply the same fixed amount to every matched line.

Example:

- reduce each matching line by `50,000`

### 3. Total Distributed Reduction

Apply one total cut across the filtered result set and distribute it across lines.

Possible future distribution rules:

- proportional to current authorized amount
- equal split
- weighted by user-selected basis

This can be a later enhancement if phase 1 should stay smaller.

## Preview Requirements

Before any draft supplementary batch is generated, the wizard should show:

- matched line count
- total current authorized budget in scope
- total planned released amount in scope
- proposed total reduction
- resulting total authorized budget after reduction
- per-line calculated reduction amount
- per-line resulting authorized amount

The preview should also show blocked lines and the reason.

## Control Rules

The wizard must not create reductions that break execution controls.

At minimum:

- do not reduce a line below `0`
- do not reduce a line below already released authority
- do not allow a calculated line reduction that results in an invalid negative authorized amount
- do not silently skip invalid lines without reporting them in preview

Future control options may also include:

- block reductions below reserved amounts
- block reductions below committed amounts
- client-policy settings for whether released-only control is sufficient

## Posting Rule

Confirmed reductions should generate:

- one supplementary header for the reduction run
- many negative supplementary lines

Recommended header metadata:

- reduction run title
- method used
- scope summary
- calculation basis
- user note

Recommended line metadata:

- original execution balance line reference
- original current authorized amount
- generated reduction amount
- resulting amount after reduction

## Audit Requirements

The wizard should store enough detail to reproduce why the batch was generated.

Recommended persisted audit information:

- filter criteria used
- method used
- percentage or amount entered
- preview totals at time of generation
- generated supplementary batch id
- user id
- generation timestamp

This may justify a dedicated wizard run table later, even if posting still goes through supplementary tables.

## Relationship To Future Specific Budget Line Reduction

There should be two related but distinct user flows:

### A. Budget Reduction Wizard

For:

- bulk reductions
- rule-based reductions
- percentage reductions
- ministry/program/economic class reductions

### B. Specific Budget Line Reduction

For:

- one-off manual cuts to selected lines
- direct user targeting without building a full filter rule

Recommended future rule:

- both flows should still create negative supplementary lines
- the difference is only how the lines are selected and generated

## Dependency On Budget Submission Input Sheet

Brand-new line creation from scratch should wait until the Budget Submission Input Sheet and shared line model are finalized.

Reason:

- execution and submission should share one stable understanding of a budget line structure
- mandatory and optional dimensions are not yet final
- building scratch line creation too early risks rework

Current recommendation:

- the reduction wizard phase should work only against existing execution balance lines
- new line creation should be deferred until the line model is finalized

## Recommended Phase Build

### Phase 1

- filter execution balance lines
- support percentage reduction
- support fixed-amount reduction per line
- preview affected lines
- generate draft supplementary batch with negative lines

### Phase 2

- support manual deselection from preview
- support richer filter combinations
- support explicit saved reduction notes and audit run records

### Phase 3

- support specific budget line reduction workflow
- support total distributed reduction logic
- support brand-new budget line creation only after shared line model finalization

## Summary

- bulk budget cuts should be a dedicated function
- the function should generate negative supplementary lines rather than bypass supplementaries
- phase 1 should target existing execution balance lines only
- specific budget line reduction should be added later as a separate targeted mode
