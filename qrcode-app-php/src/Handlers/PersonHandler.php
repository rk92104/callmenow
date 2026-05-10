<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Http;
use QrApp\PersonMapper;
use QrApp\PhoneLineType;
use QrApp\QrStickerStatus;
use QrApp\Services\IvrAccessCode;
use QrApp\Services\QrPngRenderer;
use QrApp\Services\QrPublicIdGenerator;
use QrApp\Time;
use QrApp\Validation;

final class PersonHandler
{
    /** @param array<string,mixed> $cfg */
    public static function listAll(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $stmt = $pdo->query('SELECT * FROM persons ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
        $out = array_map(static fn (array $r) => PersonMapper::toApi($r), $rows);
        Http::json(200, $out);
    }

    /** @param array<string,mixed> $cfg */
    public static function getById(PDO $pdo, array $cfg, int $id): void
    {
        unset($cfg);
        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) {
            Http::notFound('Person not found');
            return;
        }
        Http::json(200, PersonMapper::toApi($r));
    }

    /** @param array<string,mixed> $cfg */
    public static function create(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }
        $errors = Validation::validatePersonPayload($dto, true);
        if ($errors !== []) {
            Http::json(400, ['errors' => $errors]);
            return;
        }

        $now = Time::utcNowSql();
        $reg = Validation::normalizeVehicleRegistration((string) ($dto['vehicleRegistration'] ?? ''));
        $stmt = $pdo->prepare(
            'INSERT INTO persons (name, phone_number, phone_number_type, email, address, father_name, vehicle_registration, emergency_contact_phone, emergency_contact_phone_type, payment_completed, payment_reference, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            trim((string) $dto['name']),
            trim((string) $dto['phoneNumber']),
            PhoneLineType::normalize($dto['phoneNumberType'] ?? null),
            trim((string) $dto['email']),
            trim((string) $dto['address']),
            trim((string) $dto['fatherName']),
            $reg,
            trim((string) ($dto['emergencyContactPhone'] ?? '')),
            PhoneLineType::normalize($dto['emergencyContactPhoneType'] ?? null),
            0,
            null,
            $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        Http::json(201, PersonMapper::toApi($r));
    }

    /** @param array<string,mixed> $cfg */
    public static function update(PDO $pdo, array $cfg, int $id): void
    {
        unset($cfg);
        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }
        $errors = Validation::validatePersonPayload($dto, true);
        if ($errors !== []) {
            Http::json(400, ['errors' => $errors]);
            return;
        }

        $stmt = $pdo->prepare('SELECT id FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Http::notFound('Person not found');
            return;
        }

        $reg = Validation::normalizeVehicleRegistration((string) ($dto['vehicleRegistration'] ?? ''));
        $now = Time::utcNowSql();
        $stmt = $pdo->prepare(
            'UPDATE persons SET name=?, phone_number=?, phone_number_type=?, email=?, address=?, father_name=?, vehicle_registration=?, emergency_contact_phone=?, emergency_contact_phone_type=?, updated_at=? WHERE id=?'
        );
        $stmt->execute([
            trim((string) $dto['name']),
            trim((string) $dto['phoneNumber']),
            PhoneLineType::normalize($dto['phoneNumberType'] ?? null),
            trim((string) $dto['email']),
            trim((string) $dto['address']),
            trim((string) $dto['fatherName']),
            $reg,
            trim((string) ($dto['emergencyContactPhone'] ?? '')),
            PhoneLineType::normalize($dto['emergencyContactPhoneType'] ?? null),
            $now,
            $id,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        Http::json(200, PersonMapper::toApi($stmt->fetch()));
    }

    /** @param array<string,mixed> $cfg */
    public static function delete(PDO $pdo, array $cfg, int $id): void
    {
        unset($cfg);
        $stmt = $pdo->prepare('DELETE FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Http::notFound('Person not found');
            return;
        }
        Http::json(200, ['message' => 'Person deleted successfully']);
    }

    /** @param array<string,mixed> $cfg */
    public static function qrcodeBase64(PDO $pdo, array $cfg, int $id, ?int $modulePixels): void
    {
        $stmt = $pdo->prepare('SELECT id FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Http::notFound('Person not found');
            return;
        }
        $sticker = self::getOrCreateActiveSticker($pdo, $cfg, $id);
        if ($sticker === null) {
            Http::notFound('Could not create QR for this owner.');
            return;
        }
        $url = $cfg['publicBaseUrl'] . '/q/' . rawurlencode($sticker['public_id']);
        $dataUri = QrPngRenderer::dataUriForImg($url, $modulePixels ?? 20);
        Http::json(200, [
            'qrCodeImage' => $dataUri,
            'qrText' => $url,
            'publicId' => $sticker['public_id'],
        ]);
    }

    /** @param array<string,mixed> $cfg */
    public static function qrcodePng(PDO $pdo, array $cfg, int $id, ?int $modulePixels): void
    {
        $stmt = $pdo->prepare('SELECT id FROM persons WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Http::notFound('Person not found');
            return;
        }
        $sticker = self::getOrCreateActiveSticker($pdo, $cfg, $id);
        if ($sticker === null) {
            Http::notFound('Could not create QR for this owner.');
            return;
        }
        $url = $cfg['publicBaseUrl'] . '/q/' . rawurlencode($sticker['public_id']);
        $px = $modulePixels ?? 20;
        $png = QrPngRenderer::tryPng($url, $px);
        if ($png !== null) {
            Http::png($png);
            return;
        }
        Http::svg(QrPngRenderer::svg($url, $px));
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>|null
     */
    private static function getOrCreateActiveSticker(PDO $pdo, array $cfg, int $personId): ?array
    {
        unset($cfg);
        $stmt = $pdo->prepare(
            'SELECT * FROM qr_stickers WHERE person_id = ? AND status = ? ORDER BY activated_at DESC LIMIT 1'
        );
        $stmt->execute([$personId, QrStickerStatus::ACTIVE]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $stmt = $pdo->prepare('SELECT id FROM persons WHERE id = ?');
        $stmt->execute([$personId]);
        if (!$stmt->fetch()) {
            return null;
        }

        do {
            $publicId = QrPublicIdGenerator::createNext();
            $chk = $pdo->prepare('SELECT 1 FROM qr_stickers WHERE public_id = ?');
            $chk->execute([$publicId]);
        } while ($chk->fetch());

        $now = Time::utcNowSql();
        if (IvrAccessCode::columnExists($pdo)) {
            $ivr = IvrAccessCode::generateUnique($pdo);
            $ins = $pdo->prepare(
                'INSERT INTO qr_stickers (public_id, ivr_access_code, product_type, status, person_id, scan_count, unique_scanner_count, created_at, activated_at) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $publicId,
                $ivr,
                'CarSticker',
                QrStickerStatus::ACTIVE,
                $personId,
                0,
                0,
                $now,
                $now,
            ]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO qr_stickers (public_id, product_type, status, person_id, scan_count, unique_scanner_count, created_at, activated_at) VALUES (?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $publicId,
                'CarSticker',
                QrStickerStatus::ACTIVE,
                $personId,
                0,
                0,
                $now,
                $now,
            ]);
        }

        $stmt = $pdo->prepare('SELECT * FROM qr_stickers WHERE id = ?');
        $stmt->execute([(int) $pdo->lastInsertId()]);

        return $stmt->fetch() ?: null;
    }
}
