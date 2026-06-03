using CBMS.ScenarioEngine.Runner.Configuration;
using Microsoft.Extensions.Configuration;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class RunnerConfiguration
{
    public static AppSettings Load(string baseDirectory)
    {
        var builder = new ConfigurationBuilder()
            .SetBasePath(baseDirectory)
            .AddJsonFile("appsettings.json", optional: true, reloadOnChange: false)
            .AddEnvironmentVariables(prefix: "CBMS_SCENARIO_ENGINE_");

        var settings = new AppSettings();
        builder.Build().Bind(settings);

        var envPath = FindBackendPhpEnvPath(baseDirectory, Directory.GetCurrentDirectory());
        var envValues = DotEnvLoader.Load(envPath);

        settings = new AppSettings
        {
            Database = MergeDatabaseSettings(settings.Database, envValues),
        };

        return settings;
    }

    private static string FindBackendPhpEnvPath(params string[] startingDirectories)
    {
        foreach (var start in startingDirectories)
        {
            var dir = new DirectoryInfo(start);
            while (dir is not null)
            {
                var candidate = Path.Combine(dir.FullName, "backend-php", ".env");
                if (File.Exists(candidate))
                {
                    return candidate;
                }

                dir = dir.Parent;
            }
        }

        return Path.Combine(startingDirectories[0], "backend-php", ".env");
    }

    private static DatabaseSettings MergeDatabaseSettings(
        DatabaseSettings configured,
        IReadOnlyDictionary<string, string> envValues)
    {
        return new DatabaseSettings
        {
            ConnectionString = configured.ConnectionString,
            Host = FirstNonEmpty(configured.Host, GetEnv(envValues, "DB_HOST"), GetEnv(envValues, "DB_SERVER")),
            Database = FirstNonEmpty(configured.Database, GetEnv(envValues, "DB_NAME"), GetEnv(envValues, "DB_DATABASE")),
            User = FirstNonEmpty(configured.User, GetEnv(envValues, "DB_USER"), GetEnv(envValues, "DB_USERNAME")),
            Password = FirstNonEmpty(configured.Password, GetEnv(envValues, "DB_PASS"), GetEnv(envValues, "DB_PASSWORD")),
            Port = configured.Port ?? TryParseInt(GetEnv(envValues, "DB_PORT")),
            Encrypt = configured.ConnectionString is not null
                ? configured.Encrypt
                : TryParseBool(GetEnv(envValues, "DB_ENCRYPT")) ?? configured.Encrypt,
            TrustServerCertificate = configured.ConnectionString is not null
                ? configured.TrustServerCertificate
                : TryParseBool(GetEnv(envValues, "DB_TRUST_CERT")) ?? configured.TrustServerCertificate,
            LoginTimeout = configured.LoginTimeout > 0
                ? configured.LoginTimeout
                : TryParseInt(GetEnv(envValues, "DB_LOGIN_TIMEOUT")) ?? 5,
        };
    }

    private static string? GetEnv(IReadOnlyDictionary<string, string> envValues, string key)
        => envValues.TryGetValue(key, out var value) ? value : null;

    private static string? FirstNonEmpty(params string?[] values)
        => values.FirstOrDefault(v => !string.IsNullOrWhiteSpace(v));

    private static int? TryParseInt(string? value)
        => int.TryParse(value, out var parsed) ? parsed : null;

    private static bool? TryParseBool(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        return value.Trim().ToLowerInvariant() switch
        {
            "1" or "true" or "yes" => true,
            "0" or "false" or "no" => false,
            _ => null,
        };
    }
}
