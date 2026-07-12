<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScreenTestRunModel;
use App\Models\WorkflowUserGroupModel;
use App\Shared\CbmsModuleCatalog;
use App\Shared\ScreenTestCatalog;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../Shared/CbmsModuleCatalog.php';

final class ScreenTestsAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'userSearch' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'user-search' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'saveAssignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-assignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'cancelAssignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'cancel-assignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'cleanupAssignments' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'cleanup-assignments' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'resetAssignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'reset-assignment' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'exportSummaryExcel' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'export-summary-excel' => ['auth' => true, 'permsAny' => ['TEST_SCRIPT_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureScreenTestingEnabled();
    }

    public function summary(): void
    {
        $data = $this->buildTestingSummaryData($this->testingSummaryFiltersFromRequest());

        $this->render('screentestsadmin/Summary', [
            'title' => 'Testing Summary',
            'assignmentsInstalled' => $data['assignmentsInstalled'],
            'createAssignmentsScript' => 'backend-php/config/sql/create_tblScreenTestAssignments.sql',
            'filters' => $data['filters'],
            'moduleOptions' => $data['moduleOptions'],
            'overall' => $data['overall'],
            'moduleSummary' => $data['moduleSummary'],
            'userSummary' => $data['userSummary'],
            'assignmentRows' => $data['assignmentRows'],
        ]);
    }

    public function exportSummaryExcel(): void
    {
        $data = $this->buildTestingSummaryData($this->testingSummaryFiltersFromRequest());
        $exportRows = [];

        foreach ([
            'Total Assigned' => 'total',
            'Not Started' => 'not_started',
            'In Progress' => 'in_progress',
            'Completed' => 'completed',
            'Overdue' => 'overdue',
        ] as $label => $key) {
            $exportRows[] = [
                'Section' => 'Overall',
                'Name' => $label,
                'Username' => '',
                'Script' => '',
                'Module' => '',
                'Due Date' => '',
                'Status' => '',
                'Latest Result' => '',
                'Latest Run' => '',
                'Defect Reference' => '',
                'Total' => (string) (int) ($data['overall'][$key] ?? 0),
                'Completed' => '',
                'Overdue' => '',
                'Completion Percent' => '',
            ];
        }

        foreach ($data['moduleSummary'] as $row) {
            $exportRows[] = [
                'Section' => 'Module Summary',
                'Name' => (string) ($row['module'] ?? ''),
                'Username' => '',
                'Script' => '',
                'Module' => (string) ($row['module'] ?? ''),
                'Due Date' => '',
                'Status' => '',
                'Latest Result' => '',
                'Latest Run' => '',
                'Defect Reference' => '',
                'Total' => (string) (int) ($row['total'] ?? 0),
                'Completed' => (string) (int) ($row['completed'] ?? 0),
                'Overdue' => (string) (int) ($row['overdue'] ?? 0),
                'Completion Percent' => (string) (int) ($row['completion_percent'] ?? 0),
            ];
        }

        foreach ($data['userSummary'] as $row) {
            $exportRows[] = [
                'Section' => 'Tester Summary',
                'Name' => (string) ($row['user_name'] ?? ''),
                'Username' => (string) ($row['username'] ?? ''),
                'Script' => '',
                'Module' => '',
                'Due Date' => '',
                'Status' => '',
                'Latest Result' => '',
                'Latest Run' => '',
                'Defect Reference' => '',
                'Total' => (string) (int) ($row['total'] ?? 0),
                'Completed' => (string) (int) ($row['completed'] ?? 0),
                'Overdue' => (string) (int) ($row['overdue'] ?? 0),
                'Completion Percent' => (string) (int) ($row['completion_percent'] ?? 0),
            ];
        }

        foreach ($data['assignmentRows'] as $row) {
            $displayName = trim((string) ($row['DisplayName'] ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row['Username'] ?? ('User #' . (int) ($row['UserID'] ?? 0))));
            }
            $exportRows[] = [
                'Section' => 'Assignment Detail',
                'Name' => $displayName,
                'Username' => (string) ($row['Username'] ?? ''),
                'Script' => (string) ($row['ScenarioTitle'] ?? $row['ScenarioCode'] ?? ''),
                'Module' => (string) ($row['ModuleName'] ?? ''),
                'Due Date' => (string) ($row['DueDate'] ?? ''),
                'Status' => $this->testingStatusLabel((string) ($row['AssignmentStatus'] ?? 'assigned')),
                'Latest Result' => $this->testingResultLabel((string) ($row['RunResult'] ?? '')),
                'Latest Run' => (string) ($row['LatestRunCompletedAt'] ?? ''),
                'Defect Reference' => (string) ($row['DefectReference'] ?? ''),
                'Total' => '',
                'Completed' => '',
                'Overdue' => '',
                'Completion Percent' => '',
            ];
        }

        $this->downloadExcel('Testing Summary', 'TestingSummary', [
            ['label' => 'Section', 'key' => 'Section'],
            ['label' => 'Name', 'key' => 'Name'],
            ['label' => 'Username', 'key' => 'Username'],
            ['label' => 'Script', 'key' => 'Script'],
            ['label' => 'Module', 'key' => 'Module'],
            ['label' => 'Due Date', 'key' => 'Due Date'],
            ['label' => 'Status', 'key' => 'Status'],
            ['label' => 'Latest Result', 'key' => 'Latest Result'],
            ['label' => 'Latest Run', 'key' => 'Latest Run'],
            ['label' => 'Defect Reference', 'key' => 'Defect Reference'],
            ['label' => 'Total', 'key' => 'Total'],
            ['label' => 'Completed', 'key' => 'Completed'],
            ['label' => 'Overdue', 'key' => 'Overdue'],
            ['label' => 'Completion Percent', 'key' => 'Completion Percent'],
        ], $exportRows);
    }

    public function scenarios(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
        ];

        $rows = [];
        $moduleOptions = [];
        foreach (ScreenTestCatalog::all() as $scenarioId => $scenario) {
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }

            $sourceState = $this->sourceStateForScenario($scenarioId);
            $searchHaystack = strtolower(implode(' ', [
                $scenarioId,
                (string) ($scenario['title'] ?? ''),
                $moduleName,
                (string) ($scenario['screen_family'] ?? ''),
                (string) ($scenario['target_route'] ?? ''),
                (string) ($scenario['audience'] ?? ''),
            ]));

            if ($filters['module'] !== '' && strcasecmp($moduleName, $filters['module']) !== 0) {
                continue;
            }
            if ($filters['q'] !== '' && !str_contains($searchHaystack, strtolower($filters['q']))) {
                continue;
            }

            $rows[] = [
                'id' => $scenarioId,
                'title' => (string) ($scenario['title'] ?? $scenarioId),
                'module' => $moduleName,
                'screen_family' => (string) ($scenario['screen_family'] ?? ''),
                'target_route' => (string) ($scenario['target_route'] ?? ''),
                'step_count' => count(is_array($scenario['steps'] ?? null) ? $scenario['steps'] : []),
                'source_state' => $sourceState,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $moduleCompare = strcasecmp((string) ($left['module'] ?? ''), (string) ($right['module'] ?? ''));
            if ($moduleCompare !== 0) {
                return $moduleCompare;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        $moduleOptions = CbmsModuleCatalog::mergeWithObserved($moduleOptions);

        $this->render('screentestsadmin/ScenarioList', [
            'title' => 'Test Script Catalogue',
            'rows' => $rows,
            'filters' => $filters,
            'moduleOptions' => $moduleOptions,
            'storagePath' => ScreenTestCatalog::storagePath(),
        ]);
    }

    public function scenarioForm(): void
    {
        $scenarioId = trim((string) ($_GET['scenario_id'] ?? ''));
        $record = $scenarioId !== '' ? ScreenTestCatalog::get($scenarioId) : null;
        $sourceState = $scenarioId !== '' ? $this->sourceStateForScenario($scenarioId) : 'custom';

        $this->render('screentestsadmin/ScenarioForm', [
            'title' => $record !== null ? 'Edit Test Script' : 'Create Test Script',
            'record' => $record,
            'sourceState' => $sourceState,
            'storagePath' => ScreenTestCatalog::storagePath(),
            'moduleOptions' => CbmsModuleCatalog::mergeWithObserved($record !== null ? [(string) ($record['module'] ?? '')] : []),
        ]);
    }

    public function assignments(): void
    {
        $model = $this->screenTestRunModel();
        $assignmentsInstalled = $model instanceof ScreenTestRunModel && $model->supportsScreenTestAssignments();
        $workflowGroupModel = $this->workflowUserGroupModel();
        $workflowGroupsInstalled = $workflowGroupModel instanceof WorkflowUserGroupModel && $workflowGroupModel->supportsWorkflowUserGroups();

        $scenarioRows = [];
        $moduleOptions = [];
        foreach (ScreenTestCatalog::all() as $scenarioId => $scenario) {
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }
            $scenarioRows[] = [
                'id' => $scenarioId,
                'title' => (string) ($scenario['title'] ?? $scenarioId),
                'module' => $moduleName,
                'screen_family' => (string) ($scenario['screen_family'] ?? ''),
                'target_route' => (string) ($scenario['target_route'] ?? ''),
            ];
        }
        usort($scenarioRows, static function (array $left, array $right): int {
            $moduleCompare = strcasecmp((string) ($left['module'] ?? ''), (string) ($right['module'] ?? ''));
            if ($moduleCompare !== 0) {
                return $moduleCompare;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
        $moduleOptions = CbmsModuleCatalog::mergeWithObserved($moduleOptions);

        $this->render('screentestsadmin/Assignments', [
            'title' => 'Assign Test Scripts',
            'assignmentsInstalled' => $assignmentsInstalled,
            'createAssignmentsScript' => 'backend-php/config/sql/create_tblScreenTestAssignments.sql',
            'scenarioRows' => $scenarioRows,
            'moduleOptions' => $moduleOptions,
            'assignments' => $assignmentsInstalled && $model instanceof ScreenTestRunModel ? $model->listAssignments(['status' => 'open']) : [],
            'workflowUserGroups' => $workflowGroupsInstalled && $workflowGroupModel instanceof WorkflowUserGroupModel ? $workflowGroupModel->listGroups('', '1') : [],
            'workflowUserGroupsInstalled' => $workflowGroupsInstalled,
        ]);
    }

    public function userSearch(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $model = $this->screenTestRunModel();
            if (!$model instanceof ScreenTestRunModel) {
                throw new \RuntimeException('Screen test model is not available.');
            }

            $items = [];
            foreach ($model->searchUsers(trim((string) ($_GET['q'] ?? '')), (int) ($_GET['limit'] ?? 50)) as $row) {
                $userId = (int) ($row['UserID'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                $displayName = trim((string) ($row['DisplayName'] ?? ''));
                $username = trim((string) ($row['Username'] ?? ''));
                $email = trim((string) ($row['Email'] ?? ''));
                $label = $displayName !== '' ? $displayName : ($username !== '' ? $username : ('User #' . $userId));
                $items[] = [
                    'id' => $userId,
                    'label' => $label,
                    'username' => $username,
                    'email' => $email,
                ];
            }
            echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    public function saveAssignment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $model = $this->screenTestRunModel();
            if (!$model instanceof ScreenTestRunModel || !$model->supportsScreenTestAssignments()) {
                throw new \RuntimeException('Screen test assignment storage is not available.');
            }

            $selectedUserIds = is_array($_POST['AssignmentUserIDs'] ?? null) ? $_POST['AssignmentUserIDs'] : [];
            $selectedUserIdsText = implode(',', array_filter(array_map(
                static fn($userId): string => (string) max(0, (int) $userId),
                $selectedUserIds
            )));
            $selectedGroupIds = $this->normalizeIntArray($_POST['WorkflowUserGroupIDs'] ?? []);
            $groupUserIds = [];
            if ($selectedGroupIds !== []) {
                $workflowUserGroupModel = $this->workflowUserGroupModel();
                if (!$workflowUserGroupModel instanceof WorkflowUserGroupModel || !$workflowUserGroupModel->supportsWorkflowUserGroups()) {
                    throw new \RuntimeException('Workflow user group tables are not installed.');
                }
                foreach ($workflowUserGroupModel->listActiveMembersForGroups($selectedGroupIds) as $row) {
                    $groupUserId = (int) ($row['UserID'] ?? 0);
                    if ($groupUserId > 0) {
                        $groupUserIds[] = $groupUserId;
                    }
                }
            }

            $selectedScenarioCodes = is_array($_POST['ScenarioCodes'] ?? null) ? $_POST['ScenarioCodes'] : [];
            $selectedModule = trim((string) ($_POST['ModuleName'] ?? ''));
            $scenarioCodes = [];
            $moduleByScenario = [];
            foreach (ScreenTestCatalog::all() as $scenarioId => $scenario) {
                $moduleName = trim((string) ($scenario['module'] ?? ''));
                $isSelectedScenario = in_array((string) $scenarioId, array_map('strval', $selectedScenarioCodes), true);
                $isSelectedModule = $selectedModule !== '' && strcasecmp($moduleName, $selectedModule) === 0;
                if ($isSelectedScenario || $isSelectedModule) {
                    $scenarioCodes[] = (string) $scenarioId;
                    $moduleByScenario[(string) $scenarioId] = $moduleName;
                }
            }

            $result = $model->saveAssignments([
                'UserIDs' => trim($selectedUserIdsText . ',' . implode(',', $groupUserIds), " \t\n\r\0\x0B,"),
                'ScenarioCodes' => $scenarioCodes,
                'ModuleByScenario' => $moduleByScenario,
                'DueDate' => trim((string) ($_POST['DueDate'] ?? '')),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));

            $created = (int) ($result['created'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $message = $created . ' test script assignment' . ($created === 1 ? '' : 's') . ' created.';
            if ($skipped > 0) {
                $message .= ' ' . $skipped . ' already assigned and skipped.';
            }
            $this->flashSuccess($message);
        } catch (\Throwable $e) {
            $this->flashError('Test script assignment failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/assignments');
    }

    public function cancelAssignment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $model = $this->screenTestRunModel();
            if (!$model instanceof ScreenTestRunModel) {
                throw new \RuntimeException('Screen test model is not available.');
            }
            $cancelled = $model->cancelAssignment(
                (int) ($_POST['ScreenTestAssignmentID'] ?? 0),
                (int) SessionHelper::get('auth.user_id', 0)
            );
            if ($cancelled) {
                $this->flashSuccess('Test script assignment removed.');
            } else {
                $this->flashError('Test script assignment could not be removed. It may already be completed or cancelled.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Test script assignment remove failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/assignments');
    }

    public function cleanupAssignments(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $model = $this->screenTestRunModel();
            if (!$model instanceof ScreenTestRunModel || !$model->supportsScreenTestAssignments()) {
                throw new \RuntimeException('Screen test assignment storage is not available.');
            }

            $cleaned = $model->cleanupAssignments([
                'scope' => trim((string) ($_POST['CleanupScope'] ?? 'completed')),
                'module' => trim((string) ($_POST['CleanupModuleName'] ?? '')),
                'due_before' => trim((string) ($_POST['CleanupDueBefore'] ?? '')),
            ], (int) SessionHelper::get('auth.user_id', 0));

            $this->flashSuccess($cleaned . ' test script assignment' . ($cleaned === 1 ? '' : 's') . ' cleaned up.');
        } catch (\Throwable $e) {
            $this->flashError('Test script assignment cleanup failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/assignments');
    }

    public function resetAssignment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $model = $this->screenTestRunModel();
            if (!$model instanceof ScreenTestRunModel) {
                throw new \RuntimeException('Screen test model is not available.');
            }
            $reset = $model->resetAssignment(
                (int) ($_POST['ScreenTestAssignmentID'] ?? 0),
                (int) SessionHelper::get('auth.user_id', 0)
            );
            if ($reset) {
                $this->flashSuccess('Test script assignment reset for retesting.');
            } else {
                $this->flashError('Test script assignment could not be reset.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Test script assignment reset failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/summary');
    }

    public function saveScenario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=screen-tests-admin/scenarios');
            return;
        }

        $scenarioId = trim((string) ($_POST['id'] ?? ''));
        $payload = [
            'id' => $scenarioId,
            'module' => trim((string) ($_POST['module'] ?? '')),
            'screen_family' => trim((string) ($_POST['screen_family'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'purpose' => trim((string) ($_POST['purpose'] ?? '')),
            'audience' => trim((string) ($_POST['audience'] ?? '')),
            'difficulty' => trim((string) ($_POST['difficulty'] ?? '')),
            'target_route' => trim((string) ($_POST['target_route'] ?? '')),
            'target_label' => trim((string) ($_POST['target_label'] ?? '')),
            'baseline_context' => $this->parseKeyValueRows(
                is_array($_POST['baseline_label'] ?? null) ? $_POST['baseline_label'] : [],
                is_array($_POST['baseline_value'] ?? null) ? $_POST['baseline_value'] : []
            ),
            'prerequisites' => $this->parseStringList(is_array($_POST['prerequisites'] ?? null) ? $_POST['prerequisites'] : []),
            'test_data_defs' => $this->parseKeyValueRows(
                is_array($_POST['sample_key'] ?? null) ? $_POST['sample_key'] : [],
                is_array($_POST['sample_value'] ?? null) ? $_POST['sample_value'] : []
            ),
            'steps' => $this->parseSteps(
                is_array($_POST['step_number'] ?? null) ? $_POST['step_number'] : [],
                is_array($_POST['step_title'] ?? null) ? $_POST['step_title'] : [],
                is_array($_POST['step_instruction'] ?? null) ? $_POST['step_instruction'] : []
            ),
            'expected_visible' => $this->parseStringList(is_array($_POST['expected_visible'] ?? null) ? $_POST['expected_visible'] : []),
            'expected_data' => $this->parseStringList(is_array($_POST['expected_data'] ?? null) ? $_POST['expected_data'] : []),
            'verification_queries' => $this->parseStringList(is_array($_POST['verification_queries'] ?? null) ? $_POST['verification_queries'] : []),
            'reset_scripts' => $this->parseResetScripts(
                is_array($_POST['reset_path'] ?? null) ? $_POST['reset_path'] : [],
                is_array($_POST['reset_note'] ?? null) ? $_POST['reset_note'] : []
            ),
        ];

        try {
            $this->validatePayload($payload);
            $savedScenarioId = ScreenTestCatalog::saveEditableScenario($payload);
            $this->flashSuccess('Test script saved.');
            header('Location: index.php?route=screen-tests-admin/scenario-form&scenario_id=' . urlencode($savedScenarioId));
            return;
        } catch (\Throwable $e) {
            $this->flashError('Test script save failed: ' . $e->getMessage());
            $returnId = $scenarioId !== '' ? '&scenario_id=' . urlencode($scenarioId) : '';
            header('Location: index.php?route=screen-tests-admin/scenario-form' . $returnId);
            return;
        }
    }

    public function resetScenario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=screen-tests-admin/scenarios');
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        try {
            if (!ScreenTestCatalog::isBuiltInScenario($scenarioId)) {
                throw new \RuntimeException('Only built-in scripts can be reset to default.');
            }
            ScreenTestCatalog::deleteEditableScenario($scenarioId);
            $this->flashSuccess('Test script reset to built-in default.');
        } catch (\Throwable $e) {
            $this->flashError('Test script reset failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/scenarios');
    }

    public function deleteScenario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=screen-tests-admin/scenarios');
            return;
        }

        $scenarioId = trim((string) ($_POST['scenario_id'] ?? ''));
        try {
            if (ScreenTestCatalog::isBuiltInScenario($scenarioId)) {
                throw new \RuntimeException('Built-in scripts cannot be deleted.');
            }
            ScreenTestCatalog::deleteEditableScenario($scenarioId);
            $this->flashSuccess('Custom test script deleted.');
        } catch (\Throwable $e) {
            $this->flashError('Test script delete failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=screen-tests-admin/scenarios');
    }

    private function sourceStateForScenario(string $scenarioId): string
    {
        $isBuiltIn = ScreenTestCatalog::isBuiltInScenario($scenarioId);
        $hasOverride = ScreenTestCatalog::hasEditableOverride($scenarioId);

        if ($isBuiltIn && $hasOverride) {
            return 'override';
        }
        if ($isBuiltIn) {
            return 'built_in';
        }

        return 'custom';
    }

    private function screenTestRunModel(): ?ScreenTestRunModel
    {
        return $this->db instanceof \PDO ? new ScreenTestRunModel($this->db) : null;
    }

    private function workflowUserGroupModel(): ?WorkflowUserGroupModel
    {
        return $this->db instanceof \PDO ? new WorkflowUserGroupModel($this->db) : null;
    }

    private function normalizeIntArray(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($rawValues as $rawValue) {
            $id = (int) $rawValue;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function testingSummaryFiltersFromRequest(): array
    {
        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
    }

    private function buildTestingSummaryData(array $filters): array
    {
        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'module' => trim((string) ($filters['module'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
        ];

        $moduleOptions = [];
        foreach (ScreenTestCatalog::all() as $scenario) {
            $moduleName = trim((string) ($scenario['module'] ?? ''));
            if ($moduleName !== '') {
                $moduleOptions[$moduleName] = $moduleName;
            }
        }
        $moduleOptions = CbmsModuleCatalog::mergeWithObserved($moduleOptions);

        $model = $this->screenTestRunModel();
        $assignmentsInstalled = $model instanceof ScreenTestRunModel && $model->supportsScreenTestAssignments();
        $assignmentRows = [];
        if ($assignmentsInstalled && $model instanceof ScreenTestRunModel) {
            $assignmentRows = $model->listAssignmentProgress(0, [
                'q' => $filters['q'],
                'module' => $filters['module'],
            ], true);
        }

        if ($filters['status'] !== '') {
            $selectedStatus = strtolower($filters['status']);
            $assignmentRows = array_values(array_filter($assignmentRows, static function (array $row) use ($selectedStatus): bool {
                return strtolower((string) ($row['AssignmentStatus'] ?? 'assigned')) === $selectedStatus;
            }));
        }

        $overall = $this->emptySummaryBucket();
        $moduleSummary = [];
        $userSummary = [];
        $today = date('Y-m-d');

        foreach ($assignmentRows as $row) {
            $status = strtolower((string) ($row['AssignmentStatus'] ?? 'assigned'));
            $result = strtolower((string) ($row['RunResult'] ?? ''));
            $dueDate = trim((string) ($row['DueDate'] ?? ''));
            $isOverdue = $dueDate !== '' && $dueDate < $today && $status !== 'completed';
            $moduleName = trim((string) ($row['ModuleName'] ?? ''));
            if ($moduleName === '') {
                $moduleName = 'Unassigned Module';
            }
            $userId = (int) ($row['UserID'] ?? 0);
            $displayName = trim((string) ($row['DisplayName'] ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row['Username'] ?? ('User #' . $userId)));
            }

            $this->addTestingSummaryRow($overall, $status, $result, $isOverdue);

            if (!isset($moduleSummary[$moduleName])) {
                $moduleSummary[$moduleName] = array_merge($this->emptySummaryBucket(), [
                    'module' => $moduleName,
                ]);
            }
            $this->addTestingSummaryRow($moduleSummary[$moduleName], $status, $result, $isOverdue);

            if (!isset($userSummary[$userId])) {
                $userSummary[$userId] = array_merge($this->emptySummaryBucket(), [
                    'user_id' => $userId,
                    'user_name' => $displayName,
                    'username' => (string) ($row['Username'] ?? ''),
                ]);
            }
            $this->addTestingSummaryRow($userSummary[$userId], $status, $result, $isOverdue);
        }

        $overall = $this->withCompletionPercent($overall);
        $moduleSummary = array_map(fn (array $row): array => $this->withCompletionPercent($row), array_values($moduleSummary));
        $userSummary = array_map(fn (array $row): array => $this->withCompletionPercent($row), array_values($userSummary));

        usort($moduleSummary, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['module'] ?? ''), (string) ($right['module'] ?? ''));
        });
        usort($userSummary, static function (array $left, array $right): int {
            $completionCompare = ((int) ($left['completion_percent'] ?? 0)) <=> ((int) ($right['completion_percent'] ?? 0));
            if ($completionCompare !== 0) {
                return $completionCompare;
            }
            return strcasecmp((string) ($left['user_name'] ?? ''), (string) ($right['user_name'] ?? ''));
        });

        return [
            'filters' => $filters,
            'moduleOptions' => $moduleOptions,
            'assignmentsInstalled' => $assignmentsInstalled,
            'overall' => $overall,
            'moduleSummary' => $moduleSummary,
            'userSummary' => $userSummary,
            'assignmentRows' => $assignmentRows,
        ];
    }

    private function testingStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            default => 'Not Started',
        };
    }

    private function testingResultLabel(string $result): string
    {
        return match (strtolower(trim($result))) {
            'passed' => 'Passed',
            'failed' => 'Failed',
            'blocked' => 'Blocked',
            default => 'Not Run',
        };
    }

    private function emptySummaryBucket(): array
    {
        return [
            'total' => 0,
            'not_started' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'passed' => 0,
            'failed' => 0,
            'blocked' => 0,
            'completion_percent' => 0,
        ];
    }

    private function addTestingSummaryRow(array &$bucket, string $status, string $result, bool $isOverdue): void
    {
        $bucket['total'] = (int) ($bucket['total'] ?? 0) + 1;
        if ($status === 'completed') {
            $bucket['completed'] = (int) ($bucket['completed'] ?? 0) + 1;
        } elseif ($status === 'in_progress') {
            $bucket['in_progress'] = (int) ($bucket['in_progress'] ?? 0) + 1;
        } else {
            $bucket['not_started'] = (int) ($bucket['not_started'] ?? 0) + 1;
        }

        if ($isOverdue) {
            $bucket['overdue'] = (int) ($bucket['overdue'] ?? 0) + 1;
        }
        if (in_array($result, ['passed', 'failed', 'blocked'], true)) {
            $bucket[$result] = (int) ($bucket[$result] ?? 0) + 1;
        }
    }

    private function withCompletionPercent(array $bucket): array
    {
        $total = max(0, (int) ($bucket['total'] ?? 0));
        $completed = max(0, (int) ($bucket['completed'] ?? 0));
        $bucket['completion_percent'] = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        return $bucket;
    }

    private function validatePayload(array $payload): void
    {
        if (trim((string) ($payload['id'] ?? '')) === '') {
            throw new \RuntimeException('Scenario ID is required.');
        }
        if (trim((string) ($payload['title'] ?? '')) === '') {
            throw new \RuntimeException('Title is required.');
        }
        if (trim((string) ($payload['module'] ?? '')) === '') {
            throw new \RuntimeException('Module is required.');
        }
        if (trim((string) ($payload['screen_family'] ?? '')) === '') {
            throw new \RuntimeException('Screen family is required.');
        }
        if (trim((string) ($payload['target_route'] ?? '')) === '') {
            throw new \RuntimeException('Target route is required.');
        }
        if (!preg_match('/^[a-z0-9_]+$/i', (string) ($payload['id'] ?? ''))) {
            throw new \RuntimeException('Scenario ID may contain only letters, numbers, and underscores.');
        }
    }

    private function parseKeyValueRows(array $keys, array $values): array
    {
        $rows = [];
        $count = max(count($keys), count($values));
        for ($i = 0; $i < $count; $i++) {
            $key = trim((string) ($keys[$i] ?? ''));
            $value = trim((string) ($values[$i] ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }
            $rows[$key] = $value;
        }

        return $rows;
    }

    private function parseStringList(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $rows[] = $text;
        }

        return array_values($rows);
    }

    private function parseSteps(array $numbers, array $titles, array $instructions): array
    {
        $rows = [];
        $count = max(count($numbers), count($titles), count($instructions));
        for ($i = 0; $i < $count; $i++) {
            $number = max(1, (int) ($numbers[$i] ?? ($i + 1)));
            $title = trim((string) ($titles[$i] ?? ''));
            $instruction = trim((string) ($instructions[$i] ?? ''));
            if ($title === '' && $instruction === '') {
                continue;
            }
            $rows[] = [
                'number' => $number,
                'title' => $title,
                'instruction' => $instruction,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0));
        });

        return array_values($rows);
    }

    private function parseResetScripts(array $paths, array $notes): array
    {
        $rows = [];
        $count = max(count($paths), count($notes));
        for ($i = 0; $i < $count; $i++) {
            $path = trim((string) ($paths[$i] ?? ''));
            $note = trim((string) ($notes[$i] ?? ''));
            if ($path === '') {
                continue;
            }
            $rows[] = [
                'path' => $path,
                'note' => $note,
            ];
        }

        return array_values($rows);
    }
}
