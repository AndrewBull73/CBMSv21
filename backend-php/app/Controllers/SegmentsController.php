<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SegmentsAdminModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../shared/csrf.php';

final class SegmentsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['SEGMENTS_EDIT', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
        'list' => ['auth' => true, 'permsAny' => ['SEGMENTS_VIEW', 'SEGMENTS_EDIT', 'BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
        'form' => ['auth' => true, 'permsAny' => ['SEGMENTS_VIEW', 'SEGMENTS_EDIT', 'BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
        'save' => ['auth' => true, 'permsAny' => ['SEGMENTS_EDIT', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
        'uploadProcess' => ['auth' => true, 'permsAny' => ['SEGMENTS_EDIT', 'SEGMENT_VALUES_IMPORT', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
        'downloadTemplate' => ['auth' => true, 'permsAny' => ['SEGMENTS_VIEW', 'SEGMENTS_EDIT', 'BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL']],
    ];

    public function list(): void
    {
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'dimension' => trim((string)($_GET['dimension'] ?? '')),
            'group' => trim((string)($_GET['group'] ?? '')),
        ];

        $model = new SegmentsAdminModel($this->db);

        $this->render('config/SegmentsList', [
            'title' => 'Segments',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'dimensions' => $model->listDimensions(),
            'groups' => $model->listGroups(),
            '_csrf' => csrf_token(),
        ]);
    }

    public function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $model = new SegmentsAdminModel($this->db);

        $this->render('config/SegmentsForm', [
            'title' => $id > 0 ? 'Edit Segment' : 'Create Segment',
            'record' => $id > 0 ? $model->getById($id) : null,
            'dimensions' => $model->listDimensions(),
            'groups' => $model->listGroups(),
            'parentSegments' => $model->listRows([]),
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
            header('Location: index.php?route=segments/list');
            return;
        }

        $payload = [
            'SegmentID' => (int)($_POST['SegmentID'] ?? 0),
            'SegmentCode' => trim((string)($_POST['SegmentCode'] ?? '')),
            'SegmentName' => trim((string)($_POST['SegmentName'] ?? '')),
            'MaxLength' => $_POST['MaxLength'] ?? null,
            'MinLength' => $_POST['MinLength'] ?? null,
            'StartPoint' => $_POST['StartPoint'] ?? null,
            'EndPoint' => $_POST['EndPoint'] ?? null,
            'Attribute1Name' => trim((string)($_POST['Attribute1Name'] ?? '')),
            'Attribute2Name' => trim((string)($_POST['Attribute2Name'] ?? '')),
            'Attribute3Name' => trim((string)($_POST['Attribute3Name'] ?? '')),
            'Attribute4Name' => trim((string)($_POST['Attribute4Name'] ?? '')),
            'Type' => trim((string)($_POST['Type'] ?? '')),
            'DefaultBusinessArea' => trim((string)($_POST['DefaultBusinessArea'] ?? '')),
            'CBMSDimension' => trim((string)($_POST['CBMSDimension'] ?? '')),
            'Editable' => trim((string)($_POST['Editable'] ?? '')),
            'Static' => trim((string)($_POST['Static'] ?? '')),
            'SegmentGroup' => trim((string)($_POST['SegmentGroup'] ?? '')),
            'UsedInFinancialAccount' => isset($_POST['UsedInFinancialAccount']) ? 1 : 0,
            'UsedInStrategicPlanning' => isset($_POST['UsedInStrategicPlanning']) ? 1 : 0,
            'UsedInOrgStructure' => isset($_POST['UsedInOrgStructure']) ? 1 : 0,
            'DisplayOrder' => $_POST['DisplayOrder'] ?? null,
            'Delimiter' => trim((string)($_POST['Delimiter'] ?? '')),
            'ParentSegmentNoDefault' => $_POST['ParentSegmentNoDefault'] ?? null,
            'ParentRequired' => isset($_POST['ParentRequired']) ? 1 : 0,
        ];

        $model = new SegmentsAdminModel($this->db);

        try {
            $existing = $model->getById((int)$payload['SegmentID']);
            $model->save($payload);
            $this->auditEvent($existing !== null ? 'UPDATE' : 'CREATE', 'Segment', (string) $payload['SegmentID'], [
                'SegmentCode' => $payload['SegmentCode'],
                'SegmentName' => $payload['SegmentName'],
                'CBMSDimension' => $payload['CBMSDimension'],
                'SegmentGroup' => $payload['SegmentGroup'],
                'UsedInFinancialAccount' => $payload['UsedInFinancialAccount'],
                'UsedInStrategicPlanning' => $payload['UsedInStrategicPlanning'],
                'UsedInOrgStructure' => $payload['UsedInOrgStructure'],
                'ParentRequired' => $payload['ParentRequired'],
            ]);
            $this->flashSuccess($existing !== null ? 'Segment updated.' : 'Segment created.');
        } catch (\Throwable $e) {
            $this->logHandledException('SegmentsController::save failed', $e, [
                'segmentId' => $payload['SegmentID'],
                'segmentCode' => $payload['SegmentCode'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            header('Location: index.php?route=segments/form' . ((int) $payload['SegmentID'] > 0 ? '&id=' . (int) $payload['SegmentID'] : ''));
            return;
        }

        $query = http_build_query([
            'route' => 'segments/list',
            'q' => trim((string)($_POST['return_q'] ?? '')),
            'dimension' => trim((string)($_POST['return_dimension'] ?? '')),
            'group' => trim((string)($_POST['return_group'] ?? '')),
        ]);
        header('Location: index.php?' . $query);
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
            header('Location: index.php?route=segments/list');
            return;
        }

        if (!isset($_FILES['uploadFile']) || (int)($_FILES['uploadFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('Select an Excel or CSV file to upload.');
            header('Location: index.php?route=segments/list');
            return;
        }

        $model = new SegmentsAdminModel($this->db);
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $spreadsheet = IOFactory::load((string)$_FILES['uploadFile']['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('Segments') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                throw new \RuntimeException('Empty worksheet.');
            }

            $headerMap = [];
            foreach (($rows[0] ?? []) as $index => $value) {
                $label = strtoupper(trim((string)$value));
                if ($label !== '') {
                    $headerMap[$label] = $index;
                }
            }

            $requiredHeaders = ['SEGMENTID', 'SEGMENTCODE', 'SEGMENTNAME'];
            foreach ($requiredHeaders as $requiredHeader) {
                if (!array_key_exists($requiredHeader, $headerMap)) {
                    throw new \RuntimeException('Missing required column: ' . $requiredHeader . '.');
                }
            }

            $valueFor = static function (array $map, array $sourceRow, string $header): string {
                $index = $map[strtoupper($header)] ?? null;
                return $index === null ? '' : trim((string)($sourceRow[$index] ?? ''));
            };

            for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[$rowIndex];
                if (!array_filter($row, static fn($value): bool => trim((string)$value) !== '')) {
                    $skipped++;
                    continue;
                }

                $segmentId = (int)$valueFor($headerMap, $row, 'SegmentID');
                $segmentCode = $valueFor($headerMap, $row, 'SegmentCode');
                $segmentName = $valueFor($headerMap, $row, 'SegmentName');

                if ($segmentId <= 0 || $segmentCode === '' || $segmentName === '') {
                    $errors[] = 'Row ' . ($rowIndex + 1) . ': SegmentID, SegmentCode, and SegmentName are required.';
                    continue;
                }

                try {
                    $wasCreated = $model->saveImportRow([
                        'SegmentID' => $segmentId,
                        'SegmentCode' => $segmentCode,
                        'SegmentName' => $segmentName,
                        'MaxLength' => $valueFor($headerMap, $row, 'MaxLength'),
                        'MinLength' => $valueFor($headerMap, $row, 'MinLength'),
                        'StartPoint' => $valueFor($headerMap, $row, 'StartPoint'),
                        'EndPoint' => $valueFor($headerMap, $row, 'EndPoint'),
                        'Attribute1Name' => $valueFor($headerMap, $row, 'Attribute1Name'),
                        'Attribute2Name' => $valueFor($headerMap, $row, 'Attribute2Name'),
                        'Attribute3Name' => $valueFor($headerMap, $row, 'Attribute3Name'),
                        'Attribute4Name' => $valueFor($headerMap, $row, 'Attribute4Name'),
                        'Type' => $valueFor($headerMap, $row, 'Type'),
                        'DefaultBusinessArea' => $valueFor($headerMap, $row, 'DefaultBusinessArea'),
                        'CBMSDimension' => $valueFor($headerMap, $row, 'CBMSDimension'),
                        'Editable' => $valueFor($headerMap, $row, 'Editable'),
                        'Static' => $valueFor($headerMap, $row, 'Static'),
                        'SegmentGroup' => $valueFor($headerMap, $row, 'SegmentGroup'),
                        'UsedInFinancialAccount' => $valueFor($headerMap, $row, 'UsedInFinancialAccount') === '' ? 0 : (int)$valueFor($headerMap, $row, 'UsedInFinancialAccount'),
                        'UsedInStrategicPlanning' => $valueFor($headerMap, $row, 'UsedInStrategicPlanning') === '' ? 0 : (int)$valueFor($headerMap, $row, 'UsedInStrategicPlanning'),
                        'UsedInOrgStructure' => $valueFor($headerMap, $row, 'UsedInOrgStructure') === '' ? 0 : (int)$valueFor($headerMap, $row, 'UsedInOrgStructure'),
                        'DisplayOrder' => $valueFor($headerMap, $row, 'DisplayOrder'),
                        'Delimiter' => $valueFor($headerMap, $row, 'Delimiter'),
                        'ParentSegmentNoDefault' => $valueFor($headerMap, $row, 'ParentSegmentNoDefault'),
                        'ParentRequired' => $valueFor($headerMap, $row, 'ParentRequired') === '' ? 0 : (int)$valueFor($headerMap, $row, 'ParentRequired'),
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

            $message = "Segments upload completed. Created: {$created}, updated: {$updated}, skipped blank rows: {$skipped}.";
            $this->auditEvent('UPLOAD', 'Segment', 'batch', [
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
            $this->logHandledException('SegmentsController::uploadProcess failed', $e, [
                'fileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);
            $this->flashError('Upload failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=segments/list');
    }

    public function downloadTemplate(): void
    {
        $model = new SegmentsAdminModel($this->db);
        $spreadsheet = $model->buildTemplateWorkbook();
        $filename = 'SegmentsUploadTemplate_' . date('Ymd_His') . '.xlsx';

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
