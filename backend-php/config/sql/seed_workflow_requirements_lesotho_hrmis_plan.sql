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

DECLARE @ProjectCode NVARCHAR(50) = N'LSO-CBMS-PLAN';
DECLARE @ProjectID INT = NULL;
DECLARE @OwnerUserID INT = NULL;
DECLARE @UserCount INT = 0;
DECLARE @SeedTag NVARCHAR(100) = N'WORKFLOW_REQUIREMENTS_LSO_HRMIS_PLAN_SEED';

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowRequirements is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowProjects is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblUsers', N'U') IS NULL
BEGIN
    RAISERROR(N'tblUsers is missing.', 16, 1);
    RETURN;
END;

DECLARE @Users TABLE
(
    RowNo INT IDENTITY(1,1) NOT NULL,
    UserID INT NOT NULL
);

IF COL_LENGTH(N'dbo.tblUsers', N'IsActive') IS NOT NULL
BEGIN
    INSERT INTO @Users (UserID)
    SELECT TOP (8) UserID
    FROM dbo.tblUsers
    WHERE IsActive = 1
    ORDER BY UserID ASC;
END
ELSE
BEGIN
    INSERT INTO @Users (UserID)
    SELECT TOP (8) UserID
    FROM dbo.tblUsers
    ORDER BY UserID ASC;
END;

IF NOT EXISTS (SELECT 1 FROM @Users)
BEGIN
    RAISERROR(N'No users are available for assigning seeded requirements.', 16, 1);
    RETURN;
END;

SELECT @UserCount = COUNT(*) FROM @Users;

SELECT TOP (1) @OwnerUserID = UserID
FROM @Users
ORDER BY CASE WHEN UserID = 2 THEN 0 ELSE 1 END, UserID ASC;

SELECT @ProjectID = WorkflowProjectID
FROM dbo.tblWorkflowProjects
WHERE ProjectCode = @ProjectCode;

IF @ProjectID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowProjects
        (ProjectCode, ProjectName, [Description], ProjectOwnerUserID, ProjectStatusCode,
         StartDate, TargetEndDate, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (@ProjectCode,
         N'CBMS Lesotho Upgrade and Budgetary Governance',
         N'Requirements seeded from the CBMS Lesotho revised final implementation plan with HRMIS interface support.',
         @OwnerUserID,
         N'ACTIVE',
         '2026-07-01',
         '2027-07-01',
         1,
         SYSUTCDATETIME(),
         @OwnerUserID,
         SYSUTCDATETIME(),
         @OwnerUserID);

    SET @ProjectID = CONVERT(INT, SCOPE_IDENTITY());
END
ELSE
BEGIN
    UPDATE dbo.tblWorkflowProjects
    SET ProjectName = N'CBMS Lesotho Upgrade and Budgetary Governance',
        [Description] = N'Requirements seeded from the CBMS Lesotho revised final implementation plan with HRMIS interface support.',
        ProjectOwnerUserID = COALESCE(ProjectOwnerUserID, @OwnerUserID),
        ProjectStatusCode = CASE WHEN ProjectStatusCode IN (N'COMPLETED', N'CANCELLED') THEN ProjectStatusCode ELSE N'ACTIVE' END,
        StartDate = COALESCE(StartDate, '2026-07-01'),
        TargetEndDate = COALESCE(TargetEndDate, '2027-07-01'),
        Active = 1,
        UpdatedAt = SYSUTCDATETIME(),
        UpdatedBy = @OwnerUserID
    WHERE WorkflowProjectID = @ProjectID;
END;

DECLARE @Source TABLE
(
    RequirementCode NVARCHAR(50) NOT NULL PRIMARY KEY,
    ModuleCode NVARCHAR(100) NULL,
    RequirementTitle NVARCHAR(255) NOT NULL,
    RequirementTypeCode NVARCHAR(30) NOT NULL,
    PriorityCode NVARCHAR(20) NOT NULL,
    RequirementStatusCode NVARCHAR(30) NOT NULL,
    [Description] NVARCHAR(MAX) NULL,
    AcceptanceCriteria NVARCHAR(MAX) NULL,
    OwnerSlot INT NOT NULL,
    RequestedSlot INT NOT NULL
);

INSERT INTO @Source
    (RequirementCode, ModuleCode, RequirementTitle, RequirementTypeCode, PriorityCode, RequirementStatusCode,
     [Description], AcceptanceCriteria, OwnerSlot, RequestedSlot)
VALUES
    (N'REQ-LSO-CBMS-001', N'Programme Governance', N'Separate upgrade deliverables from enhancement deliverables', N'FUNCTIONAL', N'MUST', N'APPROVED',
     N'<p>The programme must classify CBMS platform upgrade work separately from business enhancement work so governance, funding, acceptance and scope control are transparent.</p>',
     N'<ul><li>Each requirement can be classified as upgrade, enhancement or shared delivery control.</li><li>Acceptance evidence can be tracked against the classification.</li><li>The classification supports phase acceptance and milestone assessment.</li></ul>', 0, 1),
    (N'REQ-LSO-CBMS-002', N'Programme Governance', N'Maintain phased implementation roadmap from July 2026 to July 2027', N'FUNCTIONAL', N'MUST', N'APPROVED',
     N'<p>The implementation must follow the agreed roadmap across mobilisation and four delivery phases, with target go-live milestones on 5 October 2026, 16 November 2026, 1 April 2027 and 1 July 2027.</p>',
     N'<ul><li>Each phase has start dates, target go-live and primary outcomes recorded.</li><li>Phase dates are visible to project stakeholders.</li><li>Go-live dates can be used for project tracking and acceptance reporting.</li></ul>', 1, 2),
    (N'REQ-LSO-CBMS-003', N'Mobilisation', N'Establish inception report and governance framework', N'FUNCTIONAL', N'MUST', N'APPROVED',
     N'<p>The mobilisation phase must produce an approved inception report, stakeholder alignment outputs, roadmap, governance model and scope control approach.</p>',
     N'<ul><li>Inception report is completed and accepted.</li><li>Stakeholder workshop outcomes and priorities are recorded.</li><li>Governance structure, reporting cadence and decision model are confirmed.</li></ul>', 2, 3),
    (N'REQ-LSO-CBMS-004', N'Mobilisation', N'Validate requirements against budgeting and integration needs', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>The project must validate requirements covering budgeting, fiduciary governance, analytics, reporting and integration needs before configuration begins.</p>',
     N'<ul><li>Requirements validation notes are captured.</li><li>Enhancement and upgrade deliverable matrix is approved.</li><li>Phase acceptance criteria are agreed before implementation work starts.</li></ul>', 3, 4),
    (N'REQ-LSO-CBMS-005', N'Platform Upgrade', N'Deploy latest CBMS platform environment', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>The implementation must deploy the latest CBMS platform environment to reduce legacy platform risk and provide modern module capability.</p>',
     N'<ul><li>Target CBMS environment is installed and available.</li><li>Environment readiness evidence is captured.</li><li>Security and access arrangements are available for project users.</li></ul>', 4, 5),
    (N'REQ-LSO-CBMS-006', N'Platform Upgrade', N'Migrate workflows, configuration and data structures', N'DATA', N'MUST', N'REVIEW',
     N'<p>CBMS workflows, configuration, fiscal planning structures and operational data must be migrated and validated as part of the upgrade work.</p>',
     N'<ul><li>Migration approach is documented.</li><li>Workflow and configuration migration is validated.</li><li>Data reconciliation evidence is available before go-live.</li></ul>', 5, 0),
    (N'REQ-LSO-CBMS-007', N'Security', N'Upgrade security, governance and workflow framework', N'SECURITY', N'MUST', N'REVIEW',
     N'<p>The upgraded CBMS environment must support roles, permissions, approval structures, audit framework and data governance controls.</p>',
     N'<ul><li>User roles and permissions are configured.</li><li>Approval structures and workflow controls are validated.</li><li>Audit and data ownership controls are enabled.</li></ul>', 6, 1),
    (N'REQ-LSO-CBMS-008', N'Budget Strategy', N'Configure MTFF and MTEF alignment', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>CBMS must support MTFF and MTEF alignment for fiscal planning and strategic budgeting processes.</p>',
     N'<ul><li>MTFF functionality is configured.</li><li>MTEF process alignment is demonstrated.</li><li>Budget Department users accept the configured process through UAT.</li></ul>', 7, 2),
    (N'REQ-LSO-CBMS-009', N'Budget Strategy', N'Capture and manage macroeconomic assumptions', N'FUNCTIONAL', N'SHOULD', N'REVIEW',
     N'<p>Fiscal planning users must be able to capture macroeconomic assumptions used for strategic budgeting and medium-term planning.</p>',
     N'<ul><li>Macroeconomic assumption fields are configured.</li><li>Assumptions can be maintained by authorised users.</li><li>Assumptions flow into fiscal planning outputs where applicable.</li></ul>', 0, 3),
    (N'REQ-LSO-CBMS-010', N'Resource Ceilings', N'Configure resource ceiling allocation and scenario modelling', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>CBMS must provide fiscal ceiling allocation, scenario modelling, versioning, resource allocation modelling and ceiling reporting.</p>',
     N'<ul><li>Ceiling calculator is configured.</li><li>Scenarios and versions can be created and compared.</li><li>Ceiling management reports are validated by stakeholders.</li></ul>', 1, 4),
    (N'REQ-LSO-CBMS-011', N'Manpower Budgeting', N'Upgrade manpower budgeting functionality', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>The latest CBMS manpower budgeting functionality must be enabled and aligned to fiscal planning and HRMIS/payroll interface needs.</p>',
     N'<ul><li>Manpower budgeting module is upgraded or configured.</li><li>Budget projections can be maintained.</li><li>Manpower budget outputs can support HRMIS interface testing.</li></ul>', 2, 5),
    (N'REQ-LSO-CBMS-012', N'HRMIS Interface', N'Review HRMIS-CBMS interface requirements', N'INTEGRATION', N'MUST', N'REVIEW',
     N'<p>The project must review and align the existing HRMIS-CBMS interface requirements from the CBMS side, with HRMIS-side implementation remaining with the HRMIS technical team.</p>',
     N'<ul><li>CBMS-side interface requirements are documented.</li><li>Scope boundary between CBMS and HRMIS responsibilities is clear.</li><li>Interface assumptions and dependencies are recorded.</li></ul>', 3, 0),
    (N'REQ-LSO-CBMS-013', N'HRMIS Interface', N'Coordinate two-way HRMIS-CBMS data exchange', N'INTEGRATION', N'MUST', N'REVIEW',
     N'<p>CBMS must support two-way data exchange coordination through CBMS-side configuration, data mapping, API coordination and testing support.</p>',
     N'<ul><li>CBMS-side mappings are defined.</li><li>API or data exchange coordination points are documented.</li><li>Test exchange results are captured with HRMIS stakeholders.</li></ul>', 4, 1),
    (N'REQ-LSO-CBMS-014', N'HRMIS Interface', N'Exchange approved manpower budget and wage ceiling data', N'INTEGRATION', N'MUST', N'REVIEW',
     N'<p>CBMS must support exchange of approved manpower budget data, annual and monthly wage bill ceilings, budget classification breakdowns, budget version numbers and approval dates.</p>',
     N'<ul><li>Required data fields are identified and mapped.</li><li>Approved budget data can be exported or made available from CBMS.</li><li>Classification, version and approval date fields are included in validation outputs.</li></ul>', 5, 2),
    (N'REQ-LSO-CBMS-015', N'HRMIS Interface', N'Support payroll projection and retirement planning synchronisation', N'INTEGRATION', N'SHOULD', N'REVIEW',
     N'<p>CBMS-side data exchange must support fields required for payroll projections, manpower budgeting and retirement planning analysis.</p>',
     N'<ul><li>Projection and retirement planning data fields are documented.</li><li>CBMS outputs support payroll synchronisation testing.</li><li>Exceptions and reconciliation issues can be tracked.</li></ul>', 6, 3),
    (N'REQ-LSO-CBMS-016', N'HRMIS Interface', N'Provide HRMIS reconciliation reports and validation outputs', N'REPORTING', N'MUST', N'REVIEW',
     N'<p>CBMS must provide reports or validation outputs to compare approved budget data with payroll projections and actuals.</p>',
     N'<ul><li>Validation reports are configured or developed.</li><li>Reports compare budget, payroll projection and actual values.</li><li>Reconciliation outputs are accepted by relevant stakeholders.</li></ul>', 7, 4),
    (N'REQ-LSO-CBMS-017', N'Budget Formulation', N'Upgrade budget preparation and submission functionality', N'FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>Budget preparation and submission functionality must be upgraded for Phase 2 to support ministry budget formulation.</p>',
     N'<ul><li>Budget preparation functionality is available in the upgraded environment.</li><li>Ministry submission workflow structures are migrated or configured.</li><li>Budget formulation UAT is completed before Phase 2 go-live.</li></ul>', 0, 5),
    (N'REQ-LSO-CBMS-018', N'Budget Formulation', N'Configure ministry submission workflows and approval routing', N'FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>CBMS must support ministry budget submission workflows, approval routing, workflow controls, tracking and monitoring.</p>',
     N'<ul><li>Submission workflow steps are configured.</li><li>Approval routing is validated with stakeholders.</li><li>Submission status can be tracked and reported.</li></ul>', 1, 0),
    (N'REQ-LSO-CBMS-019', N'Subvention Reporting', N'Enable SOE and autonomous entity subvention reporting', N'REPORTING', N'SHOULD', N'DRAFT',
     N'<p>The solution must provide subvention tracking and reporting for SOEs and autonomous spending entities.</p>',
     N'<ul><li>Subvention reporting structures are configured.</li><li>Summary monitoring reports are available.</li><li>Report outputs are validated during Phase 2 UAT.</li></ul>', 2, 1),
    (N'REQ-LSO-CBMS-020', N'Reporting', N'Support Excel integration and configurable reporting templates', N'REPORTING', N'SHOULD', N'DRAFT',
     N'<p>Budget formulation and subvention reporting must support Excel integration, reporting templates and configurable reporting outputs.</p>',
     N'<ul><li>Required templates are identified.</li><li>Excel-based reporting or integration outputs are validated.</li><li>Reports can be generated by authorised users.</li></ul>', 3, 2),
    (N'REQ-LSO-CBMS-021', N'Budget Execution', N'Upgrade budget execution and operational controls', N'FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>Operational budget execution functionality, workflows, controls and production configuration must be upgraded for Phase 3.</p>',
     N'<ul><li>Budget execution module is upgraded or configured.</li><li>Operational workflows and controls are validated.</li><li>Production readiness evidence is captured before 1 April 2027 go-live.</li></ul>', 4, 3),
    (N'REQ-LSO-CBMS-022', N'Virement Workflow', N'Configure virement initiation, approvals and compliance monitoring', N'FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>CBMS must support ministry-level virement initiation, workflow approvals, routing, compliance monitoring, threshold reporting and printable submission documents.</p>',
     N'<ul><li>Virement initiation workflow is configured.</li><li>Approval routing and compliance checks are validated.</li><li>Printable reports and threshold monitoring outputs are available.</li></ul>', 5, 4),
    (N'REQ-LSO-CBMS-023', N'Operational Support', N'Prepare technical handover and local support framework', N'TRAINING', N'MUST', N'DRAFT',
     N'<p>The Ministry IT support team must receive technical training, support procedures, escalation procedures and handover documentation.</p>',
     N'<ul><li>Technical training is completed.</li><li>First and second-level support procedures are documented.</li><li>Escalation and warranty support arrangements are agreed.</li></ul>', 6, 5),
    (N'REQ-LSO-CBMS-024', N'Documentation', N'Complete technical, user and operational documentation', N'NON_FUNCTIONAL', N'SHOULD', N'DRAFT',
     N'<p>Technical, user and operational documentation must be prepared and updated before operational transition.</p>',
     N'<ul><li>User documentation is available for implemented modules.</li><li>Technical and operational procedures are documented.</li><li>Documentation is reviewed during phase acceptance.</li></ul>', 7, 0),
    (N'REQ-LSO-CBMS-025', N'Analytics', N'Upgrade analytics, dashboards and reporting framework', N'REPORTING', N'MUST', N'DRAFT',
     N'<p>Enterprise analytics, dashboards, forecasting and reporting framework must be upgraded or implemented during Phase 4.</p>',
     N'<ul><li>Dashboard and reporting framework is available.</li><li>Analytics and forecasting functionality is validated.</li><li>Reporting users complete UAT before final go-live.</li></ul>', 0, 1),
    (N'REQ-LSO-CBMS-026', N'Analytics', N'Configure data warehouse and ETL processes', N'DATA', N'SHOULD', N'DRAFT',
     N'<p>The solution must support data integration processes, historical reporting structures, cross-departmental reporting and performance monitoring.</p>',
     N'<ul><li>ETL configuration is documented.</li><li>Historical and cross-department reporting structures are validated.</li><li>Performance monitoring is available for reporting processes.</li></ul>', 1, 2),
    (N'REQ-LSO-CBMS-027', N'Analytics', N'Provide executive, operational and policy analysis reporting', N'REPORTING', N'SHOULD', N'DRAFT',
     N'<p>CBMS must provide management dashboards, monitoring reports, policy analysis reporting and financial analytics reporting.</p>',
     N'<ul><li>Executive dashboard requirements are validated.</li><li>Operational monitoring reports are available.</li><li>Policy and financial analytics outputs are accepted by stakeholders.</li></ul>', 2, 3),
    (N'REQ-LSO-CBMS-028', N'Testing', N'Apply formal phase acceptance gates', N'FUNCTIONAL', N'MUST', N'APPROVED',
     N'<p>The programme must use formal acceptance gates for requirements confirmation, configuration and migration completion, testing, training readiness, go-live and phase acceptance.</p>',
     N'<ul><li>Each phase has documented acceptance evidence.</li><li>Gate evidence includes requirements notes, configuration logs, test results, training records and go-live approvals.</li><li>Phase cannot close without required sign-off.</li></ul>', 3, 4),
    (N'REQ-LSO-CBMS-029', N'Testing', N'Complete integration testing, UAT and QAT where applicable', N'FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>Each phase must complete integration testing, user acceptance testing and quality assurance testing where applicable.</p>',
     N'<ul><li>Test scripts and results are captured.</li><li>Defect logs are maintained and resolved or accepted.</li><li>Stakeholder sign-off is recorded before go-live.</li></ul>', 4, 5),
    (N'REQ-LSO-CBMS-030', N'Training', N'Deliver user and technical training by phase', N'TRAINING', N'MUST', N'DRAFT',
     N'<p>Training must be delivered to Budget Department users, pilot ministries, reporting users and technical support teams according to phase needs.</p>',
     N'<ul><li>Training materials are prepared.</li><li>Attendance records are captured.</li><li>Training completion supports transition readiness.</li></ul>', 5, 0),
    (N'REQ-LSO-CBMS-031', N'Dependencies', N'Track stakeholder availability and sign-off dependency', N'NON_FUNCTIONAL', N'SHOULD', N'DRAFT',
     N'<p>The project must track Ministry business owner, ICT team and nominated user availability for workshops, testing, training and sign-off.</p>',
     N'<ul><li>Stakeholder responsibilities are recorded.</li><li>Availability risks are logged and escalated.</li><li>Delays to UAT, training or sign-off are visible in project governance.</li></ul>', 6, 1),
    (N'REQ-LSO-CBMS-032', N'Dependencies', N'Track environment, access and infrastructure readiness', N'NON_FUNCTIONAL', N'MUST', N'DRAFT',
     N'<p>Required CBMS environments, infrastructure, access and security arrangements must be available in line with the project plan.</p>',
     N'<ul><li>Environment readiness is tracked.</li><li>Access requirements are confirmed.</li><li>Infrastructure blockers are escalated through governance.</li></ul>', 7, 2),
    (N'REQ-LSO-CBMS-033', N'Dependencies', N'Track data availability, quality and reconciliation risks', N'DATA', N'MUST', N'DRAFT',
     N'<p>Fiscal planning, budget, payroll, HRMIS, subvention and historical reporting data must be available and reconcilable.</p>',
     N'<ul><li>Required data sources are listed.</li><li>Data quality and cleansing issues are tracked.</li><li>Reconciliation evidence is produced before relevant go-live gates.</li></ul>', 0, 3),
    (N'REQ-LSO-CBMS-034', N'Dependencies', N'Manage ERP and HRMIS external workstream dependencies', N'INTEGRATION', N'SHOULD', N'DRAFT',
     N'<p>ERP upgrade dependencies, HRMIS-side Oracle configuration, HRMIS customisation and payroll business-rule changes must be monitored through governance because they are external to CBMS-side delivery.</p>',
     N'<ul><li>External dependency owners are identified.</li><li>API, specification and test data dependencies are recorded.</li><li>Integration risks are escalated when external readiness affects CBMS testing.</li></ul>', 1, 4),
    (N'REQ-LSO-CBMS-035', N'Commercial', N'Track milestone acceptance evidence for payment gates', N'FUNCTIONAL', N'SHOULD', N'APPROVED',
     N'<p>The programme must align milestone payment assessment to accepted phase outputs, including inception, Phase 1, Phase 2, Phase 3 and Phase 4 final acceptance.</p>',
     N'<ul><li>Payment milestones can be linked to phase acceptance evidence.</li><li>Phase acceptance certificates and go-live approvals are retained.</li><li>Milestone reporting supports transparent commercial governance.</li></ul>', 2, 5);

BEGIN TRANSACTION;

MERGE dbo.tblWorkflowRequirements AS target
USING @Source AS source
    ON target.RequirementCode = source.RequirementCode
WHEN MATCHED THEN
    UPDATE SET
        WorkflowProjectID = @ProjectID,
        ModuleCode = source.ModuleCode,
        RequirementTitle = source.RequirementTitle,
        RequirementTypeCode = source.RequirementTypeCode,
        PriorityCode = source.PriorityCode,
        RequirementStatusCode = source.RequirementStatusCode,
        [Description] = source.[Description],
        AcceptanceCriteria = source.AcceptanceCriteria,
        RequestedByUserID = COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.RequestedSlot % @UserCount) + 1)), @OwnerUserID),
        OwnerUserID = COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.OwnerSlot % @UserCount) + 1)), @OwnerUserID),
        ApprovedByUserID = CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN @OwnerUserID ELSE NULL END,
        ApprovedAt = CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN SYSUTCDATETIME() ELSE NULL END,
        Active = 1,
        UpdatedAt = SYSUTCDATETIME(),
        UpdatedBy = @OwnerUserID
WHEN NOT MATCHED THEN
    INSERT
        (RequirementCode, WorkflowProjectID, ModuleCode, RequirementTitle, RequirementTypeCode,
         PriorityCode, RequirementStatusCode, [Description], AcceptanceCriteria,
         RequestedByUserID, OwnerUserID, ApprovedByUserID, ApprovedAt, Active,
         CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (source.RequirementCode, @ProjectID, source.ModuleCode, source.RequirementTitle, source.RequirementTypeCode,
         source.PriorityCode, source.RequirementStatusCode, source.[Description], source.AcceptanceCriteria,
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.RequestedSlot % @UserCount) + 1)), @OwnerUserID),
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.OwnerSlot % @UserCount) + 1)), @OwnerUserID),
         CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN @OwnerUserID ELSE NULL END,
         CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN SYSUTCDATETIME() ELSE NULL END,
         1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
BEGIN
    DELETE l
    FROM dbo.tblWorkflowEntityLinks l
    WHERE l.LinkTypeCode = N'REQUIREMENT'
      AND l.LinkedEntity = N'WorkflowRequirement'
      AND l.WorkflowTaskID IS NULL
      AND l.Notes = @SeedTag
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.tblWorkflowRequirements r
          INNER JOIN @Source source
              ON source.RequirementCode = r.RequirementCode
          WHERE r.WorkflowRequirementID = l.LinkedEntityID
      );

    MERGE dbo.tblWorkflowEntityLinks AS target
    USING (
        SELECT
            r.WorkflowRequirementID,
            r.RequirementCode,
            r.RequirementTitle
        FROM dbo.tblWorkflowRequirements r
        INNER JOIN @Source source
            ON source.RequirementCode = r.RequirementCode
    ) AS source
        ON target.LinkTypeCode = N'REQUIREMENT'
       AND target.LinkedEntity = N'WorkflowRequirement'
       AND target.LinkedEntityID = source.WorkflowRequirementID
       AND target.WorkflowTaskID IS NULL
    WHEN MATCHED THEN
        UPDATE SET
            WorkflowProjectID = @ProjectID,
            LinkedEntityKey = source.RequirementCode,
            LinkedTitle = source.RequirementTitle,
            LinkedUrl = N'index.php?route=workflow-requirements/form&id=' + CONVERT(NVARCHAR(20), source.WorkflowRequirementID),
            Notes = @SeedTag,
            Active = 1,
            UpdatedAt = SYSUTCDATETIME(),
            UpdatedBy = @OwnerUserID
    WHEN NOT MATCHED THEN
        INSERT
            (WorkflowProjectID, WorkflowTaskID, LinkTypeCode, LinkedEntity, LinkedEntityID,
             LinkedEntityKey, LinkedTitle, LinkedUrl, Notes, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
        VALUES
            (@ProjectID, NULL, N'REQUIREMENT', N'WorkflowRequirement', source.WorkflowRequirementID,
             source.RequirementCode, source.RequirementTitle,
             N'index.php?route=workflow-requirements/form&id=' + CONVERT(NVARCHAR(20), source.WorkflowRequirementID),
             @SeedTag, 1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);
END;

COMMIT TRANSACTION;

SELECT
    @ProjectID AS ProjectID,
    COUNT(*) AS SeededRequirementCount,
    SUM(CASE WHEN RequirementStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedCount,
    SUM(CASE WHEN RequirementStatusCode = N'REVIEW' THEN 1 ELSE 0 END) AS ReviewCount,
    SUM(CASE WHEN RequirementStatusCode = N'DRAFT' THEN 1 ELSE 0 END) AS DraftCount
FROM dbo.tblWorkflowRequirements
WHERE RequirementCode LIKE N'REQ-LSO-CBMS-%';
GO
