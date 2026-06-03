IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tblGLGrouping' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE dbo.tblGLGrouping (
        GLGroupingID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        ReportID INT NOT NULL,
        Level INT NOT NULL,
        Prefix NVARCHAR(20) NOT NULL,
        GroupName NVARCHAR(200) NOT NULL,
        TransactionType NVARCHAR(20) NULL,
        MathSign NVARCHAR(5) NULL,
        MathSignage INT NULL,
        SortOrder INT NULL,
        BudgetClassID INT NULL,
        Ceiling NVARCHAR(50) NULL,
        UpdatedBy INT NULL,
        DateUpdated DATETIME NULL
    );

    CREATE INDEX IX_tblGLGrouping_ScopeLevelPrefix
        ON dbo.tblGLGrouping (FiscalYearID, VersionID, ReportID, Level, Prefix);

    CREATE INDEX IX_tblGLGrouping_ScopeSort
        ON dbo.tblGLGrouping (FiscalYearID, VersionID, ReportID, Level, SortOrder);
END;

IF OBJECT_ID('tempdb..#src') IS NOT NULL DROP TABLE #src;

SELECT
    r.FiscalYearID,
    r.VersionID,
    r.ReportID,
    CASE
        WHEN r.DetailedGLCode = 1 THEN 3
        WHEN r.TotalGrouping2 = 1 THEN 2
        ELSE 1
    END AS Level,
    CAST(r.Level1ID AS NVARCHAR(20)) AS Prefix,
    r.Level1Name AS GroupName,
    r.TransactionType,
    r.MathSign,
    r.MathSignage,
    r.SortOrder,
    r.BudgetClassID,
    r.Ceiling,
    r.UpdatedBy,
    r.DateUpdated
INTO #src
FROM dbo.tblReportLayoutLevel1 r
WHERE r.BudgetID = 7
  AND r.VersionID = 7
  AND r.ReportID = 1;

INSERT INTO dbo.tblGLGrouping (
    FiscalYearID, VersionID, ReportID, Level, Prefix, GroupName,
    TransactionType, MathSign, MathSignage, SortOrder, BudgetClassID, Ceiling, UpdatedBy, DateUpdated
)
SELECT 2026 AS FiscalYearID, s.VersionID, s.ReportID, s.Level, s.Prefix, s.GroupName,
       s.TransactionType, s.MathSign, s.MathSignage, s.SortOrder, s.BudgetClassID, s.Ceiling, s.UpdatedBy, s.DateUpdated
FROM #src s
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblGLGrouping g
    WHERE g.FiscalYearID = 2026
      AND g.VersionID = s.VersionID
      AND g.ReportID = s.ReportID
      AND g.Level = s.Level
      AND g.Prefix = s.Prefix
      AND g.GroupName = s.GroupName
);
