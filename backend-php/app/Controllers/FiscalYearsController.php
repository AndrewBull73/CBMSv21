<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\FiscalYearsAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class FiscalYearsController extends BaseController
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

        $model = new FiscalYearsAdminModel($this->db);
        $ctx = $this->context();
        $this->render('config/FiscalYearsList', [
            'title' => 'Fiscal Years',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels((int) ($ctx['FiscalYearID'] ?? 0), (int) ($ctx['VersionID'] ?? 0)),
        ]);
    }

    public function form(): void
    {
        $fiscalYearId = (int) ($_GET['id'] ?? 0);
        $model = new FiscalYearsAdminModel($this->db);
        $ctx = $this->context();

        $this->render('config/FiscalYearsForm', [
            'title' => $fiscalYearId > 0 ? 'Edit Fiscal Year' : 'Create Fiscal Year',
            'record' => $fiscalYearId > 0 ? $model->getById($fiscalYearId) : null,
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
            header('Location: index.php?route=fiscal-years/list');
            return;
        }

        $payload = [
            'FiscalYearID' => (int) ($_POST['FiscalYearID'] ?? 0),
            'OriginalFiscalYearID' => $_POST['OriginalFiscalYearID'] ?? '',
            'YearLabel' => trim((string) ($_POST['YearLabel'] ?? '')),
            'StartDate' => trim((string) ($_POST['StartDate'] ?? '')),
            'EndDate' => trim((string) ($_POST['EndDate'] ?? '')),
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
            'IsSystemDefault' => isset($_POST['IsSystemDefault']) ? 1 : 0,
        ];

        $model = new FiscalYearsAdminModel($this->db);

        try {
            $existing = $model->getById((int) $payload['FiscalYearID']);
            $model->save($payload, (string) SessionHelper::get('auth.username', 'system'));
            $this->auditEvent($existing !== null ? 'UPDATE' : 'CREATE', 'FiscalYear', (string) $payload['FiscalYearID'], [
                'YearLabel' => $payload['YearLabel'],
                'StartDate' => $payload['StartDate'],
                'EndDate' => $payload['EndDate'],
                'IsActive' => $payload['IsActive'],
                'IsSystemDefault' => $payload['IsSystemDefault'],
            ]);
            $this->flashSuccess($existing !== null ? 'Fiscal year updated.' : 'Fiscal year created.');
            header('Location: index.php?route=fiscal-years/list');
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('FiscalYearsController::save failed', $e, [
                'fiscalYearId' => $payload['FiscalYearID'],
                'yearLabel' => $payload['YearLabel'],
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
            header('Location: index.php?route=fiscal-years/form' . ((int) $payload['FiscalYearID'] > 0 ? '&id=' . (int) $payload['FiscalYearID'] : ''));
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
