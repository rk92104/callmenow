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
    CREATE UNIQUE INDEX [IX_Persons_Email] ON [dbo].[Persons] ([Email]);
    CREATE UNIQUE INDEX [IX_Persons_PhoneNumber] ON [dbo].[Persons] ([PhoneNumber]);
END
GO

-- 3. Create QrStickers Table
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[QrStickers]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[QrStickers] (
        [Id] INT IDENTITY(1,1) NOT NULL,
        [PublicId] NVARCHAR(40) NOT NULL,
        [IvrAccessCode] NVARCHAR(6) NULL,
        [IvrEmergencyAccessCode] NVARCHAR(6) NULL,
        [ProductType] NVARCHAR(50) NOT NULL,
        [Status] INT NOT NULL DEFAULT 0,
        [PersonId] INT NULL,
        [ScanCount] INT NOT NULL DEFAULT 0,
        [UniqueScannerCount] INT NOT NULL DEFAULT 0,
        [CreatedAt] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        [ActivatedAt] DATETIME2(7) NULL,
        [PaymentTransactionId] NVARCHAR(120) NULL,
        CONSTRAINT [PK_QrStickers] PRIMARY KEY CLUSTERED ([Id] ASC),
        CONSTRAINT [FK_QrStickers_Persons_PersonId] FOREIGN KEY ([PersonId]) REFERENCES [dbo].[Persons] ([Id]) ON DELETE SET NULL
    );
    CREATE UNIQUE INDEX [IX_QrStickers_PublicId] ON [dbo].[QrStickers] ([PublicId]);
    CREATE INDEX [IX_QrStickers_PersonId] ON [dbo].[QrStickers] ([PersonId]);
END
GO

-- 4. Create QrScanEvents Table
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[QrScanEvents]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[QrScanEvents] (
        [Id] BIGINT IDENTITY(1,1) NOT NULL,
        [QrStickerId] INT NOT NULL,
        [VisitorHash] NVARCHAR(64) NOT NULL,
        [ScannedAtUtc] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        [UserAgentSnippet] NVARCHAR(256) NULL,
        CONSTRAINT [PK_QrScanEvents] PRIMARY KEY CLUSTERED ([Id] ASC),
        CONSTRAINT [FK_QrScanEvents_QrStickers_QrStickerId] FOREIGN KEY ([QrStickerId]) REFERENCES [dbo].[QrStickers] ([Id]) ON DELETE CASCADE
    );
    CREATE INDEX [IX_QrScanEvents_QrStickerId] ON [dbo].[QrScanEvents] ([QrStickerId]);
    CREATE INDEX [IX_QrScanEvents_ScannedAtUtc] ON [dbo].[QrScanEvents] ([ScannedAtUtc]);
END
GO

-- 5. Create MarketingLeads Table
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MarketingLeads]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[MarketingLeads] (
        [Id] INT IDENTITY(1,1) NOT NULL,
        [PhoneNormalized] NVARCHAR(20) NOT NULL,
        [QrPublicId] NVARCHAR(40) NULL,
        [ReferralCode] NVARCHAR(32) NULL,
        [Source] NVARCHAR(64) NOT NULL DEFAULT 'scan_page_coupon',
        [CreatedAtUtc] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_MarketingLeads] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
    CREATE INDEX [IX_MarketingLeads_CreatedAtUtc] ON [dbo].[MarketingLeads] ([CreatedAtUtc]);
    CREATE INDEX [IX_MarketingLeads_PhoneNormalized] ON [dbo].[MarketingLeads] ([PhoneNormalized]);
END
GO

-- 6. Create ActiveCallMappings Table
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[ActiveCallMappings]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[ActiveCallMappings] (
        [Id] INT IDENTITY(1,1) NOT NULL,
        [CallerPhoneNormalized] NVARCHAR(20) NOT NULL,
        [TargetOwnerPhone] NVARCHAR(20) NOT NULL,
        [CreatedAtUtc] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        [ExpiryUtc] DATETIME2(7) NOT NULL,
        CONSTRAINT [PK_ActiveCallMappings] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
END
GO

-- 7. Create StickerOrders Table (New E-commerce Table)
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[StickerOrders]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[StickerOrders] (
        [Id] INT IDENTITY(1,1) NOT NULL,
        [CustomerName] NVARCHAR(100) NOT NULL,
        [CustomerPhone] NVARCHAR(20) NOT NULL,
        [ShippingAddress] NVARCHAR(500) NOT NULL,
        [City] NVARCHAR(50) NOT NULL,
        [Pincode] NVARCHAR(10) NOT NULL,
        [ProductId] NVARCHAR(50) NOT NULL,
        [ProductName] NVARCHAR(100) NOT NULL,
        [Amount] DECIMAL(18, 2) NOT NULL,
        [Status] NVARCHAR(20) NOT NULL DEFAULT 'Pending',
        [AssignedPublicId] NVARCHAR(40) NULL,
        [RazorpayOrderId] NVARCHAR(100) NULL,
        [RazorpayPaymentId] NVARCHAR(100) NULL,
        [RazorpaySignature] NVARCHAR(256) NULL,
        [CreatedAtUtc] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_StickerOrders] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
    CREATE INDEX [IX_StickerOrders_CreatedAtUtc] ON [dbo].[StickerOrders] ([CreatedAtUtc]);
END
GO

PRINT 'Database schema updated successfully!';
