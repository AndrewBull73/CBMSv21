<?php
declare(strict_types=1);

namespace App\Controllers;

final class DashboardController extends BaseController
{
    protected array $acl = [
        'index' => [
            'auth' => true,
            'permsAny' => ['DASHBOARD_VIEW', 'DASHBOARD_ADMIN', 'ADMIN_ALL'],
        ],
        'flexdash' => [
            'auth' => true,
            'permsAny' => ['DASHBOARD_VIEW', 'DASHBOARD_ADMIN', 'ANALYTICS_VIEW', 'ADMIN_ALL'],
        ],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->render('dashboards/HeadDash', [
            'title' => 'CBMS Analytics Dashboard',
        ]);
    }

    public function flexdash(): void
    {
        $this->render('dashboards/FlexDash', [
            'title' => 'CBMS Flexmonster Dashboard',
        ]);
    }
}
