namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcMetadataBundle(
    CalcModel Model,
    IReadOnlyList<CalcScenario> Scenarios,
    IReadOnlyList<CalcPeriod> Periods,
    IReadOnlyList<CalcCostObject> CostObjects,
    IReadOnlyList<CalcNode> Nodes,
    IReadOnlyList<CalcFormula> Formulas,
    IReadOnlyList<CalcDependency> Dependencies);
