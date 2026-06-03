<?php
declare(strict_types=1);

namespace App\Models;

final class HelpModel
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getHelpForRoute(string $route, string $lang = 'en'): ?array
    {
        $sql = "SELECT HelpID, Route, Title, Content, LanguageCode
                FROM dbo.tblHelp
                WHERE Route = :route AND LanguageCode = :lang AND IsActive = 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':route' => $route, ':lang' => $lang]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
