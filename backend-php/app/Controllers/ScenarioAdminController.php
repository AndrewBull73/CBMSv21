<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScenarioAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class ScenarioAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['CALC_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
    ];

    public function index(): void
    {
        $model = $this->buildModel();

        $this->render('scenarioadmin/Index', [
            'title' => 'Scenario Config',
            'models' => $model->listModelsSummary(),
        ]);
    }

    public function detail(): void
    {
        $calcModelId = (int) ($_GET['id'] ?? 0);
        if ($calcModelId <= 0) {
            $this->flashError('Invalid calculation model.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $model = $this->buildModel();
        $calcModel = $model->findModel($calcModelId);

        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $this->render('scenarioadmin/Detail', [
            'title' => 'Scenario Config',
            'calcModel' => $calcModel,
            'counts' => $model->getModelCounts($calcModelId),
            'scenarios' => $model->listScenariosByModel($calcModelId),
            'nodes' => $model->listNodesByModel($calcModelId),
            'dependencies' => $model->listDependenciesByModel($calcModelId),
            'recentRuns' => $model->listRecentRunsByModel($calcModelId),
            'recentPublishes' => $model->listRecentPublishesByModel($calcModelId),
        ]);
    }

    public function model(): void
    {
        $calcModelId = (int) ($_GET['id'] ?? 0);
        $model = $this->buildModel();
        $calcModel = $calcModelId > 0 ? $model->findModel($calcModelId) : null;

        $this->render('scenarioadmin/ModelForm', [
            'title' => $calcModelId > 0 ? 'Edit Scenario Model' : 'Create Scenario Model',
            'calcModel' => $calcModel,
            'statusOptions' => ['DRAFT', 'ACTIVE', 'ARCHIVED'],
        ]);
    }

    public function saveModel(): void
    {
        $this->requirePostWithCsrf();

        $data = [
            'CalcModelID' => (int) ($_POST['CalcModelID'] ?? 0),
            'ModelCode' => trim((string) ($_POST['ModelCode'] ?? '')),
            'ModelName' => trim((string) ($_POST['ModelName'] ?? '')),
            'ModelVersion' => max(1, (int) ($_POST['ModelVersion'] ?? 1)),
            'StatusCode' => trim((string) ($_POST['StatusCode'] ?? 'DRAFT')),
            'EffectiveFrom' => $this->nullableDate($_POST['EffectiveFrom'] ?? null),
            'EffectiveTo' => $this->nullableDate($_POST['EffectiveTo'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
        ];

        if ($data['ModelCode'] === '' || $data['ModelName'] === '') {
            $this->flashError('Model code and model name are required.');
            header('Location: index.php?route=scenario-admin/model' . ($data['CalcModelID'] > 0 ? '&id=' . $data['CalcModelID'] : ''));
            exit;
        }

        try {
            $model = $this->buildModel();
            $id = $model->saveModel($data, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Calculation model saved.');
            header('Location: index.php?route=scenario-admin/detail&id=' . $id);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to save calculation model: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/model' . ($data['CalcModelID'] > 0 ? '&id=' . $data['CalcModelID'] : ''));
            exit;
        }
    }

    public function scenario(): void
    {
        $scenarioId = (int) ($_GET['id'] ?? 0);
        $calcModelId = (int) ($_GET['model_id'] ?? 0);
        $model = $this->buildModel();
        $scenario = $scenarioId > 0 ? $model->findScenario($scenarioId) : null;

        if ($scenario !== null) {
            $calcModelId = (int) $scenario['CalcModelID'];
        }

        if ($calcModelId <= 0) {
            $this->flashError('A calculation model is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModel = $model->findModel($calcModelId);
        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $this->render('scenarioadmin/ScenarioForm', [
            'title' => $scenarioId > 0 ? 'Edit Scenario' : 'Create Scenario',
            'calcModel' => $calcModel,
            'scenario' => $scenario,
            'parentOptions' => $model->listScenarioOptions($calcModelId, $scenarioId),
            'scenarioTypeOptions' => ['BASE', 'FORECAST', 'WHATIF', 'PLAN'],
            'scenarioStatusOptions' => ['DRAFT', 'ACTIVE', 'LOCKED', 'ARCHIVED'],
        ]);
    }

    public function saveScenario(): void
    {
        $this->requirePostWithCsrf();

        $data = [
            'ScenarioID' => (int) ($_POST['ScenarioID'] ?? 0),
            'CalcModelID' => (int) ($_POST['CalcModelID'] ?? 0),
            'ParentScenarioID' => $this->nullableInt($_POST['ParentScenarioID'] ?? null),
            'ScenarioCode' => trim((string) ($_POST['ScenarioCode'] ?? '')),
            'ScenarioName' => trim((string) ($_POST['ScenarioName'] ?? '')),
            'ScenarioTypeCode' => trim((string) ($_POST['ScenarioTypeCode'] ?? 'BASE')),
            'ScenarioStatusCode' => trim((string) ($_POST['ScenarioStatusCode'] ?? 'DRAFT')),
            'SortOrder' => max(1, (int) ($_POST['SortOrder'] ?? 100)),
            'LockedFlag' => isset($_POST['LockedFlag']) ? 1 : 0,
            'ApprovedFlag' => isset($_POST['ApprovedFlag']) ? 1 : 0,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
        ];

        if ($data['CalcModelID'] <= 0 || $data['ScenarioCode'] === '' || $data['ScenarioName'] === '') {
            $this->flashError('Model, scenario code, and scenario name are required.');
            $redirect = 'index.php?route=scenario-admin/scenario&model_id=' . $data['CalcModelID'];
            if ($data['ScenarioID'] > 0) {
                $redirect .= '&id=' . $data['ScenarioID'];
            }
            header('Location: ' . $redirect);
            exit;
        }

        try {
            $model = $this->buildModel();
            $model->saveScenario($data, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Scenario saved.');
            header('Location: index.php?route=scenario-admin/detail&id=' . $data['CalcModelID']);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to save scenario: ' . $e->getMessage());
            $redirect = 'index.php?route=scenario-admin/scenario&model_id=' . $data['CalcModelID'];
            if ($data['ScenarioID'] > 0) {
                $redirect .= '&id=' . $data['ScenarioID'];
            }
            header('Location: ' . $redirect);
            exit;
        }
    }

    public function values(): void
    {
        $scenarioId = (int) ($_GET['scenario_id'] ?? $_GET['id'] ?? 0);
        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $model = $this->buildModel();
        $scenario = $model->findScenario($scenarioId);
        if ($scenario === null) {
            $this->flashError('Scenario not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModel = $model->findModel((int) $scenario['CalcModelID']);
        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $costObjects = $model->listCostObjectsByModel((int) $scenario['CalcModelID']);
        $selectedCostObjectId = (int) ($_GET['cost_object_id'] ?? 0);
        if ($selectedCostObjectId <= 0 && $costObjects !== []) {
            $selectedCostObjectId = (int) ($costObjects[0]['CostObjectID'] ?? 0);
        }
        $search = trim((string) ($_GET['q'] ?? ''));

        $editor = $model->getScenarioValuesEditor(
            $scenarioId,
            $selectedCostObjectId,
            $search
        );

        $this->render('scenarioadmin/Values', [
            'title' => 'Scenario Values',
            'calcModel' => $calcModel,
            'scenario' => $scenario,
            'costObjects' => $costObjects,
            'selectedCostObjectId' => $selectedCostObjectId,
            'search' => $search,
            'periods' => $editor['periods'],
            'nodes' => $editor['nodes'],
            'values' => $editor['values'],
        ]);
    }

    public function saveValues(): void
    {
        $this->requirePostWithCsrf();

        $scenarioId = (int) ($_POST['ScenarioID'] ?? 0);
        $costObjectId = (int) ($_POST['CostObjectID'] ?? 0);
        $search = trim((string) ($_POST['q'] ?? ''));
        $values = is_array($_POST['values'] ?? null) ? $_POST['values'] : [];

        if ($scenarioId <= 0 || $costObjectId <= 0) {
            $this->flashError('Scenario and cost object are required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $model->saveScenarioValues(
                $scenarioId,
                $costObjectId,
                $values,
                (int) SessionHelper::get('auth.user_id', 0)
            );
            $this->flashSuccess('Scenario values saved.');
        } catch (\Throwable $e) {
            $this->flashError('Failed to save scenario values: ' . $e->getMessage());
        }

        $redirect = 'index.php?route=scenario-admin/values&scenario_id=' . $scenarioId . '&cost_object_id=' . $costObjectId;
        if ($search !== '') {
            $redirect .= '&q=' . urlencode($search);
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function rateOverrides(): void
    {
        $scenarioId = (int) ($_GET['scenario_id'] ?? $_GET['id'] ?? 0);
        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $model = $this->buildModel();
        $scenario = $model->findScenario($scenarioId);
        if ($scenario === null) {
            $this->flashError('Scenario not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModel = $model->findModel((int) $scenario['CalcModelID']);
        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $rateCode = trim((string) ($_GET['rate_code'] ?? ''));
        $dataObjectCode = trim((string) ($_GET['data_object_code'] ?? ''));
        $editor = $model->getScenarioRateOverrideEditor($scenarioId, $rateCode, $dataObjectCode);

        $this->render('scenarioadmin/RateOverrides', [
            'title' => 'Scenario Rate Overrides',
            'calcModel' => $calcModel,
            'scenario' => $scenario,
            'filters' => [
                'rate_code' => $rateCode,
                'data_object_code' => $dataObjectCode,
            ],
            'contexts' => $editor['contexts'],
            'rows' => $editor['rows'],
        ]);
    }

    public function saveRateOverrides(): void
    {
        $this->requirePostWithCsrf();

        $scenarioId = (int) ($_POST['ScenarioID'] ?? 0);
        $rateCode = trim((string) ($_POST['rate_code'] ?? ''));
        $dataObjectCode = trim((string) ($_POST['data_object_code'] ?? ''));
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];

        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $model->saveScenarioRateOverrides($scenarioId, $rows, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Scenario rate overrides saved.');
        } catch (\Throwable $e) {
            $this->flashError('Failed to save scenario rate overrides: ' . $e->getMessage());
        }

        $redirect = 'index.php?route=scenario-admin/rate-overrides&scenario_id=' . $scenarioId;
        if ($rateCode !== '') {
            $redirect .= '&rate_code=' . urlencode($rateCode);
        }
        if ($dataObjectCode !== '') {
            $redirect .= '&data_object_code=' . urlencode($dataObjectCode);
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function runScenario(): void
    {
        $this->requirePostWithCsrf();

        $scenarioId = (int) ($_POST['scenario_id'] ?? 0);
        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $scenario = $model->findScenario($scenarioId);
            if ($scenario === null) {
                throw new \RuntimeException('Scenario not found.');
            }

            $calcModel = $model->findModel((int) ($scenario['CalcModelID'] ?? 0));
            if ($calcModel === null) {
                throw new \RuntimeException('Calculation model not found.');
            }

            $result = $this->runScenarioEngineCommand([
                'execute-model',
                (string) ($calcModel['ModelCode'] ?? ''),
                (string) ($scenario['ScenarioCode'] ?? ''),
            ]);

            if (($result['exitCode'] ?? 1) === 0) {
                $this->flashSuccess('Scenario run completed. ' . ($result['summary'] ?? ''));
            } else {
                $this->flashError('Scenario run failed: ' . ($result['summary'] ?? 'Unknown error.'));
            }

            header('Location: index.php?route=scenario-admin/detail&id=' . (int) $scenario['CalcModelID']);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Scenario run failed: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function publishScenario(): void
    {
        $this->requirePostWithCsrf();

        $scenarioId = (int) ($_POST['scenario_id'] ?? 0);
        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $scenario = $model->findScenario($scenarioId);
            if ($scenario === null) {
                throw new \RuntimeException('Scenario not found.');
            }

            $calcModel = $model->findModel((int) ($scenario['CalcModelID'] ?? 0));
            if ($calcModel === null) {
                throw new \RuntimeException('Calculation model not found.');
            }

            $result = $this->runScenarioEngineCommand([
                'publish-latest',
                (string) ($calcModel['ModelCode'] ?? ''),
                (string) ($scenario['ScenarioCode'] ?? ''),
            ]);

            if (($result['exitCode'] ?? 1) === 0) {
                $this->flashSuccess('Scenario published. ' . ($result['summary'] ?? ''));
            } else {
                $this->flashError('Scenario publish failed: ' . ($result['summary'] ?? 'Unknown error.'));
            }

            header('Location: index.php?route=scenario-admin/detail&id=' . (int) $scenario['CalcModelID']);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Scenario publish failed: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function resetScenario(): void
    {
        $this->requirePostWithCsrf();

        $scenarioId = (int) ($_POST['scenario_id'] ?? 0);
        if ($scenarioId <= 0) {
            $this->flashError('A scenario is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $scenario = $model->findScenario($scenarioId);
            if ($scenario === null) {
                throw new \RuntimeException('Scenario not found.');
            }

            if ((int) ($scenario['ParentScenarioID'] ?? 0) <= 0) {
                throw new \RuntimeException('Only child scenarios can be reset back to the inherited base configuration.');
            }

            $model->resetScenarioOverrides($scenarioId);
            $this->flashSuccess('Scenario overrides were cleared. This scenario now inherits the parent/base configuration again.');
            header('Location: index.php?route=scenario-admin/detail&id=' . (int) $scenario['CalcModelID']);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to reset scenario: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function resetModelScenarios(): void
    {
        $this->requirePostWithCsrf();

        $calcModelId = (int) ($_POST['calc_model_id'] ?? 0);
        if ($calcModelId <= 0) {
            $this->flashError('A calculation model is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $calcModel = $model->findModel($calcModelId);
            if ($calcModel === null) {
                throw new \RuntimeException('Calculation model not found.');
            }

            $model->resetModelScenarios($calcModelId);
            $syncSummary = null;
            try {
                $syncSummary = $model->syncLegacyFormulaChainForModel($calcModelId, (int) SessionHelper::get('auth.user_id', 0));
            } catch (\Throwable $syncError) {
                $syncSummary = null;
            }

            if ($syncSummary !== null) {
                $this->flashSuccess(
                    'Scenario setup reset. This model now has one clean BASE scenario, no scenario history or overrides, and refreshed formulas from the legacy chain. ' .
                    'Updated ' . $syncSummary['updated_formulas'] . ' formula(s) across ' . $syncSummary['updated_models'] . ' model(s).'
                );
            } else {
                $this->flashSuccess('Scenario setup reset. This model now has one clean BASE scenario and no scenario history or overrides.');
            }
            header('Location: index.php?route=scenario-admin/detail&id=' . $calcModelId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to reset model scenarios: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function syncLegacyFormulas(): void
    {
        $this->requirePostWithCsrf();

        $calcModelId = (int) ($_POST['calc_model_id'] ?? 0);
        if ($calcModelId <= 0) {
            $this->flashError('A calculation model is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $calcModel = $model->findModel($calcModelId);
            if ($calcModel === null) {
                throw new \RuntimeException('Calculation model not found.');
            }

            $summary = $model->syncLegacyFormulasForModel($calcModelId, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Legacy formulas synced. Updated ' . $summary['updated'] . ' scenario formula(s) from CalculationID ' . $summary['legacyCalculationId'] . '.');
            header('Location: index.php?route=scenario-admin/detail&id=' . $calcModelId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to sync legacy formulas: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function syncLegacyChain(): void
    {
        $this->requirePostWithCsrf();

        $calcModelId = (int) ($_POST['calc_model_id'] ?? 0);
        if ($calcModelId <= 0) {
            $this->flashError('A calculation model is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        try {
            $model = $this->buildModel();
            $calcModel = $model->findModel($calcModelId);
            if ($calcModel === null) {
                throw new \RuntimeException('Calculation model not found.');
            }

            $summary = $model->syncLegacyFormulaChainForModel($calcModelId, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess(
                'Legacy chain synced. Updated ' . $summary['updated_formulas'] .
                ' formula(s) across ' . $summary['updated_models'] .
                ' model(s) from chain starting CalculationID ' . $summary['root_legacy_calculation_id'] . '.'
            );
            header('Location: index.php?route=scenario-admin/detail&id=' . $calcModelId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to sync legacy chain: ' . $e->getMessage());
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    public function runDetail(): void
    {
        $runId = (int) ($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            $this->flashError('A scenario run is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $model = $this->buildModel();
        $run = $model->findRun($runId);
        if ($run === null) {
            $this->flashError('Scenario run not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModelId = (int) ($run['CalcModelID'] ?? 0);
        if ($calcModelId <= 0) {
            $this->flashError('Scenario run is not linked to a valid model.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModel = $model->findModel($calcModelId);
        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $this->render('scenarioadmin/RunDetail', [
            'title' => 'Scenario Run Detail',
            'calcModel' => $calcModel,
            'run' => $run,
            'errors' => $model->listRunErrors($runId),
            'results' => $model->listRunResults($runId),
        ]);
    }

    public function node(): void
    {
        $nodeId = (int) ($_GET['id'] ?? 0);
        $calcModelId = (int) ($_GET['model_id'] ?? 0);
        $model = $this->buildModel();
        $node = $nodeId > 0 ? $model->findNode($nodeId) : null;

        if ($node !== null) {
            $calcModelId = (int) $node['CalcModelID'];
        }

        if ($calcModelId <= 0) {
            $this->flashError('A calculation model is required.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $calcModel = $model->findModel($calcModelId);
        if ($calcModel === null) {
            $this->flashError('Calculation model not found.');
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }

        $formula = $nodeId > 0 ? $model->findFormulaByNodeId($nodeId) : null;
        $dependencies = $nodeId > 0 ? $model->listDependenciesByNode($nodeId) : [];

        $this->render('scenarioadmin/NodeForm', [
            'title' => $nodeId > 0 ? 'Edit Node' : 'Create Node',
            'calcModel' => $calcModel,
            'node' => $node,
            'formula' => $formula,
            'dependencies' => $dependencies,
            'nodeTypeOptions' => ['INPUT', 'FORMULA', 'RESULT', 'REVENUE', 'SUMMARY'],
            'nodeCategoryOptions' => ['GENERAL', 'COST', 'REVENUE', 'SUMMARY', 'DRIVER', 'METRIC'],
            'dataTypeOptions' => ['DECIMAL', 'INT', 'TEXT', 'BOOLEAN'],
        ]);
    }

    public function saveNode(): void
    {
        $this->requirePostWithCsrf();

        $data = [
            'NodeID' => (int) ($_POST['NodeID'] ?? 0),
            'CalcModelID' => (int) ($_POST['CalcModelID'] ?? 0),
            'NodeCode' => trim((string) ($_POST['NodeCode'] ?? '')),
            'NodeName' => trim((string) ($_POST['NodeName'] ?? '')),
            'NodeTypeCode' => trim((string) ($_POST['NodeTypeCode'] ?? 'INPUT')),
            'NodeCategoryCode' => trim((string) ($_POST['NodeCategoryCode'] ?? 'GENERAL')),
            'DataTypeCode' => trim((string) ($_POST['DataTypeCode'] ?? 'DECIMAL')),
            'UnitOfMeasureCode' => $this->nullableString($_POST['UnitOfMeasureCode'] ?? null),
            'DecimalScale' => max(0, min(6, (int) ($_POST['DecimalScale'] ?? 6))),
            'DefaultDecimalValue' => $this->nullableDecimal($_POST['DefaultDecimalValue'] ?? null),
            'DefaultTextValue' => $this->nullableString($_POST['DefaultTextValue'] ?? null),
            'DefaultBitValue' => $this->nullableBit($_POST['DefaultBitValue'] ?? null),
            'NodeOrder' => max(1, (int) ($_POST['NodeOrder'] ?? 100)),
            'OutputFlag' => isset($_POST['OutputFlag']) ? 1 : 0,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'ExpressionText' => trim((string) ($_POST['ExpressionText'] ?? '')),
            'FormulaActiveFlag' => isset($_POST['FormulaActiveFlag']) ? 1 : 0,
        ];

        if ($data['CalcModelID'] <= 0 || $data['NodeCode'] === '' || $data['NodeName'] === '') {
            $this->flashError('Model, node code, and node name are required.');
            $redirect = 'index.php?route=scenario-admin/node&model_id=' . $data['CalcModelID'];
            if ($data['NodeID'] > 0) {
                $redirect .= '&id=' . $data['NodeID'];
            }
            header('Location: ' . $redirect);
            exit;
        }

        if ($data['NodeTypeCode'] === 'FORMULA' && $data['ExpressionText'] === '') {
            $this->flashError('Formula nodes require an expression.');
            $redirect = 'index.php?route=scenario-admin/node&model_id=' . $data['CalcModelID'];
            if ($data['NodeID'] > 0) {
                $redirect .= '&id=' . $data['NodeID'];
            }
            header('Location: ' . $redirect);
            exit;
        }

        try {
            $model = $this->buildModel();
            $model->saveNode($data, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Node saved.');
            header('Location: index.php?route=scenario-admin/detail&id=' . $data['CalcModelID']);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Failed to save node: ' . $e->getMessage());
            $redirect = 'index.php?route=scenario-admin/node&model_id=' . $data['CalcModelID'];
            if ($data['NodeID'] > 0) {
                $redirect .= '&id=' . $data['NodeID'];
            }
            header('Location: ' . $redirect);
            exit;
        }
    }

    private function buildModel(): ScenarioAdminModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new ScenarioAdminModel($this->db);
    }

    private function requirePostWithCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            exit;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=scenario-admin/index');
            exit;
        }
    }

    private function runScenarioEngineCommand(array $arguments): array
    {
        $repoRoot = realpath(__DIR__ . '/../../..');
        if ($repoRoot === false) {
            throw new \RuntimeException('Could not resolve repository root.');
        }

        $projectPath = $repoRoot . DIRECTORY_SEPARATOR . 'scenario-engine' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'CBMS.ScenarioEngine.Runner';
        if (!is_dir($projectPath)) {
            throw new \RuntimeException('Scenario engine runner project was not found.');
        }

        $repoArg = '"' . str_replace('"', '""', $repoRoot) . '"';
        $projectArg = '"' . str_replace('"', '""', $projectPath) . '"';
        $escapedArgs = array_map(
            static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"',
            array_values(array_filter($arguments, static fn(string $value): bool => trim($value) !== ''))
        );

        $command = 'cmd /d /c "cd /d ' . $repoArg . ' && dotnet run --project ' . $projectArg . ' -- ' . implode(' ', $escapedArgs) . ' 2>&1"';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        $summary = '';
        foreach ($output as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $summary = $line;
        }

        return [
            'exitCode' => $exitCode,
            'summary' => $summary,
            'output' => $output,
        ];
    }

    private function nullableDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : (int) $value;
    }

    private function nullableDecimal($value): ?float
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : (float) $value;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableBit($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['1', 'true', 'yes'], true) ? 1 : 0;
    }
}
