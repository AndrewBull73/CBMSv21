<?php
declare(strict_types=1);

namespace App\Controllers;

final class DashboardController extends BaseController
{
    protected array $acl = [
        'index' => [
            'auth' => true,
            'rolesAny' => [
                'Dashboard User',
                'Dashboard Administrator',
                'System Administrator',
            ],
        ],
        'flexdash' => [
            'auth' => true,
            'rolesAny' => [
                'Dashboard User',
                'Dashboard Administrator',
                'Analytics User',
                'Analytics Administrator',
                'System Administrator',
            ],
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
