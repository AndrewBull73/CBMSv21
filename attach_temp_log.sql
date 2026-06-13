USE [master]
GO

PRINT 'Attempting attach with log file in temp directory...'

BEGIN TRY
    CREATE DATABASE [CBMSv2_INITTEST] ON
        (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf'),
        (FILENAME = 'C:\\temp\\CBMSv2_INITTEST_log.ldf')
    FOR ATTACH
    
    PRINT 'SUCCESS!'
END TRY
BEGIN CATCH
    PRINT 'ERROR: ' + ERROR_MESSAGE()
    PRINT 'Retrying with ATTACH_REBUILD_LOG and temp log location...'
    
    BEGIN TRY
        CREATE DATABASE [CBMSv2_INITTEST] ON
            (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf')
        FOR ATTACH_REBUILD_LOG
        
        PRINT 'SUCCESS with rebuild log in temp!'
    END TRY
    BEGIN CATCH
        PRINT 'ERROR: ' + ERROR_MESSAGE()
    END CATCH
END CATCH
GO

SELECT name, state_desc FROM sys.databases WHERE name = 'CBMSv2_INITTEST'
GO
