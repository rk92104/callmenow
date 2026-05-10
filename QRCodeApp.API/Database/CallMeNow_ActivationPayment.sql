/* Payment fields saved when a sticker is activated (demo checkbox + optional gateway ref) */
IF COL_LENGTH('dbo.Persons', 'PaymentCompleted') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [PaymentCompleted] BIT NOT NULL CONSTRAINT [DF_Persons_PaymentCompleted] DEFAULT (0);
END
GO
IF COL_LENGTH('dbo.Persons', 'PaymentReference') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [PaymentReference] NVARCHAR(120) NULL;
END
GO
