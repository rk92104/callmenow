<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Config;
use QrApp\Http;
use QrApp\QrStickerStatus;
use QrApp\Services\IvrAccessCode;

/**
 * Exotel App Builder: Gather applet (dynamic URL) → Connect applet (dynamic URL).
 * Docs: working-with-gather-applet, programmable-connect-working-with-connect-applet-dynamic-url
 */
final class ExotelIvrHandler
{
    /** @param array<string,mixed> $cfg */
    public static function gather(array $cfg): void
    {
        if (!self::authorize($cfg)) {
            Http::json(403, ['message' => 'Forbidden']);
            return;
        }

        $welcome = (string) ($cfg['exotelIvrGatherPrompt'] ?? '');
        $repeat = (string) ($cfg['exotelIvrGatherRepeatPrompt'] ?? '');

        $body = [
            'gather_prompt' => [
                'text' => $welcome,
            ],
            'max_input_digits' => 6,
            'finish_on_key' => '#',
            'input_timeout' => 8,
            'repeat_menu' => 1,
            'repeat_gather_prompt' => [
                'text' => $repeat,
            ],
        ];
        Http::jsonRaw(200, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,mixed> $cfg */
    public static function connect(PDO $pdo, array $cfg): void
    {
        if (!self::authorize($cfg)) {
            Http::json(403, ['message' => 'Forbidden']);
            return;
        }

        if (!IvrAccessCode::columnExists($pdo)) {
            Http::json(503, ['message' => 'IVR not ready: run database/ensure_qr_stickers_ivr_access_code.sql']);
            return;
        }

        $digitsRaw = isset($_GET['digits']) ? (string) $_GET['digits'] : (isset($_GET['Digits']) ? (string) $_GET['Digits'] : '');
        $digitsRaw = trim($digitsRaw, " \t\n\r\0\x0B\"'");
        $digits = preg_replace('/\D/', '', $digitsRaw) ?? '';
        if (strlen($digits) !== 6) {
            self::respondConnectEmpty();
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT p.phone_number AS owner_phone FROM qr_stickers q
             INNER JOIN persons p ON p.id = q.person_id
             WHERE q.ivr_access_code = ? AND q.status = ? LIMIT 1'
        );
        $stmt->execute([$digits, QrStickerStatus::ACTIVE]);
        $row = $stmt->fetch();
        $e164 = $row ? self::ownerToE164((string) ($row['owner_phone'] ?? ''), (string) ($cfg['exotelDefaultIsd'] ?? '91')) : null;

        if ($e164 === null || $e164 === '') {
            self::respondConnectEmpty();
            return;
        }

        $payload = [
            'fetch_after_attempt' => false,
            'destination' => [
                'numbers' => [$e164],
            ],
            'max_ringing_duration' => 45,
            'max_conversation_duration' => 3600,
        ];
        Http::jsonRaw(200, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function respondConnectEmpty(): void
    {
        $payload = [
            'fetch_after_attempt' => false,
            'destination' => ['numbers' => []],
        ];
        Http::jsonRaw(200, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,mixed> $cfg */
    private static function authorize(array $cfg): bool
    {
        $secret = trim((string) ($cfg['exotelIvrWebhookSecret'] ?? ''));
        if ($secret === '') {
            return true;
        }
        $got = isset($_GET['secret']) ? trim((string) $_GET['secret']) : '';

        return hash_equals($secret, $got);
    }

    private static function ownerToE164(string $phoneRaw, string $defaultIsd): ?string
    {
        $d = preg_replace('/\D/', '', $phoneRaw) ?? '';
        if ($d === '') {
            return null;
        }
        $cc = preg_replace('/\D/', '', $defaultIsd) ?? '91';
        if ($cc === '') {
            $cc = '91';
        }
        if ($cc === '91') {
            if (strlen($d) === 10) {
                return '+91' . $d;
            }
            if (strlen($d) === 12 && str_starts_with($d, '91')) {
                return '+' . $d;
            }
            if (strlen($d) === 11 && str_starts_with($d, '0')) {
                return '+91' . substr($d, 1);
            }
        }

        if (strlen($d) >= 10 && strlen($d) <= 15) {
            return '+' . $d;
        }

        return null;
    }
}
