/*
    Quick safety check before running any reset or verification script.

    Expected result for this cycle:
    - CBMSv2_INITTEST
*/

SET NOCOUNT ON;

SELECT
    DB_NAME() AS ActiveDatabase,
    @@SERVERNAME AS SqlServerName,
    SYSDATETIME() AS CheckedAt;
