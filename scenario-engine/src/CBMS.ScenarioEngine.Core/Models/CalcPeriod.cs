namespace CBMS.ScenarioEngine.Core.Models;

public sealed record CalcPeriod(
    int PeriodId,
    int CalcModelId,
    string PeriodCode,
    int? FiscalYearId,
    int? PeriodNo,
    string PeriodTypeCode,
    int SequenceNo,
    bool ActiveFlag);
