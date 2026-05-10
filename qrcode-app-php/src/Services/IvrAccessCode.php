<?php

declare(strict_types=1);

namespace QrApp\Services;

use PDO;

/** Unique 6-digit codes for Exotel Gather → Connect IVR. */
final class IvrAccessCode
{
    /** @throws \RuntimeException */
    public static function allocate(PDO $pdo): string
    {
        for ($i = 0; $i < 80; $i++) {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE ivr_access_code = ? OR ivr_emergency_access_code = ? LIMIT 1');
            $chk->execute([$code, $code]);
            if (!$chk->fetch()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate IVR access code.');
    }

    public static function repairSchema(PDO $pdo): void
    {
        // Add IvrAccessCode if missing
        $pdo->exec("
            SET @db = (SELECT DATABASE());
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND COLUMN_NAME = 'ivr_access_code') THEN
                ALTER TABLE qr_stickers ADD COLUMN ivr_access_code VARCHAR(6) NULL AFTER public_id;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND COLUMN_NAME = 'ivr_emergency_access_code') THEN
                ALTER TABLE qr_stickers ADD COLUMN ivr_emergency_access_code VARCHAR(6) NULL AFTER ivr_access_code;
            END IF;
        ");
    }
}
