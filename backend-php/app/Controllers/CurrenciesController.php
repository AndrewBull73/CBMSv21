<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrenciesAdminModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../shared/csrf.php';

final class CurrenciesController extends BaseController
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
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new CurrenciesAdminModel($this->db);
        $ctx = $this->context();
        $this->render('config/CurrenciesList', [
            'title' => 'Currencies',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
            '_csrf' => csrf_token(),
        ]);
    }

    public function form(): void
    {
        $currencyCode = trim((string) ($_GET['code'] ?? ''));
        $model = new CurrenciesAdminModel($this->db);
        $ctx = $this->context();
        $record = $currencyCode !== '' ? $model->getByCode($currencyCode) : null;

        $this->render('config/CurrenciesForm', [
            'title' => $record !== null ? 'Edit Currency' : 'Create Currency',
            'record' => $record,
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
            header('Location: index.php?route=currencies/list');
            return;
        }

        $payload = [
            'CurrencyCode' => trim((string) ($_POST['CurrencyCode'] ?? '')),
            'CurrencyName' => trim((string) ($_POST['CurrencyName'] ?? '')),
            'CurrencySymbol' => trim((string) ($_POST['CurrencySymbol'] ?? '')),
            'IsoNumericCode' => trim((string) ($_POST['IsoNumericCode'] ?? '')),
            'DecimalPlaces' => (int) ($_POST['DecimalPlaces'] ?? 2),
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 100),
            'IsSystemDefault' => isset($_POST['IsSystemDefault']) ? 1 : 0,
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
        ];

        $model = new CurrenciesAdminModel($this->db);
        try {
            $currencyCode = $model->save($payload);
            $this->auditEvent(!empty($_POST['_editing']) ? 'UPDATE' : 'CREATE', 'Currency', $currencyCode, [
                'CurrencyName' => $payload['CurrencyName'],
                'CurrencySymbol' => $payload['CurrencySymbol'],
                'IsoNumericCode' => $payload['IsoNumericCode'],
                'DecimalPlaces' => $payload['DecimalPlaces'],
                'SortOrder' => $payload['SortOrder'],
                'IsSystemDefault' => $payload['IsSystemDefault'],
                'IsActive' => $payload['IsActive'],
            ]);
            $this->flashSuccess(!empty($_POST['_editing']) ? 'Currency updated.' : 'Currency saved.');
            header('Location: index.php?route=currencies/list');
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('CurrenciesController::save failed', $e, [
                'currencyCode' => $payload['CurrencyCode'],
                'currencyName' => $payload['CurrencyName'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'currencies/form',
                'code' => strtoupper(trim((string) ($payload['CurrencyCode'] ?? ''))),
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
            header('Location: index.php?route=currencies/list');
            return;
        }

        if (!isset($_FILES['uploadFile']) || (int) ($_FILES['uploadFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('Select an Excel or CSV file to upload.');
            header('Location: index.php?route=currencies/list');
            return;
        }

        $model = new CurrenciesAdminModel($this->db);
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $spreadsheet = IOFactory::load((string) $_FILES['uploadFile']['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('Currencies') ?? $spreadsheet->getActiveSheet();
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

            foreach (['CURRENCYCODE', 'CURRENCYNAME'] as $requiredHeader) {
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

                $currencyCode = $valueFor($headerMap, $row, 'CurrencyCode');
                $currencyName = $valueFor($headerMap, $row, 'CurrencyName');
                if ($currencyCode === '' || $currencyName === '') {
                    $errors[] = 'Row ' . ($rowIndex + 1) . ': CurrencyCode and CurrencyName are required.';
                    continue;
                }

                try {
                    $wasCreated = $model->saveImportRow([
                        'CurrencyCode' => $currencyCode,
                        'CurrencyName' => $currencyName,
                        'CurrencySymbol' => $valueFor($headerMap, $row, 'CurrencySymbol'),
                        'IsoNumericCode' => $valueFor($headerMap, $row, 'IsoNumericCode'),
                        'DecimalPlaces' => $valueFor($headerMap, $row, 'DecimalPlaces') === '' ? 2 : (int) $valueFor($headerMap, $row, 'DecimalPlaces'),
                        'IsSystemDefault' => $valueFor($headerMap, $row, 'IsSystemDefault') === '' ? 0 : (int) $valueFor($headerMap, $row, 'IsSystemDefault'),
                        'IsActive' => $valueFor($headerMap, $row, 'IsActive') === '' ? 1 : (int) $valueFor($headerMap, $row, 'IsActive'),
                        'SortOrder' => $valueFor($headerMap, $row, 'SortOrder') === '' ? 100 : (int) $valueFor($headerMap, $row, 'SortOrder'),
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

            $message = "Currency upload completed. Created: {$created}, updated: {$updated}, skipped blank rows: {$skipped}.";
            $this->auditEvent('UPLOAD', 'Currency', 'batch', [
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
            $this->logHandledException('CurrenciesController::uploadProcess failed', $e, [
                'fileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);
            $this->flashError('Upload failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=currencies/list');
    }

    public function downloadTemplate(): void
    {
        $model = new CurrenciesAdminModel($this->db);
        $spreadsheet = $model->buildTemplateWorkbook();
        $filename = 'CurrenciesUploadTemplate_' . date('Ymd_His') . '.xlsx';

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
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new CurrenciesAdminModel($this->db);
        $spreadsheet = $model->buildExportWorkbook($filters);
        $filename = 'Currencies_' . date('Ymd_His') . '.xlsx';

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
