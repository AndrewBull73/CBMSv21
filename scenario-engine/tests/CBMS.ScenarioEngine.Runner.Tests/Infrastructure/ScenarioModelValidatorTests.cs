using CBMS.ScenarioEngine.Core.Models;
using CBMS.ScenarioEngine.Runner.Infrastructure;
using Xunit;

namespace CBMS.ScenarioEngine.Runner.Tests.Infrastructure;

public sealed class ScenarioModelValidatorTests
{
    [Fact]
    public void Validate_ReturnsNoIssuesForWellFormedModel()
    {
        var bundle = BuildBundle(
            nodes:
            [
                new CalcNode(10, 1, "RevenueInput", "Revenue Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true),
                new CalcNode(20, 1, "Revenue", "Revenue", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
            ],
            formulas:
            [
                new CalcFormula(1, 20, "@RevenueInput@", "TOKEN_ARITH", "v1", true),
            ],
            dependencies:
            [
                new CalcDependency(1, 20, 10, "VALUE", 0, true),
            ]);

        var report = ScenarioModelValidator.Validate(bundle);

        Assert.Empty(report.Issues);
        Assert.False(report.HasErrors);
    }

    [Fact]
    public void Validate_FlagsTokenWithoutMatchingDependency()
    {
        var bundle = BuildBundle(
            nodes:
            [
                new CalcNode(10, 1, "RevenueInput", "Revenue Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true),
                new CalcNode(20, 1, "RevenuePreviousPeriod", "Revenue Previous Period", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
            ],
            formulas:
            [
                new CalcFormula(1, 20, "@RevenueInput[-1]@", "TOKEN_ARITH", "v1", true),
            ],
            dependencies:
            [
                new CalcDependency(1, 20, 10, "VALUE", 0, false),
            ]);

        var report = ScenarioModelValidator.Validate(bundle);

        Assert.True(report.HasErrors);
        Assert.Contains(report.Issues, x => x.Message.Contains("@RevenueInput[-1]@", StringComparison.Ordinal));
    }

    [Fact]
    public void Validate_FlagsFormulaSyntaxError()
    {
        var bundle = BuildBundle(
            nodes:
            [
                new CalcNode(10, 1, "RevenueInput", "Revenue Input", "INPUT", "DRIVER", "DECIMAL", "CUR", 6, 10, false, true),
                new CalcNode(20, 1, "BrokenFormula", "Broken Formula", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
            ],
            formulas:
            [
                new CalcFormula(1, 20, "@RevenueInput@ + )", "TOKEN_ARITH", "v1", true),
            ],
            dependencies:
            [
                new CalcDependency(1, 20, 10, "VALUE", 0, true),
            ]);

        var report = ScenarioModelValidator.Validate(bundle);

        Assert.True(report.HasErrors);
        Assert.Contains(report.Issues, x => x.Message.Contains("Formula syntax error", StringComparison.Ordinal));
    }

    [Fact]
    public void Validate_FlagsSamePeriodFormulaCycle()
    {
        var bundle = BuildBundle(
            nodes:
            [
                new CalcNode(10, 1, "FormulaA", "Formula A", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 100, true, true),
                new CalcNode(20, 1, "FormulaB", "Formula B", "FORMULA", "SUMMARY", "DECIMAL", "CUR", 6, 110, true, true),
            ],
            formulas:
            [
                new CalcFormula(1, 10, "@FormulaB@", "TOKEN_ARITH", "v1", true),
                new CalcFormula(2, 20, "@FormulaA@", "TOKEN_ARITH", "v1", true),
            ],
            dependencies:
            [
                new CalcDependency(1, 10, 20, "VALUE", 0, true),
                new CalcDependency(2, 20, 10, "VALUE", 0, true),
            ]);

        var report = ScenarioModelValidator.Validate(bundle);

        Assert.True(report.HasErrors);
        Assert.Contains(report.Issues, x => x.Message.Contains("Cyclic same-period formula dependencies", StringComparison.Ordinal));
    }

    private static CalcMetadataBundle BuildBundle(
        IReadOnlyList<CalcNode> nodes,
        IReadOnlyList<CalcFormula> formulas,
        IReadOnlyList<CalcDependency> dependencies)
    {
        var model = new CalcModel(1, "VALIDATION_TEST", "Validation Test", 1, "ACTIVE", true);
        var scenarios = new[]
        {
            new CalcScenario(1, 1, null, "BASE", "Base", "BASE", "ACTIVE", 10, true),
        };
        var periods = new[]
        {
            new CalcPeriod(1, 1, "2026-01", 2026, 1, "MONTH", 1, true),
        };
        var costObjects = new[]
        {
            new CalcCostObject(1, 1, null, "GLOBAL", "Global", "GENERIC", true),
        };

        return new CalcMetadataBundle(model, scenarios, periods, costObjects, nodes, formulas, dependencies);
    }
}
