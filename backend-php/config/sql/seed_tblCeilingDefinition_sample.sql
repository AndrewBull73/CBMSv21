USE [CBMSv2];
GO

/*
Auto-seed ceiling definitions + balance initialization.

Usage modes:
1) Auto from transaction row (recommended)
   - Set @AutoSeedTransactionID = <your TransactionID>

2) Manual values
   - Leave @AutoSeedTransactionID = NULL
   - Set @DataObjectCode/@TransactionTypeCode/@Segment1..@Segment20
*/

DECLARE @FiscalYearID int = 2026;
DECLARE @VersionID int = 1;
DECLARE @CreatedBy int = 1;
DECLARE @ResetBalances bit = 0; -- set to 1 only when you want to reset balance = ceiling values

DECLARE @AutoSeedTransactionID int = 2; -- set NULL to use manual dimension values below

DECLARE @CeilingBP1 decimal(19,6) = 1000;
DECLARE @CeilingBP2 decimal(19,6) = 1000;
DECLARE @CeilingBP3 decimal(19,6) = 1000;
DECLARE @CeilingBP4 decimal(19,6) = 1000;
DECLARE @CeilingBP5 decimal(19,6) = 1000;
DECLARE @CeilingBP6 decimal(19,6) = 1000;
DECLARE @CeilingBP7 decimal(19,6) = 1000;
DECLARE @CeilingBP8 decimal(19,6) = 1000;
DECLARE @CeilingBP9 decimal(19,6) = 1000;
DECLARE @CeilingBP10 decimal(19,6) = 1000;
DECLARE @CeilingBP11 decimal(19,6) = 1000;
DECLARE @CeilingBP12 decimal(19,6) = 1000;
DECLARE @CeilingBPTotal decimal(19,6) = 12000;

-- Manual fallback values (used only when @AutoSeedTransactionID IS NULL)
DECLARE @DataObjectCode nvarchar(20) = N'CC100';
DECLARE @TransactionTypeCode nvarchar(20) = N'EXP';
DECLARE @Segment1Code nvarchar(20) = N'S1';
DECLARE @Segment2Code nvarchar(20) = N'S2';
DECLARE @Segment3Code nvarchar(20) = NULL;
DECLARE @Segment4Code nvarchar(20) = NULL;
DECLARE @Segment5Code nvarchar(20) = NULL;
DECLARE @Segment6Code nvarchar(20) = NULL;
DECLARE @Segment7Code nvarchar(20) = NULL;
DECLARE @Segment8Code nvarchar(20) = NULL;
DECLARE @Segment9Code nvarchar(20) = NULL;
DECLARE @Segment10Code nvarchar(20) = NULL;
DECLARE @Segment11Code nvarchar(20) = NULL;
DECLARE @Segment12Code nvarchar(20) = NULL;
DECLARE @Segment13Code nvarchar(20) = NULL;
DECLARE @Segment14Code nvarchar(20) = NULL;
DECLARE @Segment15Code nvarchar(20) = NULL;
DECLARE @Segment16Code nvarchar(20) = NULL;
DECLARE @Segment17Code nvarchar(20) = NULL;
DECLARE @Segment18Code nvarchar(20) = NULL;
DECLARE @Segment19Code nvarchar(20) = NULL;
DECLARE @Segment20Code nvarchar(20) = NULL;

IF @AutoSeedTransactionID IS NOT NULL
BEGIN
    SELECT
        @FiscalYearID = ti.FiscalYearID,
        @VersionID = ti.VersionID,
        @DataObjectCode = ti.DataObjectCode,
        @TransactionTypeCode = ti.TransactionTypeCode,
        @Segment1Code = ti.Segment1Code,
        @Segment2Code = ti.Segment2Code,
        @Segment3Code = ti.Segment3Code,
        @Segment4Code = ti.Segment4Code,
        @Segment5Code = ti.Segment5Code,
        @Segment6Code = ti.Segment6Code,
        @Segment7Code = ti.Segment7Code,
        @Segment8Code = ti.Segment8Code,
        @Segment9Code = ti.Segment9Code,
        @Segment10Code = ti.Segment10Code,
        @Segment11Code = ti.Segment11Code,
        @Segment12Code = ti.Segment12Code,
        @Segment13Code = ti.Segment13Code,
        @Segment14Code = ti.Segment14Code,
        @Segment15Code = ti.Segment15Code,
        @Segment16Code = ti.Segment16Code,
        @Segment17Code = ti.Segment17Code,
        @Segment18Code = ti.Segment18Code,
        @Segment19Code = ti.Segment19Code,
        @Segment20Code = ti.Segment20Code
    FROM dbo.tblTransactionInput ti
    WHERE ti.TransactionID = @AutoSeedTransactionID;

    IF @@ROWCOUNT = 0
    BEGIN
        THROW 51000, 'Auto-seed failed: transaction not found in tblTransactionInput.', 1;
    END
END

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCeilingDefinition
    WHERE FiscalYearID = @FiscalYearID
      AND VersionID = @VersionID
      AND ISNULL(DataObjectCode, '') = ISNULL(@DataObjectCode, '')
      AND ISNULL(TransactionTypeCode, '') = ISNULL(@TransactionTypeCode, '')
      AND ISNULL(Segment1Code, '') = ISNULL(@Segment1Code, '')
      AND ISNULL(Segment2Code, '') = ISNULL(@Segment2Code, '')
      AND ISNULL(Segment3Code, '') = ISNULL(@Segment3Code, '')
      AND ISNULL(Segment4Code, '') = ISNULL(@Segment4Code, '')
      AND ISNULL(Segment5Code, '') = ISNULL(@Segment5Code, '')
      AND ISNULL(Segment6Code, '') = ISNULL(@Segment6Code, '')
      AND ISNULL(Segment7Code, '') = ISNULL(@Segment7Code, '')
      AND ISNULL(Segment8Code, '') = ISNULL(@Segment8Code, '')
      AND ISNULL(Segment9Code, '') = ISNULL(@Segment9Code, '')
      AND ISNULL(Segment10Code, '') = ISNULL(@Segment10Code, '')
      AND ISNULL(Segment11Code, '') = ISNULL(@Segment11Code, '')
      AND ISNULL(Segment12Code, '') = ISNULL(@Segment12Code, '')
      AND ISNULL(Segment13Code, '') = ISNULL(@Segment13Code, '')
      AND ISNULL(Segment14Code, '') = ISNULL(@Segment14Code, '')
      AND ISNULL(Segment15Code, '') = ISNULL(@Segment15Code, '')
      AND ISNULL(Segment16Code, '') = ISNULL(@Segment16Code, '')
      AND ISNULL(Segment17Code, '') = ISNULL(@Segment17Code, '')
      AND ISNULL(Segment18Code, '') = ISNULL(@Segment18Code, '')
      AND ISNULL(Segment19Code, '') = ISNULL(@Segment19Code, '')
      AND ISNULL(Segment20Code, '') = ISNULL(@Segment20Code, '')
)
BEGIN
    INSERT INTO dbo.tblCeilingDefinition (
        FiscalYearID, VersionID,
        DataObjectCode, TransactionTypeCode,
        Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
        Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
        Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
        Segment16Code, Segment17Code, Segment18Code, Segment19Code, Segment20Code,
        CeilingBP1, CeilingBP2, CeilingBP3, CeilingBP4, CeilingBP5, CeilingBP6,
        CeilingBP7, CeilingBP8, CeilingBP9, CeilingBP10, CeilingBP11, CeilingBP12,
        CeilingBPTotal,
        CeilingStatus, ApprovedFlag, ActiveFlag, Priority,
        CreatedBy, CreatedDate
    )
    VALUES (
        @FiscalYearID, @VersionID,
        @DataObjectCode, @TransactionTypeCode,
        @Segment1Code, @Segment2Code, @Segment3Code, @Segment4Code, @Segment5Code,
        @Segment6Code, @Segment7Code, @Segment8Code, @Segment9Code, @Segment10Code,
        @Segment11Code, @Segment12Code, @Segment13Code, @Segment14Code, @Segment15Code,
        @Segment16Code, @Segment17Code, @Segment18Code, @Segment19Code, @Segment20Code,
        @CeilingBP1, @CeilingBP2, @CeilingBP3, @CeilingBP4, @CeilingBP5, @CeilingBP6,
        @CeilingBP7, @CeilingBP8, @CeilingBP9, @CeilingBP10, @CeilingBP11, @CeilingBP12,
        @CeilingBPTotal,
        'Approved', 1, 1, 10,
        @CreatedBy, GETDATE()
    );
END

;WITH MissingBalance AS (
    SELECT cd.CeilingDefinitionID, cd.FiscalYearID, cd.VersionID,
           cd.CeilingBP1, cd.CeilingBP2, cd.CeilingBP3, cd.CeilingBP4, cd.CeilingBP5, cd.CeilingBP6,
           cd.CeilingBP7, cd.CeilingBP8, cd.CeilingBP9, cd.CeilingBP10, cd.CeilingBP11, cd.CeilingBP12,
           cd.CeilingBPTotal
    FROM dbo.tblCeilingDefinition cd
    LEFT JOIN dbo.tblCeilingBalance cb
      ON cb.CeilingDefinitionID = cd.CeilingDefinitionID
    WHERE cd.FiscalYearID = @FiscalYearID
      AND cd.VersionID = @VersionID
      AND cd.ActiveFlag = 1
      AND cd.ApprovedFlag = 1
      AND cb.CeilingDefinitionID IS NULL
)
INSERT INTO dbo.tblCeilingBalance (
    CeilingDefinitionID, FiscalYearID, VersionID,
    BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
    BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
    BalanceBPTotal,
    LastTransactionID, UpdatedBy, UpdatedDate
)
SELECT
    mb.CeilingDefinitionID, mb.FiscalYearID, mb.VersionID,
    mb.CeilingBP1, mb.CeilingBP2, mb.CeilingBP3, mb.CeilingBP4, mb.CeilingBP5, mb.CeilingBP6,
    mb.CeilingBP7, mb.CeilingBP8, mb.CeilingBP9, mb.CeilingBP10, mb.CeilingBP11, mb.CeilingBP12,
    mb.CeilingBPTotal,
    NULL, @CreatedBy, GETDATE()
FROM MissingBalance mb;

IF @ResetBalances = 1
BEGIN
    UPDATE cb
    SET
        cb.BalanceBP1 = cd.CeilingBP1,
        cb.BalanceBP2 = cd.CeilingBP2,
        cb.BalanceBP3 = cd.CeilingBP3,
        cb.BalanceBP4 = cd.CeilingBP4,
        cb.BalanceBP5 = cd.CeilingBP5,
        cb.BalanceBP6 = cd.CeilingBP6,
        cb.BalanceBP7 = cd.CeilingBP7,
        cb.BalanceBP8 = cd.CeilingBP8,
        cb.BalanceBP9 = cd.CeilingBP9,
        cb.BalanceBP10 = cd.CeilingBP10,
        cb.BalanceBP11 = cd.CeilingBP11,
        cb.BalanceBP12 = cd.CeilingBP12,
        cb.BalanceBPTotal = cd.CeilingBPTotal,
        cb.LastTransactionID = NULL,
        cb.UpdatedBy = @CreatedBy,
        cb.UpdatedDate = GETDATE()
    FROM dbo.tblCeilingBalance cb
    INNER JOIN dbo.tblCeilingDefinition cd
        ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
    WHERE cd.FiscalYearID = @FiscalYearID
      AND cd.VersionID = @VersionID;
END

SELECT
    FiscalYearID,
    VersionID,
    DataObjectCode,
    TransactionTypeCode,
    Segment1Code,
    Segment2Code,
    CeilingBP1,
    CeilingBP12,
    CeilingBPTotal,
    CeilingDefinitionID
FROM dbo.tblCeilingDefinition
WHERE FiscalYearID = @FiscalYearID
  AND VersionID = @VersionID
  AND ISNULL(DataObjectCode, '') = ISNULL(@DataObjectCode, '')
  AND ISNULL(TransactionTypeCode, '') = ISNULL(@TransactionTypeCode, '')
  AND ISNULL(Segment1Code, '') = ISNULL(@Segment1Code, '')
  AND ISNULL(Segment2Code, '') = ISNULL(@Segment2Code, '');
GO
