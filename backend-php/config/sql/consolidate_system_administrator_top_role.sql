USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @ExecuteApply BIT = 0;

DECLARE @SystemAdministratorRoleID INT =
(
    SELECT RoleID
    FROM dbo.tblRoles
    WHERE RoleName = N'System Administrator'
);

DECLARE @SuperAdminRoleID INT =
(
    SELECT RoleID
    FROM dbo.tblRoles
    WHERE RoleName = N'Super Admin'
);

IF @SystemAdministratorRoleID IS NULL
BEGIN
    RAISERROR('System Administrator role was not found.', 16, 1);
    RETURN;
END;

IF @SuperAdminRoleID IS NULL
BEGIN
    RAISERROR('Super Admin role was not found.', 16, 1);
    RETURN;
END;

IF OBJECT_ID('tempdb..#MissingPerms') IS NOT NULL DROP TABLE #MissingPerms;
SELECT
    super.PermissionID
INTO #MissingPerms
FROM dbo.tblRolePermissions super
LEFT JOIN dbo.tblRolePermissions sys
    ON sys.PermissionID = super.PermissionID
   AND sys.RoleID = @SystemAdministratorRoleID
WHERE super.RoleID = @SuperAdminRoleID
  AND sys.PermissionID IS NULL;

IF OBJECT_ID('tempdb..#UsersToPromote') IS NOT NULL DROP TABLE #UsersToPromote;
SELECT
    ur.UserID
INTO #UsersToPromote
FROM dbo.tblUserRoles ur
LEFT JOIN dbo.tblUserRoles existing
    ON existing.UserID = ur.UserID
   AND existing.RoleID = @SystemAdministratorRoleID
WHERE ur.RoleID = @SuperAdminRoleID
  AND existing.UserID IS NULL;

IF OBJECT_ID('tempdb..#UsersToRemoveSuper') IS NOT NULL DROP TABLE #UsersToRemoveSuper;
SELECT
    ur.UserID
INTO #UsersToRemoveSuper
FROM dbo.tblUserRoles ur
WHERE ur.RoleID = @SuperAdminRoleID;

SELECT
    CASE WHEN @ExecuteApply = 1 THEN N'APPLY' ELSE N'PREVIEW_ONLY' END AS RunMode,
    (SELECT COUNT(*) FROM #MissingPerms) AS MissingPermissionsToCopy,
    (SELECT COUNT(*) FROM #UsersToPromote) AS UsersToAssignSystemAdministrator,
    (SELECT COUNT(*) FROM #UsersToRemoveSuper) AS SuperAdminAssignmentsToRemove;

SELECT
    p.PermissionCode
FROM #MissingPerms mp
JOIN dbo.tblPermissions p
    ON p.PermissionID = mp.PermissionID
ORDER BY p.PermissionCode;

SELECT
    u.UserID,
    u.Username
FROM #UsersToPromote up
JOIN dbo.tblUsers u
    ON u.UserID = up.UserID
ORDER BY u.Username;

SELECT
    u.UserID,
    u.Username
FROM #UsersToRemoveSuper ur
JOIN dbo.tblUsers u
    ON u.UserID = ur.UserID
ORDER BY u.Username;

IF @ExecuteApply = 1
BEGIN
    INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
    SELECT
        @SystemAdministratorRoleID,
        mp.PermissionID
    FROM #MissingPerms mp;

    INSERT INTO dbo.tblUserRoles (UserID, RoleID)
    SELECT
        up.UserID,
        @SystemAdministratorRoleID
    FROM #UsersToPromote up;

    DELETE ur
    FROM dbo.tblUserRoles ur
    WHERE ur.RoleID = @SuperAdminRoleID;
END;

SELECT
    r.RoleName,
    COUNT(*) AS AssignedUsers
FROM dbo.tblUserRoles ur
JOIN dbo.tblRoles r
    ON r.RoleID = ur.RoleID
WHERE r.RoleName IN (N'Super Admin', N'System Administrator')
GROUP BY r.RoleName
ORDER BY r.RoleName;
GO
