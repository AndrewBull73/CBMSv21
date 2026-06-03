<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\FiscalContextModel;
use App\Models\ReportingAdminModel;
use App\Models\SystemSettingsModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class ReportsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
    ];

    private ReportingAdminModel $model;
    private SystemSettingsModel $settings;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/ReportingAdminModel.php';
        require_once __DIR__ . '/../Models/SystemSettingsModel.php';

        $this->model = new ReportingAdminModel($conn);
        $this->settings = new SystemSettingsModel($conn);
    }

    public function catalogue(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
        ];

        $foundationInstalled = $this->model->supportsReportingFoundation();
        $rows = $foundationInstalled ? $this->model->listDefinitions($filters, true) : [];
        $rows = array_values(array_filter($rows, fn(array $row): bool => $this->canRunDefinition($row)));

        $this->render('reports/Catalogue', [
            'title' => 'Report Catalogue',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $foundationInstalled,
            'installScriptPath' => $this->installScriptPath(),
            'moduleOptions' => $foundationInstalled ? $this->model->listModuleOptions() : [],
            'canManageReports' => $this->isAdmin(),
        ]);
    }

    public function run(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $definition = $this->model->getDefinition($id);
        if ($definition === null || (int) ($definition['ActiveFlag'] ?? 0) !== 1) {
            $this->flashError('Report definition not found.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }
        if (!$this->canRunDefinition($definition)) {
            $this->denyAccess('You do not have permission to run this report.');
            return;
        }

        $inputs = $this->collectRunInputs($_GET, $definition);
        $previewRequested = ((string) ($_GET['preview'] ?? '') === '1');
        $previewErrors = [];
        $previewUrl = '';
        $resolvedBaseUrl = $this->resolveSsrsBaseUrl($definition);

        if ($previewRequested) {
            $previewErrors = $this->validateRunInputs($definition, $inputs);
            if ($previewErrors === []) {
                $previewUrl = $this->buildSsrsUrl($definition, $inputs);
                if ($previewUrl === '') {
                    $previewErrors[] = 'SSRS server URL or report path is missing. Set a server override on the report or configure REPORT_SSRS_BASE_URL.';
                }
            }
        }

        $this->render('reports/Runner', [
            'title' => 'Run Report',
            'definition' => $definition,
            'inputs' => $inputs,
            'previewRequested' => $previewRequested,
            'previewErrors' => $previewErrors,
            'previewUrl' => $previewUrl,
            'resolvedBaseUrl' => $resolvedBaseUrl,
            'availableFormats' => $this->availableFormats($definition),
            'foundationInstalled' => $this->model->supportsReportingFoundation(),
            'installScriptPath' => $this->installScriptPath(),
            'canManageReports' => $this->isAdmin(),
        ]);
    }

    public function open(): void
    {
        $reportCode = trim((string) ($_GET['code'] ?? ''));
        if ($reportCode === '') {
            $this->flashError('Report code is missing.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }

        $definition = $this->model->getDefinitionByCode($reportCode);
        if ($definition === null || (int) ($definition['ActiveFlag'] ?? 0) !== 1) {
            $this->flashError('Report definition not found.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }
        if (!$this->canRunDefinition($definition)) {
            $this->denyAccess('You do not have permission to run this report.');
            return;
        }

        header('Location: index.php?route=reports/run&id=' . (int) ($definition['ReportDefinitionID'] ?? 0));
        exit;
    }

    public function execute(): void
    {
        $reportCode = trim((string) ($_GET['code'] ?? ''));
        if ($reportCode === '') {
            $this->flashError('Report code is missing.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }

        $definition = $this->model->getDefinitionByCode($reportCode);
        if ($definition === null || (int) ($definition['ActiveFlag'] ?? 0) !== 1) {
            $this->flashError('Report definition not found.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }
        if (!$this->canRunDefinition($definition)) {
            $this->denyAccess('You do not have permission to run this report.');
            return;
        }

        $inputs = $this->collectRunInputs($_GET, $definition);
        $missingContext = $this->missingContextRequirements($definition, $inputs);
        if ($missingContext !== []) {
            $query = http_build_query([
                'route' => 'reports/context',
                'code' => $reportCode,
            ]);
            header('Location: index.php?' . $query);
            exit;
        }
        $errors = $this->validateRunInputs($definition, $inputs);
        if ($errors !== []) {
            $this->flashError(implode(' ', $errors));
            header('Location: index.php?route=reports/run&id=' . (int) ($definition['ReportDefinitionID'] ?? 0));
            exit;
        }

        $launchUrl = $this->buildSsrsUrl($definition, $inputs);
        if ($launchUrl === '') {
            $this->flashError('SSRS server URL or report path is missing for this report definition.');
            header('Location: index.php?route=reports/run&id=' . (int) ($definition['ReportDefinitionID'] ?? 0));
            exit;
        }

        $this->model->createRun([
            'ReportDefinitionID' => (int) ($definition['ReportDefinitionID'] ?? 0),
            'RunStatusCode' => 'launched',
            'ExecutionMode' => 'ssrs_url',
            'RequestedByUserID' => $this->currentUserId(),
            'OutputFormatCode' => $inputs['OutputFormatCode'],
            'FiscalYearID' => $inputs['FiscalYearID'],
            'VersionID' => $inputs['VersionID'],
            'DataObjectCode' => $inputs['DataObjectCode'],
            'DateFrom' => $inputs['DateFrom'],
            'DateTo' => $inputs['DateTo'],
            'CompletedAt' => date('Y-m-d H:i:s'),
            'DurationSeconds' => 0,
            'LaunchUrl' => $launchUrl,
            'ParameterPayloadJson' => json_encode($this->buildRunPayload($definition, $inputs), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'SummaryText' => 'Launched SSRS report directly from the navigation menu.',
            'ErrorText' => null,
        ]);

        header('Location: ' . $launchUrl);
        exit;
    }

    public function context(): void
    {
        $reportCode = trim((string) ($_GET['code'] ?? ''));
        if ($reportCode === '') {
            $this->flashError('Report code is missing.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }

        $definition = $this->model->getDefinitionByCode($reportCode);
        if ($definition === null || (int) ($definition['ActiveFlag'] ?? 0) !== 1) {
            $this->flashError('Report definition not found.');
            header('Location: index.php?route=reports/catalogue');
            exit;
        }
        if (!$this->canRunDefinition($definition)) {
            $this->denyAccess('You do not have permission to run this report.');
            return;
        }

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/FiscalContextModel.php';

        $contextModel = new FiscalContextModel($conn);
        $inputs = $this->collectRunInputs($_GET, $definition);
        $missingContext = $this->missingContextRequirements($definition, $inputs);

        $fiscalYears = $contextModel->listFiscalYears();
        $versions = (int) ($inputs['FiscalYearID'] ?? 0) > 0
            ? $contextModel->listVersions((int) $inputs['FiscalYearID'])
            : [];

        $returnUrl = $this->mergeLinkedContextIntoUrl('index.php?route=reports/context&code=' . rawurlencode($reportCode));
        $launchUrl = $this->mergeLinkedContextIntoUrl('index.php?route=reports/execute&code=' . rawurlencode($reportCode));
        $pickerUrl = 'index.php?' . http_build_query([
            'route' => 'dataobjects/picker',
            'fy' => (int) $inputs['FiscalYearID'],
            'ver' => (int) $inputs['VersionID'],
            'selected' => (string) ($inputs['DataObjectCode'] ?? ''),
            'return' => $returnUrl,
        ]);
        $clearScopeUrl = 'index.php?' . http_build_query([
            'route' => 'dataobjects/select',
            'clear' => 1,
            'return' => $returnUrl,
        ]);

        $this->render('reports/ContextRequired', [
            'title' => 'Set Report Context',
            'definition' => $definition,
            'inputs' => $inputs,
            'missingContext' => $missingContext,
            'fiscalYears' => $fiscalYears,
            'versions' => $versions,
            'returnUrl' => $returnUrl,
            'launchUrl' => $launchUrl,
            'pickerUrl' => $pickerUrl,
            'clearScopeUrl' => $clearScopeUrl,
            'canAutoLaunch' => $missingContext === [],
            'canManageReports' => $this->isAdmin(),
        ]);
    }

    public function launch(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Security check failed.';
            return;
        }

        $id = (int) ($_POST['ReportDefinitionID'] ?? 0);
        $definition = $this->model->getDefinition($id);
        if ($definition === null || (int) ($definition['ActiveFlag'] ?? 0) !== 1) {
            http_response_code(404);
            echo 'Report definition not found.';
            return;
        }
        if (!$this->canRunDefinition($definition)) {
            http_response_code(403);
            echo 'You do not have permission to run this report.';
            return;
        }

        $inputs = $this->collectRunInputs($_POST, $definition);
        $errors = $this->validateRunInputs($definition, $inputs);
        if ($errors !== []) {
            http_response_code(422);
            echo implode("\n", $errors);
            return;
        }

        $launchUrl = $this->buildSsrsUrl($definition, $inputs);
        if ($launchUrl === '') {
            http_response_code(422);
            echo 'SSRS server URL or report path is missing for this report definition.';
            return;
        }

        $summary = 'Launched SSRS report from CBMS.';
        $this->model->createRun([
            'ReportDefinitionID' => (int) ($definition['ReportDefinitionID'] ?? 0),
            'RunStatusCode' => 'launched',
            'ExecutionMode' => 'ssrs_url',
            'RequestedByUserID' => $this->currentUserId(),
            'OutputFormatCode' => $inputs['OutputFormatCode'],
            'FiscalYearID' => $inputs['FiscalYearID'],
            'VersionID' => $inputs['VersionID'],
            'DataObjectCode' => $inputs['DataObjectCode'],
            'DateFrom' => $inputs['DateFrom'],
            'DateTo' => $inputs['DateTo'],
            'CompletedAt' => date('Y-m-d H:i:s'),
            'DurationSeconds' => 0,
            'LaunchUrl' => $launchUrl,
            'ParameterPayloadJson' => json_encode($this->buildRunPayload($definition, $inputs), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'SummaryText' => $summary,
            'ErrorText' => null,
        ]);

        header('Location: ' . $launchUrl);
        exit;
    }

    public function history(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $isAdmin = $this->isAdmin();
        $rows = $this->model->supportsReportingFoundation()
            ? $this->model->listRuns($filters, $this->currentUserId(), !$isAdmin)
            : [];

        $this->render('reports/History', [
            'title' => 'Report Run History',
            'rows' => $rows,
            'filters' => $filters,
            'foundationInstalled' => $this->model->supportsReportingFoundation(),
            'installScriptPath' => $this->installScriptPath(),
            'moduleOptions' => $this->model->supportsReportingFoundation() ? $this->model->listModuleOptions() : [],
            'statusOptions' => $this->statusOptions(),
            'canManageReports' => $isAdmin,
        ]);
    }

    public function runDetail(): void
    {
        $runId = (int) ($_GET['id'] ?? 0);
        $run = $this->model->getRunDetail($runId, $this->currentUserId(), !$this->isAdmin());
        if ($run === null) {
            $this->flashError('Report run not found.');
            header('Location: index.php?route=reports/history');
            exit;
        }

        $parameterPayload = $this->decodeJson((string) ($run['ParameterPayloadJson'] ?? ''));

        $this->render('reports/RunDetail', [
            'title' => 'Report Run Detail',
            'run' => $run,
            'parameterPayload' => $parameterPayload,
            'canManageReports' => $this->isAdmin(),
        ]);
    }

    private function collectRunInputs(array $source, array $definition): array
    {
        $defaultFormat = strtoupper(trim((string) ($definition['DefaultFormatCode'] ?? '')));
        if ($defaultFormat === '') {
            $formats = $this->availableFormats($definition);
            $defaultFormat = $formats[0] ?? 'HTML';
        }

        $sessionFiscalYearId = (int) SessionHelper::get('FiscalYearID', 0);
        $sessionVersionId = (int) SessionHelper::get('VersionID', 0);
        $sessionDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));

        return [
            // Formal report launches should stay aligned to the active CBMS context bar.
            'FiscalYearID' => $sessionFiscalYearId,
            'VersionID' => $sessionVersionId,
            'DataObjectCode' => $sessionDataObjectCode,
            'DateFrom' => trim((string) ($source['DateFrom'] ?? '')),
            'DateTo' => trim((string) ($source['DateTo'] ?? '')),
            'OutputFormatCode' => strtoupper(trim((string) ($source['OutputFormatCode'] ?? $defaultFormat))),
        ];
    }

    private function validateRunInputs(array $definition, array $inputs): array
    {
        $errors = [];

        if ((int) ($definition['ContextRequiredFlag'] ?? 0) === 1
            && (int) $inputs['FiscalYearID'] <= 0
            && (int) $inputs['VersionID'] <= 0
            && trim((string) $inputs['DataObjectCode']) === '') {
            $errors[] = 'This report expects linked fiscal or scope context. Provide at least one context value before launch.';
        }
        if ((int) ($definition['FiscalYearRequiredFlag'] ?? 0) === 1 && (int) $inputs['FiscalYearID'] <= 0) {
            $errors[] = 'Fiscal year is required.';
        }
        if ((int) ($definition['VersionRequiredFlag'] ?? 0) === 1 && (int) $inputs['VersionID'] <= 0) {
            $errors[] = 'Version is required.';
        }
        if ((int) ($definition['DataScopeRequiredFlag'] ?? 0) === 1 && trim((string) $inputs['DataObjectCode']) === '') {
            $errors[] = 'Data scope is required.';
        }
        if ((int) ($definition['DateFromRequiredFlag'] ?? 0) === 1 && trim((string) $inputs['DateFrom']) === '') {
            $errors[] = 'From date is required.';
        }
        if ((int) ($definition['DateToRequiredFlag'] ?? 0) === 1 && trim((string) $inputs['DateTo']) === '') {
            $errors[] = 'To date is required.';
        }
        if ($inputs['DateFrom'] !== '' && !$this->isValidDate($inputs['DateFrom'])) {
            $errors[] = 'From date is not a valid date.';
        }
        if ($inputs['DateTo'] !== '' && !$this->isValidDate($inputs['DateTo'])) {
            $errors[] = 'To date is not a valid date.';
        }
        if ($inputs['DateFrom'] !== '' && $inputs['DateTo'] !== '' && $inputs['DateFrom'] > $inputs['DateTo']) {
            $errors[] = 'From date cannot be later than To date.';
        }
        if (!in_array($inputs['OutputFormatCode'], $this->availableFormats($definition), true)) {
            $errors[] = 'Output format is not allowed for this report.';
        }

        return $errors;
    }

    private function missingContextRequirements(array $definition, array $inputs): array
    {
        $missing = [];
        if ((int) ($definition['FiscalYearRequiredFlag'] ?? 0) === 1 && (int) $inputs['FiscalYearID'] <= 0) {
            $missing[] = 'Fiscal year';
        }
        if ((int) ($definition['VersionRequiredFlag'] ?? 0) === 1 && (int) $inputs['VersionID'] <= 0) {
            $missing[] = 'Version';
        }
        if ((int) ($definition['DataScopeRequiredFlag'] ?? 0) === 1 && trim((string) $inputs['DataObjectCode']) === '') {
            $missing[] = 'Data scope';
        }

        return $missing;
    }

    private function canRunDefinition(array $definition): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissionCode = trim((string) ($definition['PermissionCode'] ?? ''));
        if ($permissionCode === '') {
            return true;
        }

        $rbac = new Rbac($GLOBALS['conn'] ?? null);
        return $rbac->canAny([$permissionCode, 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function isAdmin(): bool
    {
        $rbac = new Rbac($GLOBALS['conn'] ?? null);
        return $rbac->canAny(['ADMIN_ALL', 'SYSADMIN']);
    }

    private function currentUserId(): int
    {
        return (int) SessionHelper::get('auth.user_id', 0);
    }

    private function availableFormats(array $definition): array
    {
        $allowed = ['HTML', 'PDF', 'EXCEL', 'WORD', 'CSV'];
        $parts = preg_split('/[\s,]+/', strtoupper(trim((string) ($definition['OutputFormatsCsv'] ?? '')))) ?: [];
        $formats = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && in_array($part, $allowed, true)) {
                $formats[$part] = true;
            }
        }
        if ($formats === []) {
            return ['HTML', 'PDF', 'EXCEL'];
        }
        return array_keys($formats);
    }

    private function resolveSsrsBaseUrl(array $definition): string
    {
        $override = trim((string) ($definition['ServerUrlOverride'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $setting = trim((string) ($this->settings->get('REPORT_SSRS_BASE_URL', getenv('SSRS_BASE_URL') ?: '') ?? ''));
        return $setting;
    }

    private function buildSsrsUrl(array $definition, array $inputs): string
    {
        $ssrsPath = trim((string) ($definition['SsrsPath'] ?? ''));
        if ($ssrsPath === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $ssrsPath) === 1) {
            $baseUrl = $ssrsPath;
            $pathPrefix = '';
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
        } else {
            $baseUrl = trim($this->resolveSsrsBaseUrl($definition));
            if ($baseUrl === '') {
                return '';
            }
            $baseUrl = rtrim($baseUrl, '?');
            $encodedPath = '/' . str_replace('%2F', '/', rawurlencode(ltrim($ssrsPath, '/')));
            $pathPrefix = '?' . $encodedPath;
            $separator = '&';
        }

        $query = $this->buildSsrsParameterArray($definition, $inputs);
        if ($inputs['OutputFormatCode'] !== 'HTML') {
            $query['rs:Format'] = $this->mapFormatForSsrs($inputs['OutputFormatCode']);
            $query['rs:Command'] = 'Render';
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $baseUrl . $pathPrefix . ($queryString !== '' ? $separator . $queryString : '');
    }

    private function buildSsrsParameterArray(array $definition, array $inputs): array
    {
        $config = $this->decodeJson((string) ($definition['ParameterConfigJson'] ?? ''));
        $map = is_array($config['ssrs_param_map'] ?? null) ? $config['ssrs_param_map'] : [];
        $staticParams = is_array($config['static_params'] ?? null) ? $config['static_params'] : [];

        $params = [];
        $includeFiscalYear = array_key_exists('fiscal_year', $map) || (int) ($definition['FiscalYearRequiredFlag'] ?? 0) === 1;
        $includeVersion = array_key_exists('version', $map) || (int) ($definition['VersionRequiredFlag'] ?? 0) === 1;
        $includeDataScope = array_key_exists('data_scope', $map) || (int) ($definition['DataScopeRequiredFlag'] ?? 0) === 1;
        $includeDateFrom = array_key_exists('date_from', $map) || (int) ($definition['DateFromRequiredFlag'] ?? 0) === 1;
        $includeDateTo = array_key_exists('date_to', $map) || (int) ($definition['DateToRequiredFlag'] ?? 0) === 1;

        if ($includeFiscalYear && (int) $inputs['FiscalYearID'] > 0) {
            $params[(string) ($map['fiscal_year'] ?? 'FiscalYearID')] = (string) $inputs['FiscalYearID'];
        }
        if ($includeVersion && (int) $inputs['VersionID'] > 0) {
            $params[(string) ($map['version'] ?? 'VersionID')] = (string) $inputs['VersionID'];
        }
        if ($includeDataScope && trim((string) $inputs['DataObjectCode']) !== '') {
            $params[(string) ($map['data_scope'] ?? 'DataObjectCode')] = trim((string) $inputs['DataObjectCode']);
        }
        if ($includeDateFrom && trim((string) $inputs['DateFrom']) !== '') {
            $params[(string) ($map['date_from'] ?? 'DateFrom')] = trim((string) $inputs['DateFrom']);
        }
        if ($includeDateTo && trim((string) $inputs['DateTo']) !== '') {
            $params[(string) ($map['date_to'] ?? 'DateTo')] = trim((string) $inputs['DateTo']);
        }

        foreach ($staticParams as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $params[$key] = (string) $value;
        }

        return $params;
    }

    private function buildRunPayload(array $definition, array $inputs): array
    {
        return [
            'report_code' => (string) ($definition['ReportCode'] ?? ''),
            'report_name' => (string) ($definition['ReportName'] ?? ''),
            'ssrs_path' => (string) ($definition['SsrsPath'] ?? ''),
            'resolved_base_url' => $this->resolveSsrsBaseUrl($definition),
            'output_format' => $inputs['OutputFormatCode'],
            'inputs' => $inputs,
            'ssrs_params' => $this->buildSsrsParameterArray($definition, $inputs),
        ];
    }

    private function decodeJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function mapFormatForSsrs(string $formatCode): string
    {
        return match (strtoupper(trim($formatCode))) {
            'PDF' => 'PDF',
            'EXCEL' => 'EXCELOPENXML',
            'WORD' => 'WORDOPENXML',
            'CSV' => 'CSV',
            default => 'HTML5',
        };
    }

    private function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    private function statusOptions(): array
    {
        return [
            'launched' => 'Launched',
            'failed' => 'Failed',
            'blocked' => 'Blocked',
        ];
    }

    private function installScriptPath(): string
    {
        return 'backend-php/config/sql/create_ssrs_reporting_foundation_v1.sql';
    }
}
