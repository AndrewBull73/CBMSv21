<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScenarioPublishedResultsModel;

final class ScenarioResultsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['ANALYTICS_VIEW']],
    ];

    public function index(): void
    {
        $model = $this->buildModel();
        $filters = $this->readFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = $this->readPageSize();

        $totalCount = $model->countPublishedResults($filters);
        $totalPages = max(1, (int) ceil($totalCount / max(1, $pageSize)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $rows = $model->listPublishedResults($filters, $page, $pageSize);
        $summary = $model->getPublishedSummary($filters);
        $options = $model->getFilterOptions($filters);

        $this->render('scenarioresults/Index', [
            'title' => 'Scenario Results',
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $summary,
            'options' => $options,
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ]);
    }

    public function compare(): void
    {
        $model = $this->buildModel();
        $filters = $this->readCompareFilters();
        $options = $model->getComparisonOptions($filters);
        $rows = $model->listComparisonRows($filters);
        $summary = $model->getComparisonSummary($filters, $rows);

        $this->render('scenarioresults/Compare', [
            'title' => 'Scenario Compare',
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $summary,
            'options' => $options,
        ]);
    }

    public function api(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $model = $this->buildModel();
            $filters = $this->readFilters();
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $pageSize = $this->readPageSize();

            $totalCount = $model->countPublishedResults($filters);
            $rows = $model->listPublishedResults($filters, $page, $pageSize);
            $summary = $model->getPublishedSummary($filters);

            echo json_encode([
                'ok' => true,
                'data' => [
                    'rows' => $rows,
                    'summary' => $summary,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'totalCount' => $totalCount,
                    'totalPages' => max(1, (int) ceil($totalCount / max(1, $pageSize))),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Failed to load published scenario results.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    private function buildModel(): ScenarioPublishedResultsModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new ScenarioPublishedResultsModel($this->db);
    }

    private function readFilters(): array
    {
        return [
            'model' => trim((string) ($_GET['model'] ?? '')),
            'scenario' => trim((string) ($_GET['scenario'] ?? '')),
            'cost_object' => trim((string) ($_GET['cost_object'] ?? '')),
            'period' => trim((string) ($_GET['period'] ?? '')),
            'node' => trim((string) ($_GET['node'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    private function readPageSize(): int
    {
        $pageSize = (int) ($_GET['pageSize'] ?? 50);
        return min(250, max(10, $pageSize));
    }

    private function readCompareFilters(): array
    {
        $compareRaw = $_GET['compare_scenarios'] ?? [];
        $compareScenarios = is_array($compareRaw)
            ? array_values(array_unique(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $compareRaw), static fn(string $value): bool => $value !== '')))
            : [];

        $compareMode = trim((string) ($_GET['compare_mode'] ?? 'legacy_budget'));
        $baseScenario = trim((string) ($_GET['base_scenario'] ?? ''));

        if ($baseScenario !== '' && $compareMode !== 'scenario_base') {
            $compareMode = 'scenario_base';
        }

        return [
            'compare_mode' => $compareMode,
            'model' => trim((string) ($_GET['model'] ?? '')),
            'base_scenario' => $baseScenario,
            'compare_scenarios' => $compareScenarios,
            'cost_object' => trim((string) ($_GET['cost_object'] ?? '')),
            'period' => trim((string) ($_GET['period'] ?? '')),
            'node' => trim((string) ($_GET['node'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }
}
