/*
    Add a semantic mapping layer for the AI Budget Ledger dataset.

    dbo.tblAIBudgetLedgerAnalysisSource stores generic Segment1..Segment15 values only.
    This script maps those generic segments to semantic aliases such as Vote, Program, and Economic.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerAnalysisSource was not found. Run create_ai_budget_ledger_analysis_dataset_v1.sql first.', 16, 1);
    RETURN;
END;
GO

IF COL_LENGTH(N'dbo.tblAIBudgetLedgerAnalysisSource', N'VoteCode') IS NOT NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerAnalysisSource still has legacy semantic columns such as VoteCode. In development, drop/recreate this table and reload using the updated scripts.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerSegmentRoleMap', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIBudgetLedgerSegmentRoleMap
    (
        SegmentRoleMapID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MappingCode NVARCHAR(80) NOT NULL,
        SourceSystemCode NVARCHAR(80) NULL,
        FiscalYearID INT NULL,
        BudgetVersionID INT NULL,
        SegmentNo INT NOT NULL,
        SegmentColumnName AS (CONVERT(NVARCHAR(20), N'Segment' + CONVERT(NVARCHAR(2), SegmentNo))) PERSISTED,
        SemanticRoleCode NVARCHAR(80) NOT NULL,
        SemanticRoleName NVARCHAR(120) NOT NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerSegmentRoleMap_DisplayOrder DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblAIBudgetLedgerSegmentRoleMap_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(1000) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIBudgetLedgerSegmentRoleMap_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT CK_tblAIBudgetLedgerSegmentRoleMap_SegmentNo CHECK (SegmentNo BETWEEN 1 AND 15)
    );

    CREATE UNIQUE INDEX UX_tblAIBudgetLedgerSegmentRoleMap_Code
        ON dbo.tblAIBudgetLedgerSegmentRoleMap (MappingCode);

    CREATE INDEX IX_tblAIBudgetLedgerSegmentRoleMap_Context
        ON dbo.tblAIBudgetLedgerSegmentRoleMap (ActiveFlag, SourceSystemCode, FiscalYearID, BudgetVersionID, SemanticRoleCode);
END;
GO

MERGE dbo.tblAIBudgetLedgerSegmentRoleMap AS target
USING (
    SELECT *
    FROM (VALUES
        (N'CBMSGOL_2026_SEGMENT1_VOTE', N'CBMSGOL', 2026, CAST(NULL AS INT), 1, N'VOTE', N'Vote', 10, N'Current CBMSGOL mapping. Segment1 is treated as Vote for executive analysis.'),
        (N'CBMSGOL_2026_SEGMENT2_MINISTRY', N'CBMSGOL', 2026, CAST(NULL AS INT), 2, N'MINISTRY', N'Ministry', 20, N'Current CBMSGOL mapping. Segment2 is treated as Ministry.'),
        (N'CBMSGOL_2026_SEGMENT3_DEPARTMENT', N'CBMSGOL', 2026, CAST(NULL AS INT), 3, N'DEPARTMENT', N'Department / Cost Centre', 30, N'Current CBMSGOL mapping. Segment3 is treated as Department or Cost Centre.'),
        (N'CBMSGOL_2026_SEGMENT4_FUND', N'CBMSGOL', 2026, CAST(NULL AS INT), 4, N'FUND', N'Fund', 40, N'Current CBMSGOL mapping. Segment4 is treated as Fund.'),
        (N'CBMSGOL_2026_SEGMENT5_PROGRAM', N'CBMSGOL', 2026, CAST(NULL AS INT), 5, N'PROGRAM', N'Program', 50, N'Current CBMSGOL mapping. Segment5 is treated as Program.'),
        (N'CBMSGOL_2026_SEGMENT6_ACTIVITY', N'CBMSGOL', 2026, CAST(NULL AS INT), 6, N'ACTIVITY', N'Activity', 60, N'Current CBMSGOL mapping. Segment6 is treated as Activity.'),
        (N'CBMSGOL_2026_SEGMENT11_ECONOMIC', N'CBMSGOL', 2026, CAST(NULL AS INT), 11, N'ECONOMIC', N'Economic Item', 110, N'Current CBMSGOL mapping. Segment11 is treated as Economic Item.')
    ) AS rows (MappingCode, SourceSystemCode, FiscalYearID, BudgetVersionID, SegmentNo, SemanticRoleCode, SemanticRoleName, DisplayOrder, Notes)
) AS source
ON target.MappingCode = source.MappingCode
WHEN MATCHED THEN
    UPDATE SET
        SourceSystemCode = source.SourceSystemCode,
        FiscalYearID = source.FiscalYearID,
        BudgetVersionID = source.BudgetVersionID,
        SegmentNo = source.SegmentNo,
        SemanticRoleCode = source.SemanticRoleCode,
        SemanticRoleName = source.SemanticRoleName,
        DisplayOrder = source.DisplayOrder,
        ActiveFlag = 1,
        Notes = source.Notes
WHEN NOT MATCHED THEN
    INSERT
        (MappingCode, SourceSystemCode, FiscalYearID, BudgetVersionID, SegmentNo, SemanticRoleCode, SemanticRoleName, DisplayOrder, ActiveFlag, Notes)
    VALUES
        (source.MappingCode, source.SourceSystemCode, source.FiscalYearID, source.BudgetVersionID, source.SegmentNo, source.SemanticRoleCode, source.SemanticRoleName, source.DisplayOrder, 1, source.Notes);
GO

UPDATE dbo.tblAIBudgetLedgerSegmentRoleMap
SET ActiveFlag = 0,
    Notes = COALESCE(Notes + N' ', N'') + N'Deactivated because CBMSGOL Economic Code is mapped to Segment11.'
WHERE MappingCode = N'CBMSGOL_2026_SEGMENT7_ECONOMIC';
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
WITH RoleMap AS
(
    SELECT
        SourceSystemCode,
        FiscalYearID,
        BudgetVersionID,
        SemanticRoleCode,
        SegmentNo,
        rn = ROW_NUMBER() OVER (
            PARTITION BY SourceSystemCode, FiscalYearID, BudgetVersionID, SemanticRoleCode
            ORDER BY DisplayOrder, SegmentNo
        )
    FROM dbo.tblAIBudgetLedgerSegmentRoleMap
    WHERE ActiveFlag = 1
),
Mapped AS
(
    SELECT
        s.*,
        VoteCode = CASE vote.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        MinistryCode = CASE ministry.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        DepartmentCode = CASE department.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        FundCode = CASE fund.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        ProgramCode = CASE program.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        ActivityCode = CASE activity.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END,
        EconomicCode = CASE economic.SegmentNo
            WHEN 1 THEN s.Segment1 WHEN 2 THEN s.Segment2 WHEN 3 THEN s.Segment3 WHEN 4 THEN s.Segment4 WHEN 5 THEN s.Segment5
            WHEN 6 THEN s.Segment6 WHEN 7 THEN s.Segment7 WHEN 8 THEN s.Segment8 WHEN 9 THEN s.Segment9 WHEN 10 THEN s.Segment10
            WHEN 11 THEN s.Segment11 WHEN 12 THEN s.Segment12 WHEN 13 THEN s.Segment13 WHEN 14 THEN s.Segment14 WHEN 15 THEN s.Segment15 END
    FROM dbo.tblAIBudgetLedgerAnalysisSource s
    LEFT JOIN RoleMap vote
        ON vote.SemanticRoleCode = N''VOTE''
       AND vote.rn = 1
       AND ISNULL(vote.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(vote.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(vote.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap ministry
        ON ministry.SemanticRoleCode = N''MINISTRY''
       AND ministry.rn = 1
       AND ISNULL(ministry.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(ministry.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(ministry.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap department
        ON department.SemanticRoleCode = N''DEPARTMENT''
       AND department.rn = 1
       AND ISNULL(department.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(department.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(department.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap fund
        ON fund.SemanticRoleCode = N''FUND''
       AND fund.rn = 1
       AND ISNULL(fund.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(fund.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(fund.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap program
        ON program.SemanticRoleCode = N''PROGRAM''
       AND program.rn = 1
       AND ISNULL(program.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(program.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(program.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap activity
        ON activity.SemanticRoleCode = N''ACTIVITY''
       AND activity.rn = 1
       AND ISNULL(activity.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(activity.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(activity.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    LEFT JOIN RoleMap economic
        ON economic.SemanticRoleCode = N''ECONOMIC''
       AND economic.rn = 1
       AND ISNULL(economic.SourceSystemCode, ISNULL(s.SourceSystemCode, N'''')) = ISNULL(s.SourceSystemCode, N'''')
       AND ISNULL(economic.FiscalYearID, s.FiscalYearID) = s.FiscalYearID
       AND ISNULL(economic.BudgetVersionID, ISNULL(s.BudgetVersionID, -1)) = ISNULL(s.BudgetVersionID, -1)
    WHERE s.IsActive = 1
)
SELECT
    BudgetLedgerAnalysisID,
    SourceRowReference,
    FiscalYearID,
    BudgetVersionID,
    PeriodNo,
    PostingDate,
    VoteCode,
    CAST(NULL AS NVARCHAR(255)) AS VoteName,
    MinistryCode,
    CAST(NULL AS NVARCHAR(255)) AS MinistryName,
    DepartmentCode,
    CAST(NULL AS NVARCHAR(255)) AS DepartmentName,
    FundCode,
    CAST(NULL AS NVARCHAR(255)) AS FundName,
    ProgramCode,
    CAST(NULL AS NVARCHAR(255)) AS ProgramName,
    ActivityCode,
    CAST(NULL AS NVARCHAR(255)) AS ActivityName,
    EconomicCode,
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
FROM Mapped;
');
GO

IF OBJECT_ID(N'dbo.tblAIDatasets', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblAIDatasets AS target
    USING (
        SELECT
            N'BUDGET_LEDGER_GENERIC' AS DatasetCode,
            N'Budget Ledger Generic Segments' AS DatasetName,
            N'Generic segment-based budget ledger view with Segment1..Segment15 and financial measures only. Use this when semantic aliases such as Vote or Program are not appropriate.' AS [Description],
            N'dbo.vwAI_BudgetLedgerGeneric' AS SourceObjectName,
            N'VIEW' AS SourceType,
            N'RESTRICTED' AS SensitivityLevel,
            N'AI_DATASET_ANALYZE' AS AllowedPermissionCodes,
            N'FiscalYearID' AS DefaultFiscalYearColumn,
            N'BudgetVersionID' AS DefaultVersionColumn,
            CAST(250 AS INT) AS MaxRows,
            CAST(1 AS BIT) AS RequireContext,
            CAST(1 AS BIT) AS IsActive,
            N'Generic view over dbo.tblAIBudgetLedgerAnalysisSource. Semantic aliases are handled by dbo.vwAI_BudgetLedgerAnalysis.' AS Notes
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

SELECT
    MappingCode,
    SourceSystemCode,
    FiscalYearID,
    BudgetVersionID,
    SegmentNo,
    SegmentColumnName,
    SemanticRoleCode,
    SemanticRoleName,
    ActiveFlag
FROM dbo.tblAIBudgetLedgerSegmentRoleMap
ORDER BY SourceSystemCode, FiscalYearID, DisplayOrder, SegmentNo;
GO
