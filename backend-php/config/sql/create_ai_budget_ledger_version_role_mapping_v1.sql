/*
    Add version-role governance for the AI Budget Ledger dataset.

    Why this exists:
    - Raw BudgetVersionID values are not comparable across fiscal years.
    - Actuals are loaded into the execution version for each fiscal year, and that VersionID can change.
    - Single-year execution analysis should compare a mapped budget baseline version to a mapped
      execution actuals version within the same fiscal year.
    - Multi-year trend analysis should compare the same VersionTypeID / role across fiscal years.

    After running this script, populate dbo.tblAIBudgetLedgerVersionRoleMap for each fiscal year.
    Use the diagnostics at the bottom to identify candidate versions.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerAnalysisSource was not found. Run create_ai_budget_ledger_analysis_dataset_v1.sql and load data first.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerAnalysis', N'V') IS NULL
BEGIN
    RAISERROR('dbo.vwAI_BudgetLedgerAnalysis was not found. Run create_ai_budget_ledger_semantic_mapping_v1.sql first.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerVersionRoleMap', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIBudgetLedgerVersionRoleMap
    (
        VersionRoleMapID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        BudgetVersionID INT NOT NULL,
        VersionTypeID INT NULL,
        VersionRoleCode NVARCHAR(80) NOT NULL,
        VersionRoleName NVARCHAR(120) NOT NULL,
        IsBudgetBaseline BIT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerVersionRoleMap_IsBudgetBaseline DEFAULT (0),
        IsExecutionActuals BIT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerVersionRoleMap_IsExecutionActuals DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerVersionRoleMap_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(1000) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerVersionRoleMap_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblAIBudgetLedgerVersionRoleMap_RoleFlag CHECK
        (
            IsBudgetBaseline IN (0, 1)
            AND IsExecutionActuals IN (0, 1)
            AND NOT (IsBudgetBaseline = 1 AND IsExecutionActuals = 1)
        )
    );

    CREATE UNIQUE INDEX UX_tblAIBudgetLedgerVersionRoleMap_Role
        ON dbo.tblAIBudgetLedgerVersionRoleMap (FiscalYearID, BudgetVersionID, VersionRoleCode);

    CREATE INDEX IX_tblAIBudgetLedgerVersionRoleMap_Context
        ON dbo.tblAIBudgetLedgerVersionRoleMap (ActiveFlag, FiscalYearID, VersionTypeID, VersionRoleCode)
        INCLUDE (BudgetVersionID, IsBudgetBaseline, IsExecutionActuals);
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerVersionRoleCandidates', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerVersionRoleCandidates AS SELECT CAST(NULL AS INT) AS FiscalYearID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerVersionRoleCandidates
AS
SELECT
    a.FiscalYearID,
    a.BudgetVersionID,
    VersionTypeID = v.VersionTypeID,
    VersionTypeCode = vt.VersionTypeCode,
    VersionTypeName = vt.VersionTypeName,
    VersionLabel = v.VersionLabel,
    VersionStatus = v.VersionStatus,
    IsDefault = v.IsDefault,
    BaseFiscalYearID = v.BaseFiscalYearID,
    BaseVersionID = v.BaseVersionID,
    TotalRows = COUNT_BIG(1),
    BudgetAmount = CAST(SUM(a.BudgetAmount) AS DECIMAL(28,2)),
    ActualAmount = CAST(SUM(a.ActualAmount) AS DECIMAL(28,2)),
    SuggestedRoleCode = CASE
        WHEN UPPER(ISNULL(vt.VersionTypeCode, N'''')) = N''EXECUTION'' THEN N''EXECUTION_ACTUALS''
        WHEN UPPER(ISNULL(vt.VersionTypeCode, N'''')) = N''SUBMISSION'' THEN N''BUDGET_BASELINE''
        ELSE N''REVIEW_REQUIRED''
    END
FROM dbo.vwAI_BudgetLedgerAnalysis a
LEFT JOIN dbo.tblVersions v
    ON v.FiscalYearID = a.FiscalYearID
   AND v.VersionID = a.BudgetVersionID
LEFT JOIN dbo.tblVersionTypes vt
    ON vt.VersionTypeID = v.VersionTypeID
GROUP BY
    a.FiscalYearID,
    a.BudgetVersionID,
    v.VersionTypeID,
    vt.VersionTypeCode,
    vt.VersionTypeName,
    v.VersionLabel,
    v.VersionStatus,
    v.IsDefault,
    v.BaseFiscalYearID,
    v.BaseVersionID;
');
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerVersionedAnalysis', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerVersionedAnalysis AS SELECT CAST(NULL AS INT) AS BudgetLedgerAnalysisID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerVersionedAnalysis
AS
SELECT
    a.*,
    VersionTypeID = COALESCE(m.VersionTypeID, v.VersionTypeID),
    VersionTypeCode = vt.VersionTypeCode,
    VersionTypeName = vt.VersionTypeName,
    VersionRoleCode = m.VersionRoleCode,
    VersionRoleName = m.VersionRoleName,
    IsBudgetBaseline = CAST(COALESCE(m.IsBudgetBaseline, 0) AS BIT),
    IsExecutionActuals = CAST(COALESCE(m.IsExecutionActuals, 0) AS BIT),
    VersionRoleMappedFlag = CAST(CASE WHEN m.VersionRoleMapID IS NULL THEN 0 ELSE 1 END AS BIT)
FROM dbo.vwAI_BudgetLedgerAnalysis a
LEFT JOIN dbo.tblAIBudgetLedgerVersionRoleMap m
    ON m.FiscalYearID = a.FiscalYearID
   AND m.BudgetVersionID = a.BudgetVersionID
   AND m.ActiveFlag = 1
LEFT JOIN dbo.tblVersions v
    ON v.FiscalYearID = a.FiscalYearID
   AND v.VersionID = a.BudgetVersionID
LEFT JOIN dbo.tblVersionTypes vt
    ON vt.VersionTypeID = COALESCE(m.VersionTypeID, v.VersionTypeID);
');
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerSingleYearRoleAnalysis', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerSingleYearRoleAnalysis AS SELECT CAST(NULL AS INT) AS FiscalYearID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerSingleYearRoleAnalysis
AS
SELECT
    FiscalYearID,
    BudgetVersionID,
    VersionTypeID,
    VersionTypeCode,
    VersionRoleCode,
    VersionRoleName,
    IsBudgetBaseline,
    IsExecutionActuals,
    PeriodNo,
    Segment1,
    ProgramCode,
    EconomicCode,
    CurrencyCode,
    BudgetAmount = CAST(SUM(BudgetAmount) AS DECIMAL(28,2)),
    ReleasedAmount = CAST(SUM(ReleasedAmount) AS DECIMAL(28,2)),
    WarrantAmount = CAST(SUM(WarrantAmount) AS DECIMAL(28,2)),
    CommitmentAmount = CAST(SUM(CommitmentAmount) AS DECIMAL(28,2)),
    ActualAmount = CAST(SUM(ActualAmount) AS DECIMAL(28,2)),
    AvailableBalance = CAST(SUM(AvailableBalance) AS DECIMAL(28,2))
FROM dbo.vwAI_BudgetLedgerVersionedAnalysis
WHERE VersionRoleMappedFlag = 1
GROUP BY
    FiscalYearID,
    BudgetVersionID,
    VersionTypeID,
    VersionTypeCode,
    VersionRoleCode,
    VersionRoleName,
    IsBudgetBaseline,
    IsExecutionActuals,
    PeriodNo,
    Segment1,
    ProgramCode,
    EconomicCode,
    CurrencyCode;
');
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerMultiYearRoleTrend', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAI_BudgetLedgerMultiYearRoleTrend AS SELECT CAST(NULL AS INT) AS FiscalYearID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAI_BudgetLedgerMultiYearRoleTrend
AS
SELECT
    FiscalYearID,
    VersionTypeID,
    VersionTypeCode,
    VersionRoleCode,
    VersionRoleName,
    Segment1,
    ProgramCode,
    EconomicCode,
    CurrencyCode,
    BudgetAmount = CAST(SUM(BudgetAmount) AS DECIMAL(28,2)),
    ActualAmount = CAST(SUM(ActualAmount) AS DECIMAL(28,2)),
    AvailableBalance = CAST(SUM(AvailableBalance) AS DECIMAL(28,2)),
    ExecutionRate = CAST(CASE WHEN SUM(BudgetAmount) <> 0 THEN (SUM(ActualAmount) / NULLIF(SUM(BudgetAmount), 0)) * 100.0 ELSE NULL END AS DECIMAL(18,6))
FROM dbo.vwAI_BudgetLedgerVersionedAnalysis
WHERE VersionRoleMappedFlag = 1
GROUP BY
    FiscalYearID,
    VersionTypeID,
    VersionTypeCode,
    VersionRoleCode,
    VersionRoleName,
    Segment1,
    ProgramCode,
    EconomicCode,
    CurrencyCode;
');
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasets', N'U') IS NOT NULL
BEGIN
    UPDATE dbo.tblAnalysisDatasets
    SET DatasetName = N'Budget Ledger Versioned Analysis',
        [Description] = N'Budget ledger analysis rows enriched with version type and governed version role mapping.',
        SourceObjectName = N'dbo.vwAI_BudgetLedgerVersionedAnalysis',
        SourceType = N'VIEW',
        SensitivityLevel = N'RESTRICTED',
        AllowedPermissionCodes = N'ANALYSIS_DATASET_ANALYZE',
        DefaultFiscalYearColumn = N'FiscalYearID',
        DefaultVersionColumn = N'BudgetVersionID',
        MaxRows = 250,
        RequireContext = 1,
        IsActive = 1,
        Notes = N'Use this when questions need explicit budget version role context.',
        UpdatedDate = SYSUTCDATETIME()
    WHERE DatasetCode = N'BUDGET_LEDGER_VERSIONED_ANALYSIS';

    IF @@ROWCOUNT = 0
    BEGIN
        INSERT INTO dbo.tblAnalysisDatasets
            (DatasetCode, DatasetName, [Description], SourceObjectName, SourceType, SensitivityLevel, AllowedPermissionCodes,
             DefaultFiscalYearColumn, DefaultVersionColumn, MaxRows, RequireContext, IsActive, Notes)
        VALUES
            (N'BUDGET_LEDGER_VERSIONED_ANALYSIS', N'Budget Ledger Versioned Analysis',
             N'Budget ledger analysis rows enriched with version type and governed version role mapping.',
             N'dbo.vwAI_BudgetLedgerVersionedAnalysis', N'VIEW', N'RESTRICTED', N'ANALYSIS_DATASET_ANALYZE',
             N'FiscalYearID', N'BudgetVersionID', 250, 1, 1,
             N'Use this when questions need explicit budget version role context.');
    END;

    UPDATE dbo.tblAnalysisDatasets
    SET DatasetName = N'Budget Ledger Multi-Year Role Trend',
        [Description] = N'Multi-year trend view grouped by the same governed version role and semantic budget dimensions.',
        SourceObjectName = N'dbo.vwAI_BudgetLedgerMultiYearRoleTrend',
        SourceType = N'VIEW',
        SensitivityLevel = N'RESTRICTED',
        AllowedPermissionCodes = N'ANALYSIS_DATASET_ANALYZE',
        DefaultFiscalYearColumn = N'FiscalYearID',
        DefaultVersionColumn = NULL,
        MaxRows = 250,
        RequireContext = 1,
        IsActive = 1,
        Notes = N'Use this for cross-year comparisons where the same VersionTypeID or VersionRoleCode is required.',
        UpdatedDate = SYSUTCDATETIME()
    WHERE DatasetCode = N'BUDGET_LEDGER_MULTI_YEAR_ROLE_TREND';

    IF @@ROWCOUNT = 0
    BEGIN
        INSERT INTO dbo.tblAnalysisDatasets
            (DatasetCode, DatasetName, [Description], SourceObjectName, SourceType, SensitivityLevel, AllowedPermissionCodes,
             DefaultFiscalYearColumn, DefaultVersionColumn, MaxRows, RequireContext, IsActive, Notes)
        VALUES
            (N'BUDGET_LEDGER_MULTI_YEAR_ROLE_TREND', N'Budget Ledger Multi-Year Role Trend',
             N'Multi-year trend view grouped by the same governed version role and semantic budget dimensions.',
             N'dbo.vwAI_BudgetLedgerMultiYearRoleTrend', N'VIEW', N'RESTRICTED', N'ANALYSIS_DATASET_ANALYZE',
             N'FiscalYearID', NULL, 250, 1, 1,
             N'Use this for cross-year comparisons where the same VersionTypeID or VersionRoleCode is required.');
    END;
END;
GO

SELECT
    FiscalYearID,
    BudgetVersionID,
    VersionTypeID,
    VersionTypeCode,
    VersionLabel,
    VersionStatus,
    IsDefault,
    TotalRows,
    BudgetAmount,
    ActualAmount,
    SuggestedRoleCode
FROM dbo.vwAI_BudgetLedgerVersionRoleCandidates
ORDER BY FiscalYearID DESC, VersionTypeCode, BudgetVersionID;
GO
