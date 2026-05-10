<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Http;
use QrApp\QrStickerStatus;
use QrApp\Services\IvrAccessCode;
use QrApp\Services\QrLabelHtmlBuilder;
use QrApp\Services\QrPublicIdGenerator;
use QrApp\Time;

final class InventoryHandler
{
    /** @param array<string,mixed> $cfg */
    public static function listQr(PDO $pdo, array $cfg): void
    {
        $base = $cfg['publicBaseUrl'];
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $allowedPageSizes = [5, 10, 20, 100, 500, 1000, 2000];
        $pageSizeIn = isset($_GET['pageSize']) ? (int) $_GET['pageSize'] : 20;
        $pageSize = in_array($pageSizeIn, $allowedPageSizes, true) ? $pageSizeIn : 20;
        $offset = ($page - 1) * $pageSize;

        $fromRaw = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
        $toRaw = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $status = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
        $from = self::parseDateTimeInput($fromRaw, false);
        $to = self::parseDateTimeInput($toRaw, true);

        $where = [];
        $params = [];
        if ($from !== null) {
            $where[] = 'q.created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where[] = 'q.created_at <= ?';
            $params[] = $to;
        }
        if ($search !== '') {
            $where[] = 'q.public_id LIKE ?';
            $params[] = '%' . strtoupper($search) . '%';
        }
        if ($status === 'active') {
            $where[] = 'q.status = ?';
            $params[] = QrStickerStatus::ACTIVE;
        } elseif ($status === 'unused') {
            $where[] = 'q.status = ?';
            $params[] = QrStickerStatus::UNUSED;
        }
        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

        $cnt = $pdo->prepare('SELECT COUNT(*) AS c FROM qr_stickers q' . $whereSql);
        $cnt->execute($params);
        $total = (int) ($cnt->fetch()['c'] ?? 0);

        $sql = 'SELECT q.*, p.name AS owner_name
                FROM qr_stickers q
                LEFT JOIN persons p ON p.id = q.person_id'
            . $whereSql
            . ' ORDER BY q.created_at DESC
                LIMIT ' . $pageSize . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $q) {
            $out[] = self::stickerToDto($q, $base);
        }
        Http::json(200, [
            'items' => $out,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    /** @param array<string,mixed> $cfg */
    public static function listMarketingLeads(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $take = isset($_GET['take']) ? (int) $_GET['take'] : 200;
        $take = max(1, min(1000, $take));
        $stmt = $pdo->prepare(
            'SELECT id, phone_normalized, qr_public_id, referral_code, source, created_at_utc FROM marketing_leads ORDER BY created_at_utc DESC LIMIT ' . $take
        );
        $stmt->execute();
        $raw = $stmt->fetchAll();
        $rows = [];
        foreach ($raw as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'phoneNormalized' => $r['phone_normalized'],
                'qrPublicId' => $r['qr_public_id'],
                'referralCode' => $r['referral_code'],
                'source' => $r['source'],
                'createdAtUtc' => Time::toIso($r['created_at_utc']) ?? $r['created_at_utc'],
            ];
        }
        Http::json(200, $rows);
    }

    /** @param array<string,mixed> $cfg */
    public static function generate(PDO $pdo, array $cfg): void
    {
        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }
        $count = isset($dto['count']) ? (int) $dto['count'] : 1;
        $count = max(1, min(500, $count));
        $productType = isset($dto['productType']) && trim((string) $dto['productType']) !== ''
            ? trim((string) $dto['productType'])
            : 'CarSticker';

        $base = $cfg['publicBaseUrl'];
        $now = Time::utcNowSql();
        $created = [];

        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                do {
                    $publicId = QrPublicIdGenerator::createNext();
                    $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE public_id = ?');
                    $chk->execute([$publicId]);
                } while ($chk->fetch());

                if (IvrAccessCode::columnExists($pdo)) {
                    $ivr = IvrAccessCode::generateUnique($pdo);
                    $ins = $pdo->prepare(
                        'INSERT INTO qr_stickers (public_id, ivr_access_code, product_type, status, scan_count, unique_scanner_count, created_at) VALUES (?,?,?,?,?,?,?)'
                    );
                    $ins->execute([$publicId, $ivr, $productType, QrStickerStatus::UNUSED, 0, 0, $now]);
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO qr_stickers (public_id, product_type, status, scan_count, unique_scanner_count, created_at) VALUES (?,?,?,?,?,?)'
                    );
                    $ins->execute([$publicId, $productType, QrStickerStatus::UNUSED, 0, 0, $now]);
                }
                $id = (int) $pdo->lastInsertId();
                $created[] = [
                    'id' => $id,
                    'public_id' => $publicId,
                    'product_type' => $productType,
                    'status' => QrStickerStatus::UNUSED,
                    'scan_count' => 0,
                    'unique_scanner_count' => 0,
                    'person_id' => null,
                    'owner_name' => null,
                    'created_at' => $now,
                    'activated_at' => null,
                ];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $out = array_map(static fn (array $q) => self::stickerToDto($q, $base), $created);
        Http::json(200, $out);
    }

    /** @param array<string,mixed> $cfg */
    public static function printLabel(PDO $pdo, array $cfg, string $publicIdRaw): void
    {
        $norm = strtoupper(trim($publicIdRaw));
        $embed = isset($_GET['embed']) && strcasecmp((string) $_GET['embed'], 'scan') === 0 ? 'scan' : 'activate';
        $stmt = $pdo->prepare(
            'SELECT q.*, p.name AS owner_name, p.vehicle_registration, p.emergency_contact_phone FROM qr_stickers q LEFT JOIN persons p ON p.id = q.person_id WHERE q.public_id = ?'
        );
        $stmt->execute([$norm]);
        $s = $stmt->fetch();
        if (!$s) {
            Http::notFound('QR not found.');
            return;
        }

        $rows = [[
            'publicId' => $s['public_id'],
            'vehicleRegistration' => $s['vehicle_registration'] ?? null,
            'emergencyPhone' => $s['emergency_contact_phone'] ?? null,
            'ownerName' => $s['owner_name'] ?? null,
        ]];
        $layout = isset($_GET['layout']) && strcasecmp((string) $_GET['layout'], 'vertical') === 0 ? 'vertical' : 'horizontal';
        $html = QrLabelHtmlBuilder::buildDocument($rows, $cfg['publicBaseUrl'], $embed, $layout);
        Http::textHtml(200, $html, true);
    }

    /** @param array<string,mixed> $cfg */
    public static function printBatch(PDO $pdo, array $cfg): void
    {
        $dto = Http::readJsonBody();
        if ($dto === null || empty($dto['publicIds']) || !is_array($dto['publicIds'])) {
            Http::badRequest('PublicIds required (max 500).');
            return;
        }
        $mode = isset($dto['embed']) && strcasecmp((string) $dto['embed'], 'scan') === 0 ? 'scan' : 'activate';
        $ids = [];
        foreach ($dto['publicIds'] as $p) {
            $x = strtoupper(trim((string) $p));
            if ($x !== '') {
                $ids[$x] = true;
            }
        }
        $idList = array_slice(array_keys($ids), 0, 500);
        if ($idList === []) {
            Http::badRequest('PublicIds required (max 500).');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "SELECT q.*, p.name AS owner_name, p.vehicle_registration, p.emergency_contact_phone FROM qr_stickers q LEFT JOIN persons p ON p.id = q.person_id WHERE q.public_id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($idList);
        $byId = [];
        while ($row = $stmt->fetch()) {
            $byId[$row['public_id']] = $row;
        }

        $labelRows = [];
        foreach ($idList as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $s = $byId[$id];
            $labelRows[] = [
                'publicId' => $s['public_id'],
                'vehicleRegistration' => $s['vehicle_registration'] ?? null,
                'emergencyPhone' => $s['emergency_contact_phone'] ?? null,
                'ownerName' => $s['owner_name'] ?? null,
            ];
        }

        if ($labelRows === []) {
            Http::badRequest('No stickers matched for the given ids.');
            return;
        }

        $layout = isset($dto['layout']) && strcasecmp((string) $dto['layout'], 'vertical') === 0 ? 'vertical' : 'horizontal';
        $html = QrLabelHtmlBuilder::buildDocument($labelRows, $cfg['publicBaseUrl'], $mode, $layout);
        Http::textHtml(200, $html, true);
    }

    /** @param array<string,mixed> $cfg */
    public static function deleteSelected(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $dto = Http::readJsonBody();
        if ($dto === null || empty($dto['publicIds']) || !is_array($dto['publicIds'])) {
            Http::badRequest('PublicIds required.');
            return;
        }

        $ids = [];
        foreach ($dto['publicIds'] as $p) {
            $x = strtoupper(trim((string) $p));
            if ($x !== '') {
                $ids[$x] = true;
            }
        }
        $idList = array_slice(array_keys($ids), 0, 5000);
        if ($idList === []) {
            Http::badRequest('PublicIds required.');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $pdo->prepare("DELETE FROM qr_stickers WHERE status = " . QrStickerStatus::UNUSED . " AND public_id IN ($placeholders)");
        $stmt->execute($idList);
        $deleted = $stmt->rowCount();

        Http::json(200, [
            'deletedCount' => $deleted,
            'message' => "Deleted {$deleted} sticker(s).",
        ]);
    }

    /** @param array<string,mixed> $cfg */
    public static function deleteAll(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $fromRaw = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
        $toRaw = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
        $from = self::parseDateTimeInput($fromRaw, false);
        $to = self::parseDateTimeInput($toRaw, true);

        $where = [];
        $params = [];
        if ($from !== null) {
            $where[] = 'created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where[] = 'created_at <= ?';
            $params[] = $to;
        }
        $whereSql = $where === [] ? '' : (' AND ' . implode(' AND ', $where));
        $stmt = $pdo->prepare('DELETE FROM qr_stickers WHERE status = ' . QrStickerStatus::UNUSED . $whereSql);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();

        Http::json(200, [
            'deletedCount' => $deleted,
            'message' => "Deleted {$deleted} sticker(s).",
        ]);
    }

    /**
     * @param array<string,mixed> $q
     * @return array<string,mixed>
     */
    private static function stickerToDto(array $q, string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        $pid = $q['public_id'];
        $enc = rawurlencode($pid);
        $status = (int) $q['status'] === QrStickerStatus::ACTIVE ? 'Active' : 'Unused';

        return [
            'id' => (int) $q['id'],
            'publicId' => $pid,
            'productType' => $q['product_type'],
            'status' => $status,
            'scanCount' => (int) $q['scan_count'],
            'ownerPersonId' => $q['person_id'] !== null ? (int) $q['person_id'] : null,
            'ownerName' => $q['owner_name'] ?? null,
            'paymentTransactionId' => isset($q['payment_transaction_id']) && $q['payment_transaction_id'] !== null && $q['payment_transaction_id'] !== ''
                ? (string) $q['payment_transaction_id']
                : null,
            'createdAt' => Time::toIso($q['created_at']) ?? $q['created_at'],
            'activatedAt' => $q['activated_at'] !== null ? (Time::toIso($q['activated_at']) ?? $q['activated_at']) : null,
            'activateUrl' => $base . '/activate/' . $enc,
            'scanUrl' => $base . '/q/' . $enc,
            'packagingQrImageApi' => '/api/qr/' . $enc . '/image?embed=activate&modulePixels=32',
            'stickerQrImageApi' => '/api/qr/' . $enc . '/image?embed=scan&modulePixels=32',
        ];
    }

    private static function parseDateTimeInput(string $raw, bool $isToDate): ?string
    {
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= $isToDate ? ' 23:59:59' : ' 00:00:00';
        } elseif (strlen($raw) === 16) {
            $raw .= ':00';
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw);
        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
