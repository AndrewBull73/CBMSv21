using CBMS.ScenarioEngine.Runner.Infrastructure;
using System.Diagnostics;

var cancellationToken = CancellationToken.None;
var baseDirectory = AppContext.BaseDirectory;
var settings = RunnerConfiguration.Load(baseDirectory);
var connectionString = ConnectionStringFactory.Build(settings.Database);
var repository = new ScenarioMetadataRepository(connectionString);
var executionEngine = new ScenarioExecutionEngine();

var command = args.Length > 0 ? args[0].Trim().ToLowerInvariant() : "probe";
var commandArgs = args.Skip(1).ToArray();

try
{
    switch (command)
    {
        case "probe":
            await RunProbeAsync(repository, cancellationToken);
            break;

        case "list-models":
            await RunListModelsAsync(repository, cancellationToken);
            break;

        case "inspect-model":
            await RunInspectModelAsync(repository, commandArgs, cancellationToken);
            break;

        case "execute-model":
            await RunExecuteModelAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "execute-transaction":
            await RunExecuteTransactionAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "execute-transaction-batch":
            await RunExecuteTransactionBatchAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "benchmark-transaction-batch":
            await RunBenchmarkTransactionBatchAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "benchmark-transaction-chain-batch":
            await RunBenchmarkTransactionChainBatchAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "execute-transaction-batch-bulk":
            await RunExecuteTransactionBatchBulkAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "execute-transaction-chain-batch-bulk":
            await RunExecuteTransactionChainBatchBulkAsync(repository, executionEngine, commandArgs, cancellationToken);
            break;

        case "publish-run":
            await RunPublishRunAsync(repository, commandArgs, cancellationToken);
            break;

        case "publish-latest":
            await RunPublishLatestRunAsync(repository, commandArgs, cancellationToken);
            break;

        default:
            PrintUsage();
            Environment.ExitCode = 1;
            break;
    }
}
catch (Exception ex)
{
    Console.Error.WriteLine("Scenario engine runner failed.");
    Console.Error.WriteLine(ex.Message);
    Environment.ExitCode = 1;
}

static async Task RunProbeAsync(
    ScenarioMetadataRepository repository,
    CancellationToken cancellationToken)
{
    var probe = await repository.ProbeSchemaAsync(cancellationToken);

    Console.WriteLine($"Schema ready: {(probe.IsReady ? "yes" : "no")}");
    Console.WriteLine($"Present tables: {probe.PresentTables.Count}");

    foreach (var table in probe.PresentTables)
    {
        Console.WriteLine($"  OK  {table}");
    }

    foreach (var table in probe.MissingTables)
    {
        Console.WriteLine($"  MISSING  {table}");
    }
}

static async Task RunListModelsAsync(
    ScenarioMetadataRepository repository,
    CancellationToken cancellationToken)
{
    var models = await repository.GetModelsAsync(cancellationToken);

    if (models.Count == 0)
    {
        Console.WriteLine("No models found in tblCalcModel.");
        return;
    }

    Console.WriteLine("Available models:");
    foreach (var model in models)
    {
        Console.WriteLine(
            $"  {model.ModelCode} | ID={model.CalcModelId} | Version={model.ModelVersion} | Status={model.StatusCode} | Active={model.ActiveFlag}");
    }
}

static async Task RunInspectModelAsync(
    ScenarioMetadataRepository repository,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0)
    {
        Console.WriteLine("inspect-model requires a model code.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- inspect-model SAMPLE");
        return;
    }

    var modelCode = commandArgs[0];
    var bundle = await repository.LoadBundleAsync(modelCode, cancellationToken);

    if (bundle is null)
    {
        Console.WriteLine($"Model not found: {modelCode}");
        return;
    }

    Console.WriteLine($"Model: {bundle.Model.ModelCode} | ID={bundle.Model.CalcModelId} | Version={bundle.Model.ModelVersion}");
    Console.WriteLine($"Scenarios: {bundle.Scenarios.Count}");
    Console.WriteLine($"Periods: {bundle.Periods.Count}");
    Console.WriteLine($"Cost objects: {bundle.CostObjects.Count}");
    Console.WriteLine($"Nodes: {bundle.Nodes.Count}");
    Console.WriteLine($"Formulas: {bundle.Formulas.Count}");
    Console.WriteLine($"Dependencies: {bundle.Dependencies.Count}");

    var report = ScenarioModelValidator.Validate(bundle);
    if (report.Issues.Count == 0)
    {
        Console.WriteLine("Validation: no obvious structural issues found.");
    }
    else
    {
        Console.WriteLine("Validation issues:");
        foreach (var issue in report.Issues)
        {
            Console.WriteLine($"  - [{issue.SeverityCode}] {issue.Message}");
        }
    }
}

static async Task RunExecuteModelAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0)
    {
        Console.WriteLine("execute-model requires a model code.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-model SCENARIO_V1_DEMO BASE");
        return;
    }

    var modelCode = commandArgs[0];
    var scenarioCode = commandArgs.Count > 1 ? commandArgs[1] : null;
    var selection = await repository.LoadScenarioSelectionAsync(modelCode, scenarioCode, cancellationToken);

    if (selection is null)
    {
        Console.WriteLine($"Model/scenario not found: {modelCode} {scenarioCode}".Trim());
        return;
    }

    Console.WriteLine($"Executing model: {selection.Bundle.Model.ModelCode}");
    Console.WriteLine($"Scenario: {selection.Scenario.ScenarioCode}");
    Console.WriteLine($"Input values: {selection.InputValues.Count}");

    var validation = ScenarioModelValidator.Validate(selection.Bundle);
    if (validation.Issues.Count > 0)
    {
        Console.WriteLine("Validation issues:");
        foreach (var issue in validation.Issues)
        {
            Console.WriteLine($"  - [{issue.SeverityCode}] {issue.Message}");
        }

        if (validation.HasErrors)
        {
            Console.WriteLine("Execution aborted because the model has validation errors.");
            return;
        }
    }

    var report = executionEngine.Execute(selection);
    var persistedRun = await repository.PersistExecutionAsync(selection, report, cancellationToken);

    Console.WriteLine("Execution order:");
    foreach (var nodeCode in report.ExecutionOrder)
    {
        Console.WriteLine($"  {nodeCode}");
    }

    Console.WriteLine("Calculated values:");
    foreach (var group in report.Results
                 .OrderBy(x => x.CostObjectCode, StringComparer.OrdinalIgnoreCase)
                 .ThenBy(x => x.PeriodCode, StringComparer.OrdinalIgnoreCase)
                 .GroupBy(x => new { x.CostObjectCode, x.PeriodCode }))
    {
        Console.WriteLine($"  {group.Key.CostObjectCode} | {group.Key.PeriodCode}");
        foreach (var row in group.OrderBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase))
        {
            Console.WriteLine($"    {row.NodeCode} = {row.Value:F6}");
        }
    }

    if (report.Issues.Count == 0)
    {
        Console.WriteLine("Execution completed with no issues.");
    }
    else
    {
        Console.WriteLine("Execution issues:");
        foreach (var issue in report.Issues)
        {
            Console.WriteLine($"  - {issue}");
        }
    }

    Console.WriteLine(
        $"Persisted run: ID={persistedRun.CalcRunId} | Status={persistedRun.StatusCode} | Results={persistedRun.ResultRowCount} | Errors={persistedRun.ErrorCount}");
}

static async Task RunExecuteTransactionAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var transactionId))
    {
        Console.WriteLine("execute-transaction requires a numeric TransactionID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction 68068");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction 68068 SCENARIO_V1_DEMO BASE");
        return;
    }

    var modelCode = commandArgs.Count > 1 ? commandArgs[1] : null;
    var scenarioCode = commandArgs.Count > 2 ? commandArgs[2] : null;
    var summary = await ExecuteTransactionWorkflowAsync(
        repository,
        executionEngine,
        transactionId,
        modelCode,
        scenarioCode,
        cancellationToken,
        new TransactionExecutionConsoleOptions(
            Verbose: true,
            ShowOverrides: true,
            ShowResults: true));

    if (summary is null)
    {
        Console.WriteLine($"No transaction bridge was found for TransactionID {transactionId}.");
        return;
    }
}

static async Task RunExecuteTransactionBatchAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var calculationId))
    {
        Console.WriteLine("execute-transaction-batch requires a numeric CalculationID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch 1");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch 1 100");
        return;
    }

    var limit = commandArgs.Count > 1 && int.TryParse(commandArgs[1], out var parsedLimit) && parsedLimit > 0
        ? parsedLimit
        : (int?)null;
    var progressEvery = commandArgs
        .Select(ParseProgressEvery)
        .FirstOrDefault(x => x.HasValue);
    var ceilingMode = ParseCeilingMode(commandArgs);
    var fiscalYearId = ParseIntOption(commandArgs, "--fy=");
    var versionId = ParseIntOption(commandArgs, "--version=");
    var dataObjectCodes = ParseFilterList(commandArgs, "--dataobjects=");
    var transactionTypeCodes = ParseFilterList(commandArgs, "--transactiontypes=");
    var transactionIds = await repository.GetActiveTransactionIdsByCalculationAsync(
        calculationId,
        limit,
        fiscalYearId,
        versionId,
        dataObjectCodes,
        transactionTypeCodes,
        cancellationToken);

    if (transactionIds.Count == 0)
    {
        Console.WriteLine($"No active root transactions were found for CalculationID {calculationId}.");
        return;
    }

    Console.WriteLine(
        $"Executing batch for CalculationID {calculationId}. Transactions: {transactionIds.Count}{(limit.HasValue ? $" (limit {limit.Value})" : string.Empty)}");

    var successCount = 0;
    var warningCount = 0;
    var failureCount = 0;
    var totalLegacyPersists = 0;
    var ceilingChecked = 0;
    var ceilingPassed = 0;
    var ceilingFailed = 0;

    for (var index = 0; index < transactionIds.Count; index++)
    {
        var transactionId = transactionIds[index];

        try
        {
            var summary = await ExecuteTransactionWorkflowAsync(
                repository,
                executionEngine,
                transactionId,
                null,
                null,
                cancellationToken,
                new TransactionExecutionConsoleOptions(
                    Verbose: false,
                    ShowOverrides: false,
                    ShowResults: false));

            if (summary is null)
            {
                failureCount++;
                Console.WriteLine($"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: no bridge found.");
                continue;
            }

            totalLegacyPersists += summary.LegacyPersistCount;
            if (!summary.Success)
            {
                failureCount++;
                Console.WriteLine($"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: FAILED - {summary.FailureMessage}");
                continue;
            }

            if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
            {
                var ceilingSummary = await repository.ValidateLegacyTransactionCeilingsAsync(
                    [transactionId],
                    headRecordMode: summary.StageCount > 1,
                    persistStatus: string.Equals(ceilingMode, "persist", StringComparison.OrdinalIgnoreCase),
                    cancellationToken);
                ceilingChecked += ceilingSummary.CheckedCount;
                ceilingPassed += ceilingSummary.SuccessCount;
                ceilingFailed += ceilingSummary.FailedCount;

                if (ceilingSummary.FailedCount > 0)
                {
                    warningCount++;
                    Console.WriteLine(
                        $"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: ceiling validation failed | {string.Join(" | ", ceilingSummary.SampleMessages)}");
                    continue;
                }
            }

            if (summary.HasWarnings)
            {
                warningCount++;
                Console.WriteLine(
                    $"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: warning | Stages={summary.StageCount} | LegacyResults={summary.LegacyPersistCount}");
            }
            else
            {
                successCount++;
                if (progressEvery.HasValue &&
                    ((index + 1) % progressEvery.Value == 0 || index == transactionIds.Count - 1))
                {
                    Console.WriteLine(
                        $"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: ok | Stages={summary.StageCount} | LegacyResults={summary.LegacyPersistCount}");
                }
            }
        }
        catch (Exception ex)
        {
            failureCount++;
            Console.WriteLine($"[{index + 1}/{transactionIds.Count}] Transaction {transactionId}: FAILED - {ex.Message}");
        }
    }

    Console.WriteLine();
    Console.WriteLine("Batch summary:");
    Console.WriteLine($"  CalculationID: {calculationId}");
    Console.WriteLine($"  Transactions processed: {transactionIds.Count}");
    Console.WriteLine($"  Success: {successCount}");
    Console.WriteLine($"  Warnings: {warningCount}");
    Console.WriteLine($"  Failed: {failureCount}");
    Console.WriteLine($"  Legacy result writes: {totalLegacyPersists}");
    if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
    {
        Console.WriteLine($"  Ceiling mode: {ceilingMode}");
        Console.WriteLine($"  Ceiling checks: {ceilingChecked}");
        Console.WriteLine($"  Ceiling passed: {ceilingPassed}");
        Console.WriteLine($"  Ceiling failed: {ceilingFailed}");
    }
}

static async Task RunBenchmarkTransactionBatchAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var calculationId))
    {
        Console.WriteLine("benchmark-transaction-batch requires a numeric CalculationID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-batch 1");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-batch 1 10000 --progress=1000");
        return;
    }

    var limit = commandArgs.Count > 1 && int.TryParse(commandArgs[1], out var parsedLimit) && parsedLimit > 0
        ? parsedLimit
        : (int?)null;
    var progressEvery = commandArgs
        .Select(ParseProgressEvery)
        .FirstOrDefault(x => x.HasValue);
    var ceilingMode = ParseCeilingMode(commandArgs);
    var fiscalYearId = ParseIntOption(commandArgs, "--fy=");
    var versionId = ParseIntOption(commandArgs, "--version=");
    var dataObjectCodes = ParseFilterList(commandArgs, "--dataobjects=");
    var transactionTypeCodes = ParseFilterList(commandArgs, "--transactiontypes=");

    if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
    {
        Console.WriteLine("Ceiling validation requires a persistence mode. Use Transaction by Transaction or Bulk Write-Back.");
        Environment.ExitCode = 1;
        return;
    }

    var totalStopwatch = Stopwatch.StartNew();
    var preparationStopwatch = Stopwatch.StartNew();
    var preparation = await repository.PrepareTransactionBatchBenchmarkAsync(
        calculationId,
        limit,
        fiscalYearId,
        versionId,
        dataObjectCodes,
        transactionTypeCodes,
        modelCode: null,
        scenarioCode: null,
        cancellationToken);
    preparationStopwatch.Stop();

    if (preparation.Transactions.Count == 0)
    {
        Console.WriteLine($"No active root transactions were found for CalculationID {calculationId}.");
        return;
    }

    if (preparation.UnresolvedTransactionIds.Count > 0)
    {
        Console.WriteLine("Benchmark aborted because some transactions did not resolve to a bridge.");
        Console.WriteLine($"Unresolved transactions: {preparation.UnresolvedTransactionIds.Count}");
        Console.WriteLine($"First unresolved TransactionID: {preparation.UnresolvedTransactionIds[0]}");
        return;
    }

    if (preparation.DistinctBridgeIds.Count != 1 || preparation.Bridge is null || preparation.BaseSelection is null)
    {
        Console.WriteLine("Benchmark aborted because the selected transaction set resolves to multiple bridges.");
        Console.WriteLine($"Bridge IDs: {string.Join(", ", preparation.DistinctBridgeIds)}");
        return;
    }

    if (preparation.UnsupportedSourceTypes is { Count: > 0 })
    {
        Console.WriteLine("Benchmark aborted because this bridge uses source types that still depend on per-transaction lookups.");
        Console.WriteLine($"Unsupported source types: {string.Join(", ", preparation.UnsupportedSourceTypes)}");
        return;
    }

    Console.WriteLine(
        $"Benchmarking full in-memory batch for CalculationID {calculationId}. Transactions: {preparation.Transactions.Count} | Bridge={preparation.Bridge.CalcTransactionBridgeId} | Model={preparation.Bridge.ModelCode} | Scenario={preparation.Bridge.ScenarioCode}");

    var adaptationStopwatch = Stopwatch.StartNew();
    var executionStopwatch = new Stopwatch();
    var totalIssues = 0;
    var warnedTransactions = 0;
    decimal checksum = 0m;

    for (var index = 0; index < preparation.Transactions.Count; index++)
    {
        var transaction = preparation.Transactions[index];
        var nodeMaps = ScenarioMetadataRepository.ResolveNodeMapsForBenchmark(
            transaction,
            preparation.NodeMaps,
            preparation.RateLookupCache);
        var selection = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(
            preparation.BaseSelection,
            preparation.Bridge,
            nodeMaps,
            transaction);

        adaptationStopwatch.Stop();
        executionStopwatch.Start();
        var report = executionEngine.Execute(selection.Selection);
        executionStopwatch.Stop();
        adaptationStopwatch.Start();

        var issues = selection.Issues.Count + report.Issues.Count;
        totalIssues += issues;
        if (issues > 0)
        {
            warnedTransactions++;
        }

        checksum += LegacyTransactionResultProjector.Project(report).Total;

        if (progressEvery.HasValue &&
            ((index + 1) % progressEvery.Value == 0 || index == preparation.Transactions.Count - 1))
        {
            Console.WriteLine(
                $"  Progress: {index + 1}/{preparation.Transactions.Count} | Calc={executionStopwatch.Elapsed.TotalSeconds:F2}s | Tx/sec={(index + 1) / Math.Max(executionStopwatch.Elapsed.TotalSeconds, 0.001):F0}");
        }
    }

    adaptationStopwatch.Stop();
    totalStopwatch.Stop();

    var txCount = preparation.Transactions.Count;
    var calcSeconds = executionStopwatch.Elapsed.TotalSeconds;
    var totalSeconds = totalStopwatch.Elapsed.TotalSeconds;

    Console.WriteLine();
    Console.WriteLine("Benchmark summary:");
    Console.WriteLine($"  CalculationID: {calculationId}");
    Console.WriteLine($"  Transactions: {txCount}");
    Console.WriteLine($"  Load + prep time: {preparationStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Adaptation time: {adaptationStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Pure calc time: {calcSeconds:F3}s");
    Console.WriteLine($"  Total benchmark time: {totalSeconds:F3}s");
    Console.WriteLine($"  Calc throughput: {txCount / Math.Max(calcSeconds, 0.001):F0} tx/sec");
    Console.WriteLine($"  End-to-end throughput: {txCount / Math.Max(totalSeconds, 0.001):F0} tx/sec");
    Console.WriteLine($"  Warning transactions: {warnedTransactions}");
    Console.WriteLine($"  Total issues: {totalIssues}");
    Console.WriteLine($"  BPTotal checksum: {checksum:F6}");
    Console.WriteLine("  Persistence: skipped for benchmark");
}

static async Task RunBenchmarkTransactionChainBatchAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var calculationId))
    {
        Console.WriteLine("benchmark-transaction-chain-batch requires a numeric starting CalculationID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-chain-batch 2");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-chain-batch 2 5000 --progress=1000");
        return;
    }

    var limit = commandArgs.Count > 1 && int.TryParse(commandArgs[1], out var parsedLimit) && parsedLimit > 0
        ? parsedLimit
        : (int?)null;
    var progressEvery = commandArgs
        .Select(ParseProgressEvery)
        .FirstOrDefault(x => x.HasValue);
    var ceilingMode = ParseCeilingMode(commandArgs);
    var fiscalYearId = ParseIntOption(commandArgs, "--fy=");
    var versionId = ParseIntOption(commandArgs, "--version=");
    var dataObjectCodes = ParseFilterList(commandArgs, "--dataobjects=");
    var transactionTypeCodes = ParseFilterList(commandArgs, "--transactiontypes=");

    if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
    {
        Console.WriteLine("Ceiling validation requires a persistence mode. Use Transaction by Transaction or Bulk Write-Back.");
        Environment.ExitCode = 1;
        return;
    }

    var totalStopwatch = Stopwatch.StartNew();
    var rootPreparationStopwatch = Stopwatch.StartNew();
    var rootPreparation = await repository.PrepareTransactionBatchBenchmarkAsync(
        calculationId,
        limit,
        fiscalYearId,
        versionId,
        dataObjectCodes,
        transactionTypeCodes,
        modelCode: null,
        scenarioCode: null,
        cancellationToken);
    rootPreparationStopwatch.Stop();

    if (rootPreparation.Transactions.Count == 0)
    {
        Console.WriteLine($"No active root transactions were found for CalculationID {calculationId}.");
        return;
    }

    if (rootPreparation.Transactions[0].FiscalYearId is null)
    {
        Console.WriteLine("Chain benchmark aborted because the root transactions do not have a FiscalYearID.");
        return;
    }

    var chain = await repository.LoadLegacyCalculationChainAsync(
        rootPreparation.Transactions[0].FiscalYearId.Value,
        calculationId,
        cancellationToken);

    if (chain.Count <= 1)
    {
        Console.WriteLine($"CalculationID {calculationId} is not a multi-stage chain. Use benchmark-transaction-batch instead.");
        return;
    }

    Console.WriteLine(
        $"Benchmarking in-memory chain batch for CalculationID {calculationId}. Root transactions: {rootPreparation.Transactions.Count} | Stages: {chain.Count}");

    var rootTransactions = rootPreparation.Transactions.ToArray();
    var priorReports = rootTransactions
        .Select(_ => new Dictionary<string, ExecutionReport>(StringComparer.OrdinalIgnoreCase))
        .ToArray();

    var preparationSeconds = rootPreparationStopwatch.Elapsed.TotalSeconds;
    double totalAdaptSeconds = 0;
    double totalCalcSeconds = 0;
    decimal totalChecksum = 0m;
    var totalWarnings = 0;
    var totalIssues = 0;

    for (var stageIndex = 0; stageIndex < chain.Count; stageIndex++)
    {
        var stageDefinition = chain[stageIndex];
        var stagePreparationStopwatch = Stopwatch.StartNew();
        var stagePreparation = stageIndex == 0
            ? rootPreparation
            : await repository.PrepareTransactionBatchStageAsync(
                rootTransactions
                    .Select(rootTransaction => LegacyCalculationChainOrchestrator.CreateStageTransactionContext(rootTransaction, stageDefinition))
                    .ToArray(),
                modelCode: null,
                scenarioCode: null,
                allowChainResultSources: true,
                cancellationToken);
        stagePreparationStopwatch.Stop();

        if (stageIndex > 0)
        {
            preparationSeconds += stagePreparationStopwatch.Elapsed.TotalSeconds;
        }

        if (stagePreparation.UnresolvedTransactionIds.Count > 0)
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because some transactions did not resolve to a bridge.");
            Console.WriteLine($"First unresolved TransactionID: {stagePreparation.UnresolvedTransactionIds[0]}");
            return;
        }

        if (stagePreparation.DistinctBridgeIds.Count != 1 || stagePreparation.Bridge is null || stagePreparation.BaseSelection is null)
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because multiple bridges were resolved.");
            Console.WriteLine($"Bridge IDs: {string.Join(", ", stagePreparation.DistinctBridgeIds)}");
            return;
        }

        if (stagePreparation.UnsupportedSourceTypes is { Count: > 0 })
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because unsupported source types were found.");
            Console.WriteLine($"Unsupported source types: {string.Join(", ", stagePreparation.UnsupportedSourceTypes)}");
            return;
        }

        Console.WriteLine(
            $"Stage {stageIndex + 1}/{chain.Count}: CalcID={stageDefinition.CalculationId} | {stageDefinition.CalculationName} | Model={stagePreparation.Bridge.ModelCode}");

        var stageAdaptationStopwatch = Stopwatch.StartNew();
        var stageExecutionStopwatch = new Stopwatch();
        decimal stageChecksum = 0m;
        var stageWarnings = 0;
        var stageIssues = 0;

        for (var index = 0; index < rootTransactions.Length; index++)
        {
            var stageTransaction = stageIndex == 0
                ? rootTransactions[index]
                : LegacyCalculationChainOrchestrator.CreateStageTransactionContext(rootTransactions[index], stageDefinition);
            var nodeMaps = ScenarioMetadataRepository.ResolveNodeMapsForBenchmark(
                stageTransaction,
                stagePreparation.NodeMaps,
                stagePreparation.RateLookupCache);
            var selection = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(
                stagePreparation.BaseSelection,
                stagePreparation.Bridge,
                nodeMaps,
                stageTransaction,
                previousReport: null,
                priorReportsByName: priorReports[index]);

            stageAdaptationStopwatch.Stop();
            stageExecutionStopwatch.Start();
            var report = executionEngine.Execute(selection.Selection);
            stageExecutionStopwatch.Stop();
            stageAdaptationStopwatch.Start();

            priorReports[index][stageDefinition.CalculationName] = report;

            var issues = selection.Issues.Count + report.Issues.Count;
            stageIssues += issues;
            if (issues > 0)
            {
                stageWarnings++;
            }

            stageChecksum += LegacyTransactionResultProjector.Project(report).Total;

            if (progressEvery.HasValue &&
                ((index + 1) % progressEvery.Value == 0 || index == rootTransactions.Length - 1))
            {
                Console.WriteLine(
                    $"  Progress: {index + 1}/{rootTransactions.Length} | Calc={stageExecutionStopwatch.Elapsed.TotalSeconds:F2}s | Tx/sec={(index + 1) / Math.Max(stageExecutionStopwatch.Elapsed.TotalSeconds, 0.001):F0}");
            }
        }

        stageAdaptationStopwatch.Stop();
        totalAdaptSeconds += stageAdaptationStopwatch.Elapsed.TotalSeconds;
        totalCalcSeconds += stageExecutionStopwatch.Elapsed.TotalSeconds;
        totalChecksum += stageChecksum;
        totalWarnings += stageWarnings;
        totalIssues += stageIssues;

        Console.WriteLine(
            $"  Stage summary: Adapt={stageAdaptationStopwatch.Elapsed.TotalSeconds:F3}s | Calc={stageExecutionStopwatch.Elapsed.TotalSeconds:F3}s | Checksum={stageChecksum:F6} | Warnings={stageWarnings}");
    }

    totalStopwatch.Stop();
    var rootCount = rootTransactions.Length;
    var stageEvaluations = rootCount * chain.Count;

    Console.WriteLine();
    Console.WriteLine("Chain benchmark summary:");
    Console.WriteLine($"  Starting CalculationID: {calculationId}");
    Console.WriteLine($"  Root transactions: {rootCount}");
    Console.WriteLine($"  Stages executed: {chain.Count}");
    Console.WriteLine($"  Stage evaluations: {stageEvaluations}");
    Console.WriteLine($"  Load + prep time: {preparationSeconds:F3}s");
    Console.WriteLine($"  Adaptation time: {totalAdaptSeconds:F3}s");
    Console.WriteLine($"  Pure calc time: {totalCalcSeconds:F3}s");
    Console.WriteLine($"  Total benchmark time: {totalStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Calc throughput: {stageEvaluations / Math.Max(totalCalcSeconds, 0.001):F0} stage-evals/sec");
    Console.WriteLine($"  End-to-end throughput: {stageEvaluations / Math.Max(totalStopwatch.Elapsed.TotalSeconds, 0.001):F0} stage-evals/sec");
    Console.WriteLine($"  Warning transactions: {totalWarnings}");
    Console.WriteLine($"  Total issues: {totalIssues}");
    Console.WriteLine($"  Combined checksum: {totalChecksum:F6}");
    Console.WriteLine("  Persistence: skipped for benchmark");
}

static async Task RunExecuteTransactionBatchBulkAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var calculationId))
    {
        Console.WriteLine("execute-transaction-batch-bulk requires a numeric CalculationID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch-bulk 1");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch-bulk 1 10000 --progress=1000");
        return;
    }

    var limit = commandArgs.Count > 1 && int.TryParse(commandArgs[1], out var parsedLimit) && parsedLimit > 0
        ? parsedLimit
        : (int?)null;
    var progressEvery = commandArgs
        .Select(ParseProgressEvery)
        .FirstOrDefault(x => x.HasValue);
    var ceilingMode = ParseCeilingMode(commandArgs);
    var fiscalYearId = ParseIntOption(commandArgs, "--fy=");
    var versionId = ParseIntOption(commandArgs, "--version=");
    var dataObjectCodes = ParseFilterList(commandArgs, "--dataobjects=");
    var transactionTypeCodes = ParseFilterList(commandArgs, "--transactiontypes=");

    var totalStopwatch = Stopwatch.StartNew();
    var preparationStopwatch = Stopwatch.StartNew();
    var preparation = await repository.PrepareTransactionBatchBenchmarkAsync(
        calculationId,
        limit,
        fiscalYearId,
        versionId,
        dataObjectCodes,
        transactionTypeCodes,
        modelCode: null,
        scenarioCode: null,
        cancellationToken);
    preparationStopwatch.Stop();

    if (preparation.Transactions.Count == 0)
    {
        Console.WriteLine($"No active root transactions were found for CalculationID {calculationId}.");
        return;
    }

    if (preparation.UnresolvedTransactionIds.Count > 0)
    {
        Console.WriteLine("Bulk batch aborted because some transactions did not resolve to a bridge.");
        Console.WriteLine($"Unresolved transactions: {preparation.UnresolvedTransactionIds.Count}");
        Console.WriteLine($"First unresolved TransactionID: {preparation.UnresolvedTransactionIds[0]}");
        return;
    }

    if (preparation.DistinctBridgeIds.Count != 1 || preparation.Bridge is null || preparation.BaseSelection is null)
    {
        Console.WriteLine("Bulk batch aborted because the selected transaction set resolves to multiple bridges.");
        Console.WriteLine($"Bridge IDs: {string.Join(", ", preparation.DistinctBridgeIds)}");
        return;
    }

    if (preparation.UnsupportedSourceTypes is { Count: > 0 })
    {
        Console.WriteLine("Bulk batch aborted because this bridge uses source types that still require chained or per-transaction execution.");
        Console.WriteLine($"Unsupported source types: {string.Join(", ", preparation.UnsupportedSourceTypes)}");
        return;
    }

    Console.WriteLine(
        $"Executing in-memory batch with bulk legacy persistence for CalculationID {calculationId}. Transactions: {preparation.Transactions.Count} | Bridge={preparation.Bridge.CalcTransactionBridgeId} | Model={preparation.Bridge.ModelCode} | Scenario={preparation.Bridge.ScenarioCode}");

    var adaptationStopwatch = Stopwatch.StartNew();
    var executionStopwatch = new Stopwatch();
    decimal checksum = 0m;
    var warningTransactions = 0;
    var totalIssues = 0;
    var rows = new List<LegacyBatchTransactionResult>(preparation.Transactions.Count);

    for (var index = 0; index < preparation.Transactions.Count; index++)
    {
        var transaction = preparation.Transactions[index];
        var nodeMaps = ScenarioMetadataRepository.ResolveNodeMapsForBenchmark(
            transaction,
            preparation.NodeMaps,
            preparation.RateLookupCache);
        var selection = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(
            preparation.BaseSelection,
            preparation.Bridge,
            nodeMaps,
            transaction);

        adaptationStopwatch.Stop();
        executionStopwatch.Start();
        var report = executionEngine.Execute(selection.Selection);
        executionStopwatch.Stop();
        adaptationStopwatch.Start();

        var projection = LegacyTransactionResultProjector.Project(report);
        checksum += projection.Total;
        var issues = selection.Issues.Concat(report.Issues).ToArray();
        totalIssues += issues.Length;
        if (issues.Length > 0)
        {
            warningTransactions++;
        }

        rows.Add(new LegacyBatchTransactionResult(
            transaction,
            projection,
            issues.Length == 0 ? "Success" : "SuccessWithWarnings",
            issues.Length == 0 ? null : string.Join(" | ", issues.Take(5))));

        if (progressEvery.HasValue &&
            ((index + 1) % progressEvery.Value == 0 || index == preparation.Transactions.Count - 1))
        {
            Console.WriteLine(
                $"  Progress: {index + 1}/{preparation.Transactions.Count} | Calc={executionStopwatch.Elapsed.TotalSeconds:F2}s | Tx/sec={(index + 1) / Math.Max(executionStopwatch.Elapsed.TotalSeconds, 0.001):F0}");
        }
    }

    adaptationStopwatch.Stop();
    var persistenceStopwatch = Stopwatch.StartNew();
    await repository.BulkPersistLegacyTransactionBatchAsync(
        rows,
        Guid.NewGuid(),
        preparation.BaseSelection.Bundle.Model.ModelCode,
        preparation.BaseSelection.Bundle.Model.ModelVersion,
        "scenario-engine-csharp-bulk",
        cancellationToken);
    persistenceStopwatch.Stop();

    CeilingValidationBatchSummary? ceilingSummary = null;
    Stopwatch? ceilingStopwatch = null;
    if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
    {
        ceilingStopwatch = Stopwatch.StartNew();
        ceilingSummary = await repository.ValidateLegacyTransactionCeilingsAsync(
            preparation.Transactions.Select(x => x.TransactionId).ToArray(),
            headRecordMode: false,
            persistStatus: string.Equals(ceilingMode, "persist", StringComparison.OrdinalIgnoreCase),
            cancellationToken);
        ceilingStopwatch.Stop();
    }

    totalStopwatch.Stop();

    var txCount = preparation.Transactions.Count;
    var calcSeconds = executionStopwatch.Elapsed.TotalSeconds;
    var totalSeconds = totalStopwatch.Elapsed.TotalSeconds;

    Console.WriteLine();
    Console.WriteLine("Bulk batch summary:");
    Console.WriteLine($"  CalculationID: {calculationId}");
    Console.WriteLine($"  Transactions: {txCount}");
    Console.WriteLine($"  Load + prep time: {preparationStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Adaptation time: {adaptationStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Pure calc time: {calcSeconds:F3}s");
    Console.WriteLine($"  Bulk persistence time: {persistenceStopwatch.Elapsed.TotalSeconds:F3}s");
    Console.WriteLine($"  Total batch time: {totalSeconds:F3}s");
    Console.WriteLine($"  Calc throughput: {txCount / Math.Max(calcSeconds, 0.001):F0} tx/sec");
    Console.WriteLine($"  End-to-end throughput: {txCount / Math.Max(totalSeconds, 0.001):F0} tx/sec");
    Console.WriteLine($"  Warning transactions: {warningTransactions}");
    Console.WriteLine($"  Total issues: {totalIssues}");
    Console.WriteLine($"  BPTotal checksum: {checksum:F6}");
    if (ceilingSummary is not null && ceilingStopwatch is not null)
    {
        Console.WriteLine($"  Ceiling mode: {ceilingMode}");
        Console.WriteLine($"  Ceiling validation time: {ceilingStopwatch.Elapsed.TotalSeconds:F3}s");
        Console.WriteLine($"  Ceiling checks: {ceilingSummary.CheckedCount}");
        Console.WriteLine($"  Ceiling passed: {ceilingSummary.SuccessCount}");
        Console.WriteLine($"  Ceiling failed: {ceilingSummary.FailedCount}");
        foreach (var message in ceilingSummary.SampleMessages)
        {
            Console.WriteLine($"    {message}");
        }
    }
}

static async Task RunExecuteTransactionChainBatchBulkAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !int.TryParse(commandArgs[0], out var calculationId))
    {
        Console.WriteLine("execute-transaction-chain-batch-bulk requires a numeric starting CalculationID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-chain-batch-bulk 2");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-chain-batch-bulk 2 5000 --progress=1000");
        return;
    }

    var limit = commandArgs.Count > 1 && int.TryParse(commandArgs[1], out var parsedLimit) && parsedLimit > 0
        ? parsedLimit
        : (int?)null;
    var progressEvery = commandArgs
        .Select(ParseProgressEvery)
        .FirstOrDefault(x => x.HasValue);
    var ceilingMode = ParseCeilingMode(commandArgs);
    var fiscalYearId = ParseIntOption(commandArgs, "--fy=");
    var versionId = ParseIntOption(commandArgs, "--version=");
    var dataObjectCodes = ParseFilterList(commandArgs, "--dataobjects=");
    var transactionTypeCodes = ParseFilterList(commandArgs, "--transactiontypes=");

    var totalStopwatch = Stopwatch.StartNew();
    var rootPreparation = await repository.PrepareTransactionBatchBenchmarkAsync(
        calculationId,
        limit,
        fiscalYearId,
        versionId,
        dataObjectCodes,
        transactionTypeCodes,
        modelCode: null,
        scenarioCode: null,
        cancellationToken);

    if (rootPreparation.Transactions.Count == 0)
    {
        Console.WriteLine($"No active root transactions were found for CalculationID {calculationId}.");
        return;
    }

    if (rootPreparation.Transactions[0].FiscalYearId is null)
    {
        Console.WriteLine("Chain batch aborted because the root transactions do not have a FiscalYearID.");
        return;
    }

    var chain = await repository.LoadLegacyCalculationChainAsync(
        rootPreparation.Transactions[0].FiscalYearId.Value,
        calculationId,
        cancellationToken);

    if (chain.Count <= 1)
    {
        Console.WriteLine($"CalculationID {calculationId} is not a multi-stage chain. Use execute-transaction-batch-bulk instead.");
        return;
    }

    Console.WriteLine(
        $"Executing in-memory bulk chain batch for CalculationID {calculationId}. Root transactions: {rootPreparation.Transactions.Count} | Stages: {chain.Count}");

    var rootTransactions = rootPreparation.Transactions.ToArray();
    var priorReports = rootTransactions
        .Select(_ => new Dictionary<string, ExecutionReport>(StringComparer.OrdinalIgnoreCase))
        .ToArray();
    var overallRunId = Guid.NewGuid();
    var totalWarnings = 0;
    var totalIssues = 0;
    var totalPersistedRows = 0;

    for (var stageIndex = 0; stageIndex < chain.Count; stageIndex++)
    {
        var stageDefinition = chain[stageIndex];
        var stagePreparation = stageIndex == 0
            ? rootPreparation
            : await repository.PrepareTransactionBatchStageAsync(
                rootTransactions
                    .Select(rootTransaction => LegacyCalculationChainOrchestrator.CreateStageTransactionContext(rootTransaction, stageDefinition))
                    .ToArray(),
                modelCode: null,
                scenarioCode: null,
                allowChainResultSources: true,
                cancellationToken);

        if (stagePreparation.UnresolvedTransactionIds.Count > 0)
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because some transactions did not resolve to a bridge.");
            Console.WriteLine($"First unresolved TransactionID: {stagePreparation.UnresolvedTransactionIds[0]}");
            return;
        }

        if (stagePreparation.DistinctBridgeIds.Count != 1 || stagePreparation.Bridge is null || stagePreparation.BaseSelection is null)
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because multiple bridges were resolved.");
            Console.WriteLine($"Bridge IDs: {string.Join(", ", stagePreparation.DistinctBridgeIds)}");
            return;
        }

        if (stagePreparation.UnsupportedSourceTypes is { Count: > 0 })
        {
            Console.WriteLine($"Stage {stageIndex + 1} aborted because unsupported source types were found.");
            Console.WriteLine($"Unsupported source types: {string.Join(", ", stagePreparation.UnsupportedSourceTypes)}");
            return;
        }

        Console.WriteLine(
            $"Stage {stageIndex + 1}/{chain.Count}: CalcID={stageDefinition.CalculationId} | {stageDefinition.CalculationName} | Model={stagePreparation.Bridge.ModelCode}");

        var stageAdaptationStopwatch = Stopwatch.StartNew();
        var stageCalcStopwatch = new Stopwatch();
        var stageRows = new List<LegacyBatchTransactionResult>(rootTransactions.Length);
        var childCandidates = new List<TransactionInputRecord>(rootTransactions.Length);
        var projections = new List<LegacyTransactionResultProjection>(rootTransactions.Length);
        var statuses = new List<string>(rootTransactions.Length);
        var errorMessages = new List<string?>(rootTransactions.Length);

        for (var index = 0; index < rootTransactions.Length; index++)
        {
            var stageTransaction = stageIndex == 0
                ? rootTransactions[index]
                : LegacyCalculationChainOrchestrator.CreateStageTransactionContext(rootTransactions[index], stageDefinition);
            var nodeMaps = ScenarioMetadataRepository.ResolveNodeMapsForBenchmark(
                stageTransaction,
                stagePreparation.NodeMaps,
                stagePreparation.RateLookupCache);
            var selection = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(
                stagePreparation.BaseSelection,
                stagePreparation.Bridge,
                nodeMaps,
                stageTransaction,
                previousReport: null,
                priorReportsByName: priorReports[index]);

            stageAdaptationStopwatch.Stop();
            stageCalcStopwatch.Start();
            var report = executionEngine.Execute(selection.Selection);
            stageCalcStopwatch.Stop();
            stageAdaptationStopwatch.Start();

            priorReports[index][stageDefinition.CalculationName] = report;

            var issues = selection.Issues.Concat(report.Issues).ToArray();
            totalIssues += issues.Length;
            if (issues.Length > 0)
            {
                totalWarnings++;
            }

            var projection = LegacyTransactionResultProjector.Project(report);
            projections.Add(projection);
            statuses.Add(issues.Length == 0 ? "Success" : "SuccessWithWarnings");
            errorMessages.Add(issues.Length == 0 ? null : string.Join(" | ", issues.Take(5)));

            if (stageIndex > 0 && stageDefinition.GenerateTransaction)
            {
                childCandidates.Add(LegacyCalculationChainOrchestrator.MaterializeChildTransaction(
                    rootTransactions[index],
                    stageDefinition,
                    report));
            }

            if (progressEvery.HasValue &&
                ((index + 1) % progressEvery.Value == 0 || index == rootTransactions.Length - 1))
            {
                Console.WriteLine(
                    $"  Progress: {index + 1}/{rootTransactions.Length} | Calc={stageCalcStopwatch.Elapsed.TotalSeconds:F2}s | Tx/sec={(index + 1) / Math.Max(stageCalcStopwatch.Elapsed.TotalSeconds, 0.001):F0}");
            }
        }

        stageAdaptationStopwatch.Stop();

        IReadOnlyList<TransactionInputRecord> persistenceTargets;
        var childUpsertStopwatch = Stopwatch.StartNew();
        if (stageIndex == 0)
        {
            persistenceTargets = rootTransactions;
            childUpsertStopwatch.Stop();
        }
        else if (stageDefinition.GenerateTransaction)
        {
            persistenceTargets = await repository.BulkUpsertLegacyChildTransactionsAsync(childCandidates, cancellationToken);
            childUpsertStopwatch.Stop();
        }
        else
        {
            persistenceTargets = Array.Empty<TransactionInputRecord>();
            childUpsertStopwatch.Stop();
        }

        for (var index = 0; index < persistenceTargets.Count; index++)
        {
            stageRows.Add(new LegacyBatchTransactionResult(
                persistenceTargets[index],
                projections[index],
                statuses[index],
                errorMessages[index]));
        }

        var persistenceStopwatch = Stopwatch.StartNew();
        await repository.BulkPersistLegacyTransactionBatchAsync(
            stageRows,
            overallRunId,
            stagePreparation.BaseSelection.Bundle.Model.ModelCode,
            stagePreparation.BaseSelection.Bundle.Model.ModelVersion,
            "scenario-engine-csharp-chain-bulk",
            cancellationToken);
        persistenceStopwatch.Stop();
        totalPersistedRows += stageRows.Count;

        Console.WriteLine(
            $"  Stage summary: Rows={stageRows.Count} | Adapt={stageAdaptationStopwatch.Elapsed.TotalSeconds:F3}s | Calc={stageCalcStopwatch.Elapsed.TotalSeconds:F3}s | ChildUpsert={childUpsertStopwatch.Elapsed.TotalSeconds:F3}s | Persist={persistenceStopwatch.Elapsed.TotalSeconds:F3}s");
    }

    CeilingValidationBatchSummary? ceilingSummary = null;
    Stopwatch? ceilingStopwatch = null;
    if (!string.Equals(ceilingMode, "none", StringComparison.OrdinalIgnoreCase))
    {
        ceilingStopwatch = Stopwatch.StartNew();
        ceilingSummary = await repository.ValidateLegacyTransactionCeilingsAsync(
            rootTransactions.Select(x => x.TransactionId).ToArray(),
            headRecordMode: true,
            persistStatus: string.Equals(ceilingMode, "persist", StringComparison.OrdinalIgnoreCase),
            cancellationToken);
        ceilingStopwatch.Stop();
    }

    totalStopwatch.Stop();
    Console.WriteLine();
    Console.WriteLine("Chain bulk batch summary:");
    Console.WriteLine($"  Starting CalculationID: {calculationId}");
    Console.WriteLine($"  Root transactions: {rootTransactions.Length}");
    Console.WriteLine($"  Stages executed: {chain.Count}");
    Console.WriteLine($"  Persisted transaction result sets: {totalPersistedRows}");
    Console.WriteLine($"  Warning transactions: {totalWarnings}");
    Console.WriteLine($"  Total issues: {totalIssues}");
    if (ceilingSummary is not null && ceilingStopwatch is not null)
    {
        Console.WriteLine($"  Ceiling mode: {ceilingMode}");
        Console.WriteLine($"  Ceiling validation time: {ceilingStopwatch.Elapsed.TotalSeconds:F3}s");
        Console.WriteLine($"  Ceiling checks: {ceilingSummary.CheckedCount}");
        Console.WriteLine($"  Ceiling passed: {ceilingSummary.SuccessCount}");
        Console.WriteLine($"  Ceiling failed: {ceilingSummary.FailedCount}");
        foreach (var message in ceilingSummary.SampleMessages)
        {
            Console.WriteLine($"    {message}");
        }
    }
    Console.WriteLine($"  Total elapsed: {totalStopwatch.Elapsed.TotalSeconds:F3}s");
}

static async Task<TransactionWorkflowSummary?> ExecuteTransactionWorkflowAsync(
    ScenarioMetadataRepository repository,
    ScenarioExecutionEngine executionEngine,
    int transactionId,
    string? modelCode,
    string? scenarioCode,
    CancellationToken cancellationToken,
    TransactionExecutionConsoleOptions consoleOptions)
{
    var transactionSelection = await repository.LoadTransactionExecutionSelectionAsync(
        transactionId,
        modelCode,
        scenarioCode,
        cancellationToken);

    if (transactionSelection is null)
    {
        return null;
    }

    if (consoleOptions.Verbose)
    {
        Console.WriteLine($"Executing transaction: {transactionSelection.Transaction.TransactionId}");
        Console.WriteLine(
            $"Transaction context: FiscalYearID={transactionSelection.Transaction.FiscalYearId?.ToString() ?? "NULL"} | VersionID={transactionSelection.Transaction.VersionId?.ToString() ?? "NULL"} | TransactionTypeCode={transactionSelection.Transaction.TransactionTypeCode ?? "NULL"} | UOM={transactionSelection.Transaction.UomCodeInpC ?? "NULL"}");
    }

    var chain = transactionSelection.Transaction.CalculationId.HasValue && transactionSelection.Transaction.FiscalYearId.HasValue
        ? await repository.LoadLegacyCalculationChainAsync(
            transactionSelection.Transaction.FiscalYearId.Value,
            transactionSelection.Transaction.CalculationId.Value,
            cancellationToken)
        : Array.Empty<LegacyCalculationDefinition>();

    var stageDefinitions = chain.Count == 0
        ? new LegacyCalculationDefinition?[] { null }
        : chain.Select(x => (LegacyCalculationDefinition?)x).ToArray();
    var priorReportsByName = new Dictionary<string, ExecutionReport>(StringComparer.OrdinalIgnoreCase);
    var rootTransaction = transactionSelection.Transaction;
    var runId = Guid.NewGuid();
    var hasWarnings = false;
    var legacyPersistCount = 0;
    var stageCount = 0;

    for (var stageIndex = 0; stageIndex < stageDefinitions.Length; stageIndex++)
    {
        var stageDefinition = stageDefinitions[stageIndex];
        var stageSelection = stageIndex == 0
            ? transactionSelection
            : await repository.LoadTransactionExecutionSelectionAsync(
                LegacyCalculationChainOrchestrator.CreateStageTransactionContext(rootTransaction, stageDefinition!),
                modelCode,
                scenarioCode,
                cancellationToken,
                previousReport: null,
                priorReportsByName: priorReportsByName);

        if (stageSelection is null)
        {
            hasWarnings = true;
            if (consoleOptions.Verbose)
            {
                Console.WriteLine(
                    $"No transaction bridge was found for child CalculationID {stageDefinition?.CalculationId}.");
            }
            break;
        }

        stageCount++;
        if (consoleOptions.Verbose)
        {
            Console.WriteLine();
            Console.WriteLine(stageDefinition is null
                ? $"Stage 1: bridge {stageSelection.Bridge.CalcTransactionBridgeId} | Model={stageSelection.Selection.Bundle.Model.ModelCode} | Scenario={stageSelection.Selection.Scenario.ScenarioCode} | LegacyCalculationID={stageSelection.Bridge.LegacyCalculationId?.ToString() ?? "NULL"}"
                : $"Stage {stageIndex + 1}: CalculationID={stageDefinition.CalculationId} | CalcName={stageDefinition.CalculationName} | Bridge={stageSelection.Bridge.CalcTransactionBridgeId} | Model={stageSelection.Selection.Bundle.Model.ModelCode} | Scenario={stageSelection.Selection.Scenario.ScenarioCode}");
            Console.WriteLine($"Applied overrides: {stageSelection.Overrides.Count}");
        }

        if (consoleOptions.ShowOverrides && stageSelection.Overrides.Count > 0)
        {
            foreach (var group in stageSelection.Overrides
                         .GroupBy(x => new { x.CostObjectCode, x.PeriodCode })
                         .OrderBy(x => x.Key.CostObjectCode, StringComparer.OrdinalIgnoreCase)
                         .ThenBy(x => x.Key.PeriodCode, StringComparer.OrdinalIgnoreCase))
            {
                Console.WriteLine($"  {group.Key.CostObjectCode} | {group.Key.PeriodCode}");
                foreach (var row in group.OrderBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase))
                {
                    var sourceLabel = string.IsNullOrWhiteSpace(row.SourceColumnName)
                        ? row.SourceTypeCode
                        : $"{row.SourceTypeCode}:{row.SourceColumnName}";
                    Console.WriteLine($"    {row.NodeCode} <= {sourceLabel} = {row.Value:F6}");
                }
            }
        }

        if (stageSelection.Issues.Count > 0)
        {
            hasWarnings = true;
            if (consoleOptions.Verbose)
            {
                Console.WriteLine("Bridge issues:");
                foreach (var issue in stageSelection.Issues)
                {
                    Console.WriteLine($"  - {issue}");
                }
            }
        }

        var validation = ScenarioModelValidator.Validate(stageSelection.Selection.Bundle);
        if (validation.Issues.Count > 0)
        {
            if (consoleOptions.Verbose)
            {
                Console.WriteLine("Validation issues:");
                foreach (var issue in validation.Issues)
                {
                    Console.WriteLine($"  - [{issue.SeverityCode}] {issue.Message}");
                }
            }

            if (validation.HasErrors)
            {
                return new TransactionWorkflowSummary(
                    transactionId,
                    stageCount,
                    legacyPersistCount,
                    HasWarnings: true,
                    Success: false,
                    FailureMessage: "Execution aborted because the model has validation errors.");
            }
        }

        var report = executionEngine.Execute(stageSelection.Selection);
        var combinedIssues = stageSelection.Issues.Concat(report.Issues).Distinct(StringComparer.Ordinal).ToArray();
        var finalReport = combinedIssues.Length == report.Issues.Count
            ? report
            : report with { Issues = combinedIssues };
        hasWarnings |= finalReport.Issues.Count > 0;
        var persistedRun = await repository.PersistExecutionAsync(
            stageSelection.Selection,
            finalReport,
            cancellationToken,
            ExecutionPersistenceOptions.ForTransaction(stageSelection));

        TransactionInputRecord? legacyPersistenceTarget = null;
        if (stageIndex == 0)
        {
            legacyPersistenceTarget = stageSelection.Transaction;
        }
        else if (stageDefinition?.GenerateTransaction == true)
        {
            legacyPersistenceTarget = await repository.UpsertLegacyChildTransactionAsync(
                rootTransaction,
                stageDefinition,
                finalReport,
                cancellationToken);
        }

        if (legacyPersistenceTarget is not null)
        {
            await repository.PersistLegacyTransactionResultAsync(
                legacyPersistenceTarget,
                stageSelection.Selection,
                finalReport,
                runId,
                persistedRun.CalcRunId,
                cancellationToken);
            legacyPersistCount++;
        }

        if (consoleOptions.Verbose)
        {
            Console.WriteLine("Execution order:");
            foreach (var nodeCode in finalReport.ExecutionOrder)
            {
                Console.WriteLine($"  {nodeCode}");
            }

            if (consoleOptions.ShowResults)
            {
                Console.WriteLine("Calculated values:");
                foreach (var group in finalReport.Results
                             .OrderBy(x => x.CostObjectCode, StringComparer.OrdinalIgnoreCase)
                             .ThenBy(x => x.PeriodCode, StringComparer.OrdinalIgnoreCase)
                             .GroupBy(x => new { x.CostObjectCode, x.PeriodCode }))
                {
                    Console.WriteLine($"  {group.Key.CostObjectCode} | {group.Key.PeriodCode}");
                    foreach (var row in group.OrderBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase))
                    {
                        Console.WriteLine($"    {row.NodeCode} = {row.Value:F6}");
                    }
                }
            }

            if (finalReport.Issues.Count == 0)
            {
                Console.WriteLine("Execution completed with no issues.");
            }
            else
            {
                Console.WriteLine("Execution issues:");
                foreach (var issue in finalReport.Issues)
                {
                    Console.WriteLine($"  - {issue}");
                }
            }

            if (legacyPersistenceTarget is not null)
            {
                Console.WriteLine($"Legacy transaction results persisted to TransactionID {legacyPersistenceTarget.TransactionId}.");
            }

            Console.WriteLine(
                $"Persisted run: ID={persistedRun.CalcRunId} | Status={persistedRun.StatusCode} | Results={persistedRun.ResultRowCount} | Errors={persistedRun.ErrorCount}");
        }

        if (stageDefinition is not null)
        {
            priorReportsByName[stageDefinition.CalculationName] = finalReport;
        }
        else if (stageSelection.Bridge.LegacyCalculationId.HasValue)
        {
            priorReportsByName[$"CALC_{stageSelection.Bridge.LegacyCalculationId.Value}"] = finalReport;
        }
    }

    return new TransactionWorkflowSummary(
        transactionId,
        stageCount,
        legacyPersistCount,
        HasWarnings: hasWarnings,
        Success: true,
        FailureMessage: null);
}

static async Task RunPublishRunAsync(
    ScenarioMetadataRepository repository,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0 || !long.TryParse(commandArgs[0], out var calcRunId))
    {
        Console.WriteLine("publish-run requires a numeric CalcRunID.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- publish-run 12");
        return;
    }

    var summary = await repository.PublishRunAsync(calcRunId, cancellationToken);

    Console.WriteLine(
        $"Published run: EventID={summary.CalcPublishEventId} | RunID={summary.SourceCalcRunId} | Model={summary.ModelCode} | Scenario={summary.ScenarioCode} | Rows={summary.PublishedRowCount}");
}

static async Task RunPublishLatestRunAsync(
    ScenarioMetadataRepository repository,
    IReadOnlyList<string> commandArgs,
    CancellationToken cancellationToken)
{
    if (commandArgs.Count == 0)
    {
        Console.WriteLine("publish-latest requires a model code.");
        Console.WriteLine("Example: dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- publish-latest SCENARIO_V1_DEMO BASE");
        return;
    }

    var modelCode = commandArgs[0];
    var scenarioCode = commandArgs.Count > 1 ? commandArgs[1] : null;
    var summary = await repository.PublishLatestRunAsync(modelCode, scenarioCode, cancellationToken);

    Console.WriteLine(
        $"Published latest run: EventID={summary.CalcPublishEventId} | RunID={summary.SourceCalcRunId} | Model={summary.ModelCode} | Scenario={summary.ScenarioCode} | Rows={summary.PublishedRowCount}");
}

static void PrintUsage()
{
    Console.WriteLine("Usage:");
    Console.WriteLine("  probe");
    Console.WriteLine("  list-models");
    Console.WriteLine("  inspect-model <ModelCode>");
    Console.WriteLine("  execute-model <ModelCode> [ScenarioCode]");
    Console.WriteLine("  execute-transaction <TransactionID> [ModelCode] [ScenarioCode]");
    Console.WriteLine("  execute-transaction-batch <CalculationID> [Limit] [--fy=2026] [--version=5] [--dataobjects=3081001] [--transactiontypes=12] [--progress=1000] [--ceiling-mode=none|validate|persist]");
    Console.WriteLine("  benchmark-transaction-batch <CalculationID> [Limit] [--fy=2026] [--version=5] [--dataobjects=3081001] [--transactiontypes=12] [--progress=1000]");
    Console.WriteLine("  benchmark-transaction-chain-batch <CalculationID> [Limit] [--fy=2026] [--version=5] [--dataobjects=3081001] [--transactiontypes=12] [--progress=1000]");
    Console.WriteLine("  execute-transaction-batch-bulk <CalculationID> [Limit] [--fy=2026] [--version=5] [--dataobjects=3081001] [--transactiontypes=12] [--progress=1000] [--ceiling-mode=none|validate|persist]");
    Console.WriteLine("  execute-transaction-chain-batch-bulk <CalculationID> [Limit] [--fy=2026] [--version=5] [--dataobjects=3081001] [--transactiontypes=12] [--progress=1000] [--ceiling-mode=none|validate|persist]");
    Console.WriteLine("  publish-run <CalcRunID>");
    Console.WriteLine("  publish-latest <ModelCode> [ScenarioCode]");
}

static int? ParseProgressEvery(string argument)
{
    if (!argument.StartsWith("--progress=", StringComparison.OrdinalIgnoreCase))
    {
        return null;
    }

    var rawValue = argument["--progress=".Length..];
    return int.TryParse(rawValue, out var parsed) && parsed > 0
        ? parsed
        : null;
}

static IReadOnlyList<string> ParseFilterList(IReadOnlyList<string> args, string prefix)
{
    var raw = args.FirstOrDefault(x => x.StartsWith(prefix, StringComparison.OrdinalIgnoreCase));
    if (string.IsNullOrWhiteSpace(raw))
    {
        return [];
    }

    return raw[prefix.Length..]
        .Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
        .Where(x => !string.IsNullOrWhiteSpace(x))
        .Distinct(StringComparer.OrdinalIgnoreCase)
        .ToArray();
}

static int? ParseIntOption(IReadOnlyList<string> args, string prefix)
{
    var raw = args.FirstOrDefault(x => x.StartsWith(prefix, StringComparison.OrdinalIgnoreCase));
    if (string.IsNullOrWhiteSpace(raw))
    {
        return null;
    }

    var value = raw[prefix.Length..].Trim();
    return int.TryParse(value, out var parsed) && parsed > 0
        ? parsed
        : null;
}

static string ParseCeilingMode(IReadOnlyList<string> args)
{
    var raw = args.FirstOrDefault(x => x.StartsWith("--ceiling-mode=", StringComparison.OrdinalIgnoreCase));
    if (string.IsNullOrWhiteSpace(raw))
    {
        return "none";
    }

    var value = raw["--ceiling-mode=".Length..].Trim().ToLowerInvariant();
    return value is "validate" or "persist"
        ? value
        : "none";
}

internal sealed record TransactionExecutionConsoleOptions(
    bool Verbose,
    bool ShowOverrides,
    bool ShowResults);

internal sealed record TransactionWorkflowSummary(
    int TransactionId,
    int StageCount,
    int LegacyPersistCount,
    bool HasWarnings,
    bool Success,
    string? FailureMessage);
