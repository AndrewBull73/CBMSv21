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

        $params = [
            ':fyChildren' => $fy,
            ':fyMain' => $fy,
        ];
        $placeholders = [];
        foreach (array_values($codes) as $index => $code) {
            $key = ':code' . $index;
            $placeholders[] = $key;
            $params[$key] = $code;
        }
        $in = implode(',', $placeholders);
        $sql = "
            SELECT 
                DataObjectCode,
                DataObjectName,
                DataObjectCodeParent,
                CASE WHEN EXISTS (
                    SELECT 1 FROM tblDataObjectCodes c2 
                    WHERE c2.DataObjectCodeParent = c.DataObjectCode 
                      AND c2.FiscalYearID = :fyChildren
                ) THEN 1 ELSE 0 END AS hasChildren
            FROM tblDataObjectCodes c
            WHERE DataObjectCode IN ($in)
              AND FiscalYearID = :fyMain
            ORDER BY DataObjectCode
        ";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function listRows(int $fiscalYearId, array $filters = []): array
    {
        if ($fiscalYearId <= 0) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($filters['pageSize'] ?? 50)));
        $offset = ($page - 1) * $pageSize;

        $where = ['tr.FiscalYearID = :fy'];
        $params = [':fy' => $fiscalYearId];

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(
                tr.AncestorCode LIKE :searchAncestorCode
                OR tr.DescendantCode LIKE :searchDescendantCode
                OR ISNULL(ancestor.DataObjectName, \'\') LIKE :searchAncestorName
                OR ISNULL(descendant.DataObjectName, \'\') LIKE :searchDescendantName
            )';
            $searchLike = '%' . $search . '%';
            $params[':searchAncestorCode'] = $searchLike;
            $params[':searchDescendantCode'] = $searchLike;
            $params[':searchAncestorName'] = $searchLike;
            $params[':searchDescendantName'] = $searchLike;
        }

        $ancestorCode = trim((string)($filters['ancestor_code'] ?? ''));
        if ($ancestorCode !== '') {
            $where[] = 'tr.AncestorCode = :ancestorCode';
            $params[':ancestorCode'] = $ancestorCode;
        }

        $descendantCode = trim((string)($filters['descendant_code'] ?? ''));
        if ($descendantCode !== '') {
            $where[] = 'tr.DescendantCode = :descendantCode';
            $params[':descendantCode'] = $descendantCode;
        }

        $depth = trim((string)($filters['depth'] ?? ''));
        if ($depth !== '' && ctype_digit($depth)) {
            $where[] = 'tr.Depth = :depth';
            $params[':depth'] = (int)$depth;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM dbo.tblDataObjectTree tr
            LEFT JOIN dbo.tblDataObjectCodes ancestor
              ON ancestor.FiscalYearID = tr.FiscalYearID
             AND ancestor.DataObjectCode = tr.AncestorCode
            LEFT JOIN dbo.tblDataObjectCodes descendant
              ON descendant.FiscalYearID = tr.FiscalYearID
             AND descendant.DataObjectCode = tr.DescendantCode
            WHERE {$whereSql}
        ";

        $sql = "
            SELECT
                tr.FiscalYearID,
                tr.AncestorCode,
                ancestor.DataObjectName AS AncestorName,
                tr.DescendantCode,
                descendant.DataObjectName AS DescendantName,
                descendant.DataObjectCodeParent AS DescendantParentCode,
                descendant.DataObjectTypeID AS DescendantTypeID,
                dot.DataObjectTypeName AS DescendantTypeName,
                tr.Depth
            FROM dbo.tblDataObjectTree tr
            LEFT JOIN dbo.tblDataObjectCodes ancestor
              ON ancestor.FiscalYearID = tr.FiscalYearID
             AND ancestor.DataObjectCode = tr.AncestorCode
            LEFT JOIN dbo.tblDataObjectCodes descendant
              ON descendant.FiscalYearID = tr.FiscalYearID
             AND descendant.DataObjectCode = tr.DescendantCode
            LEFT JOIN dbo.tblDataObjectTypes dot
              ON dot.DataObjectTypeID = descendant.DataObjectTypeID
            WHERE {$whereSql}
            ORDER BY tr.AncestorCode ASC, tr.Depth ASC, tr.DescendantCode ASC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        ";

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }

    public function listTreeNodes(int $fiscalYearId, string $search = ''): array
    {
        if ($fiscalYearId <= 0) {
            return [];
        }

        $search = trim($search);
        $params = [':fyWhere' => $fiscalYearId];
        $where = 'c.FiscalYearID = :fyWhere';

        if ($search !== '') {
            $where .= "
                AND (
                    c.DataObjectCode LIKE :searchCode
                    OR ISNULL(c.DataObjectName, '') LIKE :searchName
                    OR EXISTS (
                        SELECT 1
                        FROM dbo.tblDataObjectTree tr
                        INNER JOIN dbo.tblDataObjectCodes matched
                          ON matched.FiscalYearID = tr.FiscalYearID
                         AND matched.DataObjectCode = tr.DescendantCode
                        WHERE tr.FiscalYearID = c.FiscalYearID
                          AND tr.AncestorCode = c.DataObjectCode
                          AND (
                              matched.DataObjectCode LIKE :searchMatchedCode
                              OR ISNULL(matched.DataObjectName, '') LIKE :searchMatchedName
                          )
                    )
                )
            ";
            $searchLike = '%' . $search . '%';
            $params[':searchCode'] = $searchLike;
            $params[':searchName'] = $searchLike;
            $params[':searchMatchedCode'] = $searchLike;
            $params[':searchMatchedName'] = $searchLike;
        }

        $sql = "
            SELECT
                c.FiscalYearID,
                c.DataObjectCode,
                c.DataObjectName,
                c.DataObjectCodeParent,
                c.DataObjectTypeID,
                dot.DataObjectTypeName,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectCodes child
                    WHERE child.FiscalYearID = c.FiscalYearID
                      AND child.DataObjectCodeParent = c.DataObjectCode
                ) THEN 1 ELSE 0 END AS HasChildren,
                CASE WHEN :rawSearchForMatch = '' THEN 0
                     WHEN c.DataObjectCode LIKE :rawSearchLikeCode OR ISNULL(c.DataObjectName, '') LIKE :rawSearchLikeName THEN 1
                     ELSE 0
                END AS IsSearchMatch,
                CASE WHEN :rawSearchForDescendant = '' THEN 0
                     WHEN EXISTS (
                        SELECT 1
                        FROM dbo.tblDataObjectTree tr
                        INNER JOIN dbo.tblDataObjectCodes matched
                          ON matched.FiscalYearID = tr.FiscalYearID
                         AND matched.DataObjectCode = tr.DescendantCode
                        WHERE tr.FiscalYearID = c.FiscalYearID
                          AND tr.AncestorCode = c.DataObjectCode
                          AND tr.DescendantCode <> c.DataObjectCode
                          AND (
                              matched.DataObjectCode LIKE :rawSearchLikeDescendantCode
                              OR ISNULL(matched.DataObjectName, '') LIKE :rawSearchLikeDescendantName
                          )
                     ) THEN 1
                     ELSE 0
                END AS HasSearchMatchDescendant
            FROM dbo.tblDataObjectCodes c
            LEFT JOIN dbo.tblDataObjectTypes dot
              ON dot.DataObjectTypeID = c.DataObjectTypeID
            WHERE {$where}
            ORDER BY
                CASE WHEN c.DataObjectCodeParent IS NULL OR LTRIM(RTRIM(c.DataObjectCodeParent)) = '' THEN 0 ELSE 1 END,
                c.DataObjectCodeParent,
                c.DataObjectCode
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $searchLike = '%' . $search . '%';
        $stmt->bindValue(':rawSearchForMatch', $search);
        $stmt->bindValue(':rawSearchForDescendant', $search);
        $stmt->bindValue(':rawSearchLikeCode', $searchLike);
        $stmt->bindValue(':rawSearchLikeName', $searchLike);
        $stmt->bindValue(':rawSearchLikeDescendantCode', $searchLike);
        $stmt->bindValue(':rawSearchLikeDescendantName', $searchLike);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
