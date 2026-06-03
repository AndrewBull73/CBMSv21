USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @ExecuteApply BIT = 0;

IF OBJECT_ID('tempdb..#RolesToDelete') IS NOT NULL DROP TABLE #RolesToDelete;
CREATE TABLE #RolesToDelete
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #RolesToDelete (RoleName) VALUES
(N'Admin'),
(N'Analytics'),
(N'Config'),
(N'DataObjects'),
(N'Estimates'),
(N'Execution'),
(N'FiscalFramework'),
(N'Manager'),
(N'Reports'),
(N'Strategy'),
(N'Super Admin'),
(N'SysAdmin'),
(N'Test Role'),
(N'User'),
(N'Workflow');

IF OBJECT_ID('tempdb..#RoleTargets') IS NOT NULL DROP TABLE #RoleTargets;
SELECT
    r.RoleID,
    r.RoleName
INTO #RoleTargets
FROM dbo.tblRoles r
INNER JOIN #RolesToDelete d
    ON d.RoleName = r.RoleName;

SELECT
    CASE WHEN @ExecuteApply = 1 THEN N'APPLY' ELSE N'PREVIEW_ONLY' END AS RunMode,
    COUNT(*) AS RolesFound
FROM #RoleTargets;

SELECT
    rt.RoleName,
    (SELECT COUNT(*) FROM dbo.tblRolePermissions rp WHERE rp.RoleID = rt.RoleID) AS PermissionLinks,
    (SELECT COUNT(*) FROM dbo.tblUserRoles ur WHERE ur.RoleID = rt.RoleID) AS UserRoleLinks,
    (SELECT COUNT(*) FROM dbo.tblUsers u WHERE u.RoleID = rt.RoleID) AS LegacyUserColumnLinks
FROM #RoleTargets rt
ORDER BY rt.RoleName;

IF @ExecuteApply = 1
BEGIN
    DELETE rp
    FROM dbo.tblRolePermissions rp
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = rp.RoleID;

    DELETE ur
    FROM dbo.tblUserRoles ur
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = ur.RoleID;

    UPDATE u
    SET RoleID = NULL
    FROM dbo.tblUsers u
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = u.RoleID;

    DELETE r
    FROM dbo.tblRoles r
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = r.RoleID;
END;

SELECT
    d.RoleName,
    CASE WHEN r.RoleID IS NULL THEN N'DELETED' ELSE N'REMAINING' END AS DeleteStatus
FROM #RolesToDelete d
LEFT JOIN dbo.tblRoles r
    ON r.RoleName = d.RoleName
ORDER BY d.RoleName;
GO
