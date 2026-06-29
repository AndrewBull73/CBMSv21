<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TrainingCatalogAdminModel;
use App\Models\TrainingManagementModel;
use App\Shared\Lang;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/training_features.php';

final class TrainingAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function scenarios(): void
    {
        $this->ensureTrainingEnabled();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = $this->model();
        $tableInstalled = $model->supportsScenarioCatalog();
        $this->render('trainingadmin/ScenarioList', [
            'title' => 'Training Catalogue',
            'rows' => $tableInstalled ? $model->listScenarios($filters) : [],
            'filters' => $filters,
            'tableInstalled' => $tableInstalled,
        ]);
    }

    public function scenarioForm(): void
    {
        $this->ensureTrainingEnabled();

        $scenarioCode = trim((string) ($_GET['scenario_code'] ?? ''));
        $model = $this->model();
        $tableInstalled = $model->supportsScenarioCatalog();
        $record = ($tableInstalled && $scenarioCode !== '') ? $model->getScenario($scenarioCode) : null;

        $this->render('trainingadmin/ScenarioForm', [
            'title' => $record ? 'Edit Training Scenario' : 'Create Training Scenario',
            'record' => $record,
            'scenarioOptions' => $tableInstalled ? $model->listScenarioOptions(false) : [],
            'tableInstalled' => $tableInstalled,
        ]);
    }

    public function saveScenario(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=training-admin/scenarios');
            return;
        }

        $payload = [
            'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
            'ScenarioTitle' => trim((string) ($_POST['ScenarioTitle'] ?? '')),
            'ScreenFamily' => trim((string) ($_POST['ScreenFamily'] ?? '')),
            'ModuleName' => trim((string) ($_POST['ModuleName'] ?? '')),
            'Audience' => trim((string) ($_POST['Audience'] ?? '')),
            'Difficulty' => trim((string) ($_POST['Difficulty'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'RunnerRoute' => trim((string) ($_POST['RunnerRoute'] ?? '')),
            'NextScenarioCode' => trim((string) ($_POST['NextScenarioCode'] ?? '')),
            'Prerequisites' => trim((string) ($_POST['Prerequisites'] ?? '')),
            'Samples' => trim((string) ($_POST['Samples'] ?? '')),
            'ActiveFlag' => ((int) ($_POST['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
        ];

        try {
            $this->ensureCatalogInstalled();
            $scenarioCode = $this->model()->saveScenario($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training scenario saved.');
            header('Location: index.php?route=training-admin/scenario-form&scenario_code=' . urlencode($scenarioCode));
            return;
        } catch (\Throwable $e) {
            $this->flashError('Training scenario save failed: ' . $e->getMessage());
            $returnCode = $payload['ScenarioCode'] !== '' ? '&scenario_code=' . urlencode($payload['ScenarioCode']) : '';
            header('Location: index.php?route=training-admin/scenario-form' . $returnCode);
            return;
        }
    }

    public function steps(): void
    {
        $this->ensureTrainingEnabled();

        $model = $this->model();
        $tableInstalled = $model->supportsScenarioCatalog();
        $scenarioCode = trim((string) ($_GET['scenario_code'] ?? ''));
        if ($tableInstalled && $scenarioCode === '') {
            $options = $model->listScenarioOptions(true);
            $scenarioCode = trim((string) ($options[0]['ScenarioCode'] ?? ''));
        }

        $this->render('trainingadmin/StepList', [
            'title' => 'Training Scenario Steps',
            'scenarioCode' => $scenarioCode,
            'scenario' => ($tableInstalled && $scenarioCode !== '') ? $model->getScenario($scenarioCode) : null,
            'rows' => ($tableInstalled && $scenarioCode !== '') ? $model->listSteps($scenarioCode) : [],
            'scenarioOptions' => $tableInstalled ? $model->listScenarioOptions(false) : [],
            'tableInstalled' => $tableInstalled,
        ]);
    }

    public function stepForm(): void
    {
        $this->ensureTrainingEnabled();

        $scenarioCode = trim((string) ($_GET['scenario_code'] ?? ''));
        $stepNo = (int) ($_GET['step_no'] ?? 0);
        $model = $this->model();
        $tableInstalled = $model->supportsScenarioCatalog();

        $record = ($tableInstalled && $scenarioCode !== '' && $stepNo > 0) ? $model->getStep($scenarioCode, $stepNo) : null;
        if ($scenarioCode === '' && is_array($record)) {
            $scenarioCode = (string) ($record['ScenarioCode'] ?? '');
        }

        $this->render('trainingadmin/StepForm', [
            'title' => $record ? 'Edit Training Step' : 'Create Training Step',
            'record' => $record,
            'scenarioCode' => $scenarioCode,
            'scenario' => ($tableInstalled && $scenarioCode !== '') ? $model->getScenario($scenarioCode) : null,
            'scenarioOptions' => $tableInstalled ? $model->listScenarioOptions(false) : [],
            'completionModes' => $this->completionModes(),
            'tableInstalled' => $tableInstalled,
            'stepSupport' => ($tableInstalled && $scenarioCode !== '' && $stepNo > 0)
                ? $this->managementModel()->getStepSupport($scenarioCode, $stepNo)
                : [],
            'managementInstalled' => $this->managementModel()->supportsManagementTables(),
        ]);
    }

    public function saveStep(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=training-admin/scenarios');
            return;
        }

        $payload = [
            'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
            'OldStepNo' => (int) ($_POST['OldStepNo'] ?? 0),
            'StepNo' => (int) ($_POST['StepNo'] ?? 0),
            'Route' => trim((string) ($_POST['Route'] ?? '')),
            'TargetElementID' => trim((string) ($_POST['TargetElementID'] ?? '')),
            'StepTitle' => trim((string) ($_POST['StepTitle'] ?? '')),
            'InstructionText' => trim((string) ($_POST['InstructionText'] ?? '')),
            'CompletionMode' => trim((string) ($_POST['CompletionMode'] ?? '')),
            'SampleKey' => trim((string) ($_POST['SampleKey'] ?? '')),
            'ExpectedUserSampleKey' => trim((string) ($_POST['ExpectedUserSampleKey'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
        ];

        try {
            $this->ensureCatalogInstalled();
            $stepNo = $this->model()->saveStep($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->managementModel()->saveStepSupport([
                'ScenarioCode' => $payload['ScenarioCode'],
                'StepNo' => $stepNo,
                'TrainerNote' => trim((string) ($_POST['TrainerNote'] ?? '')),
                'ExpectedOutcome' => trim((string) ($_POST['ExpectedOutcome'] ?? '')),
                'CommonIssues' => trim((string) ($_POST['CommonIssues'] ?? '')),
                'QuestionText' => trim((string) ($_POST['QuestionText'] ?? '')),
                'ExpectedAnswer' => trim((string) ($_POST['ExpectedAnswer'] ?? '')),
                'CheckpointRequired' => isset($_POST['CheckpointRequired']) ? 1 : 0,
                'CheckpointActive' => isset($_POST['CheckpointActive']) ? 1 : 0,
            ], (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training step saved.');
            header('Location: index.php?route=training-admin/step-form&scenario_code=' . urlencode($payload['ScenarioCode']) . '&step_no=' . $stepNo);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Training step save failed: ' . $e->getMessage());
            $qs = http_build_query([
                'route' => 'training-admin/step-form',
                'scenario_code' => $payload['ScenarioCode'],
                'step_no' => $payload['OldStepNo'] > 0 ? $payload['OldStepNo'] : $payload['StepNo'],
            ]);
            header('Location: index.php?' . $qs);
            return;
        }
    }

    public function archiveStep(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=training-admin/scenarios');
            return;
        }

        $scenarioCode = trim((string) ($_POST['ScenarioCode'] ?? ''));
        $stepNo = (int) ($_POST['StepNo'] ?? 0);
        try {
            $this->ensureCatalogInstalled();
            $this->model()->archiveStep($scenarioCode, $stepNo, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training step archived.');
        } catch (\Throwable $e) {
            $this->flashError('Training step archive failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=training-admin/steps&scenario_code=' . urlencode($scenarioCode));
    }

    public function translations(): void
    {
        $this->ensureTrainingEnabled();

        $model = $this->model();
        $tableInstalled = $model->supportsScenarioCatalog();
        $scenarioCode = trim((string) ($_GET['scenario_code'] ?? ''));
        $languageCode = trim((string) ($_GET['language_code'] ?? ''));

        $scenarioOptions = $tableInstalled ? $model->listScenarioOptions(false) : [];
        if ($tableInstalled && $scenarioCode === '') {
            $scenarioCode = trim((string) ($scenarioOptions[0]['ScenarioCode'] ?? ''));
        }

        $languageOptions = array_values(array_unique(array_filter(array_merge(
            Lang::availableLanguages(),
            ($tableInstalled && $scenarioCode !== '') ? $model->listTranslationLanguages($scenarioCode) : []
        ))));
        sort($languageOptions);
        if ($languageCode === '') {
            $languageCode = $languageOptions[0] ?? Lang::getActiveLang();
        }

        $this->render('trainingadmin/Translations', [
            'title' => 'Training Translations',
            'scenarioCode' => $scenarioCode,
            'languageCode' => $languageCode,
            'scenario' => ($tableInstalled && $scenarioCode !== '') ? $model->getScenario($scenarioCode) : null,
            'bundle' => ($tableInstalled && $scenarioCode !== '') ? $model->getTranslationsBundle($scenarioCode, $languageCode) : ['scenario' => [], 'steps' => [], 'stepTranslations' => [], 'samples' => [], 'sampleTranslations' => []],
            'scenarioOptions' => $scenarioOptions,
            'languageOptions' => $languageOptions,
            'tableInstalled' => $tableInstalled,
        ]);
    }

    public function saveTranslations(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=training-admin/scenarios');
            return;
        }

        $scenarioCode = trim((string) ($_POST['ScenarioCode'] ?? ''));
        $languageCode = trim((string) ($_POST['LanguageCode'] ?? ''));

        $payload = [
            'ScenarioTitle' => trim((string) ($_POST['ScenarioTitle'] ?? '')),
            'ModuleName' => trim((string) ($_POST['ModuleName'] ?? '')),
            'Audience' => trim((string) ($_POST['Audience'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'Prerequisites' => trim((string) ($_POST['Prerequisites'] ?? '')),
            'StepTitles' => is_array($_POST['StepTitles'] ?? null) ? $_POST['StepTitles'] : [],
            'StepInstructions' => is_array($_POST['StepInstructions'] ?? null) ? $_POST['StepInstructions'] : [],
            'SampleValues' => is_array($_POST['SampleValues'] ?? null) ? $_POST['SampleValues'] : [],
        ];

        try {
            $this->ensureCatalogInstalled();
            $this->model()->saveTranslationsBundle($scenarioCode, $languageCode, $payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training translations saved.');
        } catch (\Throwable $e) {
            $this->flashError('Training translations save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=training-admin/translations&scenario_code=' . urlencode($scenarioCode) . '&language_code=' . urlencode($languageCode));
    }

    public function operations(): void
    {
        $this->ensureTrainingEnabled();

        $catalog = $this->model();
        $management = $this->managementModel();
        $catalogInstalled = $catalog->supportsScenarioCatalog();
        $managementInstalled = $management->supportsManagementTables();
        $scenarioOptions = $catalogInstalled ? $catalog->listScenarioOptions(false) : [];

        $this->render('trainingadmin/Operations', [
            'title' => 'Training Operations',
            'catalogInstalled' => $catalogInstalled,
            'managementInstalled' => $managementInstalled,
            'paths' => $managementInstalled ? $management->listPaths() : [],
            'assignments' => $managementInstalled ? $management->listAssignments(['status' => 'assigned']) : [],
            'sessions' => $managementInstalled ? $management->listSessions() : [],
            'stuckEvents' => $managementInstalled ? $management->listStuckEvents('open') : [],
            'cleanupTags' => $managementInstalled ? $management->listCleanupTags() : [],
            'scenarioOptions' => $scenarioOptions,
        ]);
    }

    public function matrix(): void
    {
        $this->ensureTrainingEnabled();

        $documentPath = realpath(__DIR__ . '/../../../TRAINING_SCENARIO_MATRIX.md') ?: (__DIR__ . '/../../../TRAINING_SCENARIO_MATRIX.md');
        $matrix = $this->loadTrainingMatrix($documentPath);
        $filters = [
            'path' => trim((string) ($_GET['path'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];

        $scenarioRows = is_array($matrix['scenarios'] ?? null) ? $matrix['scenarios'] : [];
        $filteredScenarioRows = $this->filterTrainingMatrixRows($scenarioRows, $filters);
        $statusCounts = [];
        foreach ($scenarioRows as $row) {
            $status = strtoupper(trim((string) ($row['Status'] ?? '')));
            if ($status !== '') {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }

        $pathOptions = [];
        $statusOptions = [];
        foreach ($scenarioRows as $row) {
            $path = trim((string) ($row['Path'] ?? ''));
            $status = strtoupper(trim((string) ($row['Status'] ?? '')));
            if ($path !== '') {
                $pathOptions[$path] = $path;
            }
            if ($status !== '') {
                $statusOptions[$status] = $status;
            }
        }
        ksort($pathOptions);
        ksort($statusOptions);

        $this->render('trainingadmin/Matrix', [
            'title' => 'Training Matrix',
            'documentPath' => $documentPath,
            'documentModified' => is_file($documentPath) ? (int) filemtime($documentPath) : 0,
            'documentAvailable' => is_file($documentPath),
            'filters' => $filters,
            'pathOptions' => array_values($pathOptions),
            'statusOptions' => array_values($statusOptions),
            'paths' => is_array($matrix['paths'] ?? null) ? $matrix['paths'] : [],
            'roles' => is_array($matrix['roles'] ?? null) ? $matrix['roles'] : [],
            'statusValues' => is_array($matrix['statusValues'] ?? null) ? $matrix['statusValues'] : [],
            'scenarios' => $filteredScenarioRows,
            'summary' => [
                'paths' => count(is_array($matrix['paths'] ?? null) ? $matrix['paths'] : []),
                'scenarios' => count($scenarioRows),
                'displayed' => count($filteredScenarioRows),
                'implemented' => (int) ($statusCounts['IMPLEMENTED'] ?? 0),
                'planned' => (int) ($statusCounts['PLANNED'] ?? 0),
                'review' => (int) ($statusCounts['REVIEW'] ?? 0),
            ],
        ]);
    }

    public function savePath(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $this->ensureManagementInstalled();
            $pathCode = $this->managementModel()->savePath([
                'PathCode' => trim((string) ($_POST['PathCode'] ?? '')),
                'PathTitle' => trim((string) ($_POST['PathTitle'] ?? '')),
                'Audience' => trim((string) ($_POST['Audience'] ?? '')),
                'Description' => trim((string) ($_POST['Description'] ?? '')),
                'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'ScenarioCodes' => trim((string) ($_POST['ScenarioCodes'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training path saved.');
            header('Location: index.php?route=training-admin/operations&path_code=' . urlencode($pathCode));
            return;
        } catch (\Throwable $e) {
            $this->flashError('Training path save failed: ' . $e->getMessage());
            header('Location: index.php?route=training-admin/operations');
            return;
        }
    }

    public function saveAssignment(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $this->ensureManagementInstalled();
            $count = $this->managementModel()->saveAssignment([
                'UserIDs' => trim((string) ($_POST['UserIDs'] ?? '')),
                'PathCode' => trim((string) ($_POST['PathCode'] ?? '')),
                'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
                'DueDate' => trim((string) ($_POST['DueDate'] ?? '')),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess($count . ' training assignment' . ($count === 1 ? '' : 's') . ' created.');
        } catch (\Throwable $e) {
            $this->flashError('Training assignment failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=training-admin/operations');
    }

    public function saveSession(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $this->ensureManagementInstalled();
            $sessionId = $this->managementModel()->saveSession([
                'SessionCode' => trim((string) ($_POST['SessionCode'] ?? '')),
                'SessionTitle' => trim((string) ($_POST['SessionTitle'] ?? '')),
                'InstructorUserID' => (int) ($_POST['InstructorUserID'] ?? 0),
                'PathCode' => trim((string) ($_POST['PathCode'] ?? '')),
                'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
                'ScheduledAt' => trim((string) ($_POST['ScheduledAt'] ?? '')),
                'Status' => trim((string) ($_POST['Status'] ?? 'planned')),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserIDs' => trim((string) ($_POST['UserIDs'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training session saved.');
            header('Location: index.php?route=training-admin/session-dashboard&session_id=' . $sessionId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Training session save failed: ' . $e->getMessage());
            header('Location: index.php?route=training-admin/operations');
            return;
        }
    }

    public function sessionDashboard(): void
    {
        $this->ensureTrainingEnabled();
        $sessionId = (int) ($_GET['session_id'] ?? 0);
        $management = $this->managementModel();
        $managementInstalled = $management->supportsManagementTables();

        $dashboard = $managementInstalled
            ? $management->getSessionDashboard($sessionId)
            : ['session' => null, 'participants' => []];

        $this->render('trainingadmin/SessionDashboard', [
            'title' => 'Training Session Dashboard',
            'managementInstalled' => $managementInstalled,
            'sessionId' => $sessionId,
            'session' => $dashboard['session'] ?? null,
            'participants' => $dashboard['participants'] ?? [],
        ]);
    }

    public function saveEvidence(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $sessionId = (int) ($_POST['TrainingSessionID'] ?? 0);
        try {
            $this->ensureManagementInstalled();
            $this->managementModel()->saveEvidence([
                'TrainingSessionID' => $sessionId,
                'UserID' => (int) ($_POST['UserID'] ?? 0),
                'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
                'AttemptNo' => (int) ($_POST['AttemptNo'] ?? 0),
                'EvidenceType' => trim((string) ($_POST['EvidenceType'] ?? 'instructor_signoff')),
                'EvidenceNote' => trim((string) ($_POST['EvidenceNote'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Training evidence recorded.');
        } catch (\Throwable $e) {
            $this->flashError('Training evidence save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=training-admin/session-dashboard&session_id=' . $sessionId);
    }

    public function validation(): void
    {
        $this->ensureTrainingEnabled();
        $findings = [];
        $catalogInstalled = $this->model()->supportsScenarioCatalog();
        $managementInstalled = $this->managementModel()->supportsManagementTables();

        if ($catalogInstalled) {
            try {
                $routes = require __DIR__ . '/../../config/routes.php';
                $findings = $this->managementModel()->validateCatalog(is_array($routes) ? $routes : [], __DIR__ . '/../Views');
            } catch (\Throwable $e) {
                $findings[] = [
                    'ScenarioCode' => '',
                    'StepNo' => null,
                    'Severity' => 'error',
                    'Message' => 'Validation failed.',
                    'Detail' => $e->getMessage(),
                ];
            }
        }

        $this->render('trainingadmin/Validation', [
            'title' => 'Training Validation',
            'catalogInstalled' => $catalogInstalled,
            'managementInstalled' => $managementInstalled,
            'findings' => $findings,
        ]);
    }

    public function resolveStuck(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $this->ensureManagementInstalled();
            $this->managementModel()->resolveStuckEvent(
                (int) ($_POST['TrainingStuckEventID'] ?? 0),
                trim((string) ($_POST['ResolutionNote'] ?? '')),
                (int) SessionHelper::get('auth.user_id', 0)
            );
            $this->flashSuccess('Stuck event resolved.');
        } catch (\Throwable $e) {
            $this->flashError('Stuck event update failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=training-admin/operations');
    }

    private function model(): TrainingCatalogAdminModel
    {
        return new TrainingCatalogAdminModel($this->db);
    }

    private function managementModel(): TrainingManagementModel
    {
        return new TrainingManagementModel($this->db);
    }

    private function completionModes(): array
    {
        return [
            'navigation' => 'navigation',
            'field_nonempty' => 'field_nonempty',
            'field_email' => 'field_email',
            'field_prefilled' => 'field_prefilled',
            'field_matches_sample' => 'field_matches_sample',
            'checkbox_checked' => 'checkbox_checked',
            'manual_continue' => 'manual_continue',
            'submit_success' => 'submit_success',
            'click_target' => 'click_target',
        ];
    }

    private function loadTrainingMatrix(string $documentPath): array
    {
        if (!is_file($documentPath)) {
            return [
                'paths' => [],
                'roles' => [],
                'statusValues' => [],
                'scenarios' => [],
            ];
        }

        $content = (string) file_get_contents($documentPath);
        return [
            'paths' => $this->extractMarkdownTable($content, 'Training Path Order'),
            'roles' => $this->extractMarkdownTable($content, 'Role to Path Alignment'),
            'statusValues' => $this->extractMarkdownTable($content, 'Scenario Status Values'),
            'scenarios' => $this->extractMarkdownTable($content, 'Scenario Matrix'),
        ];
    }

    /**
     * Parses the first markdown table after a second-level heading.
     *
     * The training matrix markdown is intentionally simple, so this parser only
     * needs pipe tables without escaped pipes or multi-line cells.
     */
    private function extractMarkdownTable(string $content, string $heading): array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $inSection = false;
        $tableLines = [];

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if (preg_match('/^##\s+' . preg_quote($heading, '/') . '\s*$/', $trimmed) === 1) {
                $inSection = true;
                continue;
            }
            if (!$inSection) {
                continue;
            }
            if (str_starts_with($trimmed, '## ') && $tableLines !== []) {
                break;
            }
            if (str_starts_with($trimmed, '|')) {
                $tableLines[] = $trimmed;
                continue;
            }
            if ($tableLines !== []) {
                break;
            }
        }

        if (count($tableLines) < 3) {
            return [];
        }

        $headers = $this->parseMarkdownTableRow($tableLines[0]);
        $rows = [];
        foreach (array_slice($tableLines, 2) as $tableLine) {
            $cells = $this->parseMarkdownTableRow($tableLine);
            if ($cells === []) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $cells[$index] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function parseMarkdownTableRow(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        if ($line === '') {
            return [];
        }

        return array_map(
            static fn (string $cell): string => trim($cell),
            explode('|', $line)
        );
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @param array{path:string,status:string,q:string} $filters
     * @return array<int,array<string,string>>
     */
    private function filterTrainingMatrixRows(array $rows, array $filters): array
    {
        $pathFilter = trim((string) ($filters['path'] ?? ''));
        $statusFilter = strtoupper(trim((string) ($filters['status'] ?? '')));
        $search = strtolower(trim((string) ($filters['q'] ?? '')));

        return array_values(array_filter($rows, static function (array $row) use ($pathFilter, $statusFilter, $search): bool {
            if ($pathFilter !== '' && strcasecmp((string) ($row['Path'] ?? ''), $pathFilter) !== 0) {
                return false;
            }
            if ($statusFilter !== '' && strcasecmp((string) ($row['Status'] ?? ''), $statusFilter) !== 0) {
                return false;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_map(static fn ($value): string => (string) $value, $row)));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function ensureCatalogInstalled(): void
    {
        if (!$this->model()->supportsScenarioCatalog()) {
            throw new \RuntimeException('Training catalogue tables are not installed.');
        }
    }

    private function ensureManagementInstalled(): void
    {
        if (!$this->managementModel()->supportsManagementTables()) {
            throw new \RuntimeException('Training management tables are not installed.');
        }
    }

    private function ensureTrainingEnabled(): void
    {
        if (!training_features_enabled($this->db)) {
            $this->flashError(__t('training_features_disabled'));
            header('Location: index.php?route=home/index');
            exit;
        }
    }
}
