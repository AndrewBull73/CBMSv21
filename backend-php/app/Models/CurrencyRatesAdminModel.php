<?php
declare(strict_types=1);

namespace App\Models;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class CurrencyRatesAdminModel
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
            $where[] = '(r.RateType LIKE :search OR ISNULL(r.RateSource, \'\') LIKE :search OR ISNULL(r.Notes, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $fromCurrencyCode = strtoupper(trim((string) ($filters['from_currency_code'] ?? '')));
        if ($fromCurrencyCode !== '') {
            $where[] = 'r.FromCurrencyCode = :fromCurrencyCode';
            $params[':fromCurrencyCode'] = $fromCurrencyCode;
        }

        $toCurrencyCode = strtoupper(trim((string) ($filters['to_currency_code'] ?? '')));
        if ($toCurrencyCode !== '') {
            $where[] = 'r.ToCurrencyCode = :toCurrencyCode';
            $params[':toCurrencyCode'] = $toCurrencyCode;
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'r.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                r.CurrencyRateID,
                r.FromCurrencyCode,
                fromc.CurrencyName AS FromCurrencyName,
                r.ToCurrencyCode,
                toc.CurrencyName AS ToCurrencyName,
                r.RateDate,
                r.RateType,
                r.RateValue,
                r.RateSource,
                r.Notes,
                r.IsActive,
                r.CreatedAt,
                r.UpdatedAt
            FROM dbo.tblCurrencyRates r
            INNER JOIN dbo.tblCurrencies fromc
                ON fromc.CurrencyCode = r.FromCurrencyCode
            INNER JOIN dbo.tblCurrencies toc
                ON toc.CurrencyCode = r.ToCurrencyCode
            {$whereSql}
            ORDER BY r.RateDate DESC, r.FromCurrencyCode, r.ToCurrencyCode, r.CurrencyRateID DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $currencyRateId): ?array
    {
        if ($currencyRateId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                CurrencyRateID,
                FromCurrencyCode,
                ToCurrencyCode,
                RateDate,
                RateType,
                RateValue,
                RateSource,
                Notes,
                IsActive,
                CreatedAt,
                UpdatedAt
            FROM dbo.tblCurrencyRates
            WHERE CurrencyRateID = :currencyRateId
        ");
        $stmt->execute([':currencyRateId' => $currencyRateId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $currencyRateId = (int) ($data['CurrencyRateID'] ?? 0);
        $fromCurrencyCode = strtoupper(trim((string) ($data['FromCurrencyCode'] ?? '')));
        $toCurrencyCode = strtoupper(trim((string) ($data['ToCurrencyCode'] ?? '')));
        $rateDate = trim((string) ($data['RateDate'] ?? ''));
        $rateType = strtoupper(trim((string) ($data['RateType'] ?? 'SPOT')));
        $rateValue = (string) ($data['RateValue'] ?? '');
        $rateSource = $this->nullIfEmpty($data['RateSource'] ?? null);
        $notes = $this->nullIfEmpty($data['Notes'] ?? null);
        $isActive = !empty($data['IsActive']) ? 1 : 0;

        if (!preg_match('/^[A-Z]{3}$/', $fromCurrencyCode) || !preg_match('/^[A-Z]{3}$/', $toCurrencyCode)) {
            throw new \RuntimeException('From and to currency codes must be valid 3-letter codes.');
        }
        if ($fromCurrencyCode === $toCurrencyCode) {
            throw new \RuntimeException('From currency and to currency must be different.');
        }
        if (!$this->currencyExists($fromCurrencyCode) || !$this->currencyExists($toCurrencyCode)) {
            throw new \RuntimeException('Selected currency code was not found.');
        }
        if ($rateDate === '' || strtotime($rateDate) === false) {
            throw new \RuntimeException('Rate date is required and must be a valid date.');
        }
        if (!is_numeric($rateValue) || (float) $rateValue <= 0) {
            throw new \RuntimeException('Rate value must be greater than zero.');
        }
        if ($rateType === '') {
            throw new \RuntimeException('Rate type is required.');
        }

        $existing = $currencyRateId > 0 ? $this->getById($currencyRateId) : null;

        $this->pdo->beginTransaction();
        try {
            if ($existing !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE dbo.tblCurrencyRates
                    SET FromCurrencyCode = :fromCurrencyCode,
                        ToCurrencyCode = :toCurrencyCode,
                        RateDate = :rateDate,
                        RateType = :rateType,
                        RateValue = :rateValue,
                        RateSource = :rateSource,
                        Notes = :notes,
                        IsActive = :isActive,
                        UpdatedAt = SYSDATETIME()
                    WHERE CurrencyRateID = :currencyRateId
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dbo.tblCurrencyRates (
                        FromCurrencyCode,
                        ToCurrencyCode,
                        RateDate,
                        RateType,
                        RateValue,
                        RateSource,
                        Notes,
                        IsActive,
                        CreatedAt,
                        UpdatedAt
                    ) VALUES (
                        :fromCurrencyCode,
                        :toCurrencyCode,
                        :rateDate,
                        :rateType,
                        :rateValue,
                        :rateSource,
                        :notes,
                        :isActive,
                        SYSDATETIME(),
                        SYSDATETIME()
                    )
                ");
            }

            $stmt->execute([
                ':currencyRateId' => $currencyRateId,
                ':fromCurrencyCode' => $fromCurrencyCode,
                ':toCurrencyCode' => $toCurrencyCode,
                ':rateDate' => $rateDate,
                ':rateType' => $rateType,
                ':rateValue' => $rateValue,
                ':rateSource' => $rateSource,
                ':notes' => $notes,
                ':isActive' => $isActive,
            ]);

            if ($existing === null) {
                $currencyRateId = (int) $this->pdo->lastInsertId();
            }

            $this->pdo->commit();
            return $currencyRateId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveImportRow(array $data): bool
    {
        $fromCurrencyCode = strtoupper(trim((string) ($data['FromCurrencyCode'] ?? '')));
        $toCurrencyCode = strtoupper(trim((string) ($data['ToCurrencyCode'] ?? '')));
        $rateDate = trim((string) ($data['RateDate'] ?? ''));
        $rateType = strtoupper(trim((string) ($data['RateType'] ?? 'SPOT')));

        if ($fromCurrencyCode === '' || $toCurrencyCode === '' || $rateDate === '') {
            throw new \RuntimeException('FromCurrencyCode, ToCurrencyCode, and RateDate are required.');
        }

        $existingId = (int) ($data['CurrencyRateID'] ?? 0);
        if ($existingId <= 0) {
            $existingId = $this->findExistingId($fromCurrencyCode, $toCurrencyCode, $rateDate, $rateType);
        }
        $existing = $existingId > 0 ? $this->getById($existingId) : null;

        $this->save([
            'CurrencyRateID' => $existingId,
            'FromCurrencyCode' => $fromCurrencyCode,
            'ToCurrencyCode' => $toCurrencyCode,
            'RateDate' => $rateDate,
            'RateType' => $rateType,
            'RateValue' => $data['RateValue'] ?? '',
            'RateSource' => trim((string) ($data['RateSource'] ?? '')),
            'Notes' => trim((string) ($data['Notes'] ?? '')),
            'IsActive' => array_key_exists('IsActive', $data) ? (int) $data['IsActive'] : 1,
        ]);

        return $existing === null;
    }

    public function buildTemplateWorkbook(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CurrencyRates');

        $headers = [
            'FromCurrencyCode',
            'ToCurrencyCode',
            'RateDate',
            'RateType',
            'RateValue',
            'RateSource',
            'Notes',
            'IsActive',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['USD', 'LSL', date('Y-m-d'), 'SPOT', '18.25000000', 'Central Bank', 'Example spot rate', 1],
            ['AUD', 'LSL', date('Y-m-d'), 'BUDGET', '12.40000000', 'Treasury Working Paper', 'Example planning rate', 1],
        ], null, 'A2');

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['Column', 'Required', 'Notes'],
            ['FromCurrencyCode', 'Yes', 'Three-letter source currency code.'],
            ['ToCurrencyCode', 'Yes', 'Three-letter target currency code. Must be different from FromCurrencyCode.'],
            ['RateDate', 'Yes', 'Effective date in YYYY-MM-DD format.'],
            ['RateType', 'No', 'Defaults to SPOT when blank. Used with pair and date as the import upsert key.'],
            ['RateValue', 'Yes', 'Positive numeric exchange rate value.'],
            ['RateSource', 'No', 'Optional source or authority for the rate.'],
            ['Notes', 'No', 'Optional implementation notes.'],
            ['IsActive', 'No', 'Use 1 or 0. Defaults to 1 when blank.'],
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
        $sheet->setTitle('CurrencyRates');

        $headers = [
            'CurrencyRateID',
            'FromCurrencyCode',
            'FromCurrencyName',
            'ToCurrencyCode',
            'ToCurrencyName',
            'RateDate',
            'RateType',
            'RateValue',
            'RateSource',
            'Notes',
            'IsActive',
            'CreatedAt',
            'UpdatedAt',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                (int) ($row['CurrencyRateID'] ?? 0),
                trim((string) ($row['FromCurrencyCode'] ?? '')),
                (string) ($row['FromCurrencyName'] ?? ''),
                trim((string) ($row['ToCurrencyCode'] ?? '')),
                (string) ($row['ToCurrencyName'] ?? ''),
                (string) ($row['RateDate'] ?? ''),
                (string) ($row['RateType'] ?? ''),
                (string) ($row['RateValue'] ?? ''),
                (string) ($row['RateSource'] ?? ''),
                (string) ($row['Notes'] ?? ''),
                (int) ($row['IsActive'] ?? 0),
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
            WHERE IsActive = 1
            ORDER BY SortOrder, CurrencyCode
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function currencyExists(string $currencyCode): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM dbo.tblCurrencies
            WHERE CurrencyCode = :currencyCode
              AND ISNULL(IsActive, 1) = 1
        ");
        $stmt->execute([':currencyCode' => $currencyCode]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function findExistingId(string $fromCurrencyCode, string $toCurrencyCode, string $rateDate, string $rateType): int
    {
        $stmt = $this->pdo->prepare("
            SELECT TOP 1 CurrencyRateID
            FROM dbo.tblCurrencyRates
            WHERE FromCurrencyCode = :fromCurrencyCode
              AND ToCurrencyCode = :toCurrencyCode
              AND RateDate = :rateDate
              AND RateType = :rateType
        ");
        $stmt->execute([
            ':fromCurrencyCode' => $fromCurrencyCode,
            ':toCurrencyCode' => $toCurrencyCode,
            ':rateDate' => $rateDate,
            ':rateType' => $rateType,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
