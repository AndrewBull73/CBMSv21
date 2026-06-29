USE [CBMSv2];
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

DECLARE @RequestedProjectID INT = 1;
DECLARE @ProjectID INT = NULL;
DECLARE @OwnerUserID INT = NULL;
DECLARE @UserCount INT = 0;
DECLARE @SeedTag NVARCHAR(100) = N'WORKFLOW_REQUIREMENTS_DEMO_SEED';
DECLARE @ProjectTaskSeedTag NVARCHAR(100) = N'WORKFLOW_PROJECT_DEMO_SEED';
DECLARE @ProjectTaskSeedKey NVARCHAR(100) = N'PROJECT_SUMMARY_GANTT_DEMO';
DECLARE @ResetExistingDemoData BIT = 1;
DECLARE @RemoveAllProjectTasks BIT = 1;
DECLARE @ReloadAsParentRequirements BIT = 0;
DECLARE @DeletedTaskCount INT = 0;
DECLARE @DeletedRequirementCount INT = 0;
DECLARE @DeletedLinkCount INT = 0;

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowRequirements is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF COL_LENGTH(N'dbo.tblWorkflowRequirements', N'ParentRequirementID') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowRequirements', N'RequirementLevelCode') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowRequirements hierarchy columns are missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
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
    RAISERROR(N'No users are available for assigning demo requirements.', 16, 1);
    RETURN;
END;

SELECT @UserCount = COUNT(*) FROM @Users;

SELECT TOP (1) @OwnerUserID = UserID
FROM @Users
ORDER BY CASE WHEN UserID = 2 THEN 0 ELSE 1 END, UserID ASC;

SELECT @ProjectID = WorkflowProjectID
FROM dbo.tblWorkflowProjects
WHERE WorkflowProjectID = @RequestedProjectID;

IF @ProjectID IS NULL
BEGIN
    SELECT TOP (1) @ProjectID = WorkflowProjectID
    FROM dbo.tblWorkflowProjects
    ORDER BY WorkflowProjectID ASC;
END;

IF @ProjectID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowProjects
        (ProjectCode, ProjectName, [Description], ProjectOwnerUserID, ProjectStatusCode,
         StartDate, TargetEndDate, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (N'DEMO-REQ', N'Demo Requirements Project', N'Demo project for reviewing requirement records and summary screens.',
         @OwnerUserID, N'ACTIVE', '2026-06-01', '2026-08-31', 1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

    SET @ProjectID = CONVERT(INT, SCOPE_IDENTITY());
END;

DECLARE @Source TABLE
(
    RequirementCode NVARCHAR(50) NOT NULL PRIMARY KEY,
    ModuleCode NVARCHAR(100) NULL,
    RequirementTitle NVARCHAR(255) NOT NULL,
    RequirementLevelCode NVARCHAR(30) NOT NULL DEFAULT (N'HIGH_LEVEL'),
    ParentRequirementCode NVARCHAR(50) NULL,
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
    (N'REQ-DEMO-001', N'Projects', N'Create project containers for related tasks', N'FUNCTIONAL', N'MUST', N'COMPLETED',
     N'<p>Users need to group workflow tasks into a project so development, training, testing, and release work can be managed together.</p><ul><li>Projects have owner, dates, status, and active flag.</li><li>Project tasks appear in the project summary and Gantt chart.</li></ul>',
     N'<ul><li>A user can create and edit a project.</li><li>A project can show all active project tasks.</li><li>Project tasks remain linked to the selected project.</li></ul>', 0, 1),
    (N'REQ-DEMO-002', N'Projects', N'Assign users directly to projects', N'FUNCTIONAL', N'MUST', N'COMPLETED',
     N'<p>Project membership should not rely only on global workflow groups because project teams can be different for each project.</p>',
     N'<ul><li>Project users can be added with a role.</li><li>Duplicate project user assignments are prevented.</li><li>Large user lists can be searched or filtered in the UI.</li></ul>', 1, 2),
    (N'REQ-DEMO-003', N'Tasks', N'Force project tasks to use Project Task type', N'FUNCTIONAL', N'MUST', N'COMPLETED',
     N'<p>Tasks created from the project screen must always use the Project Task workflow type.</p>',
     N'<ul><li>The type is automatically selected.</li><li>The user cannot change the project task type to another type.</li><li>The Project Task type is installed if missing.</li></ul>', 2, 0),
    (N'REQ-DEMO-004', N'Tasks', N'Project task dates must fit inside project dates', N'FUNCTIONAL', N'MUST', N'IN_TEST',
     N'<p>Project task start and end dates need to remain inside the project planning window so the Gantt chart and summary remain reliable.</p>',
     N'<ul><li>Task planned start cannot be before project start.</li><li>Task planned end cannot be after project target end.</li><li>Clear validation messages appear before save.</li></ul>', 3, 1),
    (N'REQ-DEMO-005', N'Tasks', N'Capture utilisation percentage for project tasks', N'FUNCTIONAL', N'SHOULD', N'IN_BUILD',
     N'<p>Project tasks should capture utilisation percentage so workload can be reviewed by project and person.</p>',
     N'<ul><li>Utilisation accepts values from 0 to 100.</li><li>Summary screens can show utilisation values.</li><li>Invalid percentages are rejected.</li></ul>', 4, 2),
    (N'REQ-DEMO-006', N'Planning', N'Show simple Gantt chart for project tasks', N'REPORTING', N'MUST', N'IN_TEST',
     N'<p>The project screen needs a simple visual timeline of project tasks, including parent phases and progress.</p>',
     N'<ul><li>Project tasks render against their planned dates.</li><li>Percent complete is visible.</li><li>Tasks without dates do not break the chart.</li></ul>', 0, 3),
    (N'REQ-DEMO-007', N'Planning', N'Show project summary dashboard', N'REPORTING', N'MUST', N'APPROVED',
     N'<p>Project managers need a one-screen overview showing task progress, outstanding work, linked requirements, and risks.</p>',
     N'<ul><li>Summary includes counts by status.</li><li>Summary includes overdue and upcoming task signals.</li><li>Linked requirements appear in project context.</li></ul>', 1, 4),
    (N'REQ-DEMO-008', N'Requirements', N'Capture structured requirements', N'FUNCTIONAL', N'MUST', N'IN_BUILD',
     N'<p>Developers need a requirements form that captures purpose, owner, priority, status, and acceptance criteria.</p>',
     N'<ul><li>Requirement title is mandatory.</li><li>Rich text can be used for description and acceptance criteria.</li><li>Requirements can be linked to a workflow project.</li></ul>', 2, 5),
    (N'REQ-DEMO-009', N'Requirements', N'Show requirements summary dashboard', N'REPORTING', N'SHOULD', N'REVIEW',
     N'<p>The requirements module needs a summary screen to highlight coverage, ownership gaps, and approval status.</p>',
     N'<ul><li>Summary shows totals by status, type, priority, and project.</li><li>Missing owner and missing acceptance criteria are counted.</li><li>Recent requirements are visible.</li></ul>', 3, 0),
    (N'REQ-DEMO-010', N'Requirements', N'Attach supporting files to requirements', N'FUNCTIONAL', N'SHOULD', N'REVIEW',
     N'<p>Requirements often need supporting documents such as screenshots, specification notes, spreadsheets, and meeting outcomes.</p>',
     N'<ul><li>Users can upload allowed file types.</li><li>Attachments can be downloaded from the requirement form.</li><li>Uploaded files are retained under controlled storage.</li></ul>', 4, 1),
    (N'REQ-DEMO-011', N'Training', N'Link training scenarios to projects and tasks', N'TRAINING', N'SHOULD', N'DRAFT',
     N'<p>Training work should connect to the project plan so training build, validation, and rollout tasks are visible alongside development work.</p>',
     N'<ul><li>Training scenarios can be linked to project work.</li><li>Training tasks can be tracked in the same workflow register.</li><li>Project summary shows training-related linked work.</li></ul>', 5, 2),
    (N'REQ-DEMO-012', N'Testing', N'Link testing scenarios and results to project tasks', N'FUNCTIONAL', N'SHOULD', N'DRAFT',
     N'<p>Testing outcomes need traceability to the project tasks and requirements they validate.</p>',
     N'<ul><li>Testing scenarios can be linked to requirements.</li><li>Failed tests can become workflow tasks or defects.</li><li>Project summary can include testing status.</li></ul>', 6, 3),
    (N'REQ-DEMO-013', N'Notifications', N'Send workflow reminders and escalations', N'FUNCTIONAL', N'COULD', N'APPROVED',
     N'<p>Task owners need reminder emails before due dates and escalation emails when tasks become overdue.</p>',
     N'<ul><li>Reminder jobs can process enabled tasks.</li><li>Overdue escalations respect task configuration.</li><li>Email activity is recorded for traceability.</li></ul>', 7, 4),
    (N'REQ-DEMO-014', N'Documentation', N'Maintain module documentation in the platform', N'NON_FUNCTIONAL', N'COULD', N'DRAFT',
     N'<p>The platform should reduce external documentation drift by capturing module notes, decisions, and requirements in one place.</p>',
     N'<ul><li>Documentation can be linked to projects and requirements.</li><li>Documents can support multiple languages over time.</li><li>Attachments can hold supporting evidence where needed.</li></ul>', 0, 5),
    (N'REQ-DEMO-015', N'Security', N'Restrict project management features by permission', N'SECURITY', N'MUST', N'APPROVED',
     N'<p>Project and requirement maintenance must respect workflow operation permissions.</p>',
     N'<ul><li>Users with view permission can read registers and summaries.</li><li>Only edit/admin users can maintain records.</li><li>Administrative actions remain restricted.</li></ul>', 1, 0),
    (N'REQ-DEMO-016', N'Internationalisation', N'Keep workflow screens translation ready', N'NON_FUNCTIONAL', N'SHOULD', N'REVIEW',
     N'<p>Workflow, project, and requirement screens should use translation keys so modules can be finalised in multiple languages.</p>',
     N'<ul><li>New labels use language keys.</li><li>English and French files include matching keys.</li><li>Module-by-module review can complete translations later.</li></ul>', 2, 1),
    (N'REQ-DEMO-017', N'RAD Platform', N'Use projects as delivery streams', N'FUNCTIONAL', N'MUST', N'APPROVED',
     N'<p>The application should support Rapid Application Development by letting each delivery stream manage requirements, tasks, testing, and training in one workspace.</p>',
     N'<ul><li>Projects can represent development, testing, training, release, or support streams.</li><li>Each stream can have its own tasks and users.</li><li>Summary screens show delivery status quickly.</li></ul>', 3, 2),
    (N'REQ-DEMO-018', N'RAD Platform', N'Trace requirements through build and validation', N'FUNCTIONAL', N'SHOULD', N'DRAFT',
     N'<p>Requirements should be traceable through project tasks, testing, training, and release decisions.</p>',
     N'<ul><li>Each detailed requirement can show linked build tasks.</li><li>Validation and training links are visible from the traceability matrix.</li><li>Uncovered requirements are highlighted before release decisions.</li></ul>', 4, 3),
    (N'REQ-DEMO-019', N'Budget Ceiling', N'Provide budget ceiling calculator for planning decisions', N'FUNCTIONAL', N'MUST', N'REVIEW',
     N'<p>Budget officers need a calculator that can derive planning ceilings from baseline allocation, approved adjustments, policy limits, and scenario assumptions.</p><ul><li>The calculator supports transparent planning decisions before budget ceilings are communicated.</li><li>Assumptions should be retained with the calculation for audit and review.</li></ul>',
     N'<ul><li>A user can enter baseline amount, adjustment values, and ceiling rules.</li><li>The calculated ceiling is shown with the inputs and assumptions used.</li><li>Invalid or incomplete calculator inputs are rejected with clear messages.</li></ul>', 5, 4),
    (N'REQ-DEMO-020', N'Budget Ceiling', N'Capture detailed ceiling calculator inputs and outputs', N'FUNCTIONAL', N'SHOULD', N'DRAFT',
     N'<p>The detailed calculator requirement should capture the specific inputs, validation rules, and output values required to support a budget ceiling calculation.</p>',
     N'<ul><li>Input fields capture baseline allocation, recurring adjustments, one-off adjustments, caps, and notes.</li><li>Calculated output includes proposed ceiling, variance from baseline, and calculation timestamp.</li><li>The calculation can be linked to the related project requirement and workflow tasks.</li></ul>', 6, 5);

IF @ReloadAsParentRequirements = 0
BEGIN
    UPDATE @Source
    SET RequirementLevelCode = N'DETAILED',
        ParentRequirementCode = CASE
            WHEN RequirementCode IN (N'REQ-DEMO-002', N'REQ-DEMO-003', N'REQ-DEMO-004', N'REQ-DEMO-005', N'REQ-DEMO-006', N'REQ-DEMO-007') THEN N'REQ-DEMO-001'
            WHEN RequirementCode IN (N'REQ-DEMO-009', N'REQ-DEMO-010', N'REQ-DEMO-018') THEN N'REQ-DEMO-008'
            WHEN RequirementCode IN (N'REQ-DEMO-011', N'REQ-DEMO-012', N'REQ-DEMO-013', N'REQ-DEMO-014', N'REQ-DEMO-015', N'REQ-DEMO-016') THEN N'REQ-DEMO-017'
            WHEN RequirementCode = N'REQ-DEMO-020' THEN N'REQ-DEMO-019'
            ELSE ParentRequirementCode
        END
    WHERE RequirementCode IN
        (N'REQ-DEMO-002', N'REQ-DEMO-003', N'REQ-DEMO-004', N'REQ-DEMO-005', N'REQ-DEMO-006', N'REQ-DEMO-007',
         N'REQ-DEMO-009', N'REQ-DEMO-010', N'REQ-DEMO-011', N'REQ-DEMO-012', N'REQ-DEMO-013', N'REQ-DEMO-014',
         N'REQ-DEMO-015', N'REQ-DEMO-016', N'REQ-DEMO-018', N'REQ-DEMO-020');
END;

BEGIN TRANSACTION;

IF @ResetExistingDemoData = 1
BEGIN
    DECLARE @ExistingDemoRequirements TABLE
    (
        WorkflowRequirementID INT NOT NULL PRIMARY KEY
    );

    DECLARE @ExistingDemoTasks TABLE
    (
        WorkflowTaskID INT NOT NULL PRIMARY KEY
    );

    INSERT INTO @ExistingDemoRequirements (WorkflowRequirementID)
    SELECT WorkflowRequirementID
    FROM dbo.tblWorkflowRequirements
    WHERE RequirementCode LIKE N'REQ-DEMO-%';

    IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
       AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
    BEGIN
        INSERT INTO @ExistingDemoTasks (WorkflowTaskID)
        SELECT DISTINCT l.WorkflowTaskID
        FROM dbo.tblWorkflowEntityLinks l
        INNER JOIN @ExistingDemoRequirements r
            ON r.WorkflowRequirementID = l.LinkedEntityID
        WHERE l.WorkflowTaskID IS NOT NULL
          AND l.LinkedEntity = N'WorkflowRequirement'
          AND l.LinkedEntityID IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM @ExistingDemoTasks existing
              WHERE existing.WorkflowTaskID = l.WorkflowTaskID
          );
    END;

    IF @RemoveAllProjectTasks = 1
       AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
       AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
    BEGIN
        INSERT INTO @ExistingDemoTasks (WorkflowTaskID)
        SELECT t.WorkflowTaskID
        FROM dbo.tblWorkflowTasks t
        WHERE t.WorkflowProjectID = @ProjectID
          AND NOT EXISTS (
              SELECT 1
              FROM @ExistingDemoTasks existing
              WHERE existing.WorkflowTaskID = t.WorkflowTaskID
          );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
       AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
       AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'RelatedEntity') IS NOT NULL
       AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'RelatedKey') IS NOT NULL
    BEGIN
        INSERT INTO @ExistingDemoTasks (WorkflowTaskID)
        SELECT t.WorkflowTaskID
        FROM dbo.tblWorkflowTasks t
        WHERE t.WorkflowProjectID = @ProjectID
          AND t.RelatedEntity = @ProjectTaskSeedTag
          AND t.RelatedKey = @ProjectTaskSeedKey
          AND NOT EXISTS (
              SELECT 1
              FROM @ExistingDemoTasks existing
              WHERE existing.WorkflowTaskID = t.WorkflowTaskID
          );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
    BEGIN
        DELETE d
        FROM dbo.tblWorkflowTaskDependencies d
        WHERE EXISTS (SELECT 1 FROM @ExistingDemoTasks t WHERE t.WorkflowTaskID = d.WorkflowTaskID)
           OR EXISTS (SELECT 1 FROM @ExistingDemoTasks t WHERE t.WorkflowTaskID = d.DependsOnWorkflowTaskID);
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
    BEGIN
        DELETE l
        FROM dbo.tblWorkflowEntityLinks l
        WHERE EXISTS (SELECT 1 FROM @ExistingDemoTasks t WHERE t.WorkflowTaskID = l.WorkflowTaskID)
           OR EXISTS (
               SELECT 1
               FROM @ExistingDemoRequirements r
               WHERE l.LinkedEntity = N'WorkflowRequirement'
                 AND l.LinkedEntityID = r.WorkflowRequirementID
           )
           OR l.Notes = @SeedTag;

        SET @DeletedLinkCount = @DeletedLinkCount + @@ROWCOUNT;
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTaskActivity', N'U') IS NOT NULL
    BEGIN
        DELETE activity
        FROM dbo.tblWorkflowTaskActivity activity
        WHERE EXISTS (
            SELECT 1
            FROM @ExistingDemoTasks t
            WHERE t.WorkflowTaskID = activity.WorkflowTaskID
        );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NOT NULL
    BEGIN
        DELETE attachment
        FROM dbo.tblWorkflowTaskAttachments attachment
        WHERE EXISTS (
            SELECT 1
            FROM @ExistingDemoTasks t
            WHERE t.WorkflowTaskID = attachment.WorkflowTaskID
        );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NOT NULL
    BEGIN
        DELETE taskComment
        FROM dbo.tblWorkflowTaskComments taskComment
        WHERE EXISTS (
            SELECT 1
            FROM @ExistingDemoTasks t
            WHERE t.WorkflowTaskID = taskComment.WorkflowTaskID
        );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NOT NULL
    BEGIN
        DELETE taskView
        FROM dbo.tblWorkflowTaskViews taskView
        WHERE EXISTS (
            SELECT 1
            FROM @ExistingDemoTasks t
            WHERE t.WorkflowTaskID = taskView.WorkflowTaskID
        );
    END;

    IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
    BEGIN
        IF COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NOT NULL
        BEGIN
            UPDATE childTask
            SET ParentWorkflowTaskID = NULL
            FROM dbo.tblWorkflowTasks childTask
            WHERE EXISTS (
                SELECT 1
                FROM @ExistingDemoTasks t
                WHERE t.WorkflowTaskID = childTask.ParentWorkflowTaskID
            );
        END;

        DELETE task
        FROM dbo.tblWorkflowTasks task
        WHERE EXISTS (
            SELECT 1
            FROM @ExistingDemoTasks t
            WHERE t.WorkflowTaskID = task.WorkflowTaskID
        );

        SET @DeletedTaskCount = @@ROWCOUNT;
    END;

    UPDATE childRequirement
    SET ParentRequirementID = NULL
    FROM dbo.tblWorkflowRequirements childRequirement
    WHERE EXISTS (
        SELECT 1
        FROM @ExistingDemoRequirements r
        WHERE r.WorkflowRequirementID = childRequirement.ParentRequirementID
    );

    DELETE requirement
    FROM dbo.tblWorkflowRequirements requirement
    WHERE EXISTS (
        SELECT 1
        FROM @ExistingDemoRequirements r
        WHERE r.WorkflowRequirementID = requirement.WorkflowRequirementID
    );

    SET @DeletedRequirementCount = @@ROWCOUNT;
END;

MERGE dbo.tblWorkflowRequirements AS target
USING @Source AS source
    ON target.RequirementCode = source.RequirementCode
WHEN MATCHED THEN
    UPDATE SET
        WorkflowProjectID = @ProjectID,
        ParentRequirementID = NULL,
        RequirementLevelCode = source.RequirementLevelCode,
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
        (RequirementCode, WorkflowProjectID, ParentRequirementID, RequirementLevelCode, ModuleCode, RequirementTitle, RequirementTypeCode,
         PriorityCode, RequirementStatusCode, [Description], AcceptanceCriteria,
         RequestedByUserID, OwnerUserID, ApprovedByUserID, ApprovedAt, Active,
         CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (source.RequirementCode, @ProjectID, NULL, source.RequirementLevelCode, source.ModuleCode, source.RequirementTitle, source.RequirementTypeCode,
         source.PriorityCode, source.RequirementStatusCode, source.[Description], source.AcceptanceCriteria,
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.RequestedSlot % @UserCount) + 1)), @OwnerUserID),
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.OwnerSlot % @UserCount) + 1)), @OwnerUserID),
         CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN @OwnerUserID ELSE NULL END,
         CASE WHEN source.RequirementStatusCode IN (N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED') THEN SYSUTCDATETIME() ELSE NULL END,
         1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

UPDATE child
SET child.ParentRequirementID = parent.WorkflowRequirementID,
    child.RequirementLevelCode = N'DETAILED',
    child.WorkflowProjectID = @ProjectID,
    child.UpdatedAt = SYSUTCDATETIME(),
    child.UpdatedBy = @OwnerUserID
FROM dbo.tblWorkflowRequirements child
INNER JOIN @Source source
    ON source.RequirementCode = child.RequirementCode
INNER JOIN dbo.tblWorkflowRequirements parent
    ON parent.RequirementCode = source.ParentRequirementCode
WHERE source.ParentRequirementCode IS NOT NULL;

UPDATE parent
SET parent.ParentRequirementID = NULL,
    parent.RequirementLevelCode = N'HIGH_LEVEL',
    parent.WorkflowProjectID = @ProjectID,
    parent.UpdatedAt = SYSUTCDATETIME(),
    parent.UpdatedBy = @OwnerUserID
FROM dbo.tblWorkflowRequirements parent
INNER JOIN @Source source
    ON source.RequirementCode = parent.RequirementCode
WHERE source.ParentRequirementCode IS NULL;

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
BEGIN
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
    @ResetExistingDemoData AS ResetExistingDemoData,
    @RemoveAllProjectTasks AS RemovedAllProjectTasks,
    @ReloadAsParentRequirements AS ReloadedAsParentRequirements,
    CASE WHEN @ReloadAsParentRequirements = 0 THEN 1 ELSE 0 END AS SeededDetailedRequirements,
    @DeletedTaskCount AS DeletedDemoTaskCount,
    @DeletedRequirementCount AS DeletedDemoRequirementCount,
    @DeletedLinkCount AS DeletedDemoLinkCount,
    COUNT(*) AS DemoRequirementCount,
    SUM(CASE WHEN RequirementLevelCode = N'HIGH_LEVEL' THEN 1 ELSE 0 END) AS HighLevelRequirementCount,
    SUM(CASE WHEN RequirementLevelCode = N'DETAILED' THEN 1 ELSE 0 END) AS DetailedRequirementCount,
    SUM(CASE WHEN AcceptanceCriteria IS NULL OR LTRIM(RTRIM(AcceptanceCriteria)) = N'' THEN 1 ELSE 0 END) AS MissingAcceptanceCriteriaCount,
    SUM(CASE WHEN RequirementStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedCount
FROM dbo.tblWorkflowRequirements
WHERE RequirementCode LIKE N'REQ-DEMO-%';
GO
