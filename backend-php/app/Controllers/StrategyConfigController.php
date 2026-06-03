<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Models\StrategicRolloverModel;
use App\Shared\SessionHelper;

final class StrategyConfigController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function segmentMapping(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $currentMappings = $fy > 0 ? $model->getStrategicSegmentDecisions($fy) : [];
        $oldInput = SessionHelper::pull('strategy.segment_mapping.input', []);
        $inlineErrors = SessionHelper::pull('strategy.segment_mapping.errors', []);

        if (is_array($oldInput)) {
            foreach ($oldInput as $dimensionCode => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $segmentNo = (int) ($row['SegmentNo'] ?? 0);
                $decision = strtoupper(trim((string) ($row['Decision'] ?? '')));
                $currentMappings[(string) $dimensionCode] = [
                    'StrategicDimensionCode' => (string) $dimensionCode,
                    'SegmentNo' => $segmentNo,
                    'Notes' => $row['Notes'] ?? null,
                    'DecisionState' => $decision !== '' ? $decision : ($segmentNo > 0 ? 'MAPPED' : 'UNSET'),
                ];
            }
        }

        $this->render('strategy/SegmentMapping', [
            'title' => 'Strategic Segment Mapping',
            'context' => $ctx,
            'fiscalYearId' => $fy,
            'definitions' => $model->getStrategicSegmentDefinitions(),
            'availableSegments' => $model->listAvailableSegments(),
            'currentMappings' => $currentMappings,
            'inlineErrors' => is_array($inlineErrors) ? $inlineErrors : [],
        ]);
    }

    public function importDashboard(): void
    {
        $model = $this->buildModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        $this->render('strategy/ImportDashboard', [
            'title' => __t('strategy_import_dimensions'),
            'context' => $ctx + $labels,
            'fiscalYearId' => $fy,
            'definitions' => $model->getStrategicSegmentDefinitions(),
            'statuses' => $fy > 0 ? $model->getOverlayImportDashboard($fy, $ver) : [],
            'sectorOptions' => $model->listSectorOptions(),
        ]);
    }

    public function fiscalPeriods(): void
    {
        $model = $this->buildModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        $record = $fy > 0 ? $model->getFiscalPeriodConfig($fy) : $model->buildDefaultFiscalPeriodConfig(0);

        $this->render('strategy/FiscalPeriodConfig', [
            'title' => __t('strategy_fiscal_period_labels'),
            'context' => $ctx + $labels,
            'fiscalYearId' => $fy,
            'record' => $record,
            'monthOptions' => $model->getMonthOptions(),
            'periodConfigAvailable' => $model->supportsFiscalPeriodConfig(),
        ]);
    }

    public function phasingProfiles(): void
    {
        $model = $this->buildModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? ($model->getPhasingProfile($id) ?? $model->buildDefaultPhasingProfile($fy)) : $model->buildDefaultPhasingProfile($fy);

        $this->render('strategy/PhasingProfileConfig', [
            'title' => 'Custom Phasing Profiles',
            'context' => $ctx + $labels,
            'fiscalYearId' => $fy,
            'record' => $record,
            'records' => $model->listPhasingProfiles($fy),
            'periodLabels' => $model->getFiscalPeriodLabels($fy),
            'phasingProfileAvailable' => $model->supportsPhasingProfileConfig(),
        ]);
    }

    public function fiscalAssumptions(): void
    {
        $model = $this->buildModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? ($model->getFiscalAssumption($id) ?? $model->buildDefaultFiscalAssumption($fy, $ver)) : $model->buildDefaultFiscalAssumption($fy, $ver);

        $this->render('strategy/FiscalAssumptionConfig', [
            'title' => 'Fiscal Assumptions',
            'context' => $ctx + $labels,
            'record' => $record,
            'records' => $model->listFiscalAssumptions($fy, $ver),
            'assumptionDefinitions' => $model->getFiscalAssumptionDefinitions(),
            'fiscalAssumptionAvailable' => $model->supportsFiscalAssumptionConfig(),
        ]);
    }

    public function rollover(): void
    {
        $rolloverModel = $this->buildRolloverModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        $fiscalYears = $rolloverModel->listFiscalYears();
        $sourceFiscalYearId = $fy > 0 ? $fy : (int) ($fiscalYears[0]['FiscalYearID'] ?? 0);
        $versionsByFiscalYear = [];
        foreach ($fiscalYears as $row) {
            $rowFiscalYearId = (int) ($row['FiscalYearID'] ?? 0);
            if ($rowFiscalYearId <= 0) {
                continue;
            }

            $versionsByFiscalYear[$rowFiscalYearId] = $rolloverModel->listSubmissionVersions($rowFiscalYearId);
        }

        if (!isset($versionsByFiscalYear[$sourceFiscalYearId])) {
            $sourceFiscalYearId = (int) array_key_first($versionsByFiscalYear);
        }

        $versionDefaults = $rolloverModel->buildVersionRolloverDefaults($sourceFiscalYearId, $ver);
        $fiscalDefaults = $rolloverModel->buildFiscalYearRolloverDefaults($sourceFiscalYearId, $ver);

        $this->render('strategy/RolloverConfig', [
            'title' => 'Fiscal and Version Rollover',
            'context' => $ctx + $labels,
            'rolloverAvailable' => $rolloverModel->supportsRollover() && $rolloverModel->supportsVersionTyping() && $rolloverModel->getSubmissionVersionTypeId() > 0,
            'fiscalYears' => $fiscalYears,
            'versionsByFiscalYear' => $versionsByFiscalYear,
            'versionScopes' => $rolloverModel->getVersionScopeDefinitions(),
            'fiscalScopes' => $rolloverModel->getFiscalScopeDefinitions(),
            'versionDefaults' => $versionDefaults,
            'fiscalDefaults' => $fiscalDefaults,
        ]);
    }

    public function runVersionRollover(): void
    {
        $this->assertPostWithCsrf();

        try {
            $result = $this->buildRolloverModel()->runVersionRollover([
                'SourceFiscalYearID' => (int) ($_POST['SourceFiscalYearID'] ?? 0),
                'SourceVersionID' => (int) ($_POST['SourceVersionID'] ?? 0),
                'TargetVersionLabel' => trim((string) ($_POST['TargetVersionLabel'] ?? '')),
                'IsDefault' => isset($_POST['IsDefault']) ? 1 : 0,
                'Scopes' => is_array($_POST['Scopes'] ?? null) ? $_POST['Scopes'] : [],
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);

            SessionHelper::set('FiscalYearID', (int) ($result['TargetFiscalYearID'] ?? 0));
            SessionHelper::set('VersionID', (int) ($result['TargetVersionID'] ?? 0));

            $this->flashSuccess('Version rollover created :label in FY :fy with :count copied scopes.', [
                'label' => (string) ($result['TargetVersionLabel'] ?? ''),
                'fy' => (string) ((int) ($result['TargetFiscalYearID'] ?? 0)),
                'count' => (string) count((array) ($result['ScopeResults'] ?? [])),
            ]);
        } catch (\Throwable $e) {
            $this->flashError('Rollover failed: :msg', ['msg' => $e->getMessage()]);
        }

        header('Location: index.php?route=strategy-config/rollover');
        exit;
    }

    public function runFiscalYearRollover(): void
    {
        $this->assertPostWithCsrf();

        try {
            $result = $this->buildRolloverModel()->runFiscalYearRollover([
                'SourceFiscalYearID' => (int) ($_POST['SourceFiscalYearID'] ?? 0),
                'SourceVersionID' => (int) ($_POST['SourceVersionID'] ?? 0),
                'TargetFiscalYearID' => (int) ($_POST['TargetFiscalYearID'] ?? 0),
                'TargetYearLabel' => trim((string) ($_POST['TargetYearLabel'] ?? '')),
                'TargetStartDate' => trim((string) ($_POST['TargetStartDate'] ?? '')),
                'TargetEndDate' => trim((string) ($_POST['TargetEndDate'] ?? '')),
                'TargetVersionLabel' => trim((string) ($_POST['TargetVersionLabel'] ?? '')),
                'TargetFiscalYearActive' => isset($_POST['TargetFiscalYearActive']) ? 1 : 0,
                'IsDefault' => isset($_POST['IsDefault']) ? 1 : 0,
                'FiscalScopes' => is_array($_POST['FiscalScopes'] ?? null) ? $_POST['FiscalScopes'] : [],
                'VersionScopes' => is_array($_POST['VersionScopes'] ?? null) ? $_POST['VersionScopes'] : [],
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);

            SessionHelper::set('FiscalYearID', (int) ($result['TargetFiscalYearID'] ?? 0));
            SessionHelper::set('VersionID', (int) ($result['TargetVersionID'] ?? 0));

            $scopeGroupCount = count((array) ($result['FiscalScopeResults'] ?? [])) + count((array) ($result['VersionScopeResults'] ?? []));
            $this->flashSuccess('Fiscal year rollover created FY :fy and version :label with :count copied scope groups.', [
                'fy' => (string) ((int) ($result['TargetFiscalYearID'] ?? 0)),
                'label' => (string) ($result['TargetVersionLabel'] ?? ''),
                'count' => (string) $scopeGroupCount,
            ]);
        } catch (\Throwable $e) {
            $this->flashError('Rollover failed: :msg', ['msg' => $e->getMessage()]);
        }

        header('Location: index.php?route=strategy-config/rollover');
        exit;
    }

    public function configurationReadiness(): void
    {
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $dashboard = $reportingModel->getConfigurationReadinessDashboard($fy);

        $this->render('strategy/ReportReadiness', [
            'title' => __t('strategy_configuration_readiness'),
            'contextLabels' => $reportingModel->getContextLabels($fy, $ver),
            'workflowState' => [],
            'summary' => $dashboard['summary'] ?? [],
            'checks' => $dashboard['checks'] ?? [],
            'readinessType' => 'configuration',
        ]);
    }

    public function resolutionCheck(): void
    {
        $model = $this->buildModel();
        $reportingModel = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $labels = ($fy > 0 && $ver > 0) ? $reportingModel->getContextLabels($fy, $ver) : [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        $filters = [
            'dimension_code' => trim((string) ($_GET['dimension_code'] ?? '')),
            'data_object_code' => trim((string) ($_GET['data_object_code'] ?? '')),
            'segment_code' => trim((string) ($_GET['segment_code'] ?? '')),
        ];

        $result = [];
        if ($fy > 0 && $filters['dimension_code'] !== '' && $filters['data_object_code'] !== '' && $filters['segment_code'] !== '') {
            $result = $model->resolveStrategicDimensionSource(
                $fy,
                $filters['dimension_code'],
                $filters['data_object_code'],
                $filters['segment_code']
            );
        }

        $this->render('strategy/ResolutionCheck', [
            'title' => __t('strategy_source_resolution_check'),
            'context' => $ctx + $labels,
            'fiscalYearId' => $fy,
            'definitions' => $model->getStrategicSegmentDefinitions(),
            'filters' => $filters,
            'result' => $result,
        ]);
    }

    public function customAttributes(): void
    {
        $model = $this->buildModel();
        $dimensionCode = trim((string) ($_GET['dimension_code'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));

        $this->render('strategy/DimensionAttributeList', [
            'title' => 'Strategic Custom Attributes',
            'records' => $model->listDimensionAttributes($dimensionCode !== '' ? $dimensionCode : null, $q),
            'dimensionCode' => $dimensionCode,
            'q' => $q,
            'dimensionOptions' => $model->getStrategicAttributeDimensionDefinitions(),
            'attributesAvailable' => $model->supportsStrategicDimensionAttributes(),
        ]);
    }

    public function savePhasingProfile(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/phasing-profiles');

        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $id = (int) ($_POST['PhasingProfileID'] ?? 0);

        if (!$model->supportsPhasingProfileConfig()) {
            $this->flashError('Run create_tblSbPhasingProfile.sql before maintaining custom phasing profiles.');
            header('Location: index.php?route=strategy-config/phasing-profiles');
            exit;
        }

        $payload = [
            'ProfileCode' => trim((string) ($_POST['ProfileCode'] ?? '')),
            'ProfileName' => trim((string) ($_POST['ProfileName'] ?? '')),
            'ProfileDescription' => $this->nullableTrim($_POST['ProfileDescription'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];
        for ($i = 1; $i <= 12; $i++) {
            $payload['BP' . $i . 'Weight'] = (float) preg_replace('/[^0-9.\-]/', '', (string) ($_POST['BP' . $i . 'Weight'] ?? '0'));
        }

        try {
            $model->savePhasingProfile($fy, $payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Custom phasing profile updated.' : 'Custom phasing profile created.');
        } catch (\Throwable $e) {
            $this->flashError('Custom phasing profile save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-config/phasing-profiles' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-config/phasing-profiles');
        exit;
    }

    public function deletePhasingProfile(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/phasing-profiles');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivatePhasingProfile($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Custom phasing profile archived.');
        }

        header('Location: index.php?route=strategy-config/phasing-profiles');
        exit;
    }

    public function saveFiscalAssumption(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/fiscal-assumptions');

        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['FiscalAssumptionID'] ?? 0);

        if (!$model->supportsFiscalAssumptionConfig()) {
            $this->flashError('Run create_tblSbFiscalAssumption.sql before maintaining fiscal assumptions.');
            header('Location: index.php?route=strategy-config/fiscal-assumptions');
            exit;
        }

        $payload = [
            'AssumptionCode' => trim((string) ($_POST['AssumptionCode'] ?? '')),
            'AssumptionName' => trim((string) ($_POST['AssumptionName'] ?? '')),
            'AssumptionValue' => (float) preg_replace('/[^0-9.\-]/', '', (string) ($_POST['AssumptionValue'] ?? '0')),
            'AssumptionNotes' => $this->nullableTrim($_POST['AssumptionNotes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        try {
            $model->saveFiscalAssumption($fy, $ver, $payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Fiscal assumption updated.' : 'Fiscal assumption created.');
        } catch (\Throwable $e) {
            $this->flashError('Fiscal assumption save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-config/fiscal-assumptions' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-config/fiscal-assumptions');
        exit;
    }

    public function deleteFiscalAssumption(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/fiscal-assumptions');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateFiscalAssumption($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Fiscal assumption archived.');
        }

        header('Location: index.php?route=strategy-config/fiscal-assumptions');
        exit;
    }

    public function customAttributeForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $dimensionCode = trim((string) ($_GET['dimension_code'] ?? ''));

        $this->render('strategy/DimensionAttributeForm', [
            'title' => $id > 0 ? 'Edit Custom Attribute' : 'Create Custom Attribute',
            'record' => $id > 0 ? $model->getDimensionAttribute($id) : null,
            'dimensionCode' => $dimensionCode,
            'dimensionOptions' => $model->getStrategicAttributeDimensionDefinitions(),
            'attributesAvailable' => $model->supportsStrategicDimensionAttributes(),
        ]);
    }

    public function saveCustomAttribute(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/custom-attributes');

        $model = $this->buildModel();
        $id = (int) ($_POST['AttributeID'] ?? 0);
        $payload = [
            'StrategicDimensionCode' => strtoupper(trim((string) ($_POST['StrategicDimensionCode'] ?? ''))),
            'AttributeCode' => strtoupper(trim((string) ($_POST['AttributeCode'] ?? ''))),
            'AttributeName' => trim((string) ($_POST['AttributeName'] ?? '')),
            'DataTypeCode' => strtoupper(trim((string) ($_POST['DataTypeCode'] ?? 'TEXT'))),
            'HelpText' => $this->nullableTrim($_POST['HelpText'] ?? null),
            'IsRequired' => isset($_POST['IsRequired']) ? 1 : 0,
            'DisplayOrder' => (int) ($_POST['DisplayOrder'] ?? 0),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['StrategicDimensionCode'] === '' || $payload['AttributeCode'] === '' || $payload['AttributeName'] === '') {
            $this->flashError('Dimension, attribute code, and attribute name are required.');
            header('Location: index.php?route=strategy-config/custom-attribute-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $model->saveDimensionAttribute($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Custom attribute updated.' : 'Custom attribute created.');
        } catch (\Throwable $e) {
            $this->flashError('Custom attribute save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-config/custom-attribute-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-config/custom-attributes&dimension_code=' . urlencode($payload['StrategicDimensionCode']));
        exit;
    }

    public function deleteCustomAttribute(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/custom-attributes');

        $id = (int) ($_POST['id'] ?? 0);
        $dimensionCode = trim((string) ($_POST['dimension_code'] ?? ''));
        if ($id > 0) {
            $this->buildModel()->deactivateDimensionAttribute($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Custom attribute archived.');
        }

        $redirect = 'index.php?route=strategy-config/custom-attributes';
        if ($dimensionCode !== '') {
            $redirect .= '&dimension_code=' . urlencode($dimensionCode);
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function customAttributeOptions(): void
    {
        $model = $this->buildModel();
        $attributeId = (int) ($_GET['attribute_id'] ?? 0);
        $attribute = $attributeId > 0 ? $model->getDimensionAttribute($attributeId) : null;

        $this->render('strategy/DimensionAttributeOptionList', [
            'title' => 'Custom Attribute Options',
            'attribute' => $attribute,
            'records' => $attributeId > 0 ? $model->listDimensionAttributeOptions($attributeId) : [],
            'attributesAvailable' => $model->supportsStrategicDimensionAttributes(),
        ]);
    }

    public function customAttributeOptionForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $attributeId = (int) ($_GET['attribute_id'] ?? 0);
        $record = $id > 0 ? $model->getDimensionAttributeOption($id) : null;
        if ($record !== null) {
            $attributeId = (int) ($record['AttributeID'] ?? $attributeId);
        }

        $this->render('strategy/DimensionAttributeOptionForm', [
            'title' => $id > 0 ? 'Edit Custom Attribute Option' : 'Create Custom Attribute Option',
            'record' => $record,
            'attribute' => $attributeId > 0 ? $model->getDimensionAttribute($attributeId) : null,
            'attributesAvailable' => $model->supportsStrategicDimensionAttributes(),
        ]);
    }

    public function saveCustomAttributeOption(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/custom-attributes');

        $model = $this->buildModel();
        $id = (int) ($_POST['AttributeOptionID'] ?? 0);
        $attributeId = (int) ($_POST['AttributeID'] ?? 0);
        $payload = [
            'AttributeID' => $attributeId,
            'OptionCode' => strtoupper(trim((string) ($_POST['OptionCode'] ?? ''))),
            'OptionLabel' => trim((string) ($_POST['OptionLabel'] ?? '')),
            'DisplayOrder' => (int) ($_POST['DisplayOrder'] ?? 0),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['AttributeID'] <= 0 || $payload['OptionCode'] === '' || $payload['OptionLabel'] === '') {
            $this->flashError('Attribute, option code, and option label are required.');
            header('Location: index.php?route=strategy-config/custom-attribute-option-form' . ($id > 0 ? '&id=' . $id : '&attribute_id=' . $attributeId));
            exit;
        }

        try {
            $model->saveDimensionAttributeOption($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Custom attribute option updated.' : 'Custom attribute option created.');
        } catch (\Throwable $e) {
            $this->flashError('Custom attribute option save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-config/custom-attribute-option-form' . ($id > 0 ? '&id=' . $id : '&attribute_id=' . $attributeId));
            exit;
        }

        header('Location: index.php?route=strategy-config/custom-attribute-options&attribute_id=' . $attributeId);
        exit;
    }

    public function deleteCustomAttributeOption(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/custom-attributes');

        $id = (int) ($_POST['id'] ?? 0);
        $attributeId = (int) ($_POST['attribute_id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateDimensionAttributeOption($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Custom attribute option archived.');
        }

        header('Location: index.php?route=strategy-config/custom-attribute-options&attribute_id=' . $attributeId);
        exit;
    }

    public function saveSegmentMapping(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/segment-mapping');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before configuring strategic segment mappings.');
            header('Location: index.php?route=strategy-config/segment-mapping');
            exit;
        }

        $posted = $_POST['mapping'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        $payload = [];
        foreach ($posted as $dimensionCode => $row) {
            if (!is_array($row)) {
                continue;
            }
            $segmentNo = (int) ($row['SegmentNo'] ?? 0);
            $payload[(string) $dimensionCode] = [
                'Decision' => strtoupper(trim((string) ($row['Decision'] ?? ''))),
                'SegmentNo' => $segmentNo,
                'Notes' => $this->nullableTrim($row['Notes'] ?? null),
            ];
        }

        $validationErrors = [];
        foreach ($payload as $dimensionCode => $row) {
            $decision = (string) ($row['Decision'] ?? '');
            $segmentNo = (int) ($row['SegmentNo'] ?? 0);
            if ($decision === 'MAPPED' && $segmentNo <= 0) {
                $validationErrors[(string) $dimensionCode]['SegmentNo'] = 'Select a segment when the decision is Mapped.';
            }
        }

        if ($validationErrors !== []) {
            SessionHelper::set('strategy.segment_mapping.input', $payload);
            SessionHelper::set('strategy.segment_mapping.errors', $validationErrors);
            header('Location: index.php?route=strategy-config/segment-mapping');
            exit;
        }

        try {
            $this->buildModel()->saveStrategicSegmentMappings(
                $fy,
                $payload,
                (int) SessionHelper::get('auth.user_id', 1)
            );
            $this->flashSuccess('Strategic segment mappings updated.');
        } catch (\Throwable $e) {
            $message = 'Strategic segment mapping save failed: ' . $e->getMessage();
            if (str_contains($e->getMessage(), 'CK_tblSbSegmentConfig_StrategicDimensionCode')) {
                $message = 'Strategic segment mapping save failed because the database constraint is still using the older dimension list. Run alter_tblSbSegmentConfig_add_objective_target_activity.sql and try again.';
            }
            $this->flashError($message);
        }

        header('Location: index.php?route=strategy-config/segment-mapping');
        exit;
    }

    public function saveFiscalPeriods(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-config/fiscal-periods');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before configuring fiscal period labels.');
            header('Location: index.php?route=strategy-config/fiscal-periods');
            exit;
        }

        $model = $this->buildModel();
        if (!$model->supportsFiscalPeriodConfig()) {
            $this->flashError('Run create_tblSbFiscalPeriodConfig.sql before maintaining fiscal period labels.');
            header('Location: index.php?route=strategy-config/fiscal-periods');
            exit;
        }

        $startMonthNo = (int) ($_POST['StartMonthNo'] ?? 0);
        if ($startMonthNo < 1 || $startMonthNo > 12) {
            $this->flashError('Select a valid fiscal start month.');
            header('Location: index.php?route=strategy-config/fiscal-periods');
            exit;
        }

        $payload = [
            'StartMonthNo' => $startMonthNo,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        for ($i = 1; $i <= 12; $i++) {
            $payload['BP' . $i . 'Label'] = $this->nullableTrim($_POST['BP' . $i . 'Label'] ?? null);
        }
        for ($i = 1; $i <= 5; $i++) {
            $payload['OuterYear' . $i . 'Label'] = $this->nullableTrim($_POST['OuterYear' . $i . 'Label'] ?? null);
        }

        try {
            $model->saveFiscalPeriodConfig($fy, $payload);
            $this->flashSuccess('Fiscal period labels updated.');
        } catch (\Throwable $e) {
            $this->flashError('Fiscal period label save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-config/fiscal-periods');
        exit;
    }

    private function buildModel(): StrategicBudgetingAdminModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingAdminModel($this->db);
    }

    private function buildReportingModel(): StrategicBudgetingModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingModel($this->db);
    }

    private function buildRolloverModel(): StrategicRolloverModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicRolloverModel($this->db);
    }

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-config/configuration-readiness'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
