using CBMS.ScenarioEngine.Core.Models;
using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class ScenarioExecutionEngineTests
{
    [Fact]
    public void Execute_ResolvesRelativeAndAbsolutePeriodTokens()
    {
        var selection = BuildSelection(
            new[]
            {
                new CalcNode(10, 1, "RevenueInput", "Revenue Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true),
                new CalcNode(20, 1, "RevenuePreviousPeriod", "Revenue Previous Period", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
                new CalcNode(30, 1, "RevenueNextPeriod", "Revenue Next Period", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 110, true, true),
                new CalcNode(40, 1, "RevenueMarchBaseline", "Revenue March Baseline", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 120, true, true),
                new CalcNode(50, 1, "RevenueThreeMonthWindow", "Revenue Three Month Window", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 130, true, true),
            },
            new[]
            {
                new CalcFormula(1, 20, "@RevenueInput[-1]@", "TOKEN_ARITH", "v1", true),
                new CalcFormula(2, 30, "@RevenueInput[+1]@", "TOKEN_ARITH", "v1", true),
                new CalcFormula(3, 40, "@RevenueInput[2026-03]@", "TOKEN_ARITH", "v1", true),
                new CalcFormula(4, 50, "@RevenueInput[-1]@ + @RevenueInput@ + @RevenueInput[+1]@", "TOKEN_ARITH", "v1", true),
            },
            new[]
            {
                new CalcDependency(1, 20, 10, "VALUE", -1, false),
                new CalcDependency(2, 30, 10, "VALUE", 1, false),
                new CalcDependency(3, 40, 10, "VALUE", 0, true),
                new CalcDependency(4, 50, 10, "VALUE", -1, false),
                new CalcDependency(5, 50, 10, "VALUE", 0, true),
                new CalcDependency(6, 50, 10, "VALUE", 1, false),
            },
            new Dictionary<string, decimal>
            {
                ["2026-01"] = 100m,
                ["2026-02"] = 200m,
                ["2026-03"] = 300m,
            });

        var report = new ScenarioExecutionEngine().Execute(selection);

        Assert.Empty(report.Issues);
        AssertValue(report, "2026-01", "RevenuePreviousPeriod", 0m);
        AssertValue(report, "2026-02", "RevenuePreviousPeriod", 100m);
        AssertValue(report, "2026-03", "RevenuePreviousPeriod", 200m);
        AssertValue(report, "2026-01", "RevenueNextPeriod", 200m);
        AssertValue(report, "2026-02", "RevenueNextPeriod", 300m);
        AssertValue(report, "2026-03", "RevenueNextPeriod", 0m);
        AssertValue(report, "2026-01", "RevenueMarchBaseline", 300m);
        AssertValue(report, "2026-02", "RevenueMarchBaseline", 300m);
        AssertValue(report, "2026-03", "RevenueMarchBaseline", 300m);
        AssertValue(report, "2026-01", "RevenueThreeMonthWindow", 300m);
        AssertValue(report, "2026-02", "RevenueThreeMonthWindow", 600m);
        AssertValue(report, "2026-03", "RevenueThreeMonthWindow", 500m);
    }

    [Fact]
    public void Execute_SupportsShiftedSelfDependencyWithoutCycle()
    {
        var selection = BuildSelection(
            new[]
            {
                new CalcNode(10, 1, "RevenueInput", "Revenue Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true),
                new CalcNode(20, 1, "RollingRevenue", "Rolling Revenue", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
            },
            new[]
            {
                new CalcFormula(1, 20, "@RollingRevenue[-1]@ + @RevenueInput@", "TOKEN_ARITH", "v1", true),
            },
            new[]
            {
                new CalcDependency(1, 20, 20, "VALUE", -1, false),
                new CalcDependency(2, 20, 10, "VALUE", 0, true),
            },
            new Dictionary<string, decimal>
            {
                ["2026-01"] = 100m,
                ["2026-02"] = 200m,
                ["2026-03"] = 300m,
            });

        var report = new ScenarioExecutionEngine().Execute(selection);

        Assert.Empty(report.Issues);
        Assert.Equal(["RollingRevenue"], report.ExecutionOrder);
        AssertValue(report, "2026-01", "RollingRevenue", 100m);
        AssertValue(report, "2026-02", "RollingRevenue", 300m);
        AssertValue(report, "2026-03", "RollingRevenue", 600m);
    }

    private static ScenarioSelection BuildSelection(
        IReadOnlyList<CalcNode> nodes,
        IReadOnlyList<CalcFormula> formulas,
        IReadOnlyList<CalcDependency> dependencies,
        IReadOnlyDictionary<string, decimal> revenueByPeriodCode)
    {
        var model = new CalcModel(1, "TEST_MODEL", "Test Model", 1, "ACTIVE", true);
        var scenario = new CalcScenario(1, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true);
        var periods = new[]
        {
            new CalcPeriod(1, 1, "2026-01", 2026, 1, "MONTH", 1, true),
            new CalcPeriod(2, 1, "2026-02", 2026, 2, "MONTH", 2, true),
            new CalcPeriod(3, 1, "2026-03", 2026, 3, "MONTH", 3, true),
        };
        var costObjects = new[]
        {
            new CalcCostObject(1, 1, null, "GLOBAL", "Global", "GENERIC", true),
        };
        var inputNode = nodes.Single(x => x.NodeCode == "RevenueInput");
        var inputValues = periods
            .Select(period => new ScenarioNodeValue(
                scenario.ScenarioId,
                costObjects[0].CostObjectId,
                period.PeriodId,
                inputNode.NodeId,
                revenueByPeriodCode[period.PeriodCode],
                "BASE",
                false))
            .ToArray();

        var bundle = new CalcMetadataBundle(
            model,
            new[] { scenario },
            periods,
            costObjects,
            nodes,
            formulas,
            dependencies);

        return new ScenarioSelection(bundle, scenario, inputValues);
    }

    private static void AssertValue(
        ExecutionReport report,
        string periodCode,
        string nodeCode,
        decimal expected)
    {
        var row = report.Results.Single(x => x.PeriodCode == periodCode && x.NodeCode == nodeCode);
        Assert.Equal(expected, row.Value);
    }
}
