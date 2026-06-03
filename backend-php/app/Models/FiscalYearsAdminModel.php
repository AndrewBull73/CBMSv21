<?php
declare(strict_types=1);

namespace App\Models;

final class FiscalYearsAdminModel
{
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
            $where[] = '(CAST(fy.FiscalYearID AS NVARCHAR(20)) LIKE :search OR fy.YearLabel LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'fy.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';
        $systemDefaultFiscalYearId = $this->getSystemDefaultFiscalYearId();
        $systemDefaultVersionId = $this->getSystemDefaultVersionId();

        $sql = "
            SELECT
                fy.FiscalYearID,
                fy.YearLabel,
                fy.StartDate,
                fy.EndDate,
                fy.IsActive,
                fy.CreatedAt,
                fy.UpdatedAt,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblVersions v
                    WHERE v.FiscalYearID = fy.FiscalYearID
                ) AS VersionCount,
                (
                    SELECT TOP 1 v.VersionLabel
                    FROM dbo.tblVersions v
                    WHERE v.FiscalYearID = fy.FiscalYearID
                      AND ISNULL(v.IsDefault, 0) = 1
                      AND ISNULL(v.IsActive, 1) = 1
                    ORDER BY ISNULL(v.VersionTypeID, 999), v.VersionID
                ) AS DefaultVersionLabel,
                CASE WHEN fy.FiscalYearID = :systemDefaultFiscalYearIdFlag THEN 1 ELSE 0 END AS IsSystemDefault,
                CASE WHEN fy.FiscalYearID = :systemDefaultFiscalYearIdValue THEN :systemDefaultVersionId ELSE 0 END AS SystemDefaultVersionID
            FROM dbo.tblFiscalYears fy
            {$whereSql}
            ORDER BY fy.FiscalYearID DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params + [
            ':systemDefaultFiscalYearIdFlag' => $systemDefaultFiscalYearId,
            ':systemDefaultFiscalYearIdValue' => $systemDefaultFiscalYearId,
            ':systemDefaultVersionId' => $systemDefaultVersionId,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $fiscalYearId): ?array
    {
        if ($fiscalYearId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT FiscalYearID, YearLabel, StartDate, EndDate, IsActive, CreatedAt, UpdatedAt
            FROM dbo.tblFiscalYears
            WHERE FiscalYearID = :fiscalYearId
        ");
        $stmt->execute([':fiscalYearId' => $fiscalYearId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['IsSystemDefault'] = $fiscalYearId === $this->getSystemDefaultFiscalYearId() ? 1 : 0;
        return $row;
    }

    public function save(array $data, string $updatedBy = 'system'): int
    {
        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $originalFiscalYearId = $this->nullIfZero($data['OriginalFiscalYearID'] ?? null);
        $yearLabel = trim((string) ($data['YearLabel'] ?? ''));
        $startDate = $this->normalizeDate($data['StartDate'] ?? '');
        $endDate = $this->normalizeDate($data['EndDate'] ?? '');
        $isActive = !empty($data['IsActive']) ? 1 : 0;
        $isSystemDefault = !empty($data['IsSystemDefault']) ? 1 : 0;

        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal Year ID is required.');
        }
        if ($yearLabel === '') {
            throw new \RuntimeException('Fiscal year label is required.');
        }
        if (($originalFiscalYearId === null) !== empty($data['OriginalFiscalYearID'])) {
            throw new \RuntimeException('The original fiscal year identity is invalid. Reload the form and try again.');
        }
        if ($startDate === null || $endDate === null) {
            throw new \RuntimeException('Start date and end date are required.');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new \RuntimeException('Start date cannot be after end date.');
        }
        if ($isSystemDefault === 1 && $isActive !== 1) {
            throw new \RuntimeException('A system-default fiscal year must be active.');
        }

        $existing = null;
        if ($originalFiscalYearId !== null) {
            if ($originalFiscalYearId !== $fiscalYearId) {
                throw new \RuntimeException('The fiscal year identity cannot be changed once it has been created.');
            }
            $existing = $this->getById($originalFiscalYearId);
            if ($existing === null) {
                throw new \RuntimeException('The fiscal year record no longer exists. Reload the list and try again.');
            }
        } else {
            $existing = $this->getById($fiscalYearId);
        }

        $currentSystemDefaultFiscalYearId = $this->getSystemDefaultFiscalYearId();
        $isCurrentSystemDefault = $existing !== null && $fiscalYearId === $currentSystemDefaultFiscalYearId;
        if ($isCurrentSystemDefault && $isSystemDefault !== 1) {
            throw new \RuntimeException('Select another system-default fiscal year before removing that status from the current system-default fiscal year.');
        }

        $defaultVersionId = null;
        if ($isSystemDefault === 1) {
            $defaultVersionId = $this->getPreferredActiveDefaultVersionId($fiscalYearId);
            if ($defaultVersionId === null) {
                throw new \RuntimeException('A system-default fiscal year must have at least one active default version before it can become the system default.');
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($existing !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE dbo.tblFiscalYears
                    SET YearLabel = :yearLabel,
                        StartDate = :startDate,
                        EndDate = :endDate,
                        IsActive = :isActive,
                        UpdatedAt = SYSDATETIME()
                    WHERE FiscalYearID = :fiscalYearId
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dbo.tblFiscalYears (
                        FiscalYearID,
                        YearLabel,
                        StartDate,
                        EndDate,
                        IsActive,
                        CreatedAt,
                        UpdatedAt
                    ) VALUES (
                        :fiscalYearId,
                        :yearLabel,
                        :startDate,
                        :endDate,
                        :isActive,
                        SYSDATETIME(),
                        SYSDATETIME()
                    )
                ");
            }

            $stmt->execute([
                ':fiscalYearId' => $fiscalYearId,
                ':yearLabel' => $yearLabel,
                ':startDate' => $startDate,
                ':endDate' => $endDate,
                ':isActive' => $isActive,
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
                    (string) $defaultVersionId,
                    'int',
                    $updatedBy,
                    'Default version used when no session context is set.',
                    'Base Configuration'
                )) {
                    throw new \RuntimeException('Failed to update DEFAULT_VERSION.');
                }
            }

            $this->pdo->commit();
            return $fiscalYearId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listFiscalYearOptions(): array
    {
        $stmt = $this->pdo->query("
            SELECT FiscalYearID, YearLabel, IsActive
            FROM dbo.tblFiscalYears
            ORDER BY FiscalYearID DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getPreferredActiveDefaultVersionId(int $fiscalYearId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT VersionID
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fiscalYearId
              AND ISNULL(IsActive, 1) = 1
              AND ISNULL(IsDefault, 0) = 1
            ORDER BY ISNULL(VersionTypeID, 999), VersionID
        ");
        $stmt->execute([':fiscalYearId' => $fiscalYearId]);
        $versionId = $stmt->fetchColumn();
        return $versionId === false ? null : (int) $versionId;
    }

    private function nullIfZero(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date instanceof \DateTimeImmutable || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            throw new \RuntimeException('Start date and end date must be valid dates.');
        }

        return $date->format('Y-m-d');
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
