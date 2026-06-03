<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;

final class BaseConfigurationController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function readiness(): void
    {
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $model = new BaseConfigurationReadinessModel($this->db);
        $dashboard = $model->getDashboard($fy, $ver);

        $this->render('config/BaseConfigurationReadiness', [
            'title' => 'Base Configuration Readiness',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'summary' => $dashboard['summary'] ?? [],
            'checks' => $dashboard['checks'] ?? [],
        ]);
    }
}
