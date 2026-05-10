/*
  CallMeNow — SQL Server reference scripts.
  Recommended: let the API run `Migrate()` on startup (EF Core migrations).

  If EF history says “up to date” but features break (inventory generate, scan, persons API),
  run these idempotent scripts on your database in order:

  1) CallMeNow_BaseTables.sql       — Persons + QrStickers (new DB only)
  2) CallMeNow_VehicleEmergency.sql — Person vehicle + emergency columns
  3) CallMeNow_PhoneLineTypes.sql   — Person Mobile/Landline types
  4) CallMeNow_AnalyticsLeads.sql   — QrStickers.UniqueScannerCount + QrScanEvents + MarketingLeads
*/
