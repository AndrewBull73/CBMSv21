using System.Globalization;
using System.Text.RegularExpressions;
using CBMS.ScenarioEngine.Core.Expressions;
using CBMS.ScenarioEngine.Core.Models;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal sealed class ScenarioExecutionEngine
{
    private static readonly Regex TokenRegex = new(@"@([A-Za-z0-9_]+)(?:\[([^\]]+)\])?@", RegexOptions.Compiled);

    public ExecutionReport Execute(ScenarioSelection selection)
    {
        var bundle = selection.Bundle;
        var nodeById = bundle.Nodes.ToDictionary(x => x.NodeId);
        var nodeByCode = bundle.Nodes.ToDictionary(x => x.NodeCode, StringComparer.OrdinalIgnoreCase);
        var formulaByNodeId = bundle.Formulas.ToDictionary(x => x.NodeId);
        var dependencyLookup = bundle.Dependencies
            .GroupBy(x => x.NodeId)
            .ToDictionary(x => x.Key, x => x.ToList());
        var activePeriods = bundle.Periods
            .Where(x => x.ActiveFlag)
            .OrderBy(x => x.SequenceNo)
            .ToArray();
        var periodById = activePeriods.ToDictionary(x => x.PeriodId);
        var periodByCode = activePeriods.ToDictionary(x => x.PeriodCode, StringComparer.OrdinalIgnoreCase);
        var periodBySequence = activePeriods.ToDictionary(x => x.SequenceNo);

        var orderedFormulaNodes = TopologicallySortFormulaNodes(bundle.Nodes, bundle.Dependencies, nodeById);
        var values = new Dictionary<(int CostObjectId, int PeriodId, int NodeId), decimal>();
        var results = new Dictionary<(int CostObjectId, int PeriodId, int NodeId), ExecutionValue>();
        var issues = new List<string>();
        var issueSet = new HashSet<string>(StringComparer.Ordinal);

        foreach (var input in selection.InputValues)
        {
            if (input.ValueDecimal.HasValue)
            {
                values[(input.CostObjectId, input.PeriodId, input.NodeId)] = input.ValueDecimal.Value;
            }
        }

        foreach (var costObject in bundle.CostObjects.Where(x => x.ActiveFlag))
        {
            foreach (var period in activePeriods)
            {
                foreach (var formulaNode in orderedFormulaNodes)
                {
                    EvaluateFormulaNode(
                        costObject,
                        period,
                        formulaNode,
                        nodeById,
                        nodeByCode,
                        formulaByNodeId,
                        dependencyLookup,
                        periodByCode,
                        periodBySequence,
                        values,
                        results,
                        new HashSet<(int CostObjectId, int PeriodId, int NodeId)>(),
                        issue => AddIssue(issueSet, issues, issue));
                }
            }
        }

        return new ExecutionReport(
            orderedFormulaNodes.Select(x => x.NodeCode).ToArray(),
            results.Values
                .OrderBy(x => x.CostObjectCode, StringComparer.OrdinalIgnoreCase)
                .ThenBy(x => periodById[x.PeriodId].SequenceNo)
                .ThenBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase)
                .ToArray(),
            issues);
    }

    private static IReadOnlyList<CalcNode> TopologicallySortFormulaNodes(
        IReadOnlyList<CalcNode> nodes,
        IReadOnlyList<CalcDependency> dependencies,
        IReadOnlyDictionary<int, CalcNode> nodeById)
    {
        var formulaNodes = nodes
            .Where(x => x.ActiveFlag && string.Equals(x.NodeTypeCode, "FORMULA", StringComparison.OrdinalIgnoreCase))
            .OrderBy(x => x.NodeOrder)
            .ThenBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase)
            .ToList();

        var formulaNodeIds = formulaNodes.Select(x => x.NodeId).ToHashSet();
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

        var ready = new List<CalcNode>(formulaNodes.Where(x => incoming[x.NodeId] == 0));
        var sorted = new List<CalcNode>();

        while (ready.Count > 0)
        {
            var next = ready
                .OrderBy(x => x.NodeOrder)
                .ThenBy(x => x.NodeCode, StringComparer.OrdinalIgnoreCase)
                .First();
            ready.Remove(next);
            sorted.Add(next);

            foreach (var targetNodeId in outgoing[next.NodeId])
            {
                incoming[targetNodeId]--;
                if (incoming[targetNodeId] == 0)
                {
                    ready.Add(nodeById[targetNodeId]);
                }
            }
        }

        if (sorted.Count != formulaNodes.Count)
        {
            var cyclic = formulaNodes
                .Where(x => !sorted.Any(y => y.NodeId == x.NodeId))
                .Select(x => x.NodeCode);
            throw new InvalidOperationException(
                $"Cyclic or unresolved formula dependencies detected: {string.Join(", ", cyclic)}");
        }

        return sorted;
    }

    private static void EvaluateFormulaNode(
        CalcCostObject costObject,
        CalcPeriod period,
        CalcNode formulaNode,
        IReadOnlyDictionary<int, CalcNode> nodeById,
        IReadOnlyDictionary<string, CalcNode> nodeByCode,
        IReadOnlyDictionary<int, CalcFormula> formulaByNodeId,
        IReadOnlyDictionary<int, List<CalcDependency>> dependencyLookup,
        IReadOnlyDictionary<string, CalcPeriod> periodByCode,
        IReadOnlyDictionary<int, CalcPeriod> periodBySequence,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), decimal> values,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), ExecutionValue> results,
        ISet<(int CostObjectId, int PeriodId, int NodeId)> evaluationStack,
        Action<string> addIssue)
    {
        var key = (costObject.CostObjectId, period.PeriodId, formulaNode.NodeId);
        if (values.ContainsKey(key))
        {
            if (!results.ContainsKey(key))
            {
                results[key] = CreateExecutionValue(costObject, period, formulaNode, values[key]);
            }

            return;
        }

        if (!formulaByNodeId.TryGetValue(formulaNode.NodeId, out var formula))
        {
            return;
        }

        if (!evaluationStack.Add(key))
        {
            addIssue(
                $"Cyclic cross-period reference detected while evaluating '{formulaNode.NodeCode}' for cost object '{costObject.CostObjectCode}' period '{period.PeriodCode}'.");
            return;
        }

        try
        {
            var tokenIndex = 0;
            var tokenResolvers = new Dictionary<string, Func<decimal>>(StringComparer.OrdinalIgnoreCase);
            var preparedExpression = TokenRegex.Replace(formula.ExpressionText, match =>
            {
                var placeholder = $"__v{tokenIndex++}";
                tokenResolvers[placeholder] = () => ResolveTokenValue(
                    match.Value,
                    match.Groups[1].Value,
                    match.Groups[2].Success ? match.Groups[2].Value.Trim() : null,
                    costObject,
                    period,
                    formulaNode,
                    nodeById,
                    nodeByCode,
                    formulaByNodeId,
                    dependencyLookup,
                    periodByCode,
                    periodBySequence,
                    values,
                    results,
                    evaluationStack,
                    addIssue);
                return placeholder;
            });

            var value = EvaluateArithmetic(
                preparedExpression,
                identifier => tokenResolvers.TryGetValue(identifier, out var resolver)
                    ? resolver()
                    : throw new InvalidOperationException($"Unknown expression identifier '{identifier}'."));
            values[key] = value;
            results[key] = CreateExecutionValue(costObject, period, formulaNode, value);
        }
        catch (Exception ex)
        {
            addIssue(
                $"Failed to evaluate '{formulaNode.NodeCode}' for cost object '{costObject.CostObjectCode}' period '{period.PeriodCode}': {ex.Message}");
        }
        finally
        {
            evaluationStack.Remove(key);
        }
    }

    private static decimal? ResolveNodeValue(
        CalcCostObject costObject,
        CalcPeriod period,
        CalcNode node,
        IReadOnlyDictionary<int, CalcNode> nodeById,
        IReadOnlyDictionary<string, CalcNode> nodeByCode,
        IReadOnlyDictionary<int, CalcFormula> formulaByNodeId,
        IReadOnlyDictionary<int, List<CalcDependency>> dependencyLookup,
        IReadOnlyDictionary<string, CalcPeriod> periodByCode,
        IReadOnlyDictionary<int, CalcPeriod> periodBySequence,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), decimal> values,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), ExecutionValue> results,
        ISet<(int CostObjectId, int PeriodId, int NodeId)> evaluationStack,
        Action<string> addIssue)
    {
        var key = (costObject.CostObjectId, period.PeriodId, node.NodeId);
        if (values.TryGetValue(key, out var existing))
        {
            return existing;
        }

        if (!formulaByNodeId.ContainsKey(node.NodeId))
        {
            return null;
        }

        EvaluateFormulaNode(
            costObject,
            period,
            node,
            nodeById,
            nodeByCode,
            formulaByNodeId,
            dependencyLookup,
            periodByCode,
            periodBySequence,
            values,
            results,
            evaluationStack,
            addIssue);

        return values.TryGetValue(key, out var computed) ? computed : null;
    }

    private static decimal ResolveTokenValue(
        string tokenText,
        string tokenCode,
        string? selectorText,
        CalcCostObject costObject,
        CalcPeriod period,
        CalcNode formulaNode,
        IReadOnlyDictionary<int, CalcNode> nodeById,
        IReadOnlyDictionary<string, CalcNode> nodeByCode,
        IReadOnlyDictionary<int, CalcFormula> formulaByNodeId,
        IReadOnlyDictionary<int, List<CalcDependency>> dependencyLookup,
        IReadOnlyDictionary<string, CalcPeriod> periodByCode,
        IReadOnlyDictionary<int, CalcPeriod> periodBySequence,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), decimal> values,
        IDictionary<(int CostObjectId, int PeriodId, int NodeId), ExecutionValue> results,
        ISet<(int CostObjectId, int PeriodId, int NodeId)> evaluationStack,
        Action<string> addIssue)
    {
        if (!nodeByCode.TryGetValue(tokenCode, out var dependencyNode))
        {
            addIssue($"Unknown token '{tokenCode}' in formula node '{formulaNode.NodeCode}'.");
            return 0m;
        }

        if (!TryResolveTargetPeriod(selectorText, period, periodByCode, periodBySequence, out var targetPeriod))
        {
            if (IsDependencyRequired(formulaNode.NodeId, dependencyNode.NodeId, selectorText, dependencyLookup))
            {
                addIssue(
                    $"Invalid period selector '{selectorText}' for token '{tokenCode}' while evaluating '{formulaNode.NodeCode}' for cost object '{costObject.CostObjectCode}' period '{period.PeriodCode}'.");
            }

            return 0m;
        }

        var tokenValue = ResolveNodeValue(
            costObject,
            targetPeriod,
            dependencyNode,
            nodeById,
            nodeByCode,
            formulaByNodeId,
            dependencyLookup,
            periodByCode,
            periodBySequence,
            values,
            results,
            evaluationStack,
            addIssue);

        if (tokenValue.HasValue)
        {
            return tokenValue.Value;
        }

        if (IsDependencyRequired(formulaNode.NodeId, dependencyNode.NodeId, selectorText, dependencyLookup))
        {
            addIssue(
                $"Missing value for token '{tokenText}' while evaluating '{formulaNode.NodeCode}' for cost object '{costObject.CostObjectCode}' period '{period.PeriodCode}'.");
        }

        return 0m;
    }

    private static bool TryResolveTargetPeriod(
        string? selectorText,
        CalcPeriod currentPeriod,
        IReadOnlyDictionary<string, CalcPeriod> periodByCode,
        IReadOnlyDictionary<int, CalcPeriod> periodBySequence,
        out CalcPeriod targetPeriod)
    {
        if (string.IsNullOrWhiteSpace(selectorText))
        {
            targetPeriod = currentPeriod;
            return true;
        }

        if (int.TryParse(selectorText, NumberStyles.Integer, CultureInfo.InvariantCulture, out var offset))
        {
            return periodBySequence.TryGetValue(currentPeriod.SequenceNo + offset, out targetPeriod!);
        }

        return periodByCode.TryGetValue(selectorText, out targetPeriod!);
    }

    private static bool IsDependencyRequired(
        int formulaNodeId,
        int dependsOnNodeId,
        string? selectorText,
        IReadOnlyDictionary<int, List<CalcDependency>> dependencyLookup)
    {
        if (!dependencyLookup.TryGetValue(formulaNodeId, out var dependencies))
        {
            return false;
        }

        var offset = ParseOffset(selectorText);
        var matchingDependency = dependencies.FirstOrDefault(x =>
            x.DependsOnNodeId == dependsOnNodeId &&
            (offset.HasValue ? x.OffsetPeriods == offset.Value : x.OffsetPeriods == 0));

        return matchingDependency?.RequiredFlag ?? false;
    }

    private static int? ParseOffset(string? selectorText)
        => int.TryParse(selectorText, NumberStyles.Integer, CultureInfo.InvariantCulture, out var offset)
            ? offset
            : null;

    private static ExecutionValue CreateExecutionValue(
        CalcCostObject costObject,
        CalcPeriod period,
        CalcNode node,
        decimal value)
        => new(
            costObject.CostObjectId,
            costObject.CostObjectCode,
            period.PeriodId,
            period.PeriodCode,
            node.NodeId,
            node.NodeCode,
            value);

    private static void AddIssue(
        ISet<string> issueSet,
        IList<string> issues,
        string issue)
    {
        if (issueSet.Add(issue))
        {
            issues.Add(issue);
        }
    }

    private static decimal EvaluateArithmetic(string expression, Func<string, decimal>? variableResolver = null)
        => ArithmeticExpressionEvaluator.Evaluate(expression, variableResolver);
}

internal sealed record ExecutionValue(
    int CostObjectId,
    string CostObjectCode,
    int PeriodId,
    string PeriodCode,
    int NodeId,
    string NodeCode,
    decimal Value);

internal sealed record ExecutionReport(
    IReadOnlyList<string> ExecutionOrder,
    IReadOnlyList<ExecutionValue> Results,
    IReadOnlyList<string> Issues);
