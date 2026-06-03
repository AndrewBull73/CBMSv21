<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SegmentValuesAdminModel;
use App\Shared\SessionHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../shared/csrf.php';

final class SegmentValuesController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'upload' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'archive' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'uploadProcess' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'downloadTemplate' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function list(): void
    {
        $ctxFy = (int)SessionHelper::get('FiscalYearID', 0);

        $filters = [
            'fy' => trim((string)($_GET['fy'] ?? ($ctxFy > 0 ? (string)$ctxFy : ''))),
            'data_object_code' => trim((string)($_GET['data_object_code'] ?? '')),
            'segment_no' => trim((string)($_GET['segment_no'] ?? '')),
            'q' => trim((string)($_GET['q'] ?? '')),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $model = new SegmentValuesAdminModel($this->db);

        $rows = $model->listRows($filters);

        $this->render('config/SegmentValuesList', [
            'title' => 'Segment Values',
            'rows' => $rows,
            'filters' => $filters,
            'fiscalYears' => $model->listFiscalYears(),
            'segments' => $model->listSegments(),
            'ctxFy' => $ctxFy,
        ]);
    }

    public function form(): void
    {
        $ctxFy = (int) SessionHelper::get('FiscalYearID', 0);
        $id = (int) ($_GET['id'] ?? 0);

        $model = new SegmentValuesAdminModel($this->db);
        $record = $id > 0 ? $model->getById($id) : null;
        $formFiscalYearId = (int) ($record['FiscalYearID'] ?? $ctxFy);

        $this->render('config/SegmentValuesForm', [
            'title' => $id > 0 ? 'Edit Segment Value' : 'Create Segment Value',
            'record' => $record,
            'ctxFy' => $ctxFy,
            'fiscalYears' => $model->listFiscalYears(),
            'segments' => $model->listSegments(),
            'dataObjectCodes' => $model->listDataObjectCodes($formFiscalYearId),
        ]);
    }

    public function upload(): void
    {
        $ctxFy = (int) SessionHelper::get('FiscalYearID', 0);
        $model = new SegmentValuesAdminModel($this->db);

        $this->render('config/SegmentValuesUpload', [
            'title' => 'Upload Segment Values',
            'ctxFy' => $ctxFy,
            'fiscalYears' => $model->listFiscalYears(),
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
            header('Location: index.php?route=segment-values/list');
            return;
        }

        $id = (int)($_POST['SegmentValueID'] ?? 0);
        $fy = (int)($_POST['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string)($_POST['DataObjectCode'] ?? ''));
        $segmentNo = (int)($_POST['SegmentNo'] ?? 0);
        $segmentCode = trim((string)($_POST['SegmentCode'] ?? ''));

        if ($fy <= 0 || $dataObjectCode === '' || $segmentNo <= 0 || $segmentCode === '') {
            $this->flashError('Fiscal Year, Data Object Code, Segment No, and Segment Code are required.');
            header('Location: index.php?route=segment-values/form' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }

        $payload = [
            'SegmentValueID' => $id,
            'FiscalYearID' => $fy,
            'DataObjectCode' => $dataObjectCode,
            'SegmentNo' => $segmentNo,
            'SegmentCode' => $segmentCode,
            'SegmentName' => trim((string)($_POST['SegmentName'] ?? '')),
            'SegmentExternalID' => trim((string)($_POST['SegmentExternalID'] ?? '')),
            'ParentSegmentValueID' => (int)($_POST['ParentSegmentValueID'] ?? 0),
            'SortOrder' => (int)($_POST['SortOrder'] ?? 0),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UpdatedBy' => (int)SessionHelper::get('auth.user_id', 0),
            'ParentSegmentNo' => (int)($_POST['ParentSegmentNo'] ?? 0),
            'ParentSegmentCode' => trim((string)($_POST['ParentSegmentCode'] ?? '')),
        ];

        $model = new SegmentValuesAdminModel($this->db);

        try {
            $model->save($payload);
            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'SegmentValue', $id > 0 ? (string) $id : ($fy . ':' . $dataObjectCode . ':' . $segmentNo . ':' . $segmentCode), [
                'FiscalYearID' => $fy,
                'DataObjectCode' => $dataObjectCode,
                'SegmentNo' => $segmentNo,
                'SegmentCode' => $segmentCode,
                'SegmentName' => $payload['SegmentName'],
                'ActiveFlag' => $payload['ActiveFlag'],
            ]);
            $this->flashSuccess($id > 0 ? 'Segment value updated.' : 'Segment value created.');
        } catch (\Throwable $e) {
            $this->logHandledException('SegmentValuesController::save failed', $e, [
                'segmentValueId' => $id,
                'fiscalYearId' => $fy,
                'dataObjectCode' => $dataObjectCode,
                'segmentNo' => $segmentNo,
                'segmentCode' => $segmentCode,
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            header('Location: index.php?route=segment-values/form' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }

        $query = http_build_query([
            'route' => 'segment-values/list',
            'fy' => (string)$fy,
            'data_object_code' => $dataObjectCode,
            'segment_no' => (string)$segmentNo,
            'active' => isset($_POST['ActiveFlag']) ? '1' : '0',
        ]);
        header('Location: index.php?' . $query);
    }

    public function archive(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=segment-values/list');
            return;
        }

        $id = (int)($_POST['SegmentValueID'] ?? 0);
        if ($id <= 0) {
            $this->flashError('Missing segment value id.');
            header('Location: index.php?route=segment-values/list');
            return;
        }

        $model = new SegmentValuesAdminModel($this->db);

        try {
            $model->archive($id, (int)SessionHelper::get('auth.user_id', 0));
            $this->auditEvent('ARCHIVE', 'SegmentValue', (string) $id, []);
            $this->flashSuccess('Segment value archived.');
        } catch (\Throwable $e) {
            $this->logHandledException('SegmentValuesController::archive failed', $e, [
                'segmentValueId' => $id,
            ]);
            $this->flashError('Archive failed: ' . $e->getMessage());
        }

        $returnTo = trim((string)($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = 'index.php?route=segment-values/list';
        }
        header('Location: ' . $returnTo);
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
            header('Location: index.php?route=segment-values/list');
            return;
        }

        if (!isset($_FILES['uploadFile']) || (int)($_FILES['uploadFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('Select an Excel or CSV file to upload.');
            header('Location: index.php?route=segment-values/list');
            return;
        }

        $overrideFiscalYearId = (int)($_POST['UploadFiscalYearID'] ?? 0);
        $updatedBy = (int)SessionHelper::get('auth.user_id', 0);
        $model = new SegmentValuesAdminModel($this->db);
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $spreadsheet = IOFactory::load((string)$_FILES['uploadFile']['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('SegmentValues') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                throw new \RuntimeException('Empty worksheet.');
            }

            $expected = [
                'FiscalYearID',
                'DataObjectCode',
                'SegmentNo',
                'SegmentCode',
                'SegmentName',
                'SegmentExternalID',
                'ParentSegmentValueID',
                'ParentSegmentNo',
                'ParentSegmentCode',
                'SortOrder',
                'ActiveFlag',
            ];

            $headerMap = [];
            foreach (($rows[0] ?? []) as $index => $value) {
                $label = strtoupper(trim((string) $value));
                if ($label !== '') {
                    $headerMap[$label] = $index;
                }
            }

            foreach ($expected as $column) {
                if (!array_key_exists(strtoupper($column), $headerMap)) {
                    throw new \RuntimeException('Missing required column: ' . $column . '.');
                }
            }

            for ($r = 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                if (!array_filter($row, static fn($v): bool => trim((string)$v) !== '')) {
                    $skipped++;
                    continue;
                }

                $assoc = [];
                foreach ($expected as $label) {
                    $index = $headerMap[strtoupper($label)] ?? null;
                    $assoc[$label] = $index === null ? '' : trim((string) ($row[$index] ?? ''));
                }

                $fiscalYearId = $overrideFiscalYearId > 0 ? $overrideFiscalYearId : (int)$assoc['FiscalYearID'];
                $dataObjectCode = $assoc['DataObjectCode'];
                $segmentNo = (int)$assoc['SegmentNo'];
                $segmentCode = $assoc['SegmentCode'];

                if ($fiscalYearId <= 0 || $dataObjectCode === '' || $segmentNo <= 0 || $segmentCode === '') {
                    $errors[] = 'Row ' . ($r + 1) . ': FiscalYearID, DataObjectCode, SegmentNo, and SegmentCode are required.';
                    continue;
                }

                try {
                    $wasCreated = $model->saveImportRow([
                        'FiscalYearID' => $fiscalYearId,
                        'DataObjectCode' => $dataObjectCode,
                        'SegmentNo' => $segmentNo,
                        'SegmentCode' => $segmentCode,
                        'SegmentName' => $assoc['SegmentName'],
                        'SegmentExternalID' => $assoc['SegmentExternalID'],
                        'ParentSegmentValueID' => (int)$assoc['ParentSegmentValueID'],
                        'ParentSegmentNo' => (int)$assoc['ParentSegmentNo'],
                        'ParentSegmentCode' => $assoc['ParentSegmentCode'],
                        'SortOrder' => $assoc['SortOrder'] === '' ? 0 : (int)$assoc['SortOrder'],
                        'ActiveFlag' => $assoc['ActiveFlag'] === '' ? 1 : (int)$assoc['ActiveFlag'],
                        'UpdatedBy' => $updatedBy,
                    ]);
                    if ($wasCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Row ' . ($r + 1) . ': ' . $e->getMessage();
                }
            }

            $message = "Segment values upload completed. Created: {$created}, updated: {$updated}, skipped blank rows: {$skipped}.";
            $this->auditEvent('UPLOAD', 'SegmentValue', 'batch', [
                'Created' => $created,
                'Updated' => $updated,
                'Skipped' => $skipped,
                'ErrorCount' => count($errors),
                'UploadFiscalYearID' => $overrideFiscalYearId,
                'FileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);
            if ($errors) {
                $previewErrors = array_slice($errors, 0, 10);
                if (count($errors) > 10) {
                    $previewErrors[] = 'Additional errors: ' . (count($errors) - 10) . '.';
                }
                $message .= '<br>' . implode('<br>', $previewErrors);
                $this->flashError($message);
            } else {
                $this->flashSuccess($message);
            }
        } catch (\Throwable $e) {
            $this->logHandledException('SegmentValuesController::uploadProcess failed', $e, [
                'fileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
                'uploadFiscalYearId' => $overrideFiscalYearId,
            ]);
            $this->flashError('Upload failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=segment-values/list');
    }

    public function downloadTemplate(): void
    {
        $model = new SegmentValuesAdminModel($this->db);
        $spreadsheet = $model->buildTemplateWorkbook();
        $filename = 'SegmentValuesUploadTemplate_' . date('Ymd_His') . '.xlsx';

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
}
