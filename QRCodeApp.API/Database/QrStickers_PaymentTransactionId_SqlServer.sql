/*
  QrStickers: ensure column PaymentTransactionId (nvarchar(120), NULL).
  - If legacy column PaymentPayerUpiVpa exists → rename to PaymentTransactionId.
  - Else if PaymentTransactionId missing → ADD COLUMN.

  Safe to run multiple times. Matches EF migrations:
    20260323120000_AddPaymentPayerUpiVpaToQrStickers
    20260323150000_FixLegacyPaymentColumnOnQrStickers

  Optional: second batch records migrations in __EFMigrationsHistory when you applied
  this script manually and want Database.Migrate() to skip them (same idea as
  CallMeNow_SyncEfHistory_AfterManualScripts.sql).
*/
SET NOCOUNT ON;

IF COL_LENGTH('dbo.QrStickers', 'PaymentTransactionId') IS NULL
   AND COL_LENGTH('dbo.QrStickers', 'PaymentPayerUpiVpa') IS NOT NULL
BEGIN
    EXEC sp_rename N'dbo.QrStickers.PaymentPayerUpiVpa', N'PaymentTransactionId', 'COLUMN';
END
GO

IF COL_LENGTH('dbo.QrStickers', 'PaymentTransactionId') IS NULL
   AND COL_LENGTH('dbo.QrStickers', 'PaymentPayerUpiVpa') IS NULL
BEGIN
    ALTER TABLE [dbo].[QrStickers] ADD [PaymentTransactionId] NVARCHAR(120) NULL;
END
GO

PRINT N'QrStickers.PaymentTransactionId ensured.';
GO

/* --- Optional: sync EF migration history (uncomment if needed) ---
DECLARE @v NVARCHAR(32) = N'10.0.3';

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260323120000_AddPaymentPayerUpiVpaToQrStickers')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260323120000_AddPaymentPayerUpiVpaToQrStickers', @v);

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260323150000_FixLegacyPaymentColumnOnQrStickers')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260323150000_FixLegacyPaymentColumnOnQrStickers', @v);

PRINT N'__EFMigrationsHistory updated for QrStickers payment column migrations.';
GO
*/
