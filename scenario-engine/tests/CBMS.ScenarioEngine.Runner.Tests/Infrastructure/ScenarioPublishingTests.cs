using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class ScenarioPublishingTests
{
    [Fact]
    public void EnsurePublishable_AllowsCompletedRunWithResults()
    {
        var run = new PublishableRun(12, 1, "SCENARIO_V1_DEMO", 1, "BASE", "COMPLETED", 132);

        ScenarioMetadataRepository.EnsurePublishable(run);
    }

    [Fact]
    public void EnsurePublishable_ThrowsForNonCompletedRun()
    {
        var run = new PublishableRun(12, 1, "SCENARIO_V1_DEMO", 1, "BASE", "RUNNING", 132);

        var ex = Assert.Throws<InvalidOperationException>(() =>
            ScenarioMetadataRepository.EnsurePublishable(run));

        Assert.Contains("not publishable", ex.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void EnsurePublishable_ThrowsWhenRunHasNoResults()
    {
        var run = new PublishableRun(12, 1, "SCENARIO_V1_DEMO", 1, "BASE", "COMPLETED", 0);

        var ex = Assert.Throws<InvalidOperationException>(() =>
            ScenarioMetadataRepository.EnsurePublishable(run));

        Assert.Contains("no result rows", ex.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void EnsurePublishable_ThrowsWhenRunIsMissing()
    {
        var ex = Assert.Throws<InvalidOperationException>(() =>
            ScenarioMetadataRepository.EnsurePublishable(null));

        Assert.Contains("No publishable run", ex.Message, StringComparison.OrdinalIgnoreCase);
    }
}
