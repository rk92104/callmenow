-- Optional dev seed: three unused stickers when the table is empty (similar to old .NET Development seed).
INSERT INTO qr_stickers (public_id, product_type, status, scan_count, unique_scanner_count, created_at)
SELECT * FROM (
  SELECT 'CMN-SEED-01' AS public_id, 'CarSticker' AS product_type, 0 AS status, 0 AS scan_count, 0 AS unique_scanner_count, UTC_TIMESTAMP(3) AS created_at
  UNION ALL SELECT 'CMN-SEED-02', 'KeyFinder', 0, 0, 0, UTC_TIMESTAMP(3)
  UNION ALL SELECT 'CMN-SEED-03', 'LuggageTag', 0, 0, 0, UTC_TIMESTAMP(3)
) AS s
WHERE (SELECT COUNT(*) FROM qr_stickers) = 0;
