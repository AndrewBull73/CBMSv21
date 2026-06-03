<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccessMatrixModel;

final class AccessMatrixController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['ROLES_VIEW', 'ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function index(): void
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'functional_area' => trim((string) ($_GET['functional_area'] ?? '')),
            'permission' => trim((string) ($_GET['permission'] ?? '')),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'access_type' => trim((string) ($_GET['access_type'] ?? '')),
        ];

        $model = new AccessMatrixModel($this->db);
        $dashboard = $model->getDashboard($filters);

        $this->render('config/AccessMatrix', [
            'title' => 'Access Matrix',
            'filters' => $filters,
            'summary' => $dashboard['summary'] ?? [],
            'rows' => $dashboard['rows'] ?? [],
            'modules' => $dashboard['modules'] ?? [],
            'functionalAreas' => $dashboard['functional_areas'] ?? [],
            'groupedRows' => $dashboard['grouped_rows'] ?? [],
            'permissions' => $dashboard['permissions'] ?? [],
            'roles' => $dashboard['roles'] ?? [],
        ]);
    }
}
