USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroups', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowUserGroups
    (
        WorkflowUserGroupID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowUserGroups PRIMARY KEY,
        GroupName NVARCHAR(255) NOT NULL,
        [Description] NVARCHAR(500) NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowUserGroups_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowUserGroups_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroups', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowUserGroups')
         AND name = N'UX_tblWorkflowUserGroups_GroupName'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowUserGroups_GroupName
        ON dbo.tblWorkflowUserGroups (GroupName);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowUserGroupMembers
    (
        WorkflowUserGroupMemberID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowUserGroupMembers PRIMARY KEY,
        WorkflowUserGroupID INT NOT NULL,
        UserID INT NOT NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblWorkflowUserGroupMembers_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowUserGroupMembers_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowUserGroups', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers')
         AND name = N'FK_tblWorkflowUserGroupMembers_Group'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowUserGroupMembers
    ADD CONSTRAINT FK_tblWorkflowUserGroupMembers_Group
        FOREIGN KEY (WorkflowUserGroupID)
        REFERENCES dbo.tblWorkflowUserGroups (WorkflowUserGroupID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers')
         AND name = N'FK_tblWorkflowUserGroupMembers_User'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowUserGroupMembers
    ADD CONSTRAINT FK_tblWorkflowUserGroupMembers_User
        FOREIGN KEY (UserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers')
         AND name = N'UX_tblWorkflowUserGroupMembers_GroupUser'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowUserGroupMembers_GroupUser
        ON dbo.tblWorkflowUserGroupMembers (WorkflowUserGroupID, UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers')
         AND name = N'IX_tblWorkflowUserGroupMembers_User'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowUserGroupMembers_User
        ON dbo.tblWorkflowUserGroupMembers (UserID, Active);
END;
GO
