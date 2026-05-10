<?php

declare(strict_types=1);

namespace QrApp\Services;

final class VisitorFingerprint
{
    public static function compute(string $publicId, ?string $remoteIp, string $userAgent): string
    {
        $ua = $userAgent;
        if (strlen($ua) > 400) {
            $ua = substr($ua, 0, 400);
        }
        $ip = ($remoteIp === null || trim($remoteIp) === '') ? 'unknown' : trim($remoteIp);
        $raw = $publicId . '|' . $ip . '|' . $ua;

        return strtolower(hash('sha256', $raw));
    }
}
