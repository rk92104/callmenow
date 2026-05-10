/* Mobile vs landline labels for owner + emergency numbers */
IF COL_LENGTH('dbo.Persons', 'PhoneNumberType') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [PhoneNumberType] NVARCHAR(16) NOT NULL CONSTRAINT [DF_Persons_PhoneNumberType] DEFAULT (N'Mobile');
END
GO
IF COL_LENGTH('dbo.Persons', 'EmergencyContactPhoneType') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [EmergencyContactPhoneType] NVARCHAR(16) NOT NULL CONSTRAINT [DF_Persons_EmergencyContactPhoneType] DEFAULT (N'Mobile');
END
GO
