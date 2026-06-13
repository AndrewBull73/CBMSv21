USE [master]
GO

-- First, check if database is already attached
IF EXISTS (SELECT * FROM sys.databases WHERE name = 'CBMSv2_INITTEST')
BEGIN
    ALTER DATABASE [CBMSv2_INITTEST] SET OFFLINE WITH ROLLBACK IMMEDIATE
    EXEC sp_detach_db @dbname = 'CBMSv2_INITTEST'
    PRINT 'Detached existing CBMSv2_INITTEST'
END
GO

-- Now attempt attach using sp_attach_db (older method, sometimes works better)
PRINT 'Attempting attach with sp_attach_db stored procedure...'

BEGIN TRY
    EXEC sp_attach_db @dbname = 'CBMSv2_INITTEST',
        @filename1 = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf',
        @filename2 = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST_log.ldf'
    
    PRINT 'SUCCESS: Database attached!'
END TRY
BEGIN CATCH
    PRINT 'ERROR with sp_attach_db: ' + ERROR_MESSAGE()
    PRINT 'Trying ALTER DATABASE attach method...'
    
    BEGIN TRY
        CREATE DATABASE [CBMSv2_INITTEST] ON
            (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST.mdf'),
            (FILENAME = 'C:\\CBMS\\CBMSv21\\database\\CBMSv2_INITTEST_log.ldf')
        FOR ATTACH
        
        PRINT 'SUCCESS: Database attached via CREATE DATABASE!'
    END TRY
    BEGIN CATCH
        PRINT 'ERROR with CREATE DATABASE: ' + ERROR_MESSAGE()
    END CATCH
END CATCH
GO

-- Check final status
SELECT name, state_desc, user_access_desc FROM sys.databases WHERE name = 'CBMSv2_INITTEST'
GO
