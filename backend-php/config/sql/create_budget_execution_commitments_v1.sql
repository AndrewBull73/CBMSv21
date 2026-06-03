/*
Budget Execution Commitments v1

Creates the minimal persistence needed for:
- commitment headers
- commitment lines tied to execution opening balances
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    THROW 50033, 'Budget Execution Commitments require dbo.tblBeExecutionOpeningBalance to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeCommitment', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeCommitment (
        CommitmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        CommitmentNo NVARCHAR(50) NOT NULL,
        CommitmentTitle NVARCHAR(200) NOT NULL,
        CommitmentStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeCommitment_Status DEFAULT (N'DRAFT'),
        CommitmentDate DATE NOT NULL
            CONSTRAINT DF_tblBeCommitment_CommitmentDate DEFAULT (CAST(GETDATE() AS DATE)),
        EffectiveDate DATE NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeCommitment_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeCommitment_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeCommitment_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeCommitment_Status CHECK (
            CommitmentStatusCode IN (N'DRAFT', N'APPROVED', N'CANCELLED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeCommitmentLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeCommitmentLine (
        CommitmentLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CommitmentID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        ExecutionOpeningBalanceID INT NOT NULL,
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
        CommitmentAmount DECIMAL(19,6) NOT NULL,
        Notes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeCommitmentLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeCommitmentLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeCommitmentLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeCommitment_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitment')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitment
    ADD CONSTRAINT FK_tblBeCommitment_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeCommitmentLine_Commitment'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitmentLine
    ADD CONSTRAINT FK_tblBeCommitmentLine_Commitment
        FOREIGN KEY (CommitmentID)
        REFERENCES dbo.tblBeCommitment (CommitmentID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeCommitmentLine_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitmentLine
    ADD CONSTRAINT FK_tblBeCommitmentLine_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeCommitmentLine_OpeningBalance'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitmentLine
    ADD CONSTRAINT FK_tblBeCommitmentLine_OpeningBalance
        FOREIGN KEY (ExecutionOpeningBalanceID)
        REFERENCES dbo.tblBeExecutionOpeningBalance (ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblBeCommitmentLine_CommitmentAmount'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitmentLine
    ADD CONSTRAINT CK_tblBeCommitmentLine_CommitmentAmount CHECK (CommitmentAmount > 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeCommitment')
      AND name = 'IX_tblBeCommitment_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeCommitment_Context
        ON dbo.tblBeCommitment (FiscalYearID, ExecutionVersionID, CommitmentStatusCode, CreatedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeCommitment')
      AND name = 'UX_tblBeCommitment_Number'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeCommitment_Number
        ON dbo.tblBeCommitment (FiscalYearID, ExecutionVersionID, CommitmentNo)
        WHERE ActiveFlag = 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
      AND name = 'IX_tblBeCommitmentLine_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeCommitmentLine_Context
        ON dbo.tblBeCommitmentLine (FiscalYearID, ExecutionVersionID, ActiveFlag, CommitmentID, CommitmentLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeCommitmentLine')
      AND name = 'IX_tblBeCommitmentLine_Balance'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeCommitmentLine_Balance
        ON dbo.tblBeCommitmentLine (ExecutionOpeningBalanceID, ActiveFlag, CommitmentID);
END;
