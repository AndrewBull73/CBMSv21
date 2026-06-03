<?php
declare(strict_types=1);

namespace App\Models;

final class DataObjectTreeModel
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buildTreeForCodes(array $codes, int $fy): array
    {
        if (empty($codes)) return [];

        $in = str_repeat('?,', count($codes) - 1) . '?';
        $sql = "
            SELECT 
                DataObjectCode,
                DataObjectName,
                DataObjectCodeParent,
                CASE WHEN EXISTS (
                    SELECT 1 FROM tblDataObjectCodes c2 
                    WHERE c2.DataObjectCodeParent = c.DataObjectCode 
                      AND c2.FiscalYearID = :fy
                ) THEN 1 ELSE 0 END AS hasChildren
            FROM tblDataObjectCodes c
            WHERE DataObjectCode IN ($in)
              AND FiscalYearID = :fy
            ORDER BY DataObjectCode
        ";
        try {
            $st = $this->pdo->prepare($sql);
            $params = $codes;
            $params[':fy'] = $fy;
            $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}