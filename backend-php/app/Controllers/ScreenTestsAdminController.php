<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\ScreenTestCatalog;

require_once __DIR__ . '/../../shared/csrf.php';

final class ScreenTestsAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureScreenTestingEnabled();
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

        ksort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);

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
        ]);
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
