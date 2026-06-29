<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScreenTestRunModel;
use App\Shared\ScreenTestCatalog;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/screen_test_capture.php';

final class ScreenTestsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
        'scenarios' => ['auth' => true],
        'runner' => ['auth' => true],
        'start' => ['auth' => true],
        'saveResult' => ['auth' => true],
        'summary' => ['auth' => true],
        'captureScreenshot' => ['auth' => true],
        'uploadAttachment' => ['auth' => true],
        'viewAttachment' => ['auth' => true],
        'downloadAttachment' => ['auth' => true],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureScreenTestingEnabled();
    }

    public function scenarios(): void
    {
        $allScenarios = array_values(array_filter(ScreenTestCatalog::all()));
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'result' => trim((string) ($_GET['result'] ?? '')),
        ];

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $allRuns = $this->listRuns($userId, [], false);
        $latestRuns = $this->latestRunsByScenario($allRuns);
        $activeRuns = $this->activeRuns();

        $moduleOptions = [];
        foreach ($allScenarios as $scenario) {
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }
        }
        ksort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $scenarios = array_values(array_filter($allScenarios, function (array $scenario) use ($filters, $latestRuns, $activeRuns): bool {
            $scenarioId = trim((string) ($scenario['id'] ?? ''));
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            $title = trim((string) ($scenario['title'] ?? ''));
            $description = trim((string) ($scenario['description'] ?? ''));
            $screenFamily = trim((string) ($scenario['screen_family'] ?? ''));
            $audience = trim((string) ($scenario['audience'] ?? ''));
            $resultState = $this->scenarioCardResult($scenarioId, $latestRuns[$scenarioId] ?? null, $activeRuns[$scenarioId] ?? null);

            if ($filters['module'] !== '' && strcasecmp($moduleName, $filters['module']) !== 0) {
                return false;
            }
            if ($filters['result'] !== '' && $resultState !== strtolower($filters['result'])) {
                return false;
            }
            if ($filters['q'] !== '') {
                $needle = function_exists('mb_strtolower')
                    ? mb_strtolower($filters['q'], 'UTF-8')
                    : strtolower($filters['q']);
                $haystack = implode(' ', [$scenarioId, $moduleName, $title, $description, $screenFamily, $audience]);
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
            $moduleCompare = strcasecmp((string) ($left['module'] ?? ''), (string) ($right['module'] ?? ''));
            if ($moduleCompare !== 0) {
                return $moduleCompare;
            }

            return strcasecmp(
                (string) ($left['title'] ?? $left['id'] ?? ''),
                (string) ($right['title'] ?? $right['id'] ?? '')
            );
        });

        $this->render('screentests/Scenarios', [
            'title' => __t('screen_tests_title'),
            'scenarios' => $scenarios,
            'filters' => $filters,
            'moduleOptions' => $moduleOptions,
            'latestRuns' => $latestRuns,
            'activeRuns' => $activeRuns,
            'storageReady' => $this->persistentStorageAvailable(),
            'createTableScript' => 'backend-php/config/sql/create_tblScreenTestRuns.sql',
        ]);
    }

    public function runner(): void
    {
        $scenarioId = trim((string) ($_GET['scenario_id'] ?? ''));
        if ($scenarioId === '') {
            $scenarioId = trim((string) SessionHelper::get('screen_tests.requested_scenario_id', ''));
        }

        $scenario = ScreenTestCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->flashError('screen_tests_scenario_not_found');
            header('Location: index.php?route=screen-tests/scenarios');
            exit;
        }

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        SessionHelper::set('screen_tests.requested_scenario_id', $scenarioId);
        $activeRun = $this->activeRuns()[$scenarioId] ?? null;
        $previewRun = is_array($activeRun) ? $activeRun : ScreenTestCatalog::makeRun($scenarioId, $this->buildRunContext());
        $testData = is_array($previewRun) ? (array) ($previewRun['test_data'] ?? []) : [];
        $runContext = is_array($previewRun) ? (array) ($previewRun['context'] ?? []) : $this->buildRunContext();
        $recentRuns = array_values(array_filter($this->listRuns($userId, [], false), static function (array $row) use ($scenarioId): bool {
            return (string) ($row['ScenarioCode'] ?? '') === $scenarioId;
        }));
        $recentRuns = array_slice($recentRuns, 0, 5);

        $this->render('screentests/Runner', [
            'title' => ((string) ($scenario['title'] ?? __t('screen_tests_title'))) . ' - ' . __t('screen_tests_title'),
            'scenario' => $scenario,
            'activeRun' => $activeRun,
            'previewRun' => $previewRun,
            'testData' => $testData,
            'runContext' => $runContext,
            'targetUrl' => $this->targetUrlForScenario($scenario, $runContext),
            'recentRuns' => $recentRuns,
            'resolvedVerificationQueries' => ScreenTestCatalog::resolveTemplateList(
                is_array($scenario['verification_queries'] ?? null) ? $scenario['verification_queries'] : [],
                $runContext,
                $testData
            ),
            'storageReady' => $this->persistentStorageAvailable(),
            'createTableScript' => 'backend-php/config/sql/create_tblScreenTestRuns.sql',
            'captureEnabled' => $this->screenCaptureFeatureEnabled(),
            'captureStorageReady' => $this->screenCaptureStorageAvailable(),
            'pendingAttachments' => $this->pendingAttachmentsFromRun($activeRun),
        ]);
    }

    public function start(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $scenario = ScreenTestCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->flashError('screen_tests_scenario_not_found');
            header('Location: index.php?route=screen-tests/scenarios');
            exit;
        }

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $run = ScreenTestCatalog::makeRun($scenarioId, $this->buildRunContext());
        if (!is_array($run)) {
            $this->flashError('screen_tests_scenario_not_found');
            header('Location: index.php?route=screen-tests/scenarios');
            exit;
        }

        $run['attempt_no'] = $this->nextAttemptNo($userId, $scenarioId);
        $run['tester_user_id'] = $userId;
        $run['tester_username'] = (string) SessionHelper::get('auth.username', '');
        $this->cleanupPendingAttachmentsFromRun($this->activeRuns()[$scenarioId] ?? null);
        $run['pending_attachments'] = [];

        $activeRuns = $this->activeRuns();
        $activeRuns[$scenarioId] = $run;
        SessionHelper::set('screen_tests.active_runs', $activeRuns);
        SessionHelper::set('screen_tests.requested_scenario_id', $scenarioId);

        $this->flashSuccess('screen_tests_run_started');
        header('Location: index.php?route=screen-tests/runner&scenario_id=' . rawurlencode($scenarioId));
        exit;
    }

    public function saveResult(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $scenario = ScreenTestCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->flashError('screen_tests_scenario_not_found');
            header('Location: index.php?route=screen-tests/scenarios');
            exit;
        }

        $allowedResults = ['passed', 'failed', 'blocked'];
        $allowedVerification = ['not_run', 'manual_pass', 'manual_fail'];
        $runResult = strtolower(trim((string) ($_POST['run_result'] ?? '')));
        $verificationStatus = strtolower(trim((string) ($_POST['verification_status'] ?? '')));
        if (!in_array($runResult, $allowedResults, true)) {
            $runResult = 'blocked';
        }
        if (!in_array($verificationStatus, $allowedVerification, true)) {
            $verificationStatus = 'not_run';
        }

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $activeRun = $this->activeRuns()[$scenarioId] ?? null;
        if (!is_array($activeRun)) {
            $this->flashError('screen_tests_active_run_required');
            header('Location: index.php?route=screen-tests/runner&scenario_id=' . rawurlencode($scenarioId));
            exit;
        }

        $runContext = is_array($activeRun) ? (array) ($activeRun['context'] ?? []) : $this->buildRunContext();
        $testData = is_array($activeRun) ? (array) ($activeRun['test_data'] ?? []) : ScreenTestCatalog::previewTestData($scenarioId, $runContext);
        $startedAt = trim((string) ($activeRun['started_at'] ?? '')) ?: gmdate('Y-m-d H:i:s');
        $attemptNo = max(1, (int) ($activeRun['attempt_no'] ?? 0));

        $completedAt = gmdate('Y-m-d H:i:s');
        $startedTs = strtotime($startedAt . ' UTC') ?: strtotime($startedAt) ?: time();
        $completedTs = strtotime($completedAt . ' UTC') ?: time();
        $durationSeconds = max(0, $completedTs - $startedTs);

        $row = [
            'user_id' => $userId,
            'scenario_code' => $scenarioId,
            'scenario_title' => (string) ($scenario['title'] ?? $scenarioId),
            'module_name' => (string) ($scenario['module'] ?? ''),
            'screen_family' => (string) ($scenario['screen_family'] ?? ''),
            'target_route' => (string) ($scenario['target_route'] ?? ''),
            'run_result' => $runResult,
            'verification_status' => $verificationStatus,
            'attempt_no' => $attemptNo,
            'fiscal_year_id' => (int) ($runContext['fy'] ?? 0),
            'version_id' => (int) ($runContext['ver'] ?? 0),
            'dataobject_code' => trim((string) ($runContext['scope_dataobject_code'] ?? '')),
            'context' => $runContext,
            'test_data' => $testData,
            'outcome_summary' => trim((string) ($_POST['outcome_summary'] ?? '')),
            'defect_reference' => trim((string) ($_POST['defect_reference'] ?? '')),
            'tester_notes' => trim((string) ($_POST['tester_notes'] ?? '')),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'duration_seconds' => $durationSeconds,
            'created_by' => $userId,
            'updated_by' => $userId,
            'username' => (string) SessionHelper::get('auth.username', ''),
            'display_name' => (string) SessionHelper::get('auth.username', ''),
        ];

        $model = $this->screenTestRunModel();
        $savedRunId = 0;
        if ($model instanceof ScreenTestRunModel && $model->supportsScreenTestRuns()) {
            $savedRunId = $model->recordRun($row);
            $this->persistPendingAttachments($model, $savedRunId, $activeRun, $userId);
        } else {
            $this->saveSessionRun($row);
            $this->cleanupPendingAttachmentsFromRun($activeRun);
        }

        $activeRuns = $this->activeRuns();
        unset($activeRuns[$scenarioId]);
        SessionHelper::set('screen_tests.active_runs', $activeRuns);
        SessionHelper::set('screen_tests.requested_scenario_id', $scenarioId);

        $this->flashSuccess('screen_tests_result_saved');
        header('Location: index.php?route=screen-tests/runner&scenario_id=' . rawurlencode($scenarioId));
        exit;
    }

    public function summary(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'scenario_code' => trim((string) ($_GET['scenario_code'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'result' => trim((string) ($_GET['result'] ?? '')),
            'verification' => trim((string) ($_GET['verification'] ?? '')),
        ];

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $canViewAll = $this->canViewAllRuns();
        $rows = $this->listRuns($userId, $filters, $canViewAll);
        $attachmentsByRunId = [];
        $model = $this->screenTestRunModel();
        if ($model instanceof ScreenTestRunModel && $model->supportsScreenTestRunAttachments()) {
            $attachmentsByRunId = $model->listAttachmentsByRunIds(array_map(
                static fn (array $row): int => (int) ($row['ScreenTestRunID'] ?? 0),
                $rows
            ));
        }

        $scenarioOptions = [];
        $moduleOptions = [];
        foreach (ScreenTestCatalog::all() as $scenarioId => $scenario) {
            $scenarioOptions[$scenarioId] = (string) ($scenario['title'] ?? $scenarioId);
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }
        }
        ksort($scenarioOptions, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $this->render('screentests/Summary', [
            'title' => __t('screen_tests_results_title'),
            'rows' => $rows,
            'filters' => $filters,
            'scenarioOptions' => $scenarioOptions,
            'moduleOptions' => $moduleOptions,
            'storageReady' => $this->persistentStorageAvailable(),
            'createTableScript' => 'backend-php/config/sql/create_tblScreenTestRuns.sql',
            'canViewAllRuns' => $canViewAll,
            'attachmentsByRunId' => $attachmentsByRunId,
        ]);
    }

    public function captureScreenshot(): void
    {
        if (!$this->screenCaptureFeatureEnabled()) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_capture_unavailable')], 403);
        }
        if (!$this->screenCaptureStorageAvailable()) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_capture_storage_required')], 400);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->jsonPayload(['ok' => false, 'message' => __t('security_check_failed')], 400);
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $scenario = ScreenTestCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_scenario_not_found')], 404);
        }

        $activeRuns = $this->activeRuns();
        $activeRun = $activeRuns[$scenarioId] ?? null;
        if (!is_array($activeRun)) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_active_run_required')], 400);
        }

        $upload = $_FILES['screenshot'] ?? null;
        $this->handlePendingAttachmentUpload($scenarioId, $activeRun, $upload, true);
    }

    public function uploadAttachment(): void
    {
        if (!$this->screenCaptureFeatureEnabled()) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_capture_unavailable')], 403);
        }
        if (!$this->screenCaptureStorageAvailable()) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_capture_storage_required')], 400);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->jsonPayload(['ok' => false, 'message' => __t('security_check_failed')], 400);
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        $scenario = ScreenTestCatalog::get($scenarioId);
        if ($scenario === null) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_scenario_not_found')], 404);
        }

        $activeRuns = $this->activeRuns();
        $activeRun = $activeRuns[$scenarioId] ?? null;
        if (!is_array($activeRun)) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_active_run_required')], 400);
        }

        $upload = $_FILES['attachment'] ?? null;
        $this->handlePendingAttachmentUpload($scenarioId, $activeRun, $upload, false);
    }

    private function handlePendingAttachmentUpload(string $scenarioId, array $activeRun, mixed $upload, bool $imagesOnly): void
    {
        if (!is_array($upload)) {
            $this->jsonPayload(['ok' => false, 'message' => __t('screen_tests_capture_missing_file')], 400);
        }

        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_upload_failed' : 'screen_tests_attachment_upload_failed';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 400);
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $defaultName = $imagesOnly ? 'screenshot.png' : 'test-evidence';
        $originalName = trim((string) ($upload['name'] ?? $defaultName));
        $fileSize = max(0, (int) ($upload['size'] ?? 0));
        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $fileSize <= 0) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_invalid_payload' : 'screen_tests_attachment_invalid_payload';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 400);
        }
        if ($fileSize > (5 * 1024 * 1024)) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_file_too_large' : 'screen_tests_attachment_file_too_large';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 400);
        }

        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmpPath);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = strtolower($detected);
                }
                finfo_close($finfo);
            }
        }
        $allowedMimeTypes = $imagesOnly
            ? ['image/png', 'image/jpeg', 'image/webp']
            : ['image/png', 'image/jpeg', 'image/webp', 'application/pdf', 'text/plain'];
        if ($mimeType === null || !in_array($mimeType, $allowedMimeTypes, true)) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_invalid_type' : 'screen_tests_attachment_invalid_type';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 400);
        }

        $extensionByMime = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];
        $extension = $extensionByMime[$mimeType] ?? 'png';

        $pendingDir = $this->screenCapturePendingDirectory($scenarioId);
        if (!is_dir($pendingDir) && !@mkdir($pendingDir, 0775, true) && !is_dir($pendingDir)) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_storage_failed' : 'screen_tests_attachment_storage_failed';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 500);
        }

        $token = bin2hex(random_bytes(12));
        $storedFileName = $token . '.' . $extension;
        $targetPath = $pendingDir . DIRECTORY_SEPARATOR . $storedFileName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $messageKey = $imagesOnly ? 'screen_tests_capture_storage_failed' : 'screen_tests_attachment_storage_failed';
            $this->jsonPayload(['ok' => false, 'message' => __t($messageKey)], 500);
        }

        $pendingAttachment = [
            'token' => $token,
            'original_name' => $originalName !== '' ? $originalName : (($imagesOnly ? 'screenshot' : 'attachment') . '-' . gmdate('Ymd-His') . '.' . $extension),
            'stored_file_name' => $storedFileName,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'storage_path' => $targetPath,
            'captured_at' => gmdate('Y-m-d H:i:s'),
        ];

        $pendingAttachments = $this->pendingAttachmentsFromRun($activeRun);
        array_unshift($pendingAttachments, $pendingAttachment);
        $activeRun['pending_attachments'] = $pendingAttachments;
        $activeRuns[$scenarioId] = $activeRun;
        SessionHelper::set('screen_tests.active_runs', $activeRuns);

        $successMessageKey = $imagesOnly ? 'screen_tests_capture_saved' : 'screen_tests_attachment_saved';
        $this->jsonPayload([
            'ok' => true,
            'message' => __t($successMessageKey),
            'attachments' => array_map([$this, 'presentPendingAttachment'], $pendingAttachments),
        ]);
    }

    public function viewAttachment(): void
    {
        $this->serveAttachment(false);
    }

    public function downloadAttachment(): void
    {
        $this->serveAttachment(true);
    }

    private function screenTestRunModel(): ?ScreenTestRunModel
    {
        if (!$this->db instanceof \PDO) {
            return null;
        }

        return new ScreenTestRunModel($this->db);
    }

    private function persistentStorageAvailable(): bool
    {
        $model = $this->screenTestRunModel();
        return $model instanceof ScreenTestRunModel && $model->supportsScreenTestRuns();
    }

    private function screenCaptureStorageAvailable(): bool
    {
        $model = $this->screenTestRunModel();
        return $model instanceof ScreenTestRunModel
            && $model->supportsScreenTestRuns()
            && $model->supportsScreenTestRunAttachments();
    }

    private function buildRunContext(): array
    {
        return [
            'fy' => (int) SessionHelper::get('FiscalYearID', 0),
            'ver' => (int) SessionHelper::get('VersionID', 0),
            'scope_dataobject_code' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
            'scope_dataobject_name' => trim((string) SessionHelper::get('scope.dataobject_name', '')),
            'user_id' => (int) SessionHelper::get('auth.user_id', 0),
            'username' => (string) SessionHelper::get('auth.username', ''),
        ];
    }

    private function activeRuns(): array
    {
        $runs = SessionHelper::get('screen_tests.active_runs', []);
        return is_array($runs) ? $runs : [];
    }

    private function nextAttemptNo(int $userId, string $scenarioId): int
    {
        $model = $this->screenTestRunModel();
        if ($model instanceof ScreenTestRunModel && $model->supportsScreenTestRuns()) {
            return $model->nextAttemptNo($userId, $scenarioId);
        }

        $attempt = 1;
        foreach ($this->sessionRuns() as $row) {
            if ((int) ($row['UserID'] ?? 0) !== $userId) {
                continue;
            }
            if ((string) ($row['ScenarioCode'] ?? '') !== $scenarioId) {
                continue;
            }
            $attempt = max($attempt, ((int) ($row['AttemptNo'] ?? 0)) + 1);
        }

        return $attempt;
    }

    private function saveSessionRun(array $row): void
    {
        $runs = $this->sessionRuns();
        $runs[] = [
            'ScreenTestRunID' => count($runs) + 1,
            'UserID' => (int) ($row['user_id'] ?? 0),
            'ScenarioCode' => (string) ($row['scenario_code'] ?? ''),
            'ScenarioTitle' => (string) ($row['scenario_title'] ?? ''),
            'ModuleName' => (string) ($row['module_name'] ?? ''),
            'ScreenFamily' => (string) ($row['screen_family'] ?? ''),
            'TargetRoute' => (string) ($row['target_route'] ?? ''),
            'RunResult' => (string) ($row['run_result'] ?? ''),
            'VerificationStatus' => (string) ($row['verification_status'] ?? ''),
            'AttemptNo' => max(1, (int) ($row['attempt_no'] ?? 1)),
            'FiscalYearID' => (int) ($row['fiscal_year_id'] ?? 0),
            'VersionID' => (int) ($row['version_id'] ?? 0),
            'DataObjectCode' => (string) ($row['dataobject_code'] ?? ''),
            'ContextJson' => json_encode($row['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'TestDataJson' => json_encode($row['test_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'OutcomeSummary' => (string) ($row['outcome_summary'] ?? ''),
            'DefectReference' => (string) ($row['defect_reference'] ?? ''),
            'TesterNotes' => (string) ($row['tester_notes'] ?? ''),
            'StartedAt' => (string) ($row['started_at'] ?? ''),
            'CompletedAt' => (string) ($row['completed_at'] ?? ''),
            'DurationSeconds' => (int) ($row['duration_seconds'] ?? 0),
            'Username' => (string) ($row['username'] ?? SessionHelper::get('auth.username', '')),
            'DisplayName' => (string) ($row['display_name'] ?? SessionHelper::get('auth.username', '')),
        ];

        usort($runs, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['CompletedAt'] ?? $left['StartedAt'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['CompletedAt'] ?? $right['StartedAt'] ?? '')) ?: 0;
            return $rightTime <=> $leftTime;
        });

        SessionHelper::set('screen_tests.run_logs', $runs);
    }

    private function sessionRuns(): array
    {
        $runs = SessionHelper::get('screen_tests.run_logs', []);
        return is_array($runs) ? $runs : [];
    }

    private function listRuns(int $userId, array $filters = [], bool $includeAll = false): array
    {
        $model = $this->screenTestRunModel();
        if ($model instanceof ScreenTestRunModel && $model->supportsScreenTestRuns()) {
            return $model->listRuns($userId, $filters, $includeAll);
        }

        $rows = array_values(array_filter($this->sessionRuns(), static function (array $row) use ($userId): bool {
            return (int) ($row['UserID'] ?? 0) === $userId;
        }));

        $rows = array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if (($filters['scenario_code'] ?? '') !== '' && (string) ($row['ScenarioCode'] ?? '') !== (string) $filters['scenario_code']) {
                return false;
            }
            if (($filters['module'] ?? '') !== '' && strcasecmp((string) ($row['ModuleName'] ?? ''), (string) $filters['module']) !== 0) {
                return false;
            }
            if (($filters['result'] ?? '') !== '' && strtolower((string) ($row['RunResult'] ?? '')) !== strtolower((string) $filters['result'])) {
                return false;
            }
            if (($filters['verification'] ?? '') !== '' && strtolower((string) ($row['VerificationStatus'] ?? '')) !== strtolower((string) $filters['verification'])) {
                return false;
            }
            if (($filters['q'] ?? '') !== '') {
                $needle = function_exists('mb_strtolower')
                    ? mb_strtolower((string) $filters['q'], 'UTF-8')
                    : strtolower((string) $filters['q']);
                $haystack = implode(' ', [
                    (string) ($row['ScenarioCode'] ?? ''),
                    (string) ($row['ScenarioTitle'] ?? ''),
                    (string) ($row['ModuleName'] ?? ''),
                    (string) ($row['DefectReference'] ?? ''),
                    (string) ($row['OutcomeSummary'] ?? ''),
                    (string) ($row['TesterNotes'] ?? ''),
                    (string) ($row['Username'] ?? ''),
                ]);
                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower($haystack, 'UTF-8')
                    : strtolower($haystack);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            return true;
        }));

        usort($rows, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['CompletedAt'] ?? $left['StartedAt'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['CompletedAt'] ?? $right['StartedAt'] ?? '')) ?: 0;
            if ($leftTime === $rightTime) {
                return ((int) ($right['ScreenTestRunID'] ?? 0)) <=> ((int) ($left['ScreenTestRunID'] ?? 0));
            }
            return $rightTime <=> $leftTime;
        });

        return $rows;
    }

    private function latestRunsByScenario(array $runs): array
    {
        $map = [];
        foreach ($runs as $row) {
            $scenarioCode = trim((string) ($row['ScenarioCode'] ?? ''));
            if ($scenarioCode === '' || isset($map[$scenarioCode])) {
                continue;
            }
            $map[$scenarioCode] = $row;
        }
        return $map;
    }

    private function scenarioCardResult(string $scenarioId, ?array $latestRun, ?array $activeRun): string
    {
        if (is_array($activeRun)) {
            return 'active';
        }

        $result = strtolower(trim((string) ($latestRun['RunResult'] ?? '')));
        return in_array($result, ['passed', 'failed', 'blocked'], true) ? $result : 'not_run';
    }

    private function targetUrlForScenario(array $scenario, array $runContext = []): string
    {
        $route = trim((string) ($scenario['target_route'] ?? 'home/index'));
        return $this->mergeLinkedContextIntoUrl(
            'index.php?' . http_build_query(['route' => $route]),
            $this->linkedContextOverridesFromRunContext($runContext)
        );
    }

    private function linkedContextOverridesFromRunContext(array $runContext): array
    {
        $scopeCode = trim((string) ($runContext['scope_dataobject_code'] ?? ''));
        $scopeName = trim((string) ($runContext['scope_dataobject_name'] ?? ''));

        return [
            'fy' => max(0, (int) ($runContext['fy'] ?? 0)),
            'ver' => max(0, (int) ($runContext['ver'] ?? 0)),
            'scope_dataobject_code' => $scopeCode,
            'scope_dataobject_name' => $scopeCode !== '' && $scopeName !== '' ? $scopeName : null,
        ];
    }

    private function canViewAllRuns(): bool
    {
        return $this->hasAnyPermission(['USERS_VIEW', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
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

    private function screenCaptureFeatureEnabled(): bool
    {
        return screen_test_capture_enabled($this->db);
    }

    private function pendingAttachmentsFromRun(?array $activeRun): array
    {
        $attachments = is_array($activeRun) ? ($activeRun['pending_attachments'] ?? []) : [];
        return is_array($attachments) ? array_values(array_filter($attachments, 'is_array')) : [];
    }

    private function presentPendingAttachment(array $attachment): array
    {
        return [
            'name' => (string) ($attachment['original_name'] ?? 'screenshot'),
            'captured_at' => (string) ($attachment['captured_at'] ?? ''),
            'size_label' => $this->formatBytes((int) ($attachment['file_size_bytes'] ?? 0)),
        ];
    }

    private function persistPendingAttachments(ScreenTestRunModel $model, int $runId, ?array $activeRun, int $userId): void
    {
        $pendingAttachments = $this->pendingAttachmentsFromRun($activeRun);
        if ($runId <= 0 || $pendingAttachments === [] || !$model->supportsScreenTestRunAttachments()) {
            $this->cleanupPendingAttachmentsFromRun($activeRun);
            return;
        }

        $finalDir = $this->screenCaptureFinalDirectory($runId);
        if (!is_dir($finalDir) && !@mkdir($finalDir, 0775, true) && !is_dir($finalDir)) {
            throw new \RuntimeException('Screen test screenshot storage directory could not be created.');
        }

        foreach ($pendingAttachments as $attachment) {
            $sourcePath = trim((string) ($attachment['storage_path'] ?? ''));
            if ($sourcePath === '' || !is_file($sourcePath)) {
                continue;
            }

            $storedFileName = trim((string) ($attachment['stored_file_name'] ?? basename($sourcePath)));
            if ($storedFileName === '') {
                $storedFileName = bin2hex(random_bytes(12)) . '.png';
            }

            $targetPath = $finalDir . DIRECTORY_SEPARATOR . $storedFileName;
            if (!@rename($sourcePath, $targetPath)) {
                if (!@copy($sourcePath, $targetPath)) {
                    throw new \RuntimeException('A captured screenshot could not be stored.');
                }
                @unlink($sourcePath);
            }

            $model->saveAttachment($runId, [
                'OriginalFileName' => (string) ($attachment['original_name'] ?? $storedFileName),
                'StoredFileName' => $storedFileName,
                'MimeType' => (string) ($attachment['mime_type'] ?? 'image/png'),
                'FileSizeBytes' => (int) ($attachment['file_size_bytes'] ?? 0),
                'StoragePath' => $targetPath,
                'UserID' => $userId,
            ]);
        }

        $this->cleanupPendingAttachmentsFromRun($activeRun);
    }

    private function cleanupPendingAttachmentsFromRun(?array $activeRun): void
    {
        foreach ($this->pendingAttachmentsFromRun($activeRun) as $attachment) {
            $path = trim((string) ($attachment['storage_path'] ?? ''));
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function screenCapturePendingDirectory(string $scenarioId): string
    {
        $safeScenarioId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $scenarioId) ?: 'screen-test';
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'screen-test-attachments'
            . DIRECTORY_SEPARATOR . 'pending'
            . DIRECTORY_SEPARATOR . session_id()
            . DIRECTORY_SEPARATOR . $safeScenarioId;
    }

    private function screenCaptureFinalDirectory(int $runId): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'screen-test-attachments'
            . DIRECTORY_SEPARATOR . 'runs'
            . DIRECTORY_SEPARATOR . $runId;
    }

    private function serveAttachment(bool $forceDownload): void
    {
        $attachmentId = (int) ($_GET['id'] ?? 0);
        $model = $this->screenTestRunModel();
        if (!$model instanceof ScreenTestRunModel || !$model->supportsScreenTestRunAttachments()) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $attachment = $model->getAttachment($attachmentId);
        if ($attachment === null) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $run = $model->getRunById((int) ($attachment['ScreenTestRunID'] ?? 0));
        if ($run === null) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ((int) ($run['UserID'] ?? 0) !== $userId && !$this->canViewAllRuns()) {
            $this->renderAccessDeniedNotice('Missing one of: USERS_VIEW, USERS_ADMIN, ADMIN_ALL, SYSADMIN', 403, 'compact');
            return;
        }

        $path = trim((string) ($attachment['StoragePath'] ?? ''));
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            echo 'Attachment file not found.';
            return;
        }

        $mimeType = trim((string) ($attachment['MimeType'] ?? 'application/octet-stream'));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        header('Content-Type: ' . $mimeType);
        $fileName = str_replace('"', '', (string) ($attachment['OriginalFileName'] ?? 'screenshot'));
        $disposition = $forceDownload ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    private function jsonPayload(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
