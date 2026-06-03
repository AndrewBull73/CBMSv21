<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemSettingsModel;
use App\Shared\SessionHelper;

final class HomeController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
        'index' => ['auth' => true]
    ];

    public function __construct()
    {
        parent::__construct();
        app_log("HomeController: Constructed", [
            'userId' => (int) SessionHelper::get('auth.user_id', 0),
            'username' => (string) SessionHelper::get('auth.username', ''),
            'roles' => SessionHelper::get('auth.roles', []),
            'perms' => SessionHelper::get('auth.perms', []),
            'session_id' => session_id()
        ], 'info');
    }

    public function index(): void
    {
        require __DIR__ . '/../../config/db.php';

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $username = (string) SessionHelper::get('auth.username', 'guest');
        $settingsModel = new SystemSettingsModel($conn);
        $clientName = trim((string) ($settingsModel->get('CLIENT_NAME', 'Government of Lesotho') ?? 'Government of Lesotho'));
        if ($clientName === '') {
            $clientName = 'Government of Lesotho';
        }

        app_log("HomeController: Rendering home page", [
            'userId' => $userId,
            'username' => $username,
            'clientName' => $clientName,
            'roles' => SessionHelper::get('auth.roles', []),
            'perms' => SessionHelper::get('auth.perms', []),
            'session_id' => session_id()
        ], 'info');

        $this->render('home/HomeIndexView', [
            'title' => __t('home_title'),
            'userId' => $userId,
            'username' => $username,
            'clientName' => $clientName,
        ]);
    }
}
