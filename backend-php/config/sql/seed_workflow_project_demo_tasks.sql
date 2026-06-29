USE [CBMSv2];

SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @RequestedProjectID INT = 1;
DECLARE @ProjectID INT = NULL;
DECLARE @OwnerUserID INT = NULL;
DECLARE @ProjectTaskTypeID INT = NULL;
DECLARE @OpenStatusID INT = NULL;
DECLARE @InProgressStatusID INT = NULL;
DECLARE @CompletedStatusID INT = NULL;
DECLARE @SeedTag NVARCHAR(100) = N'WORKFLOW_PROJECT_DEMO_SEED';
DECLARE @SeedKey NVARCHAR(100) = N'PROJECT_SUMMARY_GANTT_DEMO';
DECLARE @BatchID NVARCHAR(36) = N'DEMO-GANTT-' + CONVERT(NVARCHAR(8), GETDATE(), 112);

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowProjects is missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowTasks is missing.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblWorkflowTaskTypes', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowTaskTypes is missing.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblWorkflowTaskStatuses', N'U') IS NULL
BEGIN
    RAISERROR(N'tblWorkflowTaskStatuses is missing.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblUsers', N'U') IS NULL
BEGIN
    RAISERROR(N'tblUsers is missing.', 16, 1);
    RETURN;
END;

IF COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowTasks', N'PlannedStartDate') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowTasks', N'PlannedEndDate') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowTasks', N'PercentComplete') IS NULL
   OR COL_LENGTH(N'dbo.tblWorkflowTasks', N'ProjectUtilisationPercent') IS NULL
BEGIN
    RAISERROR(N'Project task columns are missing. Run backend-php/config/sql/create_workflow_projects.sql first.', 16, 1);
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
    RAISERROR(N'No active users are available for assigning demo project tasks.', 16, 1);
    RETURN;
END;

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
        (N'DEMO-GANTT', N'Demo Project Plan', N'Demo data for reviewing the Project Summary and Gantt chart screens.',
         @OwnerUserID, N'ACTIVE', '2026-06-01', '2026-08-31', 1, SYSUTCDATETIME(), @OwnerUserID, SYSUTCDATETIME(), @OwnerUserID);

    SET @ProjectID = CONVERT(INT, SCOPE_IDENTITY());
END;

IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'Code') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskTypes ADD Code NVARCHAR(50) NULL;
END;

SELECT TOP (1) @ProjectTaskTypeID = TaskTypeID
FROM dbo.tblWorkflowTaskTypes
WHERE UPPER(COALESCE(Code, N'')) = N'PROJECT_TASK'
   OR UPPER(Name) = N'PROJECT TASK'
ORDER BY TaskTypeID ASC;

IF @ProjectTaskTypeID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowTaskTypes
        (Code, Name, IsActive, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
    VALUES
        (N'PROJECT_TASK', N'Project Task', 1, SYSDATETIME(), N'system', SYSDATETIME(), N'system');

    SET @ProjectTaskTypeID = CONVERT(INT, SCOPE_IDENTITY());
END;

SELECT TOP (1) @OpenStatusID = StatusID
FROM dbo.tblWorkflowTaskStatuses
WHERE UPPER(Code) = N'OPEN'
   OR UPPER(Name) = N'OPEN'
ORDER BY StatusID ASC;

IF @OpenStatusID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowTaskStatuses (Code, Name, IsActive)
    VALUES (N'OPEN', N'Open', 1);

    SET @OpenStatusID = CONVERT(INT, SCOPE_IDENTITY());
END;

SELECT TOP (1) @InProgressStatusID = StatusID
FROM dbo.tblWorkflowTaskStatuses
WHERE UPPER(Code) IN (N'INPROGRESS', N'IN_PROGRESS')
   OR UPPER(Name) IN (N'IN PROGRESS', N'IN-PROGRESS')
ORDER BY StatusID ASC;

IF @InProgressStatusID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowTaskStatuses (Code, Name, IsActive)
    VALUES (N'INPROGRESS', N'In Progress', 1);

    SET @InProgressStatusID = CONVERT(INT, SCOPE_IDENTITY());
END;

SELECT TOP (1) @CompletedStatusID = StatusID
FROM dbo.tblWorkflowTaskStatuses
WHERE UPPER(Code) = N'COMPLETED'
   OR UPPER(Name) = N'COMPLETED'
ORDER BY StatusID ASC;

IF @CompletedStatusID IS NULL
BEGIN
    INSERT INTO dbo.tblWorkflowTaskStatuses (Code, Name, IsActive)
    VALUES (N'COMPLETED', N'Completed', 1);

    SET @CompletedStatusID = CONVERT(INT, SCOPE_IDENTITY());
END;

BEGIN TRANSACTION;

UPDATE dbo.tblWorkflowProjects
SET ProjectStatusCode = N'ACTIVE',
    StartDate = '2026-06-01',
    TargetEndDate = '2026-08-31',
    UpdatedAt = SYSUTCDATETIME(),
    UpdatedBy = @OwnerUserID
WHERE WorkflowProjectID = @ProjectID;

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.tblWorkflowProjectUsers
        (WorkflowProjectID, UserID, ProjectRoleCode, AssignedAt, AssignedBy)
    SELECT
        @ProjectID,
        u.UserID,
        CASE WHEN u.RowNo = 1 THEN N'LEAD' ELSE N'MEMBER' END,
        SYSUTCDATETIME(),
        @OwnerUserID
    FROM @Users u
    WHERE NOT EXISTS (
        SELECT 1
        FROM dbo.tblWorkflowProjectUsers existing
        WHERE existing.WorkflowProjectID = @ProjectID
          AND existing.UserID = u.UserID
    );
END;

DECLARE @ExistingSeededTasks TABLE (WorkflowTaskID INT NOT NULL PRIMARY KEY);

INSERT INTO @ExistingSeededTasks (WorkflowTaskID)
SELECT WorkflowTaskID
FROM dbo.tblWorkflowTasks
WHERE WorkflowProjectID = @ProjectID
  AND RelatedEntity = @SeedTag
  AND RelatedKey = @SeedKey;

IF EXISTS (SELECT 1 FROM @ExistingSeededTasks)
BEGIN
    IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
    BEGIN
        DELETE d
        FROM dbo.tblWorkflowTaskDependencies d
        WHERE EXISTS (SELECT 1 FROM @ExistingSeededTasks s WHERE s.WorkflowTaskID = d.WorkflowTaskID)
           OR EXISTS (SELECT 1 FROM @ExistingSeededTasks s WHERE s.WorkflowTaskID = d.DependsOnWorkflowTaskID);
    END;

    UPDATE t
    SET ParentWorkflowTaskID = NULL
    FROM dbo.tblWorkflowTasks t
    WHERE EXISTS (SELECT 1 FROM @ExistingSeededTasks s WHERE s.WorkflowTaskID = t.ParentWorkflowTaskID);

    DELETE t
    FROM dbo.tblWorkflowTasks t
    WHERE EXISTS (SELECT 1 FROM @ExistingSeededTasks s WHERE s.WorkflowTaskID = t.WorkflowTaskID);
END;

DECLARE @Source TABLE
(
    RowNo INT IDENTITY(1,1) NOT NULL,
    TaskKey NVARCHAR(80) NOT NULL PRIMARY KEY,
    ParentKey NVARCHAR(80) NULL,
    Title NVARCHAR(255) NOT NULL,
    PlannedStartDate DATE NOT NULL,
    PlannedEndDate DATE NOT NULL,
    DueDate DATE NOT NULL,
    PercentComplete DECIMAL(5,2) NOT NULL,
    ProjectUtilisationPercent DECIMAL(5,2) NOT NULL,
    PriorityCode NVARCHAR(20) NOT NULL,
    AssignedSlot INT NOT NULL
);

INSERT INTO @Source
    (TaskKey, ParentKey, Title, PlannedStartDate, PlannedEndDate, DueDate, PercentComplete, ProjectUtilisationPercent, PriorityCode, AssignedSlot)
VALUES
    (N'phase-initiation', NULL, N'Phase 1 - Initiation and scope', '2026-06-01', '2026-06-14', '2026-06-14', 100, 20, N'NORMAL', 0),
    (N'charter', N'phase-initiation', N'Confirm project charter', '2026-06-01', '2026-06-03', '2026-06-03', 100, 15, N'NORMAL', 1),
    (N'stakeholders', N'phase-initiation', N'Stakeholder mapping and contact list', '2026-06-04', '2026-06-07', '2026-06-07', 100, 10, N'LOW', 2),
    (N'kickoff', N'phase-initiation', N'Run project kickoff workshop', '2026-06-10', '2026-06-10', '2026-06-10', 100, 25, N'NORMAL', 3),
    (N'phase-requirements', NULL, N'Phase 2 - Requirements and design', '2026-06-10', '2026-06-30', '2026-06-30', 72, 35, N'HIGH', 0),
    (N'process-review', N'phase-requirements', N'Review current process and pain points', '2026-06-10', '2026-06-14', '2026-06-14', 100, 35, N'NORMAL', 1),
    (N'requirements', N'phase-requirements', N'Capture functional requirements', '2026-06-15', '2026-06-23', '2026-06-23', 85, 50, N'HIGH', 2),
    (N'data-ownership', N'phase-requirements', N'Resolve data ownership questions', '2026-06-18', '2026-06-24', '2026-06-24', 40, 25, N'URGENT', 3),
    (N'workflow-design', N'phase-requirements', N'Design approval workflow and escalation rules', '2026-06-20', '2026-06-27', '2026-06-27', 65, 40, N'HIGH', 4),
    (N'requirements-signoff', N'phase-requirements', N'Obtain requirements sign-off', '2026-06-28', '2026-06-30', '2026-06-30', 20, 20, N'HIGH', 0),
    (N'phase-build', NULL, N'Phase 3 - Build and configuration', '2026-07-01', '2026-07-31', '2026-07-31', 30, 60, N'HIGH', 0),
    (N'module-config', N'phase-build', N'Configure project module settings', '2026-07-01', '2026-07-08', '2026-07-08', 60, 75, N'HIGH', 1),
    (N'task-templates', N'phase-build', N'Build project task templates', '2026-07-05', '2026-07-12', '2026-07-12', 45, 60, N'NORMAL', 2),
    (N'notifications', N'phase-build', N'Configure reminder and escalation notifications', '2026-07-10', '2026-07-17', '2026-07-17', 30, 40, N'NORMAL', 3),
    (N'scheduler-validation', N'phase-build', N'Validate scheduler and email queue processing', '2026-07-15', '2026-07-18', '2026-07-18', 20, 35, N'NORMAL', 4),
    (N'security-review', N'phase-build', N'Perform security and access review', '2026-07-18', '2026-07-25', '2026-07-25', 10, 25, N'HIGH', 5),
    (N'migration-dry-run', N'phase-build', N'Run data migration dry run', '2026-07-23', '2026-07-31', '2026-07-31', 0, 50, N'NORMAL', 1),
    (N'phase-uat', NULL, N'Phase 4 - Testing and UAT', '2026-08-01', '2026-08-20', '2026-08-20', 0, 45, N'NORMAL', 0),
    (N'test-plan', N'phase-uat', N'Prepare test plan and scenarios', '2026-08-01', '2026-08-04', '2026-08-04', 0, 40, N'NORMAL', 2),
    (N'uat-cycle-1', N'phase-uat', N'Run UAT cycle 1 with project users', '2026-08-05', '2026-08-12', '2026-08-12', 0, 65, N'HIGH', 3),
    (N'defect-remediation', N'phase-uat', N'Remediate UAT findings', '2026-08-13', '2026-08-17', '2026-08-17', 0, 70, N'HIGH', 4),
    (N'uat-signoff', N'phase-uat', N'Secure UAT sign-off', '2026-08-18', '2026-08-20', '2026-08-20', 0, 30, N'NORMAL', 0),
    (N'phase-golive', NULL, N'Phase 5 - Go-live readiness', '2026-08-21', '2026-08-31', '2026-08-31', 0, 35, N'NORMAL', 0),
    (N'training-pack', N'phase-golive', N'Prepare training pack and quick reference guide', '2026-08-21', '2026-08-24', '2026-08-24', 0, 30, N'NORMAL', 1),
    (N'cutover-checklist', N'phase-golive', N'Complete cutover checklist', '2026-08-25', '2026-08-27', '2026-08-27', 0, 45, N'HIGH', 2),
    (N'readiness-review', N'phase-golive', N'Production readiness review', '2026-08-28', '2026-08-28', '2026-08-28', 0, 25, N'HIGH', 3),
    (N'golive-support', N'phase-golive', N'Go-live support window', '2026-08-29', '2026-08-31', '2026-08-31', 0, 60, N'NORMAL', 4);

DECLARE @TaskMap TABLE
(
    TaskKey NVARCHAR(80) NOT NULL PRIMARY KEY,
    WorkflowTaskID INT NOT NULL
);

MERGE dbo.tblWorkflowTasks AS target
USING @Source AS source
    ON 1 = 0
WHEN NOT MATCHED THEN
    INSERT
        (TaskTypeID, StatusID, Title, [Description], CreatedByUserID, AssignedToUserID,
         RelatedEntity, RelatedKey, PriorityCode, DueDate,
         WorkflowProjectID, ParentWorkflowTaskID, PlannedStartDate, PlannedEndDate,
         PercentComplete, ProjectUtilisationPercent, CompletedAt,
         NotifyCreatorOnCompletion, NotifyCreatorOnUpdate, NotifyAudienceOnComment,
         AutoReminderEnabled, AutoReminderDaysBeforeDue,
         OverdueEscalationEnabled, OverdueEscalationDaysAfterDue,
         WorkflowTaskBatchID, WorkflowTaskCompletionRule, CreatedAt)
    VALUES
        (@ProjectTaskTypeID,
         CASE
             WHEN source.PercentComplete >= 100 THEN @CompletedStatusID
             WHEN source.PercentComplete > 0 THEN @InProgressStatusID
             ELSE @OpenStatusID
         END,
         source.Title,
         N'Demo project task generated for reviewing the Project Summary and Gantt chart screens.',
         @OwnerUserID,
         COALESCE((SELECT TOP (1) UserID FROM @Users WHERE RowNo = ((source.AssignedSlot % (SELECT COUNT(*) FROM @Users)) + 1)), @OwnerUserID),
         @SeedTag,
         @SeedKey,
         source.PriorityCode,
         source.DueDate,
         @ProjectID,
         NULL,
         source.PlannedStartDate,
         source.PlannedEndDate,
         source.PercentComplete,
         source.ProjectUtilisationPercent,
         CASE WHEN source.PercentComplete >= 100 THEN DATEADD(HOUR, 9, CAST(source.PlannedEndDate AS DATETIME2(0))) ELSE NULL END,
         1,
         0,
         1,
         0,
         1,
         0,
         1,
         @BatchID,
         N'INDIVIDUAL',
         SYSUTCDATETIME())
OUTPUT source.TaskKey, INSERTED.WorkflowTaskID
INTO @TaskMap (TaskKey, WorkflowTaskID);

UPDATE childTask
SET ParentWorkflowTaskID = parentMap.WorkflowTaskID
FROM dbo.tblWorkflowTasks childTask
INNER JOIN @TaskMap childMap
    ON childMap.WorkflowTaskID = childTask.WorkflowTaskID
INNER JOIN @Source source
    ON source.TaskKey = childMap.TaskKey
INNER JOIN @TaskMap parentMap
    ON parentMap.TaskKey = source.ParentKey
WHERE source.ParentKey IS NOT NULL;

DECLARE @Deps TABLE
(
    TaskKey NVARCHAR(80) NOT NULL,
    DependsKey NVARCHAR(80) NOT NULL
);

INSERT INTO @Deps (TaskKey, DependsKey)
VALUES
    (N'phase-requirements', N'phase-initiation'),
    (N'process-review', N'kickoff'),
    (N'requirements', N'process-review'),
    (N'data-ownership', N'requirements'),
    (N'workflow-design', N'requirements'),
    (N'requirements-signoff', N'data-ownership'),
    (N'requirements-signoff', N'workflow-design'),
    (N'phase-build', N'requirements-signoff'),
    (N'module-config', N'requirements-signoff'),
    (N'task-templates', N'module-config'),
    (N'notifications', N'module-config'),
    (N'scheduler-validation', N'notifications'),
    (N'security-review', N'task-templates'),
    (N'migration-dry-run', N'security-review'),
    (N'phase-uat', N'phase-build'),
    (N'test-plan', N'migration-dry-run'),
    (N'uat-cycle-1', N'test-plan'),
    (N'defect-remediation', N'uat-cycle-1'),
    (N'uat-signoff', N'defect-remediation'),
    (N'phase-golive', N'phase-uat'),
    (N'training-pack', N'uat-signoff'),
    (N'cutover-checklist', N'training-pack'),
    (N'readiness-review', N'cutover-checklist'),
    (N'golive-support', N'readiness-review');

DECLARE @DependencyCount INT = 0;

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.tblWorkflowTaskDependencies
        (WorkflowTaskID, DependsOnWorkflowTaskID, DependencyTypeCode, CreatedAt, CreatedBy)
    SELECT
        taskMap.WorkflowTaskID,
        dependsMap.WorkflowTaskID,
        N'FINISH_TO_START',
        SYSUTCDATETIME(),
        @OwnerUserID
    FROM @Deps deps
    INNER JOIN @TaskMap taskMap
        ON taskMap.TaskKey = deps.TaskKey
    INNER JOIN @TaskMap dependsMap
        ON dependsMap.TaskKey = deps.DependsKey
    WHERE NOT EXISTS (
        SELECT 1
        FROM dbo.tblWorkflowTaskDependencies existing
        WHERE existing.WorkflowTaskID = taskMap.WorkflowTaskID
          AND existing.DependsOnWorkflowTaskID = dependsMap.WorkflowTaskID
    );

    SET @DependencyCount = @@ROWCOUNT;
END;

COMMIT TRANSACTION;

SELECT
    @ProjectID AS ProjectID,
    COUNT(*) AS DemoTaskCount,
    @DependencyCount AS DemoDependencyCount,
    MIN(PlannedStartDate) AS FirstPlannedStartDate,
    MAX(PlannedEndDate) AS LastPlannedEndDate
FROM dbo.tblWorkflowTasks
WHERE WorkflowProjectID = @ProjectID
  AND RelatedEntity = @SeedTag
  AND RelatedKey = @SeedKey;
