<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\AIDatasetAnalysisModel;
use App\Services\AI\AIDatasetAnalysisService;
use App\Services\AI\OpenAIProvider;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/env.php';

final class AIDatasetAnalysisController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['AI_DATASET_ANALYZE', 'AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'datasets' => ['auth' => true, 'permsAny' => ['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'dataset-form' => ['auth' => true, 'permsAny' => ['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-dataset' => ['auth' => true, 'permsAny' => ['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'import-columns' => ['auth' => true, 'permsAny' => ['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-columns' => ['auth' => true, 'permsAny' => ['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'logs' => ['auth' => true, 'permsAny' => ['AI_DATASET_VIEW_LOGS', 'AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    private AIDatasetAnalysisModel $model;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/AIDatasetAnalysisModel.php';
        require_once __DIR__ . '/../Services/AI/AIProviderInterface.php';
        require_once __DIR__ . '/../Services/AI/OpenAIProvider.php';
        require_once __DIR__ . '/../Services/AI/AIDatasetAnalysisService.php';

        $this->model = new AIDatasetAnalysisModel($conn);
    }

    public function index(): void
    {
        $this->render('ai_dataset/Analyze', [
            'title' => 'AI Dataset Analysis',
            'foundationInstalled' => $this->model->supportsDatasetAnalysis(),
            'installScriptPath' => $this->installScriptPath(),
            'summary' => $this->model->summary(),
            'datasets' => $this->model->supportsDatasetAnalysis() ? $this->visibleDatasets() : [],
            'csrf' => csrf_token(),
            'context' => $this->analysisContext(),
        ]);
    }

    public function analyse(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Security check failed.']);
            return;
        }
        if (!$this->model->supportsDatasetAnalysis()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'AI dataset analysis schema is not installed.']);
            return;
        }

        $datasetId = (int) ($_POST['dataset_id'] ?? 0);
        $question = trim((string) ($_POST['question'] ?? ''));
        $dataset = $this->model->getDataset($datasetId);
        if ($dataset === null || (int) ($dataset['IsActive'] ?? 0) !== 1 || !$this->canUseDataset($dataset)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Dataset is not available to your user profile.']);
            return;
        }
        if ($question === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Please enter an analysis question.']);
            return;
        }

        $columns = $this->model->listColumns($datasetId, true);
        if ($columns === []) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'This dataset has no approved columns. Ask an administrator to import and review metadata.']);
            return;
        }

        $service = new AIDatasetAnalysisService($this->model, $this->buildProvider());
        try {
            $result = $service->analyse($dataset, $columns, $question, $this->analysisContext());
            $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
            $queryId = $this->model->logQuery([
                'DatasetID' => $datasetId,
                'UserID' => (int) SessionHelper::get('auth.user_id', 0),
                'Question' => $question,
                'AnalysisPlanJson' => json_encode($result['plan'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ExecutedSql' => (string) ($result['sql'] ?? ''),
                'ParametersJson' => json_encode($result['params'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ResponseSummary' => (string) ($result['summary'] ?? ''),
                'RowCount' => (int) ($result['row_count'] ?? 0),
                'ResponseTimeMs' => (int) ($result['duration_ms'] ?? 0),
                'ProviderCode' => (string) ($result['provider'] ?? ''),
                'ModelCode' => (string) ($result['model'] ?? ''),
                'PromptTokens' => (int) ($usage['input_tokens'] ?? 0),
                'CompletionTokens' => (int) ($usage['output_tokens'] ?? 0),
                'TotalTokens' => (int) ($usage['total_tokens'] ?? 0),
                'StatusCode' => 'SUCCESS',
            ]);
            echo json_encode(['ok' => true, 'query_id' => $queryId] + $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->model->logQuery([
                'DatasetID' => $datasetId,
                'UserID' => (int) SessionHelper::get('auth.user_id', 0),
                'Question' => $question,
                'StatusCode' => 'ERROR',
                'ErrorMessage' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Dataset analysis failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    public function datasets(): void
    {
        $this->render('ai_dataset/Datasets', [
            'title' => 'AI Analysis Datasets',
            'foundationInstalled' => $this->model->supportsDatasetAnalysis(),
            'installScriptPath' => $this->installScriptPath(),
            'rows' => $this->model->supportsDatasetAnalysis() ? $this->model->listDatasets(false) : [],
        ]);
    }

    public function datasetForm(): void
    {
        $datasetId = (int) ($_GET['id'] ?? 0);
        $dataset = $datasetId > 0 ? $this->model->getDataset($datasetId) : null;
        $this->render('ai_dataset/DatasetForm', [
            'title' => $dataset ? 'Edit AI Dataset' : 'Register AI Dataset',
            'foundationInstalled' => $this->model->supportsDatasetAnalysis(),
            'installScriptPath' => $this->installScriptPath(),
            'dataset' => $dataset,
            'columns' => $dataset ? $this->model->listColumns((int) $dataset['DatasetID'], false) : [],
            'csrf' => csrf_token(),
        ]);
    }

    public function saveDataset(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-dataset/datasets');
        try {
            $payload = $_POST;
            $payload['RequireContext'] = isset($_POST['RequireContext']) ? 1 : 0;
            $payload['IsActive'] = isset($_POST['IsActive']) ? 1 : 0;
            $id = $this->model->saveDataset($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('AI analysis dataset saved.');
            header('Location: index.php?route=ai-dataset/dataset-form&id=' . $id);
        } catch (\Throwable $e) {
            $this->flashError('Dataset save failed: ' . $e->getMessage());
            header('Location: index.php?route=ai-dataset/dataset-form&id=' . (int) ($_POST['DatasetID'] ?? 0));
        }
    }

    public function importColumns(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-dataset/datasets');
        $datasetId = (int) ($_POST['DatasetID'] ?? 0);
        try {
            $count = $this->model->importColumnsFromSource($datasetId);
            $this->flashSuccess('Imported ' . $count . ' column(s) from the dataset source.');
        } catch (\Throwable $e) {
            $this->flashError('Column import failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=ai-dataset/dataset-form&id=' . $datasetId);
    }

    public function saveColumns(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-dataset/datasets');
        $datasetId = (int) ($_POST['DatasetID'] ?? 0);
        try {
            $rows = is_array($_POST['columns'] ?? null) ? $_POST['columns'] : [];
            $normalised = [];
            foreach ($rows as $columnId => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['DatasetColumnID'] = (int) $columnId;
                $row['IsDimension'] = isset($row['IsDimension']) ? 1 : 0;
                $row['IsMetric'] = isset($row['IsMetric']) ? 1 : 0;
                $row['IsFilterable'] = isset($row['IsFilterable']) ? 1 : 0;
                $row['IsSensitive'] = isset($row['IsSensitive']) ? 1 : 0;
                $row['IsActive'] = isset($row['IsActive']) ? 1 : 0;
                $normalised[] = $row;
            }
            $this->model->updateColumnMetadata($datasetId, $normalised);
            $this->flashSuccess('Dataset column metadata saved.');
        } catch (\Throwable $e) {
            $this->flashError('Column metadata save failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=ai-dataset/dataset-form&id=' . $datasetId);
    }

    public function logs(): void
    {
        $this->render('ai_dataset/Logs', [
            'title' => 'AI Dataset Analysis Logs',
            'foundationInstalled' => $this->model->supportsDatasetAnalysis(),
            'installScriptPath' => $this->installScriptPath(),
            'rows' => $this->model->recentQueries(50),
        ]);
    }

    private function visibleDatasets(): array
    {
        return array_values(array_filter(
            $this->model->listDatasets(true),
            fn (array $dataset): bool => $this->canUseDataset($dataset)
        ));
    }

    private function canUseDataset(array $dataset): bool
    {
        if (Rbac::canAny(['AI_DATASET_ADMIN', 'ADMIN_ALL', 'SYSADMIN'])) {
            return true;
        }
        $codes = preg_split('/[,\s]+/', strtoupper((string) ($dataset['AllowedPermissionCodes'] ?? 'AI_DATASET_ANALYZE'))) ?: [];
        $codes = array_values(array_filter($codes, static fn (string $code): bool => trim($code) !== ''));
        return $codes === [] ? Rbac::can('AI_DATASET_ANALYZE') : Rbac::canAny($codes);
    }

    private function buildProvider(): OpenAIProvider
    {
        return new OpenAIProvider(
            envStr('OPENAI_API_KEY', ''),
            envStr('OPENAI_MODEL', 'gpt-4.1') ?? 'gpt-4.1',
            envStr('OPENAI_BASE_URL', 'https://api.openai.com/v1') ?? 'https://api.openai.com/v1',
            (int) (envStr('OPENAI_TIMEOUT', '60') ?? '60'),
            (int) (envStr('OPENAI_CONNECT_TIMEOUT', '20') ?? '20')
        );
    }

    private function analysisContext(): array
    {
        return [
            'FiscalYearID' => (int) SessionHelper::get('FiscalYearID', 0),
            'VersionID' => (int) SessionHelper::get('VersionID', 0),
            'DataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
        ];
    }

    private function installScriptPath(): string
    {
        return 'backend-php/config/sql/create_ai_dataset_analysis_v1.sql';
    }
}
