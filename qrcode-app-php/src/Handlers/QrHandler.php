<?php

declare(strict_types=1);

namespace QrApp\Handlers;

use PDO;
use QrApp\Config;
use QrApp\Http;
use QrApp\PhoneLineType;
use QrApp\QrStickerStatus;
use QrApp\Services\ExotelClient;
use QrApp\Services\IvrAccessCode;
use QrApp\Services\QrPngRenderer;
use QrApp\Services\RazorpayClient;
use QrApp\Services\VisitorFingerprint;
use QrApp\Time;
use QrApp\Validation;

final class QrHandler
{
    /** @param array<string,mixed> $cfg */
    public static function marketingLead(PDO $pdo, array $cfg): void
    {
        unset($cfg);
        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }
        $phone = isset($dto['phone']) ? (string) $dto['phone'] : '';
        $digits = Validation::digitsOnly($phone);
        if (strlen($digits) < 8) {
            Http::badRequest('Enter a valid phone number.');
            return;
        }
        $pid = isset($dto['publicId']) && is_string($dto['publicId']) && trim($dto['publicId']) !== ''
            ? self::normalizePublicId($dto['publicId'])
            : null;
        $ref = isset($dto['referralCode']) && is_string($dto['referralCode']) && trim($dto['referralCode']) !== ''
            ? trim($dto['referralCode'])
            : null;

        $stmt = $pdo->prepare(
            'INSERT INTO marketing_leads (phone_normalized, qr_public_id, referral_code, source, created_at_utc) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$digits, $pid, $ref, 'scan_page_coupon', Time::utcNowSql()]);
        Http::json(200, ['message' => 'Lead saved.']);
    }

    /** @param array<string,mixed> $cfg */
    public static function scan(PDO $pdo, array $cfg, string $publicIdRaw): void
    {
        $normalized = self::normalizePublicId($publicIdRaw);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM qr_stickers WHERE public_id = ? FOR UPDATE');
            $stmt->execute([$normalized]);
            $sticker = $stmt->fetch();
            if (!$sticker) {
                $pdo->rollBack();
                Http::notFound('QR not found or invalid.');
                return;
            }

            $person = null;
            if (!empty($sticker['person_id'])) {
                $pstmt = $pdo->prepare('SELECT * FROM persons WHERE id = ?');
                $pstmt->execute([(int) $sticker['person_id']]);
                $person = $pstmt->fetch() ?: null;
            }

            $hash = VisitorFingerprint::compute($normalized, $ip, $ua);
            $snippet = strlen($ua) > 250 ? substr($ua, 0, 250) : $ua;
            $snippet = $snippet === '' ? null : $snippet;

            $seenStmt = $pdo->prepare(
                'SELECT 1 FROM qr_scan_events WHERE qr_sticker_id = ? AND visitor_hash = ? LIMIT 1'
            );
            $seenStmt->execute([(int) $sticker['id'], $hash]);
            $seenBefore = (bool) $seenStmt->fetch();

            $ins = $pdo->prepare(
                'INSERT INTO qr_scan_events (qr_sticker_id, visitor_hash, scanned_at_utc, user_agent_snippet) VALUES (?,?,?,?)'
            );
            $ins->execute([(int) $sticker['id'], $hash, Time::utcNowSql(), $snippet]);

            $u = $pdo->prepare(
                'UPDATE qr_stickers SET scan_count = scan_count + 1, unique_scanner_count = unique_scanner_count + ? WHERE id = ?'
            );
            $u->execute([$seenBefore ? 0 : 1, (int) $sticker['id']]);

            $pdo->commit();

            $sticker['scan_count'] = (int) $sticker['scan_count'] + 1;
            $sticker['unique_scanner_count'] = (int) $sticker['unique_scanner_count'] + ($seenBefore ? 0 : 1);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $base = $cfg['publicBaseUrl'];
        $scanPageUrl = $base . '/q/' . rawurlencode($sticker['public_id']);
        $activatePageUrl = $base . '/activate/' . rawurlencode($sticker['public_id']);
        $shareUrl = $scanPageUrl;

        $statusByte = (int) $sticker['status'];
        if ($statusByte === QrStickerStatus::UNUSED) {
            Http::json(200, self::unusedResponse($sticker, $cfg, $activatePageUrl, $scanPageUrl, $shareUrl));
            return;
        }

        if ($person === null) {
            Http::json(200, self::invalidResponse($sticker, $cfg, $shareUrl, $activatePageUrl, $scanPageUrl));
            return;
        }

        $tel = self::normalizeTel($person['phone_number']);
        $em = self::normalizeTel($person['emergency_contact_phone']);
        $reg = trim((string) $person['vehicle_registration']);
        $reg = $reg === '' ? null : Validation::normalizeVehicleRegistration($reg);

        $useExotel = ExotelClient::isConfigured($cfg) && $tel !== null;

        $ivrCode = $sticker['ivr_access_code'];
        $ivrEmCode = $sticker['ivr_emergency_access_code'];
        $ivrDid = trim((string) ($cfg['exotelIvrDid'] ?? ''));
        $ivrDialUri = $ivrDid !== '' ? self::telUriFromDid($ivrDid, $cfg['exotelDefaultIsd'] ?? '91') : null;

        $dialUriActive = ($ivrDialUri !== null && $ivrCode !== null) ? $ivrDialUri : ($tel === null ? null : 'tel:' . $tel);
        
        if ($useExotel && ($ivrCode === null || $ivrEmCode === null)) {
            $ivrCode = self::ensureStickerIvrAccessCode($pdo, (int)$sticker['id'], $sticker);
            // Re-fetch emergency code if needed (ensureStickerIvrAccessCode only does main)
            if ($sticker['ivr_emergency_access_code'] === null) {
               $ivrEmCode = IvrAccessCode::allocate($pdo);
               $pdo->prepare('UPDATE qr_stickers SET ivr_emergency_access_code = ? WHERE id = ?')
                   ->execute([$ivrEmCode, $sticker['id']]);
               $sticker['ivr_emergency_access_code'] = $ivrEmCode;
            }
        }

        $maskingActive = $useExotel 
            ? 'Fastest: use Open dialer — your phone app opens right away and you reach the owner after entering your 4-digit code. Below: Exotel API can ring your phone if you prefer not to dial.'
            : 'Tap Call owner to place a direct call from your phone.';

        Http::json(200, [
            'publicId' => $sticker['public_id'],
            'status' => 'active',
            'productType' => $sticker['product_type'],
            'productLabel' => self::productLabel($sticker['product_type']),
            'scanCount' => (int) $sticker['scan_count'],
            'uniqueScannerCount' => (int) $sticker['unique_scanner_count'],
            'activatePageUrl' => $activatePageUrl,
            'scanPageUrl' => $scanPageUrl,
            'vehicleRegistration' => Validation::normalizeVehicleRegistration((string) $person['vehicle_registration']),
            'emergencyDialUri' => ($ivrDialUri !== null && $ivrEmCode !== null) ? $ivrDialUri : ($em === null ? null : 'tel:' . $em),
            'emergencyContactMasked' => self::maskPhoneTail($person['emergency_contact_phone']),
            'ownerPhoneMasked' => self::maskPhoneTail($person['phone_number']),
            'primaryPhoneType' => PhoneLineType::normalize($person['phone_number_type']),
            'emergencyPhoneType' => PhoneLineType::normalize($person['emergency_contact_phone_type']),
            'headline' => 'Need the vehicle owner?',
            'subtitle' => 'Connect privately. Your number is not shown to the owner from this page.',
            'dialUri' => $dialUriActive,
            'ownerConnectViaExotel' => false,
            'exotelIvrDialUri' => $ivrDialUri,
            'ivrAccessCode' => $sticker['ivr_access_code'],
            'ivrEmergencyAccessCode' => $sticker['ivr_emergency_access_code'],
            'ownerNumberHiddenOnPage' => true,
            'maskingNote' => $maskingActive,
            'trustedOwnersLine' => $cfg['trustedOwnersLine'],
            'regionTagline' => $cfg['regionTagline'],
            'referralCode' => $cfg['referralCode'],
            'referralDiscountInr' => $cfg['referralDiscountInr'],
            'stickerPriceInr' => $cfg['stickerPriceInr'],
            'localizedCityLine' => $cfg['localizedCityLine'],
            'productVariants' => self::productVariants(),
            'sharePageUrl' => $shareUrl,
            ...self::razorpayScanFields($cfg),
        ]);
    }

    /** @param array<string,mixed> $cfg */
    public static function activateFree(PDO $pdo, array $cfg, string $publicIdRaw): void
    {
        $dto = Http::readJsonBody();
        if (!$dto) {
            Http::badRequest('Invalid body.');
            return;
        }

        $normalized = self::normalizePublicId($publicIdRaw);
        $stmt = $pdo->prepare('SELECT * FROM qr_stickers WHERE public_id = ? LIMIT 1');
        $stmt->execute([$normalized]);
        $sticker = $stmt->fetch();

        if (!$sticker) {
            Http::notFound('QR not found.');
            return;
        }
        if ((int) $sticker['status'] !== QrStickerStatus::UNUSED) {
            Http::json(409, ['message' => 'This QR is already activated.']);
            return;
        }

        $payRef = 'Admin-Free-' . date('YmdHis');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO persons (name, phone_number, phone_number_type, email, address, father_name, vehicle_registration, emergency_contact_phone, emergency_contact_phone_type, payment_completed, payment_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
            $stmt->execute([
                $dto['name'],
                $dto['phoneNumber'],
                PhoneLineType::normalize($dto['phoneNumberType']),
                $dto['email'],
                $dto['address'],
                $dto['fatherName'],
                Validation::normalizeVehicleRegistration($dto['vehicleRegistration']),
                trim((string) $dto['emergencyContactPhone']),
                PhoneLineType::normalize($dto['emergencyContactPhoneType']),
                $payRef,
                gmdate('Y-m-d H:i:s')
            ]);
            $personId = (int) $pdo->lastInsertId();

            $ivrCode = IvrAccessCode::allocate($pdo);
            $ivrEmCode = IvrAccessCode::allocate($pdo);

            $stmt = $pdo->prepare('UPDATE qr_stickers SET status = ?, person_id = ?, activated_at = ?, payment_transaction_id = ?, ivr_access_code = ?, ivr_emergency_access_code = ? WHERE id = ?');
            $stmt->execute([
                QrStickerStatus::ACTIVE,
                $personId,
                gmdate('Y-m-d H:i:s'),
                $payRef,
                $ivrCode,
                $ivrEmCode,
                $sticker['id']
            ]);

            $pdo->commit();
            Http::json(200, ['message' => 'Free activation successful.', 'publicId' => $normalized]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $cfg */
    public static function exotelConnectOwner(PDO $pdo, array $cfg, string $publicIdRaw): void
    {
        if (!ExotelClient::isConfigured($cfg)) {
            Http::json(503, ['message' => 'Exotel is not configured.']);
            return;
        }

        $normalized = self::normalizePublicId($publicIdRaw);
        $stmt = $pdo->prepare(
            'SELECT q.*, p.phone_number AS owner_phone, p.emergency_contact_phone FROM qr_stickers q
             LEFT JOIN persons p ON p.id = q.person_id
             WHERE q.public_id = ? LIMIT 1'
        );
        $stmt->execute([$normalized]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['status'] !== QrStickerStatus::ACTIVE) {
            Http::notFound('QR not found or not active.');
            return;
        }

        $dto = Http::readJsonBody();
        if ($dto === null || !isset($dto['fromPhone']) || trim((string) $dto['fromPhone']) === '') {
            Http::badRequest('Enter your phone number.');
            return;
        }

        $isEmerg = isset($_GET['emergency']) && $_GET['emergency'] === 'true';
        $targetDigits = $isEmerg ? self::normalizeTel($row['emergency_contact_phone'] ?? '') : self::normalizeTel($row['owner_phone'] ?? '');
        
        if ($targetDigits === null) {
            Http::badRequest($isEmerg ? 'No emergency phone on file.' : 'Owner has no phone on file.');
            return;
        }

        $fromDigits = Validation::digitsOnly((string) $dto['fromPhone']);
        if (strlen($fromDigits) < 8) {
            Http::badRequest('Enter a valid mobile number.');
            return;
        }

        $pdo->prepare('INSERT INTO active_call_mappings (caller_phone_normalized, target_owner_phone, expiry_utc) VALUES (?, ?, ?)')
            ->execute([$fromDigits, $targetDigits, gmdate('Y-m-d H:i:s', time() + 900)]);

        $r = ExotelClient::connectTwoLeg($cfg, $fromDigits, $targetDigits);
        if (!$r['ok']) {
            Http::json(502, ['message' => $r['error'] ?? 'Call failed.']);
            return;
        }

        Http::json(200, ['message' => 'Request sent. Your phone will ring shortly.']);
    }

    /** @param array<string,mixed> $sticker */
    /** @param array<string,mixed> $cfg */
    private static function unusedResponse(array $sticker, array $cfg, string $activatePageUrl, string $scanPageUrl, string $shareUrl): array
    {
        return [
            'publicId' => $sticker['public_id'],
            'status' => 'unused',
            'productType' => $sticker['product_type'],
            'productLabel' => self::productLabel($sticker['product_type']),
            'scanCount' => (int) $sticker['scan_count'],
            'uniqueScannerCount' => (int) $sticker['unique_scanner_count'],
            'activatePageUrl' => $activatePageUrl,
            'scanPageUrl' => $scanPageUrl,
            'headline' => 'This CallMeNow tag is not activated yet',
            'subtitle' => 'Owner completes setup and payment after purchase. Packaging QR opens activate; public sticker uses /q after go-live.',
            'maskingNote' => 'Once active, callers reach the owner without seeing their private number on this page.',
            'trustedOwnersLine' => $cfg['trustedOwnersLine'],
            'regionTagline' => $cfg['regionTagline'],
            'referralCode' => $cfg['referralCode'],
            'referralDiscountInr' => $cfg['referralDiscountInr'],
            'stickerPriceInr' => $cfg['stickerPriceInr'],
            'localizedCityLine' => $cfg['localizedCityLine'],
            'productVariants' => self::productVariants(),
            'sharePageUrl' => $shareUrl,
            'dialUri' => null,
            'vehicleRegistration' => null,
            'emergencyDialUri' => null,
            'emergencyContactMasked' => null,
            'primaryPhoneType' => null,
            'emergencyPhoneType' => null,
            'ownerNumberHiddenOnPage' => false,
            ...self::razorpayScanFields($cfg),
        ];
    }

    /** @param array<string,mixed> $cfg */
    /** @return array{razorpayEnabled:bool,razorpayKeyId:?string} */
    private static function razorpayScanFields(array $cfg): array
    {
        $kid = trim((string) ($cfg['razorpayKeyId'] ?? ''));
        $sec = trim((string) ($cfg['razorpayKeySecret'] ?? ''));
        $on = $kid !== '' && $sec !== '';

        return [
            'razorpayEnabled' => $on,
            'razorpayKeyId' => $on ? $kid : null,
        ];
    }

    /** @param array<string,mixed> $sticker */
    /** @param array<string,mixed> $cfg */
    private static function invalidResponse(array $sticker, array $cfg, string $shareUrl, string $activatePageUrl, string $scanPageUrl): array
    {
        return [
            'publicId' => $sticker['public_id'],
            'status' => 'invalid',
            'productType' => $sticker['product_type'],
            'productLabel' => self::productLabel($sticker['product_type']),
            'scanCount' => (int) $sticker['scan_count'],
            'uniqueScannerCount' => (int) $sticker['unique_scanner_count'],
            'activatePageUrl' => $activatePageUrl,
            'scanPageUrl' => $scanPageUrl,
            'headline' => 'This tag cannot be used right now',
            'subtitle' => 'Owner record is missing. Contact support.',
            'maskingNote' => '',
            'trustedOwnersLine' => $cfg['trustedOwnersLine'],
            'regionTagline' => $cfg['regionTagline'],
            'referralCode' => $cfg['referralCode'],
            'referralDiscountInr' => $cfg['referralDiscountInr'],
            'stickerPriceInr' => $cfg['stickerPriceInr'],
            'localizedCityLine' => $cfg['localizedCityLine'],
            'productVariants' => self::productVariants(),
            'sharePageUrl' => $shareUrl,
            'vehicleRegistration' => null,
            'emergencyDialUri' => null,
            'emergencyContactMasked' => null,
            'primaryPhoneType' => null,
            'emergencyPhoneType' => null,
            'dialUri' => null,
            'ownerNumberHiddenOnPage' => false,
            ...self::razorpayScanFields($cfg),
        ];
    }

    /** @param array<string,mixed> $cfg */
    public static function activate(PDO $pdo, array $cfg, string $publicIdRaw): void
    {
        $normalized = self::normalizePublicId($publicIdRaw);
        $dto = Http::readJsonBody();
        if ($dto === null) {
            Http::badRequest('Invalid JSON');
            return;
        }

        $errors = Validation::validateActivatePayload($dto);
        if ($errors !== []) {
            Http::json(400, ['errors' => $errors]);
            return;
        }

        $keyId = trim((string) ($cfg['razorpayKeyId'] ?? ''));
        $secret = trim((string) ($cfg['razorpayKeySecret'] ?? ''));
        $rzpOn = $keyId !== '' && $secret !== '';

        $payDone = !empty($dto['paymentCompleted']);
        $payRef = isset($dto['paymentReference']) ? trim((string) $dto['paymentReference']) : '';

        $rzpOrder = trim((string) ($dto['razorpayOrderId'] ?? ''));
        $rzpPay = trim((string) ($dto['razorpayPaymentId'] ?? ''));
        $rzpSig = trim((string) ($dto['razorpaySignature'] ?? ''));
        $hasRzp = $rzpOrder !== '' && $rzpPay !== '' && $rzpSig !== '';

        if ($rzpOn && $payDone && !$hasRzp) {
            Http::badRequest('Complete payment with Razorpay, or clear “payment received” and enter an offline reference.');
            return;
        }

        if ($rzpOn && $payDone && $hasRzp) {
            if (!RazorpayClient::verifySignature($rzpOrder, $rzpPay, $rzpSig, $secret)) {
                Http::badRequest('Invalid Razorpay payment signature.');
                return;
            }
            try {
                $payJson = RazorpayClient::fetchPayment($rzpPay, $keyId, $secret);
                $ordJson = RazorpayClient::fetchOrder($rzpOrder, $keyId, $secret);
            } catch (\Throwable $e) {
                Http::json(502, [
                    'message' => 'Could not confirm payment with Razorpay.',
                    'detail' => Config::isDebug() ? $e->getMessage() : null,
                ]);
                return;
            }
            $status = (string) ($payJson['status'] ?? '');
            if ($status !== 'captured' && $status !== 'authorized') {
                Http::badRequest('Payment is not completed.');
                return;
            }
            if ((string) ($payJson['order_id'] ?? '') !== $rzpOrder) {
                Http::badRequest('Payment does not match this order.');
                return;
            }
            $notes = $ordJson['notes'] ?? [];
            if (!is_array($notes)) {
                Http::badRequest('Invalid order metadata.');
                return;
            }
            $notePid = isset($notes['public_id']) ? strtoupper(trim((string) $notes['public_id'])) : '';
            if ($notePid !== $normalized) {
                Http::badRequest('This payment is for a different tag.');
                return;
            }
            $expectedPaise = isset($notes['amount_paise']) ? (int) (string) $notes['amount_paise'] : 0;
            $orderAmount = (int) ($ordJson['amount'] ?? 0);
            if ($expectedPaise <= 0 || $orderAmount !== $expectedPaise) {
                Http::badRequest('Order amount mismatch.');
                return;
            }
            if ((int) ($payJson['amount'] ?? 0) !== $orderAmount) {
                Http::badRequest('Paid amount mismatch.');
                return;
            }
            $dup = $pdo->prepare('SELECT id FROM persons WHERE payment_reference = ? LIMIT 1');
            $dup->execute([$rzpPay]);
            if ($dup->fetch()) {
                Http::conflict('This payment was already used.');
                return;
            }
            $payRef = $rzpPay;
            $payDone = true;
        }

        $stmt = $pdo->prepare('SELECT * FROM qr_stickers WHERE public_id = ? FOR UPDATE');
        $pdo->beginTransaction();
        try {
            $stmt->execute([$normalized]);
            $sticker = $stmt->fetch();
            if (!$sticker) {
                $pdo->rollBack();
                Http::notFound('QR not found.');
                return;
            }
            if ((int) $sticker['status'] !== QrStickerStatus::UNUSED) {
                $pdo->rollBack();
                Http::conflict('This QR is already activated.');
                return;
            }

            if ($payRef !== '') {
                $dupInTx = $pdo->prepare('SELECT id FROM persons WHERE payment_reference = ? LIMIT 1');
                $dupInTx->execute([$payRef]);
                if ($dupInTx->fetch()) {
                    $pdo->rollBack();
                    Http::conflict('This payment reference was already used.');
                    return;
                }
            }

            $now = Time::utcNowSql();
            $reg = Validation::normalizeVehicleRegistration((string) ($dto['vehicleRegistration'] ?? ''));
            $ins = $pdo->prepare(
                'INSERT INTO persons (name, phone_number, phone_number_type, email, address, father_name, vehicle_registration, emergency_contact_phone, emergency_contact_phone_type, payment_completed, payment_reference, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                trim((string) $dto['name']),
                trim((string) $dto['phoneNumber']),
                PhoneLineType::normalize($dto['phoneNumberType'] ?? null),
                trim((string) $dto['email']),
                trim((string) $dto['address']),
                trim((string) $dto['fatherName']),
                $reg,
                trim((string) $dto['emergencyContactPhone']),
                PhoneLineType::normalize($dto['emergencyContactPhoneType'] ?? null),
                $payDone ? 1 : 0,
                $payRef === '' ? null : $payRef,
                $now,
            ]);
            $personId = (int) $pdo->lastInsertId();

            $txnId = $payRef !== '' ? substr($payRef, 0, 120) : null;
            $ivrNew = IvrAccessCode::allocate($pdo);
            $ivrEmNew = IvrAccessCode::allocate($pdo);
            
            $upd = $pdo->prepare(
                'UPDATE qr_stickers SET person_id = ?, status = ?, activated_at = ?, payment_transaction_id = ?, ivr_access_code = ?, ivr_emergency_access_code = ? WHERE id = ?'
            );
            $upd->execute([$personId, QrStickerStatus::ACTIVE, $now, $txnId, $ivrNew, $ivrEmNew, (int) $sticker['id']]);
            
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $enc = rawurlencode($normalized);
        Http::json(200, [
            'message' => 'Activation successful.',
            'publicId' => $normalized,
            'scanUrl' => $cfg['publicBaseUrl'] . '/q/' . $enc,
            'stickerQrImageApi' => '/api/qr/' . $enc . '/image?embed=scan&modulePixels=32',
        ]);
    }

    /** @param array<string,mixed> $cfg */
    public static function qrImage(PDO $pdo, array $cfg, string $publicIdRaw, ?int $modulePixels, ?string $embed): void
    {
        $normalized = self::normalizePublicId($publicIdRaw);
        $stmt = $pdo->prepare('SELECT public_id, status FROM qr_stickers WHERE public_id = ?');
        $stmt->execute([$normalized]);
        $row = $stmt->fetch();
        if (!$row) {
            Http::notFound('QR not found.');
            return;
        }
        $url = self::resolveEmbedUrl($cfg['publicBaseUrl'], $normalized, $embed, (int) $row['status']);
        $px = $modulePixels ?? 20;
        $png = QrPngRenderer::tryPng($url, $px);
        if ($png !== null) {
            Http::png($png);
            return;
        }
        Http::svg(QrPngRenderer::svg($url, $px));
    }

    /** @param array<string,mixed> $cfg */
    public static function qrImageForOwner(PDO $pdo, array $cfg, int $personId, ?int $modulePixels): void
    {
        $stmt = $pdo->prepare(
            'SELECT public_id FROM qr_stickers WHERE person_id = ? AND status = ? ORDER BY activated_at DESC LIMIT 1'
        );
        $stmt->execute([$personId, QrStickerStatus::ACTIVE]);
        $row = $stmt->fetch();
        if (!$row) {
            Http::notFound('No active CallMeNow QR linked to this owner.');
            return;
        }
        self::qrImage($pdo, $cfg, $row['public_id'], $modulePixels, 'scan');
    }

    /** @param array<string,mixed> $cfg */
    public static function qrBase64ForOwner(PDO $pdo, array $cfg, int $personId, ?int $modulePixels): void
    {
        $stmt = $pdo->prepare(
            'SELECT public_id, status FROM qr_stickers WHERE person_id = ? AND status = ? ORDER BY activated_at DESC LIMIT 1'
        );
        $stmt->execute([$personId, QrStickerStatus::ACTIVE]);
        $row = $stmt->fetch();
        if (!$row) {
            Http::notFound('No active CallMeNow QR linked to this owner.');
            return;
        }
        $publicId = $row['public_id'];
        $url = self::resolveEmbedUrl($cfg['publicBaseUrl'], $publicId, 'scan', (int) $row['status']);
        $dataUri = QrPngRenderer::dataUriForImg($url, $modulePixels ?? 20);
        Http::json(200, [
            'qrCodeImage' => $dataUri,
            'qrText' => $url,
            'publicId' => $publicId,
        ]);
    }

    private static function normalizePublicId(string $publicId): string
    {
        return strtoupper(trim($publicId));
    }

    private static function resolveEmbedUrl(string $baseUrl, string $publicId, ?string $embed, int $status): string
    {
        $base = rtrim($baseUrl, '/');
        $enc = rawurlencode($publicId);
        $mode = ($embed === null || trim($embed) === '') ? 'auto' : strtolower(trim($embed));
        if ($mode === 'activate') {
            return $base . '/activate/' . $enc;
        }
        if ($mode === 'scan') {
            return $base . '/q/' . $enc;
        }

        return $status === QrStickerStatus::UNUSED
            ? $base . '/activate/' . $enc
            : $base . '/q/' . $enc;
    }

    private static function productLabel(string $type): string
    {
        return match ($type) {
            'KeyFinder' => 'Key Finder QR',
            'LuggageTag' => 'Luggage QR Tag',
            default => 'Car QR Sticker',
        };
    }

    /** @return list<array{icon:string,name:string,skuHint:string}> */
    private static function productVariants(): array
    {
        return [
            ['icon' => '🚗', 'name' => 'Car QR Sticker', 'skuHint' => 'CarSticker'],
            ['icon' => '🔑', 'name' => 'Key Finder QR', 'skuHint' => 'KeyFinder'],
            ['icon' => '🧳', 'name' => 'Luggage QR Tag', 'skuHint' => 'LuggageTag'],
        ];
    }

    private static function maskPhoneTail(?string $phone): ?string
    {
        $d = Validation::digitsOnly($phone);
        if ($d === '') {
            return null;
        }
        $len = strlen($d);
        if ($len <= 4) {
            return '****' . $d;
        }
        $tail = substr($d, -4);
        $stars = min(6, $len - 4);

        return str_repeat('*', $stars) . $tail;
    }

    private static function normalizeTel(?string $phone): ?string
    {
        $digits = Validation::digitsOnly($phone);

        return $digits === '' ? null : $digits;
    }

    /**
     * @param array<string,mixed> $sticker in/out; refreshed ivr_access_code when assigned
     */
    private static function ensureStickerIvrAccessCode(PDO $pdo, int $stickerId, array &$sticker): ?string
    {
        if (!IvrAccessCode::columnExists($pdo)) {
            return null;
        }
        $existing = isset($sticker['ivr_access_code']) ? trim((string) $sticker['ivr_access_code']) : '';
        if ($existing !== '') {
            return $existing;
        }
        try {
            $code = IvrAccessCode::allocate($pdo);
        } catch (\Throwable) {
            return null;
        }
        $u = $pdo->prepare('UPDATE qr_stickers SET ivr_access_code = ? WHERE id = ? AND ivr_access_code IS NULL');
        $u->execute([$code, $stickerId]);
        if ($u->rowCount() === 0) {
            $stmt = $pdo->prepare('SELECT ivr_access_code FROM qr_stickers WHERE id = ?');
            $stmt->execute([$stickerId]);
            $row = $stmt->fetch();
            $code = isset($row['ivr_access_code']) ? trim((string) $row['ivr_access_code']) : '';
            if ($code === '') {
                return null;
            }
        }
        $sticker['ivr_access_code'] = $code;

        return $code;
    }

    private static function telUriFromDid(string $didRaw, string $defaultIsd): ?string
    {
        $s = trim($didRaw);
        if ($s === '') {
            return null;
        }
        if (str_starts_with($s, 'tel:')) {
            return $s;
        }
        $d = preg_replace('/\D/', '', $s) ?? '';
        if ($d === '') {
            return null;
        }
        $cc = preg_replace('/\D/', '', $defaultIsd) ?? '91';
        if ($cc === '91' && strlen($d) === 10) {
            return 'tel:+91' . $d;
        }
        if (str_starts_with($d, '00')) {
            return 'tel:+' . substr($d, 2);
        }

        return 'tel:+' . $d;
    }
}
