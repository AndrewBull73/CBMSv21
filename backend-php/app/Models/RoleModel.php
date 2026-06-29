<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class RoleModel
{
    private PDO $conn;
    private string $lastError = '';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * List all roles in the system
     */
    public function listAll(): array
    {
        try {
            $sql = "SELECT RoleID, RoleName, Active, DateCreated, DateUpdated 
                    FROM dbo.tblRoles
                    ORDER BY RoleName ASC";
            $stmt = $this->conn->query($sql);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($roles)) {
                error_log('RoleModel::listAll returned empty result');
            }
            return $roles;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('RoleModel::listAll error: ' . $e->getMessage());
            return [];
        }
    }

    public function listPermissions(): array
    {
        try {
            $sql = "SELECT PermissionID, PermissionCode, [Description], Active
                    FROM dbo.tblPermissions
                    WHERE Active = 1 OR Active IS NULL
                    ORDER BY PermissionCode ASC";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('RoleModel::listPermissions error: ' . $e->getMessage());
            return [];
        }
    }

    public function permissionIdsForRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT PermissionID
                 FROM dbo.tblRolePermissions
                 WHERE RoleID = :roleId"
            );
            $stmt->execute([':roleId' => $roleId]);
            $ids = [];
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $ids[] = (int)($row['PermissionID'] ?? 0);
            }
            return array_values(array_filter(array_unique($ids), static fn(int $id): bool => $id > 0));
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('RoleModel::permissionIdsForRole error: ' . $e->getMessage());
            return [];
        }
    }

    public function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        if ($roleId <= 0) {
            throw new \InvalidArgumentException('Role ID is required.');
        }

        $permissionIds = array_values(array_filter(array_unique(array_map('intval', $permissionIds)), static fn(int $id): bool => $id > 0));

        $this->conn->beginTransaction();
        try {
            $delete = $this->conn->prepare('DELETE FROM dbo.tblRolePermissions WHERE RoleID = :roleId');
            $delete->execute([':roleId' => $roleId]);

            if ($permissionIds !== []) {
                $insert = $this->conn->prepare(
                    'INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
                     SELECT :roleId, :permissionId
                     WHERE EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionID = :permissionIdCheck)'
                );
                foreach ($permissionIds as $permissionId) {
                    $insert->execute([
                        ':roleId' => $roleId,
                        ':permissionId' => $permissionId,
                        ':permissionIdCheck' => $permissionId,
                    ]);
                }
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Find a role by ID
     */
    public function find(int $id): ?array
    {
        try {
            $sql = "SELECT RoleID, RoleName, Active, DateCreated, DateUpdated
                    FROM dbo.tblRoles
                    WHERE RoleID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }
}
