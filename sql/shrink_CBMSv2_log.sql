-- Aggressively shrink the CBMSv2 transaction log file
USE master;
GO

-- Backup the log to clear out log space
BACKUP LOG CBMSv2 TO DISK = 'C:\CBMS\DBFiles\CBMSv2_log_backup.bak';
GO

-- Switch to SIMPLE recovery mode to clear the log
ALTER DATABASE CBMSv2 SET RECOVERY SIMPLE;
GO

-- Shrink the log file
USE CBMSv2;
GO
DBCC SHRINKFILE (CBMSv2_log, 1);
GO

-- Switch back to FULL recovery mode
USE master;
GO
ALTER DATABASE CBMSv2 SET RECOVERY FULL;
GO

PRINT 'Transaction log shrink complete.';
