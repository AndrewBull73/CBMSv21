<?php
declare(strict_types=1);

namespace App\Models;

final class DataObjectCodesModel
{
    private \PDO $pdo;
    private string $lastError = '';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /* ------------------------------------------------------------------ */
    /*  LIST / PAGINATION                                                  */
    /* ------------------------------------------------------------------ */
    public function listPaged(
        int $fiscalYearID,
        int $page,
        int $pageSize,
        ?string $q = null,
        ?string $sortCol = 'DataObjectCode',
        ?string $sortDir = 'ASC',
        ?int $typeId = null,
        ?string $status = null
    ): array {
        $page     = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        $offset   = ($page - 1) * $pageSize;

        $sortMap = [
            'DataObjectCode'       => 'o.DataObjectCode',
            'DataObjectName'       => 'o.DataObjectName',
            'DataObjectCodeParent' => 'o.DataObjectCodeParent',
            'DataObjectTypeID'     => 't.DataObjectTypeName',
            'DataObjectCodeStatus' => 'o.DataObjectCodeStatus',
            'DateUpdated'          => 'o.DateUpdated',
        ];
        $orderBy = $sortMap[$sortCol ?? 'DataObjectCode'] ?? 'o.DataObjectCode';
        $dir     = strtoupper((string)$sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = ['o.FiscalYearID = :fy'];
        $params = [':fy' => $fiscalYearID];

        if ($q !== null && $q !== '') {
            $where[]      = '(o.DataObjectCode LIKE :q OR o.DataObjectName LIKE :q OR o.DataObjectDesc LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeId !== null) {
            $where[]           = 'o.DataObjectTypeID = :typeId';
            $params[':typeId'] = $typeId;
        }
        if ($status !== null && $status !== '') {
            $where[]           = "COALESCE(o.DataObjectCodeStatus, '') = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        try {
            // TOTAL COUNT
            $st = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM tblDataObjectCodes o
                LEFT JOIN tblDataObjectTypes t ON o.DataObjectTypeID = t.DataObjectTypeID
                WHERE {$whereSql}
            ");
            $st->execute($params);
            $total = (int)$st->fetchColumn();

            // ROWS
            $sql = "
                SELECT
                    o.FiscalYearID,
                    o.DataObjectCode,
                    o.DataObjectName,
                    o.DataObjectCodeParent,
                    o.DataObjectTypeID,
                    t.DataObjectTypeName,
                    o.DataObjectDesc,
                    o.DataObjectCodeStatus,
                    o.UpdatedBy,
                    o.DateUpdated
                FROM tblDataObjectCodes o
                LEFT JOIN tblDataObjectTypes t ON o.DataObjectTypeID = t.DataObjectTypeID
                WHERE {$whereSql}
                ORDER BY {$orderBy} {$dir}
                OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY;
            ";

            $st = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':off', $offset, \PDO::PARAM_INT);
            $st->bindValue(':lim', $pageSize, \PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("listPaged() failed: " . $e->getMessage(), [
                'fiscalYearID' => $fiscalYearID,
                'page' => $page,
                'pageSize' => $pageSize,
                'q' => $q,
                'sortCol' => $sortCol,
                'sortDir' => $sortDir,
                'typeId' => $typeId,
                'status' => $status,
            ], 'error');
            return ['rows' => [], 'total' => 0];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  GET ONE – includes Level                                           */
    /* ------------------------------------------------------------------ */
    public function getOne(int $fiscalYearID, string $code): ?array
    {
        $sql = "
            SELECT TOP 1 
                doc.*,
                dot.Level,
                dot.DataObjectTypeName
            FROM tblDataObjectCodes doc
            LEFT JOIN tblDataObjectTypes dot ON doc.DataObjectTypeID = dot.DataObjectTypeID
            WHERE doc.FiscalYearID = :fy AND doc.DataObjectCode = :code;
        ";
        try {
            $startedTransaction = !$this->pdo->inTransaction();
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $st = $this->pdo->prepare($sql);
            $st->execute([':fy' => $fiscalYearID, ':code' => $code]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            
            if ($row) {
                $row['attributes'] = $this->getAttributeValues($fiscalYearID, $code);
            }

            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("getOne() failed: " . $e->getMessage(), [
                'fiscalYearID' => $fiscalYearID,
                'code' => $code,
                'sql' => $sql,
            ], 'error');
            return null;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  UPSERT – positional parameters                                     */
    /* ------------------------------------------------------------------ */
    public function upsert(int $fiscalYearID, array $data, int $userId, bool $rebuildTree = true): bool
    {
        $code   = trim($data['DataObjectCode'] ?? '');
        $name   = trim($data['DataObjectName'] ?? '');
        $parent = trim($data['DataObjectCodeParent'] ?? '');
        $typeId = (int)($data['DataObjectTypeID'] ?? 0);
        $desc   = $data['DataObjectDesc'] ?? null;
        $status = $data['DataObjectCodeStatus'] ?? 'Active';

        // Validation
        if ($fiscalYearID <= 0) {
            $this->lastError = 'FiscalYearID is required.';
            return false;
        }
        if (!$this->fiscalYearExists($fiscalYearID)) {
            $this->lastError = 'Selected fiscal year was not found.';
            return false;
        }
        if ($code === '' || $name === '' || $typeId <= 0) {
            $this->lastError = 'Missing required fields.';
            return false;
        }
        if ($status !== 'Active' && $status !== 'Inactive') {
            $this->lastError = 'Invalid DataObjectCodeStatus; must be Active or Inactive.';
            return false;
        }
        if ($userId <= 0) {
            $this->lastError = 'Invalid UpdatedBy; must be a positive integer.';
            return false;
        }
        if ($parent !== '' && $parent === $code) {
            $this->lastError = 'Parent cannot be the same as the code.';
            return false;
        }
        if (!$this->typeExists($typeId)) {
            $this->lastError = 'Invalid DataObjectTypeID.';
            return false;
        }
        if ($parent !== '' && !$this->codeExists($fiscalYearID, $parent)) {
            $this->lastError = 'Parent code not found in the same Fiscal Year.';
            return false;
        }
        if (strlen($code) > 50 || strlen($name) > 100 || ($desc !== null && strlen($desc) > 500)) {
            $this->lastError = 'Input exceeds column length limits.';
            return false;
        }

        $sql = <<<'SQL'
MERGE tblDataObjectCodes AS tgt
USING (VALUES (?, ?)) AS src (FiscalYearID, DataObjectCode)
ON (tgt.FiscalYearID = src.FiscalYearID AND tgt.DataObjectCode = src.DataObjectCode)
WHEN MATCHED THEN
    UPDATE SET
        DataObjectName       = ?,
        DataObjectCodeParent = ?,
        DataObjectTypeID     = ?,
        DataObjectDesc       = ?,
        DataObjectCodeStatus = ?,
        UpdatedBy            = ?,
        DateUpdated          = SYSUTCDATETIME()
WHEN NOT MATCHED THEN
    INSERT (FiscalYearID, DataObjectCode, DataObjectName, DataObjectCodeParent,
            DataObjectTypeID, DataObjectDesc, DataObjectCodeStatus,
            UpdatedBy, DateUpdated)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, SYSUTCDATETIME());
SQL;

        try {
            $st = $this->pdo->prepare($sql);

            $params = [
                $fiscalYearID, $code, $name,
                $parent !== '' ? $parent : null,
                $typeId, $desc, $status, $userId,
                $fiscalYearID, $code, $name,
                $parent !== '' ? $parent : null,
                $typeId, $desc, $status, $userId,
            ];

            $result = $st->execute($params);

            if (!empty($data['attributes'] ?? [])) {
                $this->saveAttributeValues($fiscalYearID, $code, $data['attributes'], $userId);
            }

            if ($rebuildTree) {
                $this->rebuildTreeForFiscalYear($fiscalYearID);
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            app_log("upsert() failed: " . $e->getMessage(), [
                'fiscalYearID' => $fiscalYearID,
                'code' => $code,
                'data' => $data,
            ], 'error');
            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  DELETE                                                            */
    /* ------------------------------------------------------------------ */
    public function delete(int $fiscalYearID, string $code): bool
    {
        if ($fiscalYearID <= 0) {
            $this->lastError = 'FiscalYearID is required.';
            return false;
        }
        if ($code === '') {
            $this->lastError = 'DataObjectCode is required.';
            return false;
        }
        if (!$this->codeExists($fiscalYearID, $code)) {
            $this->lastError = 'Data Object Code was not found for the fiscal year.';
            return false;
        }

        try {
            $startedTransaction = !$this->pdo->inTransaction();
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $attrStmt = $this->pdo->prepare("
                DELETE FROM tblDataObjectCodeValues
                WHERE FiscalYearID = :fy AND DataObjectCode = :code;
            ");
            $attrStmt->execute([':fy' => $fiscalYearID, ':code' => $code]);

            $st = $this->pdo->prepare("
                DELETE FROM tblDataObjectCodes
                WHERE FiscalYearID = :fy AND DataObjectCode = :code;
            ");
            $result = $st->execute([':fy' => $fiscalYearID, ':code' => $code]);
            if ($result && $st->rowCount() > 0) {
                $this->rebuildTreeForFiscalYear($fiscalYearID);
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
                return true;
            }
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = 'Delete failed.';
            return false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            app_log("delete() failed: " . $e->getMessage(), [
                'fiscalYearID' => $fiscalYearID,
                'code' => $code,
            ], 'error');
            return false;
        }
    }

    public function saveImportRow(int $fiscalYearID, array $data, int $userId, bool $rebuildTree = false): bool
    {
        $code = trim((string)($data['DataObjectCode'] ?? ''));
        if ($fiscalYearID <= 0 || $code === '' || $userId <= 0) {
            throw new \RuntimeException('Fiscal year, code, and user context are required.');
        }

        $existing = $this->fetchCount(
            'SELECT COUNT(*) FROM tblDataObjectCodes WHERE FiscalYearID = :fy AND DataObjectCode = :code',
            [':fy' => $fiscalYearID, ':code' => $code]
        ) > 0;

        if (!$this->upsert($fiscalYearID, $data, $userId, $rebuildTree)) {
            $message = trim($this->lastError);
            throw new \RuntimeException($message !== '' ? $message : 'Import row could not be saved.');
        }

        return !$existing;
    }

    public function rebuildTreeForFiscalYear(int $fiscalYearID): void
    {
        if ($fiscalYearID <= 0) {
            throw new \RuntimeException('A valid fiscal year is required to rebuild hierarchy links.');
        }

        $rows = [];
        try {
            $stmt = $this->pdo->prepare('
                SELECT DataObjectCode, DataObjectCodeParent
                FROM tblDataObjectCodes
                WHERE FiscalYearID = :fy
            ');
            $stmt->execute([':fy' => $fiscalYearID]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            throw new \RuntimeException('Failed to read data object codes for hierarchy rebuild.');
        }

        $parentByCode = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['DataObjectCode'] ?? ''));
            if ($code === '') {
                continue;
            }

            $parent = trim((string)($row['DataObjectCodeParent'] ?? ''));
            $parentByCode[$code] = $parent !== '' ? $parent : null;
        }

        $treeRows = [];
        foreach (array_keys($parentByCode) as $code) {
            $treeRows[] = [$fiscalYearID, $code, $code, 0];

            $depth = 0;
            $parent = $parentByCode[$code] ?? null;
            $visited = [$code => true];

            while ($parent !== null && $parent !== '') {
                if (!array_key_exists($parent, $parentByCode)) {
                    throw new \RuntimeException('Cannot rebuild hierarchy links because parent code "' . $parent . '" is missing.');
                }
                if (isset($visited[$parent])) {
                    throw new \RuntimeException('Cannot rebuild hierarchy links because a parent-child cycle was detected involving "' . $parent . '".');
                }

                $visited[$parent] = true;
                $depth++;
                $treeRows[] = [$fiscalYearID, $parent, $code, $depth];
                $parent = $parentByCode[$parent] ?? null;
            }
        }

        try {
            $startedTransaction = !$this->pdo->inTransaction();
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM tblDataObjectTree WHERE FiscalYearID = :fy');
            $deleteStmt->execute([':fy' => $fiscalYearID]);

            if ($treeRows !== []) {
                $insertStmt = $this->pdo->prepare('
                    INSERT INTO tblDataObjectTree (FiscalYearID, AncestorCode, DescendantCode, Depth)
                    VALUES (?, ?, ?, ?)
                ');

                foreach ($treeRows as $treeRow) {
                    $insertStmt->execute($treeRow);
                }
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (isset($startedTransaction) && $startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            app_log("rebuildTreeForFiscalYear() failed: " . $e->getMessage(), [
                'fiscalYearID' => $fiscalYearID,
            ], 'error');
            throw new \RuntimeException('Failed to rebuild data object hierarchy links.');
        }
    }

    public function findTypeIdByName(string $typeName): ?int
    {
        $typeName = trim($typeName);
        if ($typeName === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT TOP 1 DataObjectTypeID
                FROM tblDataObjectTypes
                WHERE LTRIM(RTRIM(DataObjectTypeName)) = :typeName
            ');
            $stmt->execute([':typeName' => $typeName]);
            $value = $stmt->fetchColumn();
            return $value === false ? null : (int)$value;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("findTypeIdByName() failed: " . $e->getMessage(), ['typeName' => $typeName], 'error');
            return null;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  HELPER METHODS                                                    */
    /* ------------------------------------------------------------------ */
    private function fetchCount(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("fetchCount() failed: " . $e->getMessage(), ['sql' => $sql], 'error');
            return 0;
        }
    }

    private function codeExists(int $fy, string $code): bool
    {
        try {
            $st = $this->pdo->prepare("
                SELECT 1 FROM tblDataObjectCodes
                WHERE FiscalYearID = :fy AND DataObjectCode = :c;
            ");
            $st->execute([':fy' => $fy, ':c' => $code]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("codeExists() failed: " . $e->getMessage(), [
                'fy' => $fy,
                'code' => $code,
            ], 'error');
            return false;
        }
    }

    private function fiscalYearExists(int $fiscalYearId): bool
    {
        try {
            $st = $this->pdo->prepare("
                SELECT 1
                FROM dbo.tblFiscalYears
                WHERE FiscalYearID = :fy
                  AND ISNULL(IsActive, 1) = 1;
            ");
            $st->execute([':fy' => $fiscalYearId]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("fiscalYearExists() failed: " . $e->getMessage(), [
                'fiscalYearId' => $fiscalYearId,
            ], 'error');
            return false;
        }
    }

    private function typeExists(int $typeId): bool
    {
        try {
            $st = $this->pdo->prepare("
                SELECT 1 FROM tblDataObjectTypes WHERE DataObjectTypeID = :t;
            ");
            $st->execute([':t' => $typeId]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("typeExists() failed: " . $e->getMessage(), [
                'typeId' => $typeId,
            ], 'error');
            return false;
        }
    }

    public function listTypes(): array
    {
        try {
            $st = $this->pdo->query("
                SELECT DataObjectTypeID, DataObjectTypeName
                FROM tblDataObjectTypes
                ORDER BY DataObjectTypeName;
            ");
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("listTypes() failed: " . $e->getMessage(), [], 'error');
            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  LIST PARENTS – only lower Level                                   */
    /* ------------------------------------------------------------------ */
    public function listForFiscalYear(int $fy, ?string $status = null, ?int $currentLevel = null): array
    {
        $sql = "
            SELECT 
                doc.DataObjectCode,
                doc.DataObjectName,
                doc.DataObjectTypeID,
                dot.DataObjectTypeName,
                dot.Level
            FROM tblDataObjectCodes doc
            INNER JOIN tblDataObjectTypes dot ON doc.DataObjectTypeID = dot.DataObjectTypeID
            WHERE doc.FiscalYearID = :fy
        ";
        $params = [':fy' => $fy];

        if ($status !== null && $status !== '') {
            $sql .= " AND COALESCE(doc.DataObjectCodeStatus, '') = :status";
            $params[':status'] = $status;
        }

        if ($currentLevel !== null && $currentLevel > 1) {
            $sql .= " AND dot.Level < :currentLevel";
            $params[':currentLevel'] = $currentLevel;
        }

        $sql .= " ORDER BY doc.DataObjectCode ASC";

        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("listForFiscalYear() failed: " . $e->getMessage(), [
                'fy' => $fy,
                'status' => $status,
                'currentLevel' => $currentLevel,
            ], 'error');
            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  CUSTOM ATTRIBUTES                                                 */
    /* ------------------------------------------------------------------ */
    public function getAllAttributes(?int $typeId = null): array
{
    try {
        $sql = "
            SELECT 
                AttributeID,
                AttributeKey,
                AttributeLabel,
                AttributeType,
                AttributeOptions,
                IsRequired,
                SortOrder,
                HelpText
            FROM tblDataObjectAttributes 
        ";
        $params = [];
        if ($typeId !== null) {
            $sql .= " WHERE DataObjectTypeID = :typeId OR DataObjectTypeID IS NULL";
            $params[':typeId'] = $typeId;
        } else {
            $sql .= " WHERE DataObjectTypeID IS NULL";
        }
        $sql .= " ORDER BY SortOrder, AttributeLabel";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $attrs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($attrs as &$attr) {
            $attr['options'] = !empty($attr['AttributeOptions']) 
                ? json_decode($attr['AttributeOptions'], true) 
                : [];
            $attr['help'] = $attr['HelpText'] ?? '';
        }
        return $attrs;
    } catch (\Throwable $e) {
        $this->lastError = $e->getMessage();
        app_log("getAllAttributes() failed: " . $e->getMessage(), [], 'error');
        return [];
    }
}

    public function getAttributeValues(int $fy, string $code): array
    {
        try {
            $sql = "
                SELECT a.AttributeKey, v.AttributeValue
                FROM tblDataObjectCodeValues v
                INNER JOIN tblDataObjectAttributes a ON v.AttributeID = a.AttributeID
                WHERE v.FiscalYearID = :fy AND v.DataObjectCode = :code
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([':fy' => $fy, ':code' => $code]);
            return $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("getAttributeValues() failed: " . $e->getMessage(), ['fy' => $fy, 'code' => $code], 'error');
            return [];
        }
    }

    private function saveAttributeValues(int $fy, string $code, array $values, int $userId): void
    {
        try {
            $this->pdo->prepare("
                DELETE FROM tblDataObjectCodeValues 
                WHERE FiscalYearID = ? AND DataObjectCode = ?
            ")->execute([$fy, $code]);

            $st = $this->pdo->prepare("
                INSERT INTO tblDataObjectCodeValues 
                (FiscalYearID, DataObjectCode, AttributeID, AttributeValue, UpdatedBy)
                SELECT ?, ?, AttributeID, ?, ?
                FROM tblDataObjectAttributes 
                WHERE AttributeKey = ?
            ");

            foreach ($values as $key => $value) {
                $st->execute([$fy, $code, $value, $userId, $key]);
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            app_log("saveAttributeValues() failed: " . $e->getMessage(), [
                'fy' => $fy,
                'code' => $code,
                'values_count' => count($values),
            ], 'error');
            throw new \RuntimeException('Failed to save data object attribute values.', 0, $e);
        }
    }
}
