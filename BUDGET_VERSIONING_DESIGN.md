# CBMSv21 Budget Versioning Design

## Current Findings

The live `dbo.tblVersions` table already contains the main fields needed for the budget version model:

- `VersionID`
- `FiscalYearID`
- `VersionLabel`
- `VersionTypeID`
- `VersionStatus`
- `BaseFiscalYearID`
- `BaseVersionID`
- `ActualsPeriodID`
- `BaseCurrency`
- `CeilingsOn`
- `IsActive`
- `IsDefault`

Current gaps observed:

- `VersionTypeID` exists but is not populated.
- `dbo.tblVersionTypes` does not exist yet.
- the application currently lists versions by fiscal year only
- the current unique default index allows only one default version per fiscal year, regardless of type
- `BaseFiscalYearID` and `BaseVersionID` are present but not yet formalized for lineage

## Recommended Design

CBMSv21 should keep a single `dbo.tblVersions` master and distinguish version purpose through `VersionTypeID`.

### Version Types

- `SUBMISSION`
  Used for budget preparation, review, approval, and publication.

- `EXECUTION`
  Used for the live execution context for the fiscal year.

## Functional Rules

### Submission Versions

- many submission versions may exist for one fiscal year
- they support draft and iterative client work during the budget process
- reports such as draft, approved, and published budget reports should read these versions

### Execution Versions

- execution versions also live in `dbo.tblVersions`
- clients typically keep one active execution version for a fiscal year
- execution transactions should point to an execution version, not a submission version

### Version Lineage

Execution versions should point back to the approved submission version they were opened from using:

- `BaseFiscalYearID`
- `BaseVersionID`

For execution rows, that pair means:

- this execution version was initialized from submission version `BaseFiscalYearID / BaseVersionID`

This avoids introducing a new table or a new source-version column.

## Defaults

Defaults should be per fiscal year and per version type, not one generic default across all version rows.

That means CBMS should be able to have:

- one default `SUBMISSION` version for FY 2026
- one default `EXECUTION` version for FY 2026

without conflict.

## Status Usage

`VersionStatus` should remain on `dbo.tblVersions`, but its meaning should be interpreted by type.

Suggested `SUBMISSION` statuses:

- `DRAFT`
- `IN_REVIEW`
- `APPROVED`
- `PUBLISHED`
- `CLOSED`

Suggested `EXECUTION` statuses:

- `OPEN`
- `SUSPENDED`
- `CLOSED`

These are recommendations only. They should not be hard-constrained until client rules are confirmed.

## UI / Context Implications

The current shared header uses one generic version selector and currently lists all active versions in the selected fiscal year.

That is acceptable for the legacy state, but once execution versions are populated the preferred rule should be:

- Budget Submission screens show only `SUBMISSION` versions
- Budget Execution screens show only `EXECUTION` versions

The first implementation step is to make the version list API type-aware. The shared global selector can remain generic until the execution module screens are ready.

## First-Phase DB Changes

The first-phase migration should:

- create `dbo.tblVersionTypes`
- seed `SUBMISSION` and `EXECUTION`
- backfill existing `tblVersions` rows to `SUBMISSION`
- formalize the self-reference from `BaseFiscalYearID / BaseVersionID`
- change the default uniqueness rule to `FiscalYearID + VersionTypeID`
- add supporting indexes for type-aware version lookups

## Why This Model Fits CBMSv21

This design fits the client behavior described for CBMSv21:

- multiple submission versions are preserved
- one execution version can operate independently
- execution does not overwrite the approved submission version
- reporting can clearly separate submission views from execution views
