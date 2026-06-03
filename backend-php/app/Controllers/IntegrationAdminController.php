<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\IntegrationAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class IntegrationAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['ADMIN_ALL', 'SYSADMIN']],
    ];

    private IntegrationAdminModel $model;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/IntegrationAdminModel.php';

        $this->model = new IntegrationAdminModel($conn);
    }

    public function systems(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $foundationInstalled = $this->model->supportsIntegrationFoundation();
        $rows = $foundationInstalled ? $this->model->listSystems($filters) : [];

        $this->render('integrations/SystemsList', [
            'title' => 'Integration Systems',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
        ]);
    }

    public function systemForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $this->model->getSystem($id) : null;

        $this->render('integrations/SystemForm', [
            'title' => $id > 0 ? 'Edit Integration System' : 'Create Integration System',
            'record' => $record,
            'foundationInstalled' => $this->model->supportsIntegrationFoundation(),
            'installScriptPath' => $this->installScriptPath(),
            'authTypeOptions' => $this->authTypeOptions(),
            'environmentOptions' => $this->environmentOptions(),
        ]);
    }

    public function saveSystem(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/systems');
            return;
        }
        if (!$this->model->supportsIntegrationFoundation()) {
            $this->flashError('Install the integration foundation schema before saving systems.');
            header('Location: index.php?route=integration-admin/systems');
            return;
        }

        $id = (int) ($_POST['IntegrationSystemID'] ?? 0);
        $data = [
            'SystemCode' => trim((string) ($_POST['SystemCode'] ?? '')),
            'SystemName' => trim((string) ($_POST['SystemName'] ?? '')),
            'BaseUrl' => trim((string) ($_POST['BaseUrl'] ?? '')),
            'AuthType' => trim((string) ($_POST['AuthType'] ?? '')),
            'CredentialReference' => trim((string) ($_POST['CredentialReference'] ?? '')),
            'DefaultHeadersJson' => trim((string) ($_POST['DefaultHeadersJson'] ?? '')),
            'EnvironmentCode' => trim((string) ($_POST['EnvironmentCode'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'Notes' => trim((string) ($_POST['Notes'] ?? '')),
        ];

        try {
            $this->validateSystemPayload($data);
            $savedId = $this->model->saveSystem($data, (int) SessionHelper::get('auth.user_id', 0), $id > 0 ? $id : null);
            $this->flashSuccess('Integration system saved.');
            header('Location: index.php?route=integration-admin/system-form&id=' . $savedId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Integration system save failed: ' . $e->getMessage());
            $target = 'index.php?route=integration-admin/system-form';
            if ($id > 0) {
                $target .= '&id=' . $id;
            }
            header('Location: ' . $target);
            return;
        }
    }

    public function interfaces(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'system_id' => trim((string) ($_GET['system_id'] ?? '')),
            'direction' => trim((string) ($_GET['direction'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $foundationInstalled = $this->model->supportsIntegrationFoundation();
        $rows = $foundationInstalled ? $this->model->listInterfaces($filters) : [];

        $this->render('integrations/InterfacesList', [
            'title' => 'Integration Interfaces',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
            'systemOptions' => $foundationInstalled ? $this->model->listSystemOptions(false) : [],
            'directionOptions' => $this->directionOptions(),
        ]);
    }

    public function interfaceForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $this->model->getInterface($id) : null;
        $foundationInstalled = $this->model->supportsIntegrationFoundation();

        $this->render('integrations/InterfaceForm', [
            'title' => $id > 0 ? 'Edit Integration Interface' : 'Create Integration Interface',
            'record' => $record,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
            'systemOptions' => $foundationInstalled ? $this->model->listSystemOptions(false) : [],
            'directionOptions' => $this->directionOptions(),
            'triggerModeOptions' => $this->triggerModeOptions(),
            'httpMethodOptions' => $this->httpMethodOptions(),
            'payloadFormatOptions' => $this->payloadFormatOptions(),
            'approvalStageOptions' => $this->approvalStageOptions(),
            'readinessStatusOptions' => $this->readinessStatusOptions(),
            'outputProfileOptions' => $record !== null ? $this->availableOutputProfiles($record, $this->decodeMappingConfig((string) ($record['MappingConfigJson'] ?? ''))) : [],
        ]);
    }

    public function saveInterface(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }
        if (!$this->model->supportsIntegrationFoundation()) {
            $this->flashError('Install the integration foundation schema before saving interfaces.');
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $id = (int) ($_POST['IntegrationInterfaceID'] ?? 0);
        $data = [
            'InterfaceCode' => trim((string) ($_POST['InterfaceCode'] ?? '')),
            'InterfaceName' => trim((string) ($_POST['InterfaceName'] ?? '')),
            'IntegrationSystemID' => (int) ($_POST['IntegrationSystemID'] ?? 0),
            'DirectionCode' => trim((string) ($_POST['DirectionCode'] ?? '')),
            'ModuleCode' => trim((string) ($_POST['ModuleCode'] ?? '')),
            'EntityCode' => trim((string) ($_POST['EntityCode'] ?? '')),
            'TriggerMode' => trim((string) ($_POST['TriggerMode'] ?? 'manual')),
            'ScheduleExpression' => trim((string) ($_POST['ScheduleExpression'] ?? '')),
            'EndpointPath' => trim((string) ($_POST['EndpointPath'] ?? '')),
            'HttpMethod' => trim((string) ($_POST['HttpMethod'] ?? '')),
            'PayloadFormat' => trim((string) ($_POST['PayloadFormat'] ?? '')),
            'ContextRequiredFlag' => isset($_POST['ContextRequiredFlag']) ? 1 : 0,
            'FiscalYearRequiredFlag' => isset($_POST['FiscalYearRequiredFlag']) ? 1 : 0,
            'VersionRequiredFlag' => isset($_POST['VersionRequiredFlag']) ? 1 : 0,
            'DataScopeRequiredFlag' => isset($_POST['DataScopeRequiredFlag']) ? 1 : 0,
            'BatchSize' => trim((string) ($_POST['BatchSize'] ?? '')),
            'TimeoutSeconds' => trim((string) ($_POST['TimeoutSeconds'] ?? '')),
            'MappingConfigJson' => trim((string) ($_POST['MappingConfigJson'] ?? '')),
            'OutputProfilesJson' => trim((string) ($_POST['OutputProfilesJson'] ?? '')),
            'DefaultOutputProfileCode' => trim((string) ($_POST['DefaultOutputProfileCode'] ?? '')),
            'BusinessOwner' => trim((string) ($_POST['BusinessOwner'] ?? '')),
            'SourceOwner' => trim((string) ($_POST['SourceOwner'] ?? '')),
            'ApprovalStage' => trim((string) ($_POST['ApprovalStage'] ?? '')),
            'ReadinessStatus' => trim((string) ($_POST['ReadinessStatus'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'Notes' => trim((string) ($_POST['Notes'] ?? '')),
        ];

        try {
            $this->validateInterfacePayload($data);
            $savedId = $this->model->saveInterface($data, (int) SessionHelper::get('auth.user_id', 0), $id > 0 ? $id : null);
            $this->flashSuccess('Integration interface saved.');
            header('Location: index.php?route=integration-admin/interface-form&id=' . $savedId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Integration interface save failed: ' . $e->getMessage());
            $target = 'index.php?route=integration-admin/interface-form';
            if ($id > 0) {
                $target .= '&id=' . $id;
            }
            header('Location: ' . $target);
            return;
        }
    }

    public function runs(): void
    {
        $filters = [
            'system_id' => trim((string) ($_GET['system_id'] ?? '')),
            'interface_id' => trim((string) ($_GET['interface_id'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $foundationInstalled = $this->model->supportsIntegrationFoundation();
        $rows = $foundationInstalled ? $this->model->listRuns($filters) : [];

        $this->render('integrations/RunsList', [
            'title' => 'Integration Run History',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
            'systemOptions' => $foundationInstalled ? $this->model->listSystemOptions(false) : [],
            'interfaceOptions' => $foundationInstalled ? $this->model->listInterfaceOptions(false) : [],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function runDetail(): void
    {
        $runId = (int) ($_GET['id'] ?? 0);
        $run = $this->model->getRunDetail($runId);
        if ($run === null) {
            $this->flashError('Integration run not found.');
            header('Location: index.php?route=integration-admin/runs');
            exit;
        }

        $requestPayload = $this->decodeMappingConfig((string) ($run['RequestPayloadJson'] ?? ''));
        $responsePayload = $this->decodeMappingConfig((string) ($run['ResponsePayloadJson'] ?? ''));

        $this->render('integrations/RunDetail', [
            'title' => 'Integration Run Detail',
            'run' => $run,
            'requestPayload' => $requestPayload,
            'responsePayload' => $responsePayload,
            'requestPayloadPretty' => $requestPayload !== [] ? $this->jsonEncode($requestPayload, true) : '',
            'responsePayloadPretty' => $responsePayload !== [] ? $this->jsonEncode($responsePayload, true) : '',
        ]);
    }

    public function downloadRunSummary(): void
    {
        $runId = (int) ($_GET['id'] ?? 0);
        $run = $this->model->getRunDetail($runId);
        if ($run === null) {
            $this->flashError('Integration run not found.');
            header('Location: index.php?route=integration-admin/runs');
            return;
        }

        $summary = $this->buildRunSummaryText($run);
        $filename = 'integration_run_' . $runId . '_summary.txt';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($summary));
        echo $summary;
        exit;
    }

    public function testExport(): void
    {
        $interfaceId = (int) ($_GET['id'] ?? 0);
        $interface = $this->model->getInterfaceDefinition($interfaceId);
        if ($interface === null) {
            $this->flashError('Integration interface not found.');
            header('Location: index.php?route=integration-admin/interfaces');
            exit;
        }

        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $defaults = $this->testExportDefaults($interface);

        $this->render('integrations/TestExport', [
            'title' => 'Test Export Runner',
            'interface' => $interface,
            'mappingConfig' => $mappingConfig,
            'formData' => $defaults,
            'previewResult' => null,
            'recentRuns' => $this->model->listRecentRunsForInterface($interfaceId, 10),
            'outputProfileOptions' => $this->availableOutputProfiles($interface, $mappingConfig),
        ]);
    }

    public function runTestExport(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $interfaceId = (int) ($_POST['IntegrationInterfaceID'] ?? 0);
        $interface = $this->model->getInterfaceDefinition($interfaceId);
        if ($interface === null) {
            $this->flashError('Integration interface not found.');
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $formData = [
            'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
            'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
            'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'PreviewLimit' => trim((string) ($_POST['PreviewLimit'] ?? '')),
            'OutputProfileCode' => trim((string) ($_POST['OutputProfileCode'] ?? '')),
        ];

        $previewResult = null;
        $runId = 0;

        try {
            $preview = $this->prepareTestExportPreview($interface, $mappingConfig, $formData);

            $runId = $this->model->startRun([
                'IntegrationInterfaceID' => $interfaceId,
                'RunStatusCode' => 'running',
                'TriggerSourceCode' => 'manual_preview',
                'TriggeredByUserID' => (int) SessionHelper::get('auth.user_id', 0),
                'FiscalYearID' => $formData['FiscalYearID'] !== '' ? (int) $formData['FiscalYearID'] : null,
                'VersionID' => $formData['VersionID'] !== '' ? (int) $formData['VersionID'] : null,
                'DataObjectCode' => $formData['DataObjectCode'] !== '' ? $formData['DataObjectCode'] : null,
                'SummaryText' => 'Manual test export preview started.',
                'RequestPayloadJson' => $this->jsonEncode([
                    'mode' => 'manual_test_export_preview',
                    'context' => $preview['context'],
                    'resolved_scope_codes' => $preview['resolved_scope_codes'],
                    'preview_limit' => $preview['preview_limit'],
                    'requested_by' => (string) SessionHelper::get('auth.username', ''),
                ]),
            ]);

            $this->model->completeRun($runId, [
                'RunStatusCode' => $preview['status'],
                'RecordsReceived' => $preview['source_row_count'],
                'RecordsProcessed' => $preview['mapped_record_count'],
                'RecordsSkipped' => 0,
                'RecordsFailed' => 0,
                'SummaryText' => $preview['summary'],
                'ResponsePayloadJson' => $this->jsonEncode($preview['payload']),
            ]);

            $previewResult = [
                'run_id' => $runId,
                'status' => $preview['status'],
                'summary' => $preview['summary'],
                'preview_limit' => $preview['preview_limit'],
                'source_row_count' => $preview['source_row_count'],
                'mapped_record_count' => $preview['mapped_record_count'],
                'truncated' => $preview['truncated'],
                'resolved_scope_codes' => $preview['resolved_scope_codes'],
                'payload_json' => $this->jsonEncode($preview['payload'], true),
                'source_rows' => array_slice($preview['source_rows'], 0, 25),
                'mapped_records' => array_slice($preview['mapped_records'], 0, 25),
            ];
        } catch (\Throwable $e) {
            if ($runId > 0) {
                $this->model->completeRun($runId, [
                    'RunStatusCode' => 'failed',
                    'RecordsReceived' => 0,
                    'RecordsProcessed' => 0,
                    'RecordsFailed' => 1,
                    'SummaryText' => 'Manual test export preview failed.',
                    'ErrorText' => $e->getMessage(),
                    'ResponsePayloadJson' => $this->jsonEncode([
                        'mode' => 'manual_test_export_preview',
                        'error' => $e->getMessage(),
                    ]),
                ]);
            }

            $previewResult = [
                'run_id' => $runId,
                'status' => 'failed',
                'summary' => $e->getMessage(),
                'preview_limit' => (int) ($formData['PreviewLimit'] !== '' ? $formData['PreviewLimit'] : 0),
                'source_row_count' => 0,
                'mapped_record_count' => 0,
                'truncated' => false,
                'resolved_scope_codes' => [],
                'payload_json' => '',
                'source_rows' => [],
                'mapped_records' => [],
            ];
        }

        $this->render('integrations/TestExport', [
            'title' => 'Test Export Runner',
            'interface' => $interface,
            'mappingConfig' => $mappingConfig,
            'formData' => $formData,
            'previewResult' => $previewResult,
            'recentRuns' => $this->model->listRecentRunsForInterface($interfaceId, 10),
            'outputProfileOptions' => $this->availableOutputProfiles($interface, $mappingConfig),
        ]);
    }

    public function downloadTestExport(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $interfaceId = (int) ($_POST['IntegrationInterfaceID'] ?? 0);
        $interface = $this->model->getInterfaceDefinition($interfaceId);
        if ($interface === null) {
            $this->flashError('Integration interface not found.');
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $formData = [
            'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
            'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
            'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'PreviewLimit' => trim((string) ($_POST['PreviewLimit'] ?? '')),
            'OutputProfileCode' => trim((string) ($_POST['OutputProfileCode'] ?? '')),
        ];

        try {
            $preview = $this->prepareTestExportPreview($interface, $mappingConfig, $formData, true);
            $filename = $this->buildTestExportFilename($interface, $formData);
            $json = $this->jsonEncode($preview['payload'], true);

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) strlen($json));
            echo $json;
            exit;
        } catch (\Throwable $e) {
            $this->flashError('JSON export download failed: ' . $e->getMessage());
            header('Location: index.php?route=integration-admin/test-export&id=' . $interfaceId);
            return;
        }
    }

    public function downloadTestExportCsv(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $interfaceId = (int) ($_POST['IntegrationInterfaceID'] ?? 0);
        $interface = $this->model->getInterfaceDefinition($interfaceId);
        if ($interface === null) {
            $this->flashError('Integration interface not found.');
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $formData = [
            'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
            'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
            'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'PreviewLimit' => trim((string) ($_POST['PreviewLimit'] ?? '')),
            'OutputProfileCode' => trim((string) ($_POST['OutputProfileCode'] ?? '')),
        ];

        try {
            $preview = $this->prepareTestExportPreview($interface, $mappingConfig, $formData, true);
            $csv = $this->buildCsvExport($preview['mapped_records']);
            $filename = preg_replace('/\.json$/i', '.csv', $this->buildTestExportFilename($interface, $formData));

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) strlen($csv));
            echo $csv;
            exit;
        } catch (\Throwable $e) {
            $this->flashError('CSV export download failed: ' . $e->getMessage());
            header('Location: index.php?route=integration-admin/test-export&id=' . $interfaceId);
            return;
        }
    }

    public function downloadTestExportPackage(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $interfaceId = (int) ($_POST['IntegrationInterfaceID'] ?? 0);
        $interface = $this->model->getInterfaceDefinition($interfaceId);
        if ($interface === null) {
            $this->flashError('Integration interface not found.');
            header('Location: index.php?route=integration-admin/interfaces');
            return;
        }

        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $formData = [
            'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
            'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
            'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'PreviewLimit' => trim((string) ($_POST['PreviewLimit'] ?? '')),
            'OutputProfileCode' => trim((string) ($_POST['OutputProfileCode'] ?? '')),
        ];

        try {
            $preview = $this->prepareTestExportPreview($interface, $mappingConfig, $formData, true);
            $package = $this->buildTestExportPackage($interface, $mappingConfig, $formData, $preview);
            $filename = preg_replace('/\.json$/i', '.zip', $this->buildTestExportFilename($interface, $formData));

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($package));
            readfile($package);
            @unlink($package);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Export package download failed: ' . $e->getMessage());
            header('Location: index.php?route=integration-admin/test-export&id=' . $interfaceId);
            return;
        }
    }

    private function validateSystemPayload(array $data): void
    {
        if ($data['SystemCode'] === '') {
            throw new \RuntimeException('System code is required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/i', $data['SystemCode'])) {
            throw new \RuntimeException('System code may contain only letters, numbers, underscores, and hyphens.');
        }
        if ($data['SystemName'] === '') {
            throw new \RuntimeException('System name is required.');
        }
        if ($data['DefaultHeadersJson'] !== '') {
            $this->assertValidJson($data['DefaultHeadersJson'], 'Default headers JSON');
        }
    }

    private function validateInterfacePayload(array $data): void
    {
        if ($data['InterfaceCode'] === '') {
            throw new \RuntimeException('Interface code is required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/i', $data['InterfaceCode'])) {
            throw new \RuntimeException('Interface code may contain only letters, numbers, underscores, and hyphens.');
        }
        if ($data['InterfaceName'] === '') {
            throw new \RuntimeException('Interface name is required.');
        }
        if ((int) $data['IntegrationSystemID'] <= 0) {
            throw new \RuntimeException('An integration system must be selected.');
        }
        if (!array_key_exists($data['DirectionCode'], $this->directionOptions())) {
            throw new \RuntimeException('Direction code is invalid.');
        }
        if ($data['TriggerMode'] !== '' && !array_key_exists($data['TriggerMode'], $this->triggerModeOptions())) {
            throw new \RuntimeException('Trigger mode is invalid.');
        }
        if ($data['HttpMethod'] !== '' && !array_key_exists($data['HttpMethod'], $this->httpMethodOptions())) {
            throw new \RuntimeException('HTTP method is invalid.');
        }
        if ($data['PayloadFormat'] !== '' && !array_key_exists($data['PayloadFormat'], $this->payloadFormatOptions())) {
            throw new \RuntimeException('Payload format is invalid.');
        }
        if ($data['MappingConfigJson'] !== '') {
            $this->assertValidJson($data['MappingConfigJson'], 'Mapping configuration JSON');
        }
        if ($data['OutputProfilesJson'] !== '') {
            $this->assertValidJson($data['OutputProfilesJson'], 'Output profiles JSON');
            $profiles = $this->decodeJsonArray($data['OutputProfilesJson'], 'Output profiles JSON');
            $profileCodes = [];
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    throw new \RuntimeException('Each output profile must be a JSON object.');
                }
                $profileCode = trim((string) ($profile['code'] ?? ''));
                if ($profileCode === '') {
                    throw new \RuntimeException('Each output profile must include a code.');
                }
                $profileCodes[$profileCode] = true;
            }
            if ($data['DefaultOutputProfileCode'] !== '' && !isset($profileCodes[$data['DefaultOutputProfileCode']])) {
                throw new \RuntimeException('Default output profile code must match one of the configured output profiles.');
            }
        } elseif ($data['DefaultOutputProfileCode'] !== '') {
            throw new \RuntimeException('Default output profile code cannot be set without output profiles JSON.');
        }
        foreach (['BatchSize', 'TimeoutSeconds'] as $field) {
            if ($data[$field] !== '' && (!ctype_digit((string) $data[$field]) || (int) $data[$field] < 0)) {
                throw new \RuntimeException($field . ' must be a whole number.');
            }
        }
        if ($data['ApprovalStage'] !== '' && !array_key_exists($data['ApprovalStage'], $this->approvalStageOptions())) {
            throw new \RuntimeException('Approval stage is invalid.');
        }
        if ($data['ReadinessStatus'] !== '' && !array_key_exists($data['ReadinessStatus'], $this->readinessStatusOptions())) {
            throw new \RuntimeException('Readiness status is invalid.');
        }
    }

    private function assertValidJson(string $json, string $label): void
    {
        json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException($label . ' is not valid JSON: ' . json_last_error_msg());
        }
    }

    private function installScriptPath(): string
    {
        return realpath(__DIR__ . '/../../config/sql/create_api_integration_foundation_v1.sql') ?: 'backend-php/config/sql/create_api_integration_foundation_v1.sql';
    }

    private function authTypeOptions(): array
    {
        return [
            '' => 'Not set',
            'api_key' => 'API Key',
            'basic' => 'Basic Auth',
            'bearer' => 'Bearer Token',
            'oauth2_client_credentials' => 'OAuth2 Client Credentials',
            'custom' => 'Custom / Adapter Managed',
        ];
    }

    private function environmentOptions(): array
    {
        return [
            '' => 'Not set',
            'dev' => 'Development',
            'test' => 'Test',
            'uat' => 'UAT',
            'prod' => 'Production',
        ];
    }

    private function directionOptions(): array
    {
        return [
            'inbound' => 'Inbound Import',
            'outbound' => 'Outbound Export',
            'bidirectional' => 'Bidirectional',
        ];
    }

    private function triggerModeOptions(): array
    {
        return [
            'manual' => 'Manual',
            'scheduled' => 'Scheduled',
            'event' => 'Event Driven',
        ];
    }

    private function httpMethodOptions(): array
    {
        return [
            '' => 'Not set',
            'GET' => 'GET',
            'POST' => 'POST',
            'PUT' => 'PUT',
            'PATCH' => 'PATCH',
            'DELETE' => 'DELETE',
        ];
    }

    private function payloadFormatOptions(): array
    {
        return [
            '' => 'Not set',
            'json' => 'JSON',
            'xml' => 'XML',
            'csv' => 'CSV',
            'flat_file' => 'Flat File',
            'custom' => 'Custom',
        ];
    }

    private function statusOptions(): array
    {
        return [
            '' => 'All statuses',
            'queued' => 'Queued',
            'running' => 'Running',
            'success' => 'Success',
            'warning' => 'Warning',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ];
    }

    private function decodeMappingConfig(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decodeJsonArray(string $json, string $label): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException($label . ' must decode to a JSON array.');
        }

        return $decoded;
    }

    private function testExportDefaults(array $interface): array
    {
        $mappingConfig = $this->decodeMappingConfig((string) ($interface['MappingConfigJson'] ?? ''));
        $profiles = $this->availableOutputProfiles($interface, $mappingConfig);
        $defaultProfileCode = trim((string) ($interface['DefaultOutputProfileCode'] ?? ''));
        if ($defaultProfileCode === '' && $profiles !== []) {
            $defaultProfileCode = trim((string) ($profiles[0]['code'] ?? ''));
        }

        return [
            'FiscalYearID' => (string) SessionHelper::get('FiscalYearID', ''),
            'VersionID' => (string) SessionHelper::get('VersionID', ''),
            'DataObjectCode' => (string) SessionHelper::get('scope.dataobject_code', ''),
            'PreviewLimit' => (string) max(1, (int) ($interface['BatchSize'] ?? 200) ?: 200),
            'OutputProfileCode' => $defaultProfileCode,
        ];
    }

    private function ensureTestExportAllowed(array $interface, array $mappingConfig, array $formData): void
    {
        $direction = strtolower(trim((string) ($interface['DirectionCode'] ?? '')));
        if (!in_array($direction, ['outbound', 'bidirectional'], true)) {
            throw new \RuntimeException('The test export runner only supports outbound or bidirectional interfaces.');
        }
        if (((int) ($interface['ActiveFlag'] ?? 0)) !== 1) {
            throw new \RuntimeException('The interface is inactive. Activate it before running a test export.');
        }
        if ($mappingConfig === []) {
            throw new \RuntimeException('Mapping configuration JSON is required before running a test export.');
        }
        if (trim((string) ($mappingConfig['source_object'] ?? '')) === '') {
            throw new \RuntimeException('Mapping configuration must define source_object.');
        }
        if ((int) ($interface['FiscalYearRequiredFlag'] ?? 0) === 1 && trim((string) ($formData['FiscalYearID'] ?? '')) === '') {
            throw new \RuntimeException('Fiscal year is required for this interface.');
        }
        if ((int) ($interface['VersionRequiredFlag'] ?? 0) === 1 && trim((string) ($formData['VersionID'] ?? '')) === '') {
            throw new \RuntimeException('Version is required for this interface.');
        }
        if ((int) ($interface['DataScopeRequiredFlag'] ?? 0) === 1 && trim((string) ($formData['DataObjectCode'] ?? '')) === '') {
            throw new \RuntimeException('Data scope is required for this interface.');
        }
        if (trim((string) ($formData['PreviewLimit'] ?? '')) !== '' && !ctype_digit(trim((string) $formData['PreviewLimit']))) {
            throw new \RuntimeException('Preview limit must be a whole number.');
        }
    }

    private function prepareTestExportPreview(array $interface, array $mappingConfig, array $formData, bool $fullExport = false): array
    {
        $this->ensureTestExportAllowed($interface, $mappingConfig, $formData);

        $previewLimit = max(1, min(5000, (int) ($formData['PreviewLimit'] !== '' ? $formData['PreviewLimit'] : 200)));
        $context = [
            'fiscal_year' => $formData['FiscalYearID'],
            'version' => $formData['VersionID'],
            'data_scope' => $formData['DataObjectCode'],
        ];
        $resolvedScopeCodes = $formData['DataObjectCode'] !== ''
            ? $this->model->resolveScopeFilterValues((int) ($formData['FiscalYearID'] ?: 0), $formData['DataObjectCode'])
            : [];
        $selectedProfile = $this->resolveSelectedOutputProfile($interface, $mappingConfig, $formData);

        $sourceRows = $this->model->previewSourceRows($mappingConfig, $context, $fullExport ? null : $previewLimit);
        $mappedRecordsBase = $this->mapSourceRowsToExportRecords(
            $sourceRows,
            is_array($mappingConfig['field_map'] ?? null) ? $mappingConfig['field_map'] : []
        );
        $payloadTransform = $this->applyOutputProfileToRecords($mappedRecordsBase, $selectedProfile);
        $mappedRecords = $payloadTransform['records'];
        $payload = $this->buildPreviewPayload(
            $interface,
            $mappingConfig,
            $formData,
            $mappedRecords,
            $previewLimit,
            $fullExport,
            $selectedProfile,
            $payloadTransform
        );
        $truncated = !$fullExport && count($sourceRows) >= $previewLimit;
        $status = $mappedRecords === [] ? 'warning' : ($truncated ? 'warning' : 'success');
        $summary = $mappedRecords === []
            ? 'Preview returned no source rows for the selected context. No external endpoint call was made.'
            : (($fullExport ? 'Full export built ' : 'Preview built ') . count($mappedRecords) . ' export record(s). No external endpoint call was made.')
                . ($truncated ? ' The preview reached the configured row limit.' : '');
        if ($resolvedScopeCodes !== [] && count($resolvedScopeCodes) > 1) {
            $summary .= ' Parent scope expansion matched ' . count($resolvedScopeCodes) . ' scope code(s).';
        }
        if ($selectedProfile !== null) {
            $summary .= ' Output profile ' . ($selectedProfile['label'] ?? $selectedProfile['code']) . ' was applied.';
        }

        return [
            'context' => $context,
            'full_export' => $fullExport,
            'resolved_scope_codes' => $resolvedScopeCodes,
            'selected_profile' => $selectedProfile,
            'preview_limit' => $previewLimit,
            'source_rows' => $sourceRows,
            'mapped_records' => $mappedRecords,
            'source_row_count' => count($sourceRows),
            'mapped_record_count' => count($mappedRecords),
            'truncated' => $truncated,
            'status' => $status,
            'summary' => $summary,
            'payload' => $payload,
        ];
    }

    private function mapSourceRowsToExportRecords(array $sourceRows, array $fieldMap): array
    {
        $records = [];
        foreach ($sourceRows as $row) {
            $record = [];
            foreach ($fieldMap as $targetField => $sourceColumn) {
                $record[(string) $targetField] = $row[(string) $sourceColumn] ?? null;
            }
            $records[] = $record;
        }

        return $records;
    }

    private function buildPreviewPayload(
        array $interface,
        array $mappingConfig,
        array $formData,
        array $mappedRecords,
        int $previewLimit,
        bool $fullExport = false,
        ?array $selectedProfile = null,
        array $payloadTransform = []
    ): array
    {
        $resolvedScopeCodes = $formData['DataObjectCode'] !== ''
            ? $this->model->resolveScopeFilterValues((int) ($formData['FiscalYearID'] ?: 0), $formData['DataObjectCode'])
            : [];
        $recordsKey = (string) ($payloadTransform['records_key'] ?? 'records');

        $payload = [
            'mode' => 'manual_test_export_preview',
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
            'interface' => [
                'id' => (int) ($interface['IntegrationInterfaceID'] ?? 0),
                'code' => (string) ($interface['InterfaceCode'] ?? ''),
                'name' => (string) ($interface['InterfaceName'] ?? ''),
                'direction' => (string) ($interface['DirectionCode'] ?? ''),
                'system_code' => (string) ($interface['SystemCode'] ?? ''),
                'system_name' => (string) ($interface['SystemName'] ?? ''),
            ],
            'source' => [
                'type' => (string) ($mappingConfig['source_type'] ?? ''),
                'object' => (string) ($mappingConfig['source_object'] ?? ''),
            ],
            'context' => [
                'fiscal_year_id' => $formData['FiscalYearID'] !== '' ? (int) $formData['FiscalYearID'] : null,
                'version_id' => $formData['VersionID'] !== '' ? (int) $formData['VersionID'] : null,
                'data_object_code' => $formData['DataObjectCode'] !== '' ? $formData['DataObjectCode'] : null,
                'resolved_scope_codes' => $resolvedScopeCodes,
            ],
            'mapping' => [
                'field_map' => $mappingConfig['field_map'] ?? [],
                'amount_structure' => $mappingConfig['amount_structure'] ?? null,
                'export_grain' => $mappingConfig['export_grain'] ?? null,
            ],
            'output_profile' => $selectedProfile,
            'preview' => [
                'record_count' => count($mappedRecords),
                'preview_limit' => $previewLimit,
                'full_export' => $fullExport,
                'external_dispatch_performed' => false,
            ],
        ];

        $payload[$recordsKey] = $mappedRecords;
        if ($recordsKey !== 'records') {
            $payload['records_key'] = $recordsKey;
        }

        return $payload;
    }

    private function availableOutputProfiles(array $interface, array $mappingConfig): array
    {
        $profilesJson = trim((string) ($interface['OutputProfilesJson'] ?? ''));
        if ($profilesJson !== '') {
            $decoded = json_decode($profilesJson, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }
        }

        $decoded = $mappingConfig['output_profiles'] ?? [];
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function resolveSelectedOutputProfile(array $interface, array $mappingConfig, array $formData): ?array
    {
        $requestedCode = trim((string) ($formData['OutputProfileCode'] ?? ''));
        $defaultCode = trim((string) ($interface['DefaultOutputProfileCode'] ?? ''));
        $profiles = $this->availableOutputProfiles($interface, $mappingConfig);

        if ($profiles === []) {
            return null;
        }

        $selectedCode = $requestedCode !== '' ? $requestedCode : $defaultCode;
        if ($selectedCode === '') {
            return $profiles[0];
        }

        foreach ($profiles as $profile) {
            if (trim((string) ($profile['code'] ?? '')) === $selectedCode) {
                return $profile;
            }
        }

        throw new \RuntimeException('Selected output profile could not be found.');
    }

    private function applyOutputProfileToRecords(array $records, ?array $profile): array
    {
        if ($profile === null) {
            return [
                'records' => $records,
                'records_key' => 'records',
            ];
        }

        $includeFields = array_values(array_filter(array_map('strval', is_array($profile['include_fields'] ?? null) ? $profile['include_fields'] : []), static fn (string $value): bool => trim($value) !== ''));
        $excludeLookup = array_fill_keys(array_values(array_filter(array_map('strval', is_array($profile['exclude_fields'] ?? null) ? $profile['exclude_fields'] : []), static fn (string $value): bool => trim($value) !== '')), true);
        $renameMap = is_array($profile['rename_map'] ?? null) ? $profile['rename_map'] : [];
        $fieldOrder = array_values(array_filter(array_map('strval', is_array($profile['field_order'] ?? null) ? $profile['field_order'] : []), static fn (string $value): bool => trim($value) !== ''));
        $recordsKey = trim((string) ($profile['records_key'] ?? (($profile['envelope']['records_key'] ?? null) ?: 'records')));
        if ($recordsKey === '') {
            $recordsKey = 'records';
        }

        $transformed = [];
        foreach ($records as $record) {
            $current = is_array($record) ? $record : [];
            if ($includeFields !== []) {
                $current = array_intersect_key($current, array_fill_keys($includeFields, true));
            }
            if ($excludeLookup !== []) {
                $current = array_diff_key($current, $excludeLookup);
            }

            $renamed = [];
            foreach ($current as $key => $value) {
                $nextKey = array_key_exists($key, $renameMap) ? (string) $renameMap[$key] : (string) $key;
                $renamed[$nextKey] = $value;
            }

            if ($fieldOrder !== []) {
                $ordered = [];
                foreach ($fieldOrder as $field) {
                    if (array_key_exists($field, $renamed)) {
                        $ordered[$field] = $renamed[$field];
                        unset($renamed[$field]);
                    }
                }
                $renamed = $ordered + $renamed;
            }

            $transformed[] = $renamed;
        }

        return [
            'records' => $transformed,
            'records_key' => $recordsKey,
        ];
    }

    private function jsonEncode(array $payload, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($payload, $flags);
        if (!is_string($encoded)) {
            throw new \RuntimeException('JSON encoding failed.');
        }

        return $encoded;
    }

    private function buildTestExportFilename(array $interface, array $formData): string
    {
        $parts = [
            preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($interface['InterfaceCode'] ?? 'integration-export')) ?: 'integration-export',
            $formData['FiscalYearID'] !== '' ? ('fy' . $formData['FiscalYearID']) : null,
            $formData['VersionID'] !== '' ? ('ver' . $formData['VersionID']) : null,
            $formData['DataObjectCode'] !== '' ? ('scope-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $formData['DataObjectCode'])) : null,
            gmdate('Ymd-His'),
        ];

        $filename = implode('_', array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== '')));
        return $filename . '.json';
    }

    private function buildCsvExport(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('CSV export stream could not be opened.');
        }

        if ($rows === []) {
            fputcsv($stream, ['no_data']);
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            return is_string($csv) ? $csv : '';
        }

        $headers = array_keys((array) $rows[0]);
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $line[] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            fputcsv($stream, $line);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if (!is_string($csv)) {
            throw new \RuntimeException('CSV export content could not be generated.');
        }

        return $csv;
    }

    private function buildRunSummaryText(array $run): string
    {
        $lines = [
            'CBMSv21 Integration Run Summary',
            'Run ID: ' . (string) ($run['IntegrationRunID'] ?? ''),
            'Interface: ' . (string) ($run['InterfaceName'] ?? ''),
            'Interface Code: ' . (string) ($run['InterfaceCode'] ?? ''),
            'System: ' . (string) ($run['SystemName'] ?? ''),
            'System Code: ' . (string) ($run['SystemCode'] ?? ''),
            'Direction: ' . (string) ($run['DirectionCode'] ?? ''),
            'Module / Entity: ' . trim((string) (($run['ModuleCode'] ?? '') . ' / ' . ($run['EntityCode'] ?? '')), ' /'),
            'Status: ' . (string) ($run['RunStatusCode'] ?? ''),
            'Trigger Source: ' . (string) ($run['TriggerSourceCode'] ?? ''),
            'Triggered By: ' . (string) (($run['DisplayName'] ?? '') !== '' ? $run['DisplayName'] : ($run['Username'] ?? '')),
            'Started At: ' . (string) ($run['StartedAt'] ?? ''),
            'Completed At: ' . (string) ($run['CompletedAt'] ?? ''),
            'Fiscal Year ID: ' . (string) ($run['FiscalYearID'] ?? ''),
            'Version ID: ' . (string) ($run['VersionID'] ?? ''),
            'DataObjectCode: ' . (string) ($run['DataObjectCode'] ?? ''),
            'Records Received: ' . (string) ($run['RecordsReceived'] ?? 0),
            'Records Processed: ' . (string) ($run['RecordsProcessed'] ?? 0),
            'Records Failed: ' . (string) ($run['RecordsFailed'] ?? 0),
            'Approval Stage: ' . (string) ($run['ApprovalStage'] ?? ''),
            'Readiness Status: ' . (string) ($run['ReadinessStatus'] ?? ''),
            'Business Owner: ' . (string) ($run['BusinessOwner'] ?? ''),
            'Source Owner: ' . (string) ($run['SourceOwner'] ?? ''),
            '',
            'Summary',
            (string) ($run['SummaryText'] ?? ''),
        ];

        if (trim((string) ($run['ErrorText'] ?? '')) !== '') {
            $lines[] = '';
            $lines[] = 'Error';
            $lines[] = (string) $run['ErrorText'];
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function buildPreviewSummaryText(array $interface, array $formData, array $preview): string
    {
        $profile = is_array($preview['selected_profile'] ?? null) ? $preview['selected_profile'] : null;
        $lines = [
            'CBMSv21 Test Export Package',
            'Generated At UTC: ' . gmdate('Y-m-d H:i:s'),
            'Interface: ' . (string) ($interface['InterfaceName'] ?? ''),
            'Interface Code: ' . (string) ($interface['InterfaceCode'] ?? ''),
            'System: ' . (string) ($interface['SystemName'] ?? ''),
            'System Code: ' . (string) ($interface['SystemCode'] ?? ''),
            'Approval Stage: ' . (string) ($interface['ApprovalStage'] ?? ''),
            'Readiness Status: ' . (string) ($interface['ReadinessStatus'] ?? ''),
            'Business Owner: ' . (string) ($interface['BusinessOwner'] ?? ''),
            'Source Owner: ' . (string) ($interface['SourceOwner'] ?? ''),
            'Fiscal Year ID: ' . (string) ($formData['FiscalYearID'] ?? ''),
            'Version ID: ' . (string) ($formData['VersionID'] ?? ''),
            'DataObjectCode: ' . (string) ($formData['DataObjectCode'] ?? ''),
            'Resolved Scope Code Count: ' . (string) count((array) ($preview['resolved_scope_codes'] ?? [])),
            'Records Exported: ' . (string) ($preview['mapped_record_count'] ?? 0),
            'Output Profile: ' . (string) ($profile['label'] ?? $profile['code'] ?? 'default'),
            '',
            'Summary',
            (string) ($preview['summary'] ?? ''),
        ];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function buildTestExportPackage(array $interface, array $mappingConfig, array $formData, array $preview): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive is not available in PHP, so package export cannot be created.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'cbms-export-package-');
        if ($tempFile === false) {
            throw new \RuntimeException('Temporary package file could not be created.');
        }

        $zipPath = $tempFile . '.zip';
        @unlink($tempFile);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP package could not be created.');
        }

        $zip->addFromString('summary.txt', $this->buildPreviewSummaryText($interface, $formData, $preview));
        $zip->addFromString('payload.json', $this->jsonEncode($preview['payload'], true));
        $zip->addFromString('records.csv', $this->buildCsvExport($preview['mapped_records']));
        $zip->addFromString('interface.json', $this->jsonEncode([
            'interface' => $interface,
            'mapping_config' => $mappingConfig,
            'selected_profile' => $preview['selected_profile'] ?? null,
        ], true));
        $zip->close();

        return $zipPath;
    }

    private function approvalStageOptions(): array
    {
        return [
            '' => 'Not set',
            'draft' => 'Draft',
            'candidate' => 'Candidate',
            'approved_for_test' => 'Approved for Test',
            'approved_for_uat' => 'Approved for UAT',
            'approved_for_prod' => 'Approved for Production',
        ];
    }

    private function readinessStatusOptions(): array
    {
        return [
            '' => 'Not set',
            'not_started' => 'Not Started',
            'drafting' => 'Drafting',
            'test_ready' => 'Test Ready',
            'uat_ready' => 'UAT Ready',
            'production_ready' => 'Production Ready',
            'blocked' => 'Blocked',
        ];
    }
}
