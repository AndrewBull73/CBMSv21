namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcModel(
    int CalcModelId,
    string ModelCode,
    string ModelName,
    int ModelVersion,
    string StatusCode,
    bool ActiveFlag);
