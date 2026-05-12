<?php

declare(strict_types=1);

namespace QrApp;

final class Config
{
    /** @return array<string, mixed> */
    public static function load(): array
    {
        $local = self::loadLocalOverrides();

        $pick = static function (string $key, ?string $default = null) use ($local): ?string {
            if (array_key_exists($key, $local)) {
                $v = $local[$key];
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            }
            $e = getenv($key);
            if ($e !== false && $e !== '') {
                return $e;
            }

            return $default;
        };

        $pickInt = static function (string $key, int $default) use ($local): int {
            if (array_key_exists($key, $local) && $local[$key] !== null && $local[$key] !== '') {
                return (int) $local[$key];
            }
            $e = getenv($key);
            if ($e !== false && $e !== '') {
                return (int) $e;
            }

            return $default;
        };

        $dsn = $pick('MYSQL_DSN');
        if ($dsn === null || $dsn === '') {
            $host = $pick('MYSQL_HOST', '127.0.0.1') ?? '127.0.0.1';
            $port = $pick('MYSQL_PORT', '3306') ?? '3306';
            $db = $pick('MYSQL_DATABASE', 'qrcode') ?? 'qrcode';
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        }

        $user = $pick('MYSQL_USER', 'root') ?? 'root';
        $pass = '';
        if (array_key_exists('MYSQL_PASSWORD', $local)) {
            $pass = (string) $local['MYSQL_PASSWORD'];
        }
        if ($pass === '') {
            $e = getenv('MYSQL_PASSWORD');
            $pass = $e !== false ? $e : '';
        }

        $publicBaseUrlResolved = rtrim($pick('CALLMENOW_PUBLIC_BASE_URL', 'http://localhost:4200') ?? 'http://localhost:4200', '/');
        $printLogoOverride = trim((string) ($pick('CALLMENOW_PRINT_LABEL_LOGO_URL', '') ?? ''));
        $printLogoPath = '/' . ltrim(trim((string) ($pick('CALLMENOW_PRINT_LABEL_LOGO_PATH', '/marketing/scannerlogo.png') ?? '/marketing/scannerlogo.png')), '/');
        $printLabelLogoUrl = $printLogoOverride !== '' ? $printLogoOverride : ($publicBaseUrlResolved . $printLogoPath);

        return [
            'dsn' => $dsn,
            'user' => $user,
            'pass' => $pass,
            'publicBaseUrl' => $publicBaseUrlResolved,
            'printLabelLogoUrl' => $printLabelLogoUrl,
            'trustedOwnersLine' => $pick('CALLMENOW_TRUSTED_OWNERS_LINE', 'Trusted by 12,000+ vehicle owners')
                ?? 'Trusted by 12,000+ vehicle owners',
            'regionTagline' => $pick('CALLMENOW_REGION_TAGLINE', 'Used across Chandigarh & Tricity')
                ?? 'Used across Chandigarh & Tricity',
            'referralCode' => $pick('CALLMENOW_REFERRAL_CODE', 'SCAN50') ?? 'SCAN50',
            'stickerPriceInr' => $pickInt('CALLMENOW_STICKER_PRICE_INR', 299),
            'referralDiscountInr' => $pickInt('CALLMENOW_REFERRAL_DISCOUNT_INR', 50),
            'localizedCityLine' => $pick(
                'CALLMENOW_LOCALIZED_CITY_LINE',
                'People in Chandigarh use CallMeNow to solve parking problems — without sharing their number.'
            ) ?? 'People in Chandigarh use CallMeNow to solve parking problems — without sharing their number.',
            'corsOrigin' => $pick('CORS_ORIGIN', 'http://localhost:4200') ?? 'http://localhost:4200',
            'razorpayKeyId' => trim((string) ($pick('RAZORPAY_KEY_ID', '') ?? '')),
            'razorpayKeySecret' => trim((string) ($pick('RAZORPAY_KEY_SECRET', '') ?? '')),
            'exotelAccountSid' => trim((string) ($pick('EXOTEL_ACCOUNT_SID', '') ?? '')),
            'exotelApiKey' => trim((string) ($pick('EXOTEL_API_KEY', '') ?? '')),
            'exotelApiToken' => trim((string) ($pick('EXOTEL_API_TOKEN', '') ?? '')),
            'exotelSubdomain' => trim((string) ($pick('EXOTEL_SUBDOMAIN', 'api.exotel.com') ?? 'api.exotel.com')),
            'exotelCallerId' => trim((string) ($pick('EXOTEL_CALLER_ID', '') ?? '')),
            'exotelCallType' => trim((string) ($pick('EXOTEL_CALL_TYPE', 'trans') ?? 'trans')),
            'exotelDefaultIsd' => trim((string) ($pick('EXOTEL_DEFAULT_ISD', '91') ?? '91')),
            'exotelIvrDid' => trim((string) ($pick('EXOTEL_IVR_DID', '') ?? '')),
            'exotelIvrWebhookSecret' => trim((string) ($pick('EXOTEL_IVR_WEBHOOK_SECRET', '') ?? '')),
            // Default owner-first: visitor ko pehle “callback” ring nahi (Connect API From = owner).
            // Band karne ke liye config/env: EXOTEL_RING_OWNER_FIRST=0
            'exotelRingOwnerFirst' => self::envTruthy($pick('EXOTEL_RING_OWNER_FIRST', '1') ?? '1'),
            'exotelScanDirectTel' => self::envTruthy($pick('EXOTEL_SCAN_DIRECT_TEL', '0') ?? '0'),
            'exotelIvrGatherPrompt' => self::pickNonEmpty(
                $pick('EXOTEL_IVR_GATHER_PROMPT', '') ?? '',
                'Welcome to Call Me Now. Enter your four digit call code from the vehicle sticker, then press hash. Stay on the line while we connect you.'
            ),
            'exotelIvrGatherRepeatPrompt' => self::pickNonEmpty(
                $pick('EXOTEL_IVR_GATHER_REPEAT_PROMPT', '') ?? '',
                'We did not receive four digits. Please enter your four digit code, then press hash.'
            ),
            /** Exotel “connect customer to app”: only owner’s phone rings; no second leg to scanner. See EXOTEL_OWNER_ALERT_APP_URL in example config. */
            'exotelOwnerAlertAppUrl' => trim((string) ($pick('EXOTEL_OWNER_ALERT_APP_URL', '') ?? '')),
        ];
    }

    private static function pickNonEmpty(string $value, string $fallback): string
    {
        $t = trim($value);

        return $t !== '' ? $t : $fallback;
    }

    private static function envTruthy(?string $raw): bool
    {
        $s = strtolower(trim((string) ($raw ?? '')));

        return in_array($s, ['1', 'true', 'yes', 'on', 'owner', 'owner_first'], true);
    }

    public static function isDebug(): bool
    {
        $local = self::loadLocalOverrides();
        if (array_key_exists('APP_DEBUG', $local)) {
            $v = $local['APP_DEBUG'];
            if ($v === true || $v === 1) {
                return true;
            }
            if (is_string($v) && $v !== '' && $v !== '0') {
                return true;
            }
        }
        $e = getenv('APP_DEBUG');

        return $e !== false && $e !== '' && $e !== '0';
    }

    /** @return array<string, mixed> */
    private static function loadLocalOverrides(): array
    {
        $path = dirname(__DIR__) . '/config.local.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;

        return is_array($data) ? $data : [];
    }
}
