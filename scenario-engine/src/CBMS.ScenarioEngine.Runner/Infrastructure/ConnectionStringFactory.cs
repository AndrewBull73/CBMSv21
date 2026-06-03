using System.Text;
using CBMS.ScenarioEngine.Runner.Configuration;

namespace CBMS.ScenarioEngine.Runner.Infrastructure;

internal static class ConnectionStringFactory
{
    public static string Build(DatabaseSettings settings)
    {
        if (!string.IsNullOrWhiteSpace(settings.ConnectionString))
        {
            return settings.ConnectionString;
        }

        if (string.IsNullOrWhiteSpace(settings.Host) ||
            string.IsNullOrWhiteSpace(settings.Database) ||
            string.IsNullOrWhiteSpace(settings.User))
        {
            throw new InvalidOperationException(
                "Database settings are incomplete. Provide ConnectionString or Host/Database/User/Password.");
        }

        var server = settings.Port is > 0
            ? $"{settings.Host},{settings.Port}"
            : settings.Host;

        var builder = new StringBuilder();
        builder.Append("Server=").Append(server).Append(';');
        builder.Append("Database=").Append(settings.Database).Append(';');
        builder.Append("User ID=").Append(settings.User).Append(';');
        builder.Append("Password=").Append(settings.Password ?? string.Empty).Append(';');
        builder.Append("Encrypt=").Append(settings.Encrypt ? "True" : "False").Append(';');
        builder.Append("TrustServerCertificate=").Append(settings.TrustServerCertificate ? "True" : "False").Append(';');
        builder.Append("Connect Timeout=").Append(settings.LoginTimeout).Append(';');

        return builder.ToString();
    }
}
