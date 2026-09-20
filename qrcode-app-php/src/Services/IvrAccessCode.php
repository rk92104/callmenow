<?php

declare(strict_types=1);

namespace QrApp\Services;

use PDO;

/** Unique 4-digit codes for Exotel Gather → Connect IVR. */
final class IvrAccessCode
{
    private static ?bool $hasEmergencyCol = null;

    public static function hasEmergencyColumn(PDO $pdo): bool
    {
        if (self::$hasEmergencyCol !== null) {
            return self::$hasEmergencyCol;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM qr_stickers LIKE 'ivr_emergency_access_code'");
            self::$hasEmergencyCol = ($stmt && $stmt->fetch() !== false);
        } catch (\Throwable) {
            self::$hasEmergencyCol = false;
        }

        return self::$hasEmergencyCol;
    }

    /** @throws \RuntimeException */
    public static function allocate(PDO $pdo): string
    {
        $hasEm = self::hasEmergencyColumn($pdo);

        for ($i = 0; $i < 80; $i++) {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if ($hasEm) {
                $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE ivr_access_code = ? OR ivr_emergency_access_code = ? LIMIT 1');
                $chk->execute([$code, $code]);
            } else {
                $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE ivr_access_code = ? LIMIT 1');
                $chk->execute([$code]);
            }
            if (!$chk->fetch()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate IVR access code.');
    }

    public static function generateUnique(PDO $pdo): string
    {
        return self::allocate($pdo);
    }

    public static function columnExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM qr_stickers LIKE 'ivr_access_code'");
            return $stmt && $stmt->fetch() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function repairSchema(PDO $pdo): void
    {
        // Add IvrAccessCode if missing
        $pdo->exec("
            SET @db = (SELECT DATABASE());
            
            -- Add ivr_access_code if missing
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND COLUMN_NAME = 'ivr_access_code') THEN
                ALTER TABLE qr_stickers ADD COLUMN ivr_access_code VARCHAR(6) NULL AFTER public_id;
            END IF;
            
            -- Add ivr_emergency_access_code if missing
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND COLUMN_NAME = 'ivr_emergency_access_code') THEN
                ALTER TABLE qr_stickers ADD COLUMN ivr_emergency_access_code VARCHAR(6) NULL AFTER ivr_access_code;
            END IF;

            -- Add unique indexes if missing
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND INDEX_NAME = 'uq_ivr_access_code') THEN
                 ALTER TABLE qr_stickers ADD UNIQUE KEY `uq_ivr_access_code` (`ivr_access_code`);
            END IF;
            
            IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'qr_stickers' AND INDEX_NAME = 'uq_ivr_emergency_access_code') THEN
                 ALTER TABLE qr_stickers ADD UNIQUE KEY `uq_ivr_emergency_access_code` (`ivr_emergency_access_code`);
            END IF;
        ");
    }
}
