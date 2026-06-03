using System.Collections.ObjectModel;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class DotEnvLoader
{
    public static IReadOnlyDictionary<string, string> Load(string path)
    {
        var data = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);

        if (!File.Exists(path))
        {
            return new ReadOnlyDictionary<string, string>(data);
        }

        foreach (var rawLine in File.ReadAllLines(path))
        {
            var line = rawLine.Trim();
            if (line.Length == 0 || line.StartsWith('#'))
            {
                continue;
            }

            var idx = line.IndexOf('=');
            if (idx <= 0)
            {
                continue;
            }

            var key = line[..idx].Trim();
            var value = line[(idx + 1)..].Trim();
            data[key] = value;
        }

        return new ReadOnlyDictionary<string, string>(data);
    }
}
