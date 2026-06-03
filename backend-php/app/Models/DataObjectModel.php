<?php
declare(strict_types=1);

namespace App\Models;

final class DataObjectModel
{
    public function __construct(private \PDO $pdo) {}

    /** Return all data objects for FY with parent/child info. */
    public function listForFiscalYear(int $fy): array
    {
        // Use the adjacency (ParentCode) for a clean recursive build in PHP.
        $sql = "
            SELECT
                o.FiscalYearID,
                o.DataObjectCode,
                o.DataObjectName,
                o.ParentCode = o.DataObjectCodeParent,  -- assuming you renamed the column
                o.DataObjectTypeID
            FROM dbo.tblDataObjects o
            WHERE o.FiscalYearID = :fy
            ORDER BY o.DataObjectName
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':fy' => $fy]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Normalize keys
        foreach ($rows as &$r) {
            $r['DataObjectCode'] = (string)$r['DataObjectCode'];
            $r['ParentCode']     = $r['ParentCode'] !== null ? (string)$r['ParentCode'] : null;
        }
        return $rows;
    }

    /** Optional: get ancestor path (for a breadcrumb/label) */
    public function getPath(int $fy, string $code): array
    {
        $sql = "
            WITH RECURSIVE Anc AS (
                SELECT DataObjectCode, DataObjectName, DataObjectCodeParent AS ParentCode
                FROM dbo.tblDataObjects
                WHERE FiscalYearID = :fy AND DataObjectCode = :code
                UNION ALL
                SELECT p.DataObjectCode, p.DataObjectName, p.DataObjectCodeParent
                FROM dbo.tblDataObjects p
                JOIN Anc a ON a.ParentCode = p.DataObjectCode
                WHERE p.FiscalYearID = :fy
            )
            SELECT DataObjectCode, DataObjectName
            FROM Anc
        ";
        // If SQL Server, swap for a closure-table read or a while loop; leaving as-is for template.
        $st = $this->pdo->prepare($sql);
        $st->execute([':fy'=>$fy, ':code'=>$code]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows); // from root -> leaf
    }

    /** Validate that a code exists for FY */
    public function exists(int $fy, string $code): bool
    {
        $st = $this->pdo->prepare("
            SELECT 1 FROM dbo.tblDataObjects
            WHERE FiscalYearID = :fy AND DataObjectCode = :code
        ");
        $st->execute([':fy'=>$fy, ':code'=>$code]);
        return (bool)$st->fetchColumn();
    }
}
