<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TrainingCatalogAdminModel;
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

    private function model(): TrainingCatalogAdminModel
    {
        return new TrainingCatalogAdminModel($this->db);
    }

    private function completionModes(): array
    {
        return [
            'navigation' => 'navigation',
            'field_nonempty' => 'field_nonempty',
            'field_email' => 'field_email',
            'field_prefilled' => 'field_prefilled',
            'checkbox_checked' => 'checkbox_checked',
            'manual_continue' => 'manual_continue',
            'submit_success' => 'submit_success',
            'click_target' => 'click_target',
        ];
    }

    private function ensureCatalogInstalled(): void
    {
        if (!$this->model()->supportsScenarioCatalog()) {
            throw new \RuntimeException('Training catalogue tables are not installed.');
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
