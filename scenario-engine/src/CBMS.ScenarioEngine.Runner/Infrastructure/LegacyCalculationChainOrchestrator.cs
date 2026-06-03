using System.Globalization;
using CBMS.ScenarioEngine.Core.Models;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class LegacyCalculationChainOrchestrator
{
    public static TransactionInputRecord CreateStageTransactionContext(
        TransactionInputRecord rootTransaction,
        LegacyCalculationDefinition calculation)
    {
        var values = new Dictionary<string, object?>(rootTransaction.Values, StringComparer.OrdinalIgnoreCase)
        {
            ["CalculationID"] = calculation.CalculationId,
        };

        return new TransactionInputRecord(
            rootTransaction.TransactionId,
            calculation.CalculationId,
            rootTransaction.FiscalYearId,
            rootTransaction.VersionId,
            rootTransaction.TransactionTypeCode,
            rootTransaction.UomCodeInpC,
            values);
    }

    public static TransactionInputRecord MaterializeChildTransaction(
        TransactionInputRecord parentTransaction,
        LegacyCalculationDefinition childCalculation,
        ExecutionReport parentReport)
    {
        var values = new Dictionary<string, object?>(parentTransaction.Values, StringComparer.OrdinalIgnoreCase);

        values["HeadRecordID"] = parentTransaction.Values.TryGetValue("HeadRecordID", out var headRecordId)
            ? headRecordId
            : parentTransaction.TransactionId;
        values["CalculationID"] = childCalculation.CalculationId;
        values["RecordTypeCode"] = "C";

        values["UOMCodeInpC"] = childCalculation.UomCodeDefault ?? GetString(values, "UOMCodeInpC");
        values["DataObjectCode"] = ChooseOverride(childCalculation.DataObjectCode, GetString(values, "DataObjectCode"));
        values["TransactionTypeCode"] = ChooseOverride(childCalculation.TransactionTypeCode, GetString(values, "TransactionTypeCode"));
        values["GLAccountCode"] = ChooseOverride(childCalculation.GlAccountCode, GetString(values, "GLAccountCode"));

        foreach (var segmentNumber in Enumerable.Range(1, 20))
        {
            var key = $"Segment{segmentNumber}Code";
            values[key] = ChooseOverride(
                childCalculation.Segments[segmentNumber - 1],
                GetString(values, key));
        }

        foreach (var monthNumber in Enumerable.Range(1, 12))
        {
            var nodeCode = $"BP{monthNumber}";
            values[$"BP{monthNumber}InpN"] = ResolveNodeValue(parentReport, nodeCode);
        }

        values["BPTotalInpN"] = ResolveNodeValue(parentReport, "BPTotal");

        foreach (var field in ResetFields)
        {
            if (values.ContainsKey(field))
            {
                values[field] = null;
            }
        }

        if (values.ContainsKey("CeilingFailedFlag"))
        {
            values["CeilingFailedFlag"] = 0;
        }

        return new TransactionInputRecord(
            0,
            childCalculation.CalculationId,
            parentTransaction.FiscalYearId,
            parentTransaction.VersionId,
            values.TryGetValue("TransactionTypeCode", out var transactionTypeCode) ? transactionTypeCode?.ToString() : null,
            values.TryGetValue("UOMCodeInpC", out var uomCodeInpC) ? uomCodeInpC?.ToString() : null,
            values);
    }

    private static decimal ResolveNodeValue(ExecutionReport report, string nodeCode)
    {
        var matches = report.Results
            .Where(x => string.Equals(x.NodeCode, nodeCode, StringComparison.OrdinalIgnoreCase))
            .ToArray();

        if (matches.Length == 0)
        {
            return 0m;
        }

        if (matches.Length == 1)
        {
            return matches[0].Value;
        }

        var ordered = matches
            .OrderBy(x => ExtractTrailingNumber(x.NodeCode))
            .ThenBy(x => x.PeriodCode, StringComparer.OrdinalIgnoreCase)
            .ToArray();

        return ordered[0].Value;
    }

    private static int ExtractTrailingNumber(string text)
    {
        var digits = new string(text.Reverse().TakeWhile(char.IsDigit).Reverse().ToArray());
        return int.TryParse(digits, NumberStyles.Integer, CultureInfo.InvariantCulture, out var parsed)
            ? parsed
            : int.MaxValue;
    }

    private static string? ChooseOverride(string? overrideValue, string? inheritedValue)
        => string.IsNullOrWhiteSpace(overrideValue) || overrideValue == "0"
            ? inheritedValue
            : overrideValue;

    private static string? GetString(IReadOnlyDictionary<string, object?> values, string key)
        => values.TryGetValue(key, out var value) ? value?.ToString() : null;

    private static readonly string[] ResetFields =
    [
        "PY5UOMRate", "PY4UOMRate", "PY3UOMRate", "PY2UOMRate", "PY1UOMRate",
        "PY5QtyInpN", "PY4QtyInpN", "PY3QtyInpN", "PY2QtyInpN", "PY1QtyInpN",
        "CeilingStatus", "CeilingStatusCheck", "CeilingErrorMessage", "CeilingDefinitionID", "CeilingEngine",
    ];
}

internal sealed record LegacyCalculationDefinition(
    int FiscalYearId,
    int CalculationId,
    string CalculationName,
    int? ChildCalculationId,
    bool GenerateTransaction,
    string? ChildCalculationInherit,
    string? ChildTransactionTypeCode,
    string? RateLookupCode,
    string? DataObjectCode,
    string? TransactionTypeCode,
    string? GlAccountCode,
    string? UomCodeDefault,
    IReadOnlyList<string?> Segments);
