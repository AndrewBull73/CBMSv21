USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @TargetUserID INT = 2;
DECLARE @ExecuteApply BIT = 0;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblUsers
    WHERE UserID = @TargetUserID
)
BEGIN
    RAISERROR('Target UserID was not found in dbo.tblUsers.', 16, 1);
    RETURN;
END;

IF OBJECT_ID('tempdb..#RolesToGrant') IS NOT NULL DROP TABLE #RolesToGrant;

SELECT
    r.RoleID,
    r.RoleName
INTO #RolesToGrant
FROM dbo.tblRoles r
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = r.RoleID
   AND ur.UserID = @TargetUserID
WHERE ur.RoleID IS NULL
  AND (r.Active = 1 OR r.Active IS NULL);

SELECT
    CASE WHEN @ExecuteApply = 1 THEN N'APPLY' ELSE N'PREVIEW_ONLY' END AS RunMode,
    @TargetUserID AS TargetUserID,
    COUNT(*) AS RolesToGrant
FROM #RolesToGrant;

SELECT
    RoleID,
    RoleName
FROM #RolesToGrant
ORDER BY RoleName;

IF @ExecuteApply = 1
BEGIN
    INSERT INTO dbo.tblUserRoles (UserID, RoleID)
    SELECT
        @TargetUserID,
        rtg.RoleID
    FROM #RolesToGrant rtg;
END;

SELECT
    u.UserID,
    u.Username,
    r.RoleName
FROM dbo.tblUsers u
JOIN dbo.tblUserRoles ur
    ON ur.UserID = u.UserID
JOIN dbo.tblRoles r
    ON r.RoleID = ur.RoleID
WHERE u.UserID = @TargetUserID
ORDER BY r.RoleName;
GO
