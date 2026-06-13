USE [master]
GO

PRINT 'Starting database attachment...'
PRINT 'Database: CBMSv2_INITTEST'
PRINT 'MDF: C:\CBMS\CBMSv21\database\CBMSv2_INITTEST.mdf'
PRINT 'LDF: C:\CBMS\CBMSv21\database\CBMSv2_INITTEST_log.ldf'
PRINT ''

BEGIN TRY
    CREATE DATABASE [CBMSv2_INITTEST] ON 
     ( FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf' ),
     ( FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST_log.ldf' )
    FOR ATTACH
    
    PRINT 'SUCCESS: Database CBMSv2_INITTEST attached successfully!'
END TRY
BEGIN CATCH
    PRINT 'ERROR: ' + ERROR_MESSAGE()
    PRINT 'Error Number: ' + CAST(ERROR_NUMBER() AS VARCHAR(10))
    PRINT 'Error Severity: ' + CAST(ERROR_SEVERITY() AS VARCHAR(10))
    PRINT 'Error Line: ' + CAST(ERROR_LINE() AS VARCHAR(10))
END CATCH
GO
