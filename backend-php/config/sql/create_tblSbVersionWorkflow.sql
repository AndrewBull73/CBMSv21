/*
Strategic Budgeting Version Workflow
------------------------------------
Adds workflow state for each fiscal year/version context used by the
Strategic Budgeting module.
*/

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID('dbo.tblVersions', 'U') IS NULL
BEGIN
    THROW 50010, 'Strategic workflow requires dbo.tblVersions to exist first.', 1;
END;
GO

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbVersionWorkflow (
        StrategicVersionWorkflowID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        WorkflowStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbVersionWorkflow_WorkflowStatusCode DEFAULT (N'DRAFT'),
        StatusNote NVARCHAR(500) NULL,
        SubmittedBy INT NULL,
        SubmittedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        LockedBy INT NULL,
        LockedDate DATETIME2(0) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_UpdatedBy DEFAULT (1),
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_UpdatedDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblSbVersionWorkflow_StatusCode CHECK (
            WorkflowStatusCode IN (N'DRAFT', N'SUBMITTED', N'APPROVED', N'LOCKED')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbVersionWorkflow_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
)
BEGIN
    ALTER TABLE dbo.tblSbVersionWorkflow
    ADD CONSTRAINT FK_tblSbVersionWorkflow_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
      AND name = 'UX_tblSbVersionWorkflow_VersionID_FiscalYearID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbVersionWorkflow_VersionID_FiscalYearID
        ON dbo.tblSbVersionWorkflow (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
      AND name = 'IX_tblSbVersionWorkflow_StatusCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbVersionWorkflow_StatusCode
        ON dbo.tblSbVersionWorkflow (WorkflowStatusCode, FiscalYearID, VersionID);
END;
GO

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbVersionWorkflowHistory (
        StrategicWorkflowHistoryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        WorkflowActionCode NVARCHAR(20) NOT NULL,
        FromStatusCode NVARCHAR(20) NULL,
        ToStatusCode NVARCHAR(20) NOT NULL,
        StatusNote NVARCHAR(500) NULL,
        ActionBy INT NOT NULL,
        ActionDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflowHistory_ActionDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblSbVersionWorkflowHistory_ActionCode CHECK (
            WorkflowActionCode IN (N'SUBMIT', N'APPROVE', N'LOCK', N'REOPEN', N'UNLOCK')
        ),
        CONSTRAINT CK_tblSbVersionWorkflowHistory_ToStatusCode CHECK (
            ToStatusCode IN (N'DRAFT', N'SUBMITTED', N'APPROVED', N'LOCKED')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbVersionWorkflowHistory_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbVersionWorkflowHistory')
)
BEGIN
    ALTER TABLE dbo.tblSbVersionWorkflowHistory
    ADD CONSTRAINT FK_tblSbVersionWorkflowHistory_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflowHistory')
      AND name = 'IX_tblSbVersionWorkflowHistory_Version'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbVersionWorkflowHistory_Version
        ON dbo.tblSbVersionWorkflowHistory (FiscalYearID, VersionID, ActionDate DESC, StrategicWorkflowHistoryID DESC);
END;
GO
