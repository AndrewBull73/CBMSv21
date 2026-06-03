/*
Budget Execution RIE v1

Creates the minimal persistence needed for:
- RIE headers
- RIE lines tied to execution opening balances
- optional linkage from commitments back to an approved RIE
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    THROW 50034, 'Budget Execution RIE requires dbo.tblBeExecutionOpeningBalance to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeCommitment', 'U') IS NULL
BEGIN
    THROW 50035, 'Budget Execution RIE requires dbo.tblBeCommitment to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeRie', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeRie (
        RieID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        RieNo NVARCHAR(50) NOT NULL,
        RieTitle NVARCHAR(200) NOT NULL,
        RieStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeRie_Status DEFAULT (N'DRAFT'),
        RieDate DATE NOT NULL
            CONSTRAINT DF_tblBeRie_RieDate DEFAULT (CAST(GETDATE() AS DATE)),
        EffectiveDate DATE NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeRie_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeRie_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeRie_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeRie_Status CHECK (
            RieStatusCode IN (N'DRAFT', N'APPROVED', N'CANCELLED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeRieLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeRieLine (
        RieLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        RieID INT NOT NULL,
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
        RieAmount DECIMAL(19,6) NOT NULL,
        Notes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeRieLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeRieLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeRieLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF COL_LENGTH('dbo.tblBeCommitment', 'RieID') IS NULL
BEGIN
    ALTER TABLE dbo.tblBeCommitment
    ADD RieID INT NULL;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeRie_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeRie')
)
BEGIN
    ALTER TABLE dbo.tblBeRie
    ADD CONSTRAINT FK_tblBeRie_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeRieLine_Rie'
      AND parent_object_id = OBJECT_ID('dbo.tblBeRieLine')
)
BEGIN
    ALTER TABLE dbo.tblBeRieLine
    ADD CONSTRAINT FK_tblBeRieLine_Rie
        FOREIGN KEY (RieID)
        REFERENCES dbo.tblBeRie (RieID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeRieLine_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeRieLine')
)
BEGIN
    ALTER TABLE dbo.tblBeRieLine
    ADD CONSTRAINT FK_tblBeRieLine_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeRieLine_OpeningBalance'
      AND parent_object_id = OBJECT_ID('dbo.tblBeRieLine')
)
BEGIN
    ALTER TABLE dbo.tblBeRieLine
    ADD CONSTRAINT FK_tblBeRieLine_OpeningBalance
        FOREIGN KEY (ExecutionOpeningBalanceID)
        REFERENCES dbo.tblBeExecutionOpeningBalance (ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeCommitment_Rie'
      AND parent_object_id = OBJECT_ID('dbo.tblBeCommitment')
)
BEGIN
    ALTER TABLE dbo.tblBeCommitment
    ADD CONSTRAINT FK_tblBeCommitment_Rie
        FOREIGN KEY (RieID)
        REFERENCES dbo.tblBeRie (RieID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblBeRieLine_RieAmount'
      AND parent_object_id = OBJECT_ID('dbo.tblBeRieLine')
)
BEGIN
    ALTER TABLE dbo.tblBeRieLine
    ADD CONSTRAINT CK_tblBeRieLine_RieAmount CHECK (RieAmount > 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeRie')
      AND name = 'IX_tblBeRie_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeRie_Context
        ON dbo.tblBeRie (FiscalYearID, ExecutionVersionID, RieStatusCode, CreatedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeRie')
      AND name = 'UX_tblBeRie_Number'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeRie_Number
        ON dbo.tblBeRie (FiscalYearID, ExecutionVersionID, RieNo)
        WHERE ActiveFlag = 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeRieLine')
      AND name = 'IX_tblBeRieLine_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeRieLine_Context
        ON dbo.tblBeRieLine (FiscalYearID, ExecutionVersionID, ActiveFlag, RieID, RieLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeRieLine')
      AND name = 'IX_tblBeRieLine_Balance'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeRieLine_Balance
        ON dbo.tblBeRieLine (ExecutionOpeningBalanceID, ActiveFlag, RieID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeCommitment')
      AND name = 'IX_tblBeCommitment_Rie'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeCommitment_Rie
        ON dbo.tblBeCommitment (RieID, FiscalYearID, ExecutionVersionID, CommitmentStatusCode);
END;
