namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcDependency(
    int CalcDependencyId,
    int NodeId,
    int DependsOnNodeId,
    string DependencyTypeCode,
    int OffsetPeriods,
    bool RequiredFlag);
