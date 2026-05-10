<?php

declare(strict_types=1);

namespace QrApp;

final class Http
{
    public static function corsOrigin(): string
    {
        return Config::load()['corsOrigin'];
    }

    /**
     * Sends Allow-Origin for the browser request when it matches configured site
     * (fixes www vs non-www mismatch after deploy).
     */
    public static function corsHeaders(): void
    {
        header('Access-Control-Allow-Origin: ' . self::effectiveAllowOrigin());
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    private static function effectiveAllowOrigin(): string
    {
        $cfg = Config::load();
        $configured = (string) $cfg['corsOrigin'];
        $publicBase = (string) $cfg['publicBaseUrl'];
        $requestOrigin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
        if ($requestOrigin !== '' && (self::originsSameSite($requestOrigin, $configured) || self::originsSameSite($requestOrigin, $publicBase))) {
            return $requestOrigin;
        }

        return $configured;
    }

    private static function originsSameSite(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $pa = parse_url($a);
        $pb = parse_url($b);
        if (!is_array($pa) || !is_array($pb)) {
            return false;
        }
        $hostA = isset($pa['host']) ? strtolower($pa['host']) : '';
        $hostB = isset($pb['host']) ? strtolower($pb['host']) : '';
        if ($hostA === '' || $hostB === '') {
            return false;
        }
        $schemeA = isset($pa['scheme']) ? strtolower((string) $pa['scheme']) : '';
        $schemeB = isset($pb['scheme']) ? strtolower((string) $pb['scheme']) : '';
        if ($schemeA !== '' && $schemeB !== '' && $schemeA !== $schemeB) {
            return false;
        }
        $strip = static fn (string $h): string => str_starts_with($h, 'www.') ? substr($h, 4) : $h;

        return $strip($hostA) === $strip($hostB);
    }

    public static function corsPreflight(): void
    {
        self::corsHeaders();
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
    }

    /** @param array<string,mixed> $data */
    public static function json(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function jsonRaw(int $code, string $json): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
    }

    public static function textHtml(int $code, string $html, bool $preventCache = false): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        if ($preventCache) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
            header('Vary: *');
        }
        echo $html;
    }

    public static function png(string $binary): void
    {
        http_response_code(200);
        header('Content-Type: image/png');
        echo $binary;
    }

    public static function svg(string $markup): void
    {
        http_response_code(200);
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo $markup;
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::json(404, ['message' => $message]);
    }

    public static function badRequest(string|array $message): void
    {
        if (is_array($message)) {
            self::json(400, $message);
            return;
        }
        self::json(400, ['message' => $message]);
    }

    public static function conflict(string $message): void
    {
        self::json(409, ['message' => $message]);
    }

    /** @return array<string,mixed>|null */
    public static function readJsonBody(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
