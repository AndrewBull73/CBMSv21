using CBMS.ScenarioEngine.Core.Models;
using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class TransactionScenarioBridgeAdapterTests
{
    [Fact]
    public void ResolveBridge_PrefersMostSpecificMatch()
    {
        var transaction = new TransactionInputRecord(
            68068,
            2,
            2026,
            5,
            "11",
            "APS1",
            new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase));

        var generic = new CalcTransactionBridge(1, 10, "MODEL_A", 100, "BASE", 1000, null, 2026, null, "11", null, 50, true);
        var specific = new CalcTransactionBridge(2, 10, "MODEL_A", 100, "BASE", 1000, 2, 2026, null, "11", "APS1", 10, true);

        var selected = TransactionScenarioBridgeAdapter.ResolveBridge([generic, specific], transaction, null);

        Assert.NotNull(selected);
        Assert.Equal(2, selected!.CalcTransactionBridgeId);
    }

    [Fact]
    public void ApplyTransactionOverrides_OverlaysPatternMappedInputs()
    {
        var bundle = new CalcMetadataBundle(
            new CalcModel(1, "MODEL_A", "Model A", 1, "ACTIVE", true),
            [new CalcScenario(10, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true)],
            [
                new CalcPeriod(101, 1, "2026-01", 2026, 1, "MONTH", 1, true),
                new CalcPeriod(102, 1, "2026-02", 2026, 2, "MONTH", 2, true),
            ],
            [new CalcCostObject(1000, 1, null, "GLOBAL", "Global", "GENERIC", true)],
            [new CalcNode(2000, 1, "RevenueVolume", "Revenue Volume", "INPUT", "DRIVER", "DECIMAL", "UNIT", 6, 10, false, true)],
            [],
            []);

        var selection = new ScenarioSelection(
            bundle,
            bundle.Scenarios[0],
            [
                new ScenarioNodeValue(10, 1000, 101, 2000, 1000m, "BASE", false),
                new ScenarioNodeValue(10, 1000, 102, 2000, 1000m, "BASE", false),
            ]);

        var bridge = new CalcTransactionBridge(1, 1, "MODEL_A", 10, "BASE", 1000, 2, 2026, 5, "11", "APS1", 10, true);
        var mapping = new CalcTransactionNodeMap(1, 1, 2000, "COLUMN_PATTERN", "BP{PeriodNo}InpN", null, false, true);
        var transaction = new TransactionInputRecord(
            68068,
            2,
            2026,
            5,
            "11",
            "APS1",
            new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase)
            {
                ["BP1InpN"] = 1200m,
                ["BP2InpN"] = 1250m,
            });

        var adapted = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(selection, bridge, [mapping], transaction);

        Assert.Empty(adapted.Issues);
        Assert.Equal(2, adapted.Overrides.Count);
        Assert.Equal(1200m, adapted.Selection.InputValues.Single(x => x.PeriodId == 101 && x.NodeId == 2000).ValueDecimal);
        Assert.Equal(1250m, adapted.Selection.InputValues.Single(x => x.PeriodId == 102 && x.NodeId == 2000).ValueDecimal);
    }

    [Fact]
    public void ApplyTransactionOverrides_ReportsMissingRequiredValue()
    {
        var bundle = new CalcMetadataBundle(
            new CalcModel(1, "MODEL_A", "Model A", 1, "ACTIVE", true),
            [new CalcScenario(10, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true)],
            [new CalcPeriod(101, 1, "2026-01", 2026, 1, "MONTH", 1, true)],
            [new CalcCostObject(1000, 1, null, "GLOBAL", "Global", "GENERIC", true)],
            [new CalcNode(2000, 1, "RevenueVolume", "Revenue Volume", "INPUT", "DRIVER", "DECIMAL", "UNIT", 6, 10, false, true)],
            [],
            []);

        var selection = new ScenarioSelection(
            bundle,
            bundle.Scenarios[0],
            [new ScenarioNodeValue(10, 1000, 101, 2000, 1000m, "BASE", false)]);

        var bridge = new CalcTransactionBridge(1, 1, "MODEL_A", 10, "BASE", 1000, 2, 2026, 5, "11", "APS1", 10, true);
        var mapping = new CalcTransactionNodeMap(1, 1, 2000, "COLUMN_PATTERN", "BP{PeriodNo}InpN", null, true, true);
        var transaction = new TransactionInputRecord(
            68068,
            2,
            2026,
            5,
            "11",
            "APS1",
            new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase));

        var adapted = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(selection, bridge, [mapping], transaction);

        Assert.Single(adapted.Issues);
        Assert.Contains("Required transaction bridge value was not found", adapted.Issues[0], StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void ApplyTransactionOverrides_ResolvesNamedChainResult()
    {
        var bundle = new CalcMetadataBundle(
            new CalcModel(1, "MODEL_A", "Model A", 1, "ACTIVE", true),
            [new CalcScenario(10, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true)],
            [new CalcPeriod(101, 1, "2026-01", 2026, 1, "MONTH", 1, true)],
            [new CalcCostObject(1000, 1, null, "GLOBAL", "Global", "GENERIC", true)],
            [new CalcNode(2000, 1, "WagesInput", "Wages Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true)],
            [],
            []);

        var selection = new ScenarioSelection(
            bundle,
            bundle.Scenarios[0],
            []);

        var bridge = new CalcTransactionBridge(1, 1, "MODEL_A", 10, "BASE", 1000, 3, 2026, 5, "11", "APS1", 10, true);
        var mapping = new CalcTransactionNodeMap(1, 1, 2000, "CHAIN_RESULT", "WAGES|BP1", null, true, true);
        var transaction = new TransactionInputRecord(
            68068,
            3,
            2026,
            5,
            "11",
            "APS1",
            new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase));
        var priorReports = new Dictionary<string, ExecutionReport>(StringComparer.OrdinalIgnoreCase)
        {
            ["WAGES"] = new ExecutionReport(
                ["BP1"],
                [new ExecutionValue(1000, "GLOBAL", 101, "2026-01", 9000, "BP1", 1234m)],
                [])
        };

        var adapted = TransactionScenarioBridgeAdapter.ApplyTransactionOverrides(
            selection,
            bridge,
            [mapping],
            transaction,
            previousReport: null,
            priorReportsByName: priorReports);

        Assert.Empty(adapted.Issues);
        Assert.Single(adapted.Overrides);
        Assert.Equal(1234m, adapted.Selection.InputValues.Single().ValueDecimal);
    }
}
