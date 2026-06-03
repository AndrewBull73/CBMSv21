/*
Budget Execution Supplementary Budgets v1

Creates the minimal persistence needed for:
- supplementary budget batch headers
- supplementary adjustment lines tied to execution opening balances
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    THROW 50035, 'Budget Execution Supplementaries require dbo.tblBeExecutionOpeningBalance to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeSupplementaryBudget', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeSupplementaryBudget (
        SupplementaryBudgetID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        SupplementaryNo NVARCHAR(50) NOT NULL,
        SupplementaryTitle NVARCHAR(200) NOT NULL,
        SupplementaryStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudget_Status DEFAULT (N'DRAFT'),
        SupplementaryDate DATE NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudget_SupplementaryDate DEFAULT (CAST(GETDATE() AS DATE)),
        EffectiveDate DATE NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudget_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudget_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudget_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeSupplementaryBudget_Status CHECK (
            SupplementaryStatusCode IN (N'DRAFT', N'APPROVED', N'CANCELLED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeSupplementaryBudgetLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeSupplementaryBudgetLine (
        SupplementaryBudgetLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SupplementaryBudgetID INT NOT NULL,
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
        AdjustmentAmount DECIMAL(19,6) NOT NULL,
        Notes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudgetLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudgetLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeSupplementaryBudgetLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeSupplementaryBudget_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeSupplementaryBudget')
)
BEGIN
    ALTER TABLE dbo.tblBeSupplementaryBudget
    ADD CONSTRAINT FK_tblBeSupplementaryBudget_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeSupplementaryBudgetLine_SupplementaryBudget'
      AND parent_object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
)
BEGIN
    ALTER TABLE dbo.tblBeSupplementaryBudgetLine
    ADD CONSTRAINT FK_tblBeSupplementaryBudgetLine_SupplementaryBudget
        FOREIGN KEY (SupplementaryBudgetID)
        REFERENCES dbo.tblBeSupplementaryBudget (SupplementaryBudgetID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeSupplementaryBudgetLine_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
)
BEGIN
    ALTER TABLE dbo.tblBeSupplementaryBudgetLine
    ADD CONSTRAINT FK_tblBeSupplementaryBudgetLine_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeSupplementaryBudgetLine_OpeningBalance'
      AND parent_object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
)
BEGIN
    ALTER TABLE dbo.tblBeSupplementaryBudgetLine
    ADD CONSTRAINT FK_tblBeSupplementaryBudgetLine_OpeningBalance
        FOREIGN KEY (ExecutionOpeningBalanceID)
        REFERENCES dbo.tblBeExecutionOpeningBalance (ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblBeSupplementaryBudgetLine_AdjustmentAmount'
      AND parent_object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
)
BEGIN
    ALTER TABLE dbo.tblBeSupplementaryBudgetLine
    ADD CONSTRAINT CK_tblBeSupplementaryBudgetLine_AdjustmentAmount CHECK (AdjustmentAmount <> 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeSupplementaryBudget')
      AND name = 'IX_tblBeSupplementaryBudget_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeSupplementaryBudget_Context
        ON dbo.tblBeSupplementaryBudget (FiscalYearID, ExecutionVersionID, SupplementaryStatusCode, CreatedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeSupplementaryBudget')
      AND name = 'UX_tblBeSupplementaryBudget_Number'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeSupplementaryBudget_Number
        ON dbo.tblBeSupplementaryBudget (FiscalYearID, ExecutionVersionID, SupplementaryNo)
        WHERE ActiveFlag = 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
      AND name = 'IX_tblBeSupplementaryBudgetLine_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeSupplementaryBudgetLine_Context
        ON dbo.tblBeSupplementaryBudgetLine (FiscalYearID, ExecutionVersionID, ActiveFlag, SupplementaryBudgetID, SupplementaryBudgetLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeSupplementaryBudgetLine')
      AND name = 'IX_tblBeSupplementaryBudgetLine_Balance'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeSupplementaryBudgetLine_Balance
        ON dbo.tblBeSupplementaryBudgetLine (ExecutionOpeningBalanceID, ActiveFlag, SupplementaryBudgetID);
END;
