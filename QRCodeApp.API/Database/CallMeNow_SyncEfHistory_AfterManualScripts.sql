/*
  Use when schema was updated with CallMeNow_*.sql scripts but __EFMigrationsHistory
  was not updated. Prevents EF from re-running migrations that would fail (duplicate column/table).

  After this, `Database.Migrate()` on startup stays quiet and matches the compiled model.
*/
SET NOCOUNT ON;

IF COL_LENGTH('dbo.Persons', 'PaymentCompleted') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [PaymentCompleted] BIT NOT NULL CONSTRAINT [DF_Persons_PaymentCompleted] DEFAULT (0);
END
IF COL_LENGTH('dbo.Persons', 'PaymentReference') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [PaymentReference] NVARCHAR(120) NULL;
END
GO

DECLARE @v NVARCHAR(32) = N'10.0.3';

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260321140000_AddScanAnalyticsAndLeads')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260321140000_AddScanAnalyticsAndLeads', @v);

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260321150000_AddVehicleEmergencyToPerson')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260321150000_AddVehicleEmergencyToPerson', @v);

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260321180000_AddPhoneLineTypesToPerson')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260321180000_AddPhoneLineTypesToPerson', @v);

IF NOT EXISTS (SELECT 1 FROM [__EFMigrationsHistory] WHERE [MigrationId] = N'20260321193000_AddPersonActivationPayment')
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion]) VALUES (N'20260321193000_AddPersonActivationPayment', @v);

PRINT N'Persons payment columns + EF history sync done.';
GO
