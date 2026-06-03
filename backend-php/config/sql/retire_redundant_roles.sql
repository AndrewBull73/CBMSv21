USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @ExecuteApply BIT = 0;

IF OBJECT_ID('tempdb..#RolesToRetire') IS NOT NULL DROP TABLE #RolesToRetire;
CREATE TABLE #RolesToRetire
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #RolesToRetire (RoleName) VALUES
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
    r.RoleName,
    r.Active
INTO #RoleTargets
FROM dbo.tblRoles r
INNER JOIN #RolesToRetire rr
    ON rr.RoleName = r.RoleName;

SELECT
    CASE WHEN @ExecuteApply = 1 THEN N'APPLY' ELSE N'PREVIEW_ONLY' END AS RunMode,
    COUNT(*) AS RolesFound,
    SUM(CASE WHEN Active = 1 OR Active IS NULL THEN 1 ELSE 0 END) AS ActiveRolesFound
FROM #RoleTargets;

SELECT
    rt.RoleName,
    COUNT(ur.UserID) AS AssignedUsers
FROM #RoleTargets rt
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = rt.RoleID
GROUP BY rt.RoleName
ORDER BY rt.RoleName;

SELECT
    u.UserID,
    u.Username,
    rt.RoleName
FROM #RoleTargets rt
JOIN dbo.tblUserRoles ur
    ON ur.RoleID = rt.RoleID
JOIN dbo.tblUsers u
    ON u.UserID = ur.UserID
ORDER BY rt.RoleName, u.Username;

IF @ExecuteApply = 1
BEGIN
    DELETE ur
    FROM dbo.tblUserRoles ur
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = ur.RoleID;

    UPDATE r
    SET Active = 0
    FROM dbo.tblRoles r
    INNER JOIN #RoleTargets rt
        ON rt.RoleID = r.RoleID;
END;

SELECT
    r.RoleName,
    r.Active,
    COUNT(ur.UserID) AS AssignedUsers
FROM dbo.tblRoles r
INNER JOIN #RolesToRetire rr
    ON rr.RoleName = r.RoleName
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = r.RoleID
GROUP BY r.RoleName, r.Active
ORDER BY r.RoleName;
GO
