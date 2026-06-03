using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class LegacyCalculationChainOrchestratorTests
{
    [Fact]
    public void CreateStageTransactionContext_OnlyChangesCalculationId()
    {
        var root = new TransactionInputRecord(
            68068,
            2,
            2026,
            5,
            "11",
            "APS1",
            new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase)
            {
                ["CalculationID"] = 2,
                ["TransactionTypeCode"] = "11",
                ["UOMCodeInpC"] = "APS1",
            });

        var definition = new LegacyCalculationDefinition(
            2026,
            3,
            "PENSION",
            4,
            true,
            "Y",
            "PEN",
            "PENSION",
            "0",
            "11",
            "412101",
            "PENSION",
            Enumerable.Repeat<string?>(null, 20).ToArray());

        var stage = LegacyCalculationChainOrchestrator.CreateStageTransactionContext(root, definition);

        Assert.Equal(3, stage.CalculationId);
        Assert.Equal("11", stage.TransactionTypeCode);
        Assert.Equal("APS1", stage.UomCodeInpC);
        Assert.Equal(3, stage.Values["CalculationID"]);
    }
}
