<?php

declare(strict_types=1);

namespace QrApp;

use DateTimeImmutable;
use DateTimeZone;

final class Time
{
    public static function utcNowSql(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }

    public static function utcNowStr(): string
    {
        return self::utcNowSql();
    }

    public static function toIso(?string $sql): ?string
    {
        if ($sql === null || $sql === '') {
            return null;
        }
        try {
            $d = new DateTimeImmutable($sql, new DateTimeZone('UTC'));

            return $d->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return $sql;
        }
    }
}
