/*
    Seed a local dummy integration scenario for environments without external API access.

    This creates:
    - dbo.tblIntegrationDummyBudgetExportSource with sample approved budget rows
    - dbo.tblIntegrationDummyActualsSource with sample FMIS actual expenditure rows
    - MOCK_FINANCE external system
    - MOCK_BUDGET_EXPORT outbound interface that uses the test export runner
    - MOCK_ACTUALS_IMPORT mock actuals import interface that uses the same runner/dispatch path
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblIntegrationSystem', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblIntegrationInterface', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblIntegrationRun', N'U') IS NULL
BEGIN
    RAISERROR('Integration foundation tables were not found. Run create_api_integration_foundation_v1.sql before seed_integration_dummy_scenario.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationDummyBudgetExportSource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationDummyBudgetExportSource
    (
        DummyBudgetExportSourceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DummyRecordCode NVARCHAR(80) NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(80) NULL,
        ProgramCode NVARCHAR(80) NOT NULL,
        ProgramName NVARCHAR(200) NOT NULL,
        EconomicCode NVARCHAR(80) NOT NULL,
        EconomicName NVARCHAR(200) NOT NULL,
        ApprovedBudgetAmount DECIMAL(18,2) NOT NULL,
        Q1Amount DECIMAL(18,2) NOT NULL,
        Q2Amount DECIMAL(18,2) NOT NULL,
        Q3Amount DECIMAL(18,2) NOT NULL,
        Q4Amount DECIMAL(18,2) NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationDummyBudgetExportSource_ActiveFlag DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationDummyBudgetExportSource_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX UX_tblIntegrationDummyBudgetExportSource_Record
        ON dbo.tblIntegrationDummyBudgetExportSource (DummyRecordCode);
END;
GO

MERGE dbo.tblIntegrationDummyBudgetExportSource AS target
USING (
    SELECT *
    FROM (VALUES
        (N'MOCK-BUDGET-001', 2026, 1, N'100', N'PLN-001', N'Budget Strategy Support', N'211', N'Personnel Emoluments', CAST(1250000.00 AS DECIMAL(18,2)), CAST(312500.00 AS DECIMAL(18,2)), CAST(312500.00 AS DECIMAL(18,2)), CAST(312500.00 AS DECIMAL(18,2)), CAST(312500.00 AS DECIMAL(18,2))),
        (N'MOCK-BUDGET-002', 2026, 1, N'100', N'PLN-002', N'Fiscal Planning Operations', N'221', N'Goods and Services', CAST(480000.00 AS DECIMAL(18,2)), CAST(120000.00 AS DECIMAL(18,2)), CAST(120000.00 AS DECIMAL(18,2)), CAST(120000.00 AS DECIMAL(18,2)), CAST(120000.00 AS DECIMAL(18,2))),
        (N'MOCK-BUDGET-003', 2026, 1, N'200', N'EXE-001', N'Budget Execution Controls', N'311', N'Capital Assets', CAST(890000.00 AS DECIMAL(18,2)), CAST(200000.00 AS DECIMAL(18,2)), CAST(240000.00 AS DECIMAL(18,2)), CAST(225000.00 AS DECIMAL(18,2)), CAST(225000.00 AS DECIMAL(18,2))),
        (N'MOCK-BUDGET-004', 2026, 1, N'300', N'ANA-001', N'Analytics and Reporting', N'221', N'Goods and Services', CAST(315000.00 AS DECIMAL(18,2)), CAST(78750.00 AS DECIMAL(18,2)), CAST(78750.00 AS DECIMAL(18,2)), CAST(78750.00 AS DECIMAL(18,2)), CAST(78750.00 AS DECIMAL(18,2)))
    ) AS rows (
        DummyRecordCode,
        FiscalYearID,
        VersionID,
        DataObjectCode,
        ProgramCode,
        ProgramName,
        EconomicCode,
        EconomicName,
        ApprovedBudgetAmount,
        Q1Amount,
        Q2Amount,
        Q3Amount,
        Q4Amount
    )
) AS source
ON target.DummyRecordCode = source.DummyRecordCode
WHEN MATCHED THEN
    UPDATE SET
        FiscalYearID = source.FiscalYearID,
        VersionID = source.VersionID,
        DataObjectCode = source.DataObjectCode,
        ProgramCode = source.ProgramCode,
        ProgramName = source.ProgramName,
        EconomicCode = source.EconomicCode,
        EconomicName = source.EconomicName,
        ApprovedBudgetAmount = source.ApprovedBudgetAmount,
        Q1Amount = source.Q1Amount,
        Q2Amount = source.Q2Amount,
        Q3Amount = source.Q3Amount,
        Q4Amount = source.Q4Amount,
        ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT
        (DummyRecordCode, FiscalYearID, VersionID, DataObjectCode, ProgramCode, ProgramName, EconomicCode, EconomicName, ApprovedBudgetAmount, Q1Amount, Q2Amount, Q3Amount, Q4Amount, ActiveFlag)
    VALUES
        (source.DummyRecordCode, source.FiscalYearID, source.VersionID, source.DataObjectCode, source.ProgramCode, source.ProgramName, source.EconomicCode, source.EconomicName, source.ApprovedBudgetAmount, source.Q1Amount, source.Q2Amount, source.Q3Amount, source.Q4Amount, 1);
GO

IF OBJECT_ID(N'dbo.tblIntegrationDummyActualsSource', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationDummyActualsSource
    (
        DummyActualsSourceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TransactionReference NVARCHAR(80) NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        PeriodNo INT NOT NULL,
        PostingDate DATE NOT NULL,
        DataObjectCode NVARCHAR(80) NULL,
        ProgramCode NVARCHAR(80) NOT NULL,
        EconomicCode NVARCHAR(80) NOT NULL,
        SupplierName NVARCHAR(200) NULL,
        ActualAmount DECIMAL(18,2) NOT NULL,
        CurrencyCode NVARCHAR(10) NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationDummyActualsSource_ActiveFlag DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationDummyActualsSource_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX UX_tblIntegrationDummyActualsSource_Reference
        ON dbo.tblIntegrationDummyActualsSource (TransactionReference);
END;
GO

MERGE dbo.tblIntegrationDummyActualsSource AS target
USING (
    SELECT *
    FROM (VALUES
        (N'FMIS-ACT-0001', 2026, 1, 1, CAST('2026-01-15' AS DATE), N'100', N'PLN-001', N'211', N'Central Payroll Office', CAST(185250.00 AS DECIMAL(18,2)), N'LSL'),
        (N'FMIS-ACT-0002', 2026, 1, 2, CAST('2026-02-20' AS DATE), N'100', N'PLN-002', N'221', N'Office Supplies Vendor', CAST(42750.00 AS DECIMAL(18,2)), N'LSL'),
        (N'FMIS-ACT-0003', 2026, 1, 3, CAST('2026-03-10' AS DATE), N'200', N'EXE-001', N'311', N'Infrastructure Contractor', CAST(215000.00 AS DECIMAL(18,2)), N'LSL'),
        (N'FMIS-ACT-0004', 2026, 1, 4, CAST('2026-04-05' AS DATE), N'300', N'ANA-001', N'221', N'Analytics Services Provider', CAST(38750.00 AS DECIMAL(18,2)), N'LSL')
    ) AS rows (
        TransactionReference,
        FiscalYearID,
        VersionID,
        PeriodNo,
        PostingDate,
        DataObjectCode,
        ProgramCode,
        EconomicCode,
        SupplierName,
        ActualAmount,
        CurrencyCode
    )
) AS source
ON target.TransactionReference = source.TransactionReference
WHEN MATCHED THEN
    UPDATE SET
        FiscalYearID = source.FiscalYearID,
        VersionID = source.VersionID,
        PeriodNo = source.PeriodNo,
        PostingDate = source.PostingDate,
        DataObjectCode = source.DataObjectCode,
        ProgramCode = source.ProgramCode,
        EconomicCode = source.EconomicCode,
        SupplierName = source.SupplierName,
        ActualAmount = source.ActualAmount,
        CurrencyCode = source.CurrencyCode,
        ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT
        (TransactionReference, FiscalYearID, VersionID, PeriodNo, PostingDate, DataObjectCode, ProgramCode, EconomicCode, SupplierName, ActualAmount, CurrencyCode, ActiveFlag)
    VALUES
        (source.TransactionReference, source.FiscalYearID, source.VersionID, source.PeriodNo, source.PostingDate, source.DataObjectCode, source.ProgramCode, source.EconomicCode, source.SupplierName, source.ActualAmount, source.CurrencyCode, 1);
GO

DECLARE @SystemID INT;

MERGE dbo.tblIntegrationSystem AS target
USING (
    SELECT
        N'MOCK_FINANCE' AS SystemCode,
        N'Mock Finance System' AS SystemName,
        N'local://mock-finance' AS BaseUrl,
        N'none' AS AuthType,
        N'' AS CredentialReference,
        N'{"X-CBMS-Mock":"true"}' AS DefaultHeadersJson,
        N'DEV' AS EnvironmentCode,
        CAST(1 AS BIT) AS ActiveFlag,
        N'Local dummy finance system for integration testing without external API access.' AS Notes
) AS source
ON target.SystemCode = source.SystemCode
WHEN MATCHED THEN
    UPDATE SET
        SystemName = source.SystemName,
        BaseUrl = source.BaseUrl,
        AuthType = source.AuthType,
        CredentialReference = NULLIF(source.CredentialReference, N''),
        DefaultHeadersJson = source.DefaultHeadersJson,
        EnvironmentCode = source.EnvironmentCode,
        ActiveFlag = source.ActiveFlag,
        Notes = source.Notes,
        UpdatedDate = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT
        (SystemCode, SystemName, BaseUrl, AuthType, CredentialReference, DefaultHeadersJson, EnvironmentCode, ActiveFlag, Notes)
    VALUES
        (source.SystemCode, source.SystemName, source.BaseUrl, source.AuthType, NULLIF(source.CredentialReference, N''), source.DefaultHeadersJson, source.EnvironmentCode, source.ActiveFlag, source.Notes);

SELECT @SystemID = IntegrationSystemID
FROM dbo.tblIntegrationSystem
WHERE SystemCode = N'MOCK_FINANCE';

DECLARE @MappingConfigJson NVARCHAR(MAX) = N'{
  "source_type": "sql_table",
  "source_object": "dbo.tblIntegrationDummyBudgetExportSource",
  "context_filters": {
    "fiscal_year": "FiscalYearID",
    "version": "VersionID"
  },
  "filters": {
    "ActiveFlag": 1
  },
  "field_map": {
    "recordCode": "DummyRecordCode",
    "fiscalYear": "FiscalYearID",
    "version": "VersionID",
    "dataObjectCode": "DataObjectCode",
    "programCode": "ProgramCode",
    "programName": "ProgramName",
    "economicCode": "EconomicCode",
    "economicName": "EconomicName",
    "approvedBudgetAmount": "ApprovedBudgetAmount",
    "q1Amount": "Q1Amount",
    "q2Amount": "Q2Amount",
    "q3Amount": "Q3Amount",
    "q4Amount": "Q4Amount"
  },
  "amount_structure": {
    "total_field": "ApprovedBudgetAmount",
    "monthly_fields": ["Q1Amount", "Q2Amount", "Q3Amount", "Q4Amount"]
  },
  "export_grain": "program_economic_item",
  "notes": "Dummy interface for local integration testing. The test export runner builds a payload and records a run without calling an external endpoint."
}';

DECLARE @OutputProfilesJson NVARCHAR(MAX) = N'[
  {
    "code": "mock_finance_budget_v1",
    "label": "Mock Finance Budget API v1",
    "records_key": "budgetLines",
    "field_order": ["recordCode", "fiscalYear", "version", "dataObjectCode", "programCode", "programName", "economicCode", "economicName", "approvedBudgetAmount", "q1Amount", "q2Amount", "q3Amount", "q4Amount"]
  },
  {
    "code": "compact_budget_lines",
    "label": "Compact Budget Lines",
    "records_key": "lines",
    "include_fields": ["recordCode", "programCode", "economicCode", "approvedBudgetAmount"]
  }
]';

MERGE dbo.tblIntegrationInterface AS target
USING (
    SELECT
        N'MOCK_BUDGET_EXPORT' AS InterfaceCode,
        N'Mock Approved Budget Export' AS InterfaceName,
        @SystemID AS IntegrationSystemID,
        N'outbound' AS DirectionCode,
        N'Budget Planning' AS ModuleCode,
        N'Approved Budget' AS EntityCode,
        N'manual' AS TriggerMode,
        N'/api/mock/budget/export' AS EndpointPath,
        N'POST' AS HttpMethod,
        N'json' AS PayloadFormat,
        CAST(0 AS BIT) AS ContextRequiredFlag,
        CAST(1 AS BIT) AS FiscalYearRequiredFlag,
        CAST(1 AS BIT) AS VersionRequiredFlag,
        CAST(0 AS BIT) AS DataScopeRequiredFlag,
        25 AS BatchSize,
        30 AS TimeoutSeconds,
        @MappingConfigJson AS MappingConfigJson,
        @OutputProfilesJson AS OutputProfilesJson,
        N'mock_finance_budget_v1' AS DefaultOutputProfileCode,
        N'CBMS Integration Test Team' AS BusinessOwner,
        N'Local Dummy Source' AS SourceOwner,
        N'Approved for Testing' AS ApprovalStage,
        N'TEST_READY' AS ReadinessStatus,
        CAST(1 AS BIT) AS ActiveFlag,
        N'Use this interface to test integration exports without external API credentials. Open Integration Interfaces, click the play button, and run the preview.' AS Notes
) AS source
ON target.InterfaceCode = source.InterfaceCode
WHEN MATCHED THEN
    UPDATE SET
        InterfaceName = source.InterfaceName,
        IntegrationSystemID = source.IntegrationSystemID,
        DirectionCode = source.DirectionCode,
        ModuleCode = source.ModuleCode,
        EntityCode = source.EntityCode,
        TriggerMode = source.TriggerMode,
        EndpointPath = source.EndpointPath,
        HttpMethod = source.HttpMethod,
        PayloadFormat = source.PayloadFormat,
        ContextRequiredFlag = source.ContextRequiredFlag,
        FiscalYearRequiredFlag = source.FiscalYearRequiredFlag,
        VersionRequiredFlag = source.VersionRequiredFlag,
        DataScopeRequiredFlag = source.DataScopeRequiredFlag,
        BatchSize = source.BatchSize,
        TimeoutSeconds = source.TimeoutSeconds,
        MappingConfigJson = source.MappingConfigJson,
        OutputProfilesJson = source.OutputProfilesJson,
        DefaultOutputProfileCode = source.DefaultOutputProfileCode,
        BusinessOwner = source.BusinessOwner,
        SourceOwner = source.SourceOwner,
        ApprovalStage = source.ApprovalStage,
        ReadinessStatus = source.ReadinessStatus,
        ActiveFlag = source.ActiveFlag,
        Notes = source.Notes,
        UpdatedDate = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT
        (InterfaceCode, InterfaceName, IntegrationSystemID, DirectionCode, ModuleCode, EntityCode, TriggerMode, EndpointPath, HttpMethod, PayloadFormat, ContextRequiredFlag, FiscalYearRequiredFlag, VersionRequiredFlag, DataScopeRequiredFlag, BatchSize, TimeoutSeconds, MappingConfigJson, OutputProfilesJson, DefaultOutputProfileCode, BusinessOwner, SourceOwner, ApprovalStage, ReadinessStatus, ActiveFlag, Notes)
    VALUES
        (source.InterfaceCode, source.InterfaceName, source.IntegrationSystemID, source.DirectionCode, source.ModuleCode, source.EntityCode, source.TriggerMode, source.EndpointPath, source.HttpMethod, source.PayloadFormat, source.ContextRequiredFlag, source.FiscalYearRequiredFlag, source.VersionRequiredFlag, source.DataScopeRequiredFlag, source.BatchSize, source.TimeoutSeconds, source.MappingConfigJson, source.OutputProfilesJson, source.DefaultOutputProfileCode, source.BusinessOwner, source.SourceOwner, source.ApprovalStage, source.ReadinessStatus, source.ActiveFlag, source.Notes);
GO

DECLARE @ActualsSystemID INT;

SELECT @ActualsSystemID = IntegrationSystemID
FROM dbo.tblIntegrationSystem
WHERE SystemCode = N'MOCK_FINANCE';

DECLARE @ActualsMappingConfigJson NVARCHAR(MAX) = N'{
  "source_type": "sql_table",
  "source_object": "dbo.tblIntegrationDummyActualsSource",
  "context_filters": {
    "fiscal_year": "FiscalYearID",
    "version": "VersionID"
  },
  "filters": {
    "ActiveFlag": 1
  },
  "field_map": {
    "transactionReference": "TransactionReference",
    "fiscalYear": "FiscalYearID",
    "version": "VersionID",
    "period": "PeriodNo",
    "postingDate": "PostingDate",
    "dataObjectCode": "DataObjectCode",
    "programCode": "ProgramCode",
    "economicCode": "EconomicCode",
    "supplierName": "SupplierName",
    "actualAmount": "ActualAmount",
    "currencyCode": "CurrencyCode"
  },
  "amount_structure": {
    "total_field": "ActualAmount"
  },
  "export_grain": "actual_transaction",
  "notes": "Dummy actuals feed for testing FMIS actual expenditure import contracts against the local Mock FMIS API."
}';

DECLARE @ActualsOutputProfilesJson NVARCHAR(MAX) = N'[
  {
    "code": "mock_fmis_actuals_v1",
    "label": "Mock FMIS Actuals API v1",
    "records_key": "actualLines",
    "field_order": ["transactionReference", "fiscalYear", "version", "period", "postingDate", "dataObjectCode", "programCode", "economicCode", "supplierName", "actualAmount", "currencyCode"]
  }
]';

MERGE dbo.tblIntegrationInterface AS target
USING (
    SELECT
        N'MOCK_ACTUALS_IMPORT' AS InterfaceCode,
        N'Mock FMIS Actuals Import Feed' AS InterfaceName,
        @ActualsSystemID AS IntegrationSystemID,
        N'bidirectional' AS DirectionCode,
        N'Budget Execution' AS ModuleCode,
        N'Actuals' AS EntityCode,
        N'manual' AS TriggerMode,
        N'/api/mock/actuals/import' AS EndpointPath,
        N'POST' AS HttpMethod,
        N'json' AS PayloadFormat,
        CAST(0 AS BIT) AS ContextRequiredFlag,
        CAST(1 AS BIT) AS FiscalYearRequiredFlag,
        CAST(1 AS BIT) AS VersionRequiredFlag,
        CAST(0 AS BIT) AS DataScopeRequiredFlag,
        25 AS BatchSize,
        30 AS TimeoutSeconds,
        @ActualsMappingConfigJson AS MappingConfigJson,
        @ActualsOutputProfilesJson AS OutputProfilesJson,
        N'mock_fmis_actuals_v1' AS DefaultOutputProfileCode,
        N'CBMS Integration Test Team' AS BusinessOwner,
        N'Local Dummy FMIS Actuals Source' AS SourceOwner,
        N'Approved for Testing' AS ApprovalStage,
        N'TEST_READY' AS ReadinessStatus,
        CAST(1 AS BIT) AS ActiveFlag,
        N'Use this interface to test FMIS actuals import payloads without external API credentials. Open Integration Interfaces, click the play button, and dispatch to the mock API.' AS Notes
) AS source
ON target.InterfaceCode = source.InterfaceCode
WHEN MATCHED THEN
    UPDATE SET
        InterfaceName = source.InterfaceName,
        IntegrationSystemID = source.IntegrationSystemID,
        DirectionCode = source.DirectionCode,
        ModuleCode = source.ModuleCode,
        EntityCode = source.EntityCode,
        TriggerMode = source.TriggerMode,
        EndpointPath = source.EndpointPath,
        HttpMethod = source.HttpMethod,
        PayloadFormat = source.PayloadFormat,
        ContextRequiredFlag = source.ContextRequiredFlag,
        FiscalYearRequiredFlag = source.FiscalYearRequiredFlag,
        VersionRequiredFlag = source.VersionRequiredFlag,
        DataScopeRequiredFlag = source.DataScopeRequiredFlag,
        BatchSize = source.BatchSize,
        TimeoutSeconds = source.TimeoutSeconds,
        MappingConfigJson = source.MappingConfigJson,
        OutputProfilesJson = source.OutputProfilesJson,
        DefaultOutputProfileCode = source.DefaultOutputProfileCode,
        BusinessOwner = source.BusinessOwner,
        SourceOwner = source.SourceOwner,
        ApprovalStage = source.ApprovalStage,
        ReadinessStatus = source.ReadinessStatus,
        ActiveFlag = source.ActiveFlag,
        Notes = source.Notes,
        UpdatedDate = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT
        (InterfaceCode, InterfaceName, IntegrationSystemID, DirectionCode, ModuleCode, EntityCode, TriggerMode, EndpointPath, HttpMethod, PayloadFormat, ContextRequiredFlag, FiscalYearRequiredFlag, VersionRequiredFlag, DataScopeRequiredFlag, BatchSize, TimeoutSeconds, MappingConfigJson, OutputProfilesJson, DefaultOutputProfileCode, BusinessOwner, SourceOwner, ApprovalStage, ReadinessStatus, ActiveFlag, Notes)
    VALUES
        (source.InterfaceCode, source.InterfaceName, source.IntegrationSystemID, source.DirectionCode, source.ModuleCode, source.EntityCode, source.TriggerMode, source.EndpointPath, source.HttpMethod, source.PayloadFormat, source.ContextRequiredFlag, source.FiscalYearRequiredFlag, source.VersionRequiredFlag, source.DataScopeRequiredFlag, source.BatchSize, source.TimeoutSeconds, source.MappingConfigJson, source.OutputProfilesJson, source.DefaultOutputProfileCode, source.BusinessOwner, source.SourceOwner, source.ApprovalStage, source.ReadinessStatus, source.ActiveFlag, source.Notes);
GO

SELECT
    s.SystemCode,
    s.SystemName,
    i.InterfaceCode,
    i.InterfaceName,
    i.ReadinessStatus,
    i.EndpointPath
FROM dbo.tblIntegrationInterface i
INNER JOIN dbo.tblIntegrationSystem s
    ON s.IntegrationSystemID = i.IntegrationSystemID
WHERE i.InterfaceCode IN (N'MOCK_BUDGET_EXPORT', N'MOCK_ACTUALS_IMPORT')
ORDER BY i.InterfaceCode;
GO
