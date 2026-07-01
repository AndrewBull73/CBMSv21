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
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowProjects
    (
        WorkflowProjectID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowProjects PRIMARY KEY,
        ProjectCode NVARCHAR(50) NULL,
        ProjectName NVARCHAR(255) NOT NULL,
        [Description] NVARCHAR(MAX) NULL,
        ProjectOwnerUserID INT NULL,
        ProjectStatusCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowProjects_ProjectStatusCode DEFAULT (N'PLANNED'),
        StartDate DATE NULL,
        TargetEndDate DATE NULL,
        ActualEndDate DATE NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowProjects_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowProjects_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowProjects')
         AND name = N'CK_tblWorkflowProjects_ProjectStatusCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowProjects
    ADD CONSTRAINT CK_tblWorkflowProjects_ProjectStatusCode
        CHECK (ProjectStatusCode IN (N'PLANNED', N'ACTIVE', N'ON_HOLD', N'COMPLETED', N'CANCELLED'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowProjects')
         AND name = N'FK_tblWorkflowProjects_Owner'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowProjects
    ADD CONSTRAINT FK_tblWorkflowProjects_Owner
        FOREIGN KEY (ProjectOwnerUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowProjects')
         AND name = N'UX_tblWorkflowProjects_ProjectCode'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowProjects_ProjectCode
        ON dbo.tblWorkflowProjects (ProjectCode)
        WHERE ProjectCode IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowProjects')
         AND name = N'IX_tblWorkflowProjects_StatusActive'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowProjects_StatusActive
        ON dbo.tblWorkflowProjects (Active, ProjectStatusCode, ProjectName);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowProjectUsers
    (
        WorkflowProjectUserID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowProjectUsers PRIMARY KEY,
        WorkflowProjectID INT NOT NULL,
        UserID INT NOT NULL,
        ProjectRoleCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowProjectUsers_ProjectRoleCode DEFAULT (N'MEMBER'),
        AssignedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowProjectUsers_AssignedAt DEFAULT SYSUTCDATETIME(),
        AssignedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowProjectUsers')
         AND name = N'FK_tblWorkflowProjectUsers_Project'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowProjectUsers
    ADD CONSTRAINT FK_tblWorkflowProjectUsers_Project
        FOREIGN KEY (WorkflowProjectID)
        REFERENCES dbo.tblWorkflowProjects (WorkflowProjectID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowProjectUsers')
         AND name = N'FK_tblWorkflowProjectUsers_User'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowProjectUsers
    ADD CONSTRAINT FK_tblWorkflowProjectUsers_User
        FOREIGN KEY (UserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowProjectUsers')
         AND name = N'CK_tblWorkflowProjectUsers_ProjectRoleCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowProjectUsers
    ADD CONSTRAINT CK_tblWorkflowProjectUsers_ProjectRoleCode
        CHECK (ProjectRoleCode IN (N'MEMBER', N'LEAD', N'OBSERVER'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowProjectUsers')
         AND name = N'UX_tblWorkflowProjectUsers_ProjectUser'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowProjectUsers_ProjectUser
        ON dbo.tblWorkflowProjectUsers (WorkflowProjectID, UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowProjectUsers')
         AND name = N'IX_tblWorkflowProjectUsers_User'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowProjectUsers_User
        ON dbo.tblWorkflowProjectUsers (UserID, WorkflowProjectID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskTypes', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'Code') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskTypes
    ADD Code NVARCHAR(50) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskTypes', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'Code') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'Name') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'IsActive') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1
        FROM dbo.tblWorkflowTaskTypes
        WHERE UPPER(COALESCE(Code, N'')) = N'PROJECT_TASK'
           OR UPPER(Name) = N'PROJECT TASK'
    )
    BEGIN
        DECLARE @ProjectTaskTypeUpdateSql NVARCHAR(MAX) =
            N'UPDATE dbo.tblWorkflowTaskTypes
              SET Code = N''PROJECT_TASK'',
                  Name = N''Project Task'',
                  IsActive = 1';

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'UpdatedAt') IS NOT NULL
            SET @ProjectTaskTypeUpdateSql = @ProjectTaskTypeUpdateSql + N',
                  UpdatedAt = SYSDATETIME()';

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'UpdatedBy') IS NOT NULL
            SET @ProjectTaskTypeUpdateSql = @ProjectTaskTypeUpdateSql + N',
                  UpdatedBy = N''system''';

        SET @ProjectTaskTypeUpdateSql = @ProjectTaskTypeUpdateSql + N'
              WHERE UPPER(COALESCE(Code, N'''')) = N''PROJECT_TASK''
                 OR UPPER(Name) = N''PROJECT TASK'';';

        EXEC sp_executesql @ProjectTaskTypeUpdateSql;
    END
    ELSE
    BEGIN
        DECLARE @ProjectTaskTypeInsertColumns NVARCHAR(MAX) = N'Code, Name, IsActive';
        DECLARE @ProjectTaskTypeInsertValues NVARCHAR(MAX) = N'N''PROJECT_TASK'', N''Project Task'', 1';
        DECLARE @ProjectTaskTypeInsertSql NVARCHAR(MAX);

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'CreatedAt') IS NOT NULL
        BEGIN
            SET @ProjectTaskTypeInsertColumns = @ProjectTaskTypeInsertColumns + N', CreatedAt';
            SET @ProjectTaskTypeInsertValues = @ProjectTaskTypeInsertValues + N', SYSDATETIME()';
        END;

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'CreatedBy') IS NOT NULL
        BEGIN
            SET @ProjectTaskTypeInsertColumns = @ProjectTaskTypeInsertColumns + N', CreatedBy';
            SET @ProjectTaskTypeInsertValues = @ProjectTaskTypeInsertValues + N', N''system''';
        END;

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'UpdatedAt') IS NOT NULL
        BEGIN
            SET @ProjectTaskTypeInsertColumns = @ProjectTaskTypeInsertColumns + N', UpdatedAt';
            SET @ProjectTaskTypeInsertValues = @ProjectTaskTypeInsertValues + N', SYSDATETIME()';
        END;

        IF COL_LENGTH(N'dbo.tblWorkflowTaskTypes', N'UpdatedBy') IS NOT NULL
        BEGIN
            SET @ProjectTaskTypeInsertColumns = @ProjectTaskTypeInsertColumns + N', UpdatedBy';
            SET @ProjectTaskTypeInsertValues = @ProjectTaskTypeInsertValues + N', N''system''';
        END;

        SET @ProjectTaskTypeInsertSql =
            N'INSERT INTO dbo.tblWorkflowTaskTypes (' + @ProjectTaskTypeInsertColumns + N')
              VALUES (' + @ProjectTaskTypeInsertValues + N');';

        EXEC sp_executesql @ProjectTaskTypeInsertSql;
    END;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD WorkflowProjectID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'PlannedStartDate') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD PlannedStartDate DATE NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'PlannedEndDate') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD PlannedEndDate DATE NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'PercentComplete') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD PercentComplete DECIMAL(5,2) NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_PercentComplete DEFAULT (0);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ProjectUtilisationPercent') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD ProjectUtilisationPercent DECIMAL(5,2) NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_ProjectUtilisationPercent DEFAULT (0);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD ParentWorkflowTaskID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'FK_tblWorkflowTasks_Project'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT FK_tblWorkflowTasks_Project
        FOREIGN KEY (WorkflowProjectID)
        REFERENCES dbo.tblWorkflowProjects (WorkflowProjectID)
        ON DELETE SET NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_ParentWorkflowTask'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_ParentWorkflowTask
        CHECK (ParentWorkflowTaskID IS NULL OR ParentWorkflowTaskID <> WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'FK_tblWorkflowTasks_ParentTask'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT FK_tblWorkflowTasks_ParentTask
        FOREIGN KEY (ParentWorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'PercentComplete') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_PercentComplete'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_PercentComplete
        CHECK (PercentComplete BETWEEN 0 AND 100);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ProjectUtilisationPercent') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_ProjectUtilisationPercent'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_ProjectUtilisationPercent
        CHECK (ProjectUtilisationPercent BETWEEN 0 AND 100);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'IX_tblWorkflowTasks_Project'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTasks_Project
        ON dbo.tblWorkflowTasks (WorkflowProjectID, CompletedAt, PlannedEndDate, DueDate);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'ParentWorkflowTaskID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'IX_tblWorkflowTasks_ProjectParent'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTasks_ProjectParent
        ON dbo.tblWorkflowTasks (WorkflowProjectID, ParentWorkflowTaskID, PlannedStartDate, DueDate);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowTaskDependencies
    (
        WorkflowTaskDependencyID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowTaskDependencies PRIMARY KEY,
        WorkflowTaskID INT NOT NULL,
        DependsOnWorkflowTaskID INT NOT NULL,
        DependencyTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowTaskDependencies_DependencyTypeCode DEFAULT (N'FINISH_TO_START'),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskDependencies_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'CK_tblWorkflowTaskDependencies_NoSelf'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskDependencies
    ADD CONSTRAINT CK_tblWorkflowTaskDependencies_NoSelf
        CHECK (WorkflowTaskID <> DependsOnWorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'CK_tblWorkflowTaskDependencies_Type'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskDependencies
    ADD CONSTRAINT CK_tblWorkflowTaskDependencies_Type
        CHECK (DependencyTypeCode IN (N'FINISH_TO_START'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'FK_tblWorkflowTaskDependencies_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskDependencies
    ADD CONSTRAINT FK_tblWorkflowTaskDependencies_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'FK_tblWorkflowTaskDependencies_DependsOnTask'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskDependencies
    ADD CONSTRAINT FK_tblWorkflowTaskDependencies_DependsOnTask
        FOREIGN KEY (DependsOnWorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'UX_tblWorkflowTaskDependencies_TaskDependsOn'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowTaskDependencies_TaskDependsOn
        ON dbo.tblWorkflowTaskDependencies (WorkflowTaskID, DependsOnWorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskDependencies', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskDependencies')
         AND name = N'IX_tblWorkflowTaskDependencies_DependsOn'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskDependencies_DependsOn
        ON dbo.tblWorkflowTaskDependencies (DependsOnWorkflowTaskID, WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowEntityLinks
    (
        WorkflowLinkID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowEntityLinks PRIMARY KEY,
        WorkflowProjectID INT NULL,
        WorkflowTaskID INT NULL,
        LinkTypeCode NVARCHAR(50) NOT NULL CONSTRAINT DF_tblWorkflowEntityLinks_LinkTypeCode DEFAULT (N'RELATED_ITEM'),
        LinkedEntity NVARCHAR(100) NOT NULL,
        LinkedEntityID INT NULL,
        LinkedEntityKey NVARCHAR(255) NULL,
        LinkedTitle NVARCHAR(255) NULL,
        LinkedUrl NVARCHAR(1000) NULL,
        Notes NVARCHAR(MAX) NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowEntityLinks_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowEntityLinks_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'CK_tblWorkflowEntityLinks_Context'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowEntityLinks
    ADD CONSTRAINT CK_tblWorkflowEntityLinks_Context
        CHECK (WorkflowProjectID IS NOT NULL OR WorkflowTaskID IS NOT NULL);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'CK_tblWorkflowEntityLinks_LinkTypeCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowEntityLinks
    DROP CONSTRAINT CK_tblWorkflowEntityLinks_LinkTypeCode;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowEntityLinks
    ADD CONSTRAINT CK_tblWorkflowEntityLinks_LinkTypeCode
        CHECK (LinkTypeCode IN (
            N'RELATED_ITEM',
            N'REQUIREMENT',
            N'ISSUE',
            N'TRAINING_SCENARIO',
            N'TRAINING_ASSIGNMENT',
            N'TRAINING_SESSION',
            N'SCREEN_TEST_SCENARIO',
            N'SCREEN_TEST_RUN',
            N'TEST_EVIDENCE',
            N'DEFECT',
            N'RELEASE_CHECKLIST',
            N'DOCUMENTATION'
        ));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'FK_tblWorkflowEntityLinks_Project'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowEntityLinks
    ADD CONSTRAINT FK_tblWorkflowEntityLinks_Project
        FOREIGN KEY (WorkflowProjectID)
        REFERENCES dbo.tblWorkflowProjects (WorkflowProjectID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'FK_tblWorkflowEntityLinks_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowEntityLinks
    ADD CONSTRAINT FK_tblWorkflowEntityLinks_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'IX_tblWorkflowEntityLinks_Task'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowEntityLinks_Task
        ON dbo.tblWorkflowEntityLinks (WorkflowTaskID, Active, LinkTypeCode, CreatedAt);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'IX_tblWorkflowEntityLinks_Project'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowEntityLinks_Project
        ON dbo.tblWorkflowEntityLinks (WorkflowProjectID, Active, LinkTypeCode, CreatedAt);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowEntityLinks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowEntityLinks')
         AND name = N'IX_tblWorkflowEntityLinks_LinkedEntity'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowEntityLinks_LinkedEntity
        ON dbo.tblWorkflowEntityLinks (LinkedEntity, LinkedEntityID, LinkedEntityKey, Active);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowRequirements
    (
        WorkflowRequirementID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowRequirements PRIMARY KEY,
        RequirementCode NVARCHAR(50) NULL,
        WorkflowProjectID INT NULL,
        ParentRequirementID INT NULL,
        RequirementLevelCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_RequirementLevelCode DEFAULT (N'HIGH_LEVEL'),
        ModuleCode NVARCHAR(100) NULL,
        RequirementTitle NVARCHAR(255) NOT NULL,
        DeliveryClassCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_DeliveryClassCode DEFAULT (N'ENHANCEMENT'),
        RequirementTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_RequirementTypeCode DEFAULT (N'FUNCTIONAL'),
        PriorityCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_PriorityCode DEFAULT (N'SHOULD'),
        RequirementStatusCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_RequirementStatusCode DEFAULT (N'DRAFT'),
        SourceDocument NVARCHAR(255) NULL,
        SourceSection NVARCHAR(255) NULL,
        [Description] NVARCHAR(MAX) NULL,
        AcceptanceCriteria NVARCHAR(MAX) NULL,
        RequestedByUserID INT NULL,
        OwnerUserID INT NULL,
        ApprovedByUserID INT NULL,
        ApprovedAt DATETIME2(0) NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowRequirements_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowRequirements_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'ParentRequirementID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD ParentRequirementID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'RequirementLevelCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD RequirementLevelCode NVARCHAR(30) NOT NULL
        CONSTRAINT DF_tblWorkflowRequirements_RequirementLevelCode DEFAULT (N'HIGH_LEVEL');
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'DeliveryClassCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD DeliveryClassCode NVARCHAR(30) NOT NULL
        CONSTRAINT DF_tblWorkflowRequirements_DeliveryClassCode DEFAULT (N'ENHANCEMENT');
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'SourceDocument') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD SourceDocument NVARCHAR(255) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'SourceSection') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD SourceSection NVARCHAR(255) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'FK_tblWorkflowRequirements_Project'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT FK_tblWorkflowRequirements_Project
        FOREIGN KEY (WorkflowProjectID)
        REFERENCES dbo.tblWorkflowProjects (WorkflowProjectID)
        ON DELETE SET NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'ParentRequirementID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'FK_tblWorkflowRequirements_ParentRequirement'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT FK_tblWorkflowRequirements_ParentRequirement
        FOREIGN KEY (ParentRequirementID)
        REFERENCES dbo.tblWorkflowRequirements (WorkflowRequirementID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'FK_tblWorkflowRequirements_RequestedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT FK_tblWorkflowRequirements_RequestedBy
        FOREIGN KEY (RequestedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'FK_tblWorkflowRequirements_Owner'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT FK_tblWorkflowRequirements_Owner
        FOREIGN KEY (OwnerUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'FK_tblWorkflowRequirements_ApprovedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT FK_tblWorkflowRequirements_ApprovedBy
        FOREIGN KEY (ApprovedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'RequirementLevelCode') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'CK_tblWorkflowRequirements_RequirementLevelCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_RequirementLevelCode
        CHECK (RequirementLevelCode IN (N'HIGH_LEVEL', N'DETAILED'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'ParentRequirementID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'CK_tblWorkflowRequirements_ParentNotSelf'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_ParentNotSelf
        CHECK (ParentRequirementID IS NULL OR ParentRequirementID <> WorkflowRequirementID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'DeliveryClassCode') IS NOT NULL
   AND EXISTS (
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

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'DeliveryClassCode') IS NOT NULL
   AND NOT EXISTS (
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

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'CK_tblWorkflowRequirements_RequirementTypeCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_RequirementTypeCode
        CHECK (RequirementTypeCode IN (N'FUNCTIONAL', N'NON_FUNCTIONAL', N'REPORTING', N'SECURITY', N'INTEGRATION', N'DATA', N'TRAINING'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'CK_tblWorkflowRequirements_PriorityCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_PriorityCode
        CHECK (PriorityCode IN (N'MUST', N'SHOULD', N'COULD', N'WONT'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'CK_tblWorkflowRequirements_RequirementStatusCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirements
    ADD CONSTRAINT CK_tblWorkflowRequirements_RequirementStatusCode
        CHECK (RequirementStatusCode IN (N'DRAFT', N'REVIEW', N'APPROVED', N'IN_BUILD', N'IN_TEST', N'COMPLETED', N'DEFERRED', N'CANCELLED'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'ParentRequirementID') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'IX_tblWorkflowRequirements_Parent'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirements_Parent
        ON dbo.tblWorkflowRequirements (ParentRequirementID, Active, RequirementStatusCode, PriorityCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'UX_tblWorkflowRequirements_RequirementCode'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowRequirements_RequirementCode
        ON dbo.tblWorkflowRequirements (RequirementCode)
        WHERE RequirementCode IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'IX_tblWorkflowRequirements_ProjectStatus'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirements_ProjectStatus
        ON dbo.tblWorkflowRequirements (WorkflowProjectID, Active, RequirementStatusCode, PriorityCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'IX_tblWorkflowRequirements_Owner'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirements_Owner
        ON dbo.tblWorkflowRequirements (OwnerUserID, Active, RequirementStatusCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowRequirements', N'DeliveryClassCode') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirements')
         AND name = N'IX_tblWorkflowRequirements_ProjectDeliveryClass'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirements_ProjectDeliveryClass
        ON dbo.tblWorkflowRequirements (WorkflowProjectID, Active, DeliveryClassCode, RequirementStatusCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementHistory', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowRequirementHistory
    (
        WorkflowRequirementHistoryID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowRequirementHistory PRIMARY KEY,
        WorkflowRequirementID INT NOT NULL,
        EventTypeCode NVARCHAR(30) NOT NULL,
        FromStatusCode NVARCHAR(30) NULL,
        ToStatusCode NVARCHAR(30) NULL,
        FieldName NVARCHAR(100) NULL,
        OldValue NVARCHAR(MAX) NULL,
        NewValue NVARCHAR(MAX) NULL,
        Notes NVARCHAR(MAX) NULL,
        ChangedByUserID INT NULL,
        ChangedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowRequirementHistory_ChangedAt DEFAULT SYSUTCDATETIME()
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementHistory', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementHistory')
         AND name = N'FK_tblWorkflowRequirementHistory_Requirement'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirementHistory
    ADD CONSTRAINT FK_tblWorkflowRequirementHistory_Requirement
        FOREIGN KEY (WorkflowRequirementID)
        REFERENCES dbo.tblWorkflowRequirements (WorkflowRequirementID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementHistory', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementHistory')
         AND name = N'FK_tblWorkflowRequirementHistory_ChangedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirementHistory
    ADD CONSTRAINT FK_tblWorkflowRequirementHistory_ChangedBy
        FOREIGN KEY (ChangedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementHistory', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementHistory')
         AND name = N'IX_tblWorkflowRequirementHistory_Requirement'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirementHistory_Requirement
        ON dbo.tblWorkflowRequirementHistory (WorkflowRequirementID, ChangedAt DESC, WorkflowRequirementHistoryID DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementHistory', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementHistory')
         AND name = N'IX_tblWorkflowRequirementHistory_ChangedBy'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirementHistory_ChangedBy
        ON dbo.tblWorkflowRequirementHistory (ChangedByUserID, ChangedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowRequirementAttachments
    (
        WorkflowRequirementAttachmentID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowRequirementAttachments PRIMARY KEY,
        WorkflowRequirementID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        StoragePath NVARCHAR(1000) NOT NULL,
        MimeType NVARCHAR(255) NULL,
        FileSizeBytes BIGINT NOT NULL CONSTRAINT DF_tblWorkflowRequirementAttachments_FileSizeBytes DEFAULT (0),
        UploadedByUserID INT NULL,
        UploadedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowRequirementAttachments_UploadedAt DEFAULT SYSUTCDATETIME(),
        Deleted BIT NOT NULL CONSTRAINT DF_tblWorkflowRequirementAttachments_Deleted DEFAULT (0),
        DeletedByUserID INT NULL,
        DeletedAt DATETIME2(0) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments')
         AND name = N'FK_tblWorkflowRequirementAttachments_Requirement'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirementAttachments
    ADD CONSTRAINT FK_tblWorkflowRequirementAttachments_Requirement
        FOREIGN KEY (WorkflowRequirementID)
        REFERENCES dbo.tblWorkflowRequirements (WorkflowRequirementID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments')
         AND name = N'FK_tblWorkflowRequirementAttachments_UploadedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirementAttachments
    ADD CONSTRAINT FK_tblWorkflowRequirementAttachments_UploadedBy
        FOREIGN KEY (UploadedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments')
         AND name = N'FK_tblWorkflowRequirementAttachments_DeletedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowRequirementAttachments
    ADD CONSTRAINT FK_tblWorkflowRequirementAttachments_DeletedBy
        FOREIGN KEY (DeletedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments')
         AND name = N'IX_tblWorkflowRequirementAttachments_Requirement'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirementAttachments_Requirement
        ON dbo.tblWorkflowRequirementAttachments (WorkflowRequirementID, Deleted, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowRequirementAttachments')
         AND name = N'IX_tblWorkflowRequirementAttachments_UploadedBy'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowRequirementAttachments_UploadedBy
        ON dbo.tblWorkflowRequirementAttachments (UploadedByUserID, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowIssues
    (
        WorkflowIssueID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowIssues PRIMARY KEY,
        WorkflowProjectID INT NULL,
        WorkflowRequirementID INT NULL,
        IssueCode NVARCHAR(50) NULL,
        IssueTitle NVARCHAR(255) NOT NULL,
        IssueDescription NVARCHAR(MAX) NULL,
        IssueTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowIssues_IssueTypeCode DEFAULT (N'BUG'),
        SeverityCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblWorkflowIssues_SeverityCode DEFAULT (N'MEDIUM'),
        PriorityCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblWorkflowIssues_PriorityCode DEFAULT (N'SHOULD'),
        IssueStatusCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblWorkflowIssues_IssueStatusCode DEFAULT (N'OPEN'),
        RaisedByUserID INT NULL,
        OwnerUserID INT NULL,
        RaisedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowIssues_RaisedAt DEFAULT SYSUTCDATETIME(),
        DueDate DATE NULL,
        ResolvedAt DATETIME2(0) NULL,
        ResolutionSummary NVARCHAR(MAX) NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowIssues_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowIssues_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'CK_tblWorkflowIssues_IssueTypeCode')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT CK_tblWorkflowIssues_IssueTypeCode
        CHECK (IssueTypeCode IN (N'BUG', N'GAP', N'RISK', N'DECISION', N'DATA', N'DEPENDENCY', N'CHANGE_REQUEST', N'OTHER'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'CK_tblWorkflowIssues_SeverityCode')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT CK_tblWorkflowIssues_SeverityCode
        CHECK (SeverityCode IN (N'CRITICAL', N'HIGH', N'MEDIUM', N'LOW'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'CK_tblWorkflowIssues_PriorityCode')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT CK_tblWorkflowIssues_PriorityCode
        CHECK (PriorityCode IN (N'MUST', N'SHOULD', N'COULD', N'WONT'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'CK_tblWorkflowIssues_IssueStatusCode')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT CK_tblWorkflowIssues_IssueStatusCode
        CHECK (IssueStatusCode IN (N'OPEN', N'TRIAGED', N'IN_PROGRESS', N'BLOCKED', N'RESOLVED', N'CLOSED', N'DEFERRED'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'FK_tblWorkflowIssues_Project')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT FK_tblWorkflowIssues_Project
        FOREIGN KEY (WorkflowProjectID)
        REFERENCES dbo.tblWorkflowProjects (WorkflowProjectID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowRequirements', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'FK_tblWorkflowIssues_Requirement')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT FK_tblWorkflowIssues_Requirement
        FOREIGN KEY (WorkflowRequirementID)
        REFERENCES dbo.tblWorkflowRequirements (WorkflowRequirementID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'FK_tblWorkflowIssues_RaisedBy')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT FK_tblWorkflowIssues_RaisedBy
        FOREIGN KEY (RaisedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'FK_tblWorkflowIssues_Owner')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT FK_tblWorkflowIssues_Owner
        FOREIGN KEY (OwnerUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'UX_tblWorkflowIssues_IssueCode')
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowIssues_IssueCode
        ON dbo.tblWorkflowIssues (IssueCode)
        WHERE IssueCode IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'IX_tblWorkflowIssues_ProjectStatus')
BEGIN
    CREATE INDEX IX_tblWorkflowIssues_ProjectStatus
        ON dbo.tblWorkflowIssues (WorkflowProjectID, Active, IssueStatusCode, SeverityCode, DueDate);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowIssues') AND name = N'IX_tblWorkflowIssues_Requirement')
BEGIN
    CREATE INDEX IX_tblWorkflowIssues_Requirement
        ON dbo.tblWorkflowIssues (WorkflowRequirementID, Active, IssueStatusCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowIssueAttachments
    (
        WorkflowIssueAttachmentID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowIssueAttachments PRIMARY KEY,
        WorkflowIssueID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        StoragePath NVARCHAR(1000) NOT NULL,
        MimeType NVARCHAR(255) NULL,
        FileSizeBytes BIGINT NOT NULL CONSTRAINT DF_tblWorkflowIssueAttachments_FileSizeBytes DEFAULT (0),
        UploadedByUserID INT NULL,
        UploadedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowIssueAttachments_UploadedAt DEFAULT SYSUTCDATETIME(),
        Deleted BIT NOT NULL CONSTRAINT DF_tblWorkflowIssueAttachments_Deleted DEFAULT (0),
        DeletedByUserID INT NULL,
        DeletedAt DATETIME2(0) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssueAttachments') AND name = N'FK_tblWorkflowIssueAttachments_Issue')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssueAttachments
    ADD CONSTRAINT FK_tblWorkflowIssueAttachments_Issue
        FOREIGN KEY (WorkflowIssueID)
        REFERENCES dbo.tblWorkflowIssues (WorkflowIssueID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssueAttachments') AND name = N'FK_tblWorkflowIssueAttachments_UploadedBy')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssueAttachments
    ADD CONSTRAINT FK_tblWorkflowIssueAttachments_UploadedBy
        FOREIGN KEY (UploadedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssueAttachments') AND name = N'FK_tblWorkflowIssueAttachments_DeletedBy')
BEGIN
    ALTER TABLE dbo.tblWorkflowIssueAttachments
    ADD CONSTRAINT FK_tblWorkflowIssueAttachments_DeletedBy
        FOREIGN KEY (DeletedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowIssueAttachments') AND name = N'IX_tblWorkflowIssueAttachments_Issue')
BEGIN
    CREATE INDEX IX_tblWorkflowIssueAttachments_Issue
        ON dbo.tblWorkflowIssueAttachments (WorkflowIssueID, Deleted, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowIssueAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowIssueAttachments') AND name = N'IX_tblWorkflowIssueAttachments_UploadedBy')
BEGIN
    CREATE INDEX IX_tblWorkflowIssueAttachments_UploadedBy
        ON dbo.tblWorkflowIssueAttachments (UploadedByUserID, UploadedAt DESC);
END;
GO
