USE [master]
GO

PRINT 'Attempting to attach CBMSv2_INITTEST with ATTACH_REBUILD_LOG...'

BEGIN TRY
    CREATE DATABASE [CBMSv2_INITTEST] ON
        (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf')
    FOR ATTACH_REBUILD_LOG
    
    PRINT 'SUCCESS: Database attached with rebuilt log!'
END TRY
BEGIN CATCH
    PRINT 'ERROR: ' + ERROR_MESSAGE()
    PRINT 'Error Number: ' + CAST(ERROR_NUMBER() AS VARCHAR(10))
END CATCH
GO

-- Verify
SELECT name, state_desc FROM sys.databases WHERE name = 'CBMSv2_INITTEST'
GO
