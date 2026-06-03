USE [CBMSv2];
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'ReliabilityCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD ReliabilityCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionScopeTypeCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionScopeTypeCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionReference NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionDescription') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionDescription NVARCHAR(255) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSectorID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSectorID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSubProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSubProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedOrgUnitID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedOrgUnitID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedActivityID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedActivityID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedEconomicItemID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedEconomicItemID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProjectReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProjectReference NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'FinancingInstrumentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD FinancingInstrumentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYearAssumptionBasisCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYearAssumptionBasisCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear3Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear3Amount DECIMAL(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear4Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear4Amount DECIMAL(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear5Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear5Amount DECIMAL(19,6) NULL;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
    ON dbo.tblSbResourceEnvelope (
        FiscalYearID,
        VersionID,
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    DROP INDEX IX_tblSbResourceEnvelope_Context
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
    ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
    INCLUDE (
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictionDescription,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode,
        CurrentYearAmount,
        OuterYear1Amount,
        OuterYear2Amount,
        OuterYear3Amount,
        OuterYear4Amount,
        OuterYear5Amount
    );
GO
