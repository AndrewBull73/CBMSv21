USE [CBMSv2];
GO

/*
Add ceiling-status columns to tblTransactionInput for run-time enforcement tracking.
Idempotent: safe to run multiple times.
*/

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingStatus') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingStatus nvarchar(20) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingStatusCheck') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingStatusCheck nvarchar(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingErrorMessage') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingErrorMessage nvarchar(500) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingFailedFlag') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingFailedFlag bit NOT NULL CONSTRAINT DF_tblTransactionInput_CeilingFailedFlag DEFAULT ((0));
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingDefinitionID') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingDefinitionID int NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingEngine') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingEngine nvarchar(20) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingLastCheckedDate') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingLastCheckedDate datetime NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingCheckDate') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingCheckDate datetime NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP1') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP1 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP2') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP2 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP3') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP3 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP4') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP4 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP5') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP5 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP6') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP6 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP7') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP7 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP8') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP8 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP9') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP9 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP10') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP10 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP11') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP11 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedBP12') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedBP12 decimal(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblTransactionInput', 'CeilingAppliedTotal') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput ADD CeilingAppliedTotal decimal(19,6) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblTransactionInput_tblCeilingDefinition'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionInput')
)
AND COL_LENGTH('dbo.tblTransactionInput', 'CeilingDefinitionID') IS NOT NULL
AND OBJECT_ID('dbo.tblCeilingDefinition', 'U') IS NOT NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInput
    ADD CONSTRAINT FK_tblTransactionInput_tblCeilingDefinition
        FOREIGN KEY (CeilingDefinitionID)
        REFERENCES dbo.tblCeilingDefinition (CeilingDefinitionID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionInput')
      AND name = 'IX_tblTransactionInput_CeilingStatus'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblTransactionInput_CeilingStatus
        ON dbo.tblTransactionInput (CeilingFailedFlag, CeilingStatus)
        INCLUDE (TransactionID, HeadRecordID, FiscalYearID, VersionID, CeilingDefinitionID, CeilingEngine, CeilingLastCheckedDate);
END
GO
