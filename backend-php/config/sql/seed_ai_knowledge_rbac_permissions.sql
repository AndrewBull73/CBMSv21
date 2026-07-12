SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRoles', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NULL
BEGIN
    RAISERROR('RBAC tables were not found. Run the core role/permission setup before seed_ai_knowledge_rbac_permissions.sql.', 16, 1);
    RETURN;
END;
GO

IF EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'AI_DATASET_ANALYZE')
   AND NOT EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'ANALYSIS_DATASET_ANALYZE')
BEGIN
    UPDATE dbo.tblPermissions
    SET PermissionCode = N'ANALYSIS_DATASET_ANALYZE'
    WHERE PermissionCode = N'AI_DATASET_ANALYZE';
END;

IF EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'AI_DATASET_ADMIN')
   AND NOT EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'ANALYSIS_DATASET_ADMIN')
BEGIN
    UPDATE dbo.tblPermissions
    SET PermissionCode = N'ANALYSIS_DATASET_ADMIN'
    WHERE PermissionCode = N'AI_DATASET_ADMIN';
END;

IF EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'AI_DATASET_VIEW_LOGS')
   AND NOT EXISTS (SELECT 1 FROM dbo.tblPermissions WHERE PermissionCode = N'ANALYSIS_DATASET_VIEW_LOGS')
BEGIN
    UPDATE dbo.tblPermissions
    SET PermissionCode = N'ANALYSIS_DATASET_VIEW_LOGS'
    WHERE PermissionCode = N'AI_DATASET_VIEW_LOGS';
END;

IF EXISTS (SELECT 1 FROM dbo.tblRoles WHERE RoleName = N'AI Dataset Executive Analyst')
   AND NOT EXISTS (SELECT 1 FROM dbo.tblRoles WHERE RoleName = N'Analysis Dataset Executive Analyst')
BEGIN
    UPDATE dbo.tblRoles
    SET RoleName = N'Analysis Dataset Executive Analyst'
    WHERE RoleName = N'AI Dataset Executive Analyst';
END;

IF EXISTS (SELECT 1 FROM dbo.tblRoles WHERE RoleName = N'AI Dataset Administrator')
   AND NOT EXISTS (SELECT 1 FROM dbo.tblRoles WHERE RoleName = N'Analysis Dataset Administrator')
BEGIN
    UPDATE dbo.tblRoles
    SET RoleName = N'Analysis Dataset Administrator'
    WHERE RoleName = N'AI Dataset Administrator';
END;
GO

IF OBJECT_ID('tempdb..#AIHelpPermissions') IS NOT NULL DROP TABLE #AIHelpPermissions;
CREATE TABLE #AIHelpPermissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NOT NULL
);

INSERT INTO #AIHelpPermissions (PermissionCode, [Description])
VALUES
    (N'AI_HELP_USE', N'Ask questions using the CBMS AI Knowledge Assistant'),
    (N'AI_HELP_ADMIN', N'Administer the AI knowledge assistant and document register'),
    (N'AI_HELP_UPLOAD', N'Upload or index AI knowledge documents'),
    (N'AI_HELP_VIEW_LOGS', N'View AI assistant question and response logs'),
    (N'AI_HELP_DEVELOPER', N'Access developer-scoped AI knowledge documents'),
    (N'ANALYSIS_DATASET_ANALYZE', N'Run approved analysis dataset questions and summaries'),
    (N'ANALYSIS_DATASET_ADMIN', N'Administer analysis dataset sources and metadata'),
    (N'ANALYSIS_DATASET_VIEW_LOGS', N'View analysis dataset audit logs');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #AIHelpPermissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #AIHelpPermissions p
    ON p.PermissionCode = existing.PermissionCode;
GO

UPDATE dbo.tblPermissions
SET Active = 0,
    [Description] = CASE
        WHEN [Description] LIKE N'%retired%' THEN [Description]
        ELSE CONCAT([Description], N' (retired; use ANALYSIS_DATASET_* permission codes)')
    END
WHERE PermissionCode IN (N'AI_DATASET_ANALYZE', N'AI_DATASET_ADMIN', N'AI_DATASET_VIEW_LOGS');
GO

IF OBJECT_ID('tempdb..#AIHelpRoles') IS NOT NULL DROP TABLE #AIHelpRoles;
CREATE TABLE #AIHelpRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #AIHelpRoles (RoleName)
VALUES
    (N'AI Help User'),
    (N'AI Help Administrator'),
    (N'Analysis Dataset Executive Analyst'),
    (N'Analysis Dataset Administrator');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #AIHelpRoles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #AIHelpRoles r
    ON r.RoleName = existing.RoleName;
GO

UPDATE dbo.tblRoles
SET Active = 0,
    DateUpdated = GETDATE()
WHERE RoleName IN (N'AI Dataset Executive Analyst', N'AI Dataset Administrator');
GO

IF OBJECT_ID('tempdb..#AIHelpRolePerm') IS NOT NULL DROP TABLE #AIHelpRolePerm;
CREATE TABLE #AIHelpRolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #AIHelpRolePerm (RoleName, PermissionCode)
VALUES
    (N'AI Help User', N'AI_HELP_USE'),
    (N'AI Help Administrator', N'AI_HELP_USE'),
    (N'AI Help Administrator', N'AI_HELP_ADMIN'),
    (N'AI Help Administrator', N'AI_HELP_UPLOAD'),
    (N'AI Help Administrator', N'AI_HELP_VIEW_LOGS'),
    (N'Analysis Dataset Executive Analyst', N'ANALYSIS_DATASET_ANALYZE'),
    (N'Analysis Dataset Administrator', N'ANALYSIS_DATASET_ANALYZE'),
    (N'Analysis Dataset Administrator', N'ANALYSIS_DATASET_ADMIN'),
    (N'Analysis Dataset Administrator', N'ANALYSIS_DATASET_VIEW_LOGS'),
    (N'Super Admin', N'AI_HELP_USE'),
    (N'Super Admin', N'AI_HELP_ADMIN'),
    (N'Super Admin', N'AI_HELP_UPLOAD'),
    (N'Super Admin', N'AI_HELP_VIEW_LOGS'),
    (N'Super Admin', N'AI_HELP_DEVELOPER'),
    (N'Super Admin', N'ANALYSIS_DATASET_ANALYZE'),
    (N'Super Admin', N'ANALYSIS_DATASET_ADMIN'),
    (N'Super Admin', N'ANALYSIS_DATASET_VIEW_LOGS'),
    (N'System Administrator', N'AI_HELP_USE'),
    (N'System Administrator', N'AI_HELP_ADMIN'),
    (N'System Administrator', N'AI_HELP_UPLOAD'),
    (N'System Administrator', N'AI_HELP_VIEW_LOGS'),
    (N'System Administrator', N'ANALYSIS_DATASET_ANALYZE'),
    (N'System Administrator', N'ANALYSIS_DATASET_ADMIN'),
    (N'System Administrator', N'ANALYSIS_DATASET_VIEW_LOGS');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
FROM #AIHelpRolePerm rp
INNER JOIN dbo.tblRoles r
    ON r.RoleName = rp.RoleName
INNER JOIN dbo.tblPermissions p
    ON p.PermissionCode = rp.PermissionCode
LEFT JOIN dbo.tblRolePermissions existing
    ON existing.RoleID = r.RoleID
   AND existing.PermissionID = p.PermissionID
WHERE existing.RoleID IS NULL;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasets', N'U') IS NOT NULL
BEGIN
    UPDATE dbo.tblAnalysisDatasets
    SET AllowedPermissionCodes = REPLACE(REPLACE(REPLACE(
            AllowedPermissionCodes,
            N'AI_DATASET_VIEW_LOGS', N'ANALYSIS_DATASET_VIEW_LOGS'),
            N'AI_DATASET_ANALYZE', N'ANALYSIS_DATASET_ANALYZE'),
            N'AI_DATASET_ADMIN', N'ANALYSIS_DATASET_ADMIN')
    WHERE AllowedPermissionCodes LIKE N'%AI_DATASET_%';
END;
GO
