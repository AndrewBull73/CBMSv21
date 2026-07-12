<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\IntelligencePlatformModel;
use App\Services\AI\OpenAIProvider;
use App\Services\Intelligence\IntelligenceEngineClient;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/env.php';

final class IntelligencePlatformController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['INTEL_VIEW', 'INTEL_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'config' => ['auth' => true, 'permsAny' => ['INTEL_ADMIN', 'AI_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'health' => ['auth' => true, 'permsAny' => ['INTEL_VIEW', 'INTEL_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'health-check' => ['auth' => true, 'permsAny' => ['INTEL_VIEW', 'INTEL_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'runs' => ['auth' => true, 'permsAny' => ['AI_VIEW_AUDIT', 'INTEL_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'budget-ledger-version-roles' => ['auth' => true, 'permsAny' => ['INTEL_ADMIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-budget-ledger-version-roles' => ['auth' => true, 'permsAny' => ['INTEL_ADMIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-models' => ['auth' => true, 'permsAny' => ['ML_VIEW', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-model-form' => ['auth' => true, 'permsAny' => ['ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-model-detail' => ['auth' => true, 'permsAny' => ['ML_VIEW', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-prediction-drill' => ['auth' => true, 'permsAny' => ['ML_VIEW', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-ml-model' => ['auth' => true, 'permsAny' => ['ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'approve-ml-model' => ['auth' => true, 'permsAny' => ['ML_APPROVE', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-workflow-action' => ['auth' => true, 'permsAny' => ['ML_ADMIN', 'ML_APPROVE', 'ADMIN_ALL', 'SYSADMIN']],
        'ml-prediction-workflow-action' => ['auth' => true, 'permsAny' => ['ML_ADMIN', 'ML_APPROVE', 'ML_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'queue-ml-training' => ['auth' => true, 'permsAny' => ['ML_TRAIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'run-ml-predictions' => ['auth' => true, 'permsAny' => ['ML_TRAIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'interpret-ml-results' => ['auth' => true, 'permsAny' => ['AI_USE_EXTERNAL', 'AI_ADMIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    private IntelligencePlatformModel $model;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/IntelligencePlatformModel.php';
        require_once __DIR__ . '/../Services/Intelligence/IntelligenceEngineClient.php';
        require_once __DIR__ . '/../Services/AI/AIProviderInterface.php';
        require_once __DIR__ . '/../Services/AI/OpenAIProvider.php';

        $this->model = new IntelligencePlatformModel($conn);
    }

    public function index(): void
    {
        $this->render('intelligence/Dashboard', [
            'title' => 'Intelligence Engine',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'summary' => $this->model->summary(),
            'csrf' => csrf_token(),
            'engineUrl' => $this->engineUrl(),
        ]);
    }

    public function health(): void
    {
        $this->render('intelligence/Health', [
            'title' => 'Intelligence Engine Health',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'csrf' => csrf_token(),
            'engineUrl' => $this->engineUrl(),
        ]);
    }

    public function healthCheck(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Security check failed.']);
            return;
        }

        $started = microtime(true);
        try {
            $response = $this->client()->health();
            $duration = (int) round((microtime(true) - $started) * 1000);
            $this->model->logEngineRun([
                'RunTypeCode' => 'HEALTH_CHECK',
                'RequestJson' => json_encode(['url' => $this->engineUrl()], JSON_UNESCAPED_SLASHES),
                'ResponseJson' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'StatusCode' => 'SUCCESS',
                'ResponseTimeMs' => $duration,
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            echo json_encode(['ok' => true, 'duration_ms' => $duration, 'response' => $response], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $started) * 1000);
            $this->model->logEngineRun([
                'RunTypeCode' => 'HEALTH_CHECK',
                'RequestJson' => json_encode(['url' => $this->engineUrl()], JSON_UNESCAPED_SLASHES),
                'StatusCode' => 'ERROR',
                'ErrorMessage' => $e->getMessage(),
                'ResponseTimeMs' => $duration,
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            echo json_encode(['ok' => false, 'duration_ms' => $duration, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    public function config(): void
    {
        $this->render('intelligence/Config', [
            'title' => 'Intelligence and AI Configuration',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'engineUrl' => $this->engineUrl(),
            'providers' => $this->model->listProviders(),
            'moduleSettings' => $this->model->listModuleSettings(),
        ]);
    }

    public function runs(): void
    {
        $this->render('intelligence/Runs', [
            'title' => 'Intelligence Engine Runs',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'rows' => $this->model->recentRuns(50),
        ]);
    }

    public function budgetLedgerVersionRoles(): void
    {
        $fiscalYears = $this->model->listBudgetLedgerVersionRoleFiscalYears();
        $selectedFiscalYear = (int) ($_GET['FiscalYearID'] ?? 0);
        if ($selectedFiscalYear <= 0 && $fiscalYears !== []) {
            $selectedFiscalYear = (int) ($fiscalYears[0]['FiscalYearID'] ?? 0);
        }

        $this->render('intelligence/BudgetLedgerVersionRoles', [
            'title' => 'Budget Ledger Version Roles',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'rolesInstalled' => $this->model->supportsBudgetLedgerVersionRoles(),
            'installScriptPath' => 'backend-php/config/sql/create_ai_budget_ledger_version_role_mapping_v1.sql',
            'fiscalYears' => $fiscalYears,
            'selectedFiscalYear' => $selectedFiscalYear,
            'candidates' => $selectedFiscalYear > 0 ? $this->model->listBudgetLedgerVersionRoleCandidates($selectedFiscalYear) : [],
            'mappings' => $selectedFiscalYear > 0 ? $this->model->listBudgetLedgerVersionRoleMappings($selectedFiscalYear) : [],
            'csrf' => csrf_token(),
        ]);
    }

    public function saveBudgetLedgerVersionRoles(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/budget-ledger-version-roles');
        $fiscalYearId = (int) ($_POST['FiscalYearID'] ?? 0);
        try {
            $this->model->saveBudgetLedgerVersionRoles(
                $fiscalYearId,
                (int) ($_POST['BudgetBaselineVersionID'] ?? 0),
                (int) ($_POST['ExecutionActualsVersionID'] ?? 0)
            );
            $this->flashSuccess('Budget ledger version roles saved.');
        } catch (\Throwable $e) {
            $this->flashError('Version role save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=intelligence/budget-ledger-version-roles&FiscalYearID=' . $fiscalYearId);
    }

    public function mlModels(): void
    {
        $this->render('intelligence/MLModels', [
            'title' => 'ML Model Register',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'rows' => $this->model->listMLModels(),
            'canAdmin' => Rbac::canAny(['ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'canApprove' => Rbac::canAny(['ML_APPROVE', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'canTrain' => Rbac::canAny(['ML_TRAIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'csrf' => csrf_token(),
        ]);
    }

    public function mlModelForm(): void
    {
        $modelId = (int) ($_GET['id'] ?? 0);
        $model = $modelId > 0 ? $this->model->getMLModel($modelId) : null;
        $this->render('intelligence/MLModelForm', [
            'title' => $model ? 'Edit ML Model' : 'Register ML Model',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'model' => $model,
            'featureColumns' => $this->featureColumnsText($model['FeatureColumnsJson'] ?? null),
            'csrf' => csrf_token(),
        ]);
    }

    public function mlModelDetail(): void
    {
        $modelId = (int) ($_GET['id'] ?? 0);
        $model = $this->model->getMLModel($modelId);
        if ($model === null) {
            $this->flashError('ML model was not found.');
            header('Location: index.php?route=intelligence/ml-models');
            return;
        }
        $this->render('intelligence/MLModelDetail', [
            'title' => 'ML Model Detail',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'model' => $model,
            'featureColumns' => $this->featureColumnsList($model['FeatureColumnsJson'] ?? null),
            'trainingRuns' => $this->model->recentMLTrainingRuns($modelId, 20),
            'predictions' => $this->model->recentMLPredictions($modelId, 20),
            'interpretations' => $this->model->recentMLInterpretations($modelId, 5),
            'workflowEvents' => $this->model->recentMLWorkflowEvents($modelId, 50),
            'canAdmin' => Rbac::canAny(['ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'canApprove' => Rbac::canAny(['ML_APPROVE', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'canTrain' => Rbac::canAny(['ML_TRAIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'canInterpret' => Rbac::canAny(['AI_USE_EXTERNAL', 'AI_ADMIN', 'ML_ADMIN', 'ADMIN_ALL', 'SYSADMIN']),
            'csrf' => csrf_token(),
        ]);
    }

    public function mlPredictionDrill(): void
    {
        $predictionId = (int) ($_GET['id'] ?? 0);
        $prediction = $this->model->getMLPrediction($predictionId);
        if ($prediction === null) {
            $this->flashError('Prediction was not found.');
            header('Location: index.php?route=intelligence/ml-models');
            return;
        }

        $modelId = (int) ($prediction['MLModelID'] ?? 0);
        $model = $this->model->getMLModel($modelId);
        if ($model === null) {
            $this->flashError('ML model was not found.');
            header('Location: index.php?route=intelligence/ml-models');
            return;
        }

        $this->render('intelligence/MLPredictionDrill', [
            'title' => 'ML Prediction Drill Through',
            'foundationInstalled' => $this->model->supportsIntelligencePlatform(),
            'installScriptPath' => $this->installScriptPath(),
            'model' => $model,
            'prediction' => $prediction,
            'drillRows' => $this->model->listMLPredictionDrillRows($predictionId, 100),
            'underlyingRows' => $this->model->listMLPredictionUnderlyingLedgerRows($prediction, 200),
            'workflowEvents' => $this->model->recentMLPredictionWorkflowEvents($predictionId, 50),
            'csrf' => csrf_token(),
        ]);
    }

    public function saveMLModel(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        try {
            $payload = $_POST;
            $payload['ActiveFlag'] = isset($_POST['ActiveFlag']) ? 1 : 0;
            $id = $this->model->saveMLModel($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('ML model saved.');
            header('Location: index.php?route=intelligence/ml-model-detail&id=' . $id);
        } catch (\Throwable $e) {
            $this->flashError('ML model save failed: ' . $e->getMessage());
            header('Location: index.php?route=intelligence/ml-model-form&id=' . (int) ($_POST['MLModelID'] ?? 0));
        }
    }

    public function approveMLModel(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        try {
            $this->model->approveMLModel($modelId, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('ML model approved.');
        } catch (\Throwable $e) {
            $this->flashError('ML approval failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=intelligence/ml-model-detail&id=' . $modelId);
    }

    public function mlWorkflowAction(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        $actionCode = trim((string) ($_POST['ActionCode'] ?? ''));
        $notes = trim((string) ($_POST['Notes'] ?? ''));

        try {
            if ($modelId <= 0) {
                throw new \InvalidArgumentException('ML model was not supplied.');
            }
            $toStatus = $this->model->applyMLWorkflowAction(
                $modelId,
                $actionCode,
                $notes !== '' ? $notes : null,
                (int) SessionHelper::get('auth.user_id', 0)
            );
            $this->flashSuccess('Workflow action recorded. Model status is now ' . $toStatus . '.');
        } catch (\Throwable $e) {
            $this->flashError('Workflow action failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=intelligence/ml-model-detail&id=' . $modelId);
    }

    public function mlPredictionWorkflowAction(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $predictionId = (int) ($_POST['MLPredictionID'] ?? 0);
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        try {
            $this->model->applyMLPredictionWorkflowAction(
                $predictionId,
                trim((string) ($_POST['ActionCode'] ?? '')),
                trim((string) ($_POST['Notes'] ?? '')) ?: null,
                (int) SessionHelper::get('auth.user_id', 0)
            );
            $this->flashSuccess('Prediction workflow action recorded.');
        } catch (\Throwable $e) {
            $this->flashError('Prediction workflow action failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=intelligence/ml-prediction-drill&id=' . $predictionId . ($modelId > 0 ? '&model_id=' . $modelId : ''));
    }

    public function queueMLTraining(): void
    {
        $this->allowLongMLRequest();
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        $runId = 0;
        try {
            $runId = $this->model->createMLTrainingRun(
                $modelId,
                trim((string) ($_POST['TrainingPeriodStart'] ?? '')) ?: null,
                trim((string) ($_POST['TrainingPeriodEnd'] ?? '')) ?: null
            );
            $model = $this->model->getMLModel($modelId);
            if ($model === null) {
                throw new \RuntimeException('ML model was not found.');
            }
            $this->model->markMLTrainingRunRunning($runId);
            $payload = $this->model->buildMLTrainingPayload($model, (int) (envStr('ML_TRAINING_MAX_ROWS', '5000') ?? '5000'));
            $started = microtime(true);
            $response = $this->client()->trainModel($payload);
            $duration = (int) round((microtime(true) - $started) * 1000);
            if ((bool) ($response['ok'] ?? false) !== true) {
                throw new \RuntimeException((string) ($response['message'] ?? 'Training engine did not complete the run.'));
            }
            $this->model->completeMLTrainingRun($modelId, $runId, $response);
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_TRAINING',
                'RequestJson' => json_encode([
                    'MLModelID' => $modelId,
                    'MLTrainingRunID' => $runId,
                    'model_code' => $payload['model_code'] ?? null,
                    'row_count' => count(is_array($payload['rows'] ?? null) ? $payload['rows'] : []),
                ], JSON_UNESCAPED_SLASHES),
                'ResponseJson' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'StatusCode' => 'SUCCESS',
                'ResponseTimeMs' => $duration,
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashSuccess('Training run completed.');
        } catch (\Throwable $e) {
            if ($runId > 0) {
                $this->model->failMLTrainingRun($runId, $e->getMessage());
            }
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_TRAINING',
                'RequestJson' => json_encode(['MLModelID' => $modelId, 'MLTrainingRunID' => $runId], JSON_UNESCAPED_SLASHES),
                'StatusCode' => 'ERROR',
                'ErrorMessage' => $e->getMessage(),
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashError('Training run failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=intelligence/ml-model-detail&id=' . $modelId);
    }

    public function runMLPredictions(): void
    {
        $this->allowLongMLRequest();
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        try {
            $model = $this->model->getMLModel($modelId);
            if ($model === null) {
                throw new \RuntimeException('ML model was not found.');
            }
            $trainingRun = $this->model->latestCompletedMLTrainingRun($modelId);
            if ($trainingRun === null) {
                throw new \RuntimeException('Run training successfully before generating predictions.');
            }

            $payload = $this->model->buildMLPredictionPayload($model, $trainingRun, (int) (envStr('ML_PREDICTION_MAX_ROWS', '200') ?? '200'));
            $started = microtime(true);
            $response = $this->client()->predictModel($payload);
            $duration = (int) round((microtime(true) - $started) * 1000);
            if ((bool) ($response['ok'] ?? false) !== true) {
                throw new \RuntimeException((string) ($response['message'] ?? 'Prediction engine did not complete the run.'));
            }

            $predictions = is_array($response['predictions'] ?? null) ? $response['predictions'] : [];
            $count = $this->model->storeMLPredictions($modelId, $predictions);
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_PREDICTION',
                'RequestJson' => json_encode([
                    'MLModelID' => $modelId,
                    'MLTrainingRunID' => (int) ($trainingRun['MLTrainingRunID'] ?? 0),
                    'model_code' => $payload['model_code'] ?? null,
                    'row_count' => count(is_array($payload['rows'] ?? null) ? $payload['rows'] : []),
                ], JSON_UNESCAPED_SLASHES),
                'ResponseJson' => json_encode([
                    'prediction_count' => $count,
                    'engine_prediction_count' => (int) ($response['prediction_count'] ?? 0),
                ] + $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'StatusCode' => 'SUCCESS',
                'ResponseTimeMs' => $duration,
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashSuccess('Generated ' . $count . ' prediction(s).');
        } catch (\Throwable $e) {
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_PREDICTION',
                'RequestJson' => json_encode(['MLModelID' => $modelId], JSON_UNESCAPED_SLASHES),
                'StatusCode' => 'ERROR',
                'ErrorMessage' => $e->getMessage(),
                'ProviderCode' => 'INTELLIGENCE_ENGINE',
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashError('Prediction run failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=intelligence/ml-model-detail&id=' . $modelId);
    }

    public function interpretMLResults(): void
    {
        $this->assertPostWithCsrf('index.php?route=intelligence/ml-models');
        $modelId = (int) ($_POST['MLModelID'] ?? 0);
        try {
            $model = $this->model->getMLModel($modelId);
            if ($model === null) {
                throw new \RuntimeException('ML model was not found.');
            }
            $trainingRun = $this->model->latestCompletedMLTrainingRun($modelId);
            if ($trainingRun === null) {
                throw new \RuntimeException('Run training successfully before requesting AI interpretation.');
            }
            $predictions = $this->model->recentMLPredictions($modelId, 20);
            if ($predictions === []) {
                throw new \RuntimeException('Run predictions before requesting AI interpretation.');
            }

            $input = $this->buildMLInterpretationInput($model, $trainingRun, $predictions);
            $started = microtime(true);
            $result = $this->aiProvider()->generate($this->mlInterpretationInstructions(), json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $duration = (int) round((microtime(true) - $started) * 1000);
            $text = trim((string) ($result['text'] ?? ''));
            if ($text === '') {
                throw new \RuntimeException('AI provider returned an empty interpretation.');
            }

            $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_AI_INTERPRETATION',
                'RequestJson' => json_encode([
                    'MLModelID' => $modelId,
                    'MLTrainingRunID' => (int) ($trainingRun['MLTrainingRunID'] ?? 0),
                    'prediction_count' => count($predictions),
                ], JSON_UNESCAPED_SLASHES),
                'ResponseJson' => json_encode([
                    'interpretation' => $text,
                    'provider' => $this->aiProvider()->code(),
                    'model' => $this->aiProvider()->model(),
                    'usage' => $usage,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'StatusCode' => 'SUCCESS',
                'ResponseTimeMs' => $duration,
                'ProviderCode' => 'OPENAI',
                'ExternalServiceUsed' => 1,
                'DataMaskingUsed' => 1,
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashSuccess('AI interpretation generated.');
        } catch (\Throwable $e) {
            $this->model->logEngineRun([
                'RunTypeCode' => 'ML_AI_INTERPRETATION',
                'RequestJson' => json_encode(['MLModelID' => $modelId], JSON_UNESCAPED_SLASHES),
                'StatusCode' => 'ERROR',
                'ErrorMessage' => $e->getMessage(),
                'ProviderCode' => 'OPENAI',
                'ExternalServiceUsed' => 1,
                'DataMaskingUsed' => 1,
                'CreatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashError('AI interpretation failed: ' . $e->getMessage());
        }
        header('Location: index.php?route=intelligence/ml-model-detail&id=' . $modelId);
    }

    private function client(): IntelligenceEngineClient
    {
        return new IntelligenceEngineClient(
            $this->engineUrl(),
            envStr('INTELLIGENCE_ENGINE_API_KEY', ''),
            max(5, min(900, (int) (envStr('INTELLIGENCE_ENGINE_TIMEOUT', '300') ?? '300')))
        );
    }

    private function allowLongMLRequest(): void
    {
        $seconds = max(120, (int) (envStr('ML_WEB_MAX_SECONDS', '900') ?? '900'));
        @ini_set('max_execution_time', (string) $seconds);
        @set_time_limit($seconds);
    }

    private function aiProvider(): OpenAIProvider
    {
        return new OpenAIProvider(
            envStr('OPENAI_API_KEY', ''),
            envStr('OPENAI_MODEL', 'gpt-4.1') ?? 'gpt-4.1',
            envStr('OPENAI_BASE_URL', 'https://api.openai.com/v1') ?? 'https://api.openai.com/v1',
            (int) (envStr('OPENAI_TIMEOUT', '60') ?? '60'),
            (int) (envStr('OPENAI_CONNECT_TIMEOUT', '20') ?? '20')
        );
    }

    private function mlInterpretationInstructions(): string
    {
        return implode("\n", [
            'You are interpreting CBMS machine-learning outputs for senior public finance executives.',
            'Use cautious, audit-friendly language. Do not overstate causality.',
            'Explain what the model appears to show, major risks, possible operational explanations, and recommended review actions.',
            'Mention model limitations and confidence caveats.',
            'Do not expose raw sensitive row-level data beyond the supplied aggregate-like summaries.',
            'Return concise markdown with sections: Executive Readout, Key Risks, Possible Explanations, Recommended Actions, Caveats.',
        ]);
    }

    private function buildMLInterpretationInput(array $model, array $trainingRun, array $predictions): array
    {
        $training = json_decode((string) ($trainingRun['MetricsJson'] ?? ''), true);
        $predictionSummaries = [];
        foreach ($predictions as $prediction) {
            $details = json_decode((string) ($prediction['PredictionJson'] ?? ''), true);
            $predictionSummaries[] = [
                'entity_type' => (string) ($prediction['EntityTypeCode'] ?? ''),
                'entity_code' => (string) ($prediction['EntityCode'] ?? ''),
                'expected' => $prediction['PredictionValue'] ?? null,
                'risk_score' => $prediction['RiskScore'] ?? null,
                'confidence_score' => $prediction['ConfidenceScore'] ?? null,
                'actual' => is_array($details) ? ($details['actual_value'] ?? null) : null,
                'variance' => is_array($details) ? ($details['variance_amount'] ?? null) : null,
                'risk_level' => is_array($details) ? ($details['risk_level'] ?? null) : null,
                'driver' => is_array($details) ? ($details['selected_feature'] ?? null) : null,
                'interpretation' => is_array($details) ? ($details['interpretation'] ?? null) : null,
            ];
        }

        return [
            'model' => [
                'code' => (string) ($model['ModelCode'] ?? ''),
                'name' => (string) ($model['ModelName'] ?? ''),
                'use_case' => (string) ($model['UseCaseCode'] ?? ''),
                'type' => (string) ($model['ModelTypeCode'] ?? ''),
                'target_column' => (string) ($model['TargetColumnName'] ?? ''),
                'features' => $this->featureColumnsList($model['FeatureColumnsJson'] ?? null),
                'approved_source' => (string) ($model['ApprovedViewName'] ?? ''),
            ],
            'latest_training' => [
                'run_id' => (int) ($trainingRun['MLTrainingRunID'] ?? 0),
                'completed_date' => (string) ($trainingRun['CompletedDate'] ?? ''),
                'metrics' => is_array($training['metrics'] ?? null) ? $training['metrics'] : [],
                'algorithm' => (string) ($training['algorithm'] ?? ''),
                'top_terms' => is_array($training['model_artifact']['top_terms'] ?? null) ? array_slice($training['model_artifact']['top_terms'], 0, 10) : [],
            ],
            'recent_predictions' => $predictionSummaries,
        ];
    }

    private function engineUrl(): string
    {
        return trim((string) envStr('INTELLIGENCE_ENGINE_URL', 'http://127.0.0.1:8010'));
    }

    private function installScriptPath(): string
    {
        return 'backend-php/config/sql/create_intelligence_platform_foundation_v1.sql';
    }

    private function featureColumnsText(mixed $json): string
    {
        return implode("\n", $this->featureColumnsList($json));
    }

    private function featureColumnsList(mixed $json): array
    {
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static fn (string $value): bool => trim($value) !== ''));
    }
}
