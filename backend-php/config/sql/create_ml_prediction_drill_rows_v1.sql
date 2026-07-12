/*
    Create indexed snapshot rows for ML prediction drill-through.

    This avoids querying very large budget ledger views on demand from the UI.
    Rows are populated when predictions are generated.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblMLModels', N'U') IS NULL
BEGIN
    RAISERROR('ML prediction tables were not found. Run create_intelligence_platform_foundation_v1.sql first.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictionDrillRows', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLPredictionDrillRows
    (
        MLPredictionDrillRowID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLPredictionID INT NOT NULL,
        MLModelID INT NOT NULL,
        EntityTypeCode NVARCHAR(60) NOT NULL,
        EntityCode NVARCHAR(120) NOT NULL,
        FiscalYearID INT NULL,
        BudgetVersionID INT NULL,
        PeriodNo INT NULL,
        Segment1 NVARCHAR(50) NULL,
        Segment2 NVARCHAR(50) NULL,
        Segment3 NVARCHAR(50) NULL,
        ProgramCode NVARCHAR(80) NULL,
        EconomicCode NVARCHAR(80) NULL,
        CurrencyCode NVARCHAR(10) NULL,
        BudgetAmount DECIMAL(28,2) NULL,
        ActualAmount DECIMAL(28,2) NULL,
        AvailableBalance DECIMAL(28,2) NULL,
        ExecutionRate DECIMAL(18,6) NULL,
        CumulativeBudgetAmount DECIMAL(28,2) NULL,
        CumulativeActualAmount DECIMAL(28,2) NULL,
        CumulativeExecutionRate DECIMAL(18,6) NULL,
        ExpectedExecutionRate DECIMAL(18,6) NULL,
        VarianceAmount DECIMAL(28,2) NULL,
        VariancePct DECIMAL(18,6) NULL,
        RiskScore DECIMAL(9,4) NULL,
        RiskLabel NVARCHAR(20) NULL,
        AnomalyTypeCode NVARCHAR(80) NULL,
        RiskReason NVARCHAR(500) NULL,
        DetailJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLPredictionDrillRows_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblMLPredictionDrillRows_Prediction
            FOREIGN KEY (MLPredictionID) REFERENCES dbo.tblMLPredictions(MLPredictionID),
        CONSTRAINT FK_tblMLPredictionDrillRows_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID)
    );
END;
GO

IF COL_LENGTH(N'dbo.tblMLPredictionDrillRows', N'AnomalyTypeCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblMLPredictionDrillRows
        ADD AnomalyTypeCode NVARCHAR(80) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblMLPredictionDrillRows')
      AND name = N'IX_tblMLPredictionDrillRows_Prediction'
)
BEGIN
    CREATE INDEX IX_tblMLPredictionDrillRows_Prediction
        ON dbo.tblMLPredictionDrillRows (MLPredictionID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblMLPredictionDrillRows')
      AND name = N'IX_tblMLPredictionDrillRows_Entity'
)
BEGIN
    CREATE INDEX IX_tblMLPredictionDrillRows_Entity
        ON dbo.tblMLPredictionDrillRows
            (MLModelID, FiscalYearID, BudgetVersionID, PeriodNo, Segment1, ProgramCode, EconomicCode)
        INCLUDE (BudgetAmount, ActualAmount, AvailableBalance, RiskScore, RiskLabel);
END;
GO
