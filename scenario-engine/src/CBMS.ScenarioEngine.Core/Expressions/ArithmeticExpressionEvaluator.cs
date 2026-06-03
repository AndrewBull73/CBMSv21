using System.Globalization;

namespace CBMS.ScenarioEngine.Core.Expressions;

public static class ArithmeticExpressionEvaluator
{
    public static decimal Evaluate(string expression)
        => Evaluate(expression, variableResolver: null);

    public static decimal Evaluate(string expression, Func<string, decimal>? variableResolver)
    {
        if (string.IsNullOrWhiteSpace(expression))
        {
            throw new InvalidOperationException("Expression is empty.");
        }

        var parser = new Parser(expression, variableResolver);
        return parser.Parse();
    }

    private enum TokenKind
    {
        Number,
        Identifier,
        Plus,
        Minus,
        Multiply,
        Divide,
        OpenParen,
        CloseParen,
        Comma,
        Equal,
        NotEqual,
        Less,
        LessOrEqual,
        Greater,
        GreaterOrEqual,
        End,
    }

    private readonly record struct Token(TokenKind Kind, string Text, decimal NumberValue = 0m);

    private sealed class Parser
    {
        private readonly string _expression;
        private readonly Func<string, decimal>? _variableResolver;
        private int _index;
        private Token _current;

        public Parser(string expression, Func<string, decimal>? variableResolver)
        {
            _expression = expression;
            _variableResolver = variableResolver;
            _current = NextToken();
        }

        public decimal Parse()
        {
            var value = ParseComparison();
            Expect(TokenKind.End);
            return value;
        }

        private decimal ParseComparison()
        {
            var value = ParseExpression();

            while (_current.Kind is TokenKind.Equal or TokenKind.NotEqual or TokenKind.Less or
                   TokenKind.LessOrEqual or TokenKind.Greater or TokenKind.GreaterOrEqual)
            {
                var op = _current.Kind;
                Advance();
                var rhs = ParseExpression();

                var comparison = op switch
                {
                    TokenKind.Equal => value == rhs,
                    TokenKind.NotEqual => value != rhs,
                    TokenKind.Less => value < rhs,
                    TokenKind.LessOrEqual => value <= rhs,
                    TokenKind.Greater => value > rhs,
                    TokenKind.GreaterOrEqual => value >= rhs,
                    _ => throw new InvalidOperationException($"Unsupported comparison operator '{_current.Text}'."),
                };

                value = comparison ? 1m : 0m;
            }

            return value;
        }

        private decimal ParseExpression()
        {
            var value = ParseTerm();

            while (_current.Kind is TokenKind.Plus or TokenKind.Minus)
            {
                var op = _current.Kind;
                Advance();
                var rhs = ParseTerm();
                value = op == TokenKind.Plus ? value + rhs : value - rhs;
            }

            return value;
        }

        private decimal ParseTerm()
        {
            var value = ParseUnary();

            while (_current.Kind is TokenKind.Multiply or TokenKind.Divide)
            {
                var op = _current.Kind;
                Advance();
                var rhs = ParseUnary();

                if (op == TokenKind.Multiply)
                {
                    value *= rhs;
                }
                else
                {
                    if (rhs == 0m)
                    {
                        throw new InvalidOperationException("Division by zero.");
                    }

                    value /= rhs;
                }
            }

            return value;
        }

        private decimal ParseUnary()
        {
            if (_current.Kind == TokenKind.Plus)
            {
                Advance();
                return ParseUnary();
            }

            if (_current.Kind == TokenKind.Minus)
            {
                Advance();
                return -ParseUnary();
            }

            return ParsePrimary();
        }

        private decimal ParsePrimary()
        {
            if (_current.Kind == TokenKind.Number)
            {
                var value = _current.NumberValue;
                Advance();
                return value;
            }

            if (_current.Kind == TokenKind.Identifier)
            {
                var identifier = _current.Text;
                Advance();

                if (string.Equals(identifier, "TRUE", StringComparison.OrdinalIgnoreCase))
                {
                    return 1m;
                }

                if (string.Equals(identifier, "FALSE", StringComparison.OrdinalIgnoreCase))
                {
                    return 0m;
                }

                if (string.Equals(identifier, "IF", StringComparison.OrdinalIgnoreCase) &&
                    _current.Kind == TokenKind.OpenParen)
                {
                    return ParseIfFunctionCall();
                }

                if (_current.Kind != TokenKind.OpenParen)
                {
                    if (_variableResolver is not null)
                    {
                        return _variableResolver(identifier);
                    }

                    throw new InvalidOperationException($"Unexpected identifier '{identifier}'.");
                }

                return ParseFunctionCall(identifier);
            }

            if (_current.Kind == TokenKind.OpenParen)
            {
                Advance();
                var value = ParseComparison();
                Expect(TokenKind.CloseParen);
                return value;
            }

            throw new InvalidOperationException(
                $"Unexpected token '{_current.Text}' at position {_index}.");
        }

        private decimal ParseIfFunctionCall()
        {
            Expect(TokenKind.OpenParen);
            var condition = ParseComparison();
            Expect(TokenKind.Comma);

            if (condition != 0m)
            {
                var trueValue = ParseComparison();
                Expect(TokenKind.Comma);
                SkipArgument();
                Expect(TokenKind.CloseParen);
                return trueValue;
            }

            SkipArgument();
            Expect(TokenKind.Comma);
            var falseValue = ParseComparison();
            Expect(TokenKind.CloseParen);
            return falseValue;
        }

        private decimal ParseFunctionCall(string functionName)
        {
            Expect(TokenKind.OpenParen);

            var args = new List<decimal>();
            if (_current.Kind != TokenKind.CloseParen)
            {
                while (true)
                {
                    args.Add(ParseComparison());
                    if (_current.Kind == TokenKind.Comma)
                    {
                        Advance();
                        continue;
                    }

                    break;
                }
            }

            Expect(TokenKind.CloseParen);
            return EvaluateFunction(functionName, args);
        }

        private void SkipArgument()
        {
            var nestedParens = 0;

            while (true)
            {
                if (_current.Kind == TokenKind.End)
                {
                    throw new InvalidOperationException("Unexpected end of expression while skipping IF branch.");
                }

                if (nestedParens == 0 &&
                    (_current.Kind == TokenKind.Comma || _current.Kind == TokenKind.CloseParen))
                {
                    return;
                }

                if (_current.Kind == TokenKind.OpenParen)
                {
                    nestedParens++;
                }
                else if (_current.Kind == TokenKind.CloseParen)
                {
                    nestedParens--;
                }

                Advance();
            }
        }

        private static decimal EvaluateFunction(string functionName, IReadOnlyList<decimal> args)
        {
            switch (functionName.Trim().ToUpperInvariant())
            {
                case "MIN":
                    RequireAtLeast(functionName, args, 1);
                    return args.Min();

                case "MAX":
                    RequireAtLeast(functionName, args, 1);
                    return args.Max();

                case "SUM":
                    RequireAtLeast(functionName, args, 1);
                    return args.Sum();

                case "ABS":
                    RequireArgCount(functionName, args, 1);
                    return decimal.Abs(args[0]);

                case "ROUND":
                    if (args.Count is < 1 or > 2)
                    {
                        throw new InvalidOperationException("ROUND requires 1 or 2 arguments.");
                    }

                    var digits = args.Count == 2 ? decimal.ToInt32(args[1]) : 0;
                    return decimal.Round(args[0], digits, MidpointRounding.AwayFromZero);

                case "AND":
                    RequireAtLeast(functionName, args, 1);
                    return args.All(x => x != 0m) ? 1m : 0m;

                case "OR":
                    RequireAtLeast(functionName, args, 1);
                    return args.Any(x => x != 0m) ? 1m : 0m;

                case "NOT":
                    RequireArgCount(functionName, args, 1);
                    return args[0] == 0m ? 1m : 0m;

                default:
                    throw new InvalidOperationException($"Unsupported function '{functionName}'.");
            }
        }

        private static void RequireArgCount(string functionName, IReadOnlyList<decimal> args, int expected)
        {
            if (args.Count != expected)
            {
                throw new InvalidOperationException($"{functionName} requires {expected} arguments.");
            }
        }

        private static void RequireAtLeast(string functionName, IReadOnlyList<decimal> args, int expected)
        {
            if (args.Count < expected)
            {
                throw new InvalidOperationException($"{functionName} requires at least {expected} argument(s).");
            }
        }

        private void Expect(TokenKind kind)
        {
            if (_current.Kind != kind)
            {
                throw new InvalidOperationException(
                    $"Expected token '{kind}' but found '{_current.Text}'.");
            }

            Advance();
        }

        private void Advance()
        {
            _current = NextToken();
        }

        private Token NextToken()
        {
            SkipWhitespace();

            if (_index >= _expression.Length)
            {
                return new Token(TokenKind.End, "<end>");
            }

            var ch = _expression[_index];
            if (char.IsLetter(ch) || ch == '_')
            {
                return ReadIdentifierToken();
            }

            if (char.IsDigit(ch) || (ch == '.' && _index + 1 < _expression.Length && char.IsDigit(_expression[_index + 1])))
            {
                return ReadNumberToken();
            }

            return ch switch
            {
                '+' => ReadSingle(TokenKind.Plus),
                '-' => ReadSingle(TokenKind.Minus),
                '*' => ReadSingle(TokenKind.Multiply),
                '/' => ReadSingle(TokenKind.Divide),
                '(' => ReadSingle(TokenKind.OpenParen),
                ')' => ReadSingle(TokenKind.CloseParen),
                ',' => ReadSingle(TokenKind.Comma),
                '=' => ReadEqualToken(),
                '!' => ReadBangToken(),
                '<' => ReadLessToken(),
                '>' => ReadGreaterToken(),
                _ => throw new InvalidOperationException($"Unexpected character '{ch}' at position {_index}."),
            };
        }

        private Token ReadSingle(TokenKind kind)
        {
            var text = _expression[_index].ToString();
            _index++;
            return new Token(kind, text);
        }

        private Token ReadIdentifierToken()
        {
            var start = _index;
            _index++;

            while (_index < _expression.Length)
            {
                var ch = _expression[_index];
                if (char.IsLetterOrDigit(ch) || ch == '_')
                {
                    _index++;
                    continue;
                }

                break;
            }

            var text = _expression[start.._index];
            return new Token(TokenKind.Identifier, text);
        }

        private Token ReadNumberToken()
        {
            var start = _index;
            var seenDigit = false;
            var seenDecimal = false;

            while (_index < _expression.Length)
            {
                var ch = _expression[_index];
                if (char.IsDigit(ch))
                {
                    seenDigit = true;
                    _index++;
                    continue;
                }

                if (ch == '.')
                {
                    if (seenDecimal)
                    {
                        break;
                    }

                    seenDecimal = true;
                    _index++;
                    continue;
                }

                break;
            }

            if (!seenDigit)
            {
                throw new InvalidOperationException(
                    $"Unexpected character '{_expression[start]}' at position {start}.");
            }

            var text = _expression[start.._index];
            if (!decimal.TryParse(text, NumberStyles.Number, CultureInfo.InvariantCulture, out var value))
            {
                throw new InvalidOperationException($"Invalid numeric literal '{text}'.");
            }

            return new Token(TokenKind.Number, text, value);
        }

        private Token ReadEqualToken()
        {
            if (_index + 1 < _expression.Length && _expression[_index + 1] == '=')
            {
                _index += 2;
                return new Token(TokenKind.Equal, "==");
            }

            _index++;
            return new Token(TokenKind.Equal, "=");
        }

        private Token ReadBangToken()
        {
            if (_index + 1 < _expression.Length && _expression[_index + 1] == '=')
            {
                _index += 2;
                return new Token(TokenKind.NotEqual, "!=");
            }

            throw new InvalidOperationException($"Unexpected character '!' at position {_index}.");
        }

        private Token ReadLessToken()
        {
            if (_index + 1 < _expression.Length)
            {
                var next = _expression[_index + 1];
                if (next == '=')
                {
                    _index += 2;
                    return new Token(TokenKind.LessOrEqual, "<=");
                }

                if (next == '>')
                {
                    _index += 2;
                    return new Token(TokenKind.NotEqual, "<>");
                }
            }

            _index++;
            return new Token(TokenKind.Less, "<");
        }

        private Token ReadGreaterToken()
        {
            if (_index + 1 < _expression.Length && _expression[_index + 1] == '=')
            {
                _index += 2;
                return new Token(TokenKind.GreaterOrEqual, ">=");
            }

            _index++;
            return new Token(TokenKind.Greater, ">");
        }

        private void SkipWhitespace()
        {
            while (_index < _expression.Length && char.IsWhiteSpace(_expression[_index]))
            {
                _index++;
            }
        }
    }
}
