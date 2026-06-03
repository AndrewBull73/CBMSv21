USE [CBMSv2];
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode
        ON dbo.tblSbOrgUnit (SourceFiscalYearID, SourceDataObjectCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceDataObjectCode IS NOT NULL;
END
GO

IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingType (
        FundingTypeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FundingTypeCode NVARCHAR(50) NOT NULL,
        FundingTypeName NVARCHAR(150) NOT NULL,
        FundingTypeDescription NVARCHAR(MAX) NULL,
        DefaultPhasingProfileID INT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFundingType_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFundingType_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFundingType_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingType')
      AND name = 'UX_tblSbFundingType_FundingTypeCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFundingType_FundingTypeCode
        ON dbo.tblSbFundingType (FundingTypeCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingType')
      AND name = 'IX_tblSbFundingType_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingType_SourceLookup
        ON dbo.tblSbFundingType (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'FundingTypeID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD FundingTypeID INT NULL;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbFundingSource_FundingTypeCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSource')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    DROP CONSTRAINT CK_tblSbFundingSource_FundingTypeCode;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSector')
      AND name = 'IX_tblSbSector_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSector_SourceLookup
        ON dbo.tblSbSector (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgram')
      AND name = 'IX_tblSbProgram_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgram_SourceLookup
        ON dbo.tblSbProgram (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSubProgram')
      AND name = 'IX_tblSbSubProgram_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSubProgram_SourceLookup
        ON dbo.tblSbSubProgram (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbEconomicItem')
      AND name = 'IX_tblSbEconomicItem_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbEconomicItem_SourceLookup
        ON dbo.tblSbEconomicItem (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjective')
      AND name = 'IX_tblSbObjective_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbObjective_SourceLookup
        ON dbo.tblSbObjective (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicator')
      AND name = 'IX_tblSbIndicator_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicator_SourceLookup
        ON dbo.tblSbIndicator (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
      AND name = 'IX_tblSbIndicatorTarget_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicatorTarget_SourceLookup
        ON dbo.tblSbIndicatorTarget (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOutput')
      AND name = 'IX_tblSbOutput_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbOutput_SourceLookup
        ON dbo.tblSbOutput (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivity')
      AND name = 'IX_tblSbActivity_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivity_SourceLookup
        ON dbo.tblSbActivity (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'IX_tblSbFundingSource_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_SourceLookup
        ON dbo.tblSbFundingSource (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

MERGE dbo.tblSbFundingType AS target
USING (
    SELECT N'DOMESTIC' AS FundingTypeCode, N'Domestic' AS FundingTypeName
    UNION ALL SELECT N'GRANT', N'Grant'
    UNION ALL SELECT N'LOAN', N'Loan'
) AS src
ON target.FundingTypeCode = src.FundingTypeCode
WHEN MATCHED THEN
    UPDATE SET
        target.FundingTypeName = src.FundingTypeName,
        target.ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT (
        FundingTypeCode,
        FundingTypeName,
        FundingTypeDescription,
        ActiveFlag,
        CreatedBy,
        CreatedDate
    )
    VALUES (
        src.FundingTypeCode,
        src.FundingTypeName,
        NULL,
        1,
        1,
        SYSDATETIME()
    );
GO

UPDATE fs
SET fs.FundingTypeID = ft.FundingTypeID
FROM dbo.tblSbFundingSource fs
INNER JOIN dbo.tblSbFundingType ft
    ON ft.FundingTypeCode = fs.FundingTypeCode
WHERE fs.FundingTypeID IS NULL
  AND fs.FundingTypeCode IS NOT NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSource_FundingType'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSource')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD CONSTRAINT FK_tblSbFundingSource_FundingType
        FOREIGN KEY (FundingTypeID)
        REFERENCES dbo.tblSbFundingType (FundingTypeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'IX_tblSbFundingSource_FundingTypeID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_FundingTypeID
        ON dbo.tblSbFundingSource (FundingTypeID, ActiveFlag);
END
GO
