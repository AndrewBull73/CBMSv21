USE [CBMSv2];
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
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO
