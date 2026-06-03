USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @ExecuteDelete BIT = 0;

IF OBJECT_ID('tempdb..#LegacyToFunctional') IS NOT NULL DROP TABLE #LegacyToFunctional;
CREATE TABLE #LegacyToFunctional
(
    LegacyRoleName NVARCHAR(255) NOT NULL,
    FunctionalRoleName NVARCHAR(255) NOT NULL
);

INSERT INTO #LegacyToFunctional (LegacyRoleName, FunctionalRoleName) VALUES
(N'Admin', N'System Administrator'),
(N'SysAdmin', N'System Administrator'),
(N'Config', N'Configuration Administrator'),
(N'Strategy', N'Strategic Framework User'),
(N'Strategy', N'Budget Submission User'),
(N'Reports', N'Reporting User'),
(N'Analytics', N'Analytics User'),
(N'Analytics', N'Dashboard User'),
(N'Estimates', N'Budget Submission User'),
(N'FiscalFramework', N'Strategic Framework User'),
(N'FiscalFramework', N'Reporting User'),
(N'Manager', N'Reporting User'),
(N'Manager', N'Strategic Framework Approver'),
(N'DataObjects', N'System Administrator'),
(N'Workflow', N'System Administrator'),
(N'Workflow', N'Strategic Framework Reviewer'),
(N'Execution', N'Budget Execution User');

IF OBJECT_ID('tempdb..#LegacyAssignments') IS NOT NULL DROP TABLE #LegacyAssignments;
CREATE TABLE #LegacyAssignments
(
    UserID INT NOT NULL,
    UserLabel NVARCHAR(255) NOT NULL,
    LegacyRoleName NVARCHAR(255) NOT NULL,
    LegacyRoleID INT NOT NULL,
    CanRemove BIT NOT NULL
);

;WITH CurrentRoles AS
(
    SELECT
        u.UserID,
        COALESCE(NULLIF(LTRIM(RTRIM(u.Username)), N''), NULLIF(LTRIM(RTRIM(u.Email)), N''), CAST(u.UserID AS NVARCHAR(50))) AS UserLabel,
        r.RoleID,
        r.RoleName
    FROM dbo.tblUsers u
    INNER JOIN dbo.tblUserRoles ur
        ON ur.UserID = u.UserID
    INNER JOIN dbo.tblRoles r
        ON r.RoleID = ur.RoleID
),
LegacyRows AS
(
    SELECT
        cr.UserID,
        cr.UserLabel,
        cr.RoleID AS LegacyRoleID,
        cr.RoleName AS LegacyRoleName
    FROM CurrentRoles cr
    INNER JOIN (SELECT DISTINCT LegacyRoleName FROM #LegacyToFunctional) lr
        ON lr.LegacyRoleName = cr.RoleName
)
INSERT INTO #LegacyAssignments (UserID, UserLabel, LegacyRoleName, LegacyRoleID, CanRemove)
SELECT
    lr.UserID,
    lr.UserLabel,
    lr.LegacyRoleName,
    lr.LegacyRoleID,
    CASE
        WHEN NOT EXISTS
        (
            SELECT 1
            FROM #LegacyToFunctional map
            WHERE map.LegacyRoleName = lr.LegacyRoleName
              AND NOT EXISTS
              (
                  SELECT 1
                  FROM CurrentRoles fr
                  WHERE fr.UserID = lr.UserID
                    AND fr.RoleName = map.FunctionalRoleName
              )
        )
        THEN 1 ELSE 0
    END AS CanRemove
FROM LegacyRows lr;

SELECT
    UserID,
    UserLabel,
    LegacyRoleName,
    CASE WHEN CanRemove = 1 THEN N'REMOVE_READY' ELSE N'KEEP_FOR_REVIEW' END AS CleanupStatus
FROM #LegacyAssignments
ORDER BY
    CASE WHEN CanRemove = 1 THEN 0 ELSE 1 END,
    UserLabel,
    LegacyRoleName;

IF @ExecuteDelete = 1
BEGIN
    DELETE ur
    FROM dbo.tblUserRoles ur
    INNER JOIN #LegacyAssignments la
        ON la.UserID = ur.UserID
       AND la.LegacyRoleID = ur.RoleID
    WHERE la.CanRemove = 1;
END;

SELECT
    CASE WHEN @ExecuteDelete = 1 THEN N'DELETE_APPLIED' ELSE N'PREVIEW_ONLY' END AS RunMode,
    SUM(CASE WHEN CanRemove = 1 THEN 1 ELSE 0 END) AS RemoveReadyAssignments,
    SUM(CASE WHEN CanRemove = 0 THEN 1 ELSE 0 END) AS KeptForReviewAssignments
FROM #LegacyAssignments;
GO
