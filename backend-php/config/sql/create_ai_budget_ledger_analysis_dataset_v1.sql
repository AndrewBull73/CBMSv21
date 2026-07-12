/*
    Create the governed source table for Budget Ledger AI/ML analysis.

    Design rule:
    - dbo.tblAIBudgetLedgerAnalysisSource stores durable generic dimensions only: Segment1..Segment15.
    - Named concepts such as Vote, Ministry, Program, Activity, and Economic are semantic aliases
      and are derived by create_ai_budget_ledger_semantic_mapping_v1.sql.

    Development reset note:
    - If you already created the older version of this table with VoteCode/ProgramCode/etc.,
      drop/recreate and reload the dev table before relying on this script.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIBudgetLedgerAnalysisSource
    (
        BudgetLedgerAnalysisID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SourceRowReference NVARCHAR(120) NOT NULL,
        FiscalYearID INT NOT NULL,
        BudgetVersionID INT NULL,
        PeriodNo INT NULL,
        PostingDate DATE NULL,

        Segment1 NVARCHAR(50) NULL,
        Segment2 NVARCHAR(50) NULL,
        Segment3 NVARCHAR(50) NULL,
        Segment4 NVARCHAR(50) NULL,
        Segment5 NVARCHAR(50) NULL,
        Segment6 NVARCHAR(50) NULL,
        Segment7 NVARCHAR(50) NULL,
        Segment8 NVARCHAR(50) NULL,
        Segment9 NVARCHAR(50) NULL,
        Segment10 NVARCHAR(50) NULL,
        Segment11 NVARCHAR(50) NULL,
        Segment12 NVARCHAR(50) NULL,
        Segment13 NVARCHAR(50) NULL,
        Segment14 NVARCHAR(50) NULL,
        Segment15 NVARCHAR(50) NULL,

        BudgetAmount DECIMAL(19,2) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_BudgetAmount DEFAULT (0),
        ReleasedAmount DECIMAL(19,2) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_ReleasedAmount DEFAULT (0),
        WarrantAmount DECIMAL(19,2) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_WarrantAmount DEFAULT (0),
        CommitmentAmount DECIMAL(19,2) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_CommitmentAmount DEFAULT (0),
        ActualAmount DECIMAL(19,2) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_ActualAmount DEFAULT (0),
        AvailableBalance DECIMAL(19,2) NULL,
        ExecutionRate DECIMAL(9,4) NULL,
        PriorYearBudgetAmount DECIMAL(19,2) NULL,
        PriorYearActualAmount DECIMAL(19,2) NULL,

        CurrencyCode NVARCHAR(10) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_CurrencyCode DEFAULT (N'LSL'),
        SensitivityLabel NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_SensitivityLabel DEFAULT (N'RESTRICTED'),
        DataLoadBatchCode NVARCHAR(120) NULL,
        SourceSystemCode NVARCHAR(80) NULL,
        IsActive BIT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_IsActive DEFAULT (1),
        LoadedBy INT NULL,
        LoadedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerAnalysisSource_LoadedDate DEFAULT (SYSUTCDATETIME()),
        Notes NVARCHAR(1000) NULL
    );

    CREATE UNIQUE INDEX UX_tblAIBudgetLedgerAnalysisSource_RowReference
        ON dbo.tblAIBudgetLedgerAnalysisSource (SourceRowReference);

    CREATE INDEX IX_tblAIBudgetLedgerAnalysisSource_FiscalPeriod
        ON dbo.tblAIBudgetLedgerAnalysisSource (FiscalYearID, BudgetVersionID, PeriodNo, IsActive)
        INCLUDE (BudgetAmount, ReleasedAmount, WarrantAmount, CommitmentAmount, ActualAmount);

    CREATE INDEX IX_tblAIBudgetLedgerAnalysisSource_SegmentGrain
        ON dbo.tblAIBudgetLedgerAnalysisSource (FiscalYearID, BudgetVersionID, PeriodNo, Segment1, Segment5, Segment11, IsActive)
        INCLUDE (BudgetAmount, ActualAmount, CommitmentAmount, AvailableBalance, CurrencyCode);
END;
GO

IF COL_LENGTH(N'dbo.tblAIBudgetLedgerAnalysisSource', N'VoteCode') IS NOT NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerAnalysisSource still has legacy semantic columns such as VoteCode. In development, drop/recreate this table and reload using the updated scripts.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerGeneric', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerGeneric AS SELECT CAST(NULL AS INT) AS BudgetLedgerAnalysisID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerGeneric
AS
SELECT
    BudgetLedgerAnalysisID,
    SourceRowReference,
    FiscalYearID,
    BudgetVersionID,
    PeriodNo,
    PostingDate,
    Segment1,
    Segment2,
    Segment3,
    Segment4,
    Segment5,
    Segment6,
    Segment7,
    Segment8,
    Segment9,
    Segment10,
    Segment11,
    Segment12,
    Segment13,
    Segment14,
    Segment15,
    BudgetAmount,
    ReleasedAmount,
    WarrantAmount,
    CommitmentAmount,
    ActualAmount,
    COALESCE(AvailableBalance, BudgetAmount - ActualAmount - CommitmentAmount) AS AvailableBalance,
    COALESCE(ExecutionRate, CASE WHEN BudgetAmount <> 0 THEN CAST((ActualAmount / NULLIF(BudgetAmount, 0)) * 100.0 AS DECIMAL(9,4)) ELSE NULL END) AS ExecutionRate,
    PriorYearBudgetAmount,
    PriorYearActualAmount,
    CASE
        WHEN PriorYearActualAmount IS NULL OR PriorYearActualAmount = 0 THEN NULL
        ELSE CAST(((ActualAmount - PriorYearActualAmount) / NULLIF(PriorYearActualAmount, 0)) * 100.0 AS DECIMAL(9,4))
    END AS ActualVsPriorYearPct,
    CurrencyCode,
    SensitivityLabel,
    DataLoadBatchCode,
    SourceSystemCode,
    LoadedDate
FROM dbo.tblAIBudgetLedgerAnalysisSource
WHERE IsActive = 1;
');
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerAnalysis', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerAnalysis AS SELECT CAST(NULL AS INT) AS BudgetLedgerAnalysisID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerAnalysis
AS
SELECT
    BudgetLedgerAnalysisID,
    SourceRowReference,
    FiscalYearID,
    BudgetVersionID,
    PeriodNo,
    PostingDate,
    CAST(NULL AS NVARCHAR(80)) AS VoteCode,
    CAST(NULL AS NVARCHAR(255)) AS VoteName,
    CAST(NULL AS NVARCHAR(80)) AS MinistryCode,
    CAST(NULL AS NVARCHAR(255)) AS MinistryName,
    CAST(NULL AS NVARCHAR(80)) AS DepartmentCode,
    CAST(NULL AS NVARCHAR(255)) AS DepartmentName,
    CAST(NULL AS NVARCHAR(80)) AS FundCode,
    CAST(NULL AS NVARCHAR(255)) AS FundName,
    CAST(NULL AS NVARCHAR(80)) AS ProgramCode,
    CAST(NULL AS NVARCHAR(255)) AS ProgramName,
    CAST(NULL AS NVARCHAR(80)) AS ActivityCode,
    CAST(NULL AS NVARCHAR(255)) AS ActivityName,
    CAST(NULL AS NVARCHAR(80)) AS EconomicCode,
    CAST(NULL AS NVARCHAR(255)) AS EconomicName,
    Segment1,
    Segment2,
    Segment3,
    Segment4,
    Segment5,
    Segment6,
    Segment7,
    Segment8,
    Segment9,
    Segment10,
    Segment11,
    Segment12,
    Segment13,
    Segment14,
    Segment15,
    BudgetAmount,
    ReleasedAmount,
    WarrantAmount,
    CommitmentAmount,
    ActualAmount,
    AvailableBalance,
    ExecutionRate,
    PriorYearBudgetAmount,
    PriorYearActualAmount,
    ActualVsPriorYearPct,
    CurrencyCode,
    SensitivityLabel,
    DataLoadBatchCode,
    SourceSystemCode,
    LoadedDate
FROM dbo.vwAI_BudgetLedgerGeneric;
');
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasets', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblAnalysisDatasets AS target
    USING (
        SELECT
            N'BUDGET_LEDGER_ANALYSIS' AS DatasetCode,
            N'Budget Ledger Analysis' AS DatasetName,
            N'Governed budget ledger dataset for executive AI analysis. Semantic aliases are applied by dbo.tblAIBudgetLedgerSegmentRoleMap.' AS [Description],
            N'dbo.vwAI_BudgetLedgerAnalysis' AS SourceObjectName,
            N'VIEW' AS SourceType,
            N'RESTRICTED' AS SensitivityLevel,
            N'ANALYSIS_DATASET_ANALYZE' AS AllowedPermissionCodes,
            N'FiscalYearID' AS DefaultFiscalYearColumn,
            N'BudgetVersionID' AS DefaultVersionColumn,
            CAST(250 AS INT) AS MaxRows,
            CAST(1 AS BIT) AS RequireContext,
            CAST(1 AS BIT) AS IsActive,
            N'Semantic user-facing view over generic Segment1..Segment15 source table.' AS Notes
    ) AS source
    ON target.DatasetCode = source.DatasetCode
    WHEN MATCHED THEN
        UPDATE SET
            DatasetName = source.DatasetName,
            [Description] = source.[Description],
            SourceObjectName = source.SourceObjectName,
            SourceType = source.SourceType,
            SensitivityLevel = source.SensitivityLevel,
            AllowedPermissionCodes = source.AllowedPermissionCodes,
            DefaultFiscalYearColumn = source.DefaultFiscalYearColumn,
            DefaultVersionColumn = source.DefaultVersionColumn,
            MaxRows = source.MaxRows,
            RequireContext = source.RequireContext,
            IsActive = source.IsActive,
            Notes = source.Notes,
            UpdatedDate = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT
            (DatasetCode, DatasetName, [Description], SourceObjectName, SourceType, SensitivityLevel, AllowedPermissionCodes,
             DefaultFiscalYearColumn, DefaultVersionColumn, MaxRows, RequireContext, IsActive, Notes)
        VALUES
            (source.DatasetCode, source.DatasetName, source.[Description], source.SourceObjectName, source.SourceType, source.SensitivityLevel,
             source.AllowedPermissionCodes, source.DefaultFiscalYearColumn, source.DefaultVersionColumn, source.MaxRows, source.RequireContext,
             source.IsActive, source.Notes);
END;
GO
