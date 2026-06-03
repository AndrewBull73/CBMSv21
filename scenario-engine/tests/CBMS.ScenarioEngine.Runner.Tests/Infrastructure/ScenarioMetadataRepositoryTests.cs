using CBMS.ScenarioEngine.Core.Models;
using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class ScenarioMetadataRepositoryTests
{
    [Fact]
    public void BuildScenarioLineage_ReturnsChildToRootOrder()
    {
        var scenarios = CreateScenarioHierarchy();
        var selectedScenario = scenarios.Single(x => x.ScenarioCode == "WHATIF");

        var lineage = ScenarioMetadataRepository.BuildScenarioLineage(scenarios, selectedScenario);

        Assert.Equal(["WHATIF", "FORECAST", "BASE"], lineage.Select(x => x.ScenarioCode).ToArray());
    }

    [Fact]
    public void ResolveEffectiveScenarioNodeValues_PrefersNearestOverrideInLineage()
    {
        var scenarios = CreateScenarioHierarchy();
        var selectedScenario = scenarios.Single(x => x.ScenarioCode == "WHATIF");
        var lineage = ScenarioMetadataRepository.BuildScenarioLineage(scenarios, selectedScenario);
        var rows = new[]
        {
            new ScenarioNodeValue(1, 100, 202601, 10, 10m, "BASE", false),
            new ScenarioNodeValue(2, 100, 202601, 10, 12m, "OVERRIDE", true),
            new ScenarioNodeValue(3, 100, 202601, 10, 15m, "OVERRIDE", true),
            new ScenarioNodeValue(1, 100, 202602, 10, 20m, "BASE", false),
            new ScenarioNodeValue(2, 100, 202602, 10, 22m, "OVERRIDE", true),
            new ScenarioNodeValue(1, 100, 202601, 20, 100m, "BASE", false),
        };

        var effective = ScenarioMetadataRepository.ResolveEffectiveScenarioNodeValues(lineage, rows);

        AssertValue(effective, 202601, 10, 15m, 3);
        AssertValue(effective, 202602, 10, 22m, 2);
        AssertValue(effective, 202601, 20, 100m, 1);
    }

    [Fact]
    public void ResolveEffectiveScenarioNodeValues_IgnoresRowsOutsideLineage()
    {
        var scenarios = CreateScenarioHierarchy();
        var selectedScenario = scenarios.Single(x => x.ScenarioCode == "FORECAST");
        var lineage = ScenarioMetadataRepository.BuildScenarioLineage(scenarios, selectedScenario);
        var rows = new[]
        {
            new ScenarioNodeValue(1, 100, 202601, 10, 10m, "BASE", false),
            new ScenarioNodeValue(2, 100, 202601, 10, 12m, "OVERRIDE", true),
            new ScenarioNodeValue(99, 100, 202601, 10, 999m, "OVERRIDE", true),
        };

        var effective = ScenarioMetadataRepository.ResolveEffectiveScenarioNodeValues(lineage, rows);

        Assert.Single(effective);
        AssertValue(effective, 202601, 10, 12m, 2);
    }

    [Fact]
    public void BuildScenarioLineage_ThrowsWhenParentIsMissing()
    {
        var scenarios = new[]
        {
            new CalcScenario(2, 1, 99, "WHATIF", "What If", "WHATIF", "ACTIVE", 20, true),
        };

        var ex = Assert.Throws<InvalidOperationException>(() =>
            ScenarioMetadataRepository.BuildScenarioLineage(scenarios, scenarios[0]));

        Assert.Contains("missing parent scenario ID 99", ex.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void BuildScenarioLineage_ThrowsWhenCycleDetected()
    {
        var scenarios = new[]
        {
            new CalcScenario(1, 1, 2, "BASE", "Base", "BASE", "ACTIVE", 10, true),
            new CalcScenario(2, 1, 1, "WHATIF", "What If", "WHATIF", "ACTIVE", 20, true),
        };

        var ex = Assert.Throws<InvalidOperationException>(() =>
            ScenarioMetadataRepository.BuildScenarioLineage(scenarios, scenarios[1]));

        Assert.Contains("inheritance cycle", ex.Message, StringComparison.OrdinalIgnoreCase);
    }

    private static CalcScenario[] CreateScenarioHierarchy()
        =>
        [
            new CalcScenario(1, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true),
            new CalcScenario(2, 1, 1, "FORECAST", "Forecast", "FORECAST", "ACTIVE", 20, true),
            new CalcScenario(3, 1, 2, "WHATIF", "What If", "WHATIF", "ACTIVE", 30, true),
        ];

    private static void AssertValue(
        IReadOnlyList<ScenarioNodeValue> rows,
        int periodId,
        int nodeId,
        decimal expectedValue,
        int expectedScenarioId)
    {
        var row = rows.Single(x => x.PeriodId == periodId && x.NodeId == nodeId);
        Assert.Equal(expectedValue, row.ValueDecimal);
        Assert.Equal(expectedScenarioId, row.ScenarioId);
    }
}
