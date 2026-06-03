USE [CBMSv2];
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowAssignments
    (
        WorkflowAssignmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        WorkflowStageCode NVARCHAR(50) NOT NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        DataObjectCode NVARCHAR(50) NOT NULL,
        UserID INT NOT NULL,
        SequenceNo INT NOT NULL CONSTRAINT DF_tblWorkflowAssignments_SequenceNo DEFAULT (1),
        IsPrimary BIT NOT NULL CONSTRAINT DF_tblWorkflowAssignments_IsPrimary DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblWorkflowAssignments_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowAssignments_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowAssignments_UpdatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowAssignments')
      AND name = N'IX_tblWorkflowAssignments_Lookup'
)
BEGIN
    CREATE INDEX IX_tblWorkflowAssignments_Lookup
        ON dbo.tblWorkflowAssignments (WorkflowAreaCode, WorkflowStageCode, FiscalYearID, VersionID, DataObjectCode, ActiveFlag, SequenceNo, UserID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowAssignments')
      AND name = N'UX_tblWorkflowAssignments_Assignment'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowAssignments_Assignment
        ON dbo.tblWorkflowAssignments (WorkflowAreaCode, WorkflowStageCode, FiscalYearID, VersionID, DataObjectCode, UserID)
        WHERE ActiveFlag = 1;
END
GO
