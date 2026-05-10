/* CallMeNow core tables — run on empty database (or use EF InitialCreate + AddCallMeNowQrStickers migrations). */
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID(N'[dbo].[Persons]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[Persons] (
        [Id]           INT            IDENTITY (1, 1) NOT NULL,
        [Name]         NVARCHAR (100) NOT NULL,
        [PhoneNumber]  NVARCHAR (20)  NOT NULL,
        [PhoneNumberType] NVARCHAR (16) NOT NULL CONSTRAINT [DF_Persons_PhoneNumberType] DEFAULT (N'Mobile'),
        [Email]        NVARCHAR (150) NOT NULL,
        [Address]      NVARCHAR (300) NOT NULL,
        [FatherName]   NVARCHAR (100) NOT NULL,
        [VehicleRegistration] NVARCHAR (20) NOT NULL CONSTRAINT [DF_Persons_VehicleRegistration] DEFAULT (''),
        [EmergencyContactPhone] NVARCHAR (20) NOT NULL CONSTRAINT [DF_Persons_EmergencyContactPhone] DEFAULT (''),
        [EmergencyContactPhoneType] NVARCHAR (16) NOT NULL CONSTRAINT [DF_Persons_EmergencyContactPhoneType] DEFAULT (N'Mobile'),
        [CreatedAt]    DATETIME2 (7)  NOT NULL,
        [UpdatedAt]    DATETIME2 (7)  NULL,
        CONSTRAINT [PK_Persons] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
END
GO

IF OBJECT_ID(N'[dbo].[QrStickers]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[QrStickers] (
        [Id]                 INT            IDENTITY (1, 1) NOT NULL,
        [PublicId]           NVARCHAR (40)  NOT NULL,
        [ProductType]        NVARCHAR (50)  NOT NULL,
        [Status]             TINYINT        NOT NULL,
        [PersonId]           INT            NULL,
        [ScanCount]          INT            NOT NULL DEFAULT 0,
        [UniqueScannerCount] INT            NOT NULL DEFAULT 0,
        [CreatedAt]          DATETIME2 (7)  NOT NULL,
        [ActivatedAt]        DATETIME2 (7)  NULL,
        CONSTRAINT [PK_QrStickers] PRIMARY KEY CLUSTERED ([Id] ASC),
        CONSTRAINT [FK_QrStickers_Persons_PersonId] FOREIGN KEY ([PersonId]) REFERENCES [dbo].[Persons] ([Id]) ON DELETE SET NULL
    );
    CREATE UNIQUE NONCLUSTERED INDEX [IX_QrStickers_PublicId] ON [dbo].[QrStickers]([PublicId] ASC);
    CREATE NONCLUSTERED INDEX [IX_QrStickers_PersonId] ON [dbo].[QrStickers]([PersonId] ASC);
END
GO
