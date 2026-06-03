<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\FinancialConfigurationReadinessModel;

final class FinancialConfigurationController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_VIEW', 'FIN_CONFIG_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function readiness(): void
    {
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $model = new FinancialConfigurationReadinessModel($this->db);
        $dashboard = $model->getDashboard($fy, $ver);

        $this->render('config/FinancialConfigurationReadiness', [
            'title' => 'Financial & Calculation Configuration Readiness',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'summary' => $dashboard['summary'] ?? [],
            'checks' => $dashboard['checks'] ?? [],
        ]);
    }
}
