USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbResourceEnvelope does not exist. Run create_tblSbResourceEnvelope.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_RestrictedProject'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_RestrictedProject
        FOREIGN KEY (RestrictedProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding ON dbo.tblSbResourceEnvelope;
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
        RestrictedProjectID,
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
    DROP INDEX IX_tblSbResourceEnvelope_Context ON dbo.tblSbResourceEnvelope;
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
        RestrictedProjectID,
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
