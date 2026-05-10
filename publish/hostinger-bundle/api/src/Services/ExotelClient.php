<?php

declare(strict_types=1);

namespace QrApp\Services;

final class ExotelClient
{
    /**
     * @param array<string,mixed> $cfg from Config::load()
     */
    public static function isConfigured(array $cfg): bool
    {
        $sid = trim((string) ($cfg['exotelAccountSid'] ?? ''));
        $key = trim((string) ($cfg['exotelApiKey'] ?? ''));
        $tok = trim((string) ($cfg['exotelApiToken'] ?? ''));
        $cid = trim((string) ($cfg['exotelCallerId'] ?? ''));

        return $sid !== '' && $key !== '' && $tok !== '' && $cid !== '';
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array{ok:bool, error:?string}
     */
    public static function connectTwoLeg(array $cfg, string $fromDigits, string $toDigits): array
    {
        if (!self::isConfigured($cfg)) {
            return ['ok' => false, 'error' => 'Exotel is not configured.'];
        }

        $host = trim((string) ($cfg['exotelSubdomain'] ?? 'api.exotel.com'));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = rtrim($host, '/');
        if ($host === '') {
            $host = 'api.exotel.com';
        }

        $sid = rawurlencode(trim((string) $cfg['exotelAccountSid']));
        $path = "/v1/Accounts/{$sid}/Calls/connect";
        $url = 'https://' . $host . $path;

        $from = self::formatForExotel($fromDigits, (string) ($cfg['exotelDefaultIsd'] ?? '91'));
        $to = self::formatForExotel($toDigits, (string) ($cfg['exotelDefaultIsd'] ?? '91'));
        $callerIdDigits = preg_replace('/\D/', '', (string) ($cfg['exotelCallerId'] ?? '')) ?? '';
        $caller = self::formatForExotel($callerIdDigits, (string) ($cfg['exotelDefaultIsd'] ?? '91'));
        if ($from === '' || $to === '' || $caller === '') {
            return ['ok' => false, 'error' => 'Invalid phone number or CallerId.'];
        }

        $callType = trim((string) ($cfg['exotelCallType'] ?? 'trans'));
        if ($callType === '') {
            $callType = 'trans';
        }

        $body = http_build_query([
            'From' => $from,
            'To' => $to,
            'CallerId' => $caller,
            'CallType' => $callType,
        ], '', '&', PHP_QUERY_RFC3986);

        $key = trim((string) $cfg['exotelApiKey']);
        $tok = trim((string) $cfg['exotelApiToken']);

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not reach Exotel.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_USERPWD => $key . ':' . $tok,
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            return ['ok' => false, 'error' => 'Could not reach Exotel.'];
        }

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'error' => null];
        }

        return ['ok' => false, 'error' => self::formatExotelFailure($code, (string) $resp)];
    }

    /**
     * Single customer leg: rings {@see $customerDigits} only, then connects them to an Exotel flow (ExoML app URL).
     * No call to a second phone number — see https://docs.exotel.com/voice-apis/outbound-call-to-connect-a-customer-to-an-app
     *
     * @param array<string,mixed> $cfg
     * @return array{ok:bool, error:?string}
     */
    public static function connectCustomerToFlow(array $cfg, string $customerDigits, string $exomlStartUrl): array
    {
        if (!self::isConfigured($cfg)) {
            return ['ok' => false, 'error' => 'Exotel is not configured.'];
        }

        $flowUrl = trim($exomlStartUrl);
        if ($flowUrl === '' || !self::looksLikeHttpUrl($flowUrl)) {
            return ['ok' => false, 'error' => 'EXOTEL_OWNER_ALERT_APP_URL must be a full ExoML app URL from the Exotel dashboard.'];
        }

        $host = trim((string) ($cfg['exotelSubdomain'] ?? 'api.exotel.com'));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = rtrim($host, '/');
        if ($host === '') {
            $host = 'api.exotel.com';
        }

        $sid = rawurlencode(trim((string) $cfg['exotelAccountSid']));
        $path = "/v1/Accounts/{$sid}/Calls/connect";
        $url = 'https://' . $host . $path;

        $from = self::formatForExotel($customerDigits, (string) ($cfg['exotelDefaultIsd'] ?? '91'));
        $callerIdDigits = preg_replace('/\D/', '', (string) ($cfg['exotelCallerId'] ?? '')) ?? '';
        $caller = self::formatForExotel($callerIdDigits, (string) ($cfg['exotelDefaultIsd'] ?? '91'));
        if ($from === '' || $caller === '') {
            return ['ok' => false, 'error' => 'Invalid phone number or CallerId.'];
        }

        $callType = trim((string) ($cfg['exotelCallType'] ?? 'trans'));
        if ($callType === '') {
            $callType = 'trans';
        }

        $body = http_build_query([
            'From' => $from,
            'CallerId' => $caller,
            'CallType' => $callType,
            'Url' => $flowUrl,
        ], '', '&', PHP_QUERY_RFC3986);

        $key = trim((string) $cfg['exotelApiKey']);
        $tok = trim((string) $cfg['exotelApiToken']);

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not reach Exotel.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_USERPWD => $key . ':' . $tok,
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            return ['ok' => false, 'error' => 'Could not reach Exotel.'];
        }

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'error' => null];
        }

        return ['ok' => false, 'error' => self::formatExotelFailure($code, (string) $resp)];
    }

    private static function looksLikeHttpUrl(string $s): bool
    {
        if (!str_starts_with(strtolower($s), 'http://') && !str_starts_with(strtolower($s), 'https://')) {
            return false;
        }

        return filter_var($s, FILTER_VALIDATE_URL) !== false;
    }

    private static function formatExotelFailure(int $httpCode, string $body): string
    {
        if (stripos($body, 'KYC') !== false) {
            return 'Exotel: complete account KYC in the Exotel dashboard before outbound calls work.';
        }

        if (preg_match('/<Message>([^<]+)<\/Message>/i', $body, $m)) {
            return 'Exotel: ' . html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $snippet = strlen($body) > 200 ? substr($body, 0, 200) . '…' : $body;

        return "Exotel HTTP {$httpCode}: {$snippet}";
    }

    private static function formatForExotel(string $digitsRaw, string $defaultIsd): string
    {
        $d = preg_replace('/\D/', '', $digitsRaw) ?? '';
        if ($d === '') {
            return '';
        }

        $cc = preg_replace('/\D/', '', $defaultIsd) ?? '';
        if ($cc === '') {
            $cc = '91';
        }

        if ($cc === '91') {
            if (strlen($d) === 10) {
                return '0' . $d;
            }
            if (strlen($d) === 11 && str_starts_with($d, '0')) {
                return $d;
            }
            if (strlen($d) === 12 && str_starts_with($d, '91')) {
                return '0' . substr($d, 2);
            }
        }

        $len = strlen($d);
        if ($len >= 10 && $len <= 15) {
            return $d;
        }

        return '';
    }
}
