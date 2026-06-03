USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbResourceEnvelope (
        ResourceEnvelopeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        FundingTypeID INT NOT NULL,
        FundingSourceID INT NULL,
        ReliabilityCode NVARCHAR(30) NULL,
        RestrictionCode NVARCHAR(30) NULL,
        RestrictionScopeTypeCode NVARCHAR(30) NULL,
        RestrictionReference NVARCHAR(100) NULL,
        RestrictionDescription NVARCHAR(255) NULL,
        RestrictedSectorID INT NULL,
        RestrictedProgramID INT NULL,
        RestrictedSubProgramID INT NULL,
        RestrictedOrgUnitID INT NULL,
        RestrictedActivityID INT NULL,
        RestrictedEconomicItemID INT NULL,
        RestrictedProjectReference NVARCHAR(100) NULL,
        FinancingInstrumentCode NVARCHAR(50) NULL,
        OuterYearAssumptionBasisCode NVARCHAR(50) NULL,
        CurrentYearAmount DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CurrentYearAmount DEFAULT ((0)),
        BP1Amount DECIMAL(19,6) NULL,
        BP2Amount DECIMAL(19,6) NULL,
        BP3Amount DECIMAL(19,6) NULL,
        BP4Amount DECIMAL(19,6) NULL,
        BP5Amount DECIMAL(19,6) NULL,
        BP6Amount DECIMAL(19,6) NULL,
        BP7Amount DECIMAL(19,6) NULL,
        BP8Amount DECIMAL(19,6) NULL,
        BP9Amount DECIMAL(19,6) NULL,
        BP10Amount DECIMAL(19,6) NULL,
        BP11Amount DECIMAL(19,6) NULL,
        BP12Amount DECIMAL(19,6) NULL,
        OuterYear1Amount DECIMAL(19,6) NULL,
        OuterYear2Amount DECIMAL(19,6) NULL,
        OuterYear3Amount DECIMAL(19,6) NULL,
        OuterYear4Amount DECIMAL(19,6) NULL,
        OuterYear5Amount DECIMAL(19,6) NULL,
        EnvelopeNotes NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_tblSbFundingType'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_tblSbFundingType
        FOREIGN KEY (FundingTypeID)
        REFERENCES dbo.tblSbFundingType (FundingTypeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_tblSbFundingSource'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_tblSbFundingSource
        FOREIGN KEY (FundingSourceID)
        REFERENCES dbo.tblSbFundingSource (FundingSourceID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
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
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
        ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
        INCLUDE (FundingTypeID, FundingSourceID, ReliabilityCode, RestrictionCode, RestrictionScopeTypeCode, RestrictionReference, RestrictionDescription, RestrictedSectorID, RestrictedProgramID, RestrictedSubProgramID, RestrictedOrgUnitID, RestrictedActivityID, RestrictedEconomicItemID, RestrictedProjectReference, FinancingInstrumentCode, OuterYearAssumptionBasisCode, CurrentYearAmount, OuterYear1Amount, OuterYear2Amount, OuterYear3Amount, OuterYear4Amount, OuterYear5Amount);
END
GO
