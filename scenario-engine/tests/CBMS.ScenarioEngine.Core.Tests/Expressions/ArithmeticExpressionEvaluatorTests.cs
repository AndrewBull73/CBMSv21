using CBMS.ScenarioEngine.Core.Expressions;
using Xunit;

namespace CBMS.ScenarioEngine.Core.Tests.Expressions;

public sealed class ArithmeticExpressionEvaluatorTests
{
    [Fact]
    public void Evaluate_IfTrue_DoesNotEvaluateFalseBranch()
    {
        var result = ArithmeticExpressionEvaluator.Evaluate("IF(1, 42, 1 / 0)");

        Assert.Equal(42m, result);
    }

    [Fact]
    public void Evaluate_IfFalse_DoesNotEvaluateTrueBranch()
    {
        var result = ArithmeticExpressionEvaluator.Evaluate("IF(0, 1 / 0, 24)");

        Assert.Equal(24m, result);
    }

    [Fact]
    public void Evaluate_IfTrue_DoesNotResolveVariablesInFalseBranch()
    {
        var resolvedIdentifiers = new List<string>();

        var result = ArithmeticExpressionEvaluator.Evaluate(
            "IF(Headcount > 0, Revenue, MissingVariable)",
            identifier =>
            {
                resolvedIdentifiers.Add(identifier);
                return identifier switch
                {
                    "Headcount" => 10m,
                    "Revenue" => 15000m,
                    "MissingVariable" => throw new InvalidOperationException("Should not resolve."),
                    _ => throw new InvalidOperationException($"Unexpected identifier '{identifier}'."),
                };
            });

        Assert.Equal(15000m, result);
        Assert.Equal(["Headcount", "Revenue"], resolvedIdentifiers);
    }

    [Fact]
    public void Evaluate_IfFalse_DoesNotResolveVariablesInTrueBranch()
    {
        var resolvedIdentifiers = new List<string>();

        var result = ArithmeticExpressionEvaluator.Evaluate(
            "IF(Headcount > 0, MissingVariable, Revenue)",
            identifier =>
            {
                resolvedIdentifiers.Add(identifier);
                return identifier switch
                {
                    "Headcount" => 0m,
                    "Revenue" => 18375m,
                    "MissingVariable" => throw new InvalidOperationException("Should not resolve."),
                    _ => throw new InvalidOperationException($"Unexpected identifier '{identifier}'."),
                };
            });

        Assert.Equal(18375m, result);
        Assert.Equal(["Headcount", "Revenue"], resolvedIdentifiers);
    }

    [Fact]
    public void Evaluate_IfSupportsNestedExpressionsInsideSkippedBranch()
    {
        var result = ArithmeticExpressionEvaluator.Evaluate(
            "IF(TRUE, 1, MAX(5, IF(FALSE, 2, 3 / 0)))");

        Assert.Equal(1m, result);
    }

    [Fact]
    public void Evaluate_IfSupportsNestedExpressionsInsideExecutedBranch()
    {
        var result = ArithmeticExpressionEvaluator.Evaluate(
            "IF(2 > 1, ROUND((10 + 5) / 3, 2), 0)");

        Assert.Equal(5m, result);
    }

    [Fact]
    public void Evaluate_Sum_AddsAllArguments()
    {
        var result = ArithmeticExpressionEvaluator.Evaluate(
            "SUM(1, 2, 3, 4.5)");

        Assert.Equal(10.5m, result);
    }
}
