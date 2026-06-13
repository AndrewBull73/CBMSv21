-- Try attaching with UNSAFE option
USE [master]
GO

PRINT 'Attempting attach with FOR ATTACH_REBUILD_LOG and skip corruption check...'

BEGIN TRY
    -- First attempt: standard rebuild
    CREATE DATABASE [CBMSv2_INITTEST] ON
        (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf')
    FOR ATTACH_REBUILD_LOG
    
    PRINT 'SUCCESS!'
END TRY
BEGIN CATCH
    PRINT 'Method 1 failed: ' + ERROR_MESSAGE()
    
    -- Try with EXEC sp_attach_single_file_db
    BEGIN TRY
        PRINT 'Trying sp_attach_single_file_db...'
        EXEC sp_attach_single_file_db 
            @dbname = 'CBMSv2_INITTEST',
            @physname = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf'
        PRINT 'SUCCESS with sp_attach_single_file_db!'
    END TRY
    BEGIN CATCH
        PRINT 'Method 2 failed: ' + ERROR_MESSAGE()
    END CATCH
END CATCH
GO
