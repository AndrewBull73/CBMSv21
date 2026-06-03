# CBMSv21 Database Naming Standard

Date: 2026-05-23

## Purpose

Define a practical database naming standard for CBMSv21 that improves consistency without forcing a high-risk pre-UAT mass rename.

This standard covers:

- table naming
- column naming
- key naming
- constraint naming
- index naming
- future migration policy

## Current Accepted Naming Families

The current database already contains several valid naming families:

- legacy core tables such as `tblUsers`, `tblVersions`, `tblTransactionInput`
- strategic budgeting tables such as `tblSbProgram`, `tblSbResourceEnvelope`
- budget execution tables such as `tblBeWarrant`, `tblBeReservation`
- workflow tables such as `tblWorkflowDefinition`, `tblWorkflowInstance`
- scenario modelling tables such as `tblCalcModel`, `tblCalcScenario`, `tblScenarioNodeValue`

These families are accepted as the current physical baseline.

## Immediate Policy

Before UAT:

- do **not** mass-rename existing tables or columns for cosmetic consistency alone
- do **not** break working code, SQL, reports, or reset scripts to chase naming purity
- do define and enforce the naming standard for all new database objects

After UAT:

- any major schema rename should be handled through a planned migration program
- compatibility views or staged refactoring may be used where necessary

## Table Naming Standard

### General Rule

User tables should use:

- `dbo.tbl<Domain><Entity>`

Examples:

- `dbo.tblSbProject`
- `dbo.tblBeCommitment`
- `dbo.tblWorkflowInstance`
- `dbo.tblCalcModel`

### Domain Prefix Rule

Use the established family for the subsystem you are extending:

- `tblSb...` for Strategic Budgeting
- `tblBe...` for Budget Execution
- `tblWorkflow...` for the shared Workflow Engine
- `tblCalc...` and `tblScenario...` for Scenario Modelling
- existing legacy core `tbl...` family only when extending the same legacy core area

Do not introduce a new naming family when a valid family already exists for that subsystem.

### Table Name Shape

Table names should be:

- singular
- business-meaningful
- stable

Good examples:

- `tblSbGoal`
- `tblSbIndicatorTarget`
- `tblBeSupplementaryBudget`
- `tblWorkflowDefinitionAction`

Avoid:

- vague abbreviations without an established family
- mixed plural/singular naming inside the same subsystem
- names that expose implementation accidents rather than business meaning

## Column Naming Standard

### Primary Key Columns

Primary key columns should use:

- `<Entity>ID`

Examples:

- `ProjectID`
- `WorkflowInstanceID`
- `CalcModelID`

### Foreign Key Columns

Foreign key columns should use the referenced entity name plus `ID`.

Examples:

- `ProjectID`
- `GoalID`
- `WorkflowDefinitionID`
- `ScenarioID`

### Code Columns

Code-based columns should use:

- `<Entity>Code`

Examples:

- `ProgramCode`
- `WorkflowAreaCode`
- `StatusCode`

### Name Columns

Name-style descriptive columns should use:

- `<Entity>Name`

Examples:

- `ProgramName`
- `ScenarioName`
- `WorkflowStageName`

### Boolean Columns

Boolean columns should use an explicit suffix such as:

- `ActiveFlag`
- `LockedFlag`
- `ApprovedFlag`
- `CapitalFlag`

Avoid inconsistent boolean naming such as mixing `IsActive`, `Enabled`, `FlagActive`, and `ActiveFlag` in the same subsystem.

### Date And Time Columns

Use semantic event names plus `Date`.

Examples:

- `CreatedDate`
- `SubmittedDate`
- `ApprovedDate`
- `PublishedDate`

If both date and actor are tracked, pair them consistently:

- `ApprovedBy`
- `ApprovedDate`

### User Reference Columns

Actor columns should use:

- `CreatedBy`
- `UpdatedBy`
- `AssignedBy`
- `ApprovedBy`

Avoid mixing:

- `CreatedUserID`
- `UserIdCreated`
- `InsertedByUser`

unless already locked into legacy structures that cannot be changed safely.

## Constraint Naming Standard

### Default Constraints

Use:

- `DF_<TableName>_<ColumnName>`

### Check Constraints

Use:

- `CK_<TableName>_<RuleName>`

### Foreign Keys

Use:

- `FK_<ChildTable>_<ParentTable>`

### Unique Constraints Or Unique Indexes

Use:

- `UX_<TableName>_<BusinessKeyName>`

## Index Naming Standard

Use:

- `IX_<TableName>_<PurposeOrKey>`

Examples:

- `IX_tblWorkflowInstance_CurrentStage`
- `IX_tblCalcRun_ModelScenario`

## Junction Table Standard

Many-to-many tables should still remain business-readable.

Examples:

- `tblSbProjectProgramLink`
- `tblSbProjectObjectiveLink`
- `tblSbObjectiveIndicator`

For junction tables:

- use both parent keys explicitly
- prefer a meaningful business name over an over-compressed abbreviation

## Audit And History Table Standard

History tables should use a clear suffix:

- `History`

Examples:

- `tblWorkflowInstanceHistory`
- `tblSbFundingSubmissionHistory`

If a table is an event log rather than row history, prefer a term such as:

- `Event`
- `Run`
- `PublishEvent`

## Temporary And Staging Objects

Temporary or staging tables should not become permanent business objects without renaming.

Examples:

- temporary SQL tables may use `#TempName`
- staged cleanup tables should stay explicitly temporary or be renamed before becoming platform objects

## Naming Rules For New Development

For all new schema work:

1. extend the established subsystem family
2. keep business names readable
3. keep key columns predictable
4. keep actor/date pairs consistent
5. keep constraints and indexes explicitly named

## What Not To Do Before UAT

Do not:

- rename `tblSb...` to another family just for aesthetics
- rename `tblBe...` tables during active testing
- rename widely-used legacy tables like `tblUsers` or `tblTransactionInput`
- change column names purely to satisfy a style preference

Those changes should happen only through a controlled migration plan.

## Post-UAT Refactoring Policy

If the team later decides to standardize physical names more aggressively:

- define the target schema first
- create a migration plan
- map every affected code path
- preserve compatibility during transition
- retest module by module

## Summary

CBMSv21 already has workable naming families. The immediate goal is not to erase that history.

The immediate goal is to:

- stop naming drift
- make new objects predictable
- keep future migrations manageable
- preserve stability for UAT
