<?php

declare(strict_types=1);

namespace QrApp\Services;

final class QrPublicIdGenerator
{
    public static function createNext(): string
    {
        $yy = gmdate('y');
        $bytes = random_bytes(3);

        return 'CMN-' . $yy . '-' . strtoupper(bin2hex($bytes));
    }
}
