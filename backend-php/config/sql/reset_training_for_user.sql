SET NOCOUNT ON;
GO

/*
    Reset training data for ONE user only.

    Edit one of these two values before running:
      DECLARE @UserID INT = 2;
      DECLARE @Username NVARCHAR(100) = N'InitConfig';

    This clears the selected user's:
      - training assignments
      - training scenario progress
      - training attempts
      - training session participation
      - certification attempts and answers, if certification tables exist

    It does not delete scenarios, paths, certifications, questions, users, or roles.
*/



DECLARE @UserID INT = 2;
DECLARE @Username NVARCHAR(100) = N'InitConfig';

IF OBJECT_ID(N'dbo.tblUsers', N'U') IS NULL
BEGIN
    RAISERROR('dbo.tblUsers was not found. Check that you are running this in the CBMS database.', 16, 1);
    RETURN;
END;

IF @UserID IS NULL AND NULLIF(LTRIM(RTRIM(@Username)), N'') IS NOT NULL
BEGIN
    DECLARE @ResolveUserSql NVARCHAR(MAX) = N'
        SELECT @ResolvedUserID = UserID
        FROM dbo.tblUsers
        WHERE Username = @LookupUsername;
    ';

    EXEC sys.sp_executesql
        @ResolveUserSql,
        N'@LookupUsername NVARCHAR(100), @ResolvedUserID INT OUTPUT',
        @LookupUsername = @Username,
        @ResolvedUserID = @UserID OUTPUT;
END;

IF @UserID IS NULL
BEGIN
    RAISERROR('Set @UserID or @Username before running reset_training_for_user.sql.', 16, 1);
    RETURN;
END;

DECLARE @UserExists BIT = 0;
DECLARE @UserExistsSql NVARCHAR(MAX) = N'
    IF EXISTS (SELECT 1 FROM dbo.tblUsers WHERE UserID = @LookupUserID)
    BEGIN
        SET @ExistsFlag = 1;
    END;
';

EXEC sys.sp_executesql
    @UserExistsSql,
    N'@LookupUserID INT, @ExistsFlag BIT OUTPUT',
    @LookupUserID = @UserID,
    @ExistsFlag = @UserExists OUTPUT;

IF @UserExists = 0
BEGIN
    RAISERROR('The selected @UserID does not exist in dbo.tblUsers.', 16, 1);
    RETURN;
END;

BEGIN TRANSACTION;

BEGIN TRY
    IF OBJECT_ID(N'dbo.tblTrainingCertificationAnswers', N'U') IS NOT NULL
       AND OBJECT_ID(N'dbo.tblTrainingCertificationAttempts', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'
                DELETE ans
                FROM dbo.tblTrainingCertificationAnswers AS ans
                INNER JOIN dbo.tblTrainingCertificationAttempts AS att
                    ON att.TrainingCertificationAttemptID = ans.TrainingCertificationAttemptID
                WHERE att.UserID = @ResetUserID;
            ',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    IF OBJECT_ID(N'dbo.tblTrainingCertificationAttempts', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'DELETE FROM dbo.tblTrainingCertificationAttempts WHERE UserID = @ResetUserID;',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    IF OBJECT_ID(N'dbo.tblTrainingSessionUsers', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'DELETE FROM dbo.tblTrainingSessionUsers WHERE UserID = @ResetUserID;',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    IF OBJECT_ID(N'dbo.tblTrainingAssignments', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'DELETE FROM dbo.tblTrainingAssignments WHERE UserID = @ResetUserID;',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    IF OBJECT_ID(N'dbo.tblTrainingAttempts', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'DELETE FROM dbo.tblTrainingAttempts WHERE UserID = @ResetUserID;',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    IF OBJECT_ID(N'dbo.tblTrainingProgress', N'U') IS NOT NULL
    BEGIN
        EXEC sys.sp_executesql
            N'DELETE FROM dbo.tblTrainingProgress WHERE UserID = @ResetUserID;',
            N'@ResetUserID INT',
            @ResetUserID = @UserID;
    END;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
    BEGIN
        ROLLBACK TRANSACTION;
    END;

    DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
    RAISERROR(@ErrorMessage, 16, 1);
    RETURN;
END CATCH;

DECLARE @SummarySql NVARCHAR(MAX) = N'
    SELECT
        UserID AS ResetUserID,
        Username,
        N''Training reset complete'' AS ResetStatus
    FROM dbo.tblUsers
    WHERE UserID = @ResetUserID;
';

IF COL_LENGTH(N'dbo.tblUsers', N'DisplayName') IS NOT NULL
BEGIN
    SET @SummarySql = N'
        SELECT
            UserID AS ResetUserID,
            Username,
            DisplayName,
            N''Training reset complete'' AS ResetStatus
        FROM dbo.tblUsers
        WHERE UserID = @ResetUserID;
    ';
END;

EXEC sys.sp_executesql
    @SummarySql,
    N'@ResetUserID INT',
    @ResetUserID = @UserID;
GO
