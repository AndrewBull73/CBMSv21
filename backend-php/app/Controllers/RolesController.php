<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\AuditModel;
use App\Models\RoleModel;

require_once __DIR__ . '/../../shared/csrf.php';

final class RolesController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['ROLES_VIEW', 'ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['permsAny' => ['ROLES_VIEW', 'ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'view' => ['permsAny' => ['ROLES_VIEW', 'ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'edit' => ['permsAny' => ['ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['permsAny' => ['ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete' => ['permsAny' => ['ROLES_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function __construct()
    {
        parent::__construct(); // enforce auth + ACL checks
    }

    public function list(): void
    {
        require __DIR__ . '/../../config/db.php';
        $roles = [];
        if ($conn instanceof \PDO) {
            try {
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $st = $conn->query("
                    SELECT
                        r.RoleID,
                        r.RoleName,
                        r.Active,
                        r.DateCreated,
                        r.DateUpdated,
                        (
                            SELECT COUNT(DISTINCT ur.UserID)
                            FROM dbo.tblUserRoles ur
                            WHERE ur.RoleID = r.RoleID
                        ) AS AssignedUserCount,
                        (
                            SELECT COUNT(DISTINCT rp.PermissionID)
                            FROM dbo.tblRolePermissions rp
                            WHERE rp.RoleID = r.RoleID
                        ) AS PermissionCount
                    FROM dbo.tblRoles r
                    ORDER BY r.RoleName
                ");
                $roles = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                error_log('RolesController::list roles: ' . print_r($roles, true));
                if (empty($roles)) {
                    error_log('RolesController::list query returned empty result');
                }
            } catch (\Throwable $e) {
                error_log('RolesController::list query error: ' . $e->getMessage());
                $roles = [];
            }
        } else {
            error_log('RolesController::list invalid PDO connection');
        }

        $this->render('roles/RolesList', [
            'title' => __t('menu_roles'),
            'roles' => $roles,
        ]);
    }

    public function edit(): void
    {
        require __DIR__ . '/../../config/db.php';
        $id   = (int)($_GET['id'] ?? 0);
        $role = null;

        if ($id > 0 && $conn instanceof \PDO) {
            try {
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $st = $conn->prepare("
                    SELECT RoleID, RoleName, Active, DateCreated, DateUpdated
                    FROM dbo.tblRoles
                    WHERE RoleID = :id
                ");
                $st->execute([':id' => $id]);
                $role = $st->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('RolesController::edit query error: ' . $e->getMessage());
            }
        }

        $permissions = [];
        $selectedPermissionIds = [];
        if ($conn instanceof \PDO) {
            require_once __DIR__ . '/../Models/RoleModel.php';
            $roleModel = new RoleModel($conn);
            $permissions = $roleModel->listPermissions();
            $selectedPermissionIds = $roleModel->permissionIdsForRole($id);
        }

        $this->render('roles/RolesForm', [
            'title' => $id > 0 ? __t('edit_role') : __t('create_role'),
            'role'  => $role,
            'permissions' => $permissions,
            'selectedPermissionIds' => $selectedPermissionIds,
        ]);
    }

    public function save(): void
    {
        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../../shared/csrf.php';

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        $id     = (int)($_POST['RoleID'] ?? 0);
        $name   = trim((string)($_POST['RoleName'] ?? ''));
        $active = isset($_POST['Active']) ? 1 : 0;
        $permissionIds = array_values(array_filter(array_map('intval', (array)($_POST['PermissionIDs'] ?? [])), static fn(int $id): bool => $id > 0));

        // --- CSRF ---
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('csrf_failed'));
            header('Location: index.php?route=roles/edit' . ($id ? '&id='.$id : ''));
            exit;
        }

        // --- Validation ---
        if ($name === '') {
            $this->flashError(__t('role_name_required'));
            header('Location: index.php?route=roles/edit' . ($id ? '&id='.$id : ''));
            exit;
        }

        if ($conn instanceof \PDO) {
            try {
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                // Check duplicate
                $st = $conn->prepare("SELECT COUNT(*) FROM dbo.tblRoles WHERE RoleName = :name AND RoleID <> :id");
                $st->execute([':name' => $name, ':id' => $id]);
                $exists = (int)$st->fetchColumn();

                if ($exists > 0) {
                    $this->flashError(__t('role_name_exists'));
                    header('Location: index.php?route=roles/edit' . ($id ? '&id='.$id : ''));
                    exit;
                }

                // Audit model
                require_once __DIR__ . '/../Models/AuditModel.php';
                $audit = new AuditModel($conn);
                require_once __DIR__ . '/../Models/RoleModel.php';
                $roleModel = new RoleModel($conn);
                $savedRoleId = $id;

                // --- Save ---
                if ($id > 0) {
                    $st = $conn->prepare("
                        UPDATE dbo.tblRoles
                        SET RoleName = :name, Active = :active, DateUpdated = GETDATE()
                        WHERE RoleID = :id
                    ");
                    $st->execute([':name' => $name, ':active' => $active, ':id' => $id]);

                    $this->flashSuccess(__t('role_updated', ['role' => $name]));

                    // Audit log
                    $audit->insert([
                        'UserID'       => SessionHelper::get('auth.user_id'),
                        'Username'     => SessionHelper::get('auth.username', 'guest'),
                        'Action'       => 'UPDATE',
                        'Entity'       => 'Role',
                        'EntityKey'    => (string)$id,
                        'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'Details'      => [
                            'RoleName' => $name,
                            'Active'   => $active,
                        ],
                        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                        'VersionID'    => SessionHelper::get('VersionID'),
                    ]);

                } else {
                    $st = $conn->prepare("
                        INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
                        VALUES (:name, :active, GETDATE(), GETDATE())
                    ");
                    $st->execute([':name' => $name, ':active' => $active]);
                    $find = $conn->prepare("SELECT RoleID FROM dbo.tblRoles WHERE RoleName = :name");
                    $find->execute([':name' => $name]);
                    $savedRoleId = (int)($find->fetchColumn() ?: 0);

                    $this->flashSuccess(__t('role_created', ['role' => $name]));

                    // Audit log
                    $audit->insert([
                        'UserID'       => SessionHelper::get('auth.user_id'),
                        'Username'     => SessionHelper::get('auth.username', 'guest'),
                        'Action'       => 'CREATE',
                        'Entity'       => 'Role',
                        'EntityKey'    => (string)($savedRoleId ?: $name),
                        'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'Details'      => [
                            'RoleName' => $name,
                            'Active'   => $active,
                        ],
                        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                        'VersionID'    => SessionHelper::get('VersionID'),
                    ]);
                }

                if ($savedRoleId > 0) {
                    $roleModel->replaceRolePermissions($savedRoleId, $permissionIds);
                }
            } catch (\Throwable $e) {
                $this->logHandledException('RolesController::save failed', $e, [
                    'roleId' => $id,
                    'roleName' => $name,
                ]);
                $this->flashError(__t('role_save_failed') . ': ' . $e->getMessage());
            }
        }

        header('Location: index.php?route=roles/list');
        exit;
    }

    public function delete(): void
    {
        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../../shared/csrf.php';

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        $id = (int)($_POST['RoleID'] ?? 0);
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('csrf_failed'));
            header('Location: index.php?route=roles/list');
            exit;
        }

        if ($id <= 0 || !($conn instanceof \PDO)) {
            $this->flashError(__t('role_save_failed'));
            header('Location: index.php?route=roles/list');
            exit;
        }

        try {
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();
            $deletePerms = $conn->prepare('DELETE FROM dbo.tblRolePermissions WHERE RoleID = :id');
            $deletePerms->execute([':id' => $id]);
            $deleteUsers = $conn->prepare('DELETE FROM dbo.tblUserRoles WHERE RoleID = :id');
            $deleteUsers->execute([':id' => $id]);
            $deleteRole = $conn->prepare('DELETE FROM dbo.tblRoles WHERE RoleID = :id');
            $deleteRole->execute([':id' => $id]);
            $conn->commit();
            $this->flashSuccess('Role deleted.');
        } catch (\Throwable $e) {
            if ($conn instanceof \PDO && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->logHandledException('RolesController::delete failed', $e, ['roleId' => $id]);
            $this->flashError(__t('role_save_failed') . ': ' . $e->getMessage());
        }

        header('Location: index.php?route=roles/list');
        exit;
    }
}
