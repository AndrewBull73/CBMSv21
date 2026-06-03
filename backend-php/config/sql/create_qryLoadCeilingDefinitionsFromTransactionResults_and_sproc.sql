USE [CBMSv2];
GO

/*
Purpose
-------
Build ceiling definitions from transaction results for a selected FiscalYearID/VersionID.

Important assumption
--------------------
This loader intentionally makes the generated ceiling definitions generic across
TransactionTypeCode by setting TransactionTypeCode = NULL.

Why:
- the ceiling matching code treats NULL/blank TransactionTypeCode as "match any"
- recent failures showed many CEILING NOT FOUND errors for TransactionTypeCode = 11
  while otherwise-equivalent definitions existed for TransactionTypeCode = 12 / 31
- using a generic transaction type avoids missing matches caused by over-specific
  TransactionTypeCode values in generated ceiling definitions

If the business later requires separate ceilings per transaction type, this view
should be revised again to load distinct type-specific rows.
*/

CREATE OR ALTER VIEW [dbo].[qryLoadCeilingDefinitionsFromTransactionResults]
AS
SELECT
    ti.FiscalYearID,
    ti.VersionID,
    ti.DataObjectCode,
    CAST(NULL AS nvarchar(20)) AS TransactionTypeCode,
    ti.Segment1Code,
    ti.Segment2Code,
    ti.Segment3Code,
    ti.Segment4Code,
    CAST(NULL AS nvarchar(20)) AS Segment5Code,
    CAST(NULL AS nvarchar(20)) AS Segment6Code,
    CAST(NULL AS nvarchar(20)) AS Segment7Code,
    CAST(NULL AS nvarchar(20)) AS Segment8Code,
    CAST(NULL AS nvarchar(20)) AS Segment9Code,
    CAST(NULL AS nvarchar(20)) AS Segment10Code,
    CAST(NULL AS nvarchar(20)) AS Segment11Code,
    CAST(NULL AS nvarchar(20)) AS Segment12Code,
    CAST(NULL AS nvarchar(20)) AS Segment13Code,
    CAST(NULL AS nvarchar(20)) AS Segment14Code,
    CAST(NULL AS nvarchar(20)) AS Segment15Code,
    CAST(NULL AS nvarchar(20)) AS Segment16Code,
    CAST(NULL AS nvarchar(20)) AS Segment17Code,
    CAST(NULL AS nvarchar(20)) AS Segment18Code,
    CAST(NULL AS nvarchar(20)) AS Segment19Code,
    CAST(NULL AS nvarchar(20)) AS Segment20Code,
    CAST(0 AS decimal(19, 6)) AS CeilingBP1,
    CAST(0 AS decimal(19, 6)) AS CeilingBP2,
    CAST(0 AS decimal(19, 6)) AS CeilingBP3,
    CAST(0 AS decimal(19, 6)) AS CeilingBP4,
    CAST(0 AS decimal(19, 6)) AS CeilingBP5,
    CAST(0 AS decimal(19, 6)) AS CeilingBP6,
    CAST(0 AS decimal(19, 6)) AS CeilingBP7,
    CAST(0 AS decimal(19, 6)) AS CeilingBP8,
    CAST(0 AS decimal(19, 6)) AS CeilingBP9,
    CAST(0 AS decimal(19, 6)) AS CeilingBP10,
    CAST(0 AS decimal(19, 6)) AS CeilingBP11,
    CAST(0 AS decimal(19, 6)) AS CeilingBP12,
    SUM(COALESCE(tr.BPTotal, 0)) AS CeilingBPTotal,
    CAST('Approved' AS nvarchar(20)) AS CeilingStatus,
    CAST(1 AS bit) AS ApprovedFlag,
    CAST(1 AS bit) AS ActiveFlag,
    CAST(10 AS int) AS Priority,
    CAST(1 AS int) AS CreatedBy,
    GETDATE() AS CreatedDate,
    CAST(1 AS int) AS UpdatedBy,
    GETDATE() AS UpdatedDate
FROM dbo.tblTransactionInput ti
INNER JOIN dbo.tblTransactionResult tr
    ON tr.TransactionID = ti.TransactionID
GROUP BY
    ti.FiscalYearID,
    ti.VersionID,
    ti.DataObjectCode,
    ti.Segment1Code,
    ti.Segment2Code,
    ti.Segment3Code,
    ti.Segment4Code;
GO

CREATE OR ALTER PROCEDURE dbo.spLoadCeilingDefinitionsFromTransactionResults
    @FiscalYearID int,
    @VersionID int
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @DeletedRows int = 0;
    DECLARE @InsertedRows int = 0;

    BEGIN TRY
        BEGIN TRANSACTION;

        DELETE FROM dbo.tblCeilingDefinition
        WHERE FiscalYearID = @FiscalYearID
          AND VersionID = @VersionID;

        SET @DeletedRows = @@ROWCOUNT;

        INSERT INTO dbo.tblCeilingDefinition
        (
            FiscalYearID,
            VersionID,
            DataObjectCode,
            TransactionTypeCode,
            Segment1Code,
            Segment2Code,
            Segment3Code,
            Segment4Code,
            Segment5Code,
            Segment6Code,
            Segment7Code,
            Segment8Code,
            Segment9Code,
            Segment10Code,
            Segment11Code,
            Segment12Code,
            Segment13Code,
            Segment14Code,
            Segment15Code,
            Segment16Code,
            Segment17Code,
            Segment18Code,
            Segment19Code,
            Segment20Code,
            CeilingBP1,
            CeilingBP2,
            CeilingBP3,
            CeilingBP4,
            CeilingBP5,
            CeilingBP6,
            CeilingBP7,
            CeilingBP8,
            CeilingBP9,
            CeilingBP10,
            CeilingBP11,
            CeilingBP12,
            CeilingBPTotal,
            CeilingStatus,
            ApprovedFlag,
            ActiveFlag,
            Priority,
            CreatedBy,
            CreatedDate,
            UpdatedBy,
            UpdatedDate
        )
        SELECT
            v.FiscalYearID,
            v.VersionID,
            NULLIF(v.DataObjectCode, ''),
            NULLIF(v.TransactionTypeCode, ''),
            NULLIF(v.Segment1Code, ''),
            NULLIF(v.Segment2Code, ''),
            NULLIF(v.Segment3Code, ''),
            NULLIF(v.Segment4Code, ''),
            NULLIF(v.Segment5Code, ''),
            NULLIF(v.Segment6Code, ''),
            NULLIF(v.Segment7Code, ''),
            NULLIF(v.Segment8Code, ''),
            NULLIF(v.Segment9Code, ''),
            NULLIF(v.Segment10Code, ''),
            NULLIF(v.Segment11Code, ''),
            NULLIF(v.Segment12Code, ''),
            NULLIF(v.Segment13Code, ''),
            NULLIF(v.Segment14Code, ''),
            NULLIF(v.Segment15Code, ''),
            NULLIF(v.Segment16Code, ''),
            NULLIF(v.Segment17Code, ''),
            NULLIF(v.Segment18Code, ''),
            NULLIF(v.Segment19Code, ''),
            NULLIF(v.Segment20Code, ''),
            ISNULL(v.CeilingBP1, 0),
            ISNULL(v.CeilingBP2, 0),
            ISNULL(v.CeilingBP3, 0),
            ISNULL(v.CeilingBP4, 0),
            ISNULL(v.CeilingBP5, 0),
            ISNULL(v.CeilingBP6, 0),
            ISNULL(v.CeilingBP7, 0),
            ISNULL(v.CeilingBP8, 0),
            ISNULL(v.CeilingBP9, 0),
            ISNULL(v.CeilingBP10, 0),
            ISNULL(v.CeilingBP11, 0),
            ISNULL(v.CeilingBP12, 0),
            ISNULL(v.CeilingBPTotal, 0),
            v.CeilingStatus,
            ISNULL(v.ApprovedFlag, 0),
            ISNULL(v.ActiveFlag, 1),
            ISNULL(v.Priority, 100),
            ISNULL(v.CreatedBy, 1),
            ISNULL(v.CreatedDate, GETDATE()),
            v.UpdatedBy,
            v.UpdatedDate
        FROM dbo.qryLoadCeilingDefinitionsFromTransactionResults v
        WHERE v.FiscalYearID = @FiscalYearID
          AND v.VersionID = @VersionID;

        SET @InsertedRows = @@ROWCOUNT;

        COMMIT TRANSACTION;

        SELECT
            @FiscalYearID AS FiscalYearID,
            @VersionID AS VersionID,
            @DeletedRows AS DeletedRows,
            @InsertedRows AS InsertedRows;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        THROW;
    END CATCH
END
GO

/*
Example
-------
EXEC dbo.spLoadCeilingDefinitionsFromTransactionResults
    @FiscalYearID = 2026,
    @VersionID = 5;

After loading, run Full Ceiling Reset in the Batch Runner so tblCeilingBalance,
Redis ceiling state, and tblTransactionInput ceiling warnings are all refreshed.
*/
