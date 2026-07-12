SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRoles', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NULL
BEGIN
    RAISERROR('RBAC tables were not found. Run the core role/permission setup before seed_intelligence_platform_rbac_permissions.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID('tempdb..#IntelPermissions') IS NOT NULL DROP TABLE #IntelPermissions;
CREATE TABLE #IntelPermissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NOT NULL
);

INSERT INTO #IntelPermissions (PermissionCode, [Description])
VALUES
    (N'INTEL_VIEW', N'View CBMS Intelligence Engine dashboards and results'),
    (N'INTEL_ADMIN', N'Administer CBMS Intelligence Engine configuration'),
    (N'INTEL_FORECAST_VIEW', N'View intelligence forecasts'),
    (N'INTEL_FORECAST_CREATE', N'Create intelligence forecasts'),
    (N'INTEL_SCENARIO_CREATE', N'Create intelligence scenarios'),
    (N'INTEL_SCENARIO_APPROVE', N'Approve intelligence scenarios'),
    (N'ML_VIEW', N'View machine learning model register and predictions'),
    (N'ML_ADMIN', N'Administer machine learning models'),
    (N'ML_TRAIN', N'Train machine learning models'),
    (N'ML_APPROVE', N'Approve machine learning models'),
    (N'AI_VIEW', N'View CBMS AI platform settings and outputs'),
    (N'AI_ADMIN', N'Administer CBMS AI platform settings'),
    (N'AI_USE_EXTERNAL', N'Allow use of external AI providers where permitted'),
    (N'AI_VIEW_AUDIT', N'View AI and intelligence audit logs');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #IntelPermissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #IntelPermissions p
    ON p.PermissionCode = existing.PermissionCode;
GO

IF OBJECT_ID('tempdb..#IntelRoles') IS NOT NULL DROP TABLE #IntelRoles;
CREATE TABLE #IntelRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #IntelRoles (RoleName)
VALUES
    (N'Intelligence Viewer'),
    (N'Intelligence Analyst'),
    (N'Intelligence Administrator'),
    (N'Machine Learning Administrator'),
    (N'AI Platform Administrator');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #IntelRoles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #IntelRoles r
    ON r.RoleName = existing.RoleName;
GO

IF OBJECT_ID('tempdb..#IntelRolePerm') IS NOT NULL DROP TABLE #IntelRolePerm;
CREATE TABLE #IntelRolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #IntelRolePerm (RoleName, PermissionCode)
VALUES
    (N'Intelligence Viewer', N'INTEL_VIEW'),
    (N'Intelligence Viewer', N'INTEL_FORECAST_VIEW'),
    (N'Intelligence Analyst', N'INTEL_VIEW'),
    (N'Intelligence Analyst', N'INTEL_FORECAST_VIEW'),
    (N'Intelligence Analyst', N'INTEL_FORECAST_CREATE'),
    (N'Intelligence Analyst', N'INTEL_SCENARIO_CREATE'),
    (N'Intelligence Administrator', N'INTEL_VIEW'),
    (N'Intelligence Administrator', N'INTEL_ADMIN'),
    (N'Intelligence Administrator', N'INTEL_FORECAST_VIEW'),
    (N'Intelligence Administrator', N'INTEL_FORECAST_CREATE'),
    (N'Intelligence Administrator', N'INTEL_SCENARIO_CREATE'),
    (N'Intelligence Administrator', N'INTEL_SCENARIO_APPROVE'),
    (N'Intelligence Administrator', N'AI_VIEW_AUDIT'),
    (N'Machine Learning Administrator', N'ML_VIEW'),
    (N'Machine Learning Administrator', N'ML_ADMIN'),
    (N'Machine Learning Administrator', N'ML_TRAIN'),
    (N'Machine Learning Administrator', N'ML_APPROVE'),
    (N'AI Platform Administrator', N'AI_VIEW'),
    (N'AI Platform Administrator', N'AI_ADMIN'),
    (N'AI Platform Administrator', N'AI_USE_EXTERNAL'),
    (N'AI Platform Administrator', N'AI_VIEW_AUDIT'),
    (N'Super Admin', N'INTEL_VIEW'),
    (N'Super Admin', N'INTEL_ADMIN'),
    (N'Super Admin', N'INTEL_FORECAST_VIEW'),
    (N'Super Admin', N'INTEL_FORECAST_CREATE'),
    (N'Super Admin', N'INTEL_SCENARIO_CREATE'),
    (N'Super Admin', N'INTEL_SCENARIO_APPROVE'),
    (N'Super Admin', N'ML_VIEW'),
    (N'Super Admin', N'ML_ADMIN'),
    (N'Super Admin', N'ML_TRAIN'),
    (N'Super Admin', N'ML_APPROVE'),
    (N'Super Admin', N'AI_VIEW'),
    (N'Super Admin', N'AI_ADMIN'),
    (N'Super Admin', N'AI_USE_EXTERNAL'),
    (N'Super Admin', N'AI_VIEW_AUDIT'),
    (N'System Administrator', N'INTEL_VIEW'),
    (N'System Administrator', N'INTEL_ADMIN'),
    (N'System Administrator', N'ML_VIEW'),
    (N'System Administrator', N'ML_ADMIN'),
    (N'System Administrator', N'AI_VIEW'),
    (N'System Administrator', N'AI_ADMIN'),
    (N'System Administrator', N'AI_VIEW_AUDIT');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
FROM #IntelRolePerm rp
INNER JOIN dbo.tblRoles r
    ON r.RoleName = rp.RoleName
INNER JOIN dbo.tblPermissions p
    ON p.PermissionCode = rp.PermissionCode
LEFT JOIN dbo.tblRolePermissions existing
    ON existing.RoleID = r.RoleID
   AND existing.PermissionID = p.PermissionID
WHERE existing.RoleID IS NULL;
GO
