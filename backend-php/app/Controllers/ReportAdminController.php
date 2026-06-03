<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReportingAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class ReportAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['ADMIN_ALL', 'SYSADMIN']],
    ];

    private ReportingAdminModel $model;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/ReportingAdminModel.php';

        $this->model = new ReportingAdminModel($conn);
    }

    public function definitions(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $foundationInstalled = $this->model->supportsReportingFoundation();
        $rows = $foundationInstalled ? $this->model->listDefinitions($filters) : [];

        $this->render('reportadmin/DefinitionsList', [
            'title' => 'Report Definitions',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
            'moduleOptions' => $foundationInstalled ? $this->model->listModuleOptions() : [],
        ]);
    }

    public function definitionForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $this->model->getDefinition($id) : null;

        $this->render('reportadmin/DefinitionForm', [
            'title' => $id > 0 ? 'Edit Report Definition' : 'Create Report Definition',
            'record' => $record,
            'foundationInstalled' => $this->model->supportsReportingFoundation(),
            'installScriptPath' => $this->installScriptPath(),
            'formatOptions' => $this->formatOptions(),
        ]);
    }

    public function saveDefinition(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=report-admin/definitions');
            return;
        }
        if (!$this->model->supportsReportingFoundation()) {
            $this->flashError('Install the reporting foundation schema before saving report definitions.');
            header('Location: index.php?route=report-admin/definitions');
            return;
        }

        $id = (int) ($_POST['ReportDefinitionID'] ?? 0);
        $data = [
            'ReportCode' => trim((string) ($_POST['ReportCode'] ?? '')),
            'ReportName' => trim((string) ($_POST['ReportName'] ?? '')),
            'ModuleCode' => trim((string) ($_POST['ModuleCode'] ?? '')),
            'ReportGroupCode' => trim((string) ($_POST['ReportGroupCode'] ?? '')),
            'ReportDescription' => trim((string) ($_POST['ReportDescription'] ?? '')),
            'SsrsPath' => trim((string) ($_POST['SsrsPath'] ?? '')),
            'ServerUrlOverride' => trim((string) ($_POST['ServerUrlOverride'] ?? '')),
            'OutputFormatsCsv' => $this->normalizeFormatsCsv((string) ($_POST['OutputFormatsCsv'] ?? '')),
            'DefaultFormatCode' => strtoupper(trim((string) ($_POST['DefaultFormatCode'] ?? ''))),
            'PermissionCode' => trim((string) ($_POST['PermissionCode'] ?? '')),
            'ContextRequiredFlag' => isset($_POST['ContextRequiredFlag']) ? 1 : 0,
            'FiscalYearRequiredFlag' => isset($_POST['FiscalYearRequiredFlag']) ? 1 : 0,
            'VersionRequiredFlag' => isset($_POST['VersionRequiredFlag']) ? 1 : 0,
            'DataScopeRequiredFlag' => isset($_POST['DataScopeRequiredFlag']) ? 1 : 0,
            'DateFromRequiredFlag' => isset($_POST['DateFromRequiredFlag']) ? 1 : 0,
            'DateToRequiredFlag' => isset($_POST['DateToRequiredFlag']) ? 1 : 0,
            'ParameterConfigJson' => trim((string) ($_POST['ParameterConfigJson'] ?? '')),
            'SortOrder' => trim((string) ($_POST['SortOrder'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'Notes' => trim((string) ($_POST['Notes'] ?? '')),
        ];

        try {
            $this->validateDefinitionPayload($data);
            $savedId = $this->model->saveDefinition($data, (int) SessionHelper::get('auth.user_id', 0), $id > 0 ? $id : null);
            $this->flashSuccess('Report definition saved.');
            header('Location: index.php?route=report-admin/definition-form&id=' . $savedId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Report definition save failed: ' . $e->getMessage());
            $target = 'index.php?route=report-admin/definition-form';
            if ($id > 0) {
                $target .= '&id=' . $id;
            }
            header('Location: ' . $target);
            return;
        }
    }

    private function installScriptPath(): string
    {
        return 'backend-php/config/sql/create_ssrs_reporting_foundation_v1.sql';
    }

    private function formatOptions(): array
    {
        return [
            'HTML' => 'Interactive Preview',
            'PDF' => 'PDF',
            'EXCEL' => 'Excel',
            'WORD' => 'Word',
            'CSV' => 'CSV',
        ];
    }

    private function normalizeFormatsCsv(string $value): string
    {
        $parts = preg_split('/[\s,]+/', strtoupper(trim($value))) ?: [];
        $allowed = array_keys($this->formatOptions());
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || !in_array($part, $allowed, true)) {
                continue;
            }
            $clean[$part] = true;
        }
        if ($clean === []) {
            return 'HTML,PDF,EXCEL';
        }
        return implode(',', array_keys($clean));
    }

    private function validateDefinitionPayload(array $data): void
    {
        if (trim((string) ($data['ReportCode'] ?? '')) === '') {
            throw new \InvalidArgumentException('Report code is required.');
        }
        if (trim((string) ($data['ReportName'] ?? '')) === '') {
            throw new \InvalidArgumentException('Report name is required.');
        }

        $defaultFormat = trim((string) ($data['DefaultFormatCode'] ?? ''));
        $formats = array_filter(array_map('trim', explode(',', (string) ($data['OutputFormatsCsv'] ?? ''))));
        if ($defaultFormat !== '' && !in_array($defaultFormat, $formats, true)) {
            throw new \InvalidArgumentException('Default format must be included in supported output formats.');
        }

        $sortOrder = trim((string) ($data['SortOrder'] ?? ''));
        if ($sortOrder !== '' && !ctype_digit(ltrim($sortOrder, '-'))) {
            throw new \InvalidArgumentException('Sort order must be a whole number.');
        }

        $parameterConfig = trim((string) ($data['ParameterConfigJson'] ?? ''));
        if ($parameterConfig !== '') {
            json_decode($parameterConfig, true, 512, JSON_THROW_ON_ERROR);
        }
    }
}
