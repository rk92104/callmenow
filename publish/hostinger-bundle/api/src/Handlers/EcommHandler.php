<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Http;
use QrApp\Time;

final class EcommHandler
{
    /**
     * @param array<string,mixed> $cfg
     */
    public static function book(PDO $pdo, array $cfg): void
    {
        $body = Http::jsonBody();

        if (empty($body['customerName']) || empty($body['customerPhone']) || empty($body['shippingAddress']) || empty($body['city']) || empty($body['pincode'])) {
            Http::json(400, ['message' => 'Missing required fields']);
        }

        $now = Time::utcNowStr();

        // Automatically assign a fresh, unused sticker from inventory
        $stmtSticker = $pdo->query('SELECT public_id FROM qr_stickers WHERE status = 0 AND person_id IS NULL LIMIT 1');
        $sticker = $stmtSticker->fetch(PDO::FETCH_ASSOC);
        $assignedPublicId = $sticker ? $sticker['public_id'] : null;

        if ($assignedPublicId) {
            $upd = $pdo->prepare('UPDATE qr_stickers SET product_type = ? WHERE public_id = ?');
            $upd->execute([$body['productId'] ?? 'single', $assignedPublicId]);
        }

        $stmt = $pdo->prepare('
            INSERT INTO `sticker_orders` (
                `customer_name`, `customer_phone`, `shipping_address`,
                `city`, `pincode`, `product_id`, `product_name`,
                `amount`, `status`, `assigned_public_id`, `created_at_utc`,
                `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`
            ) VALUES (
                :customerName, :customerPhone, :shippingAddress,
                :city, :pincode, :productId, :productName,
                :amount, :status, :assignedPublicId, :createdAtUtc,
                :razorpayOrderId, :razorpayPaymentId, :razorpaySignature
            )
        ');

        $stmt->execute([
            ':customerName' => $body['customerName'],
            ':customerPhone' => $body['customerPhone'],
            ':shippingAddress' => $body['shippingAddress'],
            ':city' => $body['city'],
            ':pincode' => $body['pincode'],
            ':productId' => $body['productId'] ?? 'single',
            ':productName' => $body['productName'] ?? 'Solo Pack',
            ':amount' => isset($body['amount']) ? (float)$body['amount'] : 0.0,
            ':status' => 'Pending',
            ':assignedPublicId' => $assignedPublicId,
            ':createdAtUtc' => $now,
            ':razorpayOrderId' => $body['razorpayOrderId'] ?? null,
            ':razorpayPaymentId' => $body['razorpayPaymentId'] ?? null,
            ':razorpaySignature' => $body['razorpaySignature'] ?? null,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        Http::json(200, [
            'message' => 'Order placed successfully',
            'orderId' => $orderId
        ]);
    }

    /**
     * @param array<string,mixed> $cfg
     */
    public static function orders(PDO $pdo, array $cfg): void
    {
        $stmt = $pdo->query('
            SELECT *
            FROM `sticker_orders`
            ORDER BY `created_at_utc` DESC
            LIMIT 100
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert snake_case back to camelCase for the frontend if needed
        $orders = [];
        foreach ($rows as $row) {
            $orders[] = [
                'id' => (int) $row['id'],
                'customerName' => $row['customer_name'],
                'customerPhone' => $row['customer_phone'],
                'shippingAddress' => $row['shipping_address'],
                'city' => $row['city'],
                'pincode' => $row['pincode'],
                'productId' => $row['product_id'],
                'productName' => $row['product_name'],
                'amount' => (float) $row['amount'],
                'status' => $row['status'],
                'assignedPublicId' => $row['assigned_public_id'] ?? null,
                'createdAtUtc' => $row['created_at_utc'],
                'razorpayOrderId' => $row['razorpay_order_id'] ?? null,
                'razorpayPaymentId' => $row['razorpay_payment_id'] ?? null,
                'razorpaySignature' => $row['razorpay_signature'] ?? null,
            ];
        }

        Http::json(200, $orders);
    }

    /**
     * @param array<string,mixed> $cfg
     */
    public static function updateStatus(PDO $pdo, array $cfg, int $id): void
    {
        $body = Http::jsonBody();
        if (empty($body['status'])) {
            Http::json(400, ['message' => 'Missing status']);
        }

        $stmt = $pdo->prepare('UPDATE `sticker_orders` SET `status` = :status WHERE `id` = :id');
        $stmt->execute([':status' => $body['status'], ':id' => $id]);

        Http::json(200, ['message' => 'Status updated']);
    }
}
