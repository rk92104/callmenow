using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <summary>
    /// Older DBs may have PaymentPayerUpiVpa while the model uses PaymentTransactionId — renames or adds as needed.
    /// </summary>
    public class FixLegacyPaymentColumnOnQrStickers : Migration
    {
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.Sql(@"
IF COL_LENGTH('dbo.QrStickers', 'PaymentTransactionId') IS NULL
   AND COL_LENGTH('dbo.QrStickers', 'PaymentPayerUpiVpa') IS NOT NULL
BEGIN
    EXEC sp_rename N'dbo.QrStickers.PaymentPayerUpiVpa', N'PaymentTransactionId', 'COLUMN';
END
");

            migrationBuilder.Sql(@"
IF COL_LENGTH('dbo.QrStickers', 'PaymentTransactionId') IS NULL
   AND COL_LENGTH('dbo.QrStickers', 'PaymentPayerUpiVpa') IS NULL
BEGIN
    ALTER TABLE [QrStickers] ADD [PaymentTransactionId] nvarchar(120) NULL;
END
");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
        }
    }
}
