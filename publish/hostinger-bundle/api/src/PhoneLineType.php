<?php

declare(strict_types=1);

namespace QrApp;

final class PhoneLineType
{
    public const MOBILE = 'Mobile';
    public const LANDLINE = 'Landline';

    public static function isValid(?string $v): bool
    {
        if ($v === null) {
            return false;
        }

        return strcasecmp($v, self::MOBILE) === 0 || strcasecmp($v, self::LANDLINE) === 0;
    }

    public static function normalize(?string $v): string
    {
        return strcasecmp((string) $v, self::LANDLINE) === 0 ? self::LANDLINE : self::MOBILE;
    }
}
