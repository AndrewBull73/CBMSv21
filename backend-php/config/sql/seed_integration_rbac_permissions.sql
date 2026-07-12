SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRoles', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NULL
BEGIN
    RAISERROR('RBAC tables were not found. Run the core role/permission setup before seed_integration_rbac_permissions.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID('tempdb..#IntegrationPermissions') IS NOT NULL DROP TABLE #IntegrationPermissions;
CREATE TABLE #IntegrationPermissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NOT NULL
);

INSERT INTO #IntegrationPermissions (PermissionCode, [Description])
VALUES
    (N'INTEGRATION_VIEW', N'View integration dashboard, systems, interfaces, and run history'),
    (N'INTEGRATION_RUN', N'Run integration test exports and review integration run output'),
    (N'INTEGRATION_ADMIN', N'Configure integration systems, interfaces, mappings, readiness, and credential references');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #IntegrationPermissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET
    existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #IntegrationPermissions p
    ON p.PermissionCode = existing.PermissionCode;
GO

IF OBJECT_ID('tempdb..#IntegrationRoles') IS NOT NULL DROP TABLE #IntegrationRoles;
CREATE TABLE #IntegrationRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #IntegrationRoles (RoleName)
VALUES
    (N'Integration Viewer'),
    (N'Integration Operator'),
    (N'Integration Administration');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #IntegrationRoles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET
    existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #IntegrationRoles r
    ON r.RoleName = existing.RoleName;
GO

IF OBJECT_ID('tempdb..#IntegrationRolePerm') IS NOT NULL DROP TABLE #IntegrationRolePerm;
CREATE TABLE #IntegrationRolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #IntegrationRolePerm (RoleName, PermissionCode)
VALUES
    (N'Integration Viewer', N'INTEGRATION_VIEW'),
    (N'Integration Operator', N'INTEGRATION_VIEW'),
    (N'Integration Operator', N'INTEGRATION_RUN'),
    (N'Integration Administration', N'INTEGRATION_VIEW'),
    (N'Integration Administration', N'INTEGRATION_RUN'),
    (N'Integration Administration', N'INTEGRATION_ADMIN'),
    (N'Super Admin', N'INTEGRATION_VIEW'),
    (N'Super Admin', N'INTEGRATION_RUN'),
    (N'Super Admin', N'INTEGRATION_ADMIN'),
    (N'System Administrator', N'INTEGRATION_VIEW'),
    (N'System Administrator', N'INTEGRATION_RUN'),
    (N'System Administrator', N'INTEGRATION_ADMIN');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
FROM #IntegrationRolePerm rp
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
WHERE p.PermissionCode IN (N'INTEGRATION_VIEW', N'INTEGRATION_RUN', N'INTEGRATION_ADMIN')
ORDER BY p.PermissionCode;
GO
