/*
Budget Execution Warrants v1

Creates the minimal persistence needed for:
- warrant batch headers
- warrant release lines tied to execution opening balances
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    THROW 50031, 'Budget Execution Warrants require dbo.tblBeExecutionOpeningBalance to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeWarrant', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeWarrant (
        WarrantID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        WarrantNo NVARCHAR(50) NOT NULL,
        WarrantTitle NVARCHAR(200) NOT NULL,
        WarrantStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeWarrant_Status DEFAULT (N'DRAFT'),
        WarrantDate DATE NOT NULL
            CONSTRAINT DF_tblBeWarrant_WarrantDate DEFAULT (CAST(GETDATE() AS DATE)),
        EffectiveDate DATE NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeWarrant_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeWarrant_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeWarrant_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeWarrant_Status CHECK (
            WarrantStatusCode IN (N'DRAFT', N'APPROVED', N'CANCELLED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeWarrantLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeWarrantLine (
        WarrantLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WarrantID INT NOT NULL,
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
        ReleaseAmount DECIMAL(19,6) NOT NULL,
        Notes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeWarrantLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeWarrantLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeWarrantLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeWarrant_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeWarrant')
)
BEGIN
    ALTER TABLE dbo.tblBeWarrant
    ADD CONSTRAINT FK_tblBeWarrant_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeWarrantLine_Warrant'
      AND parent_object_id = OBJECT_ID('dbo.tblBeWarrantLine')
)
BEGIN
    ALTER TABLE dbo.tblBeWarrantLine
    ADD CONSTRAINT FK_tblBeWarrantLine_Warrant
        FOREIGN KEY (WarrantID)
        REFERENCES dbo.tblBeWarrant (WarrantID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeWarrantLine_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeWarrantLine')
)
BEGIN
    ALTER TABLE dbo.tblBeWarrantLine
    ADD CONSTRAINT FK_tblBeWarrantLine_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeWarrantLine_OpeningBalance'
      AND parent_object_id = OBJECT_ID('dbo.tblBeWarrantLine')
)
BEGIN
    ALTER TABLE dbo.tblBeWarrantLine
    ADD CONSTRAINT FK_tblBeWarrantLine_OpeningBalance
        FOREIGN KEY (ExecutionOpeningBalanceID)
        REFERENCES dbo.tblBeExecutionOpeningBalance (ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblBeWarrantLine_ReleaseAmount'
      AND parent_object_id = OBJECT_ID('dbo.tblBeWarrantLine')
)
BEGIN
    ALTER TABLE dbo.tblBeWarrantLine
    ADD CONSTRAINT CK_tblBeWarrantLine_ReleaseAmount CHECK (ReleaseAmount > 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeWarrant')
      AND name = 'IX_tblBeWarrant_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeWarrant_Context
        ON dbo.tblBeWarrant (FiscalYearID, ExecutionVersionID, WarrantStatusCode, CreatedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeWarrant')
      AND name = 'UX_tblBeWarrant_Number'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeWarrant_Number
        ON dbo.tblBeWarrant (FiscalYearID, ExecutionVersionID, WarrantNo)
        WHERE ActiveFlag = 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeWarrantLine')
      AND name = 'IX_tblBeWarrantLine_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeWarrantLine_Context
        ON dbo.tblBeWarrantLine (FiscalYearID, ExecutionVersionID, ActiveFlag, WarrantID, WarrantLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeWarrantLine')
      AND name = 'IX_tblBeWarrantLine_Balance'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeWarrantLine_Balance
        ON dbo.tblBeWarrantLine (ExecutionOpeningBalanceID, ActiveFlag, WarrantID);
END;
