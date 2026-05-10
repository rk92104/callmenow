<?php

declare(strict_types=1);

namespace QrApp\Services;

final class RazorpayClient
{
    private const BASE = 'https://api.razorpay.com';

    /**
     * @return array<string,mixed>
     */
    public static function createOrder(
        int $amountPaise,
        string $currency,
        string $receipt,
        array $notes,
        string $keyId,
        string $keySecret
    ): array {
        $payload = [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => $notes,
        ];

        return self::request('POST', '/v1/orders', $payload, $keyId, $keySecret);
    }

    /**
     * @return array<string,mixed>
     */
    public static function fetchPayment(string $paymentId, string $keyId, string $keySecret): array
    {
        return self::request('GET', '/v1/payments/' . rawurlencode($paymentId), null, $keyId, $keySecret);
    }

    /**
     * @return array<string,mixed>
     */
    public static function fetchOrder(string $orderId, string $keyId, string $keySecret): array
    {
        return self::request('GET', '/v1/orders/' . rawurlencode($orderId), null, $keyId, $keySecret);
    }

    public static function verifySignature(string $orderId, string $paymentId, string $signature, string $keySecret): bool
    {
        if ($orderId === '' || $paymentId === '' || $signature === '' || $keySecret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string,mixed>|null $jsonBody
     * @return array<string,mixed>
     */
    private static function request(string $method, string $path, ?array $jsonBody, string $keyId, string $keySecret): array
    {
        $url = self::BASE . $path;
        $auth = base64_encode($keyId . ':' . $keySecret);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('Razorpay: curl init failed.');
            }
            $headers = [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($jsonBody !== null && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method' => $method,
                    'header' => "Authorization: Basic {$auth}\r\nContent-Type: application/json\r\n",
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ];
            if ($jsonBody !== null && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
                $opts['http']['content'] = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $ctx = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $ctx);
            $code = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $line) {
                    if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                        $code = (int) $m[1];
                        break;
                    }
                }
            }
        }

        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Razorpay: empty response (HTTP ' . $code . ').');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Razorpay: invalid JSON response.');
        }

        if ($code >= 400) {
            $msg = isset($data['error']['description']) ? (string) $data['error']['description'] : 'Razorpay API error';
            throw new \RuntimeException($msg . ' (HTTP ' . $code . ')');
        }

        return $data;
    }
}
