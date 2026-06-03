using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class LegacyTransactionResultProjectorTests
{
    [Fact]
    public void Project_UsesFormulaOutputsWhenPresent()
    {
        var report = new ExecutionReport(
            ["BP1", "BPQ1", "BPTotal", "BPOY1"],
            [
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 1, "BP1", 100m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 2, "BP2", 200m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 3, "BP3", 300m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 4, "BP12", 1200m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 5, "BPQ1", 999m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 6, "BPTotal", 7777m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 7, "BPOY1", 8888m),
            ],
            []);

        var projection = LegacyTransactionResultProjector.Project(report);

        Assert.Equal(100m, projection.Monthly["BP1"]);
        Assert.Equal(7777m, projection.Total);
        Assert.Equal(999m, projection.Quarterly["BPQ1"]);
        Assert.Equal(8888m, projection.OutYears["BPOY1"]);
    }

    [Fact]
    public void Project_FallsBackForQuarterAndOutYearValues()
    {
        var report = new ExecutionReport(
            ["BP1", "BP2", "BP3", "BP12"],
            [
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 1, "BP1", 10m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 2, "BP2", 20m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 3, "BP3", 30m),
                new ExecutionValue(1000, "GLOBAL", 1, "TXN", 4, "BP12", 99m),
            ],
            []);

        var projection = LegacyTransactionResultProjector.Project(report);

        Assert.Equal(60m, projection.Quarterly["BPQ1"]);
        Assert.Equal(99m, projection.OutYears["BPOY1"]);
        Assert.Contains(projection.PeriodRows, x => x.PeriodCode == "BPTotal");
        Assert.Contains("\"BP1\":10", projection.ResultJson);
    }
}
