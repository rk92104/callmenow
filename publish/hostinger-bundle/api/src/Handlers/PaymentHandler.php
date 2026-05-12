<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Http;
use QrApp\QrStickerStatus;
use QrApp\Services\RazorpayClient;

final class PaymentHandler
{
    /** @param array<string,mixed> $cfg */
    public static function razorpayCreateOrder(PDO $pdo, array $cfg): void
    {
        $keyId = (string) ($cfg['razorpayKeyId'] ?? '');
        $secret = (string) ($cfg['razorpayKeySecret'] ?? '');
        if ($keyId === '' || $secret === '') {
            Http::json(503, ['message' => 'Online payment is not configured.']);
            return;
        }

        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }

        $ref = isset($dto['referralCode']) && is_string($dto['referralCode']) ? trim($dto['referralCode']) : '';

        $amountInr = isset($dto['amount']) ? (float) $dto['amount'] : 0.0;
        
        if ($amountInr > 0) {
            // Shop order
            $paise = (int) ($amountInr * 100);
            $receipt = substr(bin2hex(random_bytes(8)), 0, 20);
            $notes = [
                'type' => 'shop_order',
                'amount_inr' => (string) $amountInr,
            ];
            $inr = (int) $amountInr;
            $referralApplied = false;
        } else {
            // Sticker activation
            $publicIdRaw = isset($dto['publicId']) ? (string) $dto['publicId'] : '';
            $normalized = strtoupper(trim($publicIdRaw));
            if ($normalized === '') {
                Http::badRequest('publicId or amount is required.');
                return;
            }

            $stmt = $pdo->prepare('SELECT public_id, status FROM qr_stickers WHERE public_id = ?');
            $stmt->execute([$normalized]);
            $sticker = $stmt->fetch();
            if (!$sticker) {
                Http::notFound('QR not found.');
                return;
            }
            if ((int) $sticker['status'] !== QrStickerStatus::UNUSED) {
                Http::conflict('This QR is already activated.');
                return;
            }

            [$paise, $inr, $referralApplied] = self::computeActivationMoney($cfg, $ref);

            $receipt = substr(bin2hex(random_bytes(8)), 0, 20);
            $notes = [
                'public_id' => $normalized,
                'amount_paise' => (string) $paise,
                'referral_applied' => $referralApplied ? '1' : '0',
            ];
        }

        try {
            $order = RazorpayClient::createOrder($paise, 'INR', $receipt, $notes, $keyId, $secret);
        } catch (\Throwable $e) {
            Http::json(502, ['message' => 'Could not start payment. Try again.', 'detail' => $e->getMessage()]);
            return;
        }

        $id = (string) ($order['id'] ?? '');
        $amount = (int) ($order['amount'] ?? 0);
        if ($id === '' || $amount <= 0) {
            Http::json(502, ['message' => 'Invalid order from payment gateway.']);
            return;
        }

        Http::json(200, [
            'keyId' => $keyId,
            'orderId' => $id,
            'amount' => $amount,
            'currency' => (string) ($order['currency'] ?? 'INR'),
            'amountInr' => $inr,
            'referralApplied' => $referralApplied,
        ]);
    }

    /**
     * @return array{0:int,1:int,2:bool} paise, whole INR charged, referral applied
     */
    public static function computeActivationMoney(array $cfg, string $referralCodeInput): array
    {
        $stickerInr = (int) $cfg['stickerPriceInr'];
        $discount = (int) $cfg['referralDiscountInr'];
        $configRef = strtoupper(trim((string) $cfg['referralCode']));
        $in = strtoupper(trim($referralCodeInput));
        $applied = $in !== '' && $in === $configRef;
        $inr = $stickerInr - ($applied ? $discount : 0);
        if ($inr < 1) {
            $inr = 1;
        }
        $paise = $inr * 100;

        return [$paise, $inr, $applied];
    }

    /** @param array<string,mixed> $cfg */
    public static function razorpayPublicConfig(array $cfg): void
    {
        $keyId = (string) ($cfg['razorpayKeyId'] ?? '');
        $secret = (string) ($cfg['razorpayKeySecret'] ?? '');
        $enabled = $keyId !== '' && $secret !== '';

        Http::json(200, [
            'enabled' => $enabled,
            'keyId' => $enabled ? $keyId : null,
        ]);
    }
}
