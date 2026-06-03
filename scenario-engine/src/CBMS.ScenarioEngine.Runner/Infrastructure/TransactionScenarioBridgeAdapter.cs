using System.Globalization;
using CBMS.ScenarioEngine.Core.Models;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class TransactionScenarioBridgeAdapter
{
    public static CalcTransactionBridge? ResolveBridge(
        IReadOnlyList<CalcTransactionBridge> bridges,
        TransactionInputRecord transaction,
        string? modelCode)
    {
        var matches = bridges
            .Where(x => x.ActiveFlag)
            .Where(x => string.IsNullOrWhiteSpace(modelCode) ||
                        string.Equals(x.ModelCode, modelCode, StringComparison.OrdinalIgnoreCase))
            .Where(x => !x.LegacyCalculationId.HasValue || x.LegacyCalculationId.Value == transaction.CalculationId)
            .Where(x => !x.FiscalYearId.HasValue || x.FiscalYearId.Value == transaction.FiscalYearId)
            .Where(x => !x.VersionId.HasValue || x.VersionId.Value == transaction.VersionId)
            .Where(x => string.IsNullOrWhiteSpace(x.TransactionTypeCode) ||
                        string.Equals(x.TransactionTypeCode, transaction.TransactionTypeCode, StringComparison.OrdinalIgnoreCase))
            .Where(x => string.IsNullOrWhiteSpace(x.UomCodeInpC) ||
                        string.Equals(x.UomCodeInpC, transaction.UomCodeInpC, StringComparison.OrdinalIgnoreCase))
            .OrderByDescending(GetSpecificityScore)
            .ThenBy(x => x.PriorityNo)
            .ThenBy(x => x.CalcTransactionBridgeId)
            .ToArray();

        return matches.FirstOrDefault();
    }

    public static TransactionExecutionSelection ApplyTransactionOverrides(
        ScenarioSelection baseSelection,
        CalcTransactionBridge bridge,
        IReadOnlyList<CalcTransactionNodeMap> nodeMaps,
        TransactionInputRecord transaction,
        ExecutionReport? previousReport = null,
        IReadOnlyDictionary<string, ExecutionReport>? priorReportsByName = null)
    {
        var bundle = baseSelection.Bundle;
        var costObject = ResolveCostObject(bundle, bridge);
        var nodeById = bundle.Nodes.ToDictionary(x => x.NodeId);
        var activePeriods = bundle.Periods
            .Where(x => x.ActiveFlag)
            .OrderBy(x => x.SequenceNo)
            .ToArray();

        var values = baseSelection.InputValues.ToDictionary(
            x => (x.CostObjectId, x.PeriodId, x.NodeId),
            x => x);

        var overrides = new List<TransactionInputOverride>();
        var issues = new List<string>();

        foreach (var mapping in nodeMaps.Where(x => x.ActiveFlag))
        {
            if (!nodeById.TryGetValue(mapping.NodeId, out var node))
            {
                issues.Add(
                    $"Transaction bridge mapping {mapping.CalcTransactionNodeMapId} references missing node ID {mapping.NodeId}.");
                continue;
            }

            foreach (var period in activePeriods)
            {
                var resolved = TryResolveDecimalValue(transaction, mapping, period, costObject, previousReport);
                resolved ??= TryResolveNamedPriorResult(transaction, mapping, period, costObject, priorReportsByName);
                if (resolved is null)
                {
                    if (mapping.RequiredFlag)
                    {
                        issues.Add(
                            $"Required transaction bridge value was not found for node '{node.NodeCode}' using source '{mapping.SourceName ?? mapping.SourceTypeCode}' period '{period.PeriodCode}'.");
                    }

                    continue;
                }

                var row = new ScenarioNodeValue(
                    baseSelection.Scenario.ScenarioId,
                    costObject.CostObjectId,
                    period.PeriodId,
                    node.NodeId,
                    resolved.Value,
                    "TRANSACTION",
                    true);

                values[(row.CostObjectId, row.PeriodId, row.NodeId)] = row;
                overrides.Add(new TransactionInputOverride(
                    costObject.CostObjectId,
                    costObject.CostObjectCode,
                    period.PeriodId,
                    period.PeriodCode,
                    node.NodeId,
                    node.NodeCode,
                    mapping.SourceTypeCode,
                    resolved.SourceColumnName,
                    resolved.Value));
            }
        }

        var mergedSelection = new ScenarioSelection(
            bundle,
            baseSelection.Scenario,
            values.Values
                .OrderBy(x => x.CostObjectId)
                .ThenBy(x => x.PeriodId)
                .ThenBy(x => x.NodeId)
                .ToArray());

        return new TransactionExecutionSelection(
            transaction,
            bridge,
            mergedSelection,
            overrides
                .OrderBy(x => x.CostObjectCode, StringComparer.OrdinalIgnoreCase)
                .ThenBy(x => x.PeriodCode, StringComparer.OrdinalIgnoreCase)
                .ThenBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase)
                .ToArray(),
            issues);
    }

    internal static string ExpandSourceName(string pattern, CalcPeriod period)
        => pattern
            .Replace("{PeriodNo}", period.PeriodNo?.ToString(CultureInfo.InvariantCulture) ?? string.Empty, StringComparison.OrdinalIgnoreCase)
            .Replace("{PeriodCode}", period.PeriodCode, StringComparison.OrdinalIgnoreCase)
            .Replace("{SequenceNo}", period.SequenceNo.ToString(CultureInfo.InvariantCulture), StringComparison.OrdinalIgnoreCase);

    internal static decimal? ConvertToDecimal(object? rawValue)
    {
        if (rawValue is null || rawValue is DBNull)
        {
            return null;
        }

        return rawValue switch
        {
            decimal decimalValue => decimalValue,
            byte byteValue => byteValue,
            short shortValue => shortValue,
            int intValue => intValue,
            long longValue => longValue,
            float floatValue => Convert.ToDecimal(floatValue, CultureInfo.InvariantCulture),
            double doubleValue => Convert.ToDecimal(doubleValue, CultureInfo.InvariantCulture),
            string text when decimal.TryParse(text, NumberStyles.Any, CultureInfo.InvariantCulture, out var parsed) => parsed,
            _ => null,
        };
    }

    private static int GetSpecificityScore(CalcTransactionBridge bridge)
    {
        var score = 0;
        if (bridge.LegacyCalculationId.HasValue)
        {
            score++;
        }

        if (bridge.FiscalYearId.HasValue)
        {
            score++;
        }

        if (bridge.VersionId.HasValue)
        {
            score++;
        }

        if (!string.IsNullOrWhiteSpace(bridge.TransactionTypeCode))
        {
            score++;
        }

        if (!string.IsNullOrWhiteSpace(bridge.UomCodeInpC))
        {
            score++;
        }

        return score;
    }

    private static CalcCostObject ResolveCostObject(CalcMetadataBundle bundle, CalcTransactionBridge bridge)
    {
        if (bridge.CostObjectId.HasValue)
        {
            var explicitCostObject = bundle.CostObjects.FirstOrDefault(x => x.CostObjectId == bridge.CostObjectId.Value);
            if (explicitCostObject is null)
            {
                throw new InvalidOperationException(
                    $"Transaction bridge {bridge.CalcTransactionBridgeId} references missing CostObjectID {bridge.CostObjectId.Value}.");
            }

            return explicitCostObject;
        }

        var activeCostObjects = bundle.CostObjects.Where(x => x.ActiveFlag).ToArray();
        if (activeCostObjects.Length == 1)
        {
            return activeCostObjects[0];
        }

        throw new InvalidOperationException(
            $"Transaction bridge {bridge.CalcTransactionBridgeId} does not specify a CostObjectID, and model '{bundle.Model.ModelCode}' has {activeCostObjects.Length} active cost objects.");
    }

    private static ResolvedTransactionValue? TryResolveDecimalValue(
        TransactionInputRecord transaction,
        CalcTransactionNodeMap mapping,
        CalcPeriod period,
        CalcCostObject costObject,
        ExecutionReport? previousReport)
    {
        return mapping.SourceTypeCode.ToUpperInvariant() switch
        {
            "CONSTANT" => mapping.ConstantDecimal.HasValue
                ? new ResolvedTransactionValue(null, mapping.ConstantDecimal.Value)
                : null,
            "COLUMN" => ResolveColumnValue(transaction, mapping.SourceName),
            "COLUMN_PATTERN" => ResolveColumnValue(transaction, mapping.SourceName is null ? null : ExpandSourceName(mapping.SourceName, period)),
            "PREVIOUS_RESULT" => ResolvePreviousResultValue(previousReport, costObject.CostObjectCode, period.PeriodCode, mapping.SourceName),
            "PREVIOUS_RESULT_PATTERN" => ResolvePreviousResultValue(previousReport, costObject.CostObjectCode, period.PeriodCode, mapping.SourceName is null ? null : ExpandSourceName(mapping.SourceName, period)),
            _ => null,
        };
    }

    private static ResolvedTransactionValue? TryResolveNamedPriorResult(
        TransactionInputRecord transaction,
        CalcTransactionNodeMap mapping,
        CalcPeriod period,
        CalcCostObject costObject,
        IReadOnlyDictionary<string, ExecutionReport>? priorReportsByName)
    {
        if (priorReportsByName is null || string.IsNullOrWhiteSpace(mapping.SourceName))
        {
            return null;
        }

        return mapping.SourceTypeCode.ToUpperInvariant() switch
        {
            "CHAIN_RESULT" => ResolveNamedPriorResultValue(priorReportsByName, costObject.CostObjectCode, period.PeriodCode, mapping.SourceName),
            "CHAIN_RESULT_PATTERN" => ResolveNamedPriorResultValue(priorReportsByName, costObject.CostObjectCode, period.PeriodCode, ExpandSourceName(mapping.SourceName, period)),
            _ => null,
        };
    }

    private static ResolvedTransactionValue? ResolveColumnValue(TransactionInputRecord transaction, string? columnName)
    {
        if (string.IsNullOrWhiteSpace(columnName))
        {
            return null;
        }

        if (!transaction.Values.TryGetValue(columnName, out var rawValue))
        {
            return null;
        }

        var value = ConvertToDecimal(rawValue);
        return value.HasValue
            ? new ResolvedTransactionValue(columnName, value.Value)
            : null;
    }

    private static ResolvedTransactionValue? ResolvePreviousResultValue(
        ExecutionReport? previousReport,
        string costObjectCode,
        string periodCode,
        string? nodeCode)
    {
        if (previousReport is null || string.IsNullOrWhiteSpace(nodeCode))
        {
            return null;
        }

        var directMatch = previousReport.Results.FirstOrDefault(x =>
            string.Equals(x.CostObjectCode, costObjectCode, StringComparison.OrdinalIgnoreCase) &&
            string.Equals(x.PeriodCode, periodCode, StringComparison.OrdinalIgnoreCase) &&
            string.Equals(x.NodeCode, nodeCode, StringComparison.OrdinalIgnoreCase));

        if (directMatch is not null)
        {
            return new ResolvedTransactionValue(nodeCode, directMatch.Value);
        }

        var periodOnlyMatch = previousReport.Results.FirstOrDefault(x =>
            string.Equals(x.PeriodCode, periodCode, StringComparison.OrdinalIgnoreCase) &&
            string.Equals(x.NodeCode, nodeCode, StringComparison.OrdinalIgnoreCase));

        if (periodOnlyMatch is not null)
        {
            return new ResolvedTransactionValue(nodeCode, periodOnlyMatch.Value);
        }

        var uniqueNodeMatch = previousReport.Results
            .Where(x => string.Equals(x.NodeCode, nodeCode, StringComparison.OrdinalIgnoreCase))
            .DistinctBy(x => (x.CostObjectCode, x.PeriodCode, x.NodeCode))
            .ToArray();

        return uniqueNodeMatch.Length == 1
            ? new ResolvedTransactionValue(nodeCode, uniqueNodeMatch[0].Value)
            : null;
    }

    private static ResolvedTransactionValue? ResolveNamedPriorResultValue(
        IReadOnlyDictionary<string, ExecutionReport> priorReportsByName,
        string costObjectCode,
        string periodCode,
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

        if (!priorReportsByName.TryGetValue(parts[0], out var report))
        {
            return null;
        }

        return ResolvePreviousResultValue(report, costObjectCode, periodCode, parts[1]);
    }
}

internal sealed record TransactionInputRecord(
    int TransactionId,
    int? CalculationId,
    int? FiscalYearId,
    int? VersionId,
    string? TransactionTypeCode,
    string? UomCodeInpC,
    IReadOnlyDictionary<string, object?> Values);

internal sealed record CalcTransactionBridge(
    int CalcTransactionBridgeId,
    int CalcModelId,
    string ModelCode,
    int ScenarioId,
    string ScenarioCode,
    int? CostObjectId,
    int? LegacyCalculationId,
    int? FiscalYearId,
    int? VersionId,
    string? TransactionTypeCode,
    string? UomCodeInpC,
    int PriorityNo,
    bool ActiveFlag);

internal sealed record CalcTransactionNodeMap(
    int CalcTransactionNodeMapId,
    int CalcTransactionBridgeId,
    int NodeId,
    string SourceTypeCode,
    string? SourceName,
    decimal? ConstantDecimal,
    bool RequiredFlag,
    bool ActiveFlag);

internal sealed record TransactionInputOverride(
    int CostObjectId,
    string CostObjectCode,
    int PeriodId,
    string PeriodCode,
    int NodeId,
    string NodeCode,
    string SourceTypeCode,
    string? SourceColumnName,
    decimal Value);

internal sealed record TransactionExecutionSelection(
    TransactionInputRecord Transaction,
    CalcTransactionBridge Bridge,
    ScenarioSelection Selection,
    IReadOnlyList<TransactionInputOverride> Overrides,
    IReadOnlyList<string> Issues);

internal sealed record ResolvedTransactionValue(string? SourceColumnName, decimal Value);
