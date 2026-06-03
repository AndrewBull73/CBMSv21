namespace CBMS.ScenarioEngine.Core.Models;

public sealed record ScenarioNodeValue(
    int ScenarioId,
    int CostObjectId,
    int PeriodId,
    int NodeId,
    decimal? ValueDecimal,
    string ValueSourceCode,
    bool OverriddenFlag);
