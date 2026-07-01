SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRoles', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NULL
BEGIN
    RAISERROR('RBAC tables were not found. Run the core role/permission setup before seed_training_rbac_permissions.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID('tempdb..#TrainingPermissions') IS NOT NULL DROP TABLE #TrainingPermissions;
CREATE TABLE #TrainingPermissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NOT NULL
);

INSERT INTO #TrainingPermissions (PermissionCode, [Description])
VALUES
    (N'TRAINING_USER', N'View assigned training and complete training scenarios and certifications'),
    (N'TRAINING_ADMIN', N'Assign training, manage learner training operations, sessions, evidence, and results'),
    (N'TRAINING_CONFIG', N'Configure training scenarios, steps, paths, certifications, questions, and validation');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #TrainingPermissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET
    existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #TrainingPermissions p
    ON p.PermissionCode = existing.PermissionCode;
GO

IF OBJECT_ID('tempdb..#TrainingRoles') IS NOT NULL DROP TABLE #TrainingRoles;
CREATE TABLE #TrainingRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #TrainingRoles (RoleName)
VALUES
    (N'Training User'),
    (N'Training Administration'),
    (N'Training Configuration');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #TrainingRoles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET
    existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #TrainingRoles r
    ON r.RoleName = existing.RoleName;
GO

IF OBJECT_ID('tempdb..#TrainingRolePerm') IS NOT NULL DROP TABLE #TrainingRolePerm;
CREATE TABLE #TrainingRolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #TrainingRolePerm (RoleName, PermissionCode)
VALUES
    (N'Training User', N'TRAINING_USER'),
    (N'Training Administration', N'TRAINING_USER'),
    (N'Training Administration', N'TRAINING_ADMIN'),
    (N'Training Configuration', N'TRAINING_USER'),
    (N'Training Configuration', N'TRAINING_CONFIG'),
    (N'Super Admin', N'TRAINING_USER'),
    (N'Super Admin', N'TRAINING_ADMIN'),
    (N'Super Admin', N'TRAINING_CONFIG'),
    (N'System Administrator', N'TRAINING_USER'),
    (N'System Administrator', N'TRAINING_ADMIN'),
    (N'System Administrator', N'TRAINING_CONFIG');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
FROM #TrainingRolePerm rp
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
WHERE p.PermissionCode IN (N'TRAINING_USER', N'TRAINING_ADMIN', N'TRAINING_CONFIG')
ORDER BY p.PermissionCode;
GO
