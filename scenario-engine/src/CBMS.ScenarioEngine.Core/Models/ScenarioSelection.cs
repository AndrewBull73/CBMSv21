namespace CBMS.ScenarioEngine.Core.Models;

public sealed record ScenarioSelection(
    CalcMetadataBundle Bundle,
    CalcScenario Scenario,
    IReadOnlyList<ScenarioNodeValue> InputValues);
