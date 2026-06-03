SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Step 05
Assign every active seeded role to the preserved admin-capable login.

This keeps the rebuilt INITTEST environment usable immediately after the
role/permission model is reseeded.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'CBMSv2_INITTEST'
BEGIN
    THROW 51020, 'This admin-access seed script is restricted to CBMSv2_INITTEST.', 1;
END;

DECLARE @UserID INT;
DECLARE @Username NVARCHAR(50);

SELECT TOP (1)
    @UserID = u.UserID,
    @Username = u.Username
FROM dbo.tblUsers AS u
WHERE ISNULL(u.IsActive, 1) = 1
ORDER BY CASE WHEN LOWER(LTRIM(RTRIM(u.Username))) = N'admin' THEN 0 ELSE 1 END,
         u.UserID;

IF @UserID IS NULL
BEGIN
    THROW 51021, 'No active user is available for baseline admin access assignment.', 1;
END;

INSERT INTO dbo.tblUserRoles (UserID, RoleID, DateAssigned)
SELECT
    @UserID,
    r.RoleID,
    SYSDATETIME()
FROM dbo.tblRoles AS r
LEFT JOIN dbo.tblUserRoles AS existing
    ON existing.UserID = @UserID
   AND existing.RoleID = r.RoleID
WHERE r.Active = 1
  AND existing.UserRoleID IS NULL;

UPDATE dbo.tblUsers
SET RoleID = (
        SELECT TOP (1) r.RoleID
        FROM dbo.tblRoles AS r
        WHERE r.RoleName = N'Super Admin'
          AND r.Active = 1
        ORDER BY r.RoleID
    ),
    UpdatedBy = @UserID,
    UpdatedAt = GETDATE()
WHERE UserID = @UserID;

SELECT
    @UserID AS UserID,
    @Username AS Username,
    COUNT(*) AS ActiveRolesAssigned
FROM dbo.tblUserRoles
WHERE UserID = @UserID;

SELECT
    r.RoleName
FROM dbo.tblUserRoles AS ur
INNER JOIN dbo.tblRoles AS r
    ON r.RoleID = ur.RoleID
WHERE ur.UserID = @UserID
ORDER BY r.RoleName;
