/* Add vehicle + emergency columns to existing Persons table */
IF COL_LENGTH('dbo.Persons', 'VehicleRegistration') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [VehicleRegistration] NVARCHAR(20) NOT NULL CONSTRAINT [DF_Persons_VehicleRegistration] DEFAULT ('');
END
GO
IF COL_LENGTH('dbo.Persons', 'EmergencyContactPhone') IS NULL
BEGIN
    ALTER TABLE [dbo].[Persons] ADD [EmergencyContactPhone] NVARCHAR(20) NOT NULL CONSTRAINT [DF_Persons_EmergencyContactPhone] DEFAULT ('');
END
GO
