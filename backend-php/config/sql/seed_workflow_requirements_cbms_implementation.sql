USE [CBMSv2_INITTEST];
GO

SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowProjects is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowRequirements is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblUsers', N'U') IS NULL
BEGIN
    RAISERROR(N'tblUsers is missing.', 16, 1);
    RETURN;
END;

IF COL_LENGTH(N'dbo.tblWorkflowRequirements', N'DeliveryClassCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD DeliveryClassCode NVARCHAR(30) NOT NULL
        CONSTRAINT DF_tblWorkflowRequirements_DeliveryClassCode DEFAULT (N'ENHANCEMENT');
END;

IF COL_LENGTH(N'dbo.tblWorkflowRequirements', N'SourceDocument') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD SourceDocument NVARCHAR(255) NULL;
END;

IF COL_LENGTH(N'dbo.tblWorkflowRequirements', N'SourceSection') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD SourceSection NVARCHAR(255) NULL;
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
      AND name = N'CK_tblWorkflowRequirements_DeliveryClassCode'
      AND [definition] NOT LIKE N'%SECURITY%'
)
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    DROP CONSTRAINT CK_tblWorkflowRequirements_DeliveryClassCode;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
      AND name = N'CK_tblWorkflowRequirements_DeliveryClassCode'
)
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_DeliveryClassCode
        CHECK (DeliveryClassCode IN (N'UPGRADE', N'ENHANCEMENT', N'CONFIGURATION', N'INTEGRATION', N'MIGRATION', N'TESTING', N'TRAINING', N'GOVERNANCE', N'SECURITY', N'SUPPORT'));
END;
GO

DECLARE @OwnerUserID INT = NULL;
DECLARE @UserCount INT = 0;
DECLARE @SeedTag NVARCHAR(100) = N'CBMS_IMPLEMENTATION_REQUIREMENTS_SEED';

DECLARE @Users TABLE
(
    RowNo INT IDENTITY(1,1) NOT NULL,
    UserID INT NOT NULL
);

IF COL_LENGTH(N'dbo.tblUsers', N'IsActive') IS NOT NULL
BEGIN
    INSERT INTO @Users (UserID)
    SELECT TOP (10) UserID
    FROM dbo.tblUsers
    WHERE IsActive = 1
    ORDER BY UserID ASC;
END
ELSE
BEGIN
    INSERT INTO @Users (UserID)
    SELECT TOP (10) UserID
    FROM dbo.tblUsers
    ORDER BY UserID ASC;
END;

IF NOT EXISTS (SELECT 1 FROM @Users)
BEGIN
    RAISERROR(N'No users are available for assigning implementation requirements.', 16, 1);
    RETURN;
END;

SELECT @UserCount = COUNT(*) FROM @Users;

SELECT TOP (1) @OwnerUserID = UserID
FROM @Users
ORDER BY CASE WHEN UserID = 2 THEN 0 ELSE 1 END, UserID ASC;

DECLARE @Projects TABLE
(
    ProjectCode NVARCHAR(50) NOT NULL PRIMARY KEY,
    ProjectName NVARCHAR(255) NOT NULL,
    [Description] NVARCHAR(MAX) NULL,
    ProjectStatusCode NVARCHAR(30) NOT NULL,
    StartDate DATE NULL,
    TargetEndDate DATE NULL,
    OwnerSlot INT NOT NULL
);

INSERT INTO @Projects
    (ProjectCode, ProjectName, [Description], ProjectStatusCode, StartDate, TargetEndDate, OwnerSlot)
VALUES
    (N'CBMS-P0-GOV', N'Programme Mobilisation and Governance', N'Project mobilisation, stakeholder alignment, implementation planning, governance setup, requirements validation, and environment readiness.', N'PLANNED', '2026-07-01', '2026-08-31', 0),
    (N'CBMS-PX-STAB', N'Legacy Stabilisation and Audit Fixes', N'Immediate fixes, audit controls, strategy module corrections, reporting improvements, and early upgrade-ready functionality from the phased implementation proposal.', N'PLANNED', '2026-07-01', '2026-09-30', 1),
    (N'CBMS-P1-FISCAL', N'Phase 1 - Budget Strategy, Fiscal Planning and HRMIS Integration', N'Upgrade of strategic planning, MTFF, resource ceilings, manpower budgeting, fiscal governance, and core HRMIS integration readiness.', N'PLANNED', '2026-08-01', '2026-10-05', 2),
    (N'CBMS-HRMIS-NICR', N'HRMIS, CBMS and NICR Integration Workstream', N'Dedicated workstream for the HRMIS Oracle EBS, CBMS, and NICR integration enhancements specified in the final terms of reference.', N'PLANNED', '2026-08-03', '2026-09-25', 3),
    (N'CBMS-P2-FORM', N'Phase 2 - Budget Submission and Formulation', N'Upgrade of budget preparation, submission workflows, ministry approvals, subvention reporting, Excel templates, and formulation reports.', N'PLANNED', '2026-10-01', '2026-11-16', 4),
    (N'CBMS-P3-EXEC', N'Phase 3 - Budget Execution and Operational Rollout', N'Upgrade of budget execution, virement workflow, operational controls, reporting, ERP integration, support model, and warranty handover.', N'PLANNED', '2026-11-01', '2027-04-01', 5),
    (N'CBMS-P4-ANALYTICS', N'Phase 4 - Analytics, Reporting and Optimisation', N'Enterprise analytics, data warehouse, ETL, dashboards, forecasting, cross-department reporting, optimisation, and closure.', N'PLANNED', '2027-04-01', '2027-07-01', 6);

BEGIN TRANSACTION;

MERGE dbo.tblWorkflowProjects AS target
USING @Projects AS source
    ON target.ProjectCode = source.ProjectCode
WHEN MATCHED THEN
    UPDATE SET
        ProjectName = source.ProjectName,
        [Description] = source.[Description],
        ProjectOwnerUserID = COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.OwnerSlot % @UserCount) + 1)), @OwnerUserID),
        ProjectStatusCode = source.ProjectStatusCode,
        StartDate = source.StartDate,
        TargetEndDate = source.TargetEndDate,
        Active = 1,
        UpdatedAt = SYSUTCDATETIME(),
        UpdatedBy = @OwnerUserID
WHEN NOT MATCHED THEN
    INSERT
        (ProjectCode, ProjectName, [Description], ProjectOwnerUserID, ProjectStatusCode,
         StartDate, TargetEndDate, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (source.ProjectCode, source.ProjectName, source.[Description],
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.OwnerSlot % @UserCount) + 1)), @OwnerUserID),
         source.ProjectStatusCode, source.StartDate, source.TargetEndDate, 1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.tblWorkflowProjectUsers
        (WorkflowProjectID, UserID, ProjectRoleCode, AssignedAt, AssignedBy)
    SELECT
        p.WorkflowProjectID,
        u.UserID,
        CASE WHEN u.RowNo = 1 THEN N'LEAD' ELSE N'MEMBER' END,
        SYSUTCDATETIME(),
        @OwnerUserID
    FROM dbo.tblWorkflowProjects p
    CROSS JOIN @Users u
    WHERE p.ProjectCode IN (SELECT ProjectCode FROM @Projects)
      AND u.RowNo <= 5
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.tblWorkflowProjectUsers existing
          WHERE existing.WorkflowProjectID = p.WorkflowProjectID
            AND existing.UserID = u.UserID
      );
END;

DECLARE @Requirements TABLE
(
    RequirementCode NVARCHAR(50) NOT NULL PRIMARY KEY,
    ProjectCode NVARCHAR(50) NOT NULL,
    ModuleCode NVARCHAR(100) NULL,
    DeliveryClassCode NVARCHAR(30) NOT NULL,
    RequirementTitle NVARCHAR(255) NOT NULL,
    RequirementTypeCode NVARCHAR(30) NOT NULL,
    PriorityCode NVARCHAR(20) NOT NULL,
    RequirementStatusCode NVARCHAR(30) NOT NULL,
    SourceDocument NVARCHAR(255) NULL,
    SourceSection NVARCHAR(255) NULL,
    [Description] NVARCHAR(MAX) NULL,
    AcceptanceCriteria NVARCHAR(MAX) NULL,
    OwnerSlot INT NOT NULL,
    RequestedSlot INT NOT NULL
);

INSERT INTO @Requirements
    (RequirementCode, ProjectCode, ModuleCode, DeliveryClassCode, RequirementTitle, RequirementTypeCode,
     PriorityCode, RequirementStatusCode, SourceDocument, SourceSection, [Description], AcceptanceCriteria, OwnerSlot, RequestedSlot)
VALUES
    (N'REQ-GOV-001', N'CBMS-P0-GOV', N'Governance', N'GOVERNANCE', N'Establish implementation governance framework', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Project Mobilisation and Inception Phase',
     N'<p>Define the project governance structure for the phased CBMS upgrade and related budgetary governance work.</p><ul><li>Confirm decision forums, project roles, issue escalation, acceptance gates, and reporting cadence.</li><li>Align the governance model to the Government of Lesotho budget lifecycle and implementation milestones.</li></ul>',
     N'<ul><li>Governance structure is documented and approved.</li><li>Project roles and escalation paths are clear.</li><li>Milestone acceptance gates are defined for each phase.</li></ul>', 0, 1),
    (N'REQ-GOV-002', N'CBMS-P0-GOV', N'Inception', N'GOVERNANCE', N'Produce inception report and implementation roadmap', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Project Mobilisation and Inception Phase',
     N'<p>Prepare the formal inception report, implementation roadmap, and high-level phase plan.</p><ul><li>Include scope, assumptions, milestones, risks, dependencies, stakeholder responsibilities, and target go-live dates.</li></ul>',
     N'<ul><li>Inception report is issued.</li><li>Roadmap reflects mobilisation, Phase 1, Phase 2, Phase 3, and Phase 4.</li><li>Stakeholders agree the roadmap before configuration begins.</li></ul>', 1, 2),
    (N'REQ-GOV-003', N'CBMS-P0-GOV', N'Stakeholders', N'GOVERNANCE', N'Run stakeholder alignment and solution demonstration workshops', N'TRAINING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Project Mobilisation and Inception Phase',
     N'<p>Run structured engagement workshops to align Budget Department, IT, HRMIS, Payroll, Home Affairs, and ministry users on project scope and available CBMS capability.</p>',
     N'<ul><li>Workshop attendance and outcomes are recorded.</li><li>Open issues and scope clarifications are captured as requirements or project tasks.</li><li>Stakeholder feedback is reflected in the implementation plan.</li></ul>', 2, 3),
    (N'REQ-GOV-004', N'CBMS-P0-GOV', N'Requirements', N'GOVERNANCE', N'Maintain source-traceable requirement register', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Requirements validation',
     N'<p>Every implementation requirement must identify its source document, source section, project phase, delivery class, requirement type, priority, owner, and acceptance criteria.</p>',
     N'<ul><li>Requirements can be filtered by project, delivery class, type, priority, and status.</li><li>Source document and source section are visible on each requirement.</li><li>Requirements can be linked to tasks, tests, training, and attachments.</li></ul>', 3, 4),
    (N'REQ-GOV-005', N'CBMS-P0-GOV', N'Environment', N'CONFIGURATION', N'Plan infrastructure and environment readiness', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Initial environment and infrastructure planning',
     N'<p>Identify environments, deployment sequence, access needs, infrastructure assumptions, and data migration staging requirements before configuration begins.</p>',
     N'<ul><li>Environment plan identifies development, testing, training, and production needs.</li><li>Access, database, backup, and deployment responsibilities are documented.</li><li>Readiness gaps become project tasks.</li></ul>', 4, 0),
    (N'REQ-GOV-006', N'CBMS-P0-GOV', N'Project Controls', N'GOVERNANCE', N'Capture weekly progress reporting and issue management', N'NON_FUNCTIONAL', N'SHOULD', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Client Assignment Management Arrangement',
     N'<p>The project should record weekly progress, upcoming plans, decisions, issues, and actions in a way that can be tracked inside CBMS workflow projects.</p>',
     N'<ul><li>Weekly progress tasks can be assigned and closed.</li><li>Decisions and issues can be attached or linked to requirements.</li><li>Outstanding actions are visible in project summaries.</li></ul>', 5, 1),

    (N'REQ-STAB-001', N'CBMS-PX-STAB', N'Strategy', N'ENHANCEMENT', N'Correct strategy rollover processing', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Correct strategy rollover behaviour so new fiscal year planning starts from accurate and complete strategy records.</p>',
     N'<ul><li>Rollover produces expected records for the new fiscal year.</li><li>No duplicate or missing strategy rows are created.</li><li>Users can verify rollover results before final use.</li></ul>', 0, 2),
    (N'REQ-STAB-002', N'CBMS-PX-STAB', N'Strategy', N'ENHANCEMENT', N'Enable strategy paper printing for all heads', N'REPORTING', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Ensure Strategy Paper outputs can be printed for every Head required by the budget process.</p>',
     N'<ul><li>All Heads can be selected for printing.</li><li>Report content matches the selected Head.</li><li>Missing or empty sections are clearly identified.</li></ul>', 1, 3),
    (N'REQ-STAB-003', N'CBMS-PX-STAB', N'Performance', N'CONFIGURATION', N'Make performance indicators mandatory where required', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Apply mandatory capture rules for performance indicators so required strategy and budgeting records cannot progress with missing indicators.</p>',
     N'<ul><li>Required PI fields are validated before submission.</li><li>Clear validation messages identify missing PI data.</li><li>Rules can be reviewed by administrators.</li></ul>', 2, 4),
    (N'REQ-STAB-004', N'CBMS-PX-STAB', N'Budget Records', N'ENHANCEMENT', N'Remove Adj and Orig dual-record workflow risk', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Remove or replace the dual adjusted/original record pattern that creates audit confusion and reconciliation risk.</p>',
     N'<ul><li>Budget records have a clear original and revised state model.</li><li>Historical values remain traceable.</li><li>Reports no longer duplicate values because of dual records.</li></ul>', 3, 0),
    (N'REQ-STAB-005', N'CBMS-PX-STAB', N'Performance', N'ENHANCEMENT', N'Align target and actual performance indicator columns', N'DATA', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Align target and actual PI columns so data entry, reporting, and comparison outputs use consistent fields.</p>',
     N'<ul><li>Target and actual columns are consistently labelled.</li><li>Reports use the corrected field mapping.</li><li>Existing values are preserved during alignment.</li></ul>', 4, 1),
    (N'REQ-STAB-006', N'CBMS-PX-STAB', N'Performance', N'ENHANCEMENT', N'Add current year to performance indicator screens', N'FUNCTIONAL', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Show the current year in PI screens so users can distinguish current, prior, and future performance values.</p>',
     N'<ul><li>Current year values are visible on PI screens.</li><li>Year labels are consistent with fiscal context.</li><li>Existing filters and reports continue to work.</li></ul>', 5, 2),
    (N'REQ-STAB-007', N'CBMS-PX-STAB', N'Access Control', N'CONFIGURATION', N'Update role segregation and access policy rules', N'SECURITY', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Review and update access controls so duties are segregated and support/admin rights are controlled.</p>',
     N'<ul><li>Roles align with operational responsibility.</li><li>Conflicting permissions are identified and remediated.</li><li>Access changes are auditable.</li></ul>', 6, 3),
    (N'REQ-STAB-008', N'CBMS-PX-STAB', N'Access Control', N'CONFIGURATION', N'Define ICT support access rights', N'SECURITY', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Audit Fixes and Controls',
     N'<p>Define ICT support permissions that allow support activity without granting unnecessary operational authority.</p>',
     N'<ul><li>ICT support role permissions are documented.</li><li>Support access is separated from budget approval authority.</li><li>Support activity can be reviewed in audit logs.</li></ul>', 7, 4),
    (N'REQ-STAB-009', N'CBMS-PX-STAB', N'Strategy', N'UPGRADE', N'Deploy upgraded strategy module', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Deploy the upgraded Strategy Module as an early upgrade feature that supports downstream fiscal and budget planning work.</p>',
     N'<ul><li>Strategy module is available in the target environment.</li><li>Core strategy capture and review workflows operate correctly.</li><li>Users can validate upgraded behaviour before broader rollout.</li></ul>', 8, 0),
    (N'REQ-STAB-010', N'CBMS-PX-STAB', N'Priority Areas', N'ENHANCEMENT', N'Integrate priority area reporting', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Improve Priority Area reporting so strategy and performance information can be reviewed across priority structures.</p>',
     N'<ul><li>Priority Area reports include required strategy and performance fields.</li><li>Users can filter and export report outputs.</li><li>Report totals reconcile to underlying records.</li></ul>', 9, 1),
    (N'REQ-STAB-011', N'CBMS-PX-STAB', N'Strategy', N'ENHANCEMENT', N'Provide qualitative information module', N'FUNCTIONAL', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Capture qualitative planning and performance commentary alongside quantitative budget and PI data.</p>',
     N'<ul><li>Users can capture qualitative notes by relevant planning context.</li><li>Qualitative content can be reviewed and reported.</li><li>Formatting is preserved where rich text is used.</li></ul>', 0, 2),
    (N'REQ-STAB-012', N'CBMS-PX-STAB', N'Costing', N'ENHANCEMENT', N'Produce detailed costing report', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Provide a detailed costing report that supports review of budget costing assumptions and budget preparation outputs.</p>',
     N'<ul><li>Report includes the agreed costing dimensions.</li><li>Users can run the report by fiscal year and budget context.</li><li>Report values reconcile to captured costing records.</li></ul>', 1, 3),
    (N'REQ-STAB-013', N'CBMS-PX-STAB', N'Performance', N'ENHANCEMENT', N'Restructure priority area performance screen', N'FUNCTIONAL', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Restructure the Priority Area Performance screen so users can review progress and performance indicators more efficiently.</p>',
     N'<ul><li>Screen groups related performance information clearly.</li><li>Users can navigate, edit, and save without data loss.</li><li>The updated screen follows CBMS UI conventions.</li></ul>', 2, 4),
    (N'REQ-STAB-014', N'CBMS-PX-STAB', N'Reporting', N'ENHANCEMENT', N'Automate enhanced reporting outputs', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 1 - Quick Wins and Early Upgrade Features',
     N'<p>Automate priority reporting outputs where manual report preparation creates delay or inconsistency.</p>',
     N'<ul><li>Reports can be generated from system data without manual rework.</li><li>Outputs use consistent templates and labels.</li><li>Users can validate report content against source data.</li></ul>', 3, 0),

    (N'REQ-P1-001', N'CBMS-P1-FISCAL', N'Platform', N'UPGRADE', N'Deploy latest CBMS platform environment', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - CBMS Upgrade Deliverables',
     N'<p>Deploy the latest CBMS platform environment as the foundation for strategic budgeting, fiscal planning, manpower budgeting, workflow, reporting, and integration work.</p>',
     N'<ul><li>Target CBMS environment is deployed and reachable.</li><li>Core configuration, security, and database connectivity are validated.</li><li>Deployment readiness is signed off before functional configuration.</li></ul>', 4, 1),
    (N'REQ-P1-002', N'CBMS-P1-FISCAL', N'Strategic Budgeting', N'MIGRATION', N'Migrate strategic budgeting workflows and configuration', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - CBMS Upgrade Deliverables',
     N'<p>Migrate and validate strategic budgeting workflows and configuration from the existing CBMS environment into the upgraded platform.</p>',
     N'<ul><li>Workflow configuration is migrated for agreed strategic budgeting processes.</li><li>Workflow routes, roles, and approvals are tested.</li><li>Configuration differences are documented.</li></ul>', 5, 2),
    (N'REQ-P1-003', N'CBMS-P1-FISCAL', N'MTFF', N'CONFIGURATION', N'Configure MTFF functionality', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Functional Deliverables - MTFF and MTEF Alignment',
     N'<p>Configure MTFF functionality so medium-term fiscal planning can be captured, reviewed, and linked to MTEF planning.</p>',
     N'<ul><li>MTFF setup supports agreed fiscal years and versions.</li><li>Users can capture and review MTFF values.</li><li>MTFF outputs can feed downstream ceilings and MTEF processes.</li></ul>', 6, 3),
    (N'REQ-P1-004', N'CBMS-P1-FISCAL', N'MTFF', N'ENHANCEMENT', N'Capture and manage macroeconomic assumptions', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Functional Deliverables - MTFF and MTEF Alignment',
     N'<p>Capture macroeconomic assumptions used in MTFF and resource ceiling calculations with traceable notes and versions.</p>',
     N'<ul><li>Users can enter assumptions with source, period, and notes.</li><li>Assumptions are available to fiscal planning calculations.</li><li>Changes are visible for review and audit.</li></ul>', 7, 4),
    (N'REQ-P1-005', N'CBMS-P1-FISCAL', N'Fiscal Framework', N'CONFIGURATION', N'Configure fiscal framework structures', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Functional Deliverables - MTFF and MTEF Alignment',
     N'<p>Configure the fiscal framework structures required to support MTFF, ceilings, MTEF, manpower budgeting, and governance reporting.</p>',
     N'<ul><li>Required fiscal planning dimensions are configured.</li><li>Fiscal framework data can be captured and reported.</li><li>Configuration is validated with Budget Department users.</li></ul>', 8, 0),
    (N'REQ-P1-RC-001', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'CONFIGURATION', N'Configure resource ceiling calculator', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Resource Ceiling Calculator',
     N'<p>Configure the Resource Ceiling Calculator as a distinct fiscal planning capability rather than treating it as a generic MTFF task.</p>',
     N'<ul><li>Users can create, calculate, review, and approve ceiling calculations.</li><li>Calculator inputs and outputs are visible by fiscal year and version.</li><li>Ceiling results can be used by downstream MTEF and budget formulation workflows.</li></ul>', 9, 1),
    (N'REQ-P1-RC-002', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'ENHANCEMENT', N'Support multi-scenario ceiling modelling', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Resource Ceiling Calculator',
     N'<p>Allow Budget Department users to model multiple ceiling scenarios before selecting or approving the scenario used for budget preparation.</p>',
     N'<ul><li>Multiple scenarios can exist for the same fiscal year and version.</li><li>Users can compare scenario outputs.</li><li>The approved scenario is clearly identified.</li></ul>', 0, 2),
    (N'REQ-P1-RC-003', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'ENHANCEMENT', N'Track ceiling versions and approval dates', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Resource Ceiling Calculator',
     N'<p>Maintain ceiling version number, approval date, and calculation status so approved ceilings are traceable.</p>',
     N'<ul><li>Each ceiling calculation has a version identifier.</li><li>Approval date and approved user are recorded.</li><li>Historical ceiling versions remain available for review.</li></ul>', 1, 3),
    (N'REQ-P1-RC-004', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'CONFIGURATION', N'Allocate ceilings by agreed budget dimensions', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Resource Ceiling Calculator',
     N'<p>Support ceiling allocation by Head, programme, cost centre, and economic classification where required by the implementation plan and HRMIS integration TOR.</p>',
     N'<ul><li>Ceiling allocation dimensions are configurable.</li><li>Users can view ceiling breakdown by the agreed dimensions.</li><li>Outputs reconcile to the total approved ceiling.</li></ul>', 2, 4),
    (N'REQ-P1-RC-005', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'INTEGRATION', N'Integrate MTFF assumptions into ceiling calculations', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - MTFF and Resource Ceilings',
     N'<p>Link MTFF assumptions and fiscal framework data to the ceiling calculation process so ceilings reflect approved fiscal planning assumptions.</p>',
     N'<ul><li>Calculator can reference MTFF assumptions.</li><li>Assumption changes can be traced to ceiling outputs.</li><li>Users can review calculation basis before approval.</li></ul>', 3, 0),
    (N'REQ-P1-RC-006', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'INTEGRATION', N'Push approved ceilings into MTEF and budget formulation', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - MTFF and Resource Ceilings',
     N'<p>Approved ceilings must be available to MTEF and later budget formulation processes to support controlled budget preparation.</p>',
     N'<ul><li>Approved ceiling data is available to MTEF screens and reports.</li><li>Budget formulation workflows can reference approved ceiling values.</li><li>Users can identify the ceiling version used.</li></ul>', 4, 1),
    (N'REQ-P1-RC-007', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'ENHANCEMENT', N'Produce ceiling reports and comparison outputs', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - Resource Ceiling Calculator',
     N'<p>Provide reports for ceiling allocations, scenario comparisons, approved ceilings, and changes between versions.</p>',
     N'<ul><li>Users can generate scenario comparison outputs.</li><li>Approved ceiling reports can be printed or exported.</li><li>Reports show source version and approval date.</li></ul>', 5, 2),
    (N'REQ-P1-RC-008', N'CBMS-P1-FISCAL', N'Resource Ceilings', N'GOVERNANCE', N'Capture ceiling assumptions and calculation notes', N'DATA', N'SHOULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - MTFF and Resource Ceilings',
     N'<p>Capture notes explaining the assumptions, policy decisions, and manual adjustments used in ceiling calculations.</p>',
     N'<ul><li>Each calculation can include notes.</li><li>Notes are visible during review and approval.</li><li>Notes are retained with the ceiling version.</li></ul>', 6, 3),
    (N'REQ-P1-006', N'CBMS-P1-FISCAL', N'Manpower Budgeting', N'UPGRADE', N'Upgrade manpower budgeting functionality', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - CBMS Upgrade Deliverables',
     N'<p>Upgrade manpower budgeting so employee, establishment, wage bill, and budget ceiling information can support fiscal planning and HRMIS exchange.</p>',
     N'<ul><li>Manpower budgeting screens are available in the upgraded platform.</li><li>Core data structures support employee-level budgeting.</li><li>Manpower outputs can be reconciled to fiscal ceilings.</li></ul>', 7, 4),
    (N'REQ-P1-007', N'CBMS-P1-FISCAL', N'Data Migration', N'MIGRATION', N'Migrate and validate fiscal planning data structures', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - CBMS Upgrade Deliverables',
     N'<p>Migrate fiscal planning structures and validate that the upgraded platform can support current and future fiscal planning processes.</p>',
     N'<ul><li>Fiscal planning structures are migrated.</li><li>Validation reports identify missing or inconsistent data.</li><li>Issues are resolved or logged before user validation.</li></ul>', 8, 0),
    (N'REQ-P1-008', N'CBMS-P1-FISCAL', N'Security', N'UPGRADE', N'Upgrade security, governance, and workflow framework', N'SECURITY', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - CBMS Upgrade Deliverables',
     N'<p>Upgrade the security, governance, and workflow framework used by strategic budgeting and fiscal planning processes.</p>',
     N'<ul><li>Roles and permissions are configured.</li><li>Approval structures are operational.</li><li>Audit framework captures key changes and approvals.</li></ul>', 9, 1),

    (N'REQ-INT-001', N'CBMS-HRMIS-NICR', N'Integration Discovery', N'GOVERNANCE', N'Review and align existing HRMIS integration', N'INTEGRATION', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Scope of Work - Conduct stakeholder consultations',
     N'<p>Review the current one-way HRMIS to CBMS interface and confirm enhancement requirements with Budget Execution, wage bill management, HR cadre, Payroll, and Home Affairs stakeholders.</p>',
     N'<ul><li>Current integration behaviour is documented.</li><li>Stakeholder requirements are validated.</li><li>Confirmed gaps are translated into implementation tasks.</li></ul>', 0, 2),
    (N'REQ-INT-002', N'CBMS-HRMIS-NICR', N'HRMIS API', N'INTEGRATION', N'Enable two-way HRMIS and CBMS manpower budget exchange', N'INTEGRATION', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Background and Objectives',
     N'<p>Use the existing API capability to allow Oracle HR and Payroll to import and export manpower budget information captured in CBMS.</p>',
     N'<ul><li>CBMS can receive required HRMIS data.</li><li>HRMIS can receive approved CBMS manpower budget information.</li><li>API payloads, errors, and retries are documented and tested.</li></ul>', 1, 3),
    (N'REQ-INT-003', N'CBMS-HRMIS-NICR', N'Payroll Sync', N'INTEGRATION', N'Synchronise payroll data from HRMIS to CBMS', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 1 - HRMIS Payroll Integration and Manpower Data Exchange',
     N'<p>Synchronise payroll data into CBMS to support accurate budgeting of salaries and allowances on a per-employee basis.</p>',
     N'<ul><li>Payroll sync imports agreed employee and payroll fields.</li><li>Sync results identify accepted and rejected records.</li><li>Imported data can be used by manpower budgeting.</li></ul>', 2, 4),
    (N'REQ-INT-004', N'CBMS-HRMIS-NICR', N'Retirement Planning', N'ENHANCEMENT', N'Include date of birth and retirement planning data', N'DATA', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Background',
     N'<p>Extend imported HRMIS data to include date of birth and related fields needed for retirement planning and budget projection analysis.</p>',
     N'<ul><li>Date of birth is available where provided by HRMIS.</li><li>Retirement planning data supports fiscal analysis.</li><li>Data quality exceptions are logged for correction.</li></ul>', 3, 0),
    (N'REQ-INT-005', N'CBMS-HRMIS-NICR', N'Approved Budget Export', N'INTEGRATION', N'Transmit approved CBMS budget data to HRMIS', N'INTEGRATION', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Enhancements of CBMS and HRMIS',
     N'<p>Transmit approved CBMS budget data to HRMIS so HR and payroll transactions can be validated against the approved budget.</p>',
     N'<ul><li>Approved budget data is exported after approval.</li><li>HRMIS can identify the applicable budget version and approval date.</li><li>Exports are logged with success or failure status.</li></ul>', 4, 1),
    (N'REQ-INT-006', N'CBMS-HRMIS-NICR', N'Wage Bill Ceilings', N'INTEGRATION', N'Transmit annual and monthly wage bill ceilings', N'DATA', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Transmission of approved CBMS budget data',
     N'<p>Transmit annual and monthly wage bill ceilings from CBMS to HRMIS as part of the approved budget exchange.</p>',
     N'<ul><li>Annual wage bill ceilings are included in the export.</li><li>Monthly budget and increments are included where approved.</li><li>Values reconcile to the approved CBMS budget.</li></ul>', 5, 2),
    (N'REQ-INT-007', N'CBMS-HRMIS-NICR', N'Budget Breakdown', N'INTEGRATION', N'Transmit budget breakdown by required classifications', N'DATA', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Transmission of approved CBMS budget data',
     N'<p>Transmit budget breakdown by Head, programme, cost centre, and economic classification to support HRMIS validation.</p>',
     N'<ul><li>Export includes required budget classifications.</li><li>Classification values are valid and consistent with CBMS master data.</li><li>Missing classifications are reported before final export.</li></ul>', 6, 3),
    (N'REQ-INT-008', N'CBMS-HRMIS-NICR', N'Budget Versioning', N'INTEGRATION', N'Transmit budget version number and approval date', N'DATA', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Transmission of approved CBMS budget data',
     N'<p>Include the budget version number and approval date in outbound CBMS budget data so HRMIS can enforce the correct approved budget baseline.</p>',
     N'<ul><li>Outbound payload includes version number.</li><li>Outbound payload includes approval date.</li><li>HRMIS can distinguish original and revised budget versions.</li></ul>', 7, 4),
    (N'REQ-INT-009', N'CBMS-HRMIS-NICR', N'Establishment Control', N'INTEGRATION', N'Transmit approved establishment and post ceilings', N'DATA', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Establishment and Post Control',
     N'<p>Transmit approved establishment and post ceilings from CBMS to HRMIS for validation of recruitment, promotions, acting appointments, and allowances.</p>',
     N'<ul><li>Approved establishment and post ceiling data is available to HRMIS.</li><li>Validation use cases cover recruitment, promotion, acting appointment, and allowance scenarios.</li><li>Unbudgeted position attempts can be identified.</li></ul>', 8, 0),
    (N'REQ-INT-010', N'CBMS-HRMIS-NICR', N'Transaction Validation', N'ENHANCEMENT', N'Validate HR and payroll transactions against approved budget ceilings', N'FUNCTIONAL', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Budget Validation at Transactional Level',
     N'<p>Allow HRMIS to validate HR and payroll transactions against approved CBMS ceilings and return configurable warnings or blocks for over-commitments.</p>',
     N'<ul><li>Validation can warn or block depending on configured rule.</li><li>Over-commitment cases are logged.</li><li>Test scenarios cover within-budget, warning, and blocked transactions.</li></ul>', 9, 1),
    (N'REQ-INT-011', N'CBMS-HRMIS-NICR', N'In-Year Adjustments', N'INTEGRATION', N'Support supplementary budgets, virements, and contingency allocations', N'FUNCTIONAL', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'In-Year Budget Adjustments',
     N'<p>Support transmission and maintenance of original and revised budget versions in HRMIS after supplementary budgets, virements, or contingency allocations are approved.</p>',
     N'<ul><li>Original and revised versions are distinguishable.</li><li>Approved virements and supplementary budgets can update HRMIS validation baselines.</li><li>Changes are logged and time stamped.</li></ul>', 0, 2),
    (N'REQ-INT-012', N'CBMS-HRMIS-NICR', N'Reconciliation', N'ENHANCEMENT', N'Produce CBMS and HRMIS reconciliation reports', N'REPORTING', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Reconciliation and Reporting',
     N'<p>Develop standard reconciliation reports comparing CBMS approved budgets with HRMIS payroll projections and actuals.</p>',
     N'<ul><li>Reports compare approved budgets, projections, and actuals.</li><li>Reports identify variances and unfunded commitments.</li><li>Sample system-generated outputs are available for review.</li></ul>', 1, 3),
    (N'REQ-INT-013', N'CBMS-HRMIS-NICR', N'Salary Bill Report', N'ENHANCEMENT', N'Populate monthly salary budgeted field in HRMIS salary bill report', N'REPORTING', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Reconciliation and Reporting',
     N'<p>Update the HRMIS salary bill report so the approved monthly salary transmitted from CBMS populates the Monthly Salary Budgeted field.</p>',
     N'<ul><li>Monthly Salary Budgeted is populated from approved CBMS data.</li><li>Report values reconcile to transmitted budget data.</li><li>Missing or stale budget values are identifiable.</li></ul>', 2, 4),
    (N'REQ-INT-014', N'CBMS-HRMIS-NICR', N'Forecast Data', N'INTEGRATION', N'Transmit selected HR data to CBMS for projections and analysis', N'DATA', N'SHOULD', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Reconciliation and Reporting',
     N'<p>Enable selected HR data required for budget projections and analysis to flow from HRMIS to CBMS.</p>',
     N'<ul><li>Required HR forecast fields are defined.</li><li>CBMS receives selected HR data for projections.</li><li>Data transfer quality checks are available.</li></ul>', 3, 0),
    (N'REQ-INT-015', N'CBMS-HRMIS-NICR', N'NICR', N'INTEGRATION', N'Authenticate ID numbers in real time against NICR', N'INTEGRATION', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Enhancement of HRMIS with NICR',
     N'<p>Enhance HRMIS with NICR integration so ID numbers are authenticated in real time when captured in HRMIS.</p>',
     N'<ul><li>ID validation is triggered during HRMIS capture.</li><li>Successful and failed authentication responses are handled.</li><li>Unavailable NICR service scenarios are defined.</li></ul>', 4, 1),
    (N'REQ-INT-016', N'CBMS-HRMIS-NICR', N'NICR', N'ENHANCEMENT', N'Detect incorrect ID numbers immediately during data entry', N'FUNCTIONAL', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Enhancement of HRMIS with NICR',
     N'<p>Incorrect ID numbers should be detected immediately when entered so errors are corrected before downstream payroll and budget processes.</p>',
     N'<ul><li>Invalid ID numbers are flagged immediately.</li><li>Users receive a clear validation message.</li><li>Validation outcomes are logged where required.</li></ul>', 5, 2),
    (N'REQ-INT-017', N'CBMS-HRMIS-NICR', N'NICR', N'INTEGRATION', N'Synchronise selected NICR personal detail updates monthly', N'DATA', N'SHOULD', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Enhancement of HRMIS with NICR',
     N'<p>Share selected personal detail updates captured in NICR with HRMIS once per month.</p>',
     N'<ul><li>Monthly synchronisation scope is defined.</li><li>Updates are logged and time stamped.</li><li>Exceptions and rejected updates are reportable.</li></ul>', 6, 3),
    (N'REQ-INT-018', N'CBMS-HRMIS-NICR', N'API Security', N'SECURITY', N'Secure API access and data exchange roles', N'SECURITY', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Non-functional Requirements',
     N'<p>Apply role-based access and API security controls for CBMS, HRMIS, and NICR data exchange.</p>',
     N'<ul><li>API authentication and authorisation controls are defined.</li><li>Access is restricted to approved integration roles or service accounts.</li><li>Security configuration is tested before go-live.</li></ul>', 7, 4),
    (N'REQ-INT-019', N'CBMS-HRMIS-NICR', N'Audit', N'GOVERNANCE', N'Log imports, updates, overrides, and synchronisation events', N'SECURITY', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Non-functional Requirements',
     N'<p>Maintain full audit trails for budget imports, updates, overrides, and logged time-stamped data synchronisation events.</p>',
     N'<ul><li>Import and export events are logged.</li><li>Overrides record actor, date, reason, and affected data.</li><li>Audit logs can support reconciliation and investigation.</li></ul>', 8, 0),
    (N'REQ-INT-020', N'CBMS-HRMIS-NICR', N'Integration Strategy', N'GOVERNANCE', N'Produce integration strategy and validation rules', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Deliverables - Integration Strategy',
     N'<p>Produce an integration strategy covering budget versioning, establishment control logic, transaction-level validation rules, user acceptance tests, and operational acceptance.</p>',
     N'<ul><li>Integration strategy document is delivered.</li><li>Validation rules are documented and approved.</li><li>UAT and operational acceptance approach is included.</li></ul>', 9, 1),
    (N'REQ-INT-021', N'CBMS-HRMIS-NICR', N'Testing', N'TESTING', N'Complete integration UAT and operational acceptance', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Deliverables - User Acceptance Tests and Operational Acceptance Certificate',
     N'<p>Complete UAT and operational acceptance for HRMIS, CBMS, and NICR integration enhancements.</p>',
     N'<ul><li>UAT scenarios cover approved budget export, payroll import, establishment control, transaction validation, in-year adjustments, reconciliation, and NICR ID checks.</li><li>Defects are tracked and resolved or accepted.</li><li>Operational acceptance certificate is captured.</li></ul>', 0, 2),
    (N'REQ-INT-022', N'CBMS-HRMIS-NICR', N'Training', N'TRAINING', N'Train at least 20 integration end users and support users', N'TRAINING', N'MUST', N'REVIEW', N'Final TORs for enhanced HRMIS integration with CBMS and NICR 5th May 2026', N'Scope of Work - Train at least 20 end-users',
     N'<p>Train at least 20 users who will use or support the enhanced HRMIS, CBMS, and NICR integrations.</p>',
     N'<ul><li>Training plan and training materials are prepared.</li><li>At least 20 users attend training.</li><li>Training attendance and feedback are recorded.</li></ul>', 1, 3),

    (N'REQ-P2-001', N'CBMS-P2-FORM', N'Budget Formulation', N'UPGRADE', N'Upgrade budget preparation and submission functionality', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - CBMS Upgrade Deliverables',
     N'<p>Upgrade budget preparation and submission functionality for the ministry budget formulation process.</p>',
     N'<ul><li>Budget preparation screens are available in the upgraded platform.</li><li>Submission flow supports required ministry processes.</li><li>Users can validate preparation and submission behaviour during UAT.</li></ul>', 2, 4),
    (N'REQ-P2-002', N'CBMS-P2-FORM', N'Workflow', N'MIGRATION', N'Migrate ministry budgeting workflows', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - CBMS Upgrade Deliverables',
     N'<p>Migrate ministry-level budgeting workflows from existing configuration into the upgraded CBMS platform.</p>',
     N'<ul><li>Workflow stages, actions, and routing rules are migrated.</li><li>Approval routing is validated with sample ministries.</li><li>Configuration differences are recorded.</li></ul>', 3, 0),
    (N'REQ-P2-003', N'CBMS-P2-FORM', N'Workflow', N'CONFIGURATION', N'Configure approval routing and workflow controls', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Functional Deliverables - Budget Submission and Workflow Configuration',
     N'<p>Configure approval routing, workflow controls, and ministry submission processes for budget formulation.</p>',
     N'<ul><li>Workflow routes match agreed ministry approval model.</li><li>Users cannot bypass required approvals.</li><li>Workflow validation confirms route and permission behaviour.</li></ul>', 4, 1),
    (N'REQ-P2-004', N'CBMS-P2-FORM', N'Submission Tracking', N'ENHANCEMENT', N'Track and monitor budget submissions', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Functional Deliverables - Budget Submission and Workflow Configuration',
     N'<p>Provide submission tracking and monitoring so Budget Department can see ministry submission progress and bottlenecks.</p>',
     N'<ul><li>Submission status is visible by ministry or responsible unit.</li><li>Outstanding submissions and pending approvals are identifiable.</li><li>Monitoring output supports follow-up tasks.</li></ul>', 5, 2),
    (N'REQ-P2-005', N'CBMS-P2-FORM', N'Subventions', N'ENHANCEMENT', N'Provide SOE and subvention tracking and reporting', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Functional Deliverables - Subvention Tracking and Reporting Functionality',
     N'<p>Implement SOE and subvention reporting functionality with summary reporting and monitoring outputs.</p>',
     N'<ul><li>SOE and subvention information can be captured or reported as agreed.</li><li>Summary reports can be generated.</li><li>Monitoring reports support budget review.</li></ul>', 6, 3),
    (N'REQ-P2-006', N'CBMS-P2-FORM', N'Reporting', N'CONFIGURATION', N'Configure Excel integration and reporting templates', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Functional Deliverables - Subvention Tracking and Reporting Functionality',
     N'<p>Configure Excel integration and reporting templates required for budget formulation outputs.</p>',
     N'<ul><li>Templates match agreed reporting format.</li><li>Exports produce valid files.</li><li>Report values reconcile to source budget data.</li></ul>', 7, 4),
    (N'REQ-P2-007', N'CBMS-P2-FORM', N'Testing', N'TESTING', N'Complete workflow, reporting, and stakeholder UAT for formulation', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Testing and Validation Activities',
     N'<p>Complete workflow validation, UAT, stakeholder sign-off, and reporting validation for Phase 2.</p>',
     N'<ul><li>UAT scripts cover preparation, submission, approvals, tracking, and reports.</li><li>Defects are logged and resolved or accepted.</li><li>Stakeholder sign-off is recorded.</li></ul>', 8, 0),
    (N'REQ-P2-008', N'CBMS-P2-FORM', N'Training', N'TRAINING', N'Train Budget Department and pilot ministry users', N'TRAINING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 2 - Training and Knowledge Transfer',
     N'<p>Provide Budget Department training, pilot ministry engagement, workflow familiarisation, and operational support for Phase 2 rollout.</p>',
     N'<ul><li>Training materials cover formulation workflow and reports.</li><li>Pilot ministry engagement is completed.</li><li>Post-training issues are logged as tasks.</li></ul>', 9, 1),

    (N'REQ-P3-001', N'CBMS-P3-EXEC', N'Budget Execution', N'UPGRADE', N'Upgrade operational budget execution functionality', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - CBMS Upgrade Deliverables',
     N'<p>Upgrade operational budget execution functionality including screens, approvals, controls, and operational alignment.</p>',
     N'<ul><li>Budget execution screens are available in the upgraded platform.</li><li>Operational workflows and controls are configured.</li><li>Users can validate execution processes in UAT.</li></ul>', 0, 2),
    (N'REQ-P3-002', N'CBMS-P3-EXEC', N'Virements', N'UPGRADE', N'Upgrade virement workflow functionality', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - CBMS Upgrade Deliverables',
     N'<p>Upgrade virement workflow functionality for ministry-level initiation, approvals, compliance monitoring, and reporting.</p>',
     N'<ul><li>Users can initiate virements at ministry level.</li><li>Approval routing and controls operate as configured.</li><li>Compliance and threshold checks are visible.</li></ul>', 1, 3),
    (N'REQ-P3-003', N'CBMS-P3-EXEC', N'Operational Controls', N'MIGRATION', N'Migrate operational workflows and controls', N'DATA', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - CBMS Upgrade Deliverables',
     N'<p>Migrate operational workflows and controls into the upgraded platform and validate that the target process supports execution operations.</p>',
     N'<ul><li>Operational workflow configuration is migrated.</li><li>Control settings are validated.</li><li>Migration issues are recorded and resolved.</li></ul>', 2, 4),
    (N'REQ-P3-004', N'CBMS-P3-EXEC', N'Controls', N'ENHANCEMENT', N'Configure hard and soft budget controls and threshold monitoring', N'FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 3 - Budget Execution',
     N'<p>Configure hard and soft budget control logic with threshold monitoring and reporting for execution and virement processes.</p>',
     N'<ul><li>Hard control scenarios block disallowed actions.</li><li>Soft control scenarios warn and record exceptions.</li><li>Threshold reports identify risks and breaches.</li></ul>', 3, 0),
    (N'REQ-P3-005', N'CBMS-P3-EXEC', N'Reporting', N'ENHANCEMENT', N'Produce printable execution and virement reports', N'REPORTING', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - Functional Deliverables - Virement Workflow Functionality',
     N'<p>Produce printable reports and submission documents for execution and virement workflows.</p>',
     N'<ul><li>Users can generate printable submission documents.</li><li>Reports include approval and compliance details.</li><li>Report outputs reconcile to workflow and budget data.</li></ul>', 4, 1),
    (N'REQ-P3-006', N'CBMS-P3-EXEC', N'ERP Integration', N'INTEGRATION', N'Implement full ERP Epicor integration layer', N'INTEGRATION', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 3 - Budget Execution',
     N'<p>Implement the full ERP integration layer required for execution data exchange with Epicor or the Government ERP environment.</p>',
     N'<ul><li>Integration scope and payloads are documented.</li><li>Data exchange is tested with agreed scenarios.</li><li>Integration logs support reconciliation and support.</li></ul>', 5, 2),
    (N'REQ-P3-007', N'CBMS-P3-EXEC', N'Deployment', N'TESTING', N'Complete production deployment validation for execution rollout', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - Testing and Validation Activities',
     N'<p>Complete integration testing, UAT, QAT, and production deployment validation for the operational rollout.</p>',
     N'<ul><li>Testing covers execution, virements, reports, controls, and integrations.</li><li>Production deployment validation is documented.</li><li>Go-live readiness is signed off.</li></ul>', 6, 3),
    (N'REQ-P3-008', N'CBMS-P3-EXEC', N'Support', N'SUPPORT', N'Establish operational support procedures and escalation model', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - Ongoing Operational Support Framework',
     N'<p>Establish local support model, first and second-level support framework, escalation procedures, and post-go-live support arrangements.</p>',
     N'<ul><li>Support roles and escalation paths are documented.</li><li>Operational support procedures are handed over.</li><li>Post-go-live support tasks are tracked.</li></ul>', 7, 4),
    (N'REQ-P3-009', N'CBMS-P3-EXEC', N'Documentation', N'SUPPORT', N'Provide technical, user, operational, and warranty documentation', N'NON_FUNCTIONAL', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 3 - Documentation and Warranty Support',
     N'<p>Provide documentation and warranty support material for the upgraded budget execution and operational rollout.</p>',
     N'<ul><li>Technical documentation is available.</li><li>User and operational procedures are available.</li><li>Warranty support commencement is recorded.</li></ul>', 8, 0),

    (N'REQ-P4-001', N'CBMS-P4-ANALYTICS', N'Analytics', N'UPGRADE', N'Upgrade enterprise analytics functionality', N'REPORTING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - CBMS Upgrade Deliverables',
     N'<p>Upgrade enterprise analytics functionality to support dashboards, forecasting, trend analysis, policy analysis, and financial analytics.</p>',
     N'<ul><li>Enterprise analytics capability is available.</li><li>Users can access agreed dashboards and analytics outputs.</li><li>Analytics data sources are validated.</li></ul>', 9, 1),
    (N'REQ-P4-002', N'CBMS-P4-ANALYTICS', N'Dashboards', N'UPGRADE', N'Upgrade dashboard and reporting framework', N'REPORTING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - CBMS Upgrade Deliverables',
     N'<p>Upgrade the dashboard and reporting framework used for management, operational, and executive reporting.</p>',
     N'<ul><li>Dashboard framework is deployed.</li><li>Report structures can support required outputs.</li><li>Dashboard and report access respects permissions.</li></ul>', 0, 2),
    (N'REQ-P4-003', N'CBMS-P4-ANALYTICS', N'Data Warehouse', N'CONFIGURATION', N'Configure data warehouse and ETL processes', N'DATA', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - Data Analytics Platform',
     N'<p>Configure data warehouse schema and ETL pipelines for CBMS analytics, historical reporting, cross-department reporting, and performance monitoring.</p>',
     N'<ul><li>ETL processes load agreed source data.</li><li>Warehouse structures support dashboard and reporting needs.</li><li>ETL failures are logged and recoverable.</li></ul>', 1, 3),
    (N'REQ-P4-004', N'CBMS-P4-ANALYTICS', N'Historical Reporting', N'MIGRATION', N'Migrate reporting structures and historical reporting', N'DATA', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - CBMS Upgrade Deliverables',
     N'<p>Migrate reporting structures and historical reporting data required for analytics and trend analysis.</p>',
     N'<ul><li>Historical reporting structures are migrated.</li><li>Users can compare historical and current values.</li><li>Migration exceptions are documented.</li></ul>', 2, 4),
    (N'REQ-P4-005', N'CBMS-P4-ANALYTICS', N'Forecasting', N'ENHANCEMENT', N'Provide forecasting, scenario analysis, and trend monitoring', N'REPORTING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - Functional Deliverables - Analytics and Reporting Framework',
     N'<p>Implement forecasting, scenario analysis, and trend monitoring capability for fiscal, budget, and performance analytics.</p>',
     N'<ul><li>Users can run agreed forecasting or scenario outputs.</li><li>Trend dashboards show relevant indicators over time.</li><li>Assumptions and data source notes are visible.</li></ul>', 3, 0),
    (N'REQ-P4-006', N'CBMS-P4-ANALYTICS', N'Executive Reporting', N'ENHANCEMENT', N'Provide management, monitoring, policy, and financial analytics reports', N'REPORTING', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - Functional Deliverables - Executive and Operational Reporting',
     N'<p>Provide executive and operational reporting outputs including management dashboards, monitoring reports, policy analysis reporting, and financial analytics reporting.</p>',
     N'<ul><li>Agreed executive reports are available.</li><li>Operational monitoring reports can be generated.</li><li>Report users can validate outputs against source data.</li></ul>', 4, 1),
    (N'REQ-P4-007', N'CBMS-P4-ANALYTICS', N'Validation', N'TESTING', N'Complete dashboard, reporting, analytics, and UAT validation', N'NON_FUNCTIONAL', N'MUST', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - Testing and Validation Activities',
     N'<p>Complete dashboard testing, reporting validation, analytics validation, and UAT for Phase 4.</p>',
     N'<ul><li>Test scripts cover dashboards, reports, ETL, analytics, and forecasting.</li><li>Data reconciliation is completed for critical outputs.</li><li>UAT sign-off is recorded.</li></ul>', 5, 2),
    (N'REQ-P4-008', N'CBMS-P4-ANALYTICS', N'Training', N'TRAINING', N'Provide reporting and analytics training and knowledge transfer', N'TRAINING', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - Training and Knowledge Transfer',
     N'<p>Provide reporting training, dashboard training, support transition activities, and knowledge transfer sessions.</p>',
     N'<ul><li>Training materials cover analytics and reporting use cases.</li><li>Users can run key dashboards and reports after training.</li><li>Support transition tasks are documented.</li></ul>', 6, 3),
    (N'REQ-P4-009', N'CBMS-P4-ANALYTICS', N'Optimisation', N'SUPPORT', N'Complete final optimisation, stabilisation, and closure', N'NON_FUNCTIONAL', N'SHOULD', N'REVIEW', N'CBMS Upgrade and Budgetary Governance Implementation Plan', N'Phase 4 - Final Optimisation and Stabilisation',
     N'<p>Complete final refinements, performance tuning, stabilisation, closure reporting, and handover after analytics rollout.</p>',
     N'<ul><li>System refinements are tracked and completed or deferred.</li><li>Performance concerns are reviewed.</li><li>Closure artefacts and lessons learned are captured.</li></ul>', 7, 4),

    (N'REQ-XCUT-001', N'CBMS-P1-FISCAL', N'Low Code', N'ENHANCEMENT', N'Set up low-code and no-code capability', N'FUNCTIONAL', N'COULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - Low-Code / No-Code Platform',
     N'<p>Set up low-code or no-code capability for workflow templates and CBMS-linked reporting applications where appropriate.</p>',
     N'<ul><li>Candidate LCNC use cases are identified.</li><li>Workflow template capability is demonstrated.</li><li>Security and support boundaries are documented.</li></ul>', 8, 0),
    (N'REQ-XCUT-002', N'CBMS-P1-FISCAL', N'Workflow Templates', N'CONFIGURATION', N'Configure reusable workflow templates', N'FUNCTIONAL', N'COULD', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - Low-Code / No-Code Platform',
     N'<p>Configure reusable workflow templates that can support project, testing, training, reporting, or administrative process automation.</p>',
     N'<ul><li>At least one reusable workflow template is configured.</li><li>Template ownership and change control are documented.</li><li>Template can be linked to project tasks where useful.</li></ul>', 9, 1),
    (N'REQ-XCUT-003', N'CBMS-P0-GOV', N'Capacity Building', N'TRAINING', N'Provide IT, Budget Officer, SOE, and change management training', N'TRAINING', N'MUST', N'REVIEW', N'CBMS Phased Implementation Proposal', N'Phase 2 - Capacity Building',
     N'<p>Plan and deliver capacity building across IT technical users, Budget Officers, SOE users, and change management stakeholders.</p>',
     N'<ul><li>Training plan identifies audiences and modules.</li><li>Training attendance is captured.</li><li>Training feedback and follow-up support tasks are tracked.</li></ul>', 0, 2);

MERGE dbo.tblWorkflowRequirements AS target
USING (
    SELECT
        req.RequirementCode,
        project.WorkflowProjectID,
        req.ModuleCode,
        req.DeliveryClassCode,
        req.RequirementTitle,
        req.RequirementTypeCode,
        req.PriorityCode,
        req.RequirementStatusCode,
        req.SourceDocument,
        req.SourceSection,
        req.[Description],
        req.AcceptanceCriteria,
        COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((req.RequestedSlot % @UserCount) + 1)), @OwnerUserID) AS RequestedByUserID,
        COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((req.OwnerSlot % @UserCount) + 1)), @OwnerUserID) AS OwnerUserID
    FROM @Requirements req
    INNER JOIN dbo.tblWorkflowProjects project
        ON project.ProjectCode = req.ProjectCode
) AS source
    ON target.RequirementCode = source.RequirementCode
WHEN MATCHED THEN
    UPDATE SET
        WorkflowProjectID = source.WorkflowProjectID,
        ModuleCode = source.ModuleCode,
        DeliveryClassCode = source.DeliveryClassCode,
        RequirementTitle = source.RequirementTitle,
        RequirementTypeCode = source.RequirementTypeCode,
        PriorityCode = source.PriorityCode,
        RequirementStatusCode = source.RequirementStatusCode,
        SourceDocument = source.SourceDocument,
        SourceSection = source.SourceSection,
        [Description] = source.[Description],
        AcceptanceCriteria = source.AcceptanceCriteria,
        RequestedByUserID = source.RequestedByUserID,
        OwnerUserID = source.OwnerUserID,
        Active = 1,
        UpdatedAt = SYSUTCDATETIME(),
        UpdatedBy = @OwnerUserID
WHEN NOT MATCHED THEN
    INSERT
        (RequirementCode, WorkflowProjectID, ModuleCode, DeliveryClassCode, RequirementTitle,
         RequirementTypeCode, PriorityCode, RequirementStatusCode, SourceDocument, SourceSection,
         [Description], AcceptanceCriteria, RequestedByUserID, OwnerUserID, Active,
         CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (source.RequirementCode, source.WorkflowProjectID, source.ModuleCode, source.DeliveryClassCode, source.RequirementTitle,
         source.RequirementTypeCode, source.PriorityCode, source.RequirementStatusCode, source.SourceDocument, source.SourceSection,
         source.[Description], source.AcceptanceCriteria, source.RequestedByUserID, source.OwnerUserID, 1,
         SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblWorkflowEntityLinks AS target
    USING (
        SELECT
            r.WorkflowProjectID,
            r.WorkflowRequirementID,
            r.RequirementCode,
            r.RequirementTitle,
            r.SourceDocument,
            r.SourceSection
        FROM dbo.tblWorkflowRequirements r
        WHERE r.RequirementCode IN (SELECT RequirementCode FROM @Requirements)
    ) AS source
        ON target.LinkTypeCode = N'REQUIREMENT'
       AND target.LinkedEntity = N'WorkflowRequirement'
       AND target.LinkedEntityID = source.WorkflowRequirementID
       AND target.WorkflowTaskID IS NULL
    WHEN MATCHED THEN
        UPDATE SET
            WorkflowProjectID = source.WorkflowProjectID,
            LinkedEntityKey = source.RequirementCode,
            LinkedTitle = source.RequirementTitle,
            LinkedUrl = N'index.php?route=workflow-requirements/form&id=' + CONVERT(NVARCHAR(20), source.WorkflowRequirementID),
            Notes = @SeedTag + N': ' + COALESCE(source.SourceDocument, N'') + N' / ' + COALESCE(source.SourceSection, N''),
            Active = 1,
            UpdatedAt = SYSUTCDATETIME(),
            UpdatedBy = @OwnerUserID
    WHEN NOT MATCHED THEN
        INSERT
            (WorkflowProjectID, WorkflowTaskID, LinkTypeCode, LinkedEntity, LinkedEntityID,
             LinkedEntityKey, LinkedTitle, LinkedUrl, Notes, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
        VALUES
            (source.WorkflowProjectID, NULL, N'REQUIREMENT', N'WorkflowRequirement', source.WorkflowRequirementID,
             source.RequirementCode, source.RequirementTitle,
             N'index.php?route=workflow-requirements/form&id=' + CONVERT(NVARCHAR(20), source.WorkflowRequirementID),
             @SeedTag + N': ' + COALESCE(source.SourceDocument, N'') + N' / ' + COALESCE(source.SourceSection, N''),
             1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);
END;

COMMIT TRANSACTION;

SELECT
    COUNT(*) AS SeededProjectCount
FROM dbo.tblWorkflowProjects
WHERE ProjectCode IN (SELECT ProjectCode FROM @Projects);

SELECT
    COUNT(*) AS SeededRequirementCount,
    SUM(CASE WHEN DeliveryClassCode = N'UPGRADE' THEN 1 ELSE 0 END) AS UpgradeCount,
    SUM(CASE WHEN DeliveryClassCode = N'ENHANCEMENT' THEN 1 ELSE 0 END) AS EnhancementCount,
    SUM(CASE WHEN DeliveryClassCode = N'CONFIGURATION' THEN 1 ELSE 0 END) AS ConfigurationCount,
    SUM(CASE WHEN DeliveryClassCode = N'INTEGRATION' THEN 1 ELSE 0 END) AS IntegrationCount,
    SUM(CASE WHEN DeliveryClassCode = N'MIGRATION' THEN 1 ELSE 0 END) AS MigrationCount,
    SUM(CASE WHEN DeliveryClassCode = N'TESTING' THEN 1 ELSE 0 END) AS TestingCount,
    SUM(CASE WHEN DeliveryClassCode = N'TRAINING' THEN 1 ELSE 0 END) AS TrainingCount,
    SUM(CASE WHEN DeliveryClassCode = N'GOVERNANCE' THEN 1 ELSE 0 END) AS GovernanceCount,
    SUM(CASE WHEN DeliveryClassCode = N'SECURITY' THEN 1 ELSE 0 END) AS SecurityCount,
    SUM(CASE WHEN DeliveryClassCode = N'SUPPORT' THEN 1 ELSE 0 END) AS SupportCount
FROM dbo.tblWorkflowRequirements
WHERE RequirementCode IN (SELECT RequirementCode FROM @Requirements);
GO
