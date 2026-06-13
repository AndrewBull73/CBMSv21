-- Attach CBMSv2 database from existing MDF file
-- Update the MDF path if needed and run with appropriate SQL Server instance/credentials.

SET NOCOUNT ON;

IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'CBMSv2')
BEGIN
    PRINT 'Attempting to attach database CBMSv2...';

    BEGIN TRY
        CREATE DATABASE [CBMSv2] ON 
            (FILENAME = N'C:\CBMS\DBFiles\CBMSv2.mdf'),
            (FILENAME = N'C:\CBMS\DBFiles\CBMSv2_log.ldf')
        FOR ATTACH;
        PRINT 'CREATE DATABASE command executed successfully.';
    END TRY
    BEGIN CATCH
        PRINT 'Error attaching database:';
        PRINT ERROR_MESSAGE();
        THROW;
    END CATCH
END
ELSE
BEGIN
    PRINT 'Database CBMSv2 already exists on this server.';
END
