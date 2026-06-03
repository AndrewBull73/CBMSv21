<?php
declare(strict_types=1);

namespace App\Models;

final class VersionsAdminModel
{
    private const VALID_STATUSES = ['DRAFT', 'OPEN', 'ACTIVE', 'SUSPENDED', 'CLOSED'];

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function listRows(array $filters): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(CAST(v.VersionID AS NVARCHAR(20)) LIKE :search OR v.VersionLabel LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $fiscalYearId = (int) ($filters['fy'] ?? 0);
        if ($fiscalYearId > 0) {
            $where[] = 'v.FiscalYearID = :fiscalYearId';
            $params[':fiscalYearId'] = $fiscalYearId;
        }

        $versionTypeId = (int) ($filters['version_type_id'] ?? 0);
        if ($versionTypeId > 0) {
            $where[] = 'ISNULL(v.VersionTypeID, 0) = :versionTypeId';
            $params[':versionTypeId'] = $versionTypeId;
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'v.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';
        $systemDefaultFiscalYearId = $this->getSystemDefaultFiscalYearId();
        $systemDefaultVersionId = $this->getSystemDefaultVersionId();

        $sql = "
            SELECT
                v.VersionID,
                v.FiscalYearID,
                fy.YearLabel,
                v.VersionLabel,
                v.VersionTypeID,
                vt.VersionTypeCode,
                vt.VersionTypeName,
                v.VersionStatus,
                v.BaseFiscalYearID,
                v.BaseVersionID,
                base.VersionLabel AS BaseVersionLabel,
                v.ActualsPeriodID,
                v.BaseCurrency,
                v.CeilingsOn,
                v.IsActive,
                v.IsDefault,
                v.CreatedAt,
                v.UpdatedAt,
                CASE WHEN v.FiscalYearID = :systemDefaultFiscalYearId AND v.VersionID = :systemDefaultVersionId THEN 1 ELSE 0 END AS IsSystemDefault
            FROM dbo.tblVersions v
            INNER JOIN dbo.tblFiscalYears fy
                ON fy.FiscalYearID = v.FiscalYearID
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            LEFT JOIN dbo.tblVersions base
                ON base.FiscalYearID = v.BaseFiscalYearID
               AND base.VersionID = v.BaseVersionID
            {$whereSql}
            ORDER BY v.FiscalYearID DESC, ISNULL(v.VersionTypeID, 999), ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params + [
            ':systemDefaultFiscalYearId' => $systemDefaultFiscalYearId,
            ':systemDefaultVersionId' => $systemDefaultVersionId,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getByKey(int $fiscalYearId, int $versionId): ?array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                VersionID,
                FiscalYearID,
                VersionLabel,
                VersionTypeID,
                VersionStatus,
                BaseFiscalYearID,
                BaseVersionID,
                ActualsPeriodID,
                BaseCurrency,
                CeilingsOn,
                IsActive,
                IsDefault,
                CreatedAt,
                UpdatedAt
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
        ");
        $stmt->execute([
            ':fiscalYearId' => $fiscalYearId,
            ':versionId' => $versionId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['IsSystemDefault'] = ($fiscalYearId === $this->getSystemDefaultFiscalYearId() && $versionId === $this->getSystemDefaultVersionId()) ? 1 : 0;
        return $row;
    }

    public function save(array $data, string $updatedBy = 'system'): array
    {
        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $versionId = (int) ($data['VersionID'] ?? 0);
        $originalFiscalYearId = $this->nullIfZero($data['OriginalFiscalYearID'] ?? null);
        $originalVersionId = $this->nullIfZero($data['OriginalVersionID'] ?? null);
        $versionLabel = trim((string) ($data['VersionLabel'] ?? ''));
        $versionTypeId = (int) ($data['VersionTypeID'] ?? 0);
        $versionStatus = $this->normalizeVersionStatus($data['VersionStatus'] ?? 'DRAFT');
        $baseFiscalYearId = $this->nullIfZero($data['BaseFiscalYearID'] ?? null);
        $baseVersionId = $this->nullIfZero($data['BaseVersionID'] ?? null);
        $actualsPeriodId = $this->nullIfZero($data['ActualsPeriodID'] ?? null);
        $baseCurrency = $this->nullIfEmpty(strtoupper(trim((string) ($data['BaseCurrency'] ?? ''))));
        $ceilingsOn = !empty($data['CeilingsOn']) ? 1 : 0;
        $isActive = !empty($data['IsActive']) ? 1 : 0;
        $isDefault = !empty($data['IsDefault']) ? 1 : 0;
        $isSystemDefault = !empty($data['IsSystemDefault']) ? 1 : 0;

        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal year is required.');
        }
        if ($versionLabel === '') {
            throw new \RuntimeException('Version label is required.');
        }
        if ($versionTypeId <= 0) {
            throw new \RuntimeException('Version type is required.');
        }
        if (!$this->fiscalYearExists($fiscalYearId)) {
            throw new \RuntimeException('Selected fiscal year was not found.');
        }
        if (($originalFiscalYearId === null) !== ($originalVersionId === null)) {
            throw new \RuntimeException('The original version identity is incomplete. Reload the form and try again.');
        }
        if (!$this->versionTypeExists($versionTypeId, true)) {
            throw new \RuntimeException('Selected version type was not found or is inactive.');
        }
        if (($baseFiscalYearId === null) !== ($baseVersionId === null)) {
            throw new \RuntimeException('Base fiscal year and base version must both be provided or both be blank.');
        }
        if ($baseFiscalYearId !== null && $baseVersionId !== null && $baseFiscalYearId === $fiscalYearId && $baseVersionId === $versionId) {
            throw new \RuntimeException('A version cannot reference itself as its own base version.');
        }
        if ($baseFiscalYearId !== null && $baseVersionId !== null && !$this->versionExists($baseFiscalYearId, $baseVersionId)) {
            throw new \RuntimeException('The selected base version was not found.');
        }
        if ($baseCurrency !== null && $this->hasCurrenciesTable() && !$this->currencyExists($baseCurrency)) {
            throw new \RuntimeException('The selected base currency was not found or is inactive.');
        }
        if (($isActive === 1 || $isDefault === 1 || $isSystemDefault === 1) && !$this->fiscalYearExists($fiscalYearId, true)) {
            throw new \RuntimeException('Active or default versions must belong to an active fiscal year.');
        }
        if ($isDefault === 1 && $isActive !== 1) {
            throw new \RuntimeException('A default version must be active.');
        }
        if ($isSystemDefault === 1 && ($isDefault !== 1 || $isActive !== 1)) {
            throw new \RuntimeException('A system-default version must also be active and marked as the default for its fiscal year and type.');
        }

        $existing = null;
        if ($originalFiscalYearId !== null && $originalVersionId !== null) {
            if ($originalFiscalYearId !== $fiscalYearId || $originalVersionId !== $versionId) {
                throw new \RuntimeException('The version identity cannot be changed once it has been created.');
            }
            $existing = $this->getByKey($originalFiscalYearId, $originalVersionId);
            if ($existing === null) {
                throw new \RuntimeException('The version record no longer exists. Reload the list and try again.');
            }
        } elseif ($versionId > 0) {
            $existing = $this->getByKey($fiscalYearId, $versionId);
        }

        $currentSystemDefaultFiscalYearId = $this->getSystemDefaultFiscalYearId();
        $currentSystemDefaultVersionId = $this->getSystemDefaultVersionId();
        $isCurrentSystemDefault = $existing !== null
            && $fiscalYearId === $currentSystemDefaultFiscalYearId
            && $versionId === $currentSystemDefaultVersionId;

        if ($isCurrentSystemDefault && $isSystemDefault !== 1) {
            throw new \RuntimeException('Select another system-default version before removing that status from the current system-default version.');
        }

        if ($existing === null && $versionId <= 0) {
            $versionId = $this->nextVersionId($fiscalYearId);
        }

        $this->pdo->beginTransaction();
        try {
            if ($isDefault === 1) {
                $clearStmt = $this->pdo->prepare("
                    UPDATE dbo.tblVersions
                    SET IsDefault = 0,
                        UpdatedAt = SYSDATETIME()
                    WHERE FiscalYearID = :fiscalYearId
                      AND VersionTypeID = :versionTypeId
                      AND ISNULL(IsActive, 1) = 1
                      AND NOT (VersionID = :versionId)
                ");
                $clearStmt->execute([
                    ':fiscalYearId' => $fiscalYearId,
                    ':versionTypeId' => $versionTypeId,
                    ':versionId' => $versionId,
                ]);
            }

            if ($existing !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE dbo.tblVersions
                    SET VersionLabel = :versionLabel,
                        VersionTypeID = :versionTypeId,
                        VersionStatus = :versionStatus,
                        BaseFiscalYearID = :baseFiscalYearId,
                        BaseVersionID = :baseVersionId,
                        ActualsPeriodID = :actualsPeriodId,
                        BaseCurrency = :baseCurrency,
                        CeilingsOn = :ceilingsOn,
                        IsActive = :isActive,
                        IsDefault = :isDefault,
                        UpdatedAt = SYSDATETIME()
                    WHERE FiscalYearID = :fiscalYearId
                      AND VersionID = :versionId
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dbo.tblVersions (
                        VersionID,
                        FiscalYearID,
                        VersionLabel,
                        VersionTypeID,
                        VersionStatus,
                        BaseFiscalYearID,
                        BaseVersionID,
                        ActualsPeriodID,
                        BaseCurrency,
                        CeilingsOn,
                        IsActive,
                        IsDefault,
                        CreatedAt,
                        UpdatedAt
                    ) VALUES (
                        :versionId,
                        :fiscalYearId,
                        :versionLabel,
                        :versionTypeId,
                        :versionStatus,
                        :baseFiscalYearId,
                        :baseVersionId,
                        :actualsPeriodId,
                        :baseCurrency,
                        :ceilingsOn,
                        :isActive,
                        :isDefault,
                        SYSDATETIME(),
                        SYSDATETIME()
                    )
                ");
            }

            $stmt->execute([
                ':versionId' => $versionId,
                ':fiscalYearId' => $fiscalYearId,
                ':versionLabel' => $versionLabel,
                ':versionTypeId' => $versionTypeId,
                ':versionStatus' => $versionStatus !== '' ? $versionStatus : null,
                ':baseFiscalYearId' => $baseFiscalYearId,
                ':baseVersionId' => $baseVersionId,
                ':actualsPeriodId' => $actualsPeriodId,
                ':baseCurrency' => $baseCurrency,
                ':ceilingsOn' => $ceilingsOn,
                ':isActive' => $isActive,
                ':isDefault' => $isDefault,
            ]);

            if ($isSystemDefault === 1) {
                $settings = new SystemSettingsModel($this->pdo);
                if (!$settings->set(
                    'DEFAULT_FISCAL_YEAR',
                    (string) $fiscalYearId,
                    'int',
                    $updatedBy,
                    'Default fiscal year used when no session context is set.',
                    'Base Configuration'
                )) {
                    throw new \RuntimeException('Failed to update DEFAULT_FISCAL_YEAR.');
                }
                if (!$settings->set(
                    'DEFAULT_VERSION',
                    (string) $versionId,
                    'int',
                    $updatedBy,
                    'Default version used when no session context is set.',
                    'Base Configuration'
                )) {
                    throw new \RuntimeException('Failed to update DEFAULT_VERSION.');
                }
            }

            $this->pdo->commit();
            return [
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $versionId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listFiscalYears(): array
    {
        $stmt = $this->pdo->query("
            SELECT FiscalYearID, YearLabel, IsActive
            FROM dbo.tblFiscalYears
            ORDER BY FiscalYearID DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listVersionTypes(): array
    {
        $stmt = $this->pdo->query("
            SELECT VersionTypeID, VersionTypeCode, VersionTypeName, ActiveFlag
            FROM dbo.tblVersionTypes
            WHERE ActiveFlag = 1
            ORDER BY VersionTypeID
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listBaseVersionOptions(?int $fiscalYearId = null): array
    {
        $sql = "
            SELECT
                v.FiscalYearID,
                v.VersionID,
                v.VersionLabel,
                vt.VersionTypeCode,
                v.IsActive
            FROM dbo.tblVersions v
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
        ";
        $params = [];
        if (($fiscalYearId ?? 0) > 0) {
            $sql .= " WHERE v.FiscalYearID = :fiscalYearId";
            $params[':fiscalYearId'] = $fiscalYearId;
        }
        $sql .= " ORDER BY v.FiscalYearID DESC, v.VersionID ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function hasCurrenciesTable(): bool
    {
        $stmt = $this->pdo->query("SELECT CASE WHEN OBJECT_ID('dbo.tblCurrencies', 'U') IS NULL THEN 0 ELSE 1 END");
        return (int) ($stmt->fetchColumn() ?: 0) === 1;
    }

    public function listCurrencies(): array
    {
        if (!$this->hasCurrenciesTable()) {
            return [];
        }

        $codeColumn = $this->firstExistingColumn('dbo.tblCurrencies', ['CurrencyCode', 'Currency', 'Code']);
        if ($codeColumn === null) {
            return [];
        }

        $nameColumn = $this->firstExistingColumn('dbo.tblCurrencies', ['CurrencyName', 'CurrencyDesc', 'CurrencyDescription', 'Name', 'Description']);
        $activeColumn = $this->firstExistingColumn('dbo.tblCurrencies', ['IsActive', 'ActiveFlag']);

        $sql = "SELECT {$codeColumn} AS CurrencyCode";
        if ($nameColumn !== null) {
            $sql .= ", {$nameColumn} AS CurrencyName";
        } else {
            $sql .= ", {$codeColumn} AS CurrencyName";
        }
        $sql .= " FROM dbo.tblCurrencies";
        if ($activeColumn !== null) {
            $sql .= " WHERE ISNULL({$activeColumn}, 1) = 1";
        }
        $sql .= " ORDER BY {$codeColumn}";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function suggestNextVersionId(int $fiscalYearId): int
    {
        if ($fiscalYearId <= 0) {
            return 1;
        }
        return $this->nextVersionId($fiscalYearId);
    }

    public function listStatusOptions(): array
    {
        return self::VALID_STATUSES;
    }

    private function fiscalYearExists(int $fiscalYearId, bool $activeOnly = false): bool
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblFiscalYears WHERE FiscalYearID = :fiscalYearId';
        if ($activeOnly) {
            $sql .= ' AND ISNULL(IsActive, 1) = 1';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fiscalYearId' => $fiscalYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function versionTypeExists(int $versionTypeId, bool $activeOnly = false): bool
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblVersionTypes WHERE VersionTypeID = :versionTypeId';
        if ($activeOnly) {
            $sql .= ' AND ISNULL(ActiveFlag, 1) = 1';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':versionTypeId' => $versionTypeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function versionExists(int $fiscalYearId, int $versionId): bool
    {
        return $this->getByKey($fiscalYearId, $versionId) !== null;
    }

    private function currencyExists(string $currencyCode): bool
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblCurrencies WHERE CurrencyCode = :currencyCode';
        $activeColumn = $this->firstExistingColumn('dbo.tblCurrencies', ['IsActive', 'ActiveFlag']);
        if ($activeColumn !== null) {
            $sql .= " AND ISNULL({$activeColumn}, 1) = 1";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':currencyCode' => $currencyCode]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function nextVersionId(int $fiscalYearId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT ISNULL(MAX(VersionID), 0) + 1
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fiscalYearId
        ");
        $stmt->execute([':fiscalYearId' => $fiscalYearId]);
        return (int) ($stmt->fetchColumn() ?: 1);
    }

    private function nullIfZero(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function firstExistingColumn(string $tableName, array $candidates): ?string
    {
        foreach ($candidates as $columnName) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'dbo'
                  AND TABLE_NAME = :tableName
                  AND COLUMN_NAME = :columnName
            ");
            $stmt->execute([
                ':tableName' => preg_replace('/^dbo\./i', '', $tableName),
                ':columnName' => $columnName,
            ]);
            if ((int) $stmt->fetchColumn() > 0) {
                return $columnName;
            }
        }

        return null;
    }

    private function normalizeVersionStatus(mixed $value): string
    {
        $status = strtoupper(trim((string) $value));
        if ($status === '') {
            throw new \RuntimeException('Version status is required.');
        }
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \RuntimeException('Version status is not valid.');
        }

        return $status;
    }

    private function getSystemDefaultFiscalYearId(): int
    {
        $settings = new SystemSettingsModel($this->pdo);
        return (int) ($settings->get('DEFAULT_FISCAL_YEAR') ?? $settings->get('Default_Fiscal_Year') ?? 0);
    }

    private function getSystemDefaultVersionId(): int
    {
        $settings = new SystemSettingsModel($this->pdo);
        return (int) ($settings->get('DEFAULT_VERSION') ?? $settings->get('Default_Version') ?? 0);
    }
}
