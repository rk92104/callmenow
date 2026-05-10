-- 1. Create the Database
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'QRCodeContactDB')
BEGIN
    CREATE DATABASE [QRCodeContactDB];
END
GO

USE [QRCodeContactDB];
GO

-- 2. Create the Persons Table
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Persons]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Persons] (
        [Id] INT IDENTITY(1,1) NOT NULL,
        [Name] NVARCHAR(100) NOT NULL,
        [PhoneNumber] NVARCHAR(20) NOT NULL,
        [Email] NVARCHAR(150) NOT NULL,
        [Address] NVARCHAR(300) NOT NULL,
        [FatherName] NVARCHAR(100) NOT NULL,
        [CreatedAt] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        [UpdatedAt] DATETIME2(7) NULL,
        CONSTRAINT [PK_Persons] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
END
GO

-- 3. Create Unique Indexes (to prevent duplicate emails or phone numbers)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Persons_Email' AND object_id = OBJECT_ID(N'[dbo].[Persons]'))
BEGIN
    CREATE UNIQUE INDEX [IX_Persons_Email] ON [dbo].[Persons] ([Email]);
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Persons_PhoneNumber' AND object_id = OBJECT_ID(N'[dbo].[Persons]'))
BEGIN
    CREATE UNIQUE INDEX [IX_Persons_PhoneNumber] ON [dbo].[Persons] ([PhoneNumber]);
END
GO

PRINT 'Database and Table created successfully!';
