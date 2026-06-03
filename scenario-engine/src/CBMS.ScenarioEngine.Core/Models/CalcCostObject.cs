namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcCostObject(
    int CostObjectId,
    int CalcModelId,
    int? ParentCostObjectId,
    string CostObjectCode,
    string CostObjectName,
    string CostObjectTypeCode,
    bool ActiveFlag);
