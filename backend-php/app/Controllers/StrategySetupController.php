<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Shared\SessionHelper;

final class StrategySetupController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_SETUP_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function sectors(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));

        $this->render('strategy/SectorList', [
            'title' => 'Strategic Sectors',
            'records' => $model->listSectors($q),
            'q' => $q,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'SECTOR'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'SECTOR'),
        ]);
    }

    public function sectorForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $model->getSector($id) : null;

        $this->render('strategy/SectorForm', [
            'title' => $id > 0 ? 'Edit Sector' : 'Create Sector',
            'record' => $record,
        ]);
    }

    public function saveSector(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sectors');

        $model = $this->buildModel();
        $id = (int) ($_POST['SectorID'] ?? 0);
        $payload = [
            'SectorName' => trim((string) ($_POST['SectorName'] ?? '')),
            'SectorDescription' => $this->nullableTrim($_POST['SectorDescription'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['SectorName'] === '') {
            $this->flashError('Sector name is required.');
            header('Location: index.php?route=strategy-setup/sector-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveSector($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Sector updated.' : 'Sector created.');
        header('Location: index.php?route=strategy-setup/sectors');
        exit;
    }

    public function deleteSector(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sectors');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateSector($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Sector archived.');
        }

        header('Location: index.php?route=strategy-setup/sectors');
        exit;
    }

    public function importSectorOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sectors');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing sector records.');
            header('Location: index.php?route=strategy-setup/sectors');
            exit;
        }

        try {
            $summary = $this->buildModel()->importSectorOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('SECTOR', $fy, $summary, $resetMode);
            $this->flashSuccess(
                'Sector records imported. Created: '
                . (int) ($summary['created'] ?? 0)
                . ', linked: ' . (int) ($summary['linked'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0)
                . '.'
                . $this->formatImportResetMessage($summary)
            );
        } catch (\Throwable $e) {
            $this->flashError('Sector record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/sectors');
        exit;
    }

    public function fundingTypes(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        $this->render('strategy/FundingTypeList', [
            'title' => 'Strategic Funding Types',
            'records' => $model->listSegmentBackedFundingTypes($fy, $q),
            'orphanRecords' => $model->listFundingTypeOrphanOverlays($fy),
            'q' => $q,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'FUNDING_TYPE'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'FUNDING_TYPE'),
        ]);
    }

    public function fundingTypeForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceSegmentCode = trim((string) ($_GET['source_segment_code'] ?? ''));
        $record = $id > 0 ? $model->getFundingType($id) : null;
        $source = null;

        if ($record === null && $sourceSegmentCode !== '') {
            $source = $model->getSegmentBackedFundingType($fy, $sourceSegmentCode);
            if ($source !== null) {
                $record = array_merge($source, $model->getFundingTypeBySourceCode($fy, $sourceSegmentCode) ?? []);
            }
        }

        $this->render('strategy/FundingTypeForm', [
            'title' => $id > 0 ? 'Edit Funding Type Overlay' : 'Configure Funding Type Overlay',
            'record' => $record,
            'sourceFundingType' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'FUNDING_TYPE'),
            'phasingProfiles' => $model->listActivePhasingProfiles($fy),
            'supportsFundingTypeDefaultPhasing' => $model->supportsFundingTypeDefaultPhasing() && $model->supportsPhasingProfileConfig(),
        ]);
    }

    public function saveFundingType(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-types');

        $model = $this->buildModel();
        $id = (int) ($_POST['FundingTypeID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceSegmentCode = trim((string) ($_POST['SourceSegmentCode'] ?? ''));
        $fundingTypeCode = strtoupper(trim((string) ($_POST['FundingTypeCode'] ?? '')));
        $fundingTypeName = trim((string) ($_POST['FundingTypeName'] ?? ''));

        if ($id <= 0 && $sourceSegmentCode !== '') {
            $source = $model->getSegmentBackedFundingType($fy, $sourceSegmentCode);
            if ($source === null) {
                $this->flashError('Selected funding type source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/funding-types');
                exit;
            }
            $existing = $model->getFundingTypeBySourceCode($fy, $sourceSegmentCode);
            if ($existing !== null) {
                $id = (int) ($existing['FundingTypeID'] ?? 0);
            }
            if ($fundingTypeCode === '') {
                $fundingTypeCode = trim((string) ($source['SourceSegmentCode'] ?? ''));
            }
            if ($fundingTypeName === '') {
                $fundingTypeName = trim((string) ($source['FundingTypeName'] ?? ''));
            }
        }

        $mapping = $model->getStrategicSegmentMapping($fy, 'FUNDING_TYPE');

        $payload = [
            'FundingTypeCode' => $fundingTypeCode,
            'FundingTypeName' => $fundingTypeName,
            'FundingTypeDescription' => $this->nullableTrim($_POST['FundingTypeDescription'] ?? null),
            'DefaultPhasingProfileID' => (($defaultPhasingProfileId = (int) ($_POST['DefaultPhasingProfileID'] ?? 0)) > 0 ? $defaultPhasingProfileId : null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            'SourceFiscalYearID' => $sourceSegmentCode !== '' && $fy > 0 ? $fy : null,
            'SourceDataObjectCode' => null,
            'SourceSegmentNo' => (int) ($mapping['SegmentNo'] ?? 0),
            'SourceSegmentCode' => $sourceSegmentCode !== '' ? $sourceSegmentCode : null,
        ];

        if ($payload['FundingTypeCode'] === '' || $payload['FundingTypeName'] === '') {
            $this->flashError('Funding type code and name are required.');
            header('Location: index.php?route=strategy-setup/funding-type-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $model->saveFundingType($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Funding type record updated.' : 'Funding type record created.');
        } catch (\Throwable $e) {
            $this->flashError('Funding type save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-setup/funding-type-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-setup/funding-types');
        exit;
    }

    public function deleteFundingType(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-types');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateFundingType($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Funding type archived.');
        }

        header('Location: index.php?route=strategy-setup/funding-types');
        exit;
    }

    public function importFundingTypeOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-types');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing funding type records.');
            header('Location: index.php?route=strategy-setup/funding-types');
            exit;
        }

        try {
            $summary = $this->buildModel()->importFundingTypeOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('FUNDING_TYPE', $fy, $summary, $resetMode);
            $this->flashSuccess('Funding type records imported. Created: ' . (int) ($summary['created'] ?? 0) . ', skipped: ' . (int) ($summary['skipped'] ?? 0) . '.' . $this->formatImportResetMessage($summary));
        } catch (\Throwable $e) {
            $this->flashError('Funding type record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/funding-types');
        exit;
    }

    public function programs(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $sectorId = (int) ($_GET['sector_id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        $this->render('strategy/ProgramList', [
            'title' => 'Strategic Programs',
            'records' => $model->listSegmentBackedPrograms($fy, $q, $sectorId > 0 ? $sectorId : null),
            'q' => $q,
            'sectorId' => $sectorId,
            'sectorOptions' => $model->listSectorOptions(),
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'PROGRAM'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'PROGRAM'),
        ]);
    }

    public function programForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceDataObjectCode = trim((string) ($_GET['data_object_code'] ?? ''));
        $sourceProgramCode = trim((string) ($_GET['program_code'] ?? ''));
        $record = $id > 0 ? $model->getProgram($id) : null;
        $source = null;

        if ($record === null && $sourceDataObjectCode !== '' && $sourceProgramCode !== '') {
            $source = $model->getSegmentBackedProgramSource($fy, $sourceDataObjectCode, $sourceProgramCode);
            if ($source !== null) {
                $resolvedSourceCode = (string) ($source['SourceDataObjectCode'] ?? $sourceDataObjectCode);
                $record = array_merge($source, $model->findProgramOverlayBySource($fy, $resolvedSourceCode, $sourceProgramCode) ?? []);
            }
        }

        $linkedOrgUnits = ($record !== null && (int) ($record['ProgramID'] ?? 0) > 0)
            ? $model->listProgramLinkedOrgUnits((int) $record['ProgramID'])
            : [];
        $selectedLinkedDataObjectCodes = array_values(array_filter(array_map(
            static fn(array $row): string => (string) ($row['DataObjectCode'] ?? ''),
            $linkedOrgUnits
        )));

        $this->render('strategy/ProgramForm', [
            'title' => $id > 0 ? 'Edit Program Overlay' : 'Configure Program Overlay',
            'record' => $record,
            'sectorOptions' => $model->listSectorOptions(),
            'orgUnitOptions' => $model->listDataScopeOrgUnitOptions($fy),
            'sourceProgram' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'PROGRAM'),
            'linkedOrgUnits' => $linkedOrgUnits,
            'selectedLinkedDataObjectCodes' => $selectedLinkedDataObjectCodes,
            'supportsProgramOrgLinks' => $model->supportsProgramOrgLinks(),
            'customAttributeFields' => $model->getDimensionAttributeFields('PROGRAM', (int) ($record['ProgramID'] ?? 0) ?: null),
        ]);
    }

    public function saveProgram(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/programs');

        $model = $this->buildModel();
        $id = (int) ($_POST['ProgramID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $orgUnitCode = trim((string) ($_POST['OrgUnitDataObjectCode'] ?? ''));
        $sourceDataObjectCode = trim((string) ($_POST['SourceDataObjectCode'] ?? $orgUnitCode));
        $ownerDataObjectCode = trim((string) ($_POST['OwnerDataObjectCode'] ?? $orgUnitCode));
        $programCode = trim((string) ($_POST['ProgramCode'] ?? ''));
        $programName = trim((string) ($_POST['ProgramName'] ?? ''));
        $userId = (int) SessionHelper::get('auth.user_id', 1);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before selecting a DataScope org unit.');
            header('Location: index.php?route=strategy-setup/program-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        if ($id <= 0) {
            $source = $model->getSegmentBackedProgramSource($fy, $orgUnitCode, $programCode);
            if ($source === null) {
                $this->flashError('Selected program source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/programs');
                exit;
            }
            $sourceDataObjectCode = trim((string) ($source['SourceDataObjectCode'] ?? $orgUnitCode));
            $existing = $model->findProgramOverlayBySource($fy, $sourceDataObjectCode, $programCode);
            if ($existing !== null) {
                $id = (int) ($existing['ProgramID'] ?? 0);
            }
            $programName = (string) ($source['ProgramName'] ?? $programName);
        }

        if ($sourceDataObjectCode === '0' && $ownerDataObjectCode === '') {
            $this->flashError('Select a primary owner DataScope org unit for a globally scoped program source.');
            header('Location: index.php?route=strategy-setup/program-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $this->db->beginTransaction();

            $payload = [
                'OrgUnitID' => ($ownerDataObjectCode !== '' ? $model->ensureOrgUnitFromDataObject($fy, $ownerDataObjectCode, $userId) : 0),
                'SectorID' => (int) ($_POST['SectorID'] ?? 0),
                'ProgramCode' => $this->nullableTrim($programCode),
                'ProgramName' => $programName,
                'ProgramDescription' => $this->nullableTrim($_POST['ProgramDescription'] ?? null),
                'ProgramManagerName' => $this->nullableTrim($_POST['ProgramManagerName'] ?? null),
                'SourceFiscalYearID' => $fy > 0 ? $fy : null,
                'SourceDataObjectCode' => $this->nullableTrim($sourceDataObjectCode),
                'SourceSegmentNo' => (int) ($_POST['SourceSegmentNo'] ?? 0) ?: null,
                'SourceSegmentCode' => $this->nullableTrim($programCode),
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => $userId,
            ];

            if ($payload['OrgUnitID'] <= 0 || $payload['SectorID'] <= 0 || $payload['ProgramName'] === '') {
                throw new \RuntimeException('Program name, DataScope org unit, and sector are required.');
            }

            $programId = $model->saveProgram($payload, $id > 0 ? $id : null);
            $model->syncDimensionAttributeValues(
                'PROGRAM',
                $programId,
                is_array($_POST['custom_attributes'] ?? null) ? $_POST['custom_attributes'] : [],
                $userId
            );
            if ($model->supportsProgramOrgLinks()) {
                $linkedCodes = $_POST['LinkedDataObjectCodes'] ?? [];
                if (!is_array($linkedCodes)) {
                    $linkedCodes = [];
                }
                $model->syncProgramLinkedOrgUnitsByDataObjectCodes(
                    $programId,
                    $fy,
                    $linkedCodes,
                    (int) $payload['OrgUnitID'],
                    $userId
                );
            }

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            $this->flashSuccess($id > 0 ? 'Program record updated.' : 'Program record created.');
            header('Location: index.php?route=strategy-setup/programs');
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('Program save failed', $e, [
                'programId' => $id,
                'fiscalYearId' => $fy,
                'programCode' => $programCode,
                'sourceDataObjectCode' => $sourceDataObjectCode,
            ]);
            $this->flashError('Program save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-setup/program-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function deleteProgram(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/programs');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateProgram($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Program archived.');
        }

        header('Location: index.php?route=strategy-setup/programs');
        exit;
    }

    public function importProgramOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/programs');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sectorId = (int) ($_POST['sector_id'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');
        $model = $this->buildModel();

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing program records.');
            header('Location: index.php?route=strategy-setup/programs');
            exit;
        }

        try {
            $summary = $model->importProgramOverlaysFromSegments($fy, $sectorId, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('PROGRAM', $fy, $summary, $resetMode);
            $message = 'Program records imported. Created: ' . (int) ($summary['created'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0) . '.';

            if (isset($summary['auto_assigned'])) {
                $message .= ' Auto-assigned sectors: ' . (int) ($summary['auto_assigned'] ?? 0)
                    . ', manual sector fallback: ' . (int) ($summary['manual_assigned'] ?? 0) . '.';
            }
            if ((int) ($summary['missing_sector_link'] ?? 0) > 0 || (int) ($summary['missing_sector_overlay'] ?? 0) > 0) {
                $message .= ' Missing sector link: ' . (int) ($summary['missing_sector_link'] ?? 0)
                    . ', missing sector record: ' . (int) ($summary['missing_sector_overlay'] ?? 0) . '.';
            }

            $this->flashSuccess($message . $this->formatImportResetMessage($summary));
        } catch (\Throwable $e) {
            $this->flashError('Program record import failed: ' . $e->getMessage());
        }

        $redirect = 'index.php?route=strategy-setup/programs';
        if ($sectorId > 0) {
            $redirect .= '&sector_id=' . $sectorId;
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function subPrograms(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $programId = (int) ($_GET['program_id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        $this->render('strategy/SubProgramList', [
            'title' => 'Strategic SubPrograms',
            'records' => $model->listSegmentBackedSubPrograms($fy, $q, $programId > 0 ? $programId : null),
            'q' => $q,
            'programId' => $programId,
            'programOptions' => $model->listProgramOptions(),
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'SUBPROGRAM'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'SUBPROGRAM'),
        ]);
    }

    public function projects(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'current_imported')));
        if (!in_array($scope, ['current_imported', 'all'], true)) {
            $scope = 'current_imported';
        }
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/ProjectList', [
            'title' => 'Project Register',
            'contextLabels' => $this->getStrategyContextLabels($fy, $ver),
            'records' => $model->listSegmentBackedProjects($fy, $q, $scope),
            'q' => $q,
            'scope' => $scope,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'PROJECT'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'PROJECT'),
            'supportsProjectSourceMaps' => $model->supportsProjectSourceMaps(),
            'supportsProjectProgramLinks' => $model->supportsProjectProgramLinks(),
            'supportsProjectObjectiveLinks' => $model->supportsProjectObjectiveLinks(),
        ]);
    }

    public function projectForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string) ($_GET['data_object_code'] ?? ''));
        $projectCode = trim((string) ($_GET['project_code'] ?? ''));
        $record = $id > 0 ? $model->getProject($id) : null;
        $source = null;

        if ($record === null && $dataObjectCode !== '' && $projectCode !== '') {
            $source = $model->getSegmentBackedProjectSource($fy, $dataObjectCode, $projectCode);
            if ($source !== null) {
                $record = array_merge($source, $model->findProjectOverlayBySource($fy, $dataObjectCode, $projectCode) ?? []);
            }
        }

        $linkedPrograms = ($record !== null && (int) ($record['ProjectID'] ?? 0) > 0)
            ? $model->listProjectLinkedPrograms((int) $record['ProjectID'])
            : [];
        $selectedProgramIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['ProgramID'] ?? 0),
            $linkedPrograms
        )));

        $linkedObjectives = ($record !== null && (int) ($record['ProjectID'] ?? 0) > 0)
            ? $model->listProjectLinkedObjectives((int) $record['ProjectID'])
            : [];
        $selectedObjectiveIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['ObjectiveID'] ?? 0),
            $linkedObjectives
        )));

        $linkedOrgUnits = ($record !== null && (int) ($record['ProjectID'] ?? 0) > 0)
            ? $model->listProjectLinkedOrgUnits((int) $record['ProjectID'])
            : [];
        $selectedLinkedDataObjectCodes = array_values(array_filter(array_map(
            static fn(array $row): string => (string) ($row['DataObjectCode'] ?? ''),
            $linkedOrgUnits
        )));

        $this->render('strategy/ProjectForm', [
            'title' => $id > 0 ? 'Edit Project' : 'Create Project',
            'record' => $record,
            'sourceProject' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'PROJECT'),
            'orgUnitOptions' => $model->listDataScopeOrgUnitOptions($fy),
            'programOptions' => $model->listProgramOptions(),
            'objectiveOptions' => $model->listObjectiveOptions(),
            'linkedPrograms' => $linkedPrograms,
            'linkedObjectives' => $linkedObjectives,
            'linkedOrgUnits' => $linkedOrgUnits,
            'selectedProgramIds' => $selectedProgramIds,
            'selectedObjectiveIds' => $selectedObjectiveIds,
            'selectedLinkedDataObjectCodes' => $selectedLinkedDataObjectCodes,
            'sourceMappings' => $record !== null ? $model->listProjectSourceMappings((int) ($record['ProjectID'] ?? 0)) : [],
            'usageSummary' => $record !== null ? $model->getProjectUsageSummary((int) ($record['ProjectID'] ?? 0)) : [],
            'projectFundingSummary' => $record !== null ? $model->getProjectFundingEnvelopeSummary((int) ($record['ProjectID'] ?? 0)) : [],
            'projectFundingBreakdown' => $record !== null ? $model->listProjectFundingSourceBreakdown((int) ($record['ProjectID'] ?? 0)) : [],
            'projectFundingLines' => $record !== null ? $model->listProjectResourceEnvelopeUsage((int) ($record['ProjectID'] ?? 0)) : [],
            'supportsProjectSourceMaps' => $model->supportsProjectSourceMaps(),
            'supportsProjectProgramLinks' => $model->supportsProjectProgramLinks(),
            'supportsProjectObjectiveLinks' => $model->supportsProjectObjectiveLinks(),
            'supportsProjectOrgUnitLinks' => $model->supportsProjectOrgUnitLinks(),
            'customAttributeFields' => $model->getDimensionAttributeFields('PROJECT', (int) ($record['ProjectID'] ?? 0) ?: null),
        ]);
    }

    public function projectUsage(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $model->getProject($id) : null;

        if ($record === null) {
            $this->flashError('Project not found.');
            header('Location: index.php?route=strategy-setup/projects');
            exit;
        }

        $this->render('strategy/ProjectUsage', [
            'title' => 'Project Usage',
            'contextLabels' => $this->getStrategyContextLabels($fy, $ver),
            'record' => $record,
            'usageSummary' => $model->getProjectUsageSummary($id),
            'sourceMappings' => $model->listProjectSourceMappings($id),
            'linkedPrograms' => $model->listProjectLinkedPrograms($id),
            'linkedObjectives' => $model->listProjectLinkedObjectives($id),
            'linkedOrgUnits' => $model->listProjectLinkedOrgUnits($id),
            'fundingSubmissionUsage' => $model->listProjectFundingSubmissionUsage($id),
            'activityUsage' => $model->listProjectActivityUsage($id),
            'resourceEnvelopeUsage' => $model->listProjectResourceEnvelopeUsage($id),
            'projectFundingSummary' => $model->getProjectFundingEnvelopeSummary($id),
            'projectFundingBreakdown' => $model->listProjectFundingSourceBreakdown($id),
            'narrativeUsage' => $model->listProjectNarrativeUsage($id),
            'fiscalRiskUsage' => $model->listProjectFiscalRiskUsage($id),
        ]);
    }

    public function saveProject(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/projects');

        $model = $this->buildModel();
        $id = (int) ($_POST['ProjectID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string) ($_POST['DataObjectCode'] ?? ''));
        $sourceDataObjectCode = trim((string) ($_POST['SourceDataObjectCode'] ?? $dataObjectCode));
        $projectCode = trim((string) ($_POST['ProjectCode'] ?? ''));
        $projectName = trim((string) ($_POST['ProjectName'] ?? ''));
        $leadDataObjectCode = trim((string) ($_POST['LeadDataObjectCode'] ?? ''));
        $sponsorDataObjectCode = trim((string) ($_POST['SponsorDataObjectCode'] ?? ''));
        $userId = (int) SessionHelper::get('auth.user_id', 1);

        if ($id <= 0 && $dataObjectCode !== '' && $projectCode !== '') {
            $source = $model->getSegmentBackedProjectSource($fy, $dataObjectCode, $projectCode);
            if ($source === null) {
                $this->flashError('Selected project source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/projects');
                exit;
            }
            $sourceDataObjectCode = trim((string) ($source['DataObjectCode'] ?? $dataObjectCode));
            $payload['SourceDataObjectCode'] = $this->nullableTrim($sourceDataObjectCode);
            $existing = $model->findProjectOverlayBySource($fy, $sourceDataObjectCode, $projectCode);
            if ($existing !== null) {
                $id = (int) ($existing['ProjectID'] ?? 0);
            }
            $projectName = (string) ($source['ProjectName'] ?? $projectName);
        }

        try {
            $this->db->beginTransaction();

            $payload = [
                'ProjectCode' => $this->nullableTrim($projectCode),
                'ProjectName' => $projectName,
                'ProjectDescription' => $this->nullableTrim($_POST['ProjectDescription'] ?? null),
                'ExternalReference' => $this->nullableTrim($_POST['ExternalReference'] ?? null),
                'ProjectTypeCode' => $this->nullableTrim($_POST['ProjectTypeCode'] ?? 'OTHER'),
                'ProjectCategoryCode' => $this->nullableTrim($_POST['ProjectCategoryCode'] ?? null),
                'LifecycleStatusCode' => $this->nullableTrim($_POST['LifecycleStatusCode'] ?? 'PIPELINE'),
                'PriorityCode' => $this->nullableTrim($_POST['PriorityCode'] ?? 'MEDIUM'),
                'LeadOrgUnitID' => ($leadDataObjectCode !== '' && $fy > 0 ? $model->ensureOrgUnitFromDataObject($fy, $leadDataObjectCode, $userId) : null),
                'SponsorOrgUnitID' => ($sponsorDataObjectCode !== '' && $fy > 0 ? $model->ensureOrgUnitFromDataObject($fy, $sponsorDataObjectCode, $userId) : null),
                'ProjectManagerName' => $this->nullableTrim($_POST['ProjectManagerName'] ?? null),
                'CapitalFlag' => isset($_POST['CapitalFlag']) ? 1 : 0,
                'ProcurementRequiredFlag' => isset($_POST['ProcurementRequiredFlag']) ? 1 : 0,
                'StartDate' => $this->nullableTrim($_POST['StartDate'] ?? null),
                'EndDate' => $this->nullableTrim($_POST['EndDate'] ?? null),
                'EstimatedTotalCost' => ($_POST['EstimatedTotalCost'] ?? '') !== '' ? (float) $_POST['EstimatedTotalCost'] : null,
                'ApprovedTotalCost' => ($_POST['ApprovedTotalCost'] ?? '') !== '' ? (float) $_POST['ApprovedTotalCost'] : null,
                'FundingGapAmount' => ($_POST['FundingGapAmount'] ?? '') !== '' ? (float) $_POST['FundingGapAmount'] : null,
                'CurrencyCode' => $this->nullableTrim($_POST['CurrencyCode'] ?? null),
                'FundingStatusCode' => $this->nullableTrim($_POST['FundingStatusCode'] ?? null),
                'RiskRatingCode' => $this->nullableTrim($_POST['RiskRatingCode'] ?? null),
                'LocationCode' => $this->nullableTrim($_POST['LocationCode'] ?? null),
                'LocationDescription' => $this->nullableTrim($_POST['LocationDescription'] ?? null),
                'SourceFiscalYearID' => $fy > 0 ? $fy : null,
                'SourceDataObjectCode' => $this->nullableTrim($sourceDataObjectCode),
                'SourceSegmentNo' => (int) ($_POST['SourceSegmentNo'] ?? 0) ?: null,
                'SourceSegmentCode' => $this->nullableTrim($projectCode),
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => $userId,
            ];

            if ($payload['ProjectCode'] === null || $payload['ProjectCode'] === '' || $payload['ProjectName'] === '') {
                throw new \RuntimeException('Project code and project name are required.');
            }

            $projectId = $model->saveProject($payload, $id > 0 ? $id : null);
            if ($model->supportsProjectSourceMaps() && $payload['SourceFiscalYearID'] !== null && $payload['SourceSegmentNo'] !== null && $payload['SourceSegmentCode'] !== null) {
                $model->upsertProjectSourceMapping([
                    'ProjectID' => $projectId,
                    'FiscalYearID' => (int) $payload['SourceFiscalYearID'],
                    'DataObjectCode' => $payload['SourceDataObjectCode'],
                    'SourceSegmentNo' => (int) $payload['SourceSegmentNo'],
                    'SourceSegmentCode' => (string) $payload['SourceSegmentCode'],
                    'SourceSegmentName' => $payload['ProjectName'],
                    'SourceSystemCode' => 'SEGMENT',
                    'IsPrimaryFlag' => 1,
                    'ActiveFlag' => $payload['ActiveFlag'],
                    'UserID' => $userId,
                ]);
            }
            $model->syncDimensionAttributeValues(
                'PROJECT',
                $projectId,
                is_array($_POST['custom_attributes'] ?? null) ? $_POST['custom_attributes'] : [],
                $userId
            );
            $model->syncProjectProgramLinks(
                $projectId,
                is_array($_POST['ProgramIDs'] ?? null) ? $_POST['ProgramIDs'] : [],
                $userId
            );
            $model->syncProjectObjectiveLinks(
                $projectId,
                is_array($_POST['ObjectiveIDs'] ?? null) ? $_POST['ObjectiveIDs'] : [],
                $userId
            );
            $excludedOrgUnitIds = array_values(array_filter([
                (int) ($payload['LeadOrgUnitID'] ?? 0),
                (int) ($payload['SponsorOrgUnitID'] ?? 0),
            ]));
            $model->syncProjectOrgUnitsByDataObjectCodes(
                $projectId,
                $fy,
                is_array($_POST['LinkedDataObjectCodes'] ?? null) ? $_POST['LinkedDataObjectCodes'] : [],
                $excludedOrgUnitIds,
                $userId
            );

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            $this->flashSuccess($id > 0 ? 'Project updated.' : 'Project created.');
            header('Location: index.php?route=strategy-setup/projects');
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('Project save failed', $e, [
                'projectId' => $id,
                'fiscalYearId' => $fy,
                'projectCode' => $projectCode,
                'sourceDataObjectCode' => $sourceDataObjectCode,
            ]);
            $this->flashError('Project save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-setup/project-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function deleteProject(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/projects');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $model = $this->buildModel();
            $blockers = $model->getProjectArchiveBlockers($id);
            if ($blockers !== []) {
                $this->flashError('Project cannot be archived because it is still in use by ' . implode(', ', $blockers) . '.');
            } else {
                $model->deactivateProject($id, (int) SessionHelper::get('auth.user_id', 1));
                $this->flashSuccess('Project archived.');
            }
        }

        header('Location: index.php?route=strategy-setup/projects');
        exit;
    }

    public function importProjectOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/projects');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = strtolower(trim((string) ($_POST['reset_mode'] ?? 'none')));

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing project records.');
            header('Location: index.php?route=strategy-setup/projects');
            exit;
        }

        try {
            $summary = $this->buildModel()->importProjectOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('PROJECT', $fy, $summary, $resetMode);
            $message = 'Project records imported. Created: ' . (int) ($summary['created'] ?? 0)
                . ', matched existing: ' . (int) ($summary['matched'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0) . '.';
            $reset = is_array($summary['reset'] ?? null) ? $summary['reset'] : [];
            if (($reset['mode'] ?? 'none') !== 'none') {
                $message .= ' Reset mode: ' . strtoupper((string) ($reset['mode'] ?? 'NONE'))
                    . '. Source maps cleared: ' . (int) ($reset['source_maps_cleared'] ?? 0)
                    . ', projects archived: ' . (int) ($reset['projects_archived'] ?? 0)
                    . ', projects deleted: ' . (int) ($reset['projects_deleted'] ?? 0)
                    . ', preserved: ' . (int) ($reset['projects_preserved'] ?? 0)
                    . ', blocked: ' . (int) ($reset['blocked'] ?? 0) . '.';
            }
            $this->flashSuccess($message);
        } catch (\Throwable $e) {
            $this->flashError('Project record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/projects');
        exit;
    }

    public function subProgramForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceDataObjectCode = trim((string) ($_GET['data_object_code'] ?? ''));
        $sourceSubProgramCode = trim((string) ($_GET['sub_program_code'] ?? ''));
        $record = $id > 0 ? $model->getSubProgram($id) : null;
        $source = null;

        if ($record === null && $sourceDataObjectCode !== '' && $sourceSubProgramCode !== '') {
            $source = $model->getSegmentBackedSubProgramSource($fy, $sourceDataObjectCode, $sourceSubProgramCode);
            if ($source !== null) {
                $resolvedSourceCode = (string) ($source['SourceDataObjectCode'] ?? $sourceDataObjectCode);
                $record = array_merge($source, $model->findSubProgramOverlayBySource($fy, $resolvedSourceCode, $sourceSubProgramCode) ?? []);
            }
        }

        $this->render('strategy/SubProgramForm', [
            'title' => $id > 0 ? 'Edit SubProgram Overlay' : 'Configure SubProgram Overlay',
            'record' => $record,
            'programOptions' => $model->listProgramOptions(),
            'sourceSubProgram' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'SUBPROGRAM'),
        ]);
    }

    public function saveSubProgram(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sub-programs');

        $model = $this->buildModel();
        $id = (int) ($_POST['SubProgramID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string) ($_POST['OrgUnitDataObjectCode'] ?? ''));
        $sourceDataObjectCode = trim((string) ($_POST['SourceDataObjectCode'] ?? $dataObjectCode));
        $subProgramCode = trim((string) ($_POST['SubProgramCode'] ?? ''));
        $subProgramName = trim((string) ($_POST['SubProgramName'] ?? ''));
        $payload = [
            'ProgramID' => (int) ($_POST['ProgramID'] ?? 0),
            'SubProgramCode' => $this->nullableTrim($subProgramCode),
            'SubProgramName' => $subProgramName,
            'SubProgramDescription' => $this->nullableTrim($_POST['SubProgramDescription'] ?? null),
            'SourceFiscalYearID' => $fy > 0 ? $fy : null,
            'SourceDataObjectCode' => $this->nullableTrim($sourceDataObjectCode),
            'SourceSegmentNo' => (int) ($_POST['SourceSegmentNo'] ?? 0) ?: null,
            'SourceSegmentCode' => $this->nullableTrim($subProgramCode),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($id <= 0) {
            $source = $model->getSegmentBackedSubProgramSource($fy, $dataObjectCode, $subProgramCode);
            if ($source === null) {
                $this->flashError('Selected subprogram source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/sub-programs');
                exit;
            }
            $sourceDataObjectCode = trim((string) ($source['SourceDataObjectCode'] ?? $dataObjectCode));
            $payload['SourceDataObjectCode'] = $this->nullableTrim($sourceDataObjectCode);
            if ($payload['ProgramID'] > 0 && !$model->programBelongsToDataObject($payload['ProgramID'], $fy, $dataObjectCode)) {
                $this->flashError('Selected program record does not belong to the same DataScope org unit as the subprogram source.');
                header('Location: index.php?route=strategy-setup/sub-program-form&data_object_code=' . urlencode($dataObjectCode) . '&sub_program_code=' . urlencode($subProgramCode));
                exit;
            }
            $existing = $model->findSubProgramOverlayBySource($fy, $sourceDataObjectCode, $subProgramCode);
            if ($existing !== null) {
                $id = (int) ($existing['SubProgramID'] ?? 0);
            }
            $payload['SubProgramName'] = (string) ($source['SubProgramName'] ?? $subProgramName);
        }

        if ($payload['ProgramID'] <= 0 || $payload['SubProgramName'] === '') {
            $this->flashError('Subprogram name and parent program are required.');
            header('Location: index.php?route=strategy-setup/sub-program-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveSubProgram($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Subprogram record updated.' : 'Subprogram record created.');
        header('Location: index.php?route=strategy-setup/sub-programs');
        exit;
    }

    public function deleteSubProgram(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sub-programs');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateSubProgram($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Subprogram archived.');
        }

        header('Location: index.php?route=strategy-setup/sub-programs');
        exit;
    }

    public function importSubProgramOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/sub-programs');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing subprogram records.');
            header('Location: index.php?route=strategy-setup/sub-programs');
            exit;
        }

        try {
            $summary = $this->buildModel()->importSubProgramOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('SUBPROGRAM', $fy, $summary, $resetMode);
            $this->flashSuccess(
                'Subprogram records imported. Created: '
                . (int) ($summary['created'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0)
                . ', missing parent link: ' . (int) ($summary['missing_parent_link'] ?? 0)
                . ', missing parent record: ' . (int) ($summary['missing_parent_overlay'] ?? 0)
                . '.'
                . $this->formatImportResetMessage($summary)
            );
        } catch (\Throwable $e) {
            $this->flashError('Subprogram record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/sub-programs');
        exit;
    }

    public function economicItems(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        $this->render('strategy/EconomicItemList', [
            'title' => 'Strategic Economic Items',
            'records' => $model->listSegmentBackedEconomicItems($fy, $q),
            'q' => $q,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'ECONOMIC'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'ECONOMIC'),
        ]);
    }

    public function economicItemForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceEconomicCode = trim((string) ($_GET['economic_code'] ?? ''));
        $record = $id > 0 ? $model->getEconomicItem($id) : null;
        $source = null;

        if ($record === null && $sourceEconomicCode !== '') {
            $source = $model->getSegmentBackedEconomicSource($fy, $sourceEconomicCode);
            if ($source !== null) {
                $record = array_merge($source, $model->getEconomicItemByCode($sourceEconomicCode) ?? []);
            }
        }

        $this->render('strategy/EconomicItemForm', [
            'title' => $id > 0 ? 'Edit Economic Item Overlay' : 'Configure Economic Item Overlay',
            'record' => $record,
            'parentOptions' => $model->listEconomicItemOptions(),
            'sourceEconomicItem' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'ECONOMIC'),
        ]);
    }

    public function saveEconomicItem(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/economic-items');

        $model = $this->buildModel();
        $id = (int) ($_POST['EconomicItemID'] ?? 0);
        $parentId = (int) ($_POST['ParentEconomicItemID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $economicCode = trim((string) ($_POST['EconomicCode'] ?? ''));
        $economicName = trim((string) ($_POST['EconomicName'] ?? ''));
        $payload = [
            'ParentEconomicItemID' => $parentId > 0 ? $parentId : null,
            'EconomicCode' => $economicCode,
            'EconomicName' => $economicName,
            'EconomicLevel' => $_POST['EconomicLevel'] !== '' ? (int) $_POST['EconomicLevel'] : null,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($id <= 0) {
            $source = $model->getSegmentBackedEconomicSource($fy, $economicCode);
            if ($source === null) {
                $this->flashError('Selected economic item source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/economic-items');
                exit;
            }
            $existing = $model->getEconomicItemByCode($economicCode);
            if ($existing !== null) {
                $id = (int) ($existing['EconomicItemID'] ?? 0);
            }
            $payload['EconomicName'] = (string) ($source['EconomicName'] ?? $economicName);
        }

        if ($payload['EconomicCode'] === '' || $payload['EconomicName'] === '') {
            $this->flashError('Economic code and name are required.');
            header('Location: index.php?route=strategy-setup/economic-item-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveEconomicItem($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Economic item record updated.' : 'Economic item record created.');
        header('Location: index.php?route=strategy-setup/economic-items');
        exit;
    }

    public function deleteEconomicItem(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/economic-items');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateEconomicItem($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Economic item archived.');
        }

        header('Location: index.php?route=strategy-setup/economic-items');
        exit;
    }

    public function importEconomicItemOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/economic-items');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing economic item records.');
            header('Location: index.php?route=strategy-setup/economic-items');
            exit;
        }

        try {
            $summary = $this->buildModel()->importEconomicItemOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('ECONOMIC', $fy, $summary, $resetMode);
            $this->flashSuccess('Economic item records imported. Created: ' . (int) ($summary['created'] ?? 0) . ', skipped: ' . (int) ($summary['skipped'] ?? 0) . '.' . $this->formatImportResetMessage($summary));
        } catch (\Throwable $e) {
            $this->flashError('Economic item record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/economic-items');
        exit;
    }

    public function fundingSources(): void
    {
        $model = $this->buildModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        $this->render('strategy/FundingSourceList', [
            'title' => 'Strategic Funding Sources',
            'records' => $model->listSegmentBackedFundingSources($fy, $q),
            'q' => $q,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE'),
            'overlayTrackingAvailable' => $model->hasFundingSourceSourceTrackingColumns(),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'FUNDING_SOURCE'),
        ]);
    }

    public function fundingSourceForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceSegmentCode = trim((string) ($_GET['source_segment_code'] ?? ''));
        $record = $id > 0 ? $model->getFundingSource($id) : null;
        $source = null;

        if (!$model->hasFundingSourceSourceTrackingColumns()) {
            $this->flashError('Funding source records require the alter_tblSbFundingSource_add_source_segment.sql migration before they can be configured.');
            header('Location: index.php?route=strategy-setup/funding-sources');
            exit;
        }

        if ($record === null && $sourceSegmentCode !== '') {
            $source = $model->getSegmentBackedFundingSource($fy, $sourceSegmentCode);
            if ($source !== null) {
                $record = array_merge($source, $model->getFundingSourceBySourceCode($fy, $sourceSegmentCode) ?? []);
            }
        }

        $this->render('strategy/FundingSourceForm', [
            'title' => $id > 0 ? 'Edit Funding Source Overlay' : 'Configure Funding Source Overlay',
            'record' => $record,
            'sourceFundingSource' => $source,
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE'),
            'fundingTypeOptions' => $model->listFundingTypeOptions(),
        ]);
    }

    public function saveFundingSource(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-sources');

        $model = $this->buildModel();
        $id = (int) ($_POST['FundingSourceID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $sourceSegmentCode = trim((string) ($_POST['SourceSegmentCode'] ?? ''));
        $fundingSourceName = trim((string) ($_POST['FundingSourceName'] ?? ''));

        if (!$model->hasFundingSourceSourceTrackingColumns()) {
            $this->flashError('Funding source records require the alter_tblSbFundingSource_add_source_segment.sql migration before they can be saved.');
            header('Location: index.php?route=strategy-setup/funding-sources');
            exit;
        }

        $payload = [
            'FundingTypeCode' => strtoupper(trim((string) ($_POST['FundingTypeCode'] ?? ''))),
            'FundingSourceName' => $fundingSourceName,
            'DonorName' => $this->nullableTrim($_POST['DonorName'] ?? null),
            'ConditionsText' => $this->nullableTrim($_POST['ConditionsText'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            'SourceFiscalYearID' => $fy > 0 ? $fy : null,
            'SourceSegmentCode' => $sourceSegmentCode !== '' ? $sourceSegmentCode : null,
        ];

        if ($id <= 0) {
            $source = $model->getSegmentBackedFundingSource($fy, $sourceSegmentCode);
            if ($source === null) {
                $this->flashError('Selected funding source was not found in the configured segment values.');
                header('Location: index.php?route=strategy-setup/funding-sources');
                exit;
            }
            $existing = $model->getFundingSourceBySourceCode($fy, $sourceSegmentCode);
            if ($existing !== null) {
                $id = (int) ($existing['FundingSourceID'] ?? 0);
            }
            $payload['FundingSourceName'] = (string) ($source['FundingSourceName'] ?? $fundingSourceName);
        }

        if ($payload['FundingTypeCode'] === '' || $payload['FundingSourceName'] === '') {
            $this->flashError('Funding type and funding source name are required.');
            header('Location: index.php?route=strategy-setup/funding-source-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveFundingSource($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Funding source record updated.' : 'Funding source record created.');
        header('Location: index.php?route=strategy-setup/funding-sources');
        exit;
    }

    public function deleteFundingSource(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-sources');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildModel()->deactivateFundingSource($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Funding source archived.');
        }

        header('Location: index.php?route=strategy-setup/funding-sources');
        exit;
    }

    public function importFundingSourceOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-setup/funding-sources');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $defaultFundingTypeCode = strtoupper(trim((string) ($_POST['default_funding_type_code'] ?? 'DOMESTIC')));
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing funding source records.');
            header('Location: index.php?route=strategy-setup/funding-sources');
            exit;
        }

        try {
            $summary = $this->buildModel()->importFundingSourceOverlaysFromSegments(
                $fy,
                (int) SessionHelper::get('auth.user_id', 1),
                $defaultFundingTypeCode,
                $resetMode
            );
            $this->recordDimensionImport('FUNDING_SOURCE', $fy, $summary, $resetMode);
            $this->flashSuccess('Funding source records imported. Created: ' . (int) ($summary['created'] ?? 0) . ', skipped: ' . (int) ($summary['skipped'] ?? 0) . '.' . $this->formatImportResetMessage($summary));
        } catch (\Throwable $e) {
            $this->flashError('Funding source record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-setup/funding-sources');
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

    private function getStrategyContextLabels(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return [
                'YearLabel' => $fiscalYearId > 0 ? 'FY ' . $fiscalYearId : 'Not set',
                'VersionLabel' => '',
            ];
        }

        return $this->buildReportingModel()->getContextLabels($fiscalYearId, $versionId);
    }

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-setup/sectors'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function buildAuditModel(): AuditModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }
        return new AuditModel($this->db);
    }

    private function recordDimensionImport(string $dimensionCode, int $fiscalYearId, array $summary, string $resetMode): void
    {
        try {
            $this->buildAuditModel()->insert([
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
                'Username' => (string) SessionHelper::get('auth.username', 'system'),
                'Action' => 'IMPORT_DIMENSION',
                'Entity' => 'STRATEGY_DIMENSION',
                'EntityKey' => strtoupper(trim($dimensionCode)),
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => (int) ($this->context()['VersionID'] ?? 0) ?: null,
                'Details' => [
                    'reset_mode' => strtolower(trim($resetMode)) ?: 'none',
                    'summary' => $summary,
                ],
            ]);
        } catch (\Throwable) {
        }
    }

    private function formatImportResetMessage(array $summary): string
    {
        $reset = is_array($summary['reset'] ?? null) ? $summary['reset'] : [];
        if (($reset['mode'] ?? 'none') === 'none') {
            return '';
        }

        return ' Reset summary: archived '
            . (int) ($reset['records_archived'] ?? $reset['projects_archived'] ?? 0)
            . ', deleted ' . (int) ($reset['records_deleted'] ?? $reset['projects_deleted'] ?? 0)
            . ', preserved ' . (int) ($reset['records_preserved'] ?? $reset['projects_preserved'] ?? 0)
            . ', blocked ' . (int) ($reset['blocked'] ?? 0)
            . (
                array_key_exists('source_maps_cleared', $reset)
                    ? ', source maps cleared ' . (int) ($reset['source_maps_cleared'] ?? 0)
                    : ''
            )
            . '.';
    }
}
