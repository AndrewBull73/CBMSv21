<?php
declare(strict_types=1);

namespace App\Models;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class CurrenciesAdminModel
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
            $where[] = '(c.CurrencyCode LIKE :search OR c.CurrencyName LIKE :search OR ISNULL(c.CurrencySymbol, \'\') LIKE :search)';
            $params[':search'] = '%' . strtoupper($search) . '%';
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'c.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                c.CurrencyCode,
                c.CurrencyName,
                c.CurrencySymbol,
                c.IsoNumericCode,
                c.DecimalPlaces,
                c.IsSystemDefault,
                c.IsActive,
                c.SortOrder,
                c.CreatedAt,
                c.UpdatedAt,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblVersions v
                    WHERE v.BaseCurrency = c.CurrencyCode
                ) AS VersionUsageCount,
                CASE
                    WHEN OBJECT_ID('dbo.tblCurrencyRates', 'U') IS NULL THEN 0
                    ELSE (
                        SELECT COUNT(*)
                        FROM dbo.tblCurrencyRates r
                        WHERE r.FromCurrencyCode = c.CurrencyCode
                           OR r.ToCurrencyCode = c.CurrencyCode
                    )
                END AS RateUsageCount
            FROM dbo.tblCurrencies c
            {$whereSql}
            ORDER BY c.SortOrder, c.CurrencyCode
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getByCode(string $currencyCode): ?array
    {
        $currencyCode = strtoupper(trim($currencyCode));
        if ($currencyCode === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                CurrencyCode,
                CurrencyName,
                CurrencySymbol,
                IsoNumericCode,
                DecimalPlaces,
                IsSystemDefault,
                IsActive,
                SortOrder,
                CreatedAt,
                UpdatedAt
            FROM dbo.tblCurrencies
            WHERE CurrencyCode = :currencyCode
        ");
        $stmt->execute([':currencyCode' => $currencyCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): string
    {
        $currencyCode = strtoupper(trim((string) ($data['CurrencyCode'] ?? '')));
        $currencyName = trim((string) ($data['CurrencyName'] ?? ''));
        $currencySymbol = $this->nullIfEmpty($data['CurrencySymbol'] ?? null);
        $isoNumericCode = $this->nullIfEmpty($data['IsoNumericCode'] ?? null);
        $decimalPlaces = (int) ($data['DecimalPlaces'] ?? 2);
        $sortOrder = (int) ($data['SortOrder'] ?? 100);
        $isSystemDefault = !empty($data['IsSystemDefault']) ? 1 : 0;
        $isActive = !empty($data['IsActive']) ? 1 : 0;

        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new \RuntimeException('Currency code must be a 3-letter uppercase code.');
        }
        if ($currencyName === '') {
            throw new \RuntimeException('Currency name is required.');
        }
        if ($isoNumericCode !== null && !preg_match('/^[0-9]{3}$/', $isoNumericCode)) {
            throw new \RuntimeException('ISO numeric code must be a 3-digit code when provided.');
        }
        if ($decimalPlaces < 0 || $decimalPlaces > 6) {
            throw new \RuntimeException('Decimal places must be between 0 and 6.');
        }
        if ($isSystemDefault === 1 && $isActive !== 1) {
            throw new \RuntimeException('A system-default currency must be active.');
        }

        $existing = $this->getByCode($currencyCode);

        $this->pdo->beginTransaction();
        try {
            if ($isSystemDefault === 1) {
                $clearStmt = $this->pdo->prepare("
                    UPDATE dbo.tblCurrencies
                    SET IsSystemDefault = 0,
                        UpdatedAt = SYSDATETIME()
                    WHERE CurrencyCode <> :currencyCode
                      AND IsSystemDefault = 1
                ");
                $clearStmt->execute([':currencyCode' => $currencyCode]);
            }

            if ($existing !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE dbo.tblCurrencies
                    SET CurrencyName = :currencyName,
                        CurrencySymbol = :currencySymbol,
                        IsoNumericCode = :isoNumericCode,
                        DecimalPlaces = :decimalPlaces,
                        IsSystemDefault = :isSystemDefault,
                        IsActive = :isActive,
                        SortOrder = :sortOrder,
                        UpdatedAt = SYSDATETIME()
                    WHERE CurrencyCode = :currencyCode
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dbo.tblCurrencies (
                        CurrencyCode,
                        CurrencyName,
                        CurrencySymbol,
                        IsoNumericCode,
                        DecimalPlaces,
                        IsSystemDefault,
                        IsActive,
                        SortOrder,
                        CreatedAt,
                        UpdatedAt
                    ) VALUES (
                        :currencyCode,
                        :currencyName,
                        :currencySymbol,
                        :isoNumericCode,
                        :decimalPlaces,
                        :isSystemDefault,
                        :isActive,
                        :sortOrder,
                        SYSDATETIME(),
                        SYSDATETIME()
                    )
                ");
            }

            $stmt->execute([
                ':currencyCode' => $currencyCode,
                ':currencyName' => $currencyName,
                ':currencySymbol' => $currencySymbol,
                ':isoNumericCode' => $isoNumericCode,
                ':decimalPlaces' => $decimalPlaces,
                ':isSystemDefault' => $isSystemDefault,
                ':isActive' => $isActive,
                ':sortOrder' => $sortOrder,
            ]);

            $this->pdo->commit();
            return $currencyCode;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveImportRow(array $data): bool
    {
        $currencyCode = strtoupper(trim((string) ($data['CurrencyCode'] ?? '')));
        if ($currencyCode === '') {
            throw new \RuntimeException('CurrencyCode is required.');
        }

        $existing = $this->getByCode($currencyCode);
        $this->save([
            'CurrencyCode' => $currencyCode,
            'CurrencyName' => trim((string) ($data['CurrencyName'] ?? '')),
            'CurrencySymbol' => trim((string) ($data['CurrencySymbol'] ?? '')),
            'IsoNumericCode' => trim((string) ($data['IsoNumericCode'] ?? '')),
            'DecimalPlaces' => $data['DecimalPlaces'] ?? 2,
            'SortOrder' => $data['SortOrder'] ?? 100,
            'IsSystemDefault' => (int) ($data['IsSystemDefault'] ?? 0),
            'IsActive' => array_key_exists('IsActive', $data) ? (int) $data['IsActive'] : 1,
        ]);

        return $existing === null;
    }

    public function buildTemplateWorkbook(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Currencies');

        $headers = [
            'CurrencyCode',
            'CurrencyName',
            'CurrencySymbol',
            'IsoNumericCode',
            'DecimalPlaces',
            'IsSystemDefault',
            'IsActive',
            'SortOrder',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['LSL', 'Lesotho Loti', 'L', '426', 2, 1, 1, 10],
            ['USD', 'US Dollar', '$', '840', 2, 0, 1, 20],
        ], null, 'A2');

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['Column', 'Required', 'Notes'],
            ['CurrencyCode', 'Yes', 'Three-letter ISO-style currency code. Used as the upsert key.'],
            ['CurrencyName', 'Yes', 'Client-facing currency name.'],
            ['CurrencySymbol', 'No', 'Optional symbol such as $, L, or A$.'],
            ['IsoNumericCode', 'No', 'Optional three-digit ISO numeric code.'],
            ['DecimalPlaces', 'No', 'Defaults to 2 when blank. Allowed range is 0 to 6.'],
            ['IsSystemDefault', 'No', 'Use 1 or 0. Only one active currency can be the system default.'],
            ['IsActive', 'No', 'Use 1 or 0. Defaults to 1 when blank.'],
            ['SortOrder', 'No', 'Controls dropdown and register ordering. Defaults to 100 when blank.'],
        ], null, 'A1');

        for ($col = 1; $col <= 3; $col++) {
            $notes->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function buildExportWorkbook(array $filters): Spreadsheet
    {
        $rows = $this->listRows($filters);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Currencies');

        $headers = [
            'CurrencyCode',
            'CurrencyName',
            'CurrencySymbol',
            'IsoNumericCode',
            'DecimalPlaces',
            'IsSystemDefault',
            'IsActive',
            'SortOrder',
            'VersionUsageCount',
            'RateUsageCount',
            'CreatedAt',
            'UpdatedAt',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                trim((string) ($row['CurrencyCode'] ?? '')),
                (string) ($row['CurrencyName'] ?? ''),
                (string) ($row['CurrencySymbol'] ?? ''),
                (string) ($row['IsoNumericCode'] ?? ''),
                (int) ($row['DecimalPlaces'] ?? 0),
                (int) ($row['IsSystemDefault'] ?? 0),
                (int) ($row['IsActive'] ?? 0),
                (int) ($row['SortOrder'] ?? 0),
                (int) ($row['VersionUsageCount'] ?? 0),
                (int) ($row['RateUsageCount'] ?? 0),
                (string) ($row['CreatedAt'] ?? ''),
                (string) ($row['UpdatedAt'] ?? ''),
            ]], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    public function listCurrencyOptions(): array
    {
        $stmt = $this->pdo->query("
            SELECT CurrencyCode, CurrencyName, IsActive
            FROM dbo.tblCurrencies
            ORDER BY SortOrder, CurrencyCode
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
