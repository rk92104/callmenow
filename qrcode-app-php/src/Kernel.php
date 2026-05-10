<?php

declare(strict_types=1);

namespace QrApp;

use PDO;
use QrApp\Handlers\ExotelIvrHandler;
use QrApp\Handlers\InventoryHandler;
use QrApp\Handlers\PaymentHandler;
use QrApp\Handlers\PersonHandler;
use QrApp\Handlers\QrHandler;

final class Kernel
{
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $path === '' ? '/' : $path;
        $path = self::normalizeRequestPath($path);

        if ($method === 'OPTIONS') {
            Http::corsPreflight();
            return;
        }

        Http::corsHeaders();

        try {
            $cfg = Config::load();

            if (($method === 'GET' || $method === 'HEAD') && ($path === '/api/health' || $path === '/api/ping')) {
                self::health($cfg);
                return;
            }

            $pdo = Db::pdo();
            $this->dispatch($pdo, $cfg, $method, $path);
        } catch (\PDOException $e) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'message' => 'Database connection failed',
                'sqlstate' => $e->errorInfo[0] ?? null,
                'driver_code' => isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null,
                'hint' => 'Check MYSQL_* in public_html/api/config.local.php (or hPanel env). Use MYSQL_HOST=localhost, exact database and user names from hPanel, correct password. User must be assigned to that database.',
                'detail' => self::debugDetail($e),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'message' => 'Server error',
                'detail' => self::debugDetail($e),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    private static function debugDetail(\Throwable $e): ?string
    {
        return Config::isDebug() ? $e->getMessage() : null;
    }

    /** Rewrite quirks: /api/public/api/... → /api/... */
    private static function normalizeRequestPath(string $path): string
    {
        if (str_starts_with($path, '/api/public/')) {
            return '/api/' . substr($path, strlen('/api/public/'));
        }

        return $path;
    }

    /** @param array<string,mixed> $cfg */
    private static function health(array $cfg): void
    {
        unset($cfg);
        $debugOn = Config::isDebug();

        $localPath = dirname(__DIR__) . '/config.local.php';
        $body = [
            'ok' => true,
            'php' => PHP_VERSION,
            'config_local_php' => is_file($localPath),
            'database' => 'unknown',
            'table_persons' => false,
        ];

        try {
            $pdo = Db::pdo();
            $pdo->query('SELECT 1')->fetchColumn();
            $body['database'] = 'connected';
            $body['table_persons'] = (bool) $pdo->query("SHOW TABLES LIKE 'persons'")->fetch();
            if (!$body['table_persons']) {
                $body['ok'] = false;
                $body['hint'] = 'Import qrcode-app-php/database/phpmyadmin_tables_only.sql in phpMyAdmin.';
            }
            Http::json($body['ok'] ? 200 : 503, $body);
        } catch (\Throwable $e) {
            $body['ok'] = false;
            $body['database'] = 'error';
            if ($e instanceof \PDOException) {
                $body['sqlstate'] = $e->errorInfo[0] ?? null;
                $body['driver_code'] = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
            }
            if ($debugOn) {
                $body['error'] = $e->getMessage();
            }
            $body['hint'] = 'Fix MYSQL_* in config.local.php; Hostinger uses localhost + full DB/user names (u…_qrcode, u…_user). Link user to DB in hPanel → Databases.';
            Http::json(503, $body);
        }
    }

    /**
     * @param array<string,mixed> $cfg
     */
    private function dispatch(PDO $pdo, array $cfg, string $method, string $path): void
    {
        if ($method === 'GET' && $path === '/api/persons') {
            PersonHandler::listAll($pdo, $cfg);
            return;
        }
        if ($method === 'POST' && $path === '/api/persons') {
            PersonHandler::create($pdo, $cfg);
            return;
        }
        if (preg_match('#^/api/persons/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'GET') {
                PersonHandler::getById($pdo, $cfg, $id);
                return;
            }
            if ($method === 'PUT') {
                PersonHandler::update($pdo, $cfg, $id);
                return;
            }
            if ($method === 'DELETE') {
                PersonHandler::delete($pdo, $cfg, $id);
                return;
            }
        }
        if (preg_match('#^/api/persons/(\d+)/qrcode-base64$#', $path, $m) && $method === 'GET') {
            PersonHandler::qrcodeBase64($pdo, $cfg, (int) $m[1], self::intQuery('modulePixels'));
            return;
        }
        if (preg_match('#^/api/persons/(\d+)/qrcode$#', $path, $m) && $method === 'GET') {
            PersonHandler::qrcodePng($pdo, $cfg, (int) $m[1], self::intQuery('modulePixels'));
            return;
        }

        if ($method === 'GET' && $path === '/api/payments/razorpay/config') {
            PaymentHandler::razorpayPublicConfig($cfg);
            return;
        }
        if ($method === 'POST' && $path === '/api/payments/razorpay/order') {
            PaymentHandler::razorpayCreateOrder($pdo, $cfg);
            return;
        }

        if ($method === 'POST' && $path === '/api/qr/marketing/leads') {
            QrHandler::marketingLead($pdo, $cfg);
            return;
        }
        if (preg_match('#^/api/qr/([^/]+)/scan$#', $path, $m) && $method === 'GET') {
            QrHandler::scan($pdo, $cfg, rawurldecode($m[1]));
            return;
        }
        if (preg_match('#^/api/qr/([^/]+)/activate$#', $path, $m) && $method === 'POST') {
            QrHandler::activate($pdo, $cfg, rawurldecode($m[1]));
            return;
        }
        if (preg_match('#^/api/qr/([^/]+)/exotel/connect-owner$#', $path, $m) && $method === 'POST') {
            QrHandler::exotelConnectOwner($pdo, $cfg, rawurldecode($m[1]));
            return;
        }
        if ($method === 'GET' && $path === '/api/exotel/ivr/gather') {
            ExotelIvrHandler::gather($cfg);
            return;
        }
        if ($method === 'GET' && $path === '/api/exotel/ivr/connect') {
            ExotelIvrHandler::connect($pdo, $cfg);
            return;
        }
        if (preg_match('#^/api/qr/([^/]+)/image$#', $path, $m) && $method === 'GET') {
            QrHandler::qrImage($pdo, $cfg, rawurldecode($m[1]), self::intQuery('modulePixels'), self::strQuery('embed'));
            return;
        }
        if (preg_match('#^/api/qr/by-owner/(\d+)/image$#', $path, $m) && $method === 'GET') {
            QrHandler::qrImageForOwner($pdo, $cfg, (int) $m[1], self::intQuery('modulePixels'));
            return;
        }
        if (preg_match('#^/api/qr/by-owner/(\d+)/base64$#', $path, $m) && $method === 'GET') {
            QrHandler::qrBase64ForOwner($pdo, $cfg, (int) $m[1], self::intQuery('modulePixels'));
            return;
        }

        if ($method === 'GET' && $path === '/api/inventory/qr') {
            InventoryHandler::listQr($pdo, $cfg);
            return;
        }
        if ($method === 'GET' && $path === '/api/inventory/qr/marketing-leads') {
            InventoryHandler::listMarketingLeads($pdo, $cfg);
            return;
        }
        if ($method === 'POST' && $path === '/api/inventory/qr/generate') {
            InventoryHandler::generate($pdo, $cfg);
            return;
        }
        if (preg_match('#^/api/inventory/qr/print/label/([^/]+)$#', $path, $m) && $method === 'GET') {
            InventoryHandler::printLabel($pdo, $cfg, rawurldecode($m[1]));
            return;
        }
        if ($method === 'POST' && $path === '/api/inventory/qr/print/batch') {
            InventoryHandler::printBatch($pdo, $cfg);
            return;
        }
        if ($method === 'POST' && $path === '/api/inventory/qr/delete') {
            InventoryHandler::deleteSelected($pdo, $cfg);
            return;
        }
        if ($method === 'POST' && $path === '/api/inventory/qr/delete-all') {
            InventoryHandler::deleteAll($pdo, $cfg);
            return;
        }

        Http::notFound();
    }

    private static function intQuery(string $name): ?int
    {
        if (!isset($_GET[$name]) || $_GET[$name] === '') {
            return null;
        }

        return (int) $_GET[$name];
    }

    private static function strQuery(string $name): ?string
    {
        if (!isset($_GET[$name])) {
            return null;
        }

        return (string) $_GET[$name];
    }
}
