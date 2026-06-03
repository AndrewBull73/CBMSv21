# CBMSv21 Project Module Design

## Purpose

This document defines a recommended design for a Project module in CBMSv21.

The design assumes:

- Projects are owned functionally by the Strategy module.
- Projects must be reusable across Strategy setup, planning, funding submissions, fiscal controls, reporting, and later execution workflows.
- CBMSv21 already contains an initial `tblSbProject` table and project overlay screens, so the preferred approach is to evolve that foundation rather than create an entirely separate module.

## Design Principles

1. Projects are a core strategic dimension.
2. A project master record must be stable across fiscal years.
3. Source-segment imports and fiscal-year mappings must be separated from the project master.
4. Projects must support many-to-many relationships with programs, objectives, and org units.
5. Core project attributes should remain small and stable.
6. Client-specific fields should use the existing strategic custom-attribute framework for `PROJECT`.
7. The design should align with existing CBMSv21 patterns for setup screens, overlay imports, active flags, and audit columns.

## Recommended Functional Scope

The Project module should support these use cases:

- Register and maintain a strategic project master list.
- Import or map projects from a configured source segment where a client stores projects in `tblSegmentValues`.
- Link projects to strategic structures such as programs, objectives, and implementing organizations.
- Use projects in funding submissions and downstream strategic reports.
- Optionally associate projects with activities, resource-envelope restrictions, narratives, and risks.
- Extend project fields per client using custom attributes.

## Conceptual Model

The recommended model separates:

- project identity and business meaning
- fiscal-year/source-system mappings
- cross-module relationships
- client-specific extension fields

### Main Entities

- `tblSbProject`
- `tblSbProjectSourceMap`
- `tblSbProjectProgramLink`
- `tblSbProjectObjectiveLink`
- `tblSbProjectOrgUnitLink`
- `tblSbProjectFundingSourceLink` (optional later phase)

## Recommended Database Design

## 1. Core Master Table

### `tblSbProject`

This table should store the stable master record for a project.

Suggested columns:

- `ProjectID INT IDENTITY PRIMARY KEY`
- `ProjectCode NVARCHAR(50) NULL`
- `ProjectName NVARCHAR(200) NOT NULL`
- `ProjectDescription NVARCHAR(MAX) NULL`
- `ExternalReference NVARCHAR(100) NULL`
- `ProjectTypeCode NVARCHAR(30) NOT NULL`
- `ProjectCategoryCode NVARCHAR(30) NULL`
- `LifecycleStatusCode NVARCHAR(30) NOT NULL`
- `PriorityCode NVARCHAR(20) NULL`
- `LeadOrgUnitID INT NULL`
- `SponsorOrgUnitID INT NULL`
- `ProjectManagerName NVARCHAR(150) NULL`
- `CapitalFlag BIT NOT NULL DEFAULT (0)`
- `ProcurementRequiredFlag BIT NOT NULL DEFAULT (0)`
- `StartDate DATE NULL`
- `EndDate DATE NULL`
- `EstimatedTotalCost DECIMAL(19,6) NULL`
- `ApprovedTotalCost DECIMAL(19,6) NULL`
- `FundingGapAmount DECIMAL(19,6) NULL`
- `CurrencyCode NVARCHAR(10) NULL`
- `FundingStatusCode NVARCHAR(30) NULL`
- `RiskRatingCode NVARCHAR(20) NULL`
- `LocationCode NVARCHAR(50) NULL`
- `LocationDescription NVARCHAR(255) NULL`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- `CreatedBy INT NOT NULL DEFAULT (1)`
- `CreatedDate DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME())`
- `UpdatedBy INT NULL`
- `UpdatedDate DATETIME2(0) NULL`

Recommended check domains:

- `ProjectTypeCode`: `CAPITAL`, `REFORM`, `ICT`, `INFRASTRUCTURE`, `SERVICE_DELIVERY`, `DONOR`, `OTHER`
- `LifecycleStatusCode`: `IDEA`, `PIPELINE`, `APPRAISED`, `APPROVED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CANCELLED`
- `PriorityCode`: `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`
- `FundingStatusCode`: `UNFUNDED`, `PART_FUNDED`, `FULLY_FUNDED`, `DONOR_PENDING`, `CLOSED`
- `RiskRatingCode`: `LOW`, `MEDIUM`, `HIGH`, `SEVERE`

Recommended indexes:

- unique index on `ProjectCode` where not null and active
- nonclustered index on `LifecycleStatusCode, ActiveFlag`
- nonclustered index on `LeadOrgUnitID, ActiveFlag`
- nonclustered index on `ProjectName`

Notes:

- This table should no longer carry fiscal-year-specific source mapping fields.
- It represents the reusable enterprise project record.

## 2. Source Mapping Table

### `tblSbProjectSourceMap`

This table stores where a project came from in the source dimension structure for a given fiscal year and data scope.

Suggested columns:

- `ProjectSourceMapID INT IDENTITY PRIMARY KEY`
- `ProjectID INT NOT NULL`
- `FiscalYearID INT NOT NULL`
- `DataObjectCode NVARCHAR(50) NULL`
- `SourceSegmentNo INT NOT NULL`
- `SourceSegmentCode NVARCHAR(50) NOT NULL`
- `SourceSegmentName NVARCHAR(200) NULL`
- `SourceSystemCode NVARCHAR(30) NULL`
- `IsPrimaryFlag BIT NOT NULL DEFAULT (1)`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- `CreatedBy INT NOT NULL DEFAULT (1)`
- `CreatedDate DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME())`
- `UpdatedBy INT NULL`
- `UpdatedDate DATETIME2(0) NULL`

Recommended constraints and indexes:

- foreign key to `tblSbProject(ProjectID)`
- unique active index on `FiscalYearID, DataObjectCode, SourceSegmentNo, SourceSegmentCode`
- index on `ProjectID, FiscalYearID, ActiveFlag`

Notes:

- This replaces the current practice of storing `SourceFiscalYearID`, `SourceDataObjectCode`, `SourceSegmentNo`, and `SourceSegmentCode` directly on the project record.
- It allows the same project to remain stable while source mappings change over time.

## 3. Program Link Table

### `tblSbProjectProgramLink`

Use this when projects may contribute to one or more programs.

Suggested columns:

- `ProjectProgramLinkID INT IDENTITY PRIMARY KEY`
- `ProjectID INT NOT NULL`
- `ProgramID INT NOT NULL`
- `LinkTypeCode NVARCHAR(30) NOT NULL DEFAULT (N'PRIMARY')`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- `CreatedBy INT NOT NULL DEFAULT (1)`
- `CreatedDate DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME())`
- `UpdatedBy INT NULL`
- `UpdatedDate DATETIME2(0) NULL`

Suggested `LinkTypeCode` values:

- `PRIMARY`
- `CONTRIBUTING`
- `IMPLEMENTING`

Recommended constraints:

- foreign key to `tblSbProject(ProjectID)`
- foreign key to `tblSbProgram(ProgramID)`
- unique active index on `ProjectID, ProgramID`

## 4. Objective Link Table

### `tblSbProjectObjectiveLink`

Use this when projects support one or more objectives.

Suggested columns:

- `ProjectObjectiveLinkID INT IDENTITY PRIMARY KEY`
- `ProjectID INT NOT NULL`
- `ObjectiveID INT NOT NULL`
- `ContributionTypeCode NVARCHAR(30) NOT NULL DEFAULT (N'DIRECT')`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- audit fields

Suggested `ContributionTypeCode` values:

- `DIRECT`
- `INDIRECT`
- `ENABLING`

Recommended constraints:

- foreign key to `tblSbProject(ProjectID)`
- foreign key to `tblSbObjective(ObjectiveID)`
- unique active index on `ProjectID, ObjectiveID`

## 5. Org Unit Link Table

### `tblSbProjectOrgUnitLink`

Use this where multiple organizations participate in a project.

Suggested columns:

- `ProjectOrgUnitLinkID INT IDENTITY PRIMARY KEY`
- `ProjectID INT NOT NULL`
- `OrgUnitID INT NOT NULL`
- `RoleCode NVARCHAR(30) NOT NULL DEFAULT (N'IMPLEMENTING')`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- audit fields

Suggested `RoleCode` values:

- `LEAD`
- `SPONSOR`
- `IMPLEMENTING`
- `CONTRIBUTING`
- `REPORTING`

Recommended constraints:

- foreign key to `tblSbProject(ProjectID)`
- foreign key to `tblSbOrgUnit(OrgUnitID)`
- unique active index on `ProjectID, OrgUnitID, RoleCode`

## 6. Optional Funding Source Link Table

### `tblSbProjectFundingSourceLink`

This is optional and can be deferred until project costing and financing become more mature.

Suggested purpose:

- link a project to one or more funding sources
- capture indicative or approved financing shares

Suggested columns:

- `ProjectFundingSourceLinkID INT IDENTITY PRIMARY KEY`
- `ProjectID INT NOT NULL`
- `FundingSourceID INT NOT NULL`
- `FundingTypeID INT NULL`
- `FinancingRoleCode NVARCHAR(30) NOT NULL DEFAULT (N'PRIMARY')`
- `IndicativeAmount DECIMAL(19,6) NULL`
- `ApprovedAmount DECIMAL(19,6) NULL`
- `ActiveFlag BIT NOT NULL DEFAULT (1)`
- audit fields

## Recommended Use Of Custom Attributes

The current framework already supports custom attributes for `PROJECT`.

Use custom attributes for fields that vary by client, such as:

- contractor name
- parcel number
- district
- procurement method
- donor agreement reference
- climate tag
- gender tag
- asset class
- feasibility study status
- environmental clearance status

Do not put these into the core project table unless they become common across most implementations.

## Relationship Model

The recommended project relationships in Strategy are:

- one project to many source mappings
- one project to many programs through link table
- one project to many objectives through link table
- one project to many org units through link table
- one project to many funding submissions through `tblSbFundingSubmissionLine.ProjectID`
- optionally one project to many activities
- optionally one project to many risks or narratives

## Recommended Integration Points In CBMSv21

## Immediate Integrations

### 1. Strategy Setup

Projects should remain under:

- `Strategy > Structure Setup > Projects`

But the page should become a proper project workspace instead of just an overlay screen.

### 2. Funding Submissions

Keep and strengthen the existing `ProjectID` field in `tblSbFundingSubmissionLine`.

Recommended behavior:

- project dropdown filtered by current data scope where relevant
- ability to search by project code and project name
- display linked program and lifecycle status beside the project

### 3. Strategy Reports

Add project filtering to:

- strategic summary
- submission readiness
- MTFF view
- program budget report
- sector budget report

## Near-Term Integrations

### 4. Activities

Add `ProjectID INT NULL` to `tblSbActivity`.

Reason:

- the system already distinguishes project-style activities using `ActivityTypeCode`
- linking activities to a true project master makes delivery tracking much cleaner

Suggested rule:

- if `ActivityTypeCode = 'PROJECT'`, `ProjectID` should normally be expected

### 5. Resource Envelopes

Current design uses `RestrictedProjectReference` as text.

Recommended future improvement:

- add `RestrictedProjectID INT NULL`
- keep `RestrictedProjectReference` temporarily for backwards compatibility
- eventually prefer the foreign key for cleaner reporting and validation

### 6. Governance

Allow optional project reference in:

- program risks
- fiscal risks
- narratives

This should be optional, not mandatory.

## Screen Design

## A. Project List Screen

Route:

- `strategy-setup/projects`

Purpose:

- list active project masters
- show import status for segment-backed clients
- provide quick access to project setup and maintenance

Recommended columns:

- Project Code
- Project Name
- Type
- Lifecycle Status
- Lead Org Unit
- Linked Program Count
- Active Funding Submission Count
- Source Mapping Count
- Status
- Actions

Recommended actions:

- `New Project`
- `Import From Segment`
- `Edit`
- `Links`
- `Archive`
- `View Usage`

Recommended filters:

- search text
- lifecycle status
- project type
- lead org unit
- active/inactive

## B. Project Form Screen

Route:

- `strategy-setup/project-form`

Tabs or sections:

### 1. Project Details

Fields:

- Project Code
- Project Name
- Description
- External Reference
- Project Type
- Category
- Lifecycle Status
- Priority
- Lead Org Unit
- Sponsor Org Unit
- Project Manager

### 2. Schedule And Delivery

Fields:

- Start Date
- End Date
- Capital Flag
- Procurement Required Flag
- Location Code
- Location Description

### 3. Financial Summary

Fields:

- Estimated Total Cost
- Approved Total Cost
- Funding Gap
- Currency
- Funding Status

### 4. Strategic Links

Fields or grids:

- linked programs
- linked objectives
- linked org units

### 5. Source Mappings

Grid:

- Fiscal Year
- Data Object Code
- Segment No
- Source Segment Code
- Source Segment Name
- Primary flag

### 6. Custom Attributes

Render using the existing custom-attribute framework for `PROJECT`.

### 7. Usage

Read-only references:

- funding submissions using this project
- activities using this project
- envelopes or reports referencing this project

## C. Project Import Screen Or Modal

Purpose:

- import candidate projects from the mapped `PROJECT` segment
- match to existing project masters where possible
- create new master records where required

Recommended import options:

- create missing projects automatically
- map to existing projects by exact code
- map to existing projects by exact name
- review ambiguous matches manually

Recommended import result summary:

- created projects
- linked mappings
- skipped rows
- duplicates detected
- conflicts needing review

## D. Project Usage Screen

Purpose:

- help users understand impact before archive or merge

Show:

- linked programs
- linked objectives
- linked org units
- active funding submission lines
- active activities
- references in fiscal restrictions
- last updated date

## Recommended Business Rules

1. A project can exist without a source-segment mapping.
2. A source-segment value can map to only one active project for a fiscal year and data scope.
3. A project should not be physically deleted; use `ActiveFlag`.
4. A project code should be unique among active project masters where provided.
5. If a client uses project segment import, source mappings should be maintained in the mapping table rather than rewriting the project master every year.
6. Project custom attributes should be validated through the existing dimension-attribute framework.
7. Archiving a project should be blocked or warned when it is referenced by active funding submissions or activities.

## Workflow Recommendation

Projects do not need a heavy workflow at first.

Recommended initial statuses in `LifecycleStatusCode`:

- `IDEA`
- `PIPELINE`
- `APPRAISED`
- `APPROVED`
- `ACTIVE`
- `ON_HOLD`
- `COMPLETED`
- `CANCELLED`

If needed later, add a dedicated workflow table only when there is a real approval path for project registration itself.

## Reporting Recommendation

The module should support these reporting views:

### 1. Project Register

Shows:

- all active projects
- classification, org ownership, lifecycle, and financing summary

### 2. Project To Program Matrix

Shows:

- project
- linked programs
- primary/contributing relationship

### 3. Project To Objective Matrix

Shows:

- project
- objectives supported
- direct/indirect contribution

### 4. Project Funding Pipeline

Shows:

- project
- lifecycle
- estimated total cost
- approved total cost
- funding gap
- linked funding submissions

### 5. Project Delivery View

Shows:

- project
- linked activities
- date range
- implementation status

## Migration Strategy

## Phase 1. Stabilize The Data Model

1. expand `tblSbProject` into a master project table
2. create `tblSbProjectSourceMap`
3. migrate current source columns out of `tblSbProject`
4. update existing project list and form screens

## Phase 2. Add Cross-Links

1. create `tblSbProjectProgramLink`
2. create `tblSbProjectObjectiveLink`
3. create `tblSbProjectOrgUnitLink`
4. add project link maintenance UI

## Phase 3. Reuse Across Strategy

1. strengthen funding-submission project selection
2. add `ProjectID` to activities
3. add project filters to reports
4. add usage view

## Phase 4. Improve Fiscal And Governance Links

1. add `RestrictedProjectID` to resource envelopes
2. optionally support project references in risks and narratives
3. add project pipeline reports

## Suggested SQL Deliverables

If implemented, the likely SQL script set would be:

- `alter_tblSbProject_expand_master_fields.sql`
- `create_tblSbProjectSourceMap.sql`
- `create_tblSbProjectProgramLink.sql`
- `create_tblSbProjectObjectiveLink.sql`
- `create_tblSbProjectOrgUnitLink.sql`
- `alter_tblSbActivity_add_project.sql`
- `alter_tblSbResourceEnvelope_add_project_fk.sql`
- `migrate_tblSbProject_source_fields_to_source_map.sql`

## Suggested PHP Deliverables

Likely application updates:

- extend project methods in `StrategicBudgetingAdminModel`
- enhance `StrategySetupController` project actions
- expand `ProjectList` and `ProjectForm`
- add project link management UI
- update funding-submission project selectors
- add project-aware reporting filters

## Fit With Existing CBMSv21 Design

This design fits the current codebase because:

- Projects already exist in strategy setup and menu configuration.
- A `PROJECT` strategic segment mapping already exists.
- Funding submissions already store `ProjectID`.
- Custom attributes already support the `PROJECT` dimension.
- The application already uses active flags, audit fields, import overlays, and setup-style admin screens.

## Recommended MVP

If a smaller first release is needed, the MVP should include:

- upgraded `tblSbProject` master fields
- `tblSbProjectSourceMap`
- project list and form redesign
- project-to-program link table
- project-to-objective link table
- custom attributes for `PROJECT`
- improved funding-submission selector
- project usage screen

This gives CBMSv21 a real project master without forcing every later integration into the first release.

## Final Recommendation

Treat Projects as a shared strategic master dimension, with:

- a stable master project record
- a separate fiscal/source mapping layer
- link tables for strategic relationships
- custom attributes for client-specific fields

That design will scale much better than keeping Projects as a simple imported overlay table, and it fits naturally with the current CBMSv21 architecture.
