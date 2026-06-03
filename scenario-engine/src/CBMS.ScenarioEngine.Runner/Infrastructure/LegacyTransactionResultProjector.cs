using System.Text.Json;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class LegacyTransactionResultProjector
{
    private static readonly string[] MonthlyCodes =
    [
        "BP1", "BP2", "BP3", "BP4", "BP5", "BP6",
        "BP7", "BP8", "BP9", "BP10", "BP11", "BP12",
    ];

    private static readonly string[] QuarterlyCodes =
    [
        "BPQ1", "BPQ2", "BPQ3", "BPQ4",
    ];

    private static readonly string[] OutYearCodes =
    [
        "BPOY1", "BPOY2", "BPOY3", "BPOY4", "BPOY5",
        "BPOY6", "BPOY7", "BPOY8", "BPOY9", "BPOY10",
    ];

    public static LegacyTransactionResultProjection Project(ExecutionReport report)
    {
        var byNodeCode = report.Results
            .GroupBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase)
            .ToDictionary(
                x => x.Key,
                x => x.First().Value,
                StringComparer.OrdinalIgnoreCase);

        var monthly = MonthlyCodes.ToDictionary(
            code => code,
            code => ResolveValue(byNodeCode, code),
            StringComparer.OrdinalIgnoreCase);

        var total = ResolveValue(byNodeCode, "BPTotal", monthly.Values.Sum());
        var quarterly = new Dictionary<string, decimal>(StringComparer.OrdinalIgnoreCase)
        {
            ["BPQ1"] = ResolveValue(byNodeCode, "BPQ1", monthly["BP1"] + monthly["BP2"] + monthly["BP3"]),
            ["BPQ2"] = ResolveValue(byNodeCode, "BPQ2", monthly["BP4"] + monthly["BP5"] + monthly["BP6"]),
            ["BPQ3"] = ResolveValue(byNodeCode, "BPQ3", monthly["BP7"] + monthly["BP8"] + monthly["BP9"]),
            ["BPQ4"] = ResolveValue(byNodeCode, "BPQ4", monthly["BP10"] + monthly["BP11"] + monthly["BP12"]),
        };

        var outYearBase = monthly["BP12"];
        var outYears = OutYearCodes.ToDictionary(
            code => code,
            code => ResolveValue(byNodeCode, code, outYearBase),
            StringComparer.OrdinalIgnoreCase);

        var periodRows = MonthlyCodes
            .Concat(["BPTotal"])
            .Concat(QuarterlyCodes)
            .Concat(OutYearCodes)
            .Select(code => new LegacyTransactionPeriodAmount(code, ResolvePeriodAmount(code, monthly, total, quarterly, outYears)))
            .ToArray();

        var resultJson = JsonSerializer.Serialize(new
        {
            monthly,
            total,
            quarterly,
            outyears = outYears,
        });

        return new LegacyTransactionResultProjection(
            monthly,
            total,
            quarterly,
            outYears,
            periodRows,
            resultJson);
    }

    private static decimal ResolvePeriodAmount(
        string periodCode,
        IReadOnlyDictionary<string, decimal> monthly,
        decimal total,
        IReadOnlyDictionary<string, decimal> quarterly,
        IReadOnlyDictionary<string, decimal> outYears)
    {
        if (monthly.TryGetValue(periodCode, out var monthlyValue))
        {
            return monthlyValue;
        }

        if (string.Equals(periodCode, "BPTotal", StringComparison.OrdinalIgnoreCase))
        {
            return total;
        }

        if (quarterly.TryGetValue(periodCode, out var quarterValue))
        {
            return quarterValue;
        }

        return outYears.TryGetValue(periodCode, out var outYearValue)
            ? outYearValue
            : 0m;
    }

    private static decimal ResolveValue(
        IReadOnlyDictionary<string, decimal> byNodeCode,
        string nodeCode,
        decimal fallback = 0m)
        => byNodeCode.TryGetValue(nodeCode, out var value)
            ? value
            : fallback;
}

internal sealed record LegacyTransactionResultProjection(
    IReadOnlyDictionary<string, decimal> Monthly,
    decimal Total,
    IReadOnlyDictionary<string, decimal> Quarterly,
    IReadOnlyDictionary<string, decimal> OutYears,
    IReadOnlyList<LegacyTransactionPeriodAmount> PeriodRows,
    string ResultJson);

internal sealed record LegacyTransactionPeriodAmount(
    string PeriodCode,
    decimal Amount);
