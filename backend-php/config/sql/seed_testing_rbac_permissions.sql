SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRoles', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NULL
BEGIN
    RAISERROR('RBAC tables were not found. Run the core role/permission setup before seed_testing_rbac_permissions.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID('tempdb..#TestingPermissions') IS NOT NULL DROP TABLE #TestingPermissions;
CREATE TABLE #TestingPermissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NOT NULL
);

INSERT INTO #TestingPermissions (PermissionCode, [Description])
VALUES
    (N'TEST_SCRIPT_RUN', N'Browse and run testing scripts, capture evidence, and view own testing results'),
    (N'TEST_SCRIPT_RESULTS', N'Review testing script result history across users and modules'),
    (N'TEST_SCRIPT_ADMIN', N'Configure testing script catalogue entries and testing script metadata');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #TestingPermissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET
    existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #TestingPermissions p
    ON p.PermissionCode = existing.PermissionCode;
GO

IF OBJECT_ID('tempdb..#TestingRoles') IS NOT NULL DROP TABLE #TestingRoles;
CREATE TABLE #TestingRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #TestingRoles (RoleName)
VALUES
    (N'Testing User'),
    (N'Testing Results'),
    (N'Testing Administration');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #TestingRoles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET
    existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #TestingRoles r
    ON r.RoleName = existing.RoleName;
GO

IF OBJECT_ID('tempdb..#TestingRolePerm') IS NOT NULL DROP TABLE #TestingRolePerm;
CREATE TABLE #TestingRolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #TestingRolePerm (RoleName, PermissionCode)
VALUES
    (N'Testing User', N'TEST_SCRIPT_RUN'),
    (N'Testing Results', N'TEST_SCRIPT_RUN'),
    (N'Testing Results', N'TEST_SCRIPT_RESULTS'),
    (N'Testing Administration', N'TEST_SCRIPT_RUN'),
    (N'Testing Administration', N'TEST_SCRIPT_RESULTS'),
    (N'Testing Administration', N'TEST_SCRIPT_ADMIN'),
    (N'Super Admin', N'TEST_SCRIPT_RUN'),
    (N'Super Admin', N'TEST_SCRIPT_RESULTS'),
    (N'Super Admin', N'TEST_SCRIPT_ADMIN'),
    (N'System Administrator', N'TEST_SCRIPT_RUN'),
    (N'System Administrator', N'TEST_SCRIPT_RESULTS'),
    (N'System Administrator', N'TEST_SCRIPT_ADMIN');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
FROM #TestingRolePerm rp
INNER JOIN dbo.tblRoles r
    ON r.RoleName = rp.RoleName
INNER JOIN dbo.tblPermissions p
    ON p.PermissionCode = rp.PermissionCode
LEFT JOIN dbo.tblRolePermissions existing
    ON existing.RoleID = r.RoleID
   AND existing.PermissionID = p.PermissionID
WHERE existing.RoleID IS NULL;
GO

SELECT
    p.PermissionCode,
    p.[Description],
    p.Active
FROM dbo.tblPermissions p
WHERE p.PermissionCode IN (N'TEST_SCRIPT_RUN', N'TEST_SCRIPT_RESULTS', N'TEST_SCRIPT_ADMIN')
ORDER BY p.PermissionCode;
GO
