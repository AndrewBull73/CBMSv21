<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\SystemSettingsModel;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/logger.php';

final class SystemSettingsController extends BaseController
{
    protected array $acl = [
        '*'        => ['auth' => true],
        'list'     => ['permsAny' => ['SYSSETTINGS_VIEW','SYSSETTINGS_ADMIN']],
        'usageMap' => ['permsAny' => ['SYSSETTINGS_VIEW','SYSSETTINGS_ADMIN']],
        'save'     => ['permsAny' => ['SYSSETTINGS_EDIT','SYSSETTINGS_ADMIN']],
    ];

    private SystemSettingsModel $model;

    public function __construct()
    {
        parent::__construct(); // ✅ enforce auth + ACL checks
        
        require __DIR__ . '/../../config/db.php';   // creates $conn (PDO)
        require_once __DIR__ . '/../Models/SystemSettingsModel.php';
        $this->model = new SystemSettingsModel($conn);
    }

    public function list(): void
    {
        $rows  = $this->model->listAll();
        $flash = SessionHelper::get('flash.message', '');

        $this->render('system/SystemSettingsListView', [
            'title' => __t('system_settings'),
            'rows'  => $rows,
            'flash' => $flash,
        ]);

        if ($flash !== '') {
            SessionHelper::forget('flash.message');
        }
    }

    public function usageMap(): void
    {
        require_once __DIR__ . '/../Models/SystemSettingsUsageMapModel.php';
        $model = new \App\Models\SystemSettingsUsageMapModel($this->db);
        $dashboard = $model->getDashboard();

        $this->render('system/SystemSettingsUsageView', [
            'title' => 'System Settings Usage Map',
            'summary' => $dashboard['summary'] ?? [],
            'groups' => $dashboard['groups'] ?? [],
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
            header('Location: index.php?route=system-settings/list'); 
            return;
        }

        $key  = trim((string)($_POST['SettingKey'] ?? ''));
        $val  = (string)($_POST['SettingValue'] ?? '');
        $type = strtolower(trim((string)($_POST['SettingType'] ?? 'string')));
        $description = trim((string)($_POST['Description'] ?? ''));
        $category = trim((string)($_POST['Category'] ?? ''));

        if ($key === '') {
            $this->flashError(__t('missing_setting_key'));
            header('Location: index.php?route=system-settings/list'); 
            return;
        }

        $start = microtime(true);

        $ok = $this->model->set(
            $key,
            $val,
            $type,
            (string)SessionHelper::get('auth.username', 'system'),
            $description !== '' ? $description : null,
            $category !== '' ? $category : null
        );

        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $this->auditEvent($ok ? 'UPDATE' : 'DENIED', 'SystemSettings', $key, [
            'value' => $val,
            'type' => $type,
            'description' => $description,
            'category' => $category,
            'error' => $ok ? null : $this->model->getLastError(),
            'elapsedMs' => $elapsedMs,
        ]);

        if ($ok) {
            $this->flashSuccess(__t('setting_saved', ['key' => $key]));
        } else {
            app_log('SystemSettingsController::save failed', [
                'settingKey' => $key,
                'error' => $this->model->getLastError(),
            ], 'error');
            $this->flashError(__t('save_failed_detail', ['msg' => $this->model->getLastError()]));
        }

        header('Location: index.php?route=system-settings/list');
    }
}
