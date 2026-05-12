using Microsoft.EntityFrameworkCore;

namespace QRCodeApp.API.Data;

/// <summary>
/// Repairs LocalDB / older DBs where <c>__EFMigrationsHistory</c> is ahead of the real table
/// (e.g. missing <c>IvrAccessCode</c>), which otherwise breaks every <c>QrStickers</c> query with SQL 207.
/// </summary>
public static class QrStickerSchemaRepair
{
    public static void ApplyIfNeeded(AppDbContext db)
    {
        db.Database.ExecuteSqlRaw(
            """
            IF COL_LENGTH(N'dbo.QrStickers', N'IvrAccessCode') IS NULL
                ALTER TABLE dbo.QrStickers ADD [IvrAccessCode] NVARCHAR(6) NULL;
            
            IF COL_LENGTH(N'dbo.QrStickers', N'IvrEmergencyAccessCode') IS NULL
                ALTER TABLE dbo.QrStickers ADD [IvrEmergencyAccessCode] NVARCHAR(6) NULL;
            """);

        db.Database.ExecuteSqlRaw(
            """
            IF NOT EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE object_id = OBJECT_ID(N'dbo.QrStickers', N'U')
                  AND name = N'IX_QrStickers_IvrAccessCode')
            BEGIN
                CREATE UNIQUE NONCLUSTERED INDEX IX_QrStickers_IvrAccessCode
                ON dbo.QrStickers ([IvrAccessCode])
                WHERE [IvrAccessCode] IS NOT NULL;
            END

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
                    [CreatedAtUtc] DATETIME2(7) NOT NULL DEFAULT (GETUTCDATE()),
                    [RazorpayOrderId] NVARCHAR(100) NULL,
                    [RazorpayPaymentId] NVARCHAR(100) NULL,
                    [RazorpaySignature] NVARCHAR(200) NULL,
                    CONSTRAINT [PK_StickerOrders] PRIMARY KEY CLUSTERED ([Id] ASC)
                );
                CREATE INDEX [IX_StickerOrders_CreatedAtUtc] ON [dbo].[StickerOrders] ([CreatedAtUtc]);
            END

            IF COL_LENGTH(N'dbo.StickerOrders', N'AssignedPublicId') IS NULL
                ALTER TABLE dbo.StickerOrders ADD [AssignedPublicId] NVARCHAR(50) NULL;

            IF COL_LENGTH(N'dbo.StickerOrders', N'RazorpayOrderId') IS NULL
                ALTER TABLE dbo.StickerOrders ADD [RazorpayOrderId] NVARCHAR(100) NULL;

            IF COL_LENGTH(N'dbo.StickerOrders', N'RazorpayPaymentId') IS NULL
                ALTER TABLE dbo.StickerOrders ADD [RazorpayPaymentId] NVARCHAR(100) NULL;

            IF COL_LENGTH(N'dbo.StickerOrders', N'RazorpaySignature') IS NULL
                ALTER TABLE dbo.StickerOrders ADD [RazorpaySignature] NVARCHAR(200) NULL;
            """);
    }
}
