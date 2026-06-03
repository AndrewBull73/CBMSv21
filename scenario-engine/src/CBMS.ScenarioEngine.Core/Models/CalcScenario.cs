namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcScenario(
    int ScenarioId,
    int CalcModelId,
    int? ParentScenarioId,
    string ScenarioCode,
    string ScenarioName,
    string ScenarioTypeCode,
    string ScenarioStatusCode,
    int SortOrder,
    bool ActiveFlag);
