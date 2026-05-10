/* Analytics (each scan) + marketing leads + UniqueScannerCount on QrStickers.
   Requires: Persons + QrStickers already exist. */
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF COL_LENGTH('dbo.QrStickers', 'UniqueScannerCount') IS NULL
BEGIN
    ALTER TABLE [dbo].[QrStickers]
    ADD [UniqueScannerCount] INT NOT NULL CONSTRAINT [DF_QrStickers_UniqueScannerCount] DEFAULT (0);
END
GO

IF OBJECT_ID(N'[dbo].[QrScanEvents]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[QrScanEvents] (
        [Id]                 BIGINT         IDENTITY (1, 1) NOT NULL,
        [QrStickerId]        INT            NOT NULL,
        [VisitorHash]        NVARCHAR (64)  NOT NULL,
        [ScannedAtUtc]       DATETIME2 (7)  NOT NULL,
        [UserAgentSnippet]   NVARCHAR (256) NULL,
        CONSTRAINT [PK_QrScanEvents] PRIMARY KEY CLUSTERED ([Id] ASC),
        CONSTRAINT [FK_QrScanEvents_QrStickers_QrStickerId] FOREIGN KEY ([QrStickerId]) REFERENCES [dbo].[QrStickers] ([Id]) ON DELETE CASCADE
    );
    CREATE NONCLUSTERED INDEX [IX_QrScanEvents_QrStickerId_VisitorHash]
        ON [dbo].[QrScanEvents]([QrStickerId] ASC, [VisitorHash] ASC);
    CREATE NONCLUSTERED INDEX [IX_QrScanEvents_ScannedAtUtc]
        ON [dbo].[QrScanEvents]([ScannedAtUtc] ASC);
END
GO

IF OBJECT_ID(N'[dbo].[MarketingLeads]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[MarketingLeads] (
        [Id]               INT            IDENTITY (1, 1) NOT NULL,
        [PhoneNormalized]  NVARCHAR (20)  NOT NULL,
        [QrPublicId]       NVARCHAR (40)  NULL,
        [ReferralCode]     NVARCHAR (32)  NULL,
        [Source]           NVARCHAR (64)  NOT NULL,
        [CreatedAtUtc]     DATETIME2 (7)  NOT NULL,
        CONSTRAINT [PK_MarketingLeads] PRIMARY KEY CLUSTERED ([Id] ASC)
    );
    CREATE NONCLUSTERED INDEX [IX_MarketingLeads_CreatedAtUtc]
        ON [dbo].[MarketingLeads]([CreatedAtUtc] ASC);
    CREATE NONCLUSTERED INDEX [IX_MarketingLeads_PhoneNormalized]
        ON [dbo].[MarketingLeads]([PhoneNormalized] ASC);
END
GO
