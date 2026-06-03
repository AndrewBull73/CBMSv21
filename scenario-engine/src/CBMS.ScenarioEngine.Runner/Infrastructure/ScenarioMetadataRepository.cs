using CBMS.ScenarioEngine.Core.Models;
using Microsoft.Data.SqlClient;
using System.Data;
using System.Globalization;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal sealed class ScenarioMetadataRepository
{
    private static readonly string[] RequiredTables =
    [
        "tblCalcModel",
        "tblCalcScenario",
        "tblCalcPeriod",
        "tblCalcCostObject",
        "tblCalcNode",
        "tblCalcFormula",
        "tblCalcDependency",
        "tblScenarioNodeValue",
        "tblCalcRun",
        "tblCalcRunResult",
        "tblCalcRunError",
        "tblCalcPublishEvent",
        "tblCalcPublishedResult",
        "tblCalcTransactionBridge",
        "tblCalcTransactionNodeMap",
    ];

    private readonly string _connectionString;

    public ScenarioMetadataRepository(string connectionString)
    {
        _connectionString = connectionString;
    }

    public async Task<SchemaProbeResult> ProbeSchemaAsync(CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = 'dbo'
              AND TABLE_NAME IN (
                  'tblCalcModel',
                  'tblCalcScenario',
                  'tblCalcPeriod',
                  'tblCalcCostObject',
                  'tblCalcNode',
                  'tblCalcFormula',
                  'tblCalcDependency',
                  'tblScenarioNodeValue',
                  'tblCalcRun',
                  'tblCalcRunResult',
                  'tblCalcRunError',
                  'tblCalcPublishEvent',
                  'tblCalcPublishedResult',
                  'tblCalcTransactionBridge',
                  'tblCalcTransactionNodeMap'
              )
            ORDER BY TABLE_NAME;
            """;

        var present = new List<string>();

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var command = new SqlCommand(sql, connection);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            present.Add(reader.GetString(0));
        }

        var missing = RequiredTables.Except(present, StringComparer.OrdinalIgnoreCase).ToArray();
        return new SchemaProbeResult(present, missing);
    }

    public async Task<IReadOnlyList<CalcModel>> GetModelsAsync(CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT CalcModelID, ModelCode, ModelName, ModelVersion, StatusCode, ActiveFlag
            FROM dbo.tblCalcModel
            ORDER BY ModelCode, ModelVersion;
            """;

        var rows = new List<CalcModel>();

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var command = new SqlCommand(sql, connection);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcModel(
                reader.GetInt32(0),
                reader.GetString(1),
                reader.GetString(2),
                reader.GetInt32(3),
                reader.GetString(4),
                reader.GetBoolean(5)));
        }

        return rows;
    }

    public async Task<CalcMetadataBundle?> LoadBundleAsync(string modelCode, CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        var model = await LoadModelByCodeAsync(connection, modelCode, cancellationToken);
        return model is null
            ? null
            : await LoadBundleForModelAsync(connection, model, cancellationToken);
    }

    public async Task<ScenarioSelection?> LoadScenarioSelectionAsync(
        string modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken)
    {
        var bundle = await LoadBundleAsync(modelCode, cancellationToken);
        if (bundle is null)
        {
            return null;
        }

        var scenario = ResolveScenario(bundle, scenarioCode);
        if (scenario is null)
        {
            return null;
        }

        var lineage = BuildScenarioLineage(bundle.Scenarios, scenario);
        var inputs = await LoadEffectiveScenarioNodeValuesAsync(lineage, bundle.Model.CalcModelId, cancellationToken);
        return new ScenarioSelection(bundle, scenario, inputs);
    }

    public async Task<TransactionExecutionSelection?> LoadTransactionExecutionSelectionAsync(
        int transactionId,
        string? modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var transaction = await LoadTransactionInputAsync(connection, transactionId, cancellationToken);
        if (transaction is null)
        {
            return null;
        }

        var bridges = await LoadTransactionBridgesAsync(connection, cancellationToken);
        var bridge = TransactionScenarioBridgeAdapter.ResolveBridge(bridges, transaction, modelCode);
        if (bridge is null)
        {
            return null;
        }
        return await BuildTransactionExecutionSelectionAsync(
            connection,
            transaction,
            bridge,
            scenarioCode,
            cancellationToken,
            previousReport: null);
    }

    public async Task<TransactionExecutionSelection?> LoadTransactionExecutionSelectionAsync(
        TransactionInputRecord transaction,
        string? modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken,
        ExecutionReport? previousReport = null,
        IReadOnlyDictionary<string, ExecutionReport>? priorReportsByName = null)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var bridges = await LoadTransactionBridgesAsync(connection, cancellationToken);
        var bridge = TransactionScenarioBridgeAdapter.ResolveBridge(bridges, transaction, modelCode);
        if (bridge is null)
        {
            return null;
        }

        return await BuildTransactionExecutionSelectionAsync(
            connection,
            transaction,
            bridge,
            scenarioCode,
            cancellationToken,
            previousReport,
            priorReportsByName);
    }

    public async Task<IReadOnlyList<LegacyCalculationDefinition>> LoadLegacyCalculationChainAsync(
        int fiscalYearId,
        int startingCalculationId,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var rows = await LoadLegacyCalculationsAsync(connection, fiscalYearId, cancellationToken);
        var byId = rows.ToDictionary(x => x.CalculationId);
        var chain = new List<LegacyCalculationDefinition>();
        var visited = new HashSet<int>();
        var currentId = startingCalculationId;

        while (currentId > 0)
        {
            if (!visited.Add(currentId))
            {
                throw new InvalidOperationException($"Legacy calculation chain cycle detected at CalculationID {currentId}.");
            }

            if (!byId.TryGetValue(currentId, out var current))
            {
                throw new InvalidOperationException(
                    $"Legacy calculation chain references CalculationID {currentId}, but no row was found for FiscalYearID {fiscalYearId}.");
            }

            chain.Add(current);
            currentId = current.ChildCalculationId ?? 0;
        }

        return chain;
    }

    public async Task<IReadOnlyList<int>> GetActiveTransactionIdsByCalculationAsync(
        int calculationId,
        int? limit,
        int? fiscalYearId,
        int? versionId,
        IReadOnlyList<string>? dataObjectCodes,
        IReadOnlyList<string>? transactionTypeCodes,
        CancellationToken cancellationToken)
    {
        var maxRows = limit.GetValueOrDefault();
        var topClause = maxRows > 0
            ? $"TOP ({maxRows}) "
            : string.Empty;
        var sql = $"""
            SELECT {topClause}TransactionID
            FROM dbo.tblTransactionInput
            WHERE CalculationID = @calculationId
              AND ISNULL(DeletedFlag, 'N') = 'N'
              AND ISNULL(RecordTypeCode, '') <> 'C'
            """;
        var filters = new List<string>();

        var rows = new List<int>();

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calculationId", calculationId);
        AppendTransactionBatchFilters(
            command,
            filters,
            fiscalYearId,
            versionId,
            dataObjectCodes,
            transactionTypeCodes);
        command.CommandText += filters.Count > 0
            ? Environment.NewLine + "  AND " + string.Join(Environment.NewLine + "  AND ", filters)
            : string.Empty;
        command.CommandText += Environment.NewLine + "ORDER BY TransactionID;";
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(reader.GetInt32(0));
        }

        return rows;
    }

    public async Task<TransactionBatchBenchmarkPreparation> PrepareTransactionBatchBenchmarkAsync(
        int calculationId,
        int? limit,
        int? fiscalYearId,
        int? versionId,
        IReadOnlyList<string>? dataObjectCodes,
        IReadOnlyList<string>? transactionTypeCodes,
        string? modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var transactions = await LoadTransactionInputsByCalculationAsync(
            connection,
            calculationId,
            limit,
            fiscalYearId,
            versionId,
            dataObjectCodes,
            transactionTypeCodes,
            cancellationToken);
        if (transactions.Count == 0)
        {
            return new TransactionBatchBenchmarkPreparation(
                calculationId,
                [],
                null,
                null,
                [],
                [],
                []);
        }

        var bridges = await LoadTransactionBridgesAsync(connection, cancellationToken);
        var resolved = transactions
            .Select(transaction => new
            {
                Transaction = transaction,
                Bridge = TransactionScenarioBridgeAdapter.ResolveBridge(bridges, transaction, modelCode),
            })
            .ToArray();

        var unresolvedTransactionIds = resolved
            .Where(x => x.Bridge is null)
            .Select(x => x.Transaction.TransactionId)
            .ToArray();
        var bridged = resolved
            .Where(x => x.Bridge is not null)
            .Select(x => new TransactionBridgeResolution(x.Transaction, x.Bridge!))
            .ToArray();

        var distinctBridgeIds = bridged
            .Select(x => x.Bridge.CalcTransactionBridgeId)
            .Distinct()
            .OrderBy(x => x)
            .ToArray();

        if (distinctBridgeIds.Length != 1)
        {
            return new TransactionBatchBenchmarkPreparation(
                calculationId,
                transactions,
                null,
                null,
                [],
                unresolvedTransactionIds,
                distinctBridgeIds);
        }

        var bridge = bridged[0].Bridge;
        var model = await LoadModelByIdAsync(connection, bridge.CalcModelId, cancellationToken)
            ?? throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} references missing model ID {bridge.CalcModelId}.");
        var bundle = await LoadBundleForModelAsync(connection, model, cancellationToken);
        var scenario = !string.IsNullOrWhiteSpace(scenarioCode)
            ? ResolveScenario(bundle, scenarioCode)
            : bundle.Scenarios.FirstOrDefault(x => x.ScenarioId == bridge.ScenarioId);

        if (scenario is null)
        {
            throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} could not resolve scenario '{scenarioCode ?? bridge.ScenarioCode}'.");
        }

        var lineage = BuildScenarioLineage(bundle.Scenarios, scenario);
        var inputs = await LoadEffectiveScenarioNodeValuesAsync(lineage, bundle.Model.CalcModelId, cancellationToken);
        var selection = new ScenarioSelection(bundle, scenario, inputs);
        var nodeMaps = await LoadTransactionNodeMapsAsync(connection, bridge.CalcTransactionBridgeId, cancellationToken);
        var rateLookupCache = await LoadRateLookupCacheAsync(connection, transactions, nodeMaps, scenario.ScenarioId, cancellationToken);
        var unsupportedMapTypes = nodeMaps
            .Where(x => x.ActiveFlag)
            .Select(x => x.SourceTypeCode.ToUpperInvariant())
            .Distinct()
            .Where(x => x is "PREVIOUS_RESULT" or "PREVIOUS_RESULT_PATTERN" or "CHAIN_RESULT" or "CHAIN_RESULT_PATTERN")
            .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        return new TransactionBatchBenchmarkPreparation(
            calculationId,
            bridged.Select(x => x.Transaction).ToArray(),
            bridge,
            selection,
            nodeMaps,
            unresolvedTransactionIds,
            distinctBridgeIds,
            unsupportedMapTypes,
            rateLookupCache);
    }

    public async Task<TransactionBatchBenchmarkPreparation> PrepareTransactionBatchStageAsync(
        IReadOnlyList<TransactionInputRecord> transactions,
        string? modelCode,
        string? scenarioCode,
        bool allowChainResultSources,
        CancellationToken cancellationToken)
    {
        if (transactions.Count == 0)
        {
            return new TransactionBatchBenchmarkPreparation(
                0,
                [],
                null,
                null,
                [],
                [],
                []);
        }

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var bridges = await LoadTransactionBridgesAsync(connection, cancellationToken);
        var resolved = transactions
            .Select(transaction => new
            {
                Transaction = transaction,
                Bridge = TransactionScenarioBridgeAdapter.ResolveBridge(bridges, transaction, modelCode),
            })
            .ToArray();

        var unresolvedTransactionIds = resolved
            .Where(x => x.Bridge is null)
            .Select(x => x.Transaction.TransactionId)
            .ToArray();
        var bridged = resolved
            .Where(x => x.Bridge is not null)
            .Select(x => new TransactionBridgeResolution(x.Transaction, x.Bridge!))
            .ToArray();

        var distinctBridgeIds = bridged
            .Select(x => x.Bridge.CalcTransactionBridgeId)
            .Distinct()
            .OrderBy(x => x)
            .ToArray();

        if (distinctBridgeIds.Length != 1)
        {
            return new TransactionBatchBenchmarkPreparation(
                transactions[0].CalculationId ?? 0,
                transactions,
                null,
                null,
                [],
                unresolvedTransactionIds,
                distinctBridgeIds);
        }

        var bridge = bridged[0].Bridge;
        var model = await LoadModelByIdAsync(connection, bridge.CalcModelId, cancellationToken)
            ?? throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} references missing model ID {bridge.CalcModelId}.");
        var bundle = await LoadBundleForModelAsync(connection, model, cancellationToken);
        var scenario = !string.IsNullOrWhiteSpace(scenarioCode)
            ? ResolveScenario(bundle, scenarioCode)
            : bundle.Scenarios.FirstOrDefault(x => x.ScenarioId == bridge.ScenarioId);

        if (scenario is null)
        {
            throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} could not resolve scenario '{scenarioCode ?? bridge.ScenarioCode}'.");
        }

        var lineage = BuildScenarioLineage(bundle.Scenarios, scenario);
        var inputs = await LoadEffectiveScenarioNodeValuesAsync(lineage, bundle.Model.CalcModelId, cancellationToken);
        var selection = new ScenarioSelection(bundle, scenario, inputs);
        var nodeMaps = await LoadTransactionNodeMapsAsync(connection, bridge.CalcTransactionBridgeId, cancellationToken);
        var rateLookupCache = await LoadRateLookupCacheAsync(connection, transactions, nodeMaps, scenario.ScenarioId, cancellationToken);

        var unsupportedMapTypes = nodeMaps
            .Where(x => x.ActiveFlag)
            .Select(x => x.SourceTypeCode.ToUpperInvariant())
            .Distinct()
            .Where(x => x is "PREVIOUS_RESULT" or "PREVIOUS_RESULT_PATTERN" || (!allowChainResultSources && (x is "CHAIN_RESULT" or "CHAIN_RESULT_PATTERN")))
            .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        return new TransactionBatchBenchmarkPreparation(
            transactions[0].CalculationId ?? 0,
            transactions,
            bridge,
            selection,
            nodeMaps,
            unresolvedTransactionIds,
            distinctBridgeIds,
            unsupportedMapTypes,
            rateLookupCache);
    }

    public async Task<TransactionInputRecord> UpsertLegacyChildTransactionAsync(
        TransactionInputRecord parentTransaction,
        LegacyCalculationDefinition childCalculation,
        ExecutionReport parentReport,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var materialized = LegacyCalculationChainOrchestrator.MaterializeChildTransaction(
            parentTransaction,
            childCalculation,
            parentReport);
        var headRecordId = ResolveHeadRecordId(parentTransaction);
        var existingChild = await LoadExistingChildTransactionAsync(
            connection,
            headRecordId,
            childCalculation.CalculationId,
            cancellationToken);

        var values = new Dictionary<string, object?>(materialized.Values, StringComparer.OrdinalIgnoreCase)
        {
            ["HeadRecordID"] = headRecordId,
        };

        PreserveChildCeilingState(values, existingChild?.Values);

        if (existingChild is not null)
        {
            await UpdateTransactionInputAsync(connection, existingChild.TransactionId, values, cancellationToken);
            return await LoadTransactionInputAsync(connection, existingChild.TransactionId, cancellationToken)
                ?? throw new InvalidOperationException(
                    $"Child transaction {existingChild.TransactionId} could not be reloaded after update.");
        }

        var newTransactionId = await InsertTransactionInputAsync(connection, values, cancellationToken);
        return await LoadTransactionInputAsync(connection, newTransactionId, cancellationToken)
            ?? throw new InvalidOperationException(
                $"Child transaction {newTransactionId} could not be loaded after insert.");
    }

    public async Task PersistLegacyTransactionResultAsync(
        TransactionInputRecord transaction,
        ScenarioSelection selection,
        ExecutionReport report,
        Guid runId,
        long calcRunId,
        CancellationToken cancellationToken)
    {
        var projection = LegacyTransactionResultProjector.Project(report);
        var calculatedAt = DateTime.UtcNow;
        var status = report.Issues.Count == 0 ? "Success" : "SuccessWithWarnings";
        var errorMessage = report.Issues.Count == 0
            ? null
            : string.Join(" | ", report.Issues.Take(5));
        var formulaVersion = 1;
        var scopeVersion = selection.Bundle.Model.ModelVersion;
        var engineVersion = $"scenario-engine-csharp;CalcRunID={calcRunId}";

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transactionScope = (SqlTransaction)dbTransaction;

        try
        {
            await DeleteLegacyTransactionResultsAsync(connection, transactionScope, transaction.TransactionId, cancellationToken);
            var transactionResultId = await InsertLegacyTransactionResultAsync(
                connection,
                transactionScope,
                transaction.TransactionId,
                projection,
                runId,
                selection.Bundle.Model.ModelCode,
                scopeVersion,
                engineVersion,
                status,
                errorMessage,
                formulaVersion,
                calculatedAt,
                cancellationToken);

            await InsertLegacyTransactionResultFlatAsync(
                connection,
                transactionScope,
                transaction,
                transactionResultId,
                projection,
                calculatedAt,
                cancellationToken);

            await InsertLegacyTransactionResultPeriodsAsync(
                connection,
                transactionScope,
                transaction.TransactionId,
                transactionResultId,
                projection,
                calculatedAt,
                cancellationToken);

            await transactionScope.CommitAsync(cancellationToken);
        }
        catch
        {
            await transactionScope.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task BulkPersistLegacyTransactionBatchAsync(
        IReadOnlyList<LegacyBatchTransactionResult> rows,
        Guid runId,
        string formulaSetCode,
        int scopeVersion,
        string engineVersion,
        CancellationToken cancellationToken)
    {
        if (rows.Count == 0)
        {
            return;
        }

        var calculatedAtUtc = DateTime.UtcNow;

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transaction = (SqlTransaction)dbTransaction;

        try
        {
            await CreateLegacyBatchTempTablesAsync(connection, transaction, cancellationToken);

            var targetTable = BuildTargetTransactionsTable(rows);
            var resultStageTable = BuildLegacyResultStageTable(rows, runId, formulaSetCode, scopeVersion, engineVersion, calculatedAtUtc);
            var flatStageTable = BuildLegacyFlatStageTable(rows, calculatedAtUtc);
            var periodStageTable = BuildLegacyPeriodStageTable(rows, calculatedAtUtc);

            await BulkCopyAsync(connection, transaction, "#TargetTransactions", targetTable, cancellationToken);
            await DeleteLegacyBatchTransactionResultsAsync(connection, transaction, cancellationToken);
            await BulkCopyAsync(connection, transaction, "#ResultStage", resultStageTable, cancellationToken);
            await InsertLegacyBatchHeadersAsync(connection, transaction, cancellationToken);
            await BulkCopyAsync(connection, transaction, "#FlatStage", flatStageTable, cancellationToken);
            await InsertLegacyBatchFlatsAsync(connection, transaction, cancellationToken);
            await BulkCopyAsync(connection, transaction, "#PeriodStage", periodStageTable, cancellationToken);
            await InsertLegacyBatchPeriodsAsync(connection, transaction, cancellationToken);

            await transaction.CommitAsync(cancellationToken);
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task<CeilingValidationBatchSummary> ValidateLegacyTransactionCeilingsAsync(
        IReadOnlyList<int> transactionIds,
        bool headRecordMode,
        bool persistStatus,
        CancellationToken cancellationToken)
    {
        var normalizedTransactionIds = transactionIds
            .Where(x => x > 0)
            .Distinct()
            .ToArray();

        if (normalizedTransactionIds.Length == 0)
        {
            return new CeilingValidationBatchSummary(0, 0, 0, Array.Empty<string>());
        }

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);

        var contexts = await LoadCeilingCheckContextsAsync(connection, normalizedTransactionIds, cancellationToken);
        var failureMessages = new List<string>();
        var checkedCount = 0;
        var successCount = 0;
        var failedCount = 0;

        foreach (var transactionId in normalizedTransactionIds)
        {
            if (!contexts.TryGetValue(transactionId, out var context))
            {
                failedCount++;
                failureMessages.Add($"Transaction {transactionId}: ceiling validation context was not found.");
                continue;
            }

            try
            {
                var checkResult = await ExecuteCeilingCheckAsync(
                    connection,
                    context,
                    headRecordMode ? "HEAD_RECORD" : "TRANSACTION",
                    cancellationToken);

                checkedCount++;
                if (checkResult.IsSuccess)
                {
                    successCount++;
                }
                else
                {
                    failedCount++;
                    var detail = string.IsNullOrWhiteSpace(checkResult.ErrorMessage)
                        ? checkResult.StatusCode
                        : $"{checkResult.StatusCode} ({checkResult.ErrorMessage})";
                    failureMessages.Add($"Transaction {transactionId}: {detail}");
                }

                if (persistStatus)
                {
                    if (headRecordMode)
                    {
                        await PersistHeadRecordCeilingStatusAsync(connection, transactionId, checkResult, cancellationToken);
                    }
                    else
                    {
                        await PersistTransactionCeilingStatusAsync(connection, transactionId, checkResult, cancellationToken);
                    }
                }
            }
            catch (Exception ex)
            {
                failedCount++;
                failureMessages.Add($"Transaction {transactionId}: {ex.Message}");
            }
        }

        return new CeilingValidationBatchSummary(
            checkedCount,
            successCount,
            failedCount,
            failureMessages.Take(10).ToArray());
    }

    public async Task<IReadOnlyList<TransactionInputRecord>> BulkUpsertLegacyChildTransactionsAsync(
        IReadOnlyList<TransactionInputRecord> rows,
        CancellationToken cancellationToken)
    {
        if (rows.Count == 0)
        {
            return Array.Empty<TransactionInputRecord>();
        }

        var writableColumns = rows
            .SelectMany(x => FilterWritableTransactionValues(x.Values))
            .Select(x => x.Key)
            .Where(x => !string.Equals(x, "TransactionID", StringComparison.OrdinalIgnoreCase))
            .Where(x => !string.Equals(x, "CeilingAppliedTotal", StringComparison.OrdinalIgnoreCase))
            .Where(x => !x.StartsWith("CeilingAppliedBP", StringComparison.OrdinalIgnoreCase))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transaction = (SqlTransaction)dbTransaction;

        try
        {
            await CreateChildTransactionStageTablesAsync(connection, transaction, cancellationToken);
            var stageTable = BuildChildTransactionStageTable(rows, writableColumns);
            await BulkCopyAsync(connection, transaction, "#ChildStage", stageTable, cancellationToken);
            var transactionIdsByBatchOrdinal = await MergeChildTransactionsAsync(connection, transaction, writableColumns, cancellationToken);
            await transaction.CommitAsync(cancellationToken);

            var persisted = new List<TransactionInputRecord>(rows.Count);
            for (var index = 0; index < rows.Count; index++)
            {
                var source = rows[index];
                persisted.Add(source with
                {
                    TransactionId = transactionIdsByBatchOrdinal[index + 1],
                });
            }

            return persisted;
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task<PersistedRunSummary> PersistExecutionAsync(
        ScenarioSelection selection,
        ExecutionReport report,
        CancellationToken cancellationToken,
        ExecutionPersistenceOptions? options = null)
    {
        options ??= ExecutionPersistenceOptions.ForModel(selection);
        var startedAt = DateTime.UtcNow;
        var completedAt = DateTime.UtcNow;
        var statusCode = report.Issues.Count == 0 ? "COMPLETED" : "COMPLETED_WARN";
        var engineVersion = typeof(ScenarioMetadataRepository).Assembly.GetName().Version?.ToString() ?? "dev";

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transaction = (SqlTransaction)dbTransaction;

        try
        {
            var runId = await InsertRunAsync(
                connection,
                transaction,
                selection,
                startedAt,
                engineVersion,
                options,
                cancellationToken);

            await BulkInsertRunResultsAsync(
                connection,
                transaction,
                runId,
                selection,
                report,
                completedAt,
                cancellationToken);

            if (report.Issues.Count > 0)
            {
                await BulkInsertRunErrorsAsync(
                    connection,
                    transaction,
                    runId,
                    selection,
                    report,
                    completedAt,
                    cancellationToken);
            }

            await CompleteRunAsync(
                connection,
                transaction,
                runId,
                statusCode,
                completedAt,
                report.Results.Count,
                report.Issues.Count,
                cancellationToken);

            await transaction.CommitAsync(cancellationToken);
            return new PersistedRunSummary(runId, statusCode, report.Results.Count, report.Issues.Count);
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task<PublishedRunSummary> PublishRunAsync(
        long calcRunId,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transaction = (SqlTransaction)dbTransaction;

        try
        {
            var candidate = await LoadPublishableRunAsync(connection, transaction, calcRunId, cancellationToken);
            EnsurePublishable(candidate);
            var publishable = candidate!;

            var publishEventId = await InsertPublishEventAsync(connection, transaction, publishable, cancellationToken);
            await DeletePublishedResultsAsync(connection, transaction, publishable.CalcModelId, publishable.ScenarioId, cancellationToken);
            var publishedRowCount = await InsertPublishedResultsAsync(connection, transaction, publishEventId, publishable, cancellationToken);
            await CompletePublishEventAsync(connection, transaction, publishEventId, publishedRowCount, cancellationToken);

            await transaction.CommitAsync(cancellationToken);
            return new PublishedRunSummary(
                publishEventId,
                publishable.CalcRunId,
                publishable.CalcModelId,
                publishable.ModelCode,
                publishable.ScenarioId,
                publishable.ScenarioCode,
                publishedRowCount);
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task<PublishedRunSummary> PublishLatestRunAsync(
        string modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken)
    {
        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var dbTransaction = await connection.BeginTransactionAsync(cancellationToken);
        var transaction = (SqlTransaction)dbTransaction;

        try
        {
            var candidate = await LoadLatestPublishableRunAsync(connection, transaction, modelCode, scenarioCode, cancellationToken);
            EnsurePublishable(candidate);
            var publishable = candidate!;

            var publishEventId = await InsertPublishEventAsync(connection, transaction, publishable, cancellationToken);
            await DeletePublishedResultsAsync(connection, transaction, publishable.CalcModelId, publishable.ScenarioId, cancellationToken);
            var publishedRowCount = await InsertPublishedResultsAsync(connection, transaction, publishEventId, publishable, cancellationToken);
            await CompletePublishEventAsync(connection, transaction, publishEventId, publishedRowCount, cancellationToken);

            await transaction.CommitAsync(cancellationToken);
            return new PublishedRunSummary(
                publishEventId,
                publishable.CalcRunId,
                publishable.CalcModelId,
                publishable.ModelCode,
                publishable.ScenarioId,
                publishable.ScenarioCode,
                publishedRowCount);
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    internal static CalcScenario? ResolveScenario(CalcMetadataBundle bundle, string? scenarioCode)
    {
        if (!string.IsNullOrWhiteSpace(scenarioCode))
        {
            return bundle.Scenarios.FirstOrDefault(x =>
                string.Equals(x.ScenarioCode, scenarioCode, StringComparison.OrdinalIgnoreCase));
        }

        return bundle.Scenarios
            .OrderBy(x => x.SortOrder)
            .ThenBy(x => x.ScenarioCode, StringComparer.OrdinalIgnoreCase)
            .FirstOrDefault();
    }

    internal static IReadOnlyList<CalcScenario> BuildScenarioLineage(
        IReadOnlyList<CalcScenario> scenarios,
        CalcScenario scenario)
    {
        var byId = scenarios.ToDictionary(x => x.ScenarioId);
        var lineage = new List<CalcScenario>();
        var visited = new HashSet<int>();
        var current = scenario;

        while (true)
        {
            if (!visited.Add(current.ScenarioId))
            {
                throw new InvalidOperationException(
                    $"Scenario inheritance cycle detected starting at scenario '{scenario.ScenarioCode}'.");
            }

            lineage.Add(current);

            if (!current.ParentScenarioId.HasValue)
            {
                break;
            }

            if (!byId.TryGetValue(current.ParentScenarioId.Value, out var parent))
            {
                throw new InvalidOperationException(
                    $"Scenario '{current.ScenarioCode}' references missing parent scenario ID {current.ParentScenarioId.Value}.");
            }

            current = parent;
        }

        return lineage;
    }

    internal static IReadOnlyList<ScenarioNodeValue> ResolveEffectiveScenarioNodeValues(
        IReadOnlyList<CalcScenario> lineage,
        IReadOnlyList<ScenarioNodeValue> rows)
    {
        if (lineage.Count == 0 || rows.Count == 0)
        {
            return Array.Empty<ScenarioNodeValue>();
        }

        var precedence = lineage
            .Select((scenario, index) => new { scenario.ScenarioId, index })
            .ToDictionary(x => x.ScenarioId, x => x.index);

        return rows
            .Where(x => precedence.ContainsKey(x.ScenarioId))
            .OrderBy(x => precedence[x.ScenarioId])
            .GroupBy(x => (x.CostObjectId, x.PeriodId, x.NodeId))
            .Select(g => g.First())
            .OrderBy(x => x.CostObjectId)
            .ThenBy(x => x.PeriodId)
            .ThenBy(x => x.NodeId)
            .ToArray();
    }

    private static async Task<CalcMetadataBundle> LoadBundleForModelAsync(
        SqlConnection connection,
        CalcModel model,
        CancellationToken cancellationToken)
    {
        var scenarios = await LoadScenariosAsync(connection, model.CalcModelId, cancellationToken);
        var periods = await LoadPeriodsAsync(connection, model.CalcModelId, cancellationToken);
        var costObjects = await LoadCostObjectsAsync(connection, model.CalcModelId, cancellationToken);
        var nodes = await LoadNodesAsync(connection, model.CalcModelId, cancellationToken);
        var formulas = await LoadFormulasAsync(connection, model.CalcModelId, cancellationToken);
        var dependencies = await LoadDependenciesAsync(connection, model.CalcModelId, cancellationToken);

        return new CalcMetadataBundle(model, scenarios, periods, costObjects, nodes, formulas, dependencies);
    }

    private static async Task<CalcModel?> LoadModelByCodeAsync(
        SqlConnection connection,
        string modelCode,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT TOP (1) CalcModelID, ModelCode, ModelName, ModelVersion, StatusCode, ActiveFlag
            FROM dbo.tblCalcModel
            WHERE ModelCode = @modelCode
            ORDER BY ModelVersion DESC;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@modelCode", modelCode);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        return new CalcModel(
            reader.GetInt32(0),
            reader.GetString(1),
            reader.GetString(2),
            reader.GetInt32(3),
            reader.GetString(4),
            reader.GetBoolean(5));
    }

    private static async Task<CalcModel?> LoadModelByIdAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT CalcModelID, ModelCode, ModelName, ModelVersion, StatusCode, ActiveFlag
            FROM dbo.tblCalcModel
            WHERE CalcModelID = @calcModelId;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        return new CalcModel(
            reader.GetInt32(0),
            reader.GetString(1),
            reader.GetString(2),
            reader.GetInt32(3),
            reader.GetString(4),
            reader.GetBoolean(5));
    }

    private static async Task<IReadOnlyList<CalcScenario>> LoadScenariosAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT ScenarioID, CalcModelID, ParentScenarioID, ScenarioCode, ScenarioName,
                   ScenarioTypeCode, ScenarioStatusCode, SortOrder, ActiveFlag
            FROM dbo.tblCalcScenario
            WHERE CalcModelID = @calcModelId
            ORDER BY SortOrder, ScenarioCode;
            """;

        var rows = new List<CalcScenario>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcScenario(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.IsDBNull(2) ? null : reader.GetInt32(2),
                reader.GetString(3),
                reader.GetString(4),
                reader.GetString(5),
                reader.GetString(6),
                reader.GetInt32(7),
                reader.GetBoolean(8)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcPeriod>> LoadPeriodsAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT PeriodID, CalcModelID, PeriodCode, FiscalYearID, PeriodNo,
                   PeriodTypeCode, SequenceNo, ActiveFlag
            FROM dbo.tblCalcPeriod
            WHERE CalcModelID = @calcModelId
            ORDER BY SequenceNo, PeriodCode;
            """;

        var rows = new List<CalcPeriod>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcPeriod(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetString(2),
                reader.IsDBNull(3) ? null : reader.GetInt32(3),
                reader.IsDBNull(4) ? null : reader.GetInt32(4),
                reader.GetString(5),
                reader.GetInt32(6),
                reader.GetBoolean(7)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcCostObject>> LoadCostObjectsAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT CostObjectID, CalcModelID, ParentCostObjectID, CostObjectCode,
                   CostObjectName, CostObjectTypeCode, ActiveFlag
            FROM dbo.tblCalcCostObject
            WHERE CalcModelID = @calcModelId
            ORDER BY CostObjectCode;
            """;

        var rows = new List<CalcCostObject>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcCostObject(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.IsDBNull(2) ? null : reader.GetInt32(2),
                reader.GetString(3),
                reader.GetString(4),
                reader.GetString(5),
                reader.GetBoolean(6)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcNode>> LoadNodesAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT NodeID, CalcModelID, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode,
                   DataTypeCode, UnitOfMeasureCode, DecimalScale, NodeOrder, OutputFlag, ActiveFlag
            FROM dbo.tblCalcNode
            WHERE CalcModelID = @calcModelId
            ORDER BY NodeOrder, NodeCode;
            """;

        var rows = new List<CalcNode>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcNode(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetString(2),
                reader.GetString(3),
                reader.GetString(4),
                reader.GetString(5),
                reader.GetString(6),
                reader.IsDBNull(7) ? null : reader.GetString(7),
                reader.GetByte(8),
                reader.GetInt32(9),
                reader.GetBoolean(10),
                reader.GetBoolean(11)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcFormula>> LoadFormulasAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT f.CalcFormulaID, f.NodeID, f.ExpressionText, f.ExpressionLanguageCode,
                   f.ParserVersion, f.ActiveFlag
            FROM dbo.tblCalcFormula f
            INNER JOIN dbo.tblCalcNode n ON n.NodeID = f.NodeID
            WHERE n.CalcModelID = @calcModelId
            ORDER BY f.NodeID;
            """;

        var rows = new List<CalcFormula>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcFormula(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetString(2),
                reader.GetString(3),
                reader.GetString(4),
                reader.GetBoolean(5)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcDependency>> LoadDependenciesAsync(
        SqlConnection connection,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT d.CalcDependencyID, d.NodeID, d.DependsOnNodeID, d.DependencyTypeCode,
                   d.OffsetPeriods, d.RequiredFlag
            FROM dbo.tblCalcDependency d
            INNER JOIN dbo.tblCalcNode n ON n.NodeID = d.NodeID
            WHERE n.CalcModelID = @calcModelId
            ORDER BY d.NodeID, d.SortOrder, d.DependsOnNodeID;
            """;

        var rows = new List<CalcDependency>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcDependency(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetInt32(2),
                reader.GetString(3),
                reader.GetInt32(4),
                reader.GetBoolean(5)));
        }

        return rows;
    }

    private async Task<IReadOnlyList<ScenarioNodeValue>> LoadEffectiveScenarioNodeValuesAsync(
        IReadOnlyList<CalcScenario> lineage,
        int calcModelId,
        CancellationToken cancellationToken)
    {
        if (lineage.Count == 0)
        {
            return Array.Empty<ScenarioNodeValue>();
        }

        var scenarioParameters = lineage
            .Select((scenario, index) => new { scenario.ScenarioId, Name = $"@scenarioId{index}" })
            .ToArray();

        var sql = $"""
            SELECT v.ScenarioID, v.CostObjectID, v.PeriodID, v.NodeID,
                   v.ValueDecimal, v.ValueSourceCode, v.OverriddenFlag
            FROM dbo.tblScenarioNodeValue v
            INNER JOIN dbo.tblCalcNode n ON n.NodeID = v.NodeID
            WHERE v.ScenarioID IN ({string.Join(", ", scenarioParameters.Select(x => x.Name))})
              AND n.CalcModelID = @calcModelId
            ORDER BY v.CostObjectID, v.PeriodID, v.NodeID;
            """;

        var rows = new List<ScenarioNodeValue>();

        await using var connection = new SqlConnection(_connectionString);
        await connection.OpenAsync(cancellationToken);
        await using var command = new SqlCommand(sql, connection);
        foreach (var parameter in scenarioParameters)
        {
            command.Parameters.AddWithValue(parameter.Name, parameter.ScenarioId);
        }
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new ScenarioNodeValue(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetInt32(2),
                reader.GetInt32(3),
                reader.IsDBNull(4) ? null : reader.GetDecimal(4),
                reader.GetString(5),
                reader.GetBoolean(6)));
        }

        return ResolveEffectiveScenarioNodeValues(lineage, rows);
    }

    private static async Task<TransactionInputRecord?> LoadTransactionInputAsync(
        SqlConnection connection,
        int transactionId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT TOP (1) *
            FROM dbo.tblTransactionInput
            WHERE TransactionID = @transactionId;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@transactionId", transactionId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        var values = new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase);
        for (var ordinal = 0; ordinal < reader.FieldCount; ordinal++)
        {
            values[reader.GetName(ordinal)] = reader.IsDBNull(ordinal) ? null : reader.GetValue(ordinal);
        }

        return new TransactionInputRecord(
            transactionId,
            values.TryGetValue("CalculationID", out var calculationId) && calculationId is not null && calculationId is not DBNull
                ? Convert.ToInt32(calculationId)
                : null,
            values.TryGetValue("FiscalYearID", out var fiscalYearId) && fiscalYearId is not null && fiscalYearId is not DBNull
                ? Convert.ToInt32(fiscalYearId)
                : null,
            values.TryGetValue("VersionID", out var versionId) && versionId is not null && versionId is not DBNull
                ? Convert.ToInt32(versionId)
                : null,
            values.TryGetValue("TransactionTypeCode", out var transactionTypeCode) ? transactionTypeCode?.ToString() : null,
            values.TryGetValue("UOMCodeInpC", out var uomCodeInpC) ? uomCodeInpC?.ToString() : null,
            values);
    }

    private static async Task<IReadOnlyList<TransactionInputRecord>> LoadTransactionInputsByCalculationAsync(
        SqlConnection connection,
        int calculationId,
        int? limit,
        int? fiscalYearId,
        int? versionId,
        IReadOnlyList<string>? dataObjectCodes,
        IReadOnlyList<string>? transactionTypeCodes,
        CancellationToken cancellationToken)
    {
        var maxRows = limit.GetValueOrDefault();
        var topClause = maxRows > 0
            ? $"TOP ({maxRows}) "
            : string.Empty;
        var sql = $"""
            SELECT {topClause}*
            FROM dbo.tblTransactionInput
            WHERE CalculationID = @calculationId
              AND ISNULL(DeletedFlag, 'N') = 'N'
              AND ISNULL(RecordTypeCode, '') <> 'C'
            """;

        var rows = new List<TransactionInputRecord>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calculationId", calculationId);
        var filters = new List<string>();
        AppendTransactionBatchFilters(
            command,
            filters,
            fiscalYearId,
            versionId,
            dataObjectCodes,
            transactionTypeCodes);
        command.CommandText += filters.Count > 0
            ? Environment.NewLine + "  AND " + string.Join(Environment.NewLine + "  AND ", filters)
            : string.Empty;
        command.CommandText += Environment.NewLine + "ORDER BY TransactionID;";
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            var values = new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase);
            for (var ordinal = 0; ordinal < reader.FieldCount; ordinal++)
            {
                values[reader.GetName(ordinal)] = reader.IsDBNull(ordinal) ? null : reader.GetValue(ordinal);
            }

            var transactionId = values.TryGetValue("TransactionID", out var rawTransactionId) && rawTransactionId is not null && rawTransactionId is not DBNull
                ? Convert.ToInt32(rawTransactionId)
                : 0;

            rows.Add(new TransactionInputRecord(
                transactionId,
                values.TryGetValue("CalculationID", out var calculationValue) && calculationValue is not null && calculationValue is not DBNull
                    ? Convert.ToInt32(calculationValue)
                    : null,
                values.TryGetValue("FiscalYearID", out var fiscalYearValue) && fiscalYearValue is not null && fiscalYearValue is not DBNull
                    ? Convert.ToInt32(fiscalYearValue)
                    : null,
                values.TryGetValue("VersionID", out var versionValue) && versionValue is not null && versionValue is not DBNull
                    ? Convert.ToInt32(versionValue)
                    : null,
                values.TryGetValue("TransactionTypeCode", out var transactionTypeCode) ? transactionTypeCode?.ToString() : null,
                values.TryGetValue("UOMCodeInpC", out var uomCodeInpC) ? uomCodeInpC?.ToString() : null,
                values));
        }

        return rows;
    }

    private static void AppendTransactionBatchFilters(
        SqlCommand command,
        List<string> filters,
        int? fiscalYearId,
        int? versionId,
        IReadOnlyList<string>? dataObjectCodes,
        IReadOnlyList<string>? transactionTypeCodes)
    {
        if (fiscalYearId.HasValue && fiscalYearId.Value > 0)
        {
            command.Parameters.AddWithValue("@fiscalYearId", fiscalYearId.Value);
            filters.Add("FiscalYearID = @fiscalYearId");
        }

        if (versionId.HasValue && versionId.Value > 0)
        {
            command.Parameters.AddWithValue("@versionId", versionId.Value);
            filters.Add("VersionID = @versionId");
        }

        var normalizedDataObjects = NormalizeFilterValues(dataObjectCodes);
        if (normalizedDataObjects.Count > 0)
        {
            var clauses = new List<string>(normalizedDataObjects.Count);
            for (var index = 0; index < normalizedDataObjects.Count; index++)
            {
                var parameterName = "@doc" + index.ToString(CultureInfo.InvariantCulture);
                command.Parameters.AddWithValue(parameterName, normalizedDataObjects[index]);
                clauses.Add($"LEFT(ISNULL(DataObjectCode, ''), LEN({parameterName})) = {parameterName}");
            }

            filters.Add("(" + string.Join(" OR ", clauses) + ")");
        }

        var normalizedTransactionTypes = NormalizeFilterValues(transactionTypeCodes);
        if (normalizedTransactionTypes.Count > 0)
        {
            var parameterNames = new List<string>(normalizedTransactionTypes.Count);
            for (var index = 0; index < normalizedTransactionTypes.Count; index++)
            {
                var parameterName = "@tt" + index.ToString(CultureInfo.InvariantCulture);
                parameterNames.Add(parameterName);
                command.Parameters.AddWithValue(parameterName, normalizedTransactionTypes[index]);
            }

            filters.Add($"TransactionTypeCode IN ({string.Join(", ", parameterNames)})");
        }
    }

    private static IReadOnlyList<string> NormalizeFilterValues(IReadOnlyList<string>? values)
        => (values ?? [])
            .Select(x => x?.Trim())
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToArray()!;

    private static async Task<TransactionInputRecord?> LoadExistingChildTransactionAsync(
        SqlConnection connection,
        int headRecordId,
        int calculationId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT TOP (1) *
            FROM dbo.tblTransactionInput
            WHERE HeadRecordID = @headRecordId
              AND CalculationID = @calculationId
              AND RecordTypeCode = 'C'
            ORDER BY TransactionID;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@headRecordId", headRecordId);
        command.Parameters.AddWithValue("@calculationId", calculationId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        var values = new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase);
        for (var ordinal = 0; ordinal < reader.FieldCount; ordinal++)
        {
            values[reader.GetName(ordinal)] = reader.IsDBNull(ordinal) ? null : reader.GetValue(ordinal);
        }

        var transactionId = values.TryGetValue("TransactionID", out var rawTransactionId) && rawTransactionId is not null
            ? Convert.ToInt32(rawTransactionId)
            : 0;

        return new TransactionInputRecord(
            transactionId,
            values.TryGetValue("CalculationID", out var calculationValue) && calculationValue is not null
                ? Convert.ToInt32(calculationValue)
                : null,
            values.TryGetValue("FiscalYearID", out var fiscalYearId) && fiscalYearId is not null
                ? Convert.ToInt32(fiscalYearId)
                : null,
            values.TryGetValue("VersionID", out var versionId) && versionId is not null
                ? Convert.ToInt32(versionId)
                : null,
            values.TryGetValue("TransactionTypeCode", out var transactionTypeCode) ? transactionTypeCode?.ToString() : null,
            values.TryGetValue("UOMCodeInpC", out var uomCodeInpC) ? uomCodeInpC?.ToString() : null,
            values);
    }

    private static async Task UpdateTransactionInputAsync(
        SqlConnection connection,
        int transactionId,
        IReadOnlyDictionary<string, object?> values,
        CancellationToken cancellationToken)
    {
        var writable = FilterWritableTransactionValues(values)
            .Where(x => !string.Equals(x.Key, "TransactionID", StringComparison.OrdinalIgnoreCase))
            .ToArray();

        var setClauses = new List<string>(writable.Length);
        await using var command = new SqlCommand
        {
            Connection = connection,
            CommandText = string.Empty,
        };

        for (var index = 0; index < writable.Length; index++)
        {
            var parameterName = $"@p{index}";
            setClauses.Add($"[{writable[index].Key}] = {parameterName}");
            command.Parameters.AddWithValue(parameterName, writable[index].Value ?? DBNull.Value);
        }

        command.Parameters.AddWithValue("@transactionId", transactionId);
        command.CommandText = $"UPDATE dbo.tblTransactionInput SET {string.Join(", ", setClauses)} WHERE TransactionID = @transactionId;";
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task<int> InsertTransactionInputAsync(
        SqlConnection connection,
        IReadOnlyDictionary<string, object?> values,
        CancellationToken cancellationToken)
    {
        var writable = FilterWritableTransactionValues(values)
            .Where(x => !string.Equals(x.Key, "TransactionID", StringComparison.OrdinalIgnoreCase))
            .ToArray();

        var columns = new List<string>(writable.Length);
        var placeholders = new List<string>(writable.Length);
        await using var command = new SqlCommand
        {
            Connection = connection,
            CommandText = string.Empty,
        };

        for (var index = 0; index < writable.Length; index++)
        {
            var parameterName = $"@p{index}";
            columns.Add($"[{writable[index].Key}]");
            placeholders.Add(parameterName);
            command.Parameters.AddWithValue(parameterName, writable[index].Value ?? DBNull.Value);
        }

        command.CommandText = $"""
            INSERT INTO dbo.tblTransactionInput ({string.Join(", ", columns)})
            OUTPUT INSERTED.TransactionID
            VALUES ({string.Join(", ", placeholders)});
            """;

        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return raw is null or DBNull
            ? 0
            : Convert.ToInt32(raw);
    }

    private static IEnumerable<KeyValuePair<string, object?>> FilterWritableTransactionValues(
        IReadOnlyDictionary<string, object?> values)
        => values.Where(x => !string.IsNullOrWhiteSpace(x.Key) && x.Key[0] != '_');

    private static int ResolveHeadRecordId(TransactionInputRecord transaction)
    {
        if (transaction.Values.TryGetValue("HeadRecordID", out var rawHeadRecordId) &&
            rawHeadRecordId is not null &&
            rawHeadRecordId is not DBNull &&
            Convert.ToInt32(rawHeadRecordId) > 0)
        {
            return Convert.ToInt32(rawHeadRecordId);
        }

        return transaction.TransactionId;
    }

    private static void PreserveChildCeilingState(
        IDictionary<string, object?> targetValues,
        IReadOnlyDictionary<string, object?>? existingValues)
    {
        for (var month = 1; month <= 12; month++)
        {
            var key = $"CeilingAppliedBP{month}";
            if (!targetValues.ContainsKey(key))
            {
                continue;
            }

            targetValues[key] = existingValues is not null && existingValues.TryGetValue(key, out var existingValue)
                ? existingValue
                : null;
        }

        if (targetValues.ContainsKey("CeilingAppliedTotal"))
        {
            targetValues["CeilingAppliedTotal"] = existingValues is not null &&
                                                  existingValues.TryGetValue("CeilingAppliedTotal", out var existingTotal)
                ? existingTotal
                : null;
        }

        if (targetValues.ContainsKey("CeilingFailedFlag"))
        {
            targetValues["CeilingFailedFlag"] = 0;
        }
    }

    private async Task<TransactionExecutionSelection> BuildTransactionExecutionSelectionAsync(
        SqlConnection connection,
        TransactionInputRecord transaction,
        CalcTransactionBridge bridge,
        string? scenarioCode,
        CancellationToken cancellationToken,
        ExecutionReport? previousReport,
        IReadOnlyDictionary<string, ExecutionReport>? priorReportsByName = null)
    {
        var model = await LoadModelByIdAsync(connection, bridge.CalcModelId, cancellationToken);
        if (model is null)
        {
            throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} references missing model ID {bridge.CalcModelId}.");
        }

        var bundle = await LoadBundleForModelAsync(connection, model, cancellationToken);
        var scenario = !string.IsNullOrWhiteSpace(scenarioCode)
            ? ResolveScenario(bundle, scenarioCode)
            : bundle.Scenarios.FirstOrDefault(x => x.ScenarioId == bridge.ScenarioId);

        if (scenario is null)
        {
            throw new InvalidOperationException(
                $"Transaction bridge {bridge.CalcTransactionBridgeId} could not resolve scenario '{scenarioCode ?? bridge.ScenarioCode}'.");
        }

        var lineage = BuildScenarioLineage(bundle.Scenarios, scenario);
        var inputs = await LoadEffectiveScenarioNodeValuesAsync(lineage, bundle.Model.CalcModelId, cancellationToken);
        var baseSelection = new ScenarioSelection(bundle, scenario, inputs);
        var nodeMaps = await LoadTransactionNodeMapsAsync(connection, bridge.CalcTransactionBridgeId, cancellationToken);
        nodeMaps = await ResolveSpecialTransactionNodeMapsAsync(connection, scenario.ScenarioId, transaction, nodeMaps, cancellationToken);
        return TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(baseSelection, bridge, nodeMaps, transaction, previousReport, priorReportsByName);
    }

    private static async Task<IReadOnlyList<CalcTransactionBridge>> LoadTransactionBridgesAsync(
        SqlConnection connection,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT
                b.CalcTransactionBridgeID,
                b.CalcModelID,
                m.ModelCode,
                b.ScenarioID,
                s.ScenarioCode,
                b.CostObjectID,
                b.LegacyCalculationID,
                b.FiscalYearID,
                b.VersionID,
                b.TransactionTypeCode,
                b.UOMCodeInpC,
                b.PriorityNo,
                b.ActiveFlag
            FROM dbo.tblCalcTransactionBridge b
            INNER JOIN dbo.tblCalcModel m ON m.CalcModelID = b.CalcModelID
            INNER JOIN dbo.tblCalcScenario s ON s.ScenarioID = b.ScenarioID
            ORDER BY b.PriorityNo, b.CalcTransactionBridgeID;
            """;

        var rows = new List<CalcTransactionBridge>();
        await using var command = new SqlCommand(sql, connection);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcTransactionBridge(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetString(2),
                reader.GetInt32(3),
                reader.GetString(4),
                reader.IsDBNull(5) ? null : reader.GetInt32(5),
                reader.IsDBNull(6) ? null : reader.GetInt32(6),
                reader.IsDBNull(7) ? null : reader.GetInt32(7),
                reader.IsDBNull(8) ? null : reader.GetInt32(8),
                reader.IsDBNull(9) ? null : reader.GetString(9),
                reader.IsDBNull(10) ? null : reader.GetString(10),
                reader.GetInt32(11),
                reader.GetBoolean(12)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<LegacyCalculationDefinition>> LoadLegacyCalculationsAsync(
        SqlConnection connection,
        int fiscalYearId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT
                FiscalYearID,
                CalculationID,
                CalculationName,
                ChildCalculationID,
                GenerateTransaction,
                ChildCalculationInherit,
                ChildTransactionTypeCode,
                RateLookupCode,
                DataObjectCode,
                TransactionTypeCode,
                GLAccountCode,
                UOMCodeDefault,
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
                Segment20Code
            FROM dbo.tblCalculations
            WHERE FiscalYearID = @fiscalYearId;
            """;

        var rows = new List<LegacyCalculationDefinition>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@fiscalYearId", fiscalYearId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            var segments = new List<string?>(capacity: 20);
            for (var ordinal = 12; ordinal <= 31; ordinal++)
            {
                segments.Add(reader.IsDBNull(ordinal) ? null : reader.GetString(ordinal));
            }

            rows.Add(new LegacyCalculationDefinition(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetString(2),
                reader.IsDBNull(3) ? null : reader.GetInt32(3),
                !reader.IsDBNull(4) && Convert.ToInt32(reader.GetValue(4)) == 1,
                reader.IsDBNull(5) ? null : reader.GetString(5),
                reader.IsDBNull(6) ? null : reader.GetString(6),
                reader.IsDBNull(7) ? null : reader.GetString(7),
                reader.IsDBNull(8) ? null : reader.GetString(8),
                reader.IsDBNull(9) ? null : reader.GetString(9),
                reader.IsDBNull(10) ? null : reader.GetString(10),
                reader.IsDBNull(11) ? null : reader.GetString(11),
                segments));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcTransactionNodeMap>> LoadTransactionNodeMapsAsync(
        SqlConnection connection,
        int calcTransactionBridgeId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT
                CalcTransactionNodeMapID,
                CalcTransactionBridgeID,
                NodeID,
                SourceTypeCode,
                SourceName,
                ConstantDecimal,
                RequiredFlag,
                ActiveFlag
            FROM dbo.tblCalcTransactionNodeMap
            WHERE CalcTransactionBridgeID = @calcTransactionBridgeId
            ORDER BY NodeID, CalcTransactionNodeMapID;
            """;

        var rows = new List<CalcTransactionNodeMap>();
        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@calcTransactionBridgeId", calcTransactionBridgeId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            rows.Add(new CalcTransactionNodeMap(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.GetInt32(2),
                reader.GetString(3),
                reader.IsDBNull(4) ? null : reader.GetString(4),
                reader.IsDBNull(5) ? null : reader.GetDecimal(5),
                reader.GetBoolean(6),
                reader.GetBoolean(7)));
        }

        return rows;
    }

    private static async Task<IReadOnlyList<CalcTransactionNodeMap>> ResolveSpecialTransactionNodeMapsAsync(
        SqlConnection connection,
        int scenarioId,
        TransactionInputRecord transaction,
        IReadOnlyList<CalcTransactionNodeMap> nodeMaps,
        CancellationToken cancellationToken)
    {
        var resolved = new List<CalcTransactionNodeMap>(nodeMaps.Count);
        foreach (var nodeMap in nodeMaps)
        {
            if (string.Equals(nodeMap.SourceTypeCode, "RATE_LOOKUP", StringComparison.OrdinalIgnoreCase))
            {
                var value = await ResolveRateLookupValueAsync(connection, scenarioId, transaction, nodeMap.SourceName, cancellationToken);
                resolved.Add(nodeMap with
                {
                    SourceTypeCode = "CONSTANT",
                    SourceName = null,
                    ConstantDecimal = value,
                });
                continue;
            }

            resolved.Add(nodeMap);
        }

        return resolved;
    }

    internal static IReadOnlyList<CalcTransactionNodeMap> ResolveNodeMapsForBenchmark(
        TransactionInputRecord transaction,
        IReadOnlyList<CalcTransactionNodeMap> nodeMaps,
        RateLookupCache? rateLookupCache)
    {
        if (rateLookupCache is null)
        {
            return nodeMaps;
        }

        var resolved = new List<CalcTransactionNodeMap>(nodeMaps.Count);
        foreach (var nodeMap in nodeMaps)
        {
            if (!string.Equals(nodeMap.SourceTypeCode, "RATE_LOOKUP", StringComparison.OrdinalIgnoreCase))
            {
                resolved.Add(nodeMap);
                continue;
            }

            var value = ResolveRateLookupValue(rateLookupCache, transaction, nodeMap.SourceName);
            resolved.Add(nodeMap with
            {
                SourceTypeCode = "CONSTANT",
                SourceName = null,
                ConstantDecimal = value,
            });
        }

        return resolved;
    }

    private static async Task<decimal?> ResolveRateLookupValueAsync(
        SqlConnection connection,
        int scenarioId,
        TransactionInputRecord transaction,
        string? sourceName,
        CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(sourceName))
        {
            return null;
        }

        var parts = sourceName.Split('|', 2, StringSplitOptions.TrimEntries);
        if (parts.Length != 2)
        {
            return null;
        }

        var rateColumn = parts[0];
        var selector = parts[1];
        var rateCode = ResolveRateCode(transaction, selector);
        if (string.IsNullOrWhiteSpace(rateColumn) ||
            string.IsNullOrWhiteSpace(rateCode) ||
            !transaction.FiscalYearId.HasValue ||
            !transaction.VersionId.HasValue)
        {
            return null;
        }

        var dataObjectCode = transaction.Values.TryGetValue("DataObjectCode", out var rawDataObjectCode)
            ? rawDataObjectCode?.ToString()
            : null;

        var overrideValue = await QueryScenarioRateOverrideValueAsync(
            connection,
            scenarioId,
            transaction.FiscalYearId.Value,
            transaction.VersionId.Value,
            dataObjectCode,
            rateCode,
            rateColumn,
            cancellationToken);

        if (overrideValue.HasValue)
        {
            return overrideValue;
        }

        var value = await QueryRateValueAsync(
            connection,
            transaction.FiscalYearId.Value,
            transaction.VersionId.Value,
            dataObjectCode,
            rateCode,
            rateColumn,
            cancellationToken);

        if (value.HasValue)
        {
            return value;
        }

        if (string.Equals(dataObjectCode, "0", StringComparison.OrdinalIgnoreCase))
        {
            return null;
        }

        overrideValue = await QueryScenarioRateOverrideValueAsync(
            connection,
            scenarioId,
            transaction.FiscalYearId.Value,
            transaction.VersionId.Value,
            "0",
            rateCode,
            rateColumn,
            cancellationToken);

        if (overrideValue.HasValue)
        {
            return overrideValue;
        }

        return await QueryRateValueAsync(
            connection,
            transaction.FiscalYearId.Value,
            transaction.VersionId.Value,
            "0",
            rateCode,
            rateColumn,
            cancellationToken);
    }

    private static decimal? ResolveRateLookupValue(
        RateLookupCache cache,
        TransactionInputRecord transaction,
        string? sourceName)
    {
        if (string.IsNullOrWhiteSpace(sourceName))
        {
            return null;
        }

        var parts = sourceName.Split('|', 2, StringSplitOptions.TrimEntries);
        if (parts.Length != 2)
        {
            return null;
        }

        var rateColumn = parts[0];
        var selector = parts[1];
        var rateCode = ResolveRateCode(transaction, selector);
        if (string.IsNullOrWhiteSpace(rateColumn) ||
            string.IsNullOrWhiteSpace(rateCode) ||
            !transaction.FiscalYearId.HasValue ||
            !transaction.VersionId.HasValue)
        {
            return null;
        }

        var dataObjectCode = transaction.Values.TryGetValue("DataObjectCode", out var rawDataObjectCode)
            ? rawDataObjectCode?.ToString() ?? "0"
            : "0";

        if (cache.TryGetValue(transaction.FiscalYearId.Value, transaction.VersionId.Value, dataObjectCode, rateCode, rateColumn, out var value))
        {
            return value;
        }

        return !string.Equals(dataObjectCode, "0", StringComparison.OrdinalIgnoreCase) &&
               cache.TryGetValue(transaction.FiscalYearId.Value, transaction.VersionId.Value, "0", rateCode, rateColumn, out value)
            ? value
            : null;
    }

    private static string? ResolveRateCode(TransactionInputRecord transaction, string selector)
    {
        if (selector.StartsWith("FIELD:", StringComparison.OrdinalIgnoreCase))
        {
            var fieldName = selector["FIELD:".Length..];
            return transaction.Values.TryGetValue(fieldName, out var raw)
                ? raw?.ToString()
                : null;
        }

        if (selector.StartsWith("LITERAL:", StringComparison.OrdinalIgnoreCase))
        {
            return selector["LITERAL:".Length..];
        }

        return selector;
    }

    private static async Task<RateLookupCache?> LoadRateLookupCacheAsync(
        SqlConnection connection,
        IReadOnlyList<TransactionInputRecord> transactions,
        IReadOnlyList<CalcTransactionNodeMap> nodeMaps,
        int scenarioId,
        CancellationToken cancellationToken)
    {
        var rateLookupMaps = nodeMaps
            .Where(x => x.ActiveFlag && string.Equals(x.SourceTypeCode, "RATE_LOOKUP", StringComparison.OrdinalIgnoreCase))
            .ToArray();

        if (rateLookupMaps.Length == 0 || transactions.Count == 0)
        {
            return null;
        }

        var fiscalYears = transactions
            .Where(x => x.FiscalYearId.HasValue)
            .Select(x => x.FiscalYearId!.Value)
            .Distinct()
            .OrderBy(x => x)
            .ToArray();
        var versions = transactions
            .Where(x => x.VersionId.HasValue)
            .Select(x => x.VersionId!.Value)
            .Distinct()
            .OrderBy(x => x)
            .ToArray();

        var dataObjectCodes = transactions
            .Select(x => x.Values.TryGetValue("DataObjectCode", out var raw) ? raw?.ToString() : null)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Append("0")
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Select(x => x!)
            .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        var rateCodes = transactions
            .SelectMany(transaction => rateLookupMaps
                .Select(map =>
                {
                    var sourceName = map.SourceName;
                    if (string.IsNullOrWhiteSpace(sourceName))
                    {
                        return null;
                    }

                    var parts = sourceName.Split('|', 2, StringSplitOptions.TrimEntries);
                    return parts.Length == 2 ? ResolveRateCode(transaction, parts[1]) : null;
                }))
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Select(x => x!)
            .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        if (fiscalYears.Length == 0 || versions.Length == 0 || rateCodes.Length == 0 || dataObjectCodes.Length == 0)
        {
            return null;
        }

        var requiredColumns = rateLookupMaps
            .Select(x => x.SourceName?.Split('|', 2, StringSplitOptions.TrimEntries).FirstOrDefault())
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Select(x => x!)
            .ToArray();
        var selectColumns = requiredColumns
            .Select(ToSafeRateColumn)
            .Where(x => x is not null)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Select(x => x!)
            .ToArray();

        var fiscalYearParameters = fiscalYears.Select((value, index) => new { value, name = $"@fy{index}" }).ToArray();
        var versionParameters = versions.Select((value, index) => new { value, name = $"@ver{index}" }).ToArray();
        var dataObjectParameters = dataObjectCodes.Select((value, index) => new { value, name = $"@doc{index}" }).ToArray();
        var rateCodeParameters = rateCodes.Select((value, index) => new { value, name = $"@rc{index}" }).ToArray();

        var sql = $"""
            SELECT FiscalYearID, VersionID, DataObjectCode, RateCode, {string.Join(", ", selectColumns)}
            FROM dbo.tblRates
            WHERE FiscalYearID IN ({string.Join(", ", fiscalYearParameters.Select(x => x.name))})
              AND VersionID IN ({string.Join(", ", versionParameters.Select(x => x.name))})
              AND DataObjectCode IN ({string.Join(", ", dataObjectParameters.Select(x => x.name))})
              AND RateCode IN ({string.Join(", ", rateCodeParameters.Select(x => x.name))});
            """;

        var rows = new Dictionary<RateLookupCacheKey, Dictionary<string, decimal?>>();
        await using var command = new SqlCommand(sql, connection);
        foreach (var parameter in fiscalYearParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in versionParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in dataObjectParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in rateCodeParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }

        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            var key = new RateLookupCacheKey(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.IsDBNull(2) ? "0" : reader.GetString(2),
                reader.GetString(3));
            var values = new Dictionary<string, decimal?>(StringComparer.OrdinalIgnoreCase);
            for (var ordinal = 4; ordinal < reader.FieldCount; ordinal++)
            {
                values[reader.GetName(ordinal)] = reader.IsDBNull(ordinal) ? null : reader.GetDecimal(ordinal);
            }

            rows[key] = values;
        }

        await OverlayScenarioRateOverridesAsync(
            connection,
            rows,
            scenarioId,
            fiscalYears,
            versions,
            dataObjectCodes,
            rateCodes,
            selectColumns,
            cancellationToken);

        return new RateLookupCache(rows);
    }

    private static async Task<decimal?> QueryRateValueAsync(
        SqlConnection connection,
        int fiscalYearId,
        int versionId,
        string? dataObjectCode,
        string rateCode,
        string rateColumn,
        CancellationToken cancellationToken)
    {
        var safeColumn = rateColumn switch
        {
            "BPRate" => "BPRate",
            "BP1Rate" => "BP1Rate",
            "BP2Rate" => "BP2Rate",
            "BP3Rate" => "BP3Rate",
            "BP4Rate" => "BP4Rate",
            "BP5Rate" => "BP5Rate",
            "BP6Rate" => "BP6Rate",
            "BP7Rate" => "BP7Rate",
            "BP8Rate" => "BP8Rate",
            "BP9Rate" => "BP9Rate",
            "BP10Rate" => "BP10Rate",
            "BP11Rate" => "BP11Rate",
            "BP12Rate" => "BP12Rate",
            "OY1Rate" => "OY1Rate",
            "OY2Rate" => "OY2Rate",
            "OY3Rate" => "OY3Rate",
            "OY4Rate" => "OY4Rate",
            "OY5Rate" => "OY5Rate",
            "OY6Rate" => "OY6Rate",
            "OY7Rate" => "OY7Rate",
            "OY8Rate" => "OY8Rate",
            "OY9Rate" => "OY9Rate",
            "OY10Rate" => "OY10Rate",
            _ => null,
        };

        if (safeColumn is null)
        {
            return null;
        }

        var sql = $"""
            SELECT TOP (1) {safeColumn}
            FROM dbo.tblRates
            WHERE FiscalYearID = @fiscalYearId
              AND VersionID = @versionId
              AND DataObjectCode = @dataObjectCode
              AND RateCode = @rateCode;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@fiscalYearId", fiscalYearId);
        command.Parameters.AddWithValue("@versionId", versionId);
        command.Parameters.AddWithValue("@dataObjectCode", (object?)dataObjectCode ?? DBNull.Value);
        command.Parameters.AddWithValue("@rateCode", rateCode);
        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return raw is null or DBNull ? null : Convert.ToDecimal(raw);
    }

    private static async Task<decimal?> QueryScenarioRateOverrideValueAsync(
        SqlConnection connection,
        int scenarioId,
        int fiscalYearId,
        int versionId,
        string? dataObjectCode,
        string rateCode,
        string rateColumn,
        CancellationToken cancellationToken)
    {
        var safeColumn = ToSafeRateColumn(rateColumn);
        if (safeColumn is null || !await ScenarioRateOverrideTableExistsAsync(connection, cancellationToken))
        {
            return null;
        }

        const string lineageSqlPrefix = """
            WITH ScenarioLineage AS (
                SELECT
                    s.ScenarioID,
                    s.ParentScenarioID,
                    CAST(0 AS INT) AS Depth
                FROM dbo.tblCalcScenario s
                WHERE s.ScenarioID = @scenarioId

                UNION ALL

                SELECT
                    parent.ScenarioID,
                    parent.ParentScenarioID,
                    line.Depth + 1
                FROM dbo.tblCalcScenario parent
                INNER JOIN ScenarioLineage line
                    ON parent.ScenarioID = line.ParentScenarioID
            )
            """;

        var sql = $"""
            {lineageSqlPrefix}
            SELECT TOP (1) override.[{safeColumn}]
            FROM ScenarioLineage line
            INNER JOIN dbo.tblCalcScenarioRateOverride override
                ON override.ScenarioID = line.ScenarioID
            WHERE override.FiscalYearID = @fiscalYearId
              AND override.VersionID = @versionId
              AND override.DataObjectCode = @dataObjectCode
              AND override.RateCode = @rateCode
              AND override.[{safeColumn}] IS NOT NULL
            ORDER BY line.Depth;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@scenarioId", scenarioId);
        command.Parameters.AddWithValue("@fiscalYearId", fiscalYearId);
        command.Parameters.AddWithValue("@versionId", versionId);
        command.Parameters.AddWithValue("@dataObjectCode", (object?)dataObjectCode ?? DBNull.Value);
        command.Parameters.AddWithValue("@rateCode", rateCode);
        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return raw is null or DBNull ? null : Convert.ToDecimal(raw);
    }

    private static async Task OverlayScenarioRateOverridesAsync(
        SqlConnection connection,
        Dictionary<RateLookupCacheKey, Dictionary<string, decimal?>> rows,
        int scenarioId,
        IReadOnlyList<int> fiscalYears,
        IReadOnlyList<int> versions,
        IReadOnlyList<string> dataObjectCodes,
        IReadOnlyList<string> rateCodes,
        IReadOnlyList<string> selectColumns,
        CancellationToken cancellationToken)
    {
        if (scenarioId <= 0 || selectColumns.Count == 0 || !await ScenarioRateOverrideTableExistsAsync(connection, cancellationToken))
        {
            return;
        }

        var fiscalYearParameters = fiscalYears.Select((value, index) => new { value, name = $"@ofy{index}" }).ToArray();
        var versionParameters = versions.Select((value, index) => new { value, name = $"@over{index}" }).ToArray();
        var dataObjectParameters = dataObjectCodes.Select((value, index) => new { value, name = $"@odoc{index}" }).ToArray();
        var rateCodeParameters = rateCodes.Select((value, index) => new { value, name = $"@orc{index}" }).ToArray();
        var projectedColumns = string.Join(", ", selectColumns.Select(x => $"candidate.[{x}]"));

        var sql = $"""
            WITH ScenarioLineage AS (
                SELECT
                    s.ScenarioID,
                    s.ParentScenarioID,
                    CAST(0 AS INT) AS Depth
                FROM dbo.tblCalcScenario s
                WHERE s.ScenarioID = @scenarioId

                UNION ALL

                SELECT
                    parent.ScenarioID,
                    parent.ParentScenarioID,
                    line.Depth + 1
                FROM dbo.tblCalcScenario parent
                INNER JOIN ScenarioLineage line
                    ON parent.ScenarioID = line.ParentScenarioID
            ),
            RankedOverrides AS (
                SELECT
                    candidate.FiscalYearID,
                    candidate.VersionID,
                    candidate.DataObjectCode,
                    candidate.RateCode,
                    {projectedColumns},
                    ROW_NUMBER() OVER (
                        PARTITION BY candidate.FiscalYearID, candidate.VersionID, candidate.DataObjectCode, candidate.RateCode
                        ORDER BY line.Depth
                    ) AS RowNo
                FROM ScenarioLineage line
                INNER JOIN dbo.tblCalcScenarioRateOverride candidate
                    ON candidate.ScenarioID = line.ScenarioID
                WHERE candidate.FiscalYearID IN ({string.Join(", ", fiscalYearParameters.Select(x => x.name))})
                  AND candidate.VersionID IN ({string.Join(", ", versionParameters.Select(x => x.name))})
                  AND candidate.DataObjectCode IN ({string.Join(", ", dataObjectParameters.Select(x => x.name))})
                  AND candidate.RateCode IN ({string.Join(", ", rateCodeParameters.Select(x => x.name))})
            )
            SELECT FiscalYearID, VersionID, DataObjectCode, RateCode, {string.Join(", ", selectColumns.Select(x => $"[{x}]"))}
            FROM RankedOverrides
            WHERE RowNo = 1;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@scenarioId", scenarioId);
        foreach (var parameter in fiscalYearParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in versionParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in dataObjectParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }
        foreach (var parameter in rateCodeParameters)
        {
            command.Parameters.AddWithValue(parameter.name, parameter.value);
        }

        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            var key = new RateLookupCacheKey(
                reader.GetInt32(0),
                reader.GetInt32(1),
                reader.IsDBNull(2) ? "0" : reader.GetString(2),
                reader.GetString(3));

            if (!rows.TryGetValue(key, out var values))
            {
                values = new Dictionary<string, decimal?>(StringComparer.OrdinalIgnoreCase);
                rows[key] = values;
            }

            for (var ordinal = 4; ordinal < reader.FieldCount; ordinal++)
            {
                if (!reader.IsDBNull(ordinal))
                {
                    values[reader.GetName(ordinal)] = reader.GetDecimal(ordinal);
                }
            }
        }
    }

    private static async Task<bool> ScenarioRateOverrideTableExistsAsync(
        SqlConnection connection,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT CASE WHEN OBJECT_ID('dbo.tblCalcScenarioRateOverride', 'U') IS NOT NULL THEN 1 ELSE 0 END;
            """;
        await using var command = new SqlCommand(sql, connection);
        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return raw is not null and not DBNull && Convert.ToInt32(raw, CultureInfo.InvariantCulture) == 1;
    }

    private static string? ToSafeRateColumn(string? rateColumn)
        => rateColumn switch
        {
            "BPRate" => "BPRate",
            "BP1Rate" => "BP1Rate",
            "BP2Rate" => "BP2Rate",
            "BP3Rate" => "BP3Rate",
            "BP4Rate" => "BP4Rate",
            "BP5Rate" => "BP5Rate",
            "BP6Rate" => "BP6Rate",
            "BP7Rate" => "BP7Rate",
            "BP8Rate" => "BP8Rate",
            "BP9Rate" => "BP9Rate",
            "BP10Rate" => "BP10Rate",
            "BP11Rate" => "BP11Rate",
            "BP12Rate" => "BP12Rate",
            "OY1Rate" => "OY1Rate",
            "OY2Rate" => "OY2Rate",
            "OY3Rate" => "OY3Rate",
            "OY4Rate" => "OY4Rate",
            "OY5Rate" => "OY5Rate",
            "OY6Rate" => "OY6Rate",
            "OY7Rate" => "OY7Rate",
            "OY8Rate" => "OY8Rate",
            "OY9Rate" => "OY9Rate",
            "OY10Rate" => "OY10Rate",
            _ => null,
        };

    private static async Task DeleteLegacyTransactionResultsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        int transactionId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            DELETE FROM dbo.tblTransactionResultPeriod WHERE TransactionID = @transactionId;
            DELETE FROM dbo.tblTransactionResultFlat WHERE TransactionID = @transactionId;
            DELETE FROM dbo.tblTransactionResult WHERE TransactionID = @transactionId;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@transactionId", transactionId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task<int> InsertLegacyTransactionResultAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        int transactionId,
        LegacyTransactionResultProjection projection,
        Guid runId,
        string formulaSetCode,
        int scopeVersion,
        string engineVersion,
        string status,
        string? errorMessage,
        int formulaVersion,
        DateTime calculatedAtUtc,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResult
            (
                TransactionID,
                ResultJSON,
                CalculatedDate,
                FormulaVersion,
                RunID,
                FormulaSetCode,
                ScopeVersion,
                EngineVersion,
                Status,
                DurationMs,
                BPTotal,
                ErrorMessage
            )
            OUTPUT INSERTED.TransactionResultID
            VALUES
            (
                @transactionId,
                @resultJson,
                @calculatedDate,
                @formulaVersion,
                @runId,
                @formulaSetCode,
                @scopeVersion,
                @engineVersion,
                @status,
                NULL,
                @bpTotal,
                @errorMessage
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@transactionId", transactionId);
        command.Parameters.AddWithValue("@resultJson", projection.ResultJson);
        command.Parameters.AddWithValue("@calculatedDate", calculatedAtUtc);
        command.Parameters.AddWithValue("@formulaVersion", formulaVersion);
        command.Parameters.AddWithValue("@runId", runId);
        command.Parameters.AddWithValue("@formulaSetCode", (object?)formulaSetCode ?? DBNull.Value);
        command.Parameters.AddWithValue("@scopeVersion", scopeVersion);
        command.Parameters.AddWithValue("@engineVersion", (object?)engineVersion ?? DBNull.Value);
        command.Parameters.AddWithValue("@status", status);
        command.Parameters.AddWithValue("@bpTotal", projection.Total);
        command.Parameters.AddWithValue("@errorMessage", (object?)errorMessage ?? DBNull.Value);
        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return raw is null or DBNull
            ? 0
            : Convert.ToInt32(raw);
    }

    private static async Task InsertLegacyTransactionResultFlatAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        TransactionInputRecord input,
        int transactionResultId,
        LegacyTransactionResultProjection projection,
        DateTime calculatedAtUtc,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResultFlat
            (
                TransactionResultID,
                TransactionID,
                HeadRecordID,
                RecordTypeCode,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                TransactionTypeCode,
                AccountCode,
                GLAccountCode,
                CostItemID,
                CalculationID,
                CurrencyInpC,
                UOMCodeInpC,
                Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
                Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
                Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
                Segment16Code, Segment17Code, Segment18Code, Segment19Code, Segment20Code,
                BP1, BP2, BP3, BP4, BP5, BP6,
                BP7, BP8, BP9, BP10, BP11, BP12,
                BPTotal,
                PY5, PY4, PY3, PY2, PY1,
                BPOpBal,
                BPQ1, BPQ2, BPQ3, BPQ4,
                BPOY1, BPOY2, BPOY3, BPOY4, BPOY5,
                BPOY6, BPOY7, BPOY8, BPOY9, BPOY10,
                CalculatedDate
            )
            VALUES
            (
                @transactionResultId,
                @transactionId,
                @headRecordId,
                @recordTypeCode,
                @fiscalYearId,
                @versionId,
                @dataObjectCode,
                @transactionTypeCode,
                @accountCode,
                @glAccountCode,
                @costItemId,
                @calculationId,
                @currencyInpC,
                @uomCodeInpC,
                @segment1Code, @segment2Code, @segment3Code, @segment4Code, @segment5Code,
                @segment6Code, @segment7Code, @segment8Code, @segment9Code, @segment10Code,
                @segment11Code, @segment12Code, @segment13Code, @segment14Code, @segment15Code,
                @segment16Code, @segment17Code, @segment18Code, @segment19Code, @segment20Code,
                @bp1, @bp2, @bp3, @bp4, @bp5, @bp6,
                @bp7, @bp8, @bp9, @bp10, @bp11, @bp12,
                @bpTotal,
                @py5, @py4, @py3, @py2, @py1,
                @bpOpBal,
                @bpq1, @bpq2, @bpq3, @bpq4,
                @bpoy1, @bpoy2, @bpoy3, @bpoy4, @bpoy5,
                @bpoy6, @bpoy7, @bpoy8, @bpoy9, @bpoy10,
                @calculatedDate
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@transactionResultId", transactionResultId);
        command.Parameters.AddWithValue("@transactionId", input.TransactionId);
        command.Parameters.AddWithValue("@headRecordId", DbNullableInt(input.Values, "HeadRecordID"));
        command.Parameters.AddWithValue("@recordTypeCode", DbNullableString(input.Values, "RecordTypeCode"));
        command.Parameters.AddWithValue("@fiscalYearId", input.FiscalYearId.HasValue ? input.FiscalYearId.Value : DBNull.Value);
        command.Parameters.AddWithValue("@versionId", input.VersionId.HasValue ? input.VersionId.Value : DBNull.Value);
        command.Parameters.AddWithValue("@dataObjectCode", DbNullableString(input.Values, "DataObjectCode"));
        command.Parameters.AddWithValue("@transactionTypeCode", (object?)input.TransactionTypeCode ?? DBNull.Value);
        command.Parameters.AddWithValue("@accountCode", DbNullableString(input.Values, "AccountCode"));
        command.Parameters.AddWithValue("@glAccountCode", DbNullableString(input.Values, "GLAccountCode"));
        command.Parameters.AddWithValue("@costItemId", DbNullableInt(input.Values, "CostItemID"));
        command.Parameters.AddWithValue("@calculationId", input.CalculationId.HasValue ? input.CalculationId.Value : DBNull.Value);
        command.Parameters.AddWithValue("@currencyInpC", DbNullableString(input.Values, "CurrencyInpC"));
        command.Parameters.AddWithValue("@uomCodeInpC", (object?)input.UomCodeInpC ?? DBNull.Value);

        for (var segmentNumber = 1; segmentNumber <= 20; segmentNumber++)
        {
            command.Parameters.AddWithValue(
                $"@segment{segmentNumber}Code",
                DbNullableString(input.Values, $"Segment{segmentNumber}Code"));
        }

        for (var monthNumber = 1; monthNumber <= 12; monthNumber++)
        {
            command.Parameters.AddWithValue(
                $"@bp{monthNumber}",
                projection.Monthly[$"BP{monthNumber}"]);
        }

        command.Parameters.AddWithValue("@bpTotal", projection.Total);
        command.Parameters.AddWithValue("@py5", DbNullableDecimal(input.Values, "PY5InpN"));
        command.Parameters.AddWithValue("@py4", DbNullableDecimal(input.Values, "PY4InpN"));
        command.Parameters.AddWithValue("@py3", DbNullableDecimal(input.Values, "PY3InpN"));
        command.Parameters.AddWithValue("@py2", DbNullableDecimal(input.Values, "PY2InpN"));
        command.Parameters.AddWithValue("@py1", DbNullableDecimal(input.Values, "PY1InpN"));
        command.Parameters.AddWithValue("@bpOpBal", DbNullableDecimal(input.Values, "BPOpBalInpN"));
        command.Parameters.AddWithValue("@bpq1", projection.Quarterly["BPQ1"]);
        command.Parameters.AddWithValue("@bpq2", projection.Quarterly["BPQ2"]);
        command.Parameters.AddWithValue("@bpq3", projection.Quarterly["BPQ3"]);
        command.Parameters.AddWithValue("@bpq4", projection.Quarterly["BPQ4"]);

        for (var outYear = 1; outYear <= 10; outYear++)
        {
            command.Parameters.AddWithValue(
                $"@bpoy{outYear}",
                projection.OutYears[$"BPOY{outYear}"]);
        }

        command.Parameters.AddWithValue("@calculatedDate", calculatedAtUtc);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task InsertLegacyTransactionResultPeriodsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        int transactionId,
        int transactionResultId,
        LegacyTransactionResultProjection projection,
        DateTime calculatedAtUtc,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResultPeriod
            (
                TransactionResultID,
                TransactionID,
                PeriodCode,
                Amount,
                CalculatedDate
            )
            VALUES
            (
                @transactionResultId,
                @transactionId,
                @periodCode,
                @amount,
                @calculatedDate
            );
            """;

        foreach (var row in projection.PeriodRows)
        {
            await using var command = new SqlCommand(sql, connection, transaction);
            command.Parameters.AddWithValue("@transactionResultId", transactionResultId);
            command.Parameters.AddWithValue("@transactionId", transactionId);
            command.Parameters.AddWithValue("@periodCode", row.PeriodCode);
            command.Parameters.AddWithValue("@amount", row.Amount);
            command.Parameters.AddWithValue("@calculatedDate", calculatedAtUtc);
            await command.ExecuteNonQueryAsync(cancellationToken);
        }
    }

    private static async Task CreateChildTransactionStageTablesAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT TOP 0
                CAST(0 AS INT) AS BatchOrdinal,
                ti.*
            INTO #ChildStage
            FROM dbo.tblTransactionInput ti;

            CREATE TABLE #ChildStageResultMap
            (
                BatchOrdinal INT NOT NULL PRIMARY KEY,
                TransactionID INT NOT NULL
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static DataTable BuildChildTransactionStageTable(
        IReadOnlyList<TransactionInputRecord> rows,
        IReadOnlyList<string> writableColumns)
    {
        var table = new DataTable();
        table.Columns.Add("BatchOrdinal", typeof(int));
        foreach (var column in writableColumns)
        {
            table.Columns.Add(column, typeof(object));
        }

        for (var index = 0; index < rows.Count; index++)
        {
            var values = new object?[writableColumns.Count + 1];
            values[0] = index + 1;
            for (var columnIndex = 0; columnIndex < writableColumns.Count; columnIndex++)
            {
                values[columnIndex + 1] = NullableValue(rows[index].Values, writableColumns[columnIndex]);
            }

            table.Rows.Add(values);
        }

        return table;
    }

    private static async Task<IReadOnlyDictionary<int, int>> MergeChildTransactionsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        IReadOnlyList<string> writableColumns,
        CancellationToken cancellationToken)
    {
        var updateColumns = writableColumns
            .Where(x => !string.Equals(x, "HeadRecordID", StringComparison.OrdinalIgnoreCase))
            .Where(x => !string.Equals(x, "CalculationID", StringComparison.OrdinalIgnoreCase))
            .Where(x => !string.Equals(x, "RecordTypeCode", StringComparison.OrdinalIgnoreCase))
            .ToArray();
        var setClauses = updateColumns
            .Select(column => $"target.[{column}] = src.[{column}]");
        var insertColumns = writableColumns
            .Select(column => $"[{column}]");
        var insertValues = writableColumns
            .Select(column => $"src.[{column}]");

        var sql = $"""
            MERGE dbo.tblTransactionInput AS target
            USING #ChildStage AS src
               ON target.HeadRecordID = src.HeadRecordID
              AND target.CalculationID = src.CalculationID
              AND target.RecordTypeCode = 'C'
            WHEN MATCHED THEN
                UPDATE SET {string.Join(", ", setClauses)}
            WHEN NOT MATCHED THEN
                INSERT ({string.Join(", ", insertColumns)})
                VALUES ({string.Join(", ", insertValues)})
            OUTPUT src.BatchOrdinal, inserted.TransactionID
                INTO #ChildStageResultMap (BatchOrdinal, TransactionID);

            SELECT BatchOrdinal, TransactionID
            FROM #ChildStageResultMap
            ORDER BY BatchOrdinal;
            """;

        var map = new Dictionary<int, int>();
        await using var command = new SqlCommand(sql, connection, transaction);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
        {
            map[reader.GetInt32(0)] = reader.GetInt32(1);
        }

        return map;
    }

    private static async Task CreateLegacyBatchTempTablesAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            CREATE TABLE #TargetTransactions
            (
                TransactionID INT NOT NULL PRIMARY KEY
            );

            CREATE TABLE #ResultStage
            (
                BatchOrdinal INT NOT NULL PRIMARY KEY,
                TransactionID INT NOT NULL,
                ResultJson NVARCHAR(MAX) NOT NULL,
                CalculatedDate DATETIME2 NOT NULL,
                FormulaVersion INT NOT NULL,
                RunID UNIQUEIDENTIFIER NOT NULL,
                FormulaSetCode NVARCHAR(200) NULL,
                ScopeVersion INT NULL,
                EngineVersion NVARCHAR(200) NULL,
                Status NVARCHAR(50) NOT NULL,
                BPTotal DECIMAL(19,6) NOT NULL,
                ErrorMessage NVARCHAR(MAX) NULL
            );

            CREATE TABLE #ResultMap
            (
                BatchOrdinal INT NOT NULL PRIMARY KEY,
                TransactionResultID INT NOT NULL
            );

            CREATE TABLE #FlatStage
            (
                BatchOrdinal INT NOT NULL PRIMARY KEY,
                TransactionID INT NOT NULL,
                HeadRecordID INT NULL,
                RecordTypeCode CHAR(2) NULL,
                FiscalYearID INT NULL,
                VersionID INT NULL,
                DataObjectCode NVARCHAR(20) NULL,
                TransactionTypeCode NVARCHAR(20) NULL,
                AccountCode NVARCHAR(200) NULL,
                GLAccountCode NVARCHAR(20) NULL,
                CostItemID INT NULL,
                CalculationID INT NULL,
                CurrencyInpC NVARCHAR(20) NULL,
                UOMCodeInpC NVARCHAR(20) NULL,
                Segment1Code NVARCHAR(20) NULL,
                Segment2Code NVARCHAR(20) NULL,
                Segment3Code NVARCHAR(20) NULL,
                Segment4Code NVARCHAR(20) NULL,
                Segment5Code NVARCHAR(20) NULL,
                Segment6Code NVARCHAR(20) NULL,
                Segment7Code NVARCHAR(20) NULL,
                Segment8Code NVARCHAR(20) NULL,
                Segment9Code NVARCHAR(20) NULL,
                Segment10Code NVARCHAR(20) NULL,
                Segment11Code NVARCHAR(20) NULL,
                Segment12Code NVARCHAR(20) NULL,
                Segment13Code NVARCHAR(20) NULL,
                Segment14Code NVARCHAR(20) NULL,
                Segment15Code NVARCHAR(20) NULL,
                Segment16Code NVARCHAR(20) NULL,
                Segment17Code NVARCHAR(20) NULL,
                Segment18Code NVARCHAR(20) NULL,
                Segment19Code NVARCHAR(20) NULL,
                Segment20Code NVARCHAR(20) NULL,
                BP1 DECIMAL(19,6) NOT NULL,
                BP2 DECIMAL(19,6) NOT NULL,
                BP3 DECIMAL(19,6) NOT NULL,
                BP4 DECIMAL(19,6) NOT NULL,
                BP5 DECIMAL(19,6) NOT NULL,
                BP6 DECIMAL(19,6) NOT NULL,
                BP7 DECIMAL(19,6) NOT NULL,
                BP8 DECIMAL(19,6) NOT NULL,
                BP9 DECIMAL(19,6) NOT NULL,
                BP10 DECIMAL(19,6) NOT NULL,
                BP11 DECIMAL(19,6) NOT NULL,
                BP12 DECIMAL(19,6) NOT NULL,
                BPTotal DECIMAL(19,6) NOT NULL,
                PY5 DECIMAL(19,6) NULL,
                PY4 DECIMAL(19,6) NULL,
                PY3 DECIMAL(19,6) NULL,
                PY2 DECIMAL(19,6) NULL,
                PY1 DECIMAL(19,6) NULL,
                BPOpBal DECIMAL(19,6) NULL,
                BPQ1 DECIMAL(19,6) NULL,
                BPQ2 DECIMAL(19,6) NULL,
                BPQ3 DECIMAL(19,6) NULL,
                BPQ4 DECIMAL(19,6) NULL,
                BPOY1 DECIMAL(19,6) NULL,
                BPOY2 DECIMAL(19,6) NULL,
                BPOY3 DECIMAL(19,6) NULL,
                BPOY4 DECIMAL(19,6) NULL,
                BPOY5 DECIMAL(19,6) NULL,
                BPOY6 DECIMAL(19,6) NULL,
                BPOY7 DECIMAL(19,6) NULL,
                BPOY8 DECIMAL(19,6) NULL,
                BPOY9 DECIMAL(19,6) NULL,
                BPOY10 DECIMAL(19,6) NULL,
                CalculatedDate DATETIME2 NOT NULL
            );

            CREATE TABLE #PeriodStage
            (
                BatchOrdinal INT NOT NULL,
                TransactionID INT NOT NULL,
                PeriodCode NVARCHAR(20) NOT NULL,
                Amount DECIMAL(19,6) NOT NULL,
                CalculatedDate DATETIME2 NOT NULL
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static DataTable BuildTargetTransactionsTable(IReadOnlyList<LegacyBatchTransactionResult> rows)
    {
        var table = new DataTable();
        table.Columns.Add("TransactionID", typeof(int));

        foreach (var row in rows)
        {
            table.Rows.Add(row.Transaction.TransactionId);
        }

        return table;
    }

    private static DataTable BuildLegacyResultStageTable(
        IReadOnlyList<LegacyBatchTransactionResult> rows,
        Guid runId,
        string formulaSetCode,
        int scopeVersion,
        string engineVersion,
        DateTime calculatedAtUtc)
    {
        var table = new DataTable();
        table.Columns.Add("BatchOrdinal", typeof(int));
        table.Columns.Add("TransactionID", typeof(int));
        table.Columns.Add("ResultJson", typeof(string));
        table.Columns.Add("CalculatedDate", typeof(DateTime));
        table.Columns.Add("FormulaVersion", typeof(int));
        table.Columns.Add("RunID", typeof(Guid));
        table.Columns.Add("FormulaSetCode", typeof(string));
        table.Columns.Add("ScopeVersion", typeof(int));
        table.Columns.Add("EngineVersion", typeof(string));
        table.Columns.Add("Status", typeof(string));
        table.Columns.Add("BPTotal", typeof(decimal));
        table.Columns.Add("ErrorMessage", typeof(string));

        for (var index = 0; index < rows.Count; index++)
        {
            var row = rows[index];
            table.Rows.Add(
                index + 1,
                row.Transaction.TransactionId,
                row.Projection.ResultJson,
                calculatedAtUtc,
                1,
                runId,
                formulaSetCode,
                scopeVersion,
                engineVersion,
                row.Status,
                row.Projection.Total,
                (object?)row.ErrorMessage ?? DBNull.Value);
        }

        return table;
    }

    private static DataTable BuildLegacyFlatStageTable(
        IReadOnlyList<LegacyBatchTransactionResult> rows,
        DateTime calculatedAtUtc)
    {
        var table = new DataTable();
        table.Columns.Add("BatchOrdinal", typeof(int));
        table.Columns.Add("TransactionID", typeof(int));
        table.Columns.Add("HeadRecordID", typeof(int));
        table.Columns.Add("RecordTypeCode", typeof(string));
        table.Columns.Add("FiscalYearID", typeof(int));
        table.Columns.Add("VersionID", typeof(int));
        table.Columns.Add("DataObjectCode", typeof(string));
        table.Columns.Add("TransactionTypeCode", typeof(string));
        table.Columns.Add("AccountCode", typeof(string));
        table.Columns.Add("GLAccountCode", typeof(string));
        table.Columns.Add("CostItemID", typeof(int));
        table.Columns.Add("CalculationID", typeof(int));
        table.Columns.Add("CurrencyInpC", typeof(string));
        table.Columns.Add("UOMCodeInpC", typeof(string));
        for (var segmentNumber = 1; segmentNumber <= 20; segmentNumber++)
        {
            table.Columns.Add($"Segment{segmentNumber}Code", typeof(string));
        }
        for (var monthNumber = 1; monthNumber <= 12; monthNumber++)
        {
            table.Columns.Add($"BP{monthNumber}", typeof(decimal));
        }
        table.Columns.Add("BPTotal", typeof(decimal));
        table.Columns.Add("PY5", typeof(decimal));
        table.Columns.Add("PY4", typeof(decimal));
        table.Columns.Add("PY3", typeof(decimal));
        table.Columns.Add("PY2", typeof(decimal));
        table.Columns.Add("PY1", typeof(decimal));
        table.Columns.Add("BPOpBal", typeof(decimal));
        table.Columns.Add("BPQ1", typeof(decimal));
        table.Columns.Add("BPQ2", typeof(decimal));
        table.Columns.Add("BPQ3", typeof(decimal));
        table.Columns.Add("BPQ4", typeof(decimal));
        for (var outYear = 1; outYear <= 10; outYear++)
        {
            table.Columns.Add($"BPOY{outYear}", typeof(decimal));
        }
        table.Columns.Add("CalculatedDate", typeof(DateTime));

        for (var index = 0; index < rows.Count; index++)
        {
            var row = rows[index];
            var values = new List<object?>
            {
                index + 1,
                row.Transaction.TransactionId,
                NullableValue(row.Transaction.Values, "HeadRecordID"),
                NullableValue(row.Transaction.Values, "RecordTypeCode"),
                row.Transaction.FiscalYearId.HasValue ? row.Transaction.FiscalYearId.Value : DBNull.Value,
                row.Transaction.VersionId.HasValue ? row.Transaction.VersionId.Value : DBNull.Value,
                NullableValue(row.Transaction.Values, "DataObjectCode"),
                row.Transaction.TransactionTypeCode ?? (object)DBNull.Value,
                NullableValue(row.Transaction.Values, "AccountCode"),
                NullableValue(row.Transaction.Values, "GLAccountCode"),
                NullableValue(row.Transaction.Values, "CostItemID"),
                row.Transaction.CalculationId.HasValue ? row.Transaction.CalculationId.Value : DBNull.Value,
                NullableValue(row.Transaction.Values, "CurrencyInpC"),
                row.Transaction.UomCodeInpC ?? (object)DBNull.Value,
            };

            for (var segmentNumber = 1; segmentNumber <= 20; segmentNumber++)
            {
                values.Add(NullableValue(row.Transaction.Values, $"Segment{segmentNumber}Code"));
            }

            for (var monthNumber = 1; monthNumber <= 12; monthNumber++)
            {
                values.Add(row.Projection.Monthly[$"BP{monthNumber}"]);
            }

            values.Add(row.Projection.Total);
            values.Add(NullableValue(row.Transaction.Values, "PY5InpN"));
            values.Add(NullableValue(row.Transaction.Values, "PY4InpN"));
            values.Add(NullableValue(row.Transaction.Values, "PY3InpN"));
            values.Add(NullableValue(row.Transaction.Values, "PY2InpN"));
            values.Add(NullableValue(row.Transaction.Values, "PY1InpN"));
            values.Add(NullableValue(row.Transaction.Values, "BPOpBalInpN"));
            values.Add(row.Projection.Quarterly["BPQ1"]);
            values.Add(row.Projection.Quarterly["BPQ2"]);
            values.Add(row.Projection.Quarterly["BPQ3"]);
            values.Add(row.Projection.Quarterly["BPQ4"]);
            for (var outYear = 1; outYear <= 10; outYear++)
            {
                values.Add(row.Projection.OutYears[$"BPOY{outYear}"]);
            }
            values.Add(calculatedAtUtc);

            table.Rows.Add(values.ToArray());
        }

        return table;
    }

    private static DataTable BuildLegacyPeriodStageTable(
        IReadOnlyList<LegacyBatchTransactionResult> rows,
        DateTime calculatedAtUtc)
    {
        var table = new DataTable();
        table.Columns.Add("BatchOrdinal", typeof(int));
        table.Columns.Add("TransactionID", typeof(int));
        table.Columns.Add("PeriodCode", typeof(string));
        table.Columns.Add("Amount", typeof(decimal));
        table.Columns.Add("CalculatedDate", typeof(DateTime));

        for (var index = 0; index < rows.Count; index++)
        {
            foreach (var periodRow in rows[index].Projection.PeriodRows)
            {
                table.Rows.Add(index + 1, rows[index].Transaction.TransactionId, periodRow.PeriodCode, periodRow.Amount, calculatedAtUtc);
            }
        }

        return table;
    }

    private static async Task DeleteLegacyBatchTransactionResultsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            DELETE p
            FROM dbo.tblTransactionResultPeriod p
            INNER JOIN #TargetTransactions t ON t.TransactionID = p.TransactionID;

            DELETE f
            FROM dbo.tblTransactionResultFlat f
            INNER JOIN #TargetTransactions t ON t.TransactionID = f.TransactionID;

            DELETE r
            FROM dbo.tblTransactionResult r
            INNER JOIN #TargetTransactions t ON t.TransactionID = r.TransactionID;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task<IReadOnlyDictionary<int, CeilingCheckContext>> LoadCeilingCheckContextsAsync(
        SqlConnection connection,
        IReadOnlyList<int> transactionIds,
        CancellationToken cancellationToken)
    {
        var contexts = new Dictionary<int, CeilingCheckContext>();
        const int maxSqlParametersPerCommand = 1000;

        for (var offset = 0; offset < transactionIds.Count; offset += maxSqlParametersPerCommand)
        {
            var batch = transactionIds
                .Skip(offset)
                .Take(maxSqlParametersPerCommand)
                .Select((transactionId, index) => new { transactionId, name = $"@transactionId{index}" })
                .ToArray();
            if (batch.Length == 0)
            {
                continue;
            }

            var sql = $"""
                SELECT
                    TransactionID,
                    FiscalYearID,
                    VersionID
                FROM dbo.tblTransactionInput
                WHERE TransactionID IN ({string.Join(", ", batch.Select(x => x.name))});
                """;

            await using var command = new SqlCommand(sql, connection);
            foreach (var parameter in batch)
            {
                command.Parameters.AddWithValue(parameter.name, parameter.transactionId);
            }

            await using var reader = await command.ExecuteReaderAsync(cancellationToken);
            while (await reader.ReadAsync(cancellationToken))
            {
                if (reader.IsDBNull(0) || reader.IsDBNull(1) || reader.IsDBNull(2))
                {
                    continue;
                }

                var transactionId = reader.GetInt32(0);
                contexts[transactionId] = new CeilingCheckContext(
                    transactionId,
                    reader.GetInt32(1),
                    reader.GetInt32(2));
            }
        }

        return contexts;
    }

    private static async Task<CeilingCheckResult> ExecuteCeilingCheckAsync(
        SqlConnection connection,
        CeilingCheckContext context,
        string checkMode,
        CancellationToken cancellationToken)
    {
        const string sql = """
            DECLARE @CeilingStatusCheck nvarchar(50), @ErrorMessage nvarchar(500);
            EXEC dbo.spCheckCeilingBalance
                @FiscalYearID = @fiscalYearId,
                @VersionID = @versionId,
                @TransactionID = @transactionId,
                @UpdatedBy = @updatedBy,
                @CheckMode = @checkMode,
                @EnforcePeriod = @enforcePeriod,
                @CeilingStatusCheck = @CeilingStatusCheck OUTPUT,
                @ErrorMessage = @ErrorMessage OUTPUT;
            SELECT @CeilingStatusCheck AS CeilingStatusCheck, @ErrorMessage AS ErrorMessage;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@fiscalYearId", context.FiscalYearId);
        command.Parameters.AddWithValue("@versionId", context.VersionId);
        command.Parameters.AddWithValue("@transactionId", context.TransactionId);
        command.Parameters.AddWithValue("@updatedBy", 1);
        command.Parameters.AddWithValue("@checkMode", checkMode);
        command.Parameters.AddWithValue("@enforcePeriod", 1);

        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return new CeilingCheckResult(false, "NO STATUS", "No status returned from spCheckCeilingBalance.");
        }

        var statusCode = reader.IsDBNull(0) ? "NO STATUS" : reader.GetString(0);
        var errorMessage = reader.IsDBNull(1) ? null : reader.GetString(1);
        var isSuccess = string.Equals(statusCode, "CEILING OK", StringComparison.OrdinalIgnoreCase);
        return new CeilingCheckResult(isSuccess, statusCode, errorMessage);
    }

    private static async Task PersistTransactionCeilingStatusAsync(
        SqlConnection connection,
        int transactionId,
        CeilingCheckResult result,
        CancellationToken cancellationToken)
    {
        const string sql = """
            UPDATE dbo.tblTransactionInput
            SET
                CeilingStatus = @ceilingStatus,
                CeilingStatusCheck = @ceilingStatusCheck,
                CeilingErrorMessage = @ceilingErrorMessage,
                CeilingFailedFlag = @ceilingFailedFlag,
                CeilingEngine = @ceilingEngine,
                CeilingLastCheckedDate = GETDATE(),
                CeilingCheckDate = GETDATE()
            WHERE TransactionID = @transactionId;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@ceilingStatus", result.IsSuccess ? "OK" : "FAILED");
        command.Parameters.AddWithValue("@ceilingStatusCheck", Truncate(result.StatusCode, 50));
        command.Parameters.AddWithValue("@ceilingErrorMessage", (object?)Truncate(result.ErrorMessage, 500) ?? DBNull.Value);
        command.Parameters.AddWithValue("@ceilingFailedFlag", result.IsSuccess ? 0 : 1);
        command.Parameters.AddWithValue("@ceilingEngine", "sproc");
        command.Parameters.AddWithValue("@transactionId", transactionId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task PersistHeadRecordCeilingStatusAsync(
        SqlConnection connection,
        int headTransactionId,
        CeilingCheckResult result,
        CancellationToken cancellationToken)
    {
        const string sql = """
            UPDATE dbo.tblTransactionInput
            SET
                CeilingStatus = @ceilingStatus,
                CeilingStatusCheck = @ceilingStatusCheck,
                CeilingErrorMessage = @ceilingErrorMessage,
                CeilingFailedFlag = @ceilingFailedFlag,
                CeilingEngine = @ceilingEngine,
                CeilingLastCheckedDate = GETDATE(),
                CeilingCheckDate = GETDATE()
            WHERE TransactionID = @transactionId
               OR HeadRecordID = @transactionId;
            """;

        await using var command = new SqlCommand(sql, connection);
        command.Parameters.AddWithValue("@ceilingStatus", result.IsSuccess ? "OK" : "FAILED");
        command.Parameters.AddWithValue("@ceilingStatusCheck", Truncate(result.StatusCode, 50));
        command.Parameters.AddWithValue("@ceilingErrorMessage", (object?)Truncate(result.ErrorMessage, 500) ?? DBNull.Value);
        command.Parameters.AddWithValue("@ceilingFailedFlag", result.IsSuccess ? 0 : 1);
        command.Parameters.AddWithValue("@ceilingEngine", "sproc");
        command.Parameters.AddWithValue("@transactionId", headTransactionId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static string? Truncate(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return value;
        }

        return value.Length <= maxLength
            ? value
            : value[..maxLength];
    }

    private static async Task InsertLegacyBatchHeadersAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResult
            (
                TransactionID,
                ResultJSON,
                CalculatedDate,
                FormulaVersion,
                RunID,
                FormulaSetCode,
                ScopeVersion,
                EngineVersion,
                Status,
                DurationMs,
                BPTotal,
                ErrorMessage
            )
            SELECT
                src.TransactionID,
                src.ResultJson,
                src.CalculatedDate,
                src.FormulaVersion,
                src.RunID,
                src.FormulaSetCode,
                src.ScopeVersion,
                src.EngineVersion,
                src.Status,
                NULL,
                src.BPTotal,
                src.ErrorMessage
            FROM #ResultStage src
            ORDER BY src.BatchOrdinal;

            INSERT INTO #ResultMap (BatchOrdinal, TransactionResultID)
            SELECT
                src.BatchOrdinal,
                tr.TransactionResultID
            FROM #ResultStage src
            INNER JOIN dbo.tblTransactionResult tr
                ON tr.TransactionID = src.TransactionID
            WHERE tr.RunID = src.RunID;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task InsertLegacyBatchFlatsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResultFlat
            (
                TransactionResultID,
                TransactionID,
                HeadRecordID,
                RecordTypeCode,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                TransactionTypeCode,
                AccountCode,
                GLAccountCode,
                CostItemID,
                CalculationID,
                CurrencyInpC,
                UOMCodeInpC,
                Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
                Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
                Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
                Segment16Code, Segment17Code, Segment18Code, Segment19Code, Segment20Code,
                BP1, BP2, BP3, BP4, BP5, BP6,
                BP7, BP8, BP9, BP10, BP11, BP12,
                BPTotal,
                PY5, PY4, PY3, PY2, PY1,
                BPOpBal,
                BPQ1, BPQ2, BPQ3, BPQ4,
                BPOY1, BPOY2, BPOY3, BPOY4, BPOY5,
                BPOY6, BPOY7, BPOY8, BPOY9, BPOY10,
                CalculatedDate
            )
            SELECT
                rm.TransactionResultID,
                fs.TransactionID,
                fs.HeadRecordID,
                fs.RecordTypeCode,
                fs.FiscalYearID,
                fs.VersionID,
                fs.DataObjectCode,
                fs.TransactionTypeCode,
                fs.AccountCode,
                fs.GLAccountCode,
                fs.CostItemID,
                fs.CalculationID,
                fs.CurrencyInpC,
                fs.UOMCodeInpC,
                fs.Segment1Code, fs.Segment2Code, fs.Segment3Code, fs.Segment4Code, fs.Segment5Code,
                fs.Segment6Code, fs.Segment7Code, fs.Segment8Code, fs.Segment9Code, fs.Segment10Code,
                fs.Segment11Code, fs.Segment12Code, fs.Segment13Code, fs.Segment14Code, fs.Segment15Code,
                fs.Segment16Code, fs.Segment17Code, fs.Segment18Code, fs.Segment19Code, fs.Segment20Code,
                fs.BP1, fs.BP2, fs.BP3, fs.BP4, fs.BP5, fs.BP6,
                fs.BP7, fs.BP8, fs.BP9, fs.BP10, fs.BP11, fs.BP12,
                fs.BPTotal,
                fs.PY5, fs.PY4, fs.PY3, fs.PY2, fs.PY1,
                fs.BPOpBal,
                fs.BPQ1, fs.BPQ2, fs.BPQ3, fs.BPQ4,
                fs.BPOY1, fs.BPOY2, fs.BPOY3, fs.BPOY4, fs.BPOY5,
                fs.BPOY6, fs.BPOY7, fs.BPOY8, fs.BPOY9, fs.BPOY10,
                fs.CalculatedDate
            FROM #FlatStage fs
            INNER JOIN #ResultMap rm ON rm.BatchOrdinal = fs.BatchOrdinal
            ORDER BY fs.BatchOrdinal;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task InsertLegacyBatchPeriodsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblTransactionResultPeriod
            (
                TransactionResultID,
                TransactionID,
                PeriodCode,
                Amount,
                CalculatedDate
            )
            SELECT
                rm.TransactionResultID,
                ps.TransactionID,
                ps.PeriodCode,
                ps.Amount,
                ps.CalculatedDate
            FROM #PeriodStage ps
            INNER JOIN #ResultMap rm ON rm.BatchOrdinal = ps.BatchOrdinal
            ORDER BY ps.BatchOrdinal, ps.PeriodCode;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task BulkCopyAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        string destinationTableName,
        DataTable table,
        CancellationToken cancellationToken)
    {
        if (table.Rows.Count == 0)
        {
            return;
        }

        using var bulk = new SqlBulkCopy(connection, SqlBulkCopyOptions.Default, transaction)
        {
            DestinationTableName = destinationTableName,
            BatchSize = Math.Min(5000, table.Rows.Count),
            BulkCopyTimeout = 0,
        };

        foreach (DataColumn column in table.Columns)
        {
            bulk.ColumnMappings.Add(column.ColumnName, column.ColumnName);
        }

        await bulk.WriteToServerAsync(table, cancellationToken);
    }

    private static object NullableValue(IReadOnlyDictionary<string, object?> values, string key)
        => values.TryGetValue(key, out var raw) && raw is not null && raw is not DBNull
            ? raw
            : DBNull.Value;

    private static object DbNullableString(IReadOnlyDictionary<string, object?> values, string key)
        => values.TryGetValue(key, out var raw) && raw is not null && raw is not DBNull
            ? (object)(raw.ToString() ?? string.Empty)
            : DBNull.Value;

    private static object DbNullableInt(IReadOnlyDictionary<string, object?> values, string key)
        => values.TryGetValue(key, out var raw) && raw is not null && raw is not DBNull
            ? Convert.ToInt32(raw)
            : DBNull.Value;

    private static object DbNullableDecimal(IReadOnlyDictionary<string, object?> values, string key)
        => values.TryGetValue(key, out var raw) && raw is not null && raw is not DBNull
            ? Convert.ToDecimal(raw)
            : DBNull.Value;

    private static async Task<long> InsertRunAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        ScenarioSelection selection,
        DateTime startedAtUtc,
        string engineVersion,
        ExecutionPersistenceOptions options,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblCalcRun
            (
                CalcModelID,
                ScenarioID,
                RunTypeCode,
                RunStatusCode,
                TriggerSourceCode,
                EngineVersion,
                TriggeredBy,
                StartedDate,
                RowCountProcessed,
                ErrorCount,
                Notes
            )
            OUTPUT INSERTED.CalcRunID
            VALUES
            (
                @calcModelId,
                @scenarioId,
                @runTypeCode,
                @runStatusCode,
                @triggerSourceCode,
                @engineVersion,
                @triggeredBy,
                @startedDate,
                0,
                0,
                @notes
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@calcModelId", selection.Bundle.Model.CalcModelId);
        command.Parameters.AddWithValue("@scenarioId", selection.Scenario.ScenarioId);
        command.Parameters.AddWithValue("@runTypeCode", options.RunTypeCode);
        command.Parameters.AddWithValue("@runStatusCode", "RUNNING");
        command.Parameters.AddWithValue("@triggerSourceCode", options.TriggerSourceCode);
        command.Parameters.AddWithValue("@engineVersion", engineVersion);
        command.Parameters.AddWithValue("@triggeredBy", 1);
        command.Parameters.AddWithValue("@startedDate", startedAtUtc);
        command.Parameters.AddWithValue("@notes", options.Notes);

        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return Convert.ToInt64(raw);
    }

    private static async Task CompleteRunAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long runId,
        string statusCode,
        DateTime completedAtUtc,
        int rowCountProcessed,
        int errorCount,
        CancellationToken cancellationToken)
    {
        const string sql = """
            UPDATE dbo.tblCalcRun
            SET
                RunStatusCode = @runStatusCode,
                CompletedDate = @completedDate,
                RowCountProcessed = @rowCountProcessed,
                ErrorCount = @errorCount
            WHERE CalcRunID = @calcRunId;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@runStatusCode", statusCode);
        command.Parameters.AddWithValue("@completedDate", completedAtUtc);
        command.Parameters.AddWithValue("@rowCountProcessed", rowCountProcessed);
        command.Parameters.AddWithValue("@errorCount", errorCount);
        command.Parameters.AddWithValue("@calcRunId", runId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task<PublishableRun?> LoadPublishableRunAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long calcRunId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            SELECT
                r.CalcRunID,
                r.CalcModelID,
                m.ModelCode,
                r.ScenarioID,
                s.ScenarioCode,
                r.RunStatusCode,
                COUNT(rr.CalcRunResultID) AS ResultRowCount
            FROM dbo.tblCalcRun r
            INNER JOIN dbo.tblCalcModel m ON m.CalcModelID = r.CalcModelID
            INNER JOIN dbo.tblCalcScenario s ON s.ScenarioID = r.ScenarioID
            LEFT JOIN dbo.tblCalcRunResult rr ON rr.CalcRunID = r.CalcRunID
            WHERE r.CalcRunID = @calcRunId
            GROUP BY
                r.CalcRunID,
                r.CalcModelID,
                m.ModelCode,
                r.ScenarioID,
                s.ScenarioCode,
                r.RunStatusCode;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@calcRunId", calcRunId);
        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        return new PublishableRun(
            reader.GetInt64(0),
            reader.GetInt32(1),
            reader.GetString(2),
            reader.GetInt32(3),
            reader.GetString(4),
            reader.GetString(5),
            reader.GetInt32(6));
    }

    private static async Task<PublishableRun?> LoadLatestPublishableRunAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        string modelCode,
        string? scenarioCode,
        CancellationToken cancellationToken)
    {
        var sql = """
            SELECT TOP (1)
                r.CalcRunID,
                r.CalcModelID,
                m.ModelCode,
                r.ScenarioID,
                s.ScenarioCode,
                r.RunStatusCode,
                (SELECT COUNT(*) FROM dbo.tblCalcRunResult rr WHERE rr.CalcRunID = r.CalcRunID) AS ResultRowCount
            FROM dbo.tblCalcRun r
            INNER JOIN dbo.tblCalcModel m ON m.CalcModelID = r.CalcModelID
            INNER JOIN dbo.tblCalcScenario s ON s.ScenarioID = r.ScenarioID
            WHERE m.ModelCode = @modelCode
              AND r.RunStatusCode IN (N'COMPLETED', N'COMPLETED_WARN')
            """;

        if (!string.IsNullOrWhiteSpace(scenarioCode))
        {
            sql += Environment.NewLine + "  AND s.ScenarioCode = @scenarioCode";
        }

        sql += Environment.NewLine + """
            ORDER BY
                r.CompletedDate DESC,
                r.CalcRunID DESC;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@modelCode", modelCode);
        if (!string.IsNullOrWhiteSpace(scenarioCode))
        {
            command.Parameters.AddWithValue("@scenarioCode", scenarioCode);
        }

        await using var reader = await command.ExecuteReaderAsync(cancellationToken);
        if (!await reader.ReadAsync(cancellationToken))
        {
            return null;
        }

        return new PublishableRun(
            reader.GetInt64(0),
            reader.GetInt32(1),
            reader.GetString(2),
            reader.GetInt32(3),
            reader.GetString(4),
            reader.GetString(5),
            reader.GetInt32(6));
    }

    internal static void EnsurePublishable(PublishableRun? run)
    {
        if (run is null)
        {
            throw new InvalidOperationException("No publishable run was found.");
        }

        if (!string.Equals(run.RunStatusCode, "COMPLETED", StringComparison.OrdinalIgnoreCase) &&
            !string.Equals(run.RunStatusCode, "COMPLETED_WARN", StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException(
                $"Run {run.CalcRunId} is not publishable because its status is '{run.RunStatusCode}'.");
        }

        if (run.ResultRowCount <= 0)
        {
            throw new InvalidOperationException(
                $"Run {run.CalcRunId} is not publishable because it has no result rows.");
        }
    }

    private static async Task<long> InsertPublishEventAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        PublishableRun run,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblCalcPublishEvent
            (
                CalcRunID,
                CalcModelID,
                ScenarioID,
                PublishStatusCode,
                PublishedBy,
                PublishedDate,
                PublishedRowCount,
                Notes
            )
            OUTPUT INSERTED.CalcPublishEventID
            VALUES
            (
                @calcRunId,
                @calcModelId,
                @scenarioId,
                N'PUBLISHED',
                1,
                SYSDATETIME(),
                0,
                @notes
            );
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@calcRunId", run.CalcRunId);
        command.Parameters.AddWithValue("@calcModelId", run.CalcModelId);
        command.Parameters.AddWithValue("@scenarioId", run.ScenarioId);
        command.Parameters.AddWithValue("@notes", $"Published results from run {run.CalcRunId} for {run.ModelCode}/{run.ScenarioCode}");

        var raw = await command.ExecuteScalarAsync(cancellationToken);
        return Convert.ToInt64(raw);
    }

    private static async Task DeletePublishedResultsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        int calcModelId,
        int scenarioId,
        CancellationToken cancellationToken)
    {
        const string sql = """
            DELETE FROM dbo.tblCalcPublishedResult
            WHERE CalcModelID = @calcModelId
              AND ScenarioID = @scenarioId;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@calcModelId", calcModelId);
        command.Parameters.AddWithValue("@scenarioId", scenarioId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task<int> InsertPublishedResultsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long calcPublishEventId,
        PublishableRun run,
        CancellationToken cancellationToken)
    {
        const string sql = """
            INSERT INTO dbo.tblCalcPublishedResult
            (
                CalcPublishEventID,
                SourceCalcRunID,
                CalcModelID,
                ScenarioID,
                CostObjectID,
                PeriodID,
                NodeID,
                ValueDecimal,
                ValueText,
                ValueBit,
                PublishedDate
            )
            SELECT
                @calcPublishEventId,
                rr.CalcRunID,
                @calcModelId,
                rr.ScenarioID,
                rr.CostObjectID,
                rr.PeriodID,
                rr.NodeID,
                rr.ValueDecimal,
                rr.ValueText,
                rr.ValueBit,
                SYSDATETIME()
            FROM dbo.tblCalcRunResult rr
            WHERE rr.CalcRunID = @calcRunId;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@calcPublishEventId", calcPublishEventId);
        command.Parameters.AddWithValue("@calcModelId", run.CalcModelId);
        command.Parameters.AddWithValue("@calcRunId", run.CalcRunId);

        return await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task CompletePublishEventAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long calcPublishEventId,
        int publishedRowCount,
        CancellationToken cancellationToken)
    {
        const string sql = """
            UPDATE dbo.tblCalcPublishEvent
            SET
                PublishedRowCount = @publishedRowCount
            WHERE CalcPublishEventID = @calcPublishEventId;
            """;

        await using var command = new SqlCommand(sql, connection, transaction);
        command.Parameters.AddWithValue("@publishedRowCount", publishedRowCount);
        command.Parameters.AddWithValue("@calcPublishEventId", calcPublishEventId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static async Task BulkInsertRunResultsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long runId,
        ScenarioSelection selection,
        ExecutionReport report,
        DateTime calculatedAtUtc,
        CancellationToken cancellationToken)
    {
        var table = new DataTable();
        table.Columns.Add("CalcRunID", typeof(long));
        table.Columns.Add("ScenarioID", typeof(int));
        table.Columns.Add("CostObjectID", typeof(int));
        table.Columns.Add("PeriodID", typeof(int));
        table.Columns.Add("NodeID", typeof(int));
        table.Columns.Add("ValueDecimal", typeof(decimal));
        table.Columns.Add("CalculationStatusCode", typeof(string));
        table.Columns.Add("CalculatedDate", typeof(DateTime));

        foreach (var row in report.Results)
        {
            table.Rows.Add(
                runId,
                selection.Scenario.ScenarioId,
                row.CostObjectId,
                row.PeriodId,
                row.NodeId,
                row.Value,
                "OK",
                calculatedAtUtc);
        }

        using var bulk = new SqlBulkCopy(connection, SqlBulkCopyOptions.Default, transaction)
        {
            DestinationTableName = "dbo.tblCalcRunResult",
            BulkCopyTimeout = 60,
        };

        bulk.ColumnMappings.Add("CalcRunID", "CalcRunID");
        bulk.ColumnMappings.Add("ScenarioID", "ScenarioID");
        bulk.ColumnMappings.Add("CostObjectID", "CostObjectID");
        bulk.ColumnMappings.Add("PeriodID", "PeriodID");
        bulk.ColumnMappings.Add("NodeID", "NodeID");
        bulk.ColumnMappings.Add("ValueDecimal", "ValueDecimal");
        bulk.ColumnMappings.Add("CalculationStatusCode", "CalculationStatusCode");
        bulk.ColumnMappings.Add("CalculatedDate", "CalculatedDate");

        await bulk.WriteToServerAsync(table, cancellationToken);
    }

    private static async Task BulkInsertRunErrorsAsync(
        SqlConnection connection,
        SqlTransaction transaction,
        long runId,
        ScenarioSelection selection,
        ExecutionReport report,
        DateTime createdAtUtc,
        CancellationToken cancellationToken)
    {
        var table = new DataTable();
        table.Columns.Add("CalcRunID", typeof(long));
        table.Columns.Add("ScenarioID", typeof(int));
        table.Columns.Add("CostObjectID", typeof(int));
        table.Columns.Add("PeriodID", typeof(int));
        table.Columns.Add("NodeID", typeof(int));
        table.Columns.Add("ErrorCode", typeof(string));
        table.Columns.Add("ErrorSeverityCode", typeof(string));
        table.Columns.Add("ErrorMessage", typeof(string));
        table.Columns.Add("ExpressionText", typeof(string));
        table.Columns.Add("ContextJson", typeof(string));
        table.Columns.Add("CreatedDate", typeof(DateTime));

        foreach (var issue in report.Issues)
        {
            table.Rows.Add(
                runId,
                selection.Scenario.ScenarioId,
                DBNull.Value,
                DBNull.Value,
                DBNull.Value,
                "EXECUTION_ISSUE",
                "WARN",
                issue,
                DBNull.Value,
                DBNull.Value,
                createdAtUtc);
        }

        using var bulk = new SqlBulkCopy(connection, SqlBulkCopyOptions.Default, transaction)
        {
            DestinationTableName = "dbo.tblCalcRunError",
            BulkCopyTimeout = 60,
        };

        bulk.ColumnMappings.Add("CalcRunID", "CalcRunID");
        bulk.ColumnMappings.Add("ScenarioID", "ScenarioID");
        bulk.ColumnMappings.Add("CostObjectID", "CostObjectID");
        bulk.ColumnMappings.Add("PeriodID", "PeriodID");
        bulk.ColumnMappings.Add("NodeID", "NodeID");
        bulk.ColumnMappings.Add("ErrorCode", "ErrorCode");
        bulk.ColumnMappings.Add("ErrorSeverityCode", "ErrorSeverityCode");
        bulk.ColumnMappings.Add("ErrorMessage", "ErrorMessage");
        bulk.ColumnMappings.Add("ExpressionText", "ExpressionText");
        bulk.ColumnMappings.Add("ContextJson", "ContextJson");
        bulk.ColumnMappings.Add("CreatedDate", "CreatedDate");

        await bulk.WriteToServerAsync(table, cancellationToken);
    }
}

internal sealed record PersistedRunSummary(
    long CalcRunId,
    string StatusCode,
    int ResultRowCount,
    int ErrorCount);

internal sealed record ExecutionPersistenceOptions(
    string RunTypeCode,
    string TriggerSourceCode,
    string Notes)
{
    public static ExecutionPersistenceOptions ForModel(ScenarioSelection selection)
        => new(
            "FULL",
            "RUNNER",
            $"Scenario engine execution for {selection.Bundle.Model.ModelCode}/{selection.Scenario.ScenarioCode}");

    public static ExecutionPersistenceOptions ForTransaction(TransactionExecutionSelection selection)
        => new(
            "SINGLE_TXN",
            "TRANSACTION",
            $"Scenario engine execution for transaction {selection.Transaction.TransactionId} using {selection.Selection.Bundle.Model.ModelCode}/{selection.Selection.Scenario.ScenarioCode}");
}

internal sealed record PublishableRun(
    long CalcRunId,
    int CalcModelId,
    string ModelCode,
    int ScenarioId,
    string ScenarioCode,
    string RunStatusCode,
    int ResultRowCount);

internal sealed record PublishedRunSummary(
    long CalcPublishEventId,
    long SourceCalcRunId,
    int CalcModelId,
    string ModelCode,
    int ScenarioId,
    string ScenarioCode,
    int PublishedRowCount);

internal sealed record TransactionBatchBenchmarkPreparation(
    int CalculationId,
    IReadOnlyList<TransactionInputRecord> Transactions,
    CalcTransactionBridge? Bridge,
    ScenarioSelection? BaseSelection,
    IReadOnlyList<CalcTransactionNodeMap> NodeMaps,
    IReadOnlyList<int> UnresolvedTransactionIds,
    IReadOnlyList<int> DistinctBridgeIds,
    IReadOnlyList<string>? UnsupportedSourceTypes = null,
    RateLookupCache? RateLookupCache = null);

internal sealed record TransactionBridgeResolution(
    TransactionInputRecord Transaction,
    CalcTransactionBridge Bridge);

internal sealed record LegacyBatchTransactionResult(
    TransactionInputRecord Transaction,
    LegacyTransactionResultProjection Projection,
    string Status,
    string? ErrorMessage);

internal sealed record CeilingValidationBatchSummary(
    int CheckedCount,
    int SuccessCount,
    int FailedCount,
    IReadOnlyList<string> SampleMessages);

internal sealed record CeilingCheckContext(
    int TransactionId,
    int FiscalYearId,
    int VersionId);

internal sealed record CeilingCheckResult(
    bool IsSuccess,
    string StatusCode,
    string? ErrorMessage);

internal sealed record RateLookupCacheKey(
    int FiscalYearId,
    int VersionId,
    string DataObjectCode,
    string RateCode);

internal sealed class RateLookupCache
{
    private readonly IReadOnlyDictionary<RateLookupCacheKey, Dictionary<string, decimal?>> _rows;

    public RateLookupCache(IReadOnlyDictionary<RateLookupCacheKey, Dictionary<string, decimal?>> rows)
    {
        _rows = rows;
    }

    public bool TryGetValue(
        int fiscalYearId,
        int versionId,
        string dataObjectCode,
        string rateCode,
        string rateColumn,
        out decimal? value)
    {
        if (_rows.TryGetValue(new RateLookupCacheKey(fiscalYearId, versionId, dataObjectCode, rateCode), out var row) &&
            row.TryGetValue(rateColumn, out value))
        {
            return true;
        }

        value = null;
        return false;
    }
}
