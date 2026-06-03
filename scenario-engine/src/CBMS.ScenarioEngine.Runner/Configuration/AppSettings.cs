namespace CBMS.ScenarioEngine.Runner.Configuration;

public sealed class AppSettings
{
    public DatabaseSettings Database { get; init; } = new();
}
