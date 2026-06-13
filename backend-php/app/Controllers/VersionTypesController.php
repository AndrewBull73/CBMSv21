<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;
use App\Models\VersionTypesAdminModel;

require_once __DIR__ . '/../../shared/csrf.php';

final class VersionTypesController extends BaseController
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
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new VersionTypesAdminModel($this->db);
        $this->render('config/VersionTypesList', [
            'title' => 'Version Types',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels(),
        ]);
    }

    public function form(): void
    {
        $versionTypeId = (int) ($_GET['id'] ?? 0);
        $model = new VersionTypesAdminModel($this->db);
        $record = $versionTypeId > 0 ? $model->getById($versionTypeId) : null;

        $this->render('config/VersionTypesForm', [
            'title' => $record !== null ? 'Edit Version Type' : 'Create Version Type',
            'record' => $record,
            'contextLabels' => $this->buildContextLabels(),
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
            header('Location: index.php?route=version-types/list');
            return;
        }

        $payload = [
            'VersionTypeID' => (int) ($_POST['VersionTypeID'] ?? 0),
            'VersionTypeCode' => trim((string) ($_POST['VersionTypeCode'] ?? '')),
            'VersionTypeName' => trim((string) ($_POST['VersionTypeName'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
        ];

        $model = new VersionTypesAdminModel($this->db);
        try {
            $savedId = $model->save($payload);
            $this->auditEvent($payload['VersionTypeID'] > 0 ? 'UPDATE' : 'CREATE', 'VersionType', (string) $savedId, [
                'VersionTypeID' => $savedId,
                'VersionTypeCode' => strtoupper($payload['VersionTypeCode']),
                'VersionTypeName' => $payload['VersionTypeName'],
                'Description' => $payload['Description'],
                'ActiveFlag' => $payload['ActiveFlag'],
            ]);
            $this->flashSuccess($payload['VersionTypeID'] > 0 ? 'Version type updated.' : 'Version type created.');
            header('Location: index.php?route=version-types/list');
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('VersionTypesController::save failed', $e, [
                'versionTypeId' => $payload['VersionTypeID'],
                'versionTypeCode' => $payload['VersionTypeCode'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'version-types/form',
                'id' => (int) ($payload['VersionTypeID'] ?? 0),
            ]);
            header('Location: index.php?' . $query);
            return;
        }
    }

    private function buildContextLabels(): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $model = new BaseConfigurationReadinessModel($this->db);
        return $model->getContextLabels($fy, $ver);
    }
}
