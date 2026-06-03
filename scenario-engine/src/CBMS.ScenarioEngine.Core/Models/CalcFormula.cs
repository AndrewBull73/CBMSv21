namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcFormula(
    int CalcFormulaId,
    int NodeId,
    string ExpressionText,
    string ExpressionLanguageCode,
    string ParserVersion,
    bool ActiveFlag);
