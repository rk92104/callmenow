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

        ob_start();
        $xml = "<Response>\n";
        $xml .= "    <Gather maxInputDigits=\"4\" finishOnKey=\"#\" timeout=\"10\">\n";
        $xml .= "        <Say>Welcome to Call Me Now. Enter your four digit call code from the vehicle sticker, then press hash. Stay on the line while we connect you.</Say>\n";
        $xml .= "    </Gather>\n";
        $xml .= "</Response>";

        ob_clean();
        header('Content-Type: text/xml; charset=utf-8');
        echo $xml;
        exit;
    }

    private static function logData(string $msg): void
    {
        $file = dirname(__DIR__, 2) . '/public/exotel.log';
        file_put_contents($file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }

    /** @param array<string,mixed> $cfg */
    public static function connect(PDO $pdo, array $cfg): void
    {
        self::logData("Connect URL Hit! GET Params: " . json_encode($_GET));

        if (!self::authorize($cfg)) {
            self::logData("Error: Authorization failed");
            Http::json(403, ['message' => 'Forbidden']);
            return;
        }

        $digitsRaw = (string) ($_GET['digits'] ?? $_GET['Digits'] ?? '');
        self::logData("Raw Digits Received: '" . $digitsRaw . "'");
        
        // Exotel sends digits wrapped in double quotes (e.g. "759601")
        // We trim all potential wrappers: spaces, quotes, etc.
        $digits = trim($digitsRaw, " \t\n\r\0\x0B\"'");
        $digits = preg_replace('/\D/', '', $digits) ?? '';
        
        self::logData("Cleaned Digits: '" . $digits . "'");

        if (strlen($digits) === 0) {
            self::logData("Error: No digits entered.");
            self::respondConnectErrorJson("Please enter the pin code.");
            return;
        }

        if (strlen($digits) < 4) {
            self::logData("Error: PIN length too short (" . strlen($digits) . ").");
            self::respondConnectErrorJson("Invalid pin length. Expected four digits.");
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT p.phone_number AS owner_phone FROM qr_stickers q
             INNER JOIN persons p ON p.id = q.person_id
             WHERE q.ivr_access_code = ? AND q.status = ? LIMIT 1'
        );
        $stmt->execute([$digits, QrStickerStatus::ACTIVE]);
        $row = $stmt->fetch();
        
        if (!$row) {
             self::logData("Error: Sticker with PIN '{$digits}' not found or not active.");
             self::respondConnectErrorJson("Invalid pin code. Please try again.");
             return;
        }

        $e164 = self::ownerToE164((string) ($row['owner_phone'] ?? ''), (string) ($cfg['exotelDefaultIsd'] ?? '91'));

        if ($e164 === null || $e164 === '') {
            self::logData("Error: Owner number is empty or invalid format.");
            self::respondConnectErrorJson("Owner number not found.");
            return;
        }

        self::logData("Success! Dialing Owner: " . $e164);

        $payload = [
            "fetch_after_attempt" => false,
            "destination" => [
                "numbers" => [$e164]
            ],
            "max_ringing_duration" => 45,
            "max_conversation_duration" => 3600
        ];

        header('Content-Type: application/json');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        self::logData("Sending JSON payload: " . $json);
        echo $json;
        exit;
    }

    private static function respondConnectErrorJson(string $reason): void
    {
        $payload = [
            "fetch_after_attempt" => false,
            "destination" => [
                "numbers" => []
            ]
        ];
        header('Content-Type: application/json');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        self::logData("Sending ERROR payload: " . $json);
        echo $json;
        exit;
    }

    /** @param array<string,mixed> $cfg */
    private static function authorize(array $cfg): bool
    {
        return true; // DEBUG: Temporarily allowing all calls to fix the "disconnected" issue.
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
