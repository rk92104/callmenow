<?php

declare(strict_types=1);

namespace QrApp\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

final class QrPngRenderer
{
    /**
     * Returns raw PNG bytes if GD is available, otherwise null.
     */
    public static function tryPng(string $payload, int $modulePixels): ?string
    {
        $modulePixels = max(1, min(64, $modulePixels));

        if (!extension_loaded('gd') || !function_exists('imagepng')) {
            return null;
        }

        $opt = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => $modulePixels,
            'outputBase64' => false,
            'returnResource' => false,
        ]);

        $out = (new QRCode($opt))->render($payload);

        // If config unexpectedly returns a data URI, fall back to null and let caller use HTTP/SVG.
        if (is_string($out) && str_starts_with($out, 'data:')) {
            return null;
        }

        return $out;
    }

    /**
     * Returns SVG markup (always available).
     */
    public static function svg(string $payload, int $modulePixels): string
    {
        $modulePixels = max(1, min(64, $modulePixels));

        $opt = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'scale' => $modulePixels,
            'outputBase64' => false,
            'returnResource' => false,
        ]);

        return (new QRCode($opt))->render($payload);
    }

    /**
     * Data URI for <img src="..."> in JSON responses.
     */
    public static function dataUriForImg(string $payload, int $modulePixels): string
    {
        $png = self::tryPng($payload, $modulePixels);
        if ($png !== null) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $svg = self::svg($payload, $modulePixels);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

