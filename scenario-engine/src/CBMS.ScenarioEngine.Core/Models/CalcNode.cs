namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcNode(
    int NodeId,
    int CalcModelId,
    string NodeCode,
    string NodeName,
    string NodeTypeCode,
    string NodeCategoryCode,
    string DataTypeCode,
    string? UnitOfMeasureCode,
    byte DecimalScale,
    int NodeOrder,
    bool OutputFlag,
    bool ActiveFlag);
