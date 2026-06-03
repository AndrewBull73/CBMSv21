namespace CBMS.ScenarioEngine.Runner.Configuration;

public sealed class DatabaseSettings
{
    public string? ConnectionString { get; init; }
    public string? Host { get; init; }
    public string? Database { get; init; }
    public string? User { get; init; }
    public string? Password { get; init; }
    public int? Port { get; init; }
    public bool Encrypt { get; init; } = false;
    public bool TrustServerCertificate { get; init; } = true;
    public int LoginTimeout { get; init; } = 5;
}
