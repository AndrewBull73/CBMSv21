<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\VersionsAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class VersionsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'fy' => (int) ($_GET['fy'] ?? 0),
            'version_type_id' => (int) ($_GET['version_type_id'] ?? 0),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new VersionsAdminModel($this->db);
        $ctx = $this->context();
        $this->render('config/VersionsList', [
            'title' => 'Versions',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'fiscalYears' => $model->listFiscalYears(),
            'versionTypes' => $model->listVersionTypes(),
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
        ]);
    }

    public function form(): void
    {
        $fiscalYearId = (int) ($_GET['fy'] ?? 0);
        $versionId = (int) ($_GET['id'] ?? 0);
        $model = new VersionsAdminModel($this->db);
        $ctx = $this->context();
        $record = ($fiscalYearId > 0 && $versionId > 0) ? $model->getByKey($fiscalYearId, $versionId) : null;
        $isEditing = $record !== null;

        if ($record === null && $fiscalYearId > 0) {
            $record = [
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $model->suggestNextVersionId($fiscalYearId),
                'VersionStatus' => 'DRAFT',
                'VersionTypeID' => 1,
                'IsActive' => 1,
                'IsDefault' => 0,
                'CeilingsOn' => 0,
                'IsSystemDefault' => 0,
            ];
        }

        $selectedBaseFiscalYearId = (int) ($record['BaseFiscalYearID'] ?? 0);

        $this->render('config/VersionsForm', [
            'title' => $isEditing ? 'Edit Version' : 'Create Version',
            'record' => $record,
            'isEditing' => $isEditing,
            'fiscalYears' => $model->listFiscalYears(),
            'versionTypes' => $model->listVersionTypes(),
            'statusOptions' => $model->listStatusOptions(),
            'baseVersions' => $model->listBaseVersionOptions(),
            'currencyOptions' => $model->listCurrencies(),
            'hasCurrenciesTable' => $model->hasCurrenciesTable(),
            'selectedBaseFiscalYearId' => $selectedBaseFiscalYearId,
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
        ]);
    }

    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=versions/list');
            return;
        }

        $payload = [
            'FiscalYearID' => (int) ($_POST['FiscalYearID'] ?? 0),
            'VersionID' => $_POST['VersionID'] ?? '',
            'OriginalFiscalYearID' => $_POST['OriginalFiscalYearID'] ?? '',
            'OriginalVersionID' => $_POST['OriginalVersionID'] ?? '',
            'VersionLabel' => trim((string) ($_POST['VersionLabel'] ?? '')),
            'VersionTypeID' => (int) ($_POST['VersionTypeID'] ?? 0),
            'VersionStatus' => trim((string) ($_POST['VersionStatus'] ?? '')),
            'BaseFiscalYearID' => $_POST['BaseFiscalYearID'] ?? '',
            'BaseVersionID' => $_POST['BaseVersionID'] ?? '',
            'ActualsPeriodID' => $_POST['ActualsPeriodID'] ?? '',
            'BaseCurrency' => trim((string) ($_POST['BaseCurrency'] ?? '')),
            'CeilingsOn' => isset($_POST['CeilingsOn']) ? 1 : 0,
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
            'IsDefault' => isset($_POST['IsDefault']) ? 1 : 0,
            'IsSystemDefault' => isset($_POST['IsSystemDefault']) ? 1 : 0,
        ];

        $model = new VersionsAdminModel($this->db);

        try {
            $saved = $model->save($payload, (string) SessionHelper::get('auth.username', 'system'));
            $existing = $model->getByKey((int) $saved['FiscalYearID'], (int) $saved['VersionID']);
            $this->auditEvent(!empty($_POST['_editing']) ? 'UPDATE' : 'CREATE', 'Version', $saved['FiscalYearID'] . ':' . $saved['VersionID'], [
                'FiscalYearID' => (int) $saved['FiscalYearID'],
                'VersionID' => (int) $saved['VersionID'],
                'VersionLabel' => $payload['VersionLabel'],
                'VersionTypeID' => $payload['VersionTypeID'],
                'VersionStatus' => $payload['VersionStatus'],
                'BaseFiscalYearID' => $payload['BaseFiscalYearID'],
                'BaseVersionID' => $payload['BaseVersionID'],
                'ActualsPeriodID' => $payload['ActualsPeriodID'],
                'BaseCurrency' => $payload['BaseCurrency'],
                'CeilingsOn' => $payload['CeilingsOn'],
                'IsActive' => $payload['IsActive'],
                'IsDefault' => $payload['IsDefault'],
                'IsSystemDefault' => $payload['IsSystemDefault'],
            ]);
            $this->flashSuccess($existing !== null && !empty($_POST['_editing']) ? 'Version updated.' : 'Version saved.');
            header('Location: index.php?route=versions/list&fy=' . (int) $saved['FiscalYearID']);
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('VersionsController::save failed', $e, [
                'fiscalYearId' => $payload['FiscalYearID'],
                'versionId' => $payload['VersionID'],
                'versionLabel' => $payload['VersionLabel'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            $queryParams = [
                'route' => 'versions/form',
                'fy' => (int) ($payload['FiscalYearID'] ?? 0),
            ];
            if (!empty($_POST['_editing'])) {
                $queryParams['id'] = (int) ($payload['VersionID'] ?? 0);
            }
            $query = http_build_query($queryParams);
            header('Location: index.php?' . $query);
            return;
        }
    }

    private function buildContextLabels(int $fiscalYearId, int $versionId): array
    {
        $labels = [
            'YearLabel' => '',
            'VersionLabel' => '',
        ];

        if (!$this->db instanceof \PDO) {
            return $labels;
        }

        if ($fiscalYearId > 0) {
            $fyStmt = $this->db->prepare('SELECT TOP 1 YearLabel FROM dbo.tblFiscalYears WHERE FiscalYearID = :fiscalYearId');
            $fyStmt->execute([':fiscalYearId' => $fiscalYearId]);
            $labels['YearLabel'] = (string) ($fyStmt->fetchColumn() ?: '');
        }

        if ($fiscalYearId > 0 && $versionId > 0) {
            $verStmt = $this->db->prepare('SELECT TOP 1 VersionLabel FROM dbo.tblVersions WHERE FiscalYearID = :fiscalYearId AND VersionID = :versionId');
            $verStmt->execute([
                ':fiscalYearId' => $fiscalYearId,
                ':versionId' => $versionId,
            ]);
            $labels['VersionLabel'] = (string) ($verStmt->fetchColumn() ?: '');
        }

        return $labels;
    }
}
