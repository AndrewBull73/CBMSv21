USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblTransactionTypeSegmentConfig', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTransactionTypeSegmentConfig (
        TransactionTypeSegmentConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        TransactionTypeCode NVARCHAR(20) NOT NULL,
        SegmentNo INT NOT NULL,
        VisibleFlag BIT NOT NULL CONSTRAINT DF_ttsc_visible DEFAULT (1),
        RequiredFlag BIT NOT NULL CONSTRAINT DF_ttsc_required DEFAULT (0),
        LookupSourceType NVARCHAR(30) NOT NULL CONSTRAINT DF_ttsc_lookup DEFAULT (N'tblSegments'),
        LookupFilter NVARCHAR(200) NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_ttsc_display DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_ttsc_active DEFAULT (1),
        UpdatedBy INT NOT NULL CONSTRAINT DF_ttsc_updatedby DEFAULT (1),
        UpdatedDate DATETIME NOT NULL CONSTRAINT DF_ttsc_updateddate DEFAULT (GETDATE())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblTransactionTypeSegmentConfig_Key'
      AND object_id = OBJECT_ID('dbo.tblTransactionTypeSegmentConfig')
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTransactionTypeSegmentConfig_Key
    ON dbo.tblTransactionTypeSegmentConfig (TransactionTypeCode, SegmentNo, FiscalYearID, VersionID);
END
GO

/* -------------------------------------------------------------
   Optional baseline seed:
   - If you want all transaction types to default to Segment1-3,
     insert rows per type using your own TransactionTypeCode list.
   Example:

   INSERT INTO dbo.tblTransactionTypeSegmentConfig
       (FiscalYearID, VersionID, TransactionTypeCode, SegmentNo, VisibleFlag, RequiredFlag, LookupSourceType, LookupFilter, DisplayOrder, ActiveFlag, UpdatedBy)
   VALUES
       (2026, 5, N'21', 1, 1, 1, N'tblSegments', NULL, 1, 1, 1),
       (2026, 5, N'21', 2, 1, 1, N'tblSegments', NULL, 2, 1, 1),
       (2026, 5, N'21', 3, 1, 0, N'tblSegments', NULL, 3, 1, 1);
-------------------------------------------------------------- */
