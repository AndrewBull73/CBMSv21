namespace CBMS.ScenarioEngine.Core.Models;

public sealed record SchemaProbeResult(
    IReadOnlyList<string> PresentTables,
    IReadOnlyList<string> MissingTables)
{
    public bool IsReady => MissingTables.Count == 0;
}
