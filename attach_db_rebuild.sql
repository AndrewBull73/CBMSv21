-- Attach CBMSv2_INITTEST database and rebuild log
USE [master]
GO

PRINT 'Attempting to attach CBMSv2_INITTEST with LOG REBUILD...'

BEGIN TRY
    CREATE DATABASE [CBMSv2_INITTEST] ON 
    ( FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf' )
    FOR ATTACH_REBUILD_LOG
    
    PRINT 'SUCCESS: Database CBMSv2_INITTEST attached successfully!'
END TRY
BEGIN CATCH
    PRINT 'ERROR: ' + ERROR_MESSAGE()
    PRINT 'Error Number: ' + CAST(ERROR_NUMBER() AS VARCHAR(10))
    PRINT 'Error Severity: ' + CAST(ERROR_SEVERITY() AS VARCHAR(10))
    PRINT 'Error Line: ' + CAST(ERROR_LINE() AS VARCHAR(10))
END CATCH
GO

-- Verify database is online
SELECT name, state_desc FROM sys.databases WHERE name = 'CBMSv2_INITTEST'
GO
