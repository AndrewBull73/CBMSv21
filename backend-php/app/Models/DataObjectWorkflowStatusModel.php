<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DataObjectWorkflowStatusModel
{
    public const STATUS_OPTIONS = ['OPEN', 'IN PROGRESS', 'COMPLETED', 'APPROVED', 'REJECTED', 'CLOSED'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        // If your connection doesn't already set this:
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getStatus(int $fy, int $ver, string $code): ?string
    {
        $sql = "SELECT TOP 1 Status
                FROM dbo.tblDataObjectWorkflowStatus
                WHERE FiscalYearID = :fy AND VersionID = :ver AND DataObjectCode = :code
                ORDER BY DateUpdated DESC, WorkflowStatusID DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':fy',   $fy,   PDO::PARAM_INT);
        $stmt->bindValue(':ver',  $ver,  PDO::PARAM_INT);
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['Status'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public function listPaged(int $fy, int $ver, int $page, int $pageSize, string $search = '', string $status = ''): array
    {
        if ($fy <= 0 || $ver <= 0) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $where = ['c.FiscalYearID = :fyCodes'];
        $params = [
            ':fyCodes' => $fy,
            ':fyStatus' => $fy,
            ':verStatus' => $ver,
        ];

        $search = trim($search);
        if ($search !== '') {
            $where[] = '(c.DataObjectCode LIKE :searchCode OR ISNULL(c.DataObjectName, \'\') LIKE :searchName)';
            $searchLike = '%' . $search . '%';
            $params[':searchCode'] = $searchLike;
            $params[':searchName'] = $searchLike;
        }

        $status = strtoupper(trim($status));
        if ($status !== '') {
            if ($status === 'NOT SET') {
                $where[] = 'wf.Status IS NULL';
            } else {
                $where[] = 'UPPER(ISNULL(wf.Status, \'\')) = :filterStatus';
                $params[':filterStatus'] = $status;
            }
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = "
            FROM dbo.tblDataObjectCodes c
            OUTER APPLY (
                SELECT TOP 1
                    w.WorkflowStatusID,
                    w.Status,
                    w.UpdatedBy,
                    w.DateUpdated
                FROM dbo.tblDataObjectWorkflowStatus w
                WHERE w.FiscalYearID = :fyStatus
                  AND w.VersionID = :verStatus
                  AND w.DataObjectCode = c.DataObjectCode
                ORDER BY w.DateUpdated DESC, w.WorkflowStatusID DESC
            ) wf
            LEFT JOIN dbo.tblDataObjectTypes dot
              ON dot.DataObjectTypeID = c.DataObjectTypeID
            WHERE {$whereSql}
        ";

        $countStmt = $this->db->prepare('SELECT COUNT(*) ' . $fromSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $sql = "
            SELECT
                c.FiscalYearID,
                :selectedVersionId AS VersionID,
                c.DataObjectCode,
                c.DataObjectName,
                c.DataObjectCodeParent,
                c.DataObjectTypeID,
                dot.DataObjectTypeName,
                wf.WorkflowStatusID,
                wf.Status,
                wf.UpdatedBy,
                wf.DateUpdated
            {$fromSql}
            ORDER BY c.DataObjectCode ASC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':selectedVersionId', $ver, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(int $fy, int $ver): array
    {
        $summary = [
            'total_codes' => 0,
            'set_count' => 0,
            'missing_count' => 0,
            'open_count' => 0,
            'closed_count' => 0,
        ];

        if ($fy <= 0 || $ver <= 0) {
            return $summary;
        }

        $sql = "
            SELECT
                COUNT(*) AS TotalCodes,
                SUM(CASE WHEN wf.Status IS NULL THEN 0 ELSE 1 END) AS SetCount,
                SUM(CASE WHEN wf.Status IS NULL THEN 1 ELSE 0 END) AS MissingCount,
                SUM(CASE WHEN UPPER(ISNULL(wf.Status, '')) = 'OPEN' THEN 1 ELSE 0 END) AS OpenCount,
                SUM(CASE WHEN UPPER(ISNULL(wf.Status, '')) IN ('COMPLETED', 'APPROVED', 'CLOSED') THEN 1 ELSE 0 END) AS ClosedCount
            FROM dbo.tblDataObjectCodes c
            OUTER APPLY (
                SELECT TOP 1 w.Status
                FROM dbo.tblDataObjectWorkflowStatus w
                WHERE w.FiscalYearID = :statusFiscalYearId
                  AND w.VersionID = :statusVersionId
                  AND w.DataObjectCode = c.DataObjectCode
                ORDER BY w.DateUpdated DESC, w.WorkflowStatusID DESC
            ) wf
            WHERE c.FiscalYearID = :codeFiscalYearId
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':statusFiscalYearId' => $fy,
            ':statusVersionId' => $ver,
            ':codeFiscalYearId' => $fy,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_codes' => (int)($row['TotalCodes'] ?? 0),
            'set_count' => (int)($row['SetCount'] ?? 0),
            'missing_count' => (int)($row['MissingCount'] ?? 0),
            'open_count' => (int)($row['OpenCount'] ?? 0),
            'closed_count' => (int)($row['ClosedCount'] ?? 0),
        ];
    }

    public function buildMissingStatuses(int $fy, int $ver, string $defaultStatus, int $userId): int
    {
        $defaultStatus = $this->normalizeStatus($defaultStatus);
        if ($fy <= 0 || $ver <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Fiscal year, version, and user are required.');
        }

        $missingBeforeStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes c
            WHERE c.FiscalYearID = :countCodeFiscalYearId
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectWorkflowStatus w
                    WHERE w.FiscalYearID = :countStatusFiscalYearId
                      AND w.VersionID = :countStatusVersionId
                      AND w.DataObjectCode = c.DataObjectCode
              )
        ");
        $missingBeforeStmt->execute([
            ':countCodeFiscalYearId' => $fy,
            ':countStatusFiscalYearId' => $fy,
            ':countStatusVersionId' => $ver,
        ]);
        $missingBefore = (int)($missingBeforeStmt->fetchColumn() ?: 0);

        $sql = "
            INSERT INTO dbo.tblDataObjectWorkflowStatus
                (FiscalYearID, VersionID, DataObjectCode, Status, UpdatedBy, DateUpdated)
            SELECT
                c.FiscalYearID,
                :insertVersionId,
                c.DataObjectCode,
                :insertStatus,
                :insertUser,
                SYSUTCDATETIME()
            FROM dbo.tblDataObjectCodes c
            WHERE c.FiscalYearID = :codeFiscalYearId
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectWorkflowStatus w
                    WHERE w.FiscalYearID = :statusFiscalYearId
                      AND w.VersionID = :statusVersionId
                      AND w.DataObjectCode = c.DataObjectCode
              )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':insertVersionId' => $ver,
            ':insertStatus' => $defaultStatus,
            ':insertUser' => $userId,
            ':codeFiscalYearId' => $fy,
            ':statusFiscalYearId' => $fy,
            ':statusVersionId' => $ver,
        ]);

        return $missingBefore;
    }

    /**
     * @param array<string, string> $statusesByCode
     */
    public function saveStatuses(int $fy, int $ver, array $statusesByCode, int $userId): int
    {
        if ($fy <= 0 || $ver <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Fiscal year, version, and user are required.');
        }

        $this->db->beginTransaction();
        try {
            $updated = 0;
            foreach ($statusesByCode as $code => $status) {
                $code = trim((string)$code);
                if ($code === '') {
                    continue;
                }

                $this->upsertSingleStatus($fy, $ver, $code, $this->normalizeStatus((string)$status), $userId);
                $updated++;
            }

            $this->db->commit();
            return $updated;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function upsertSingleStatus(int $fy, int $ver, string $code, string $status, int $userId): void
    {
        $sqlUpdate = "UPDATE dbo.tblDataObjectWorkflowStatus
                         SET Status = :updateStatus,
                             UpdatedBy = :updateUser,
                             DateUpdated = SYSUTCDATETIME()
                       WHERE FiscalYearID = :updateFy
                         AND VersionID = :updateVer
                         AND DataObjectCode = :updateCode";
        $stmtU = $this->db->prepare($sqlUpdate);
        $stmtU->execute([
            ':updateStatus' => $status,
            ':updateUser' => $userId,
            ':updateFy' => $fy,
            ':updateVer' => $ver,
            ':updateCode' => $code,
        ]);

        if ($stmtU->rowCount() > 0) {
            return;
        }

        $sqlInsert = "INSERT INTO dbo.tblDataObjectWorkflowStatus
                        (FiscalYearID, VersionID, DataObjectCode, Status, UpdatedBy, DateUpdated)
                      VALUES (:insertFy, :insertVer, :insertCode, :insertStatus, :insertUser, SYSUTCDATETIME())";
        $stmtI = $this->db->prepare($sqlInsert);
        $stmtI->execute([
            ':insertFy' => $fy,
            ':insertVer' => $ver,
            ':insertCode' => $code,
            ':insertStatus' => $status,
            ':insertUser' => $userId,
        ]);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, self::STATUS_OPTIONS, true)) {
            throw new \InvalidArgumentException('Invalid workflow status: ' . $status);
        }

        return $status;
    }

    public function setStatus(int $fy, int $ver, string $code, string $status, int $userId): void
{
    $status = $this->normalizeStatus($status);
    $this->db->beginTransaction();
    try {
        // --- Get all descendants (including self)
        $sqlTree = "SELECT DescendantCode 
                      FROM dbo.tblDataObjectTree
                     WHERE FiscalYearID = :fy
                       AND AncestorCode = :code";
        $treeStmt = $this->db->prepare($sqlTree);
        $treeStmt->bindValue(':fy', $fy, PDO::PARAM_INT);
        $treeStmt->bindValue(':code', $code, PDO::PARAM_STR);
        $treeStmt->execute();

        $codes = $treeStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$codes) {
            $codes = [$code]; // fallback → at least update the one passed in
        }

        // --- Upsert each code in workflow status table
        $sqlUpdate = "UPDATE dbo.tblDataObjectWorkflowStatus
                         SET Status = :status,
                             UpdatedBy = :user,
                             DateUpdated = SYSUTCDATETIME()
                       WHERE FiscalYearID = :fy
                         AND VersionID = :ver
                         AND DataObjectCode = :code";
        $stmtU = $this->db->prepare($sqlUpdate);

        $sqlInsert = "INSERT INTO dbo.tblDataObjectWorkflowStatus
                        (FiscalYearID, VersionID, DataObjectCode, Status, UpdatedBy, DateUpdated)
                      VALUES (:fy, :ver, :code, :status, :user, SYSUTCDATETIME())";
        $stmtI = $this->db->prepare($sqlInsert);

        foreach ($codes as $c) {
            // Try update
            $stmtU->execute([
                ':status' => $status,
                ':user'   => $userId,
                ':fy'     => $fy,
                ':ver'    => $ver,
                ':code'   => $c,
            ]);

            if ($stmtU->rowCount() === 0) {
                // If no row updated → insert
                $stmtI->execute([
                    ':fy'     => $fy,
                    ':ver'    => $ver,
                    ':code'   => $c,
                    ':status' => $status,
                    ':user'   => $userId,
                ]);
            }
        }

        $this->db->commit();
    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}

}
