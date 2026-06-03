IF OBJECT_ID('dbo.tblLoginTokens', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblLoginTokens
    (
        LoginTokenID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        Token VARCHAR(128) NOT NULL,
        Email NVARCHAR(255) NULL,
        ExpiresAt DATETIME2(0) NOT NULL,
        IsUsed BIT NOT NULL CONSTRAINT DF_tblLoginTokens_IsUsed DEFAULT (0),
        UsedAt DATETIME2(0) NULL,
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblLoginTokens_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CreatedBy INT NULL,
        MessageID INT NULL
    );

    CREATE UNIQUE INDEX UX_tblLoginTokens_Token
        ON dbo.tblLoginTokens (Token);

    CREATE INDEX IX_tblLoginTokens_UserID
        ON dbo.tblLoginTokens (UserID, IsUsed, ExpiresAt);
END;
