<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrencyRatesAdminModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../shared/csrf.php';

final class CurrencyRatesController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'uploadProcess' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'downloadTemplate' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'exportExcel' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'from_currency_code' => trim((string) ($_GET['from_currency_code'] ?? '')),
            'to_currency_code' => trim((string) ($_GET['to_currency_code'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new CurrencyRatesAdminModel($this->db);
        $ctx = $this->context();
        $this->render('config/CurrencyRatesList', [
            'title' => 'Currency Rates',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'currencies' => $model->listCurrencyOptions(),
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
            '_csrf' => csrf_token(),
        ]);
    }

    public function form(): void
    {
        $currencyRateId = (int) ($_GET['id'] ?? 0);
        $model = new CurrencyRatesAdminModel($this->db);
        $ctx = $this->context();
        $record = $currencyRateId > 0 ? $model->getById($currencyRateId) : null;

        if ($record === null) {
            $record = [
                'RateDate' => date('Y-m-d'),
                'RateType' => 'SPOT',
                'IsActive' => 1,
            ];
        }

        $this->render('config/CurrencyRatesForm', [
            'title' => $currencyRateId > 0 ? 'Edit Currency Rate' : 'Create Currency Rate',
            'record' => $record,
            'currencies' => $model->listCurrencyOptions(),
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
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
            header('Location: index.php?route=currency-rates/list');
            return;
        }

        $payload = [
            'CurrencyRateID' => (int) ($_POST['CurrencyRateID'] ?? 0),
            'FromCurrencyCode' => trim((string) ($_POST['FromCurrencyCode'] ?? '')),
            'ToCurrencyCode' => trim((string) ($_POST['ToCurrencyCode'] ?? '')),
            'RateDate' => trim((string) ($_POST['RateDate'] ?? '')),
            'RateType' => trim((string) ($_POST['RateType'] ?? '')),
            'RateValue' => trim((string) ($_POST['RateValue'] ?? '')),
            'RateSource' => trim((string) ($_POST['RateSource'] ?? '')),
            'Notes' => trim((string) ($_POST['Notes'] ?? '')),
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
        ];

        $model = new CurrencyRatesAdminModel($this->db);
        try {
            $currencyRateId = $model->save($payload);
            $this->auditEvent(!empty($_POST['_editing']) ? 'UPDATE' : 'CREATE', 'CurrencyRate', (string) $currencyRateId, [
                'FromCurrencyCode' => $payload['FromCurrencyCode'],
                'ToCurrencyCode' => $payload['ToCurrencyCode'],
                'RateDate' => $payload['RateDate'],
                'RateType' => $payload['RateType'],
                'RateValue' => $payload['RateValue'],
                'RateSource' => $payload['RateSource'],
                'IsActive' => $payload['IsActive'],
            ]);
            $this->flashSuccess(!empty($_POST['_editing']) ? 'Currency rate updated.' : 'Currency rate saved.');
            header('Location: index.php?route=currency-rates/list');
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('CurrencyRatesController::save failed', $e, [
                'currencyRateId' => $payload['CurrencyRateID'],
                'fromCurrencyCode' => $payload['FromCurrencyCode'],
                'toCurrencyCode' => $payload['ToCurrencyCode'],
                'rateDate' => $payload['RateDate'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'currency-rates/form',
                'id' => (int) ($payload['CurrencyRateID'] ?? 0),
            ]);
            header('Location: index.php?' . $query);
            return;
        }
    }

    public function uploadProcess(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=currency-rates/list');
            return;
        }

        if (!isset($_FILES['uploadFile']) || (int) ($_FILES['uploadFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('Select an Excel or CSV file to upload.');
            header('Location: index.php?route=currency-rates/list');
            return;
        }

        $model = new CurrencyRatesAdminModel($this->db);
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $spreadsheet = IOFactory::load((string) $_FILES['uploadFile']['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('CurrencyRates') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            if ($rows === []) {
                throw new \RuntimeException('Empty worksheet.');
            }

            $headerMap = [];
            foreach (($rows[0] ?? []) as $index => $value) {
                $label = strtoupper(trim((string) $value));
                if ($label !== '') {
                    $headerMap[$label] = $index;
                }
            }

            foreach (['FROMCURRENCYCODE', 'TOCURRENCYCODE', 'RATEDATE', 'RATEVALUE'] as $requiredHeader) {
                if (!array_key_exists($requiredHeader, $headerMap)) {
                    throw new \RuntimeException('Missing required column: ' . $requiredHeader . '.');
                }
            }

            $valueFor = static function (array $map, array $sourceRow, string $header): string {
                $index = $map[strtoupper($header)] ?? null;
                return $index === null ? '' : trim((string) ($sourceRow[$index] ?? ''));
            };

            for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[$rowIndex];
                if (!array_filter($row, static fn($value): bool => trim((string) $value) !== '')) {
                    $skipped++;
                    continue;
                }

                $fromCurrencyCode = $valueFor($headerMap, $row, 'FromCurrencyCode');
                $toCurrencyCode = $valueFor($headerMap, $row, 'ToCurrencyCode');
                $rateDate = $valueFor($headerMap, $row, 'RateDate');
                $rateValue = $valueFor($headerMap, $row, 'RateValue');
                if ($fromCurrencyCode === '' || $toCurrencyCode === '' || $rateDate === '' || $rateValue === '') {
                    $errors[] = 'Row ' . ($rowIndex + 1) . ': FromCurrencyCode, ToCurrencyCode, RateDate, and RateValue are required.';
                    continue;
                }

                try {
                    $wasCreated = $model->saveImportRow([
                        'CurrencyRateID' => $valueFor($headerMap, $row, 'CurrencyRateID') === '' ? 0 : (int) $valueFor($headerMap, $row, 'CurrencyRateID'),
                        'FromCurrencyCode' => $fromCurrencyCode,
                        'ToCurrencyCode' => $toCurrencyCode,
                        'RateDate' => $rateDate,
                        'RateType' => $valueFor($headerMap, $row, 'RateType') !== '' ? $valueFor($headerMap, $row, 'RateType') : 'SPOT',
                        'RateValue' => $rateValue,
                        'RateSource' => $valueFor($headerMap, $row, 'RateSource'),
                        'Notes' => $valueFor($headerMap, $row, 'Notes'),
                        'IsActive' => $valueFor($headerMap, $row, 'IsActive') === '' ? 1 : (int) $valueFor($headerMap, $row, 'IsActive'),
                    ]);

                    if ($wasCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Row ' . ($rowIndex + 1) . ': ' . $e->getMessage();
                }
            }

            $message = "Currency rate upload completed. Created: {$created}, updated: {$updated}, skipped blank rows: {$skipped}.";
            $this->auditEvent('UPLOAD', 'CurrencyRate', 'batch', [
                'Created' => $created,
                'Updated' => $updated,
                'Skipped' => $skipped,
                'ErrorCount' => count($errors),
                'FileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);
            if ($errors !== []) {
                $previewErrors = array_slice($errors, 0, 10);
                if (count($errors) > 10) {
                    $previewErrors[] = 'Additional errors: ' . (count($errors) - 10) . '.';
                }
                $this->flashError($message . ' ' . implode(' | ', $previewErrors));
            } else {
                $this->flashSuccess($message);
            }
        } catch (\Throwable $e) {
            $this->logHandledException('CurrencyRatesController::uploadProcess failed', $e, [
                'fileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);
            $this->flashError('Upload failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=currency-rates/list');
    }

    public function downloadTemplate(): void
    {
        $model = new CurrencyRatesAdminModel($this->db);
        $spreadsheet = $model->buildTemplateWorkbook();
        $filename = 'CurrencyRatesUploadTemplate_' . date('Ymd_His') . '.xlsx';

        if (ob_get_length()) {
            @ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportExcel(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'from_currency_code' => trim((string) ($_GET['from_currency_code'] ?? '')),
            'to_currency_code' => trim((string) ($_GET['to_currency_code'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new CurrencyRatesAdminModel($this->db);
        $spreadsheet = $model->buildExportWorkbook($filters);
        $filename = 'CurrencyRates_' . date('Ymd_His') . '.xlsx';

        if (ob_get_length()) {
            @ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function buildContextLabels(int $fiscalYearId, int $versionId): array
    {
        $labels = [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        if (!$this->db instanceof \PDO) {
            return $labels;
        }

        if ($fiscalYearId > 0) {
            $fyStmt = $this->db->prepare('SELECT TOP 1 YearLabel FROM dbo.tblFiscalYears WHERE FiscalYearID = :fiscalYearId');
            $fyStmt->execute([':fiscalYearId' => $fiscalYearId]);
            $labels['YearLabel'] = (string) ($fyStmt->fetchColumn() ?: '');
        }

        if ($fiscalYearId > 0 && $versionId > 0) {
            $verStmt = $this->db->prepare('SELECT TOP 1 VersionLabel FROM dbo.tblVersions WHERE FiscalYearID = :fiscalYearId AND VersionID = :versionId');
            $verStmt->execute([
                ':fiscalYearId' => $fiscalYearId,
                ':versionId' => $versionId,
            ]);
            $labels['VersionLabel'] = (string) ($verStmt->fetchColumn() ?: '');
        }

        return $labels;
    }
}
