SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Step 08
Install the current CBMSv21 budget-execution foundation.
This is a curated merged copy of the active execution SQL assets.
*/

/* Source: backend-php\config\sql\create_budget_execution_foundation_v1.sql */
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


/* Source: backend-php\config\sql\create_budget_execution_warrants_v1.sql */
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


/* Source: backend-php\config\sql\create_budget_execution_reservations_v1.sql */
/*
Budget Execution Reservations v1

Creates the minimal persistence needed for:
- reservation headers
- reservation lines tied to execution opening balances
*/

SET NOCOUNT ON;

IF OBJECT_ID('dbo.tblBeExecutionOpeningBalance', 'U') IS NULL
BEGIN
    THROW 50032, 'Budget Execution Reservations require dbo.tblBeExecutionOpeningBalance to exist first.', 1;
END;

IF OBJECT_ID('dbo.tblBeReservation', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeReservation (
        ReservationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ExecutionVersionID INT NOT NULL,
        ReservationNo NVARCHAR(50) NOT NULL,
        ReservationTitle NVARCHAR(200) NOT NULL,
        ReservationStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblBeReservation_Status DEFAULT (N'DRAFT'),
        ReservationDate DATE NOT NULL
            CONSTRAINT DF_tblBeReservation_ReservationDate DEFAULT (CAST(GETDATE() AS DATE)),
        EffectiveDate DATE NULL,
        Notes NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeReservation_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeReservation_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeReservation_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblBeReservation_Status CHECK (
            ReservationStatusCode IN (N'DRAFT', N'APPROVED', N'CANCELLED')
        )
    );
END;

IF OBJECT_ID('dbo.tblBeReservationLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblBeReservationLine (
        ReservationLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ReservationID INT NOT NULL,
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
        ReservationAmount DECIMAL(19,6) NOT NULL,
        Notes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblBeReservationLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblBeReservationLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblBeReservationLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeReservation_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeReservation')
)
BEGIN
    ALTER TABLE dbo.tblBeReservation
    ADD CONSTRAINT FK_tblBeReservation_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeReservationLine_Reservation'
      AND parent_object_id = OBJECT_ID('dbo.tblBeReservationLine')
)
BEGIN
    ALTER TABLE dbo.tblBeReservationLine
    ADD CONSTRAINT FK_tblBeReservationLine_Reservation
        FOREIGN KEY (ReservationID)
        REFERENCES dbo.tblBeReservation (ReservationID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeReservationLine_ExecutionVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblBeReservationLine')
)
BEGIN
    ALTER TABLE dbo.tblBeReservationLine
    ADD CONSTRAINT FK_tblBeReservationLine_ExecutionVersion
        FOREIGN KEY (ExecutionVersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblBeReservationLine_OpeningBalance'
      AND parent_object_id = OBJECT_ID('dbo.tblBeReservationLine')
)
BEGIN
    ALTER TABLE dbo.tblBeReservationLine
    ADD CONSTRAINT FK_tblBeReservationLine_OpeningBalance
        FOREIGN KEY (ExecutionOpeningBalanceID)
        REFERENCES dbo.tblBeExecutionOpeningBalance (ExecutionOpeningBalanceID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblBeReservationLine_ReservationAmount'
      AND parent_object_id = OBJECT_ID('dbo.tblBeReservationLine')
)
BEGIN
    ALTER TABLE dbo.tblBeReservationLine
    ADD CONSTRAINT CK_tblBeReservationLine_ReservationAmount CHECK (ReservationAmount > 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeReservation')
      AND name = 'IX_tblBeReservation_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeReservation_Context
        ON dbo.tblBeReservation (FiscalYearID, ExecutionVersionID, ReservationStatusCode, CreatedDate DESC);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeReservation')
      AND name = 'UX_tblBeReservation_Number'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblBeReservation_Number
        ON dbo.tblBeReservation (FiscalYearID, ExecutionVersionID, ReservationNo)
        WHERE ActiveFlag = 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeReservationLine')
      AND name = 'IX_tblBeReservationLine_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeReservationLine_Context
        ON dbo.tblBeReservationLine (FiscalYearID, ExecutionVersionID, ActiveFlag, ReservationID, ReservationLineID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblBeReservationLine')
      AND name = 'IX_tblBeReservationLine_Balance'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblBeReservationLine_Balance
        ON dbo.tblBeReservationLine (ExecutionOpeningBalanceID, ActiveFlag, ReservationID);
END;


/* Source: backend-php\config\sql\create_budget_execution_supplementaries_v1.sql */
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


/* Source: backend-php\config\sql\create_budget_execution_commitments_v1.sql */
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


/* Source: backend-php\config\sql\create_budget_execution_rie_v1.sql */
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
