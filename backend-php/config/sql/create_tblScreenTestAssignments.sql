/*
    Create storage for assigning screen test scripts to specific users.

    Assignments let test coordinators direct users to specific scripts or module
    groups, while run results remain stored in tblScreenTestRuns.
*/

USE [CBMSv2];
GO

IF OBJECT_ID(N'dbo.tblScreenTestAssignments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblScreenTestAssignments
    (
        ScreenTestAssignmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(120) NOT NULL,
        ModuleName NVARCHAR(120) NULL,
        DueDate DATE NULL,
        Status NVARCHAR(30) NOT NULL CONSTRAINT DF_tblScreenTestAssignments_Status DEFAULT (N'assigned'),
        AssignedBy INT NULL,
        AssignedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestAssignments_AssignedAt DEFAULT (SYSUTCDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblScreenTestAssignments_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestAssignments_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblScreenTestAssignments
    ADD CONSTRAINT FK_tblScreenTestAssignments_User
        FOREIGN KEY (UserID)
        REFERENCES dbo.tblUsers (UserID);

    CREATE NONCLUSTERED INDEX IX_tblScreenTestAssignments_User_Status
        ON dbo.tblScreenTestAssignments (UserID, Status, ActiveFlag, DueDate, ScenarioCode);

    CREATE NONCLUSTERED INDEX IX_tblScreenTestAssignments_Scenario_Status
        ON dbo.tblScreenTestAssignments (ScenarioCode, Status, ActiveFlag, UserID);
END;
GO
