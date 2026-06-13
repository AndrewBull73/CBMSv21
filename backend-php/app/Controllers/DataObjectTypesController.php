<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;
use App\Models\DataObjectTypesAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class DataObjectTypesController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'container' => trim((string) ($_GET['container'] ?? '')),
        ];

        $model = new DataObjectTypesAdminModel($this->db);
        $this->render('config/DataObjectTypesList', [
            'title' => 'Data Object Types',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels(),
        ]);
    }

    public function form(): void
    {
        $dataObjectTypeId = (int) ($_GET['id'] ?? 0);
        $model = new DataObjectTypesAdminModel($this->db);
        $record = $dataObjectTypeId > 0 ? $model->getById($dataObjectTypeId) : null;

        $this->render('config/DataObjectTypesForm', [
            'title' => $record !== null ? 'Edit Data Object Type' : 'Create Data Object Type',
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
            header('Location: index.php?route=dataobject-types/list');
            return;
        }

        $payload = [
            'DataObjectTypeID' => (int) ($_POST['DataObjectTypeID'] ?? 0),
            'DataObjectTypeName' => trim((string) ($_POST['DataObjectTypeName'] ?? '')),
            'SegmentNo' => trim((string) ($_POST['SegmentNo'] ?? '')),
            'Level' => trim((string) ($_POST['Level'] ?? '')),
            'DataContainer' => isset($_POST['DataContainer']) ? 1 : 0,
        ];

        $model = new DataObjectTypesAdminModel($this->db);
        try {
            $savedId = $model->save($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->auditEvent($payload['DataObjectTypeID'] > 0 ? 'UPDATE' : 'CREATE', 'DataObjectType', (string) $savedId, [
                'DataObjectTypeID' => $savedId,
                'DataObjectTypeName' => $payload['DataObjectTypeName'],
                'SegmentNo' => $payload['SegmentNo'] !== '' ? (int) $payload['SegmentNo'] : null,
                'Level' => $payload['Level'] !== '' ? (int) $payload['Level'] : null,
                'DataContainer' => $payload['DataContainer'],
            ]);
            $this->flashSuccess($payload['DataObjectTypeID'] > 0 ? 'Data object type updated.' : 'Data object type created.');
            header('Location: index.php?route=dataobject-types/list');
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectTypesController::save failed', $e, [
                'dataObjectTypeId' => $payload['DataObjectTypeID'],
                'dataObjectTypeName' => $payload['DataObjectTypeName'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'dataobject-types/form',
                'id' => (int) ($payload['DataObjectTypeID'] ?? 0),
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
