<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class TransactionTypeSegmentConfigController extends BaseController
{
    protected array $acl = [
        'list' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_VIEW', 'FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'delete' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function list(): void
    {
        $ctxFy = (int)SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int)SessionHelper::get('VersionID', 0);
        $transactionTypeNameExpr = $this->transactionTypeNameSelectExpr('tt');
        $transactionTypeListNameExpr = $this->transactionTypeNameSelectExpr();

        $fy = trim((string)($_GET['fy'] ?? (string)$ctxFy));
        $ver = trim((string)($_GET['ver'] ?? (string)$ctxVer));
        $tt = trim((string)($_GET['tt'] ?? ''));
        $active = trim((string)($_GET['active'] ?? '1'));
        $editId = (int)($_GET['edit_id'] ?? 0);

        $where = [];
        $params = [];

        if ($fy !== '') {
            $where[] = 'c.FiscalYearID = ?';
            $params[] = (int)$fy;
        }
        if ($ver !== '') {
            $where[] = 'c.VersionID = ?';
            $params[] = (int)$ver;
        }
        if ($tt !== '') {
            $where[] = 'c.TransactionTypeCode = ?';
            $params[] = $tt;
        }
        if ($active === '1') {
            $where[] = 'c.ActiveFlag = 1';
        } elseif ($active === '0') {
            $where[] = 'c.ActiveFlag = 0';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT c.TransactionTypeSegmentConfigID,
                       c.FiscalYearID,
                       c.VersionID,
                       c.TransactionTypeCode,
                       c.SegmentNo,
                       c.VisibleFlag,
                       c.RequiredFlag,
                       c.LookupSourceType,
                       c.LookupFilter,
                       c.DisplayOrder,
                       c.ActiveFlag,
                       c.UpdatedBy,
                       c.UpdatedDate,
                       {$transactionTypeNameExpr} AS TransactionTypeName
                FROM dbo.tblTransactionTypeSegmentConfig c
                LEFT JOIN dbo.tblTransactionTypes tt
                  ON tt.TransactionTypeCode = c.TransactionTypeCode
                {$whereSql}
                ORDER BY c.TransactionTypeCode, c.DisplayOrder, c.SegmentNo, c.TransactionTypeSegmentConfigID";
        $fallbackSql = "SELECT c.TransactionTypeSegmentConfigID,
                               c.FiscalYearID,
                               c.VersionID,
                               c.TransactionTypeCode,
                               c.SegmentNo,
                               c.VisibleFlag,
                               c.RequiredFlag,
                               c.LookupSourceType,
                               c.LookupFilter,
                               c.DisplayOrder,
                               c.ActiveFlag,
                               c.UpdatedBy,
                               c.UpdatedDate,
                               CAST(NULL AS NVARCHAR(255)) AS TransactionTypeName
                        FROM dbo.tblTransactionTypeSegmentConfig c
                        {$whereSql}
                        ORDER BY c.TransactionTypeCode, c.DisplayOrder, c.SegmentNo, c.TransactionTypeSegmentConfigID";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (!$this->isInvalidTransactionTypeNameColumnError($e)) {
                throw $e;
            }

            $stmt = $this->db->prepare($fallbackSql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        try {
            $ttStmt = $this->db->query(
                "SELECT TransactionTypeCode, {$transactionTypeListNameExpr} AS TransactionTypeName
                 FROM dbo.tblTransactionTypes
                 ORDER BY TransactionTypeCode"
            );
            $transactionTypes = $ttStmt ? ($ttStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            if (!$this->isInvalidTransactionTypeNameColumnError($e)) {
                throw $e;
            }

            $ttStmt = $this->db->query(
                "SELECT TransactionTypeCode, CAST(NULL AS NVARCHAR(255)) AS TransactionTypeName
                 FROM dbo.tblTransactionTypes
                 ORDER BY TransactionTypeCode"
            );
            $transactionTypes = $ttStmt ? ($ttStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        }

        $editRow = null;
        if ($editId > 0) {
            $eStmt = $this->db->prepare(
                "SELECT *
                 FROM dbo.tblTransactionTypeSegmentConfig
                 WHERE TransactionTypeSegmentConfigID = ?"
            );
            $eStmt->execute([$editId]);
            $editRow = $eStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $this->render('config/TransactionTypeSegmentConfigList', [
            'title' => 'Transaction Type Segment Config',
            'rows' => $rows,
            'transactionTypes' => $transactionTypes,
            'filters' => [
                'fy' => $fy,
                'ver' => $ver,
                'tt' => $tt,
                'active' => $active,
            ],
            'editRow' => $editRow,
            'ctxFy' => $ctxFy,
            'ctxVer' => $ctxVer,
        ]);
    }

    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=transaction-type-segment-config/list');
            return;
        }

        $id = (int)($_POST['TransactionTypeSegmentConfigID'] ?? 0);
        $fyRaw = trim((string)($_POST['FiscalYearID'] ?? ''));
        $verRaw = trim((string)($_POST['VersionID'] ?? ''));
        $tt = trim((string)($_POST['TransactionTypeCode'] ?? ''));
        $segmentNo = (int)($_POST['SegmentNo'] ?? 0);
        $visible = isset($_POST['VisibleFlag']) ? 1 : 0;
        $required = isset($_POST['RequiredFlag']) ? 1 : 0;
        $lookupSourceType = trim((string)($_POST['LookupSourceType'] ?? 'tblSegments'));
        $lookupFilter = trim((string)($_POST['LookupFilter'] ?? ''));
        $displayOrder = (int)($_POST['DisplayOrder'] ?? $segmentNo);
        $active = isset($_POST['ActiveFlag']) ? 1 : 0;
        $updatedBy = (int)SessionHelper::get('auth.user_id', 1);

        if ($tt === '' || $segmentNo < 1 || $segmentNo > 20) {
            $this->flashError('Transaction Type and Segment No (1-20) are required.');
            header('Location: index.php?route=transaction-type-segment-config/list');
            return;
        }

        $fy = ($fyRaw === '') ? null : (int)$fyRaw;
        $ver = ($verRaw === '') ? null : (int)$verRaw;
        $lookupFilterValue = ($lookupFilter === '') ? null : $lookupFilter;
        if ($displayOrder <= 0) {
            $displayOrder = $segmentNo;
        }
        if ($lookupSourceType === '') {
            $lookupSourceType = 'tblSegments';
        }

        try {
            if ($id > 0) {
                $sql = "UPDATE dbo.tblTransactionTypeSegmentConfig
                        SET FiscalYearID = ?,
                            VersionID = ?,
                            TransactionTypeCode = ?,
                            SegmentNo = ?,
                            VisibleFlag = ?,
                            RequiredFlag = ?,
                            LookupSourceType = ?,
                            LookupFilter = ?,
                            DisplayOrder = ?,
                            ActiveFlag = ?,
                            UpdatedBy = ?,
                            UpdatedDate = GETDATE()
                        WHERE TransactionTypeSegmentConfigID = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $fy, $ver, $tt, $segmentNo, $visible, $required, $lookupSourceType,
                    $lookupFilterValue, $displayOrder, $active, $updatedBy, $id
                ]);
                $this->flashSuccess('Configuration row updated.');
            } else {
                $sql = "INSERT INTO dbo.tblTransactionTypeSegmentConfig
                        (FiscalYearID, VersionID, TransactionTypeCode, SegmentNo, VisibleFlag, RequiredFlag, LookupSourceType, LookupFilter, DisplayOrder, ActiveFlag, UpdatedBy, UpdatedDate)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $fy, $ver, $tt, $segmentNo, $visible, $required, $lookupSourceType,
                    $lookupFilterValue, $displayOrder, $active, $updatedBy
                ]);
                $this->flashSuccess('Configuration row created.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Save failed: ' . $e->getMessage());
        }

        $query = [];
        if ($fyRaw !== '') {
            $query['fy'] = $fyRaw;
        }
        if ($verRaw !== '') {
            $query['ver'] = $verRaw;
        }
        if ($tt !== '') {
            $query['tt'] = $tt;
        }
        $query['active'] = (string)$active;
        $qs = http_build_query($query);
        header('Location: index.php?route=transaction-type-segment-config/list' . ($qs !== '' ? '&' . $qs : ''));
    }

    public function delete(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=transaction-type-segment-config/list');
            return;
        }

        $id = (int)($_POST['TransactionTypeSegmentConfigID'] ?? 0);
        if ($id <= 0) {
            $this->flashError('Missing row id.');
            header('Location: index.php?route=transaction-type-segment-config/list');
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "DELETE FROM dbo.tblTransactionTypeSegmentConfig
                 WHERE TransactionTypeSegmentConfigID = ?"
            );
            $stmt->execute([$id]);
            $this->flashSuccess('Configuration row deleted.');
        } catch (\Throwable $e) {
            $this->flashError('Delete failed: ' . $e->getMessage());
        }

        $returnTo = trim((string)($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = 'index.php?route=transaction-type-segment-config/list';
        }
        header('Location: ' . $returnTo);
    }

    private function transactionTypeNameSelectExpr(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        foreach ($this->transactionTypeNameColumns() as $column) {
            if ($this->tableColumnExists('dbo.tblTransactionTypes', $column)) {
                return $prefix . $column;
            }
        }

        return "CAST(NULL AS NVARCHAR(255))";
    }

    private function transactionTypeNameColumns(): array
    {
        return [
            'TransactionTypeName',
            'Name',
            'TypeName',
            'TransactionTypeDesc',
            'Description',
        ];
    }

    private function tableColumnExists(string $qualifiedTableName, string $columnName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :tableSchema
              AND TABLE_NAME = :tableName
              AND COLUMN_NAME = :columnName
        ");
        [$schemaName, $tableName] = $this->splitQualifiedTableName($qualifiedTableName);
        $stmt->execute([
            ':tableSchema' => $schemaName,
            ':tableName' => $tableName,
            ':columnName' => $columnName,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function splitQualifiedTableName(string $qualifiedTableName): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('.', $qualifiedTableName))));
        if (count($parts) >= 2) {
            return [
                trim($parts[count($parts) - 2], '[]'),
                trim($parts[count($parts) - 1], '[]'),
            ];
        }

        return ['dbo', trim($qualifiedTableName, '[]')];
    }

    private function isInvalidTransactionTypeNameColumnError(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), "Invalid column name 'TransactionTypeName'")
            || str_contains($e->getMessage(), "Invalid column name 'Name'")
            || str_contains($e->getMessage(), "Invalid column name 'TypeName'")
            || str_contains($e->getMessage(), "Invalid column name 'TransactionTypeDesc'")
            || str_contains($e->getMessage(), "Invalid column name 'Description'");
    }
}
