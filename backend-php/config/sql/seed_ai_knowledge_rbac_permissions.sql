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
    (N'AI_DATASET_ANALYZE', N'Run approved AI dataset analysis'),
    (N'AI_DATASET_ADMIN', N'Administer AI dataset analysis sources and metadata'),
    (N'AI_DATASET_VIEW_LOGS', N'View AI dataset analysis audit logs');

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

IF OBJECT_ID('tempdb..#AIHelpRoles') IS NOT NULL DROP TABLE #AIHelpRoles;
CREATE TABLE #AIHelpRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #AIHelpRoles (RoleName)
VALUES
    (N'AI Help User'),
    (N'AI Help Administrator'),
    (N'AI Dataset Executive Analyst'),
    (N'AI Dataset Administrator');

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
    (N'AI Dataset Executive Analyst', N'AI_DATASET_ANALYZE'),
    (N'AI Dataset Administrator', N'AI_DATASET_ANALYZE'),
    (N'AI Dataset Administrator', N'AI_DATASET_ADMIN'),
    (N'AI Dataset Administrator', N'AI_DATASET_VIEW_LOGS'),
    (N'Super Admin', N'AI_HELP_USE'),
    (N'Super Admin', N'AI_HELP_ADMIN'),
    (N'Super Admin', N'AI_HELP_UPLOAD'),
    (N'Super Admin', N'AI_HELP_VIEW_LOGS'),
    (N'Super Admin', N'AI_HELP_DEVELOPER'),
    (N'Super Admin', N'AI_DATASET_ANALYZE'),
    (N'Super Admin', N'AI_DATASET_ADMIN'),
    (N'Super Admin', N'AI_DATASET_VIEW_LOGS'),
    (N'System Administrator', N'AI_HELP_USE'),
    (N'System Administrator', N'AI_HELP_ADMIN'),
    (N'System Administrator', N'AI_HELP_UPLOAD'),
    (N'System Administrator', N'AI_HELP_VIEW_LOGS'),
    (N'System Administrator', N'AI_DATASET_ANALYZE'),
    (N'System Administrator', N'AI_DATASET_ADMIN'),
    (N'System Administrator', N'AI_DATASET_VIEW_LOGS');

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
