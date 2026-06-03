using System.Globalization;
using System.Text.RegularExpressions;
using CBMS.ScenarioEngine.Core.Expressions;
using CBMS.ScenarioEngine.Core.Models;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class ScenarioModelValidator
{
    private static readonly Regex TokenRegex = new(@"@([A-Za-z0-9_]+)(?:\[([^\]]+)\])?@", RegexOptions.Compiled);

    public static ValidationReport Validate(CalcMetadataBundle bundle)
    {
        var issues = new List<ValidationIssue>();
        var nodeById = bundle.Nodes.ToDictionary(x => x.NodeId);
        var nodeByCode = bundle.Nodes.ToDictionary(x => x.NodeCode, StringComparer.OrdinalIgnoreCase);
        var activeFormulaNodes = bundle.Nodes
            .Where(x => x.ActiveFlag && string.Equals(x.NodeTypeCode, "FORMULA", StringComparison.OrdinalIgnoreCase))
            .ToArray();
        var activeFormulaByNodeId = bundle.Formulas
            .Where(x => x.ActiveFlag)
            .ToDictionary(x => x.NodeId);

        if (bundle.Periods.Count == 0)
        {
            AddIssue(issues, "ERROR", "No periods were found for the selected model.");
        }

        if (bundle.CostObjects.Count == 0)
        {
            AddIssue(issues, "ERROR", "No cost objects were found for the selected model.");
        }

        if (bundle.Nodes.Count == 0)
        {
            AddIssue(issues, "ERROR", "No nodes were found for the selected model.");
        }

        if (!bundle.Nodes.Any(x => x.ActiveFlag && x.OutputFlag))
        {
            AddIssue(issues, "WARN", "No active output nodes were found for the selected model.");
        }

        foreach (var formulaNode in activeFormulaNodes)
        {
            if (!activeFormulaByNodeId.ContainsKey(formulaNode.NodeId))
            {
                AddIssue(issues, "ERROR", $"Formula node '{formulaNode.NodeCode}' does not have an active expression.");
            }
        }

        foreach (var formula in bundle.Formulas.Where(x => x.ActiveFlag))
        {
            if (!nodeById.TryGetValue(formula.NodeId, out var node))
            {
                AddIssue(issues, "ERROR", $"Formula ID {formula.CalcFormulaId} references missing node ID {formula.NodeId}.");
                continue;
            }

            if (!string.Equals(node.NodeTypeCode, "FORMULA", StringComparison.OrdinalIgnoreCase))
            {
                AddIssue(issues, "ERROR", $"Node '{node.NodeCode}' has a formula but is typed as '{node.NodeTypeCode}'.");
                continue;
            }

            ValidateFormulaSyntax(node, formula, issues);
            ValidateFormulaTokens(node, formula, bundle.Dependencies, nodeByCode, issues);
        }

        foreach (var dependency in bundle.Dependencies)
        {
            if (!nodeById.ContainsKey(dependency.NodeId))
            {
                AddIssue(issues, "ERROR", $"Dependency ID {dependency.CalcDependencyId} references missing target node ID {dependency.NodeId}.");
            }

            if (!nodeById.ContainsKey(dependency.DependsOnNodeId))
            {
                AddIssue(issues, "ERROR", $"Dependency ID {dependency.CalcDependencyId} references missing source node ID {dependency.DependsOnNodeId}.");
            }

            if (dependency.NodeId == dependency.DependsOnNodeId && dependency.OffsetPeriods == 0)
            {
                AddIssue(issues, "ERROR", $"Dependency ID {dependency.CalcDependencyId} contains an invalid same-period self-reference.");
            }
        }

        ValidateFormulaCycles(activeFormulaNodes, bundle.Dependencies, issues);
        return new ValidationReport(issues);
    }

    private static void ValidateFormulaSyntax(
        CalcNode node,
        CalcFormula formula,
        ICollection<ValidationIssue> issues)
    {
        try
        {
            var sanitizedExpression = TokenRegex.Replace(formula.ExpressionText, "1");
            _ = ArithmeticExpressionEvaluator.Evaluate(sanitizedExpression);
        }
        catch (Exception ex)
        {
            AddIssue(issues, "ERROR", $"Formula syntax error in node '{node.NodeCode}': {ex.Message}");
        }
    }

    private static void ValidateFormulaTokens(
        CalcNode formulaNode,
        CalcFormula formula,
        IReadOnlyList<CalcDependency> dependencies,
        IReadOnlyDictionary<string, CalcNode> nodeByCode,
        ICollection<ValidationIssue> issues)
    {
        foreach (Match match in TokenRegex.Matches(formula.ExpressionText))
        {
            var tokenCode = match.Groups[1].Value;
            var selectorText = match.Groups[2].Success ? match.Groups[2].Value.Trim() : null;

            if (!nodeByCode.TryGetValue(tokenCode, out var tokenNode))
            {
                AddIssue(issues, "ERROR", $"Formula node '{formulaNode.NodeCode}' references unknown token '{tokenCode}'.");
                continue;
            }

            if (!TryResolveSelectorToOffset(selectorText, out var expectedOffset))
            {
                AddIssue(
                    issues,
                    "ERROR",
                    $"Formula node '{formulaNode.NodeCode}' has an unsupported period selector '{selectorText}' on token '{tokenCode}'.");
                continue;
            }

            var matchingDependency = dependencies.Any(x =>
                x.NodeId == formulaNode.NodeId &&
                x.DependsOnNodeId == tokenNode.NodeId &&
                x.OffsetPeriods == expectedOffset);

            if (!matchingDependency)
            {
                AddIssue(
                    issues,
                    "ERROR",
                    $"Formula node '{formulaNode.NodeCode}' references token '{match.Value}' without a matching dependency declaration.");
            }
        }
    }

    private static void ValidateFormulaCycles(
        IReadOnlyList<CalcNode> formulaNodes,
        IReadOnlyList<CalcDependency> dependencies,
        ICollection<ValidationIssue> issues)
    {
        var formulaNodeIds = formulaNodes.Select(x => x.NodeId).ToHashSet();
        var formulaNodeById = formulaNodes.ToDictionary(x => x.NodeId);
        var incoming = formulaNodes.ToDictionary(x => x.NodeId, _ => 0);
        var outgoing = formulaNodes.ToDictionary(x => x.NodeId, _ => new List<int>());

        foreach (var dependency in dependencies)
        {
            if (!formulaNodeIds.Contains(dependency.NodeId) || !formulaNodeIds.Contains(dependency.DependsOnNodeId))
            {
                continue;
            }

            if (dependency.NodeId == dependency.DependsOnNodeId && dependency.OffsetPeriods != 0)
            {
                continue;
            }

            incoming[dependency.NodeId]++;
            outgoing[dependency.DependsOnNodeId].Add(dependency.NodeId);
        }

        var ready = new Queue<int>(incoming.Where(x => x.Value == 0).Select(x => x.Key));
        var visited = 0;

        while (ready.Count > 0)
        {
            var current = ready.Dequeue();
            visited++;

            foreach (var next in outgoing[current])
            {
                incoming[next]--;
                if (incoming[next] == 0)
                {
                    ready.Enqueue(next);
                }
            }
        }

        if (visited != formulaNodes.Count)
        {
            var cyclicNodes = incoming
                .Where(x => x.Value > 0)
                .Select(x => formulaNodeById[x.Key].NodeCode)
                .OrderBy(x => x, StringComparer.OrdinalIgnoreCase)
                .ToArray();
            AddIssue(
                issues,
                "ERROR",
                $"Cyclic same-period formula dependencies detected: {string.Join(", ", cyclicNodes)}");
        }
    }

    private static bool TryResolveSelectorToOffset(string? selectorText, out int offset)
    {
        if (string.IsNullOrWhiteSpace(selectorText))
        {
            offset = 0;
            return true;
        }

        if (int.TryParse(selectorText, NumberStyles.Integer, CultureInfo.InvariantCulture, out offset))
        {
            return true;
        }

        if (Regex.IsMatch(selectorText, @"^\d{4}-\d{2}$"))
        {
            offset = 0;
            return true;
        }

        offset = 0;
        return false;
    }

    private static void AddIssue(
        ICollection<ValidationIssue> issues,
        string severityCode,
        string message)
    {
        if (!issues.Any(x => string.Equals(x.SeverityCode, severityCode, StringComparison.OrdinalIgnoreCase) &&
                             string.Equals(x.Message, message, StringComparison.Ordinal)))
        {
            issues.Add(new ValidationIssue(severityCode, message));
        }
    }
}

internal sealed record ValidationIssue(
    string SeverityCode,
    string Message);

internal sealed record ValidationReport(
    IReadOnlyList<ValidationIssue> Issues)
{
    public bool HasErrors => Issues.Any(x => string.Equals(x.SeverityCode, "ERROR", StringComparison.OrdinalIgnoreCase));
    public bool HasWarnings => Issues.Any(x => string.Equals(x.SeverityCode, "WARN", StringComparison.OrdinalIgnoreCase));
}
