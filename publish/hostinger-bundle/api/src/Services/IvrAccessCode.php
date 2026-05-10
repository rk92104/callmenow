<?php

declare(strict_types=1);

namespace QrApp\Services;

use PDO;

/** Unique 6-digit codes for Exotel Gather → Connect IVR. */
final class IvrAccessCode
{
    /** @throws \RuntimeException */
    public static function generateUnique(PDO $pdo): string
    {
        for ($i = 0; $i < 80; $i++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE ivr_access_code = ? LIMIT 1');
            $chk->execute([$code]);
            if (!$chk->fetch()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate IVR access code.');
    }

    public static function columnExists(PDO $pdo): bool
    {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$db, 'qr_stickers', 'ivr_access_code']);

        return (bool) $stmt->fetch();
    }
}
