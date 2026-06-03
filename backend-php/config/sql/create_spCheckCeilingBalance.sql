USE [CBMSv2];
GO

IF OBJECT_ID('dbo.spCheckCeilingBalance', 'P') IS NULL
BEGIN
    EXEC ('CREATE PROCEDURE [dbo].[spCheckCeilingBalance] AS BEGIN SET NOCOUNT ON; END');
END
GO

ALTER PROCEDURE [dbo].[spCheckCeilingBalance]
    @FiscalYearID int,
    @VersionID int,
    @TransactionID int,
    @UpdatedBy int,
    @CheckMode nvarchar(20) = N'TRANSACTION', -- TRANSACTION | HEAD_RECORD
    @EnforcePeriod bit = 1, -- 1 = total + period enforcement, 0 = total-only enforcement
    @CeilingStatusCheck nvarchar(50) OUTPUT,
    @ErrorMessage nvarchar(500) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    -- Important: keep OFF so this proc cannot auto-rollback caller-owned transactions.
    SET XACT_ABORT OFF;

    DECLARE @DataObjectCode nvarchar(20);
    DECLARE @TransactionTypeCode nvarchar(20);
    DECLARE @Segment1Code nvarchar(20), @Segment2Code nvarchar(20), @Segment3Code nvarchar(20), @Segment4Code nvarchar(20), @Segment5Code nvarchar(20);
    DECLARE @Segment6Code nvarchar(20), @Segment7Code nvarchar(20), @Segment8Code nvarchar(20), @Segment9Code nvarchar(20), @Segment10Code nvarchar(20);
    DECLARE @Segment11Code nvarchar(20), @Segment12Code nvarchar(20), @Segment13Code nvarchar(20), @Segment14Code nvarchar(20), @Segment15Code nvarchar(20);
    DECLARE @Segment16Code nvarchar(20), @Segment17Code nvarchar(20), @Segment18Code nvarchar(20), @Segment19Code nvarchar(20), @Segment20Code nvarchar(20);

    DECLARE @AmtBP1 decimal(19,6), @AmtBP2 decimal(19,6), @AmtBP3 decimal(19,6), @AmtBP4 decimal(19,6), @AmtBP5 decimal(19,6), @AmtBP6 decimal(19,6);
    DECLARE @AmtBP7 decimal(19,6), @AmtBP8 decimal(19,6), @AmtBP9 decimal(19,6), @AmtBP10 decimal(19,6), @AmtBP11 decimal(19,6), @AmtBP12 decimal(19,6);
    DECLARE @AmtBPTotal decimal(19,6);
    DECLARE @AppliedBP1 decimal(19,6) = 0, @AppliedBP2 decimal(19,6) = 0, @AppliedBP3 decimal(19,6) = 0, @AppliedBP4 decimal(19,6) = 0, @AppliedBP5 decimal(19,6) = 0, @AppliedBP6 decimal(19,6) = 0;
    DECLARE @AppliedBP7 decimal(19,6) = 0, @AppliedBP8 decimal(19,6) = 0, @AppliedBP9 decimal(19,6) = 0, @AppliedBP10 decimal(19,6) = 0, @AppliedBP11 decimal(19,6) = 0, @AppliedBP12 decimal(19,6) = 0;
    DECLARE @AppliedBPTotal decimal(19,6) = 0;

    DECLARE @CeilingDefinitionID int;
    DECLARE @CeilingBP1 decimal(19,6), @CeilingBP2 decimal(19,6), @CeilingBP3 decimal(19,6), @CeilingBP4 decimal(19,6), @CeilingBP5 decimal(19,6), @CeilingBP6 decimal(19,6);
    DECLARE @CeilingBP7 decimal(19,6), @CeilingBP8 decimal(19,6), @CeilingBP9 decimal(19,6), @CeilingBP10 decimal(19,6), @CeilingBP11 decimal(19,6), @CeilingBP12 decimal(19,6);
    DECLARE @CeilingBPTotal decimal(19,6);

    DECLARE @BalBP1 decimal(19,6), @BalBP2 decimal(19,6), @BalBP3 decimal(19,6), @BalBP4 decimal(19,6), @BalBP5 decimal(19,6), @BalBP6 decimal(19,6);
    DECLARE @BalBP7 decimal(19,6), @BalBP8 decimal(19,6), @BalBP9 decimal(19,6), @BalBP10 decimal(19,6), @BalBP11 decimal(19,6), @BalBP12 decimal(19,6);
    DECLARE @BalBPTotal decimal(19,6);

    DECLARE @NewBalBP1 decimal(19,6), @NewBalBP2 decimal(19,6), @NewBalBP3 decimal(19,6), @NewBalBP4 decimal(19,6), @NewBalBP5 decimal(19,6), @NewBalBP6 decimal(19,6);
    DECLARE @NewBalBP7 decimal(19,6), @NewBalBP8 decimal(19,6), @NewBalBP9 decimal(19,6), @NewBalBP10 decimal(19,6), @NewBalBP11 decimal(19,6), @NewBalBP12 decimal(19,6);
    DECLARE @NewBalBPTotal decimal(19,6);

    DECLARE @HasPeriodCeilings bit = 0;
    DECLARE @LastTransactionID int;
    DECLARE @HeadRecordID int;
    DECLARE @StartTranCount int = @@TRANCOUNT;
    DECLARE @StartedLocalTran bit = 0;
    DECLARE @HasSavepoint bit = 0;

    BEGIN TRY
        SELECT
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
            @Segment20Code = ti.Segment20Code,
            @HeadRecordID = ti.HeadRecordID
        FROM dbo.tblTransactionInput ti
        WHERE ti.TransactionID = @TransactionID;

        IF @DataObjectCode IS NULL
        BEGIN
            SET @CeilingStatusCheck = N'TRANSACTION NOT FOUND';
            SET @ErrorMessage = N'Transaction row was not found in tblTransactionInput.';
            RETURN;
        END

        IF UPPER(ISNULL(@CheckMode, N'TRANSACTION')) = N'HEAD_RECORD'
        BEGIN
            ;WITH LatestByTx AS (
                SELECT
                    rf.TransactionID,
                    rf.BP1, rf.BP2, rf.BP3, rf.BP4, rf.BP5, rf.BP6,
                    rf.BP7, rf.BP8, rf.BP9, rf.BP10, rf.BP11, rf.BP12,
                    rf.BPTotal,
                    ROW_NUMBER() OVER (
                        PARTITION BY rf.TransactionID
                        ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC
                    ) AS rn
                FROM dbo.tblTransactionResultFlat rf
                INNER JOIN dbo.tblTransactionInput ti2
                    ON ti2.TransactionID = rf.TransactionID
                WHERE (
                        ti2.HeadRecordID = COALESCE(@HeadRecordID, @TransactionID)
                        OR ti2.TransactionID = @TransactionID
                      )
            )
            SELECT
                @AmtBP1 = COALESCE(SUM(COALESCE(BP1, 0)), 0),
                @AmtBP2 = COALESCE(SUM(COALESCE(BP2, 0)), 0),
                @AmtBP3 = COALESCE(SUM(COALESCE(BP3, 0)), 0),
                @AmtBP4 = COALESCE(SUM(COALESCE(BP4, 0)), 0),
                @AmtBP5 = COALESCE(SUM(COALESCE(BP5, 0)), 0),
                @AmtBP6 = COALESCE(SUM(COALESCE(BP6, 0)), 0),
                @AmtBP7 = COALESCE(SUM(COALESCE(BP7, 0)), 0),
                @AmtBP8 = COALESCE(SUM(COALESCE(BP8, 0)), 0),
                @AmtBP9 = COALESCE(SUM(COALESCE(BP9, 0)), 0),
                @AmtBP10 = COALESCE(SUM(COALESCE(BP10, 0)), 0),
                @AmtBP11 = COALESCE(SUM(COALESCE(BP11, 0)), 0),
                @AmtBP12 = COALESCE(SUM(COALESCE(BP12, 0)), 0),
                @AmtBPTotal = COALESCE(SUM(COALESCE(BPTotal, 0)), 0)
            FROM LatestByTx
            WHERE rn = 1;
        END
        ELSE
        BEGIN
            SELECT TOP 1
                @AmtBP1 = rf.BP1,
                @AmtBP2 = rf.BP2,
                @AmtBP3 = rf.BP3,
                @AmtBP4 = rf.BP4,
                @AmtBP5 = rf.BP5,
                @AmtBP6 = rf.BP6,
                @AmtBP7 = rf.BP7,
                @AmtBP8 = rf.BP8,
                @AmtBP9 = rf.BP9,
                @AmtBP10 = rf.BP10,
                @AmtBP11 = rf.BP11,
                @AmtBP12 = rf.BP12,
                @AmtBPTotal = rf.BPTotal
            FROM dbo.tblTransactionResultFlat rf
            WHERE rf.TransactionID = @TransactionID
            ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC;
        END

        IF @AmtBPTotal IS NULL
        BEGIN
            SELECT
                @AmtBP1 = COALESCE(ti.BP1InpN, 0),
                @AmtBP2 = COALESCE(ti.BP2InpN, 0),
                @AmtBP3 = COALESCE(ti.BP3InpN, 0),
                @AmtBP4 = COALESCE(ti.BP4InpN, 0),
                @AmtBP5 = COALESCE(ti.BP5InpN, 0),
                @AmtBP6 = COALESCE(ti.BP6InpN, 0),
                @AmtBP7 = COALESCE(ti.BP7InpN, 0),
                @AmtBP8 = COALESCE(ti.BP8InpN, 0),
                @AmtBP9 = COALESCE(ti.BP9InpN, 0),
                @AmtBP10 = COALESCE(ti.BP10InpN, 0),
                @AmtBP11 = COALESCE(ti.BP11InpN, 0),
                @AmtBP12 = COALESCE(ti.BP12InpN, 0),
                @AmtBPTotal = COALESCE(ti.BPTotalInpN,
                    COALESCE(ti.BP1InpN,0) + COALESCE(ti.BP2InpN,0) + COALESCE(ti.BP3InpN,0) + COALESCE(ti.BP4InpN,0) +
                    COALESCE(ti.BP5InpN,0) + COALESCE(ti.BP6InpN,0) + COALESCE(ti.BP7InpN,0) + COALESCE(ti.BP8InpN,0) +
                    COALESCE(ti.BP9InpN,0) + COALESCE(ti.BP10InpN,0) + COALESCE(ti.BP11InpN,0) + COALESCE(ti.BP12InpN,0))
            FROM dbo.tblTransactionInput ti
            WHERE ti.TransactionID = @TransactionID;
        END

        IF UPPER(ISNULL(@CheckMode, N'TRANSACTION')) <> N'HEAD_RECORD'
        BEGIN
            SELECT
                @AppliedBP1 = COALESCE(ti.CeilingAppliedBP1, 0),
                @AppliedBP2 = COALESCE(ti.CeilingAppliedBP2, 0),
                @AppliedBP3 = COALESCE(ti.CeilingAppliedBP3, 0),
                @AppliedBP4 = COALESCE(ti.CeilingAppliedBP4, 0),
                @AppliedBP5 = COALESCE(ti.CeilingAppliedBP5, 0),
                @AppliedBP6 = COALESCE(ti.CeilingAppliedBP6, 0),
                @AppliedBP7 = COALESCE(ti.CeilingAppliedBP7, 0),
                @AppliedBP8 = COALESCE(ti.CeilingAppliedBP8, 0),
                @AppliedBP9 = COALESCE(ti.CeilingAppliedBP9, 0),
                @AppliedBP10 = COALESCE(ti.CeilingAppliedBP10, 0),
                @AppliedBP11 = COALESCE(ti.CeilingAppliedBP11, 0),
                @AppliedBP12 = COALESCE(ti.CeilingAppliedBP12, 0),
                @AppliedBPTotal = COALESCE(ti.CeilingAppliedTotal, 0)
            FROM dbo.tblTransactionInput ti
            WHERE ti.TransactionID = @TransactionID;

            SET @AmtBP1 = COALESCE(@AmtBP1,0) - COALESCE(@AppliedBP1,0);
            SET @AmtBP2 = COALESCE(@AmtBP2,0) - COALESCE(@AppliedBP2,0);
            SET @AmtBP3 = COALESCE(@AmtBP3,0) - COALESCE(@AppliedBP3,0);
            SET @AmtBP4 = COALESCE(@AmtBP4,0) - COALESCE(@AppliedBP4,0);
            SET @AmtBP5 = COALESCE(@AmtBP5,0) - COALESCE(@AppliedBP5,0);
            SET @AmtBP6 = COALESCE(@AmtBP6,0) - COALESCE(@AppliedBP6,0);
            SET @AmtBP7 = COALESCE(@AmtBP7,0) - COALESCE(@AppliedBP7,0);
            SET @AmtBP8 = COALESCE(@AmtBP8,0) - COALESCE(@AppliedBP8,0);
            SET @AmtBP9 = COALESCE(@AmtBP9,0) - COALESCE(@AppliedBP9,0);
            SET @AmtBP10 = COALESCE(@AmtBP10,0) - COALESCE(@AppliedBP10,0);
            SET @AmtBP11 = COALESCE(@AmtBP11,0) - COALESCE(@AppliedBP11,0);
            SET @AmtBP12 = COALESCE(@AmtBP12,0) - COALESCE(@AppliedBP12,0);
            SET @AmtBPTotal = COALESCE(@AmtBPTotal,0) - COALESCE(@AppliedBPTotal,0);
        END

        SELECT TOP 1
            @CeilingDefinitionID = cd.CeilingDefinitionID,
            @CeilingBP1 = cd.CeilingBP1,
            @CeilingBP2 = cd.CeilingBP2,
            @CeilingBP3 = cd.CeilingBP3,
            @CeilingBP4 = cd.CeilingBP4,
            @CeilingBP5 = cd.CeilingBP5,
            @CeilingBP6 = cd.CeilingBP6,
            @CeilingBP7 = cd.CeilingBP7,
            @CeilingBP8 = cd.CeilingBP8,
            @CeilingBP9 = cd.CeilingBP9,
            @CeilingBP10 = cd.CeilingBP10,
            @CeilingBP11 = cd.CeilingBP11,
            @CeilingBP12 = cd.CeilingBP12,
            @CeilingBPTotal = cd.CeilingBPTotal
        FROM dbo.tblCeilingDefinition cd
        WHERE cd.FiscalYearID = @FiscalYearID
          AND cd.VersionID = @VersionID
          AND cd.ActiveFlag = 1
          AND cd.ApprovedFlag = 1
          AND (cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' OR cd.DataObjectCode = @DataObjectCode)
          AND (cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' OR cd.TransactionTypeCode = @TransactionTypeCode)
          AND (cd.Segment1Code IS NULL OR cd.Segment1Code = N'' OR cd.Segment1Code = @Segment1Code)
          AND (cd.Segment2Code IS NULL OR cd.Segment2Code = N'' OR cd.Segment2Code = @Segment2Code)
          AND (cd.Segment3Code IS NULL OR cd.Segment3Code = N'' OR cd.Segment3Code = @Segment3Code)
          AND (cd.Segment4Code IS NULL OR cd.Segment4Code = N'' OR cd.Segment4Code = @Segment4Code)
          AND (cd.Segment5Code IS NULL OR cd.Segment5Code = N'' OR cd.Segment5Code = @Segment5Code)
          AND (cd.Segment6Code IS NULL OR cd.Segment6Code = N'' OR cd.Segment6Code = @Segment6Code)
          AND (cd.Segment7Code IS NULL OR cd.Segment7Code = N'' OR cd.Segment7Code = @Segment7Code)
          AND (cd.Segment8Code IS NULL OR cd.Segment8Code = N'' OR cd.Segment8Code = @Segment8Code)
          AND (cd.Segment9Code IS NULL OR cd.Segment9Code = N'' OR cd.Segment9Code = @Segment9Code)
          AND (cd.Segment10Code IS NULL OR cd.Segment10Code = N'' OR cd.Segment10Code = @Segment10Code)
          AND (cd.Segment11Code IS NULL OR cd.Segment11Code = N'' OR cd.Segment11Code = @Segment11Code)
          AND (cd.Segment12Code IS NULL OR cd.Segment12Code = N'' OR cd.Segment12Code = @Segment12Code)
          AND (cd.Segment13Code IS NULL OR cd.Segment13Code = N'' OR cd.Segment13Code = @Segment13Code)
          AND (cd.Segment14Code IS NULL OR cd.Segment14Code = N'' OR cd.Segment14Code = @Segment14Code)
          AND (cd.Segment15Code IS NULL OR cd.Segment15Code = N'' OR cd.Segment15Code = @Segment15Code)
          AND (cd.Segment16Code IS NULL OR cd.Segment16Code = N'' OR cd.Segment16Code = @Segment16Code)
          AND (cd.Segment17Code IS NULL OR cd.Segment17Code = N'' OR cd.Segment17Code = @Segment17Code)
          AND (cd.Segment18Code IS NULL OR cd.Segment18Code = N'' OR cd.Segment18Code = @Segment18Code)
          AND (cd.Segment19Code IS NULL OR cd.Segment19Code = N'' OR cd.Segment19Code = @Segment19Code)
          AND (cd.Segment20Code IS NULL OR cd.Segment20Code = N'' OR cd.Segment20Code = @Segment20Code)
        ORDER BY
            (
                CASE WHEN cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment1Code IS NULL OR cd.Segment1Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment2Code IS NULL OR cd.Segment2Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment3Code IS NULL OR cd.Segment3Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment4Code IS NULL OR cd.Segment4Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment5Code IS NULL OR cd.Segment5Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment6Code IS NULL OR cd.Segment6Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment7Code IS NULL OR cd.Segment7Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment8Code IS NULL OR cd.Segment8Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment9Code IS NULL OR cd.Segment9Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment10Code IS NULL OR cd.Segment10Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment11Code IS NULL OR cd.Segment11Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment12Code IS NULL OR cd.Segment12Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment13Code IS NULL OR cd.Segment13Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment14Code IS NULL OR cd.Segment14Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment15Code IS NULL OR cd.Segment15Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment16Code IS NULL OR cd.Segment16Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment17Code IS NULL OR cd.Segment17Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment18Code IS NULL OR cd.Segment18Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment19Code IS NULL OR cd.Segment19Code = N'' THEN 0 ELSE 1 END +
                CASE WHEN cd.Segment20Code IS NULL OR cd.Segment20Code = N'' THEN 0 ELSE 1 END
            ) DESC,
            cd.Priority ASC,
            cd.CeilingDefinitionID ASC;

        IF @CeilingDefinitionID IS NULL
        BEGIN
            SET @CeilingStatusCheck = N'CEILING NOT FOUND';
            SET @ErrorMessage = N'No active+approved ceiling definition matched this transaction context.';
            RETURN;
        END

        IF COALESCE(@CeilingBP1, 0) <> 0 OR COALESCE(@CeilingBP2, 0) <> 0 OR COALESCE(@CeilingBP3, 0) <> 0 OR
           COALESCE(@CeilingBP4, 0) <> 0 OR COALESCE(@CeilingBP5, 0) <> 0 OR COALESCE(@CeilingBP6, 0) <> 0 OR
           COALESCE(@CeilingBP7, 0) <> 0 OR COALESCE(@CeilingBP8, 0) <> 0 OR COALESCE(@CeilingBP9, 0) <> 0 OR
           COALESCE(@CeilingBP10, 0) <> 0 OR COALESCE(@CeilingBP11, 0) <> 0 OR COALESCE(@CeilingBP12, 0) <> 0
        BEGIN
            SET @HasPeriodCeilings = 1;
        END

        IF @StartTranCount = 0
        BEGIN
            BEGIN TRANSACTION;
            SET @StartedLocalTran = 1;
        END
        ELSE
        BEGIN
            SAVE TRANSACTION spCheckCeilingBalanceSave;
            SET @HasSavepoint = 1;
        END

        SELECT
            @BalBP1 = cb.BalanceBP1,
            @BalBP2 = cb.BalanceBP2,
            @BalBP3 = cb.BalanceBP3,
            @BalBP4 = cb.BalanceBP4,
            @BalBP5 = cb.BalanceBP5,
            @BalBP6 = cb.BalanceBP6,
            @BalBP7 = cb.BalanceBP7,
            @BalBP8 = cb.BalanceBP8,
            @BalBP9 = cb.BalanceBP9,
            @BalBP10 = cb.BalanceBP10,
            @BalBP11 = cb.BalanceBP11,
            @BalBP12 = cb.BalanceBP12,
            @BalBPTotal = cb.BalanceBPTotal,
            @LastTransactionID = cb.LastTransactionID
        FROM dbo.tblCeilingBalance cb WITH (UPDLOCK, HOLDLOCK)
        WHERE cb.CeilingDefinitionID = @CeilingDefinitionID;

        IF @BalBPTotal IS NULL
        BEGIN
            INSERT INTO dbo.tblCeilingBalance (
                CeilingDefinitionID, FiscalYearID, VersionID,
                BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
                BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
                BalanceBPTotal, LastTransactionID, UpdatedBy, UpdatedDate
            )
            VALUES (
                @CeilingDefinitionID, @FiscalYearID, @VersionID,
                COALESCE(@CeilingBP1,0), COALESCE(@CeilingBP2,0), COALESCE(@CeilingBP3,0), COALESCE(@CeilingBP4,0), COALESCE(@CeilingBP5,0), COALESCE(@CeilingBP6,0),
                COALESCE(@CeilingBP7,0), COALESCE(@CeilingBP8,0), COALESCE(@CeilingBP9,0), COALESCE(@CeilingBP10,0), COALESCE(@CeilingBP11,0), COALESCE(@CeilingBP12,0),
                COALESCE(@CeilingBPTotal,0), NULL, @UpdatedBy, GETDATE()
            );

            SELECT
                @BalBP1 = cb.BalanceBP1,
                @BalBP2 = cb.BalanceBP2,
                @BalBP3 = cb.BalanceBP3,
                @BalBP4 = cb.BalanceBP4,
                @BalBP5 = cb.BalanceBP5,
                @BalBP6 = cb.BalanceBP6,
                @BalBP7 = cb.BalanceBP7,
                @BalBP8 = cb.BalanceBP8,
                @BalBP9 = cb.BalanceBP9,
                @BalBP10 = cb.BalanceBP10,
                @BalBP11 = cb.BalanceBP11,
                @BalBP12 = cb.BalanceBP12,
                @BalBPTotal = cb.BalanceBPTotal,
                @LastTransactionID = cb.LastTransactionID
            FROM dbo.tblCeilingBalance cb WITH (UPDLOCK, HOLDLOCK)
            WHERE cb.CeilingDefinitionID = @CeilingDefinitionID;
        END

        SET @NewBalBP1 = COALESCE(@BalBP1,0) - COALESCE(@AmtBP1,0);
        SET @NewBalBP2 = COALESCE(@BalBP2,0) - COALESCE(@AmtBP2,0);
        SET @NewBalBP3 = COALESCE(@BalBP3,0) - COALESCE(@AmtBP3,0);
        SET @NewBalBP4 = COALESCE(@BalBP4,0) - COALESCE(@AmtBP4,0);
        SET @NewBalBP5 = COALESCE(@BalBP5,0) - COALESCE(@AmtBP5,0);
        SET @NewBalBP6 = COALESCE(@BalBP6,0) - COALESCE(@AmtBP6,0);
        SET @NewBalBP7 = COALESCE(@BalBP7,0) - COALESCE(@AmtBP7,0);
        SET @NewBalBP8 = COALESCE(@BalBP8,0) - COALESCE(@AmtBP8,0);
        SET @NewBalBP9 = COALESCE(@BalBP9,0) - COALESCE(@AmtBP9,0);
        SET @NewBalBP10 = COALESCE(@BalBP10,0) - COALESCE(@AmtBP10,0);
        SET @NewBalBP11 = COALESCE(@BalBP11,0) - COALESCE(@AmtBP11,0);
        SET @NewBalBP12 = COALESCE(@BalBP12,0) - COALESCE(@AmtBP12,0);
        SET @NewBalBPTotal = COALESCE(@BalBPTotal,0) - COALESCE(@AmtBPTotal,0);

        IF @NewBalBPTotal < 0 OR (
            @EnforcePeriod = 1 AND @HasPeriodCeilings = 1 AND (
                @NewBalBP1 < 0 OR @NewBalBP2 < 0 OR @NewBalBP3 < 0 OR @NewBalBP4 < 0 OR
                @NewBalBP5 < 0 OR @NewBalBP6 < 0 OR @NewBalBP7 < 0 OR @NewBalBP8 < 0 OR
                @NewBalBP9 < 0 OR @NewBalBP10 < 0 OR @NewBalBP11 < 0 OR @NewBalBP12 < 0
            )
        )
        BEGIN
            IF @StartedLocalTran = 1
                ROLLBACK TRANSACTION;
            SET @CeilingStatusCheck = N'CEILING EXCEEDED';
            SET @ErrorMessage = N'Insufficient ceiling balance.';
            RETURN;
        END

        UPDATE dbo.tblCeilingBalance
        SET
            BalanceBP1 = @NewBalBP1,
            BalanceBP2 = @NewBalBP2,
            BalanceBP3 = @NewBalBP3,
            BalanceBP4 = @NewBalBP4,
            BalanceBP5 = @NewBalBP5,
            BalanceBP6 = @NewBalBP6,
            BalanceBP7 = @NewBalBP7,
            BalanceBP8 = @NewBalBP8,
            BalanceBP9 = @NewBalBP9,
            BalanceBP10 = @NewBalBP10,
            BalanceBP11 = @NewBalBP11,
            BalanceBP12 = @NewBalBP12,
            BalanceBPTotal = @NewBalBPTotal,
            LastTransactionID = @TransactionID,
            UpdatedBy = @UpdatedBy,
            UpdatedDate = GETDATE()
        WHERE CeilingDefinitionID = @CeilingDefinitionID;

        IF @StartedLocalTran = 1
            COMMIT TRANSACTION;

        SET @CeilingStatusCheck = N'CEILING OK';
        SET @ErrorMessage = N'Transaction committed';
    END TRY
    BEGIN CATCH
        -- Never rollback caller-owned transactions here; caller controls outer transaction scope.
        IF @@TRANCOUNT > 0 AND @StartedLocalTran = 1
            ROLLBACK TRANSACTION;

        SET @CeilingStatusCheck = N'ERROR';
        SET @ErrorMessage = ERROR_MESSAGE();
    END CATCH
END
GO
