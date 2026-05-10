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
            """);
    }
}
