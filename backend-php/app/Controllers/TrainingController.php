<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\TrainingManagementModel;
use App\Models\TrainingProgressModel;
use App\Models\UserModel;
use App\Shared\SessionHelper;
use App\Shared\TrainingScenarioCatalog;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/training_features.php';

final class TrainingController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
        'users' => ['auth' => true, 'permsAny' => ['USERS_EDIT', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'users-edit' => ['auth' => true, 'permsAny' => ['USERS_EDIT', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'usersEdit' => ['auth' => true, 'permsAny' => ['USERS_EDIT', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'scenarios' => ['auth' => true],
        'runner' => ['auth' => true],
        'summary' => ['auth' => true, 'permsAny' => ['USERS_VIEW', 'USERS_ADMIN']],
        'state' => ['auth' => true],
        'manage' => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
        'saveNote' => ['auth' => true],
        'reset' => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
        'stuck' => ['auth' => true],
    ];

    public function __construct()
    {
        parent::__construct();
        TrainingScenarioCatalog::setDb($this->db instanceof \PDO ? $this->db : null);
    }

    public function scenarios(): void
    {
        $allScenarios = array_values(array_filter(TrainingScenarioCatalog::all()));
        $userScenarioStates = [];
        $setupRequired = false;
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($model instanceof TrainingProgressModel && $model->supportsTrainingProgress() && $userId > 0) {
            $userScenarioStates = $model->listUserStates($userId);
        } else {
            $setupRequired = true;
        }

        $userAssignmentsByScenario = [];
        $managementModel = $this->trainingManagementModel();
        if ($managementModel instanceof TrainingManagementModel && $managementModel->supportsManagementTables() && $userId > 0) {
            foreach ($managementModel->listUserAssignments($userId) as $assignment) {
                $assignedScenarioCode = trim((string) ($assignment['EffectiveScenarioCode'] ?? ''));
                if ($assignedScenarioCode !== '' && !isset($userAssignmentsByScenario[$assignedScenarioCode])) {
                    $userAssignmentsByScenario[$assignedScenarioCode] = $assignment;
                }
            }
        }

        $moduleOptions = [];
        foreach ($allScenarios as $scenario) {
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }
        }
        ksort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $scenarios = array_values(array_filter($allScenarios, function (array $scenario) use ($filters, $userScenarioStates): bool {
            $scenarioId = trim((string) ($scenario['id'] ?? ''));
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            $title = trim((string) ($scenario['title'] ?? ''));
            $description = trim((string) ($scenario['description'] ?? ''));
            $audience = trim((string) ($scenario['audience'] ?? ''));
            $screenFamily = trim((string) ($scenario['screen_family'] ?? ''));
            $status = strtolower(trim((string) (($userScenarioStates[$scenarioId]['Status'] ?? 'not_started'))));

            if ($filters['module'] !== '' && strcasecmp($moduleName, $filters['module']) !== 0) {
                return false;
            }

            if ($filters['status'] !== '' && $status !== strtolower($filters['status'])) {
                return false;
            }

            if ($filters['q'] !== '') {
                $needle = function_exists('mb_strtolower')
                    ? mb_strtolower($filters['q'], 'UTF-8')
                    : strtolower($filters['q']);
                $haystack = implode(' ', [$title, $description, $audience, $moduleName, $screenFamily, $scenarioId]);
                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower($haystack, 'UTF-8')
                    : strtolower($haystack);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        }));

        usort($scenarios, static function (array $left, array $right): int {
            $leftModule = trim((string) ($left['module'] ?? ''));
            $rightModule = trim((string) ($right['module'] ?? ''));
            $moduleCompare = strcasecmp($leftModule, $rightModule);
            if ($moduleCompare !== 0) {
                return $moduleCompare;
            }

            $leftTitle = trim((string) ($left['title'] ?? $left['id'] ?? ''));
            $rightTitle = trim((string) ($right['title'] ?? $right['id'] ?? ''));
            return strcasecmp($leftTitle, $rightTitle);
        });

        $this->render('training/TrainingScenarios', [
            'title' => __t('training_scenarios_title'),
            'scenarios' => $scenarios,
            'userScenarioStates' => $userScenarioStates,
            'userAssignmentsByScenario' => $userAssignmentsByScenario,
            'setupRequired' => $setupRequired,
            'filters' => $filters,
            'moduleOptions' => $moduleOptions,
            'createTableScript' => 'backend-php/config/sql/create_tblTrainingProgress.sql',
            'trainingGuide' => $this->buildTrainingGuideForRoute('training/scenarios'),
            'trainingEnabled' => training_features_enabled($this->db),
        ]);
    }

    public function runner(): void
    {
        $requestedScenarioId = trim((string) ($_GET['scenario_id'] ?? ''));
        if ($requestedScenarioId === '') {
            $requestedScenarioId = trim((string) SessionHelper::get('training.requested_scenario_id', ''));
        }
        if ($requestedScenarioId === '') {
            $requestedScenarioId = $this->resolveScenarioId('');
        }

        $this->renderScenarioRunner($requestedScenarioId);
    }

    public function users(): void
    {
        $this->renderScenarioRunner(TrainingScenarioCatalog::USERS_CREATE_DEMO);
    }

    public function usersEdit(): void
    {
        $this->renderScenarioRunner(TrainingScenarioCatalog::USERS_EDIT_RECORD);
    }

    public function summary(): void
    {
        $this->ensureTrainingEnabled();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'scenario_code' => trim((string) ($_GET['scenario_code'] ?? '')),
        ];

        $rows = [];
        $setupRequired = false;
        $scenarioOptions = [];

        $model = $this->trainingProgressModel();
        if ($model instanceof TrainingProgressModel && $model->supportsTrainingProgress()) {
            $rows = $model->listSummaries($filters);
            foreach (TrainingScenarioCatalog::all() as $scenarioId => $scenario) {
                $scenarioOptions[$scenarioId] = (string) ($scenario['title'] ?? $scenarioId);
            }
        } else {
            $setupRequired = true;
        }

        $this->render('training/TrainingSummary', [
            'title' => __t('training_summary_title'),
            'rows' => $rows,
            'filters' => $filters,
            'setupRequired' => $setupRequired,
            'scenarioOptions' => $scenarioOptions,
            'createTableScript' => 'backend-php/config/sql/create_tblTrainingProgress.sql',
            'canManageTraining' => $this->canManageTraining(),
        ]);
    }

    public function reset(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $model = $this->trainingProgressModel();
        if (!$model instanceof TrainingProgressModel || !$model->supportsTrainingProgress()) {
            $this->flashError('Training progress storage is not available.');
            header('Location: index.php?route=training/summary');
            exit;
        }

        $action = trim((string) ($_POST['reset_action'] ?? ''));
        $rowsAffected = 0;
        $auditDetails = [];

        if ($action === 'row') {
            $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
            $scenarioCode = trim((string) ($_POST['target_scenario_code'] ?? ''));
            $rowsAffected = $model->resetScenarioForUser($targetUserId, $scenarioCode);
            $auditDetails = [
                'Mode' => 'row',
                'TargetUserID' => $targetUserId,
                'ScenarioCode' => $scenarioCode,
                'RowsAffected' => $rowsAffected,
            ];

            if ($rowsAffected > 0) {
                $this->flashSuccess('Training progress reset for the selected user scenario.');
            } else {
                $this->flashError('No matching training progress was found to reset.');
            }

            $this->clearTrainingSessionIfMatched($targetUserId, $scenarioCode);
            $this->clearScenarioNotes($scenarioCode);
        } elseif ($action === 'all') {
            $rowsAffected = $model->resetAll();
            $auditDetails = [
                'Mode' => 'all',
                'RowsAffected' => $rowsAffected,
            ];

            $this->flashSuccess('All training progress records have been reset.');
            SessionHelper::forget('training.active');
            SessionHelper::forget('training.requested_scenario_id');
            SessionHelper::forget('training.step_notes');
        } else {
            $this->flashError('Unknown reset action.');
            header('Location: ' . $this->buildSummaryReturnUrl());
            exit;
        }

        $this->auditTrainingReset($action, $auditDetails);

        header('Location: ' . $this->buildSummaryReturnUrl());
        exit;
    }

    public function state(): void
    {
        $this->ensureTrainingEnabled();
        $scenarioId = $this->resolveScenarioId((string) ($_GET['scenario_id'] ?? ''));
        $scenario = TrainingScenarioCatalog::get($scenarioId);
        $state = $this->getTrainingState($scenarioId);
        $currentStep = $state !== null ? TrainingScenarioCatalog::getStep($state) : null;

        $this->json([
            'ok' => true,
            'scenario' => $scenario,
            'state' => $state,
            'step' => $currentStep,
            'openTargetHref' => $this->buildScenarioStepUrl($currentStep ?? (($scenario['steps'] ?? [])[0] ?? null), $scenarioId),
        ]);
    }

    public function start(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $startMode = strtolower(trim((string) ($_POST['start_mode'] ?? 'beginning')));
        if ($startMode !== 'current') {
            $this->clearScenarioNotes($scenarioId);
        }
        $state = $startMode === 'current'
            ? $this->resumeTrainingState($scenarioId)
            : TrainingScenarioCatalog::makeState($scenarioId, $this->buildScenarioContext($scenarioId));
        if ($state === null) {
            $this->flashError('Training scenario not found.');
            header('Location: index.php?route=training/scenarios');
            exit;
        }

        SessionHelper::set('training.active', $state);
        SessionHelper::set('training.requested_scenario_id', $scenarioId);
        $state = $startMode === 'current'
            ? $this->persistTrainingResume($state)
            : $this->persistTrainingStart($state);
        SessionHelper::set('training.active', $state);

        $currentStep = TrainingScenarioCatalog::getStep($state);
        header('Location: ' . $this->buildScenarioStepUrl($currentStep, $scenarioId));
        exit;
    }

    public function stop(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $state = $this->getTrainingState(trim((string) ($_POST['scenario_id'] ?? '')));
        $this->persistTrainingStop($state);
        SessionHelper::forget('training.active');
        SessionHelper::forget('training.requested_scenario_id');

        $return = trim((string) ($_POST['return'] ?? 'index.php?route=training/scenarios'));
        if (is_array($state) && (string) ($state['status'] ?? '') === 'completed') {
            $return = 'index.php?route=training/scenarios';
        }
        header('Location: ' . $return);
        exit;
    }

    public function complete(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->json(['ok' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $state = $this->getTrainingState(trim((string) ($_POST['scenario_id'] ?? '')));
        if ($state === null) {
            $this->json(['ok' => false, 'message' => 'No active training scenario.'], 400);
            return;
        }

        $stepNumber = (int) ($_POST['step_number'] ?? 0);
        if ($stepNumber <= 0 || $stepNumber !== (int) ($state['current_step'] ?? 0)) {
            $this->json(['ok' => false, 'message' => 'Step out of sync.'], 409);
            return;
        }

        $advancedState = TrainingScenarioCatalog::advanceState($state, $stepNumber);
        if ($advancedState === null) {
            $this->json(['ok' => false, 'message' => 'Step could not be advanced.'], 409);
            return;
        }

        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistTrainingState($advancedState);
        if ((string) ($advancedState['status'] ?? '') === 'completed') {
            $managementModel = $this->trainingManagementModel();
            if ($managementModel instanceof TrainingManagementModel) {
                $userId = (int) SessionHelper::get('auth.user_id', 0);
                $managementModel->markAssignmentsCompleted($userId, (string) ($advancedState['scenario_id'] ?? ''), $userId);
            }
        }

        $nextStep = ($advancedState['status'] ?? '') === 'completed' ? null : TrainingScenarioCatalog::getStep($advancedState);
        $this->json([
            'ok' => true,
            'completed' => ($advancedState['status'] ?? '') === 'completed',
            'state' => $advancedState,
            'step' => $nextStep,
        ]);
    }

    public function manage(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $action = strtolower(trim((string) ($_POST['manage_action'] ?? '')));
        $return = trim((string) ($_POST['return'] ?? TrainingScenarioCatalog::startRoute($scenarioId)));
        $state = $this->getTrainingState($scenarioId);
        if ($state === null) {
            $this->flashError('Training scenario state was not found.');
            header('Location: ' . $return);
            exit;
        }

        $updatedState = null;
        switch ($action) {
            case 'skip':
                $stepNumber = (int) ($state['current_step'] ?? 0);
                $updatedState = $stepNumber > 0 ? TrainingScenarioCatalog::advanceState($state, $stepNumber) : null;
                break;
            case 'jump':
                $targetStep = (int) ($_POST['target_step'] ?? 1);
                $updatedState = $this->forceScenarioStep($state, $targetStep);
                break;
            case 'mark_complete':
                $updatedState = $this->markScenarioCompleted($state);
                break;
            case 'reopen':
                $updatedState = $this->reopenScenario($state);
                break;
            case 'reset_current':
                $this->resetCurrentScenario($scenarioId);
                $this->flashSuccess('The current training scenario has been reset.');
                header('Location: ' . TrainingScenarioCatalog::startRoute($scenarioId));
                exit;
        }

        if ($updatedState === null) {
            $this->flashError('The requested training action could not be completed.');
            header('Location: ' . $return);
            exit;
        }

        SessionHelper::set('training.active', $updatedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($updatedState['scenario_id'] ?? $scenarioId));
        $this->persistTrainingState($updatedState);
        $this->flashSuccess(__t('training_scenario_updated'));
        header('Location: ' . $return);
        exit;
    }

    public function saveNote(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $stepNumber = max(1, (int) ($_POST['step_number'] ?? 1));
        $note = trim((string) ($_POST['step_note'] ?? ''));
        $this->saveScenarioStepNote($scenarioId, $stepNumber, $note);

        $return = trim((string) ($_POST['return'] ?? TrainingScenarioCatalog::startRoute($scenarioId)));
        $this->flashSuccess('Training note saved.');
        header('Location: ' . $return);
        exit;
    }

    public function stuck(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->json(['ok' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $state = $this->getTrainingState(trim((string) ($_POST['scenario_id'] ?? '')));
        if ($state === null) {
            $this->json(['ok' => false, 'message' => 'No active training scenario.'], 400);
            return;
        }

        $step = TrainingScenarioCatalog::getStep($state);
        if (!is_array($step)) {
            $this->json(['ok' => false, 'message' => 'No active training step.'], 400);
            return;
        }

        $model = $this->trainingManagementModel();
        if ($model === null || !$model->supportsManagementTables()) {
            $this->json(['ok' => true, 'recorded' => false, 'message' => 'Training support queue is not installed.']);
            return;
        }

        $model->recordStuckEvent((int) SessionHelper::get('auth.user_id', 0), [
            'ScenarioCode' => (string) ($state['scenario_id'] ?? ''),
            'StepNo' => (int) ($step['number'] ?? $state['current_step'] ?? 0),
            'Route' => trim((string) ($step['route'] ?? '')),
            'TargetElementID' => trim((string) ($step['target'] ?? '')),
            'Message' => trim((string) ($_POST['message'] ?? '')),
        ]);

        $this->json(['ok' => true, 'recorded' => true]);
    }

    private function getTrainingState(?string $scenarioId = null): ?array
    {
        $scenarioId = $this->resolveScenarioId($scenarioId ?? '');
        $state = SessionHelper::get('training.active');
        if (is_array($state) && (string) ($state['scenario_id'] ?? '') === $scenarioId) {
            return $state;
        }

        $persisted = $this->loadPersistedTrainingState($scenarioId);
        if ($persisted !== null) {
            SessionHelper::set('training.active', $persisted);
            return $persisted;
        }

        return null;
    }

    private function loadPersistedTrainingState(string $scenarioId): ?array
    {
        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($model === null || $userId <= 0) {
            return null;
        }

        return $model->loadState($userId, $scenarioId);
    }

    private function resumeTrainingState(string $scenarioId): ?array
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '' || TrainingScenarioCatalog::get($scenarioId) === null) {
            return null;
        }

        $state = $this->getTrainingState($scenarioId);
        if ($state !== null) {
            return $state;
        }

        return TrainingScenarioCatalog::makeState($scenarioId, $this->buildScenarioContext($scenarioId));
    }

    private function persistTrainingStart(array $state): array
    {
        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $updatedBy = $userId;
        if ($model === null || $userId <= 0) {
            return $state;
        }

        return $model->startScenario($userId, $state, $updatedBy);
    }

    private function persistTrainingResume(array $state): array
    {
        $previousStatus = (string) ($state['status'] ?? '');
        $state['status'] = 'active';
        $state['stopped_at'] = null;
        if ($previousStatus === 'completed' && (int) ($state['current_step'] ?? 0) >= (int) ($state['total_steps'] ?? 0)) {
            $state['current_step'] = max(1, (int) ($state['total_steps'] ?? 1));
        }
        $this->persistTrainingState($state);
        return $state;
    }

    private function persistTrainingState(array $state): void
    {
        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($model === null || $userId <= 0) {
            return;
        }

        $model->saveState($userId, $state, $userId);
    }

    private function persistTrainingStop(?array $state): void
    {
        if (is_array($state) && (string) ($state['status'] ?? '') === 'completed') {
            return;
        }

        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($model === null || $userId <= 0) {
            return;
        }

        $scenarioCode = (string) (($state['scenario_id'] ?? '') ?: TrainingScenarioCatalog::USERS_CREATE_DEMO);
        $model->stopScenario($userId, $scenarioCode, $userId);
    }

    private function trainingProgressModel(): ?TrainingProgressModel
    {
        if (!$this->db instanceof \PDO) {
            return null;
        }

        return new TrainingProgressModel($this->db);
    }

    private function trainingManagementModel(): ?TrainingManagementModel
    {
        if (!$this->db instanceof \PDO) {
            return null;
        }

        return new TrainingManagementModel($this->db);
    }

    private function renderScenarioRunner(string $scenarioId): void
    {
        $this->ensureTrainingEnabled();
        $scenario = TrainingScenarioCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->flashError('Training scenario not found.');
            header('Location: index.php?route=training/scenarios');
            exit;
        }

        $state = $this->getTrainingState($scenarioId);
        $currentStep = $state !== null ? TrainingScenarioCatalog::getStep($state) : null;
        $scenarioNotes = $this->loadScenarioNotes($scenarioId);
        $currentStepNumber = (int) ($state['current_step'] ?? 0);
        $currentStepNote = trim((string) ($scenarioNotes[$currentStepNumber] ?? ''));
        $nextScenarioId = trim((string) ($scenario['next_scenario_id'] ?? ''));
        $nextScenario = $nextScenarioId !== '' ? TrainingScenarioCatalog::get($nextScenarioId) : null;

        $this->render('training/TrainingRunner', [
            'title' => ((string) ($scenario['title'] ?? __t('training_scenario_title_default'))) . ' ' . __t('training_scenario_suffix'),
            'scenario' => $scenario,
            'trainingState' => $state,
            'currentStep' => $currentStep,
            'openTargetHref' => $this->buildScenarioStepUrl($currentStep ?? (($scenario['steps'] ?? [])[0] ?? null), $scenarioId),
            'canManageTraining' => $this->canManageTraining(),
            'scenarioPrerequisites' => $this->buildScenarioPrerequisites($scenarioId),
            'nextScenario' => $nextScenario,
            'currentStepNote' => $currentStepNote,
            'stepNotes' => $scenarioNotes,
        ]);
    }

    private function buildTrainingGuideForRoute(string $route): ?array
    {
        if (!training_features_enabled($this->db)) {
            return null;
        }

        $requestedScenarioId = trim((string) ($_GET['training_scenario_id'] ?? $_GET['scenario_id'] ?? ''));
        if ($requestedScenarioId === '') {
            return null;
        }

        $state = $this->getTrainingState($requestedScenarioId);
        if ($state === null) {
            return null;
        }

        $scenarioId = (string) ($state['scenario_id'] ?? '');
        $scenario = TrainingScenarioCatalog::get($scenarioId);
        if ($scenario === null) {
            return null;
        }

        $step = TrainingScenarioCatalog::getStep($state);
        $isCompleted = (string) ($state['status'] ?? '') === 'completed';
        if (!$isCompleted) {
            $stepRoute = trim((string) ($step['route'] ?? ''));
            if ($stepRoute !== $route) {
                return null;
            }
        }

        $sampleKey = (string) ($step['sample_key'] ?? '');
        $sampleValue = $sampleKey !== '' ? (string) ($state['samples'][$sampleKey] ?? '') : '';

        return [
            'scenario' => $scenario,
            'state' => $state,
            'step' => $step,
            'isCompleted' => $isCompleted,
            'sampleValue' => $sampleValue,
            'completeUrl' => 'index.php?route=training/complete',
            'stuckUrl' => 'index.php?route=training/stuck',
            'runnerUrl' => TrainingScenarioCatalog::startRoute($scenarioId),
            'stopUrl' => 'index.php?route=training/stop',
            'csrf' => csrf_token(),
        ];
    }

    private function resolveScenarioId(string $scenarioId): string
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId !== '' && TrainingScenarioCatalog::get($scenarioId) !== null) {
            return $scenarioId;
        }

        $active = SessionHelper::get('training.active');
        if (is_array($active)) {
            $activeScenarioId = trim((string) ($active['scenario_id'] ?? ''));
            if ($activeScenarioId !== '' && TrainingScenarioCatalog::get($activeScenarioId) !== null) {
                return $activeScenarioId;
            }
        }

        return TrainingScenarioCatalog::USERS_CREATE_DEMO;
    }

    private function buildScenarioContext(string $scenarioId): array
    {
        if ($scenarioId !== TrainingScenarioCatalog::USERS_EDIT_RECORD) {
            return [];
        }

        return [
            'target_user_id' => (int) SessionHelper::get('auth.user_id', 0),
            'target_username' => (string) SessionHelper::get('auth.username', ''),
        ];
    }

    private function buildScenarioStepUrl(?array $step, string $scenarioId): string
    {
        $url = TrainingScenarioCatalog::routeForStep($step);
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'training_scenario_id=' . rawurlencode($scenarioId);
    }

    private function buildScenarioPrerequisites(string $scenarioId): array
    {
        $scenario = TrainingScenarioCatalog::get($scenarioId);
        $labels = array_values(is_array($scenario['prerequisites'] ?? null) ? $scenario['prerequisites'] : []);
        $checks = [];
        foreach ($labels as $label) {
            $ok = true;
            if (str_contains($label, 'Training features')) {
                $ok = training_features_enabled($this->db);
            } elseif (str_contains($label, 'Users administration')) {
                $ok = $this->hasAnyPermission(['USERS_EDIT', 'USERS_ADMIN']);
            } elseif (str_contains($label, 'target user record')) {
                $ok = $this->targetUserExists();
            }
            $checks[] = ['label' => $label, 'ok' => $ok];
        }
        return $checks;
    }

    private function hasAnyPermission(array $required): bool
    {
        $perms = SessionHelper::get('auth.perms', []);
        if (!is_array($perms)) {
            return false;
        }
        foreach ($required as $perm) {
            if (in_array($perm, $perms, true)) {
                return true;
            }
        }
        return false;
    }

    private function targetUserExists(): bool
    {
        if (!$this->db instanceof \PDO) {
            return false;
        }
        $targetUserId = (int) SessionHelper::get('auth.user_id', 0);
        if ($targetUserId <= 0) {
            return false;
        }
        $userModel = new UserModel($this->db);
        return is_array($userModel->find($targetUserId));
    }

    private function loadScenarioNotes(string $scenarioId): array
    {
        $notes = SessionHelper::get('training.step_notes', []);
        if (!is_array($notes)) {
            return [];
        }
        $scenarioNotes = $notes[$scenarioId] ?? [];
        return is_array($scenarioNotes) ? $scenarioNotes : [];
    }

    private function saveScenarioStepNote(string $scenarioId, int $stepNumber, string $note): void
    {
        if ($scenarioId === '' || $stepNumber <= 0) {
            return;
        }
        $allNotes = SessionHelper::get('training.step_notes', []);
        if (!is_array($allNotes)) {
            $allNotes = [];
        }
        if (!isset($allNotes[$scenarioId]) || !is_array($allNotes[$scenarioId])) {
            $allNotes[$scenarioId] = [];
        }
        if ($note === '') {
            unset($allNotes[$scenarioId][$stepNumber]);
        } else {
            $allNotes[$scenarioId][$stepNumber] = $note;
        }
        SessionHelper::set('training.step_notes', $allNotes);
    }

    private function forceScenarioStep(array $state, int $targetStep): ?array
    {
        $totalSteps = max(1, (int) ($state['total_steps'] ?? 1));
        $targetStep = min(max(1, $targetStep), $totalSteps);
        $state['status'] = 'active';
        $state['current_step'] = $targetStep;
        $state['completed_at'] = null;
        $state['stopped_at'] = null;
        return $state;
    }

    private function markScenarioCompleted(array $state): array
    {
        $state['status'] = 'completed';
        $state['current_step'] = max(1, (int) ($state['total_steps'] ?? 1));
        $state['completed_at'] = gmdate('Y-m-d H:i:s');
        $state['stopped_at'] = null;
        return $state;
    }

    private function reopenScenario(array $state): array
    {
        $state['status'] = 'active';
        $state['completed_at'] = null;
        $state['stopped_at'] = null;
        $currentStep = max(1, (int) ($state['current_step'] ?? 1));
        $totalSteps = max(1, (int) ($state['total_steps'] ?? 1));
        $state['current_step'] = min($currentStep, $totalSteps);
        return $state;
    }

    private function resetCurrentScenario(string $scenarioId): void
    {
        $model = $this->trainingProgressModel();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($model instanceof TrainingProgressModel && $userId > 0) {
            $model->resetScenarioForUser($userId, $scenarioId);
        }
        $notes = SessionHelper::get('training.step_notes', []);
        if (is_array($notes) && isset($notes[$scenarioId])) {
            unset($notes[$scenarioId]);
            SessionHelper::set('training.step_notes', $notes);
        }
        SessionHelper::forget('training.active');
        SessionHelper::forget('training.requested_scenario_id');
    }

    private function ensureTrainingEnabled(): void
    {
        if (training_features_enabled($this->db)) {
            return;
        }

        $this->flashError(__t('training_features_disabled'));
        header('Location: index.php?route=home/index');
        exit;
    }

    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function canManageTraining(): bool
    {
        $perms = SessionHelper::get('auth.perms', []);
        return is_array($perms) && in_array('USERS_ADMIN', $perms, true);
    }

    private function buildSummaryReturnUrl(): string
    {
        $q = trim((string) ($_POST['return_q'] ?? $_GET['q'] ?? ''));
        $status = trim((string) ($_POST['return_status'] ?? $_GET['status'] ?? ''));
        $scenarioCode = trim((string) ($_POST['return_scenario_code'] ?? $_GET['scenario_code'] ?? ''));

        $params = ['route' => 'training/summary'];
        if ($q !== '') {
            $params['q'] = $q;
        }
        if ($status !== '') {
            $params['status'] = $status;
        }
        if ($scenarioCode !== '') {
            $params['scenario_code'] = $scenarioCode;
        }

        return 'index.php?' . http_build_query($params);
    }

    private function clearTrainingSessionIfMatched(int $targetUserId, string $scenarioCode): void
    {
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);
        if ($targetUserId <= 0 || $currentUserId !== $targetUserId) {
            return;
        }

        $state = SessionHelper::get('training.active');
        if (!is_array($state)) {
            return;
        }

        if ((string) ($state['scenario_id'] ?? '') === $scenarioCode) {
            SessionHelper::forget('training.active');
            SessionHelper::forget('training.requested_scenario_id');
        }
    }

    private function clearScenarioNotes(string $scenarioId): void
    {
        if ($scenarioId === '') {
            return;
        }

        $notes = SessionHelper::get('training.step_notes', []);
        if (!is_array($notes) || !isset($notes[$scenarioId])) {
            return;
        }

        unset($notes[$scenarioId]);
        SessionHelper::set('training.step_notes', $notes);
    }

    private function auditTrainingReset(string $actionMode, array $details): void
    {
        if (!$this->db instanceof \PDO) {
            return;
        }

        $audit = new AuditModel($this->db);
        $audit->insert([
            'UserID' => SessionHelper::get('auth.user_id'),
            'Username' => SessionHelper::get('auth.username', 'guest'),
            'Action' => 'TRAINING_RESET',
            'Entity' => 'TRAINING_PROGRESS',
            'EntityKey' => $actionMode,
            'Details' => $details,
            'FiscalYearID' => SessionHelper::get('FiscalYearID'),
            'VersionID' => SessionHelper::get('VersionID'),
        ]);
    }
}
