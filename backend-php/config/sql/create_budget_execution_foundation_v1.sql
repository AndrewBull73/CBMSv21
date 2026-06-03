/*
Budget Execution Foundation v1

Creates the minimal persistence needed for:
- execution version rollover tracking
- opening execution balances loaded from approved submission lines
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblVersions', 'U') IS NULL
BEGIN
    THROW 50030, 'Budget Execution Foundation requires dbo.tblVersions to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeExecutionRolloverRun', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeExecutionRolloverRun (
        ExecutionRolloverRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        SourceFiscalYearID INT NOT NULL,
        SourceVersionID INT NOT NULL,
        RolloverStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_Status DEFAULT (N'STARTED'),
        SourceLineCount INT NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_SourceLineCount DEFAULT ((0)),
        InsertedBalanceLineCount INT NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_InsertedCount DEFAULT ((0)),
        TotalOpeningAmount DECIMAL(19,6) NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_TotalAmount DEFAULT ((0)),
        Notes NVARCHAR(1000) NULL,
        StartedBy INT NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_StartedBy DEFAULT (1),
        StartedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeExecutionRolloverRun_StartedDate DEFAULT (SYSDATETIME()),
        CompletedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeExecutionRolloverRun_Status CHECK (
            RolloverStatusCode IN (N'STARTED', N'COMPLETED', N'FAILED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeExecutionOpeningBalance (
        ExecutionOpeningBalanceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ExecutionRolloverRunID INT NULL,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        SourceFiscalYearID INT NOT NULL,
        SourceVersionID INT NOT NULL,
        SourceSubmissionID INT NULL,
        SourceSubmissionLineID INT NULL,
        DataObjectCode NVARCHAR(50) NOT NULL,
        OrgUnitID INT NULL,
        SectorID INT NULL,
        ProgramID INT NULL,
        SubProgramID INT NULL,
        ProjectID INT NULL,
        ActivityID INT NULL,
        FundingTypeID INT NULL,
        FundingSourceID INT NULL,
        EconomicItemID INT NULL,
        BidTitle NVARCHAR(200) NULL,
        OpeningAmount DECIMAL(19,6) NOT NULL,
        CurrentAuthorizedAmount DECIMAL(19,6) NOT NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeExecutionOpeningBalance_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeExecutionOpeningBalance_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeExecutionOpeningBalance_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeExecutionRolloverRun_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionRolloverRun')
)
BEGIN
    ALTER TABLE dbo.tblBeExecutionRolloverRun
    ADD CONSTRAINT FK_tblBeExecutionRolloverRun_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeExecutionRolloverRun_SourceVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionRolloverRun')
)
BEGIN
    ALTER TABLE dbo.tblBeExecutionRolloverRun
    ADD CONSTRAINT FK_tblBeExecutionRolloverRun_SourceVersion
        FOREIGN KEY (SourceVersionID, SourceFiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeExecutionOpeningBalance_RolloverRun'
      AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
)
BEGIN
    ALTER TABLE dbo.tblBeExecutionOpeningBalance
    ADD CONSTRAINT FK_tblBeExecutionOpeningBalance_RolloverRun
        FOREIGN KEY (ExecutionRolloverRunID)
        REFERENCES dbo.tblBeExecutionRolloverRun (ExecutionRolloverRunID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeExecutionOpeningBalance_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
)
BEGIN
    ALTER TABLE dbo.tblBeExecutionOpeningBalance
    ADD CONSTRAINT FK_tblBeExecutionOpeningBalance_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeExecutionOpeningBalance_SourceVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
)
BEGIN
    ALTER TABLE dbo.tblBeExecutionOpeningBalance
    ADD CONSTRAINT FK_tblBeExecutionOpeningBalance_SourceVersion
        FOREIGN KEY (SourceVersionID, SourceFiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE name = 'FK_tblBeExecutionOpeningBalance_SourceSubmission'
         AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
   )
BEGIN
    ALTER TABLE dbo.tblBeExecutionOpeningBalance
    ADD CONSTRAINT FK_tblBeExecutionOpeningBalance_SourceSubmission
        FOREIGN KEY (SourceSubmissionID)
        REFERENCES dbo.tblSbFundingSubmission (StrategicFundingSubmissionID);
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE name = 'FK_tblBeExecutionOpeningBalance_SourceSubmissionLine'
         AND parent_object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
   )
BEGIN
    ALTER TABLE dbo.tblBeExecutionOpeningBalance
    ADD CONSTRAINT FK_tblBeExecutionOpeningBalance_SourceSubmissionLine
        FOREIGN KEY (SourceSubmissionLineID)
        REFERENCES dbo.tblSbFundingSubmissionLine (StrategicFundingSubmissionLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeExecutionRolloverRun')
      AND name = 'IX_tblBeExecutionRolloverRun_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeExecutionRolloverRun_Context
        ON dbo.tblBeExecutionRolloverRun (FiscalYearID, ExecutionVersionID, StartedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
      AND name = 'IX_tblBeExecutionOpeningBalance_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeExecutionOpeningBalance_Context
        ON dbo.tblBeExecutionOpeningBalance (FiscalYearID, ExecutionVersionID, ActiveFlag, ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeExecutionOpeningBalance')
      AND name = 'UX_tblBeExecutionOpeningBalance_SourceLine'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeExecutionOpeningBalance_SourceLine
        ON dbo.tblBeExecutionOpeningBalance (FiscalYearID, ExecutionVersionID, SourceSubmissionLineID)
        WHERE SourceSubmissionLineID IS NOT NULL;
END;
