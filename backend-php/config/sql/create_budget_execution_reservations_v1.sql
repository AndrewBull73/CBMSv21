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
