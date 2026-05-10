<?php

declare(strict_types=1);

namespace QrApp;

final class Validation
{
    public static function normalizeVehicleRegistration(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }
        $chars = preg_replace('/[^A-Za-z0-9]/', '', $raw);

        return strtoupper((string) $chars);
    }

    public static function isValidVehicleRegistration(string $normalized): bool
    {
        $len = strlen($normalized);
        if ($len < 9 || $len > 14) {
            return false;
        }
        if (!ctype_alnum($normalized)) {
            return false;
        }
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z]{1,3}\d{4}$/', $normalized)) {
            return true;
        }
        if (preg_match('/^[A-Z]{2}\d{1,2}[A-Z]{2}\d{4}$/', $normalized)) {
            return true;
        }
        if (preg_match('/^BH\d{2}[A-Z]{2}\d{4}[A-Z]{2}$/', $normalized)) {
            return true;
        }
        $letters = strlen(preg_replace('/[^A-Z]/', '', $normalized) ?? '');
        $digits = strlen(preg_replace('/\D/', '', $normalized) ?? '');

        return $letters >= 2 && $digits >= 4 && $digits <= 10 && $len <= 14;
    }

    public static function digitsOnly(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        return preg_replace('/\D/', '', $raw) ?? '';
    }

    public static function isValidPhoneForType(string $digits, string $lineType): bool
    {
        $t = strtolower(trim($lineType));
        if ($t === 'landline') {
            $l = strlen($digits);

            return $l >= 8 && $l <= 11;
        }
        if (strlen($digits) === 10) {
            return $digits[0] >= '6' && $digits[0] <= '9';
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits[2] >= '6' && $digits[2] <= '9';
        }

        return false;
    }

    public static function isValidPersonName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }
        $t = trim($name);
        if (strlen($t) < 2 || strlen($t) > 100) {
            return false;
        }

        return (bool) preg_match('/\p{L}/u', $t);
    }

    public static function isValidAddress(?string $address): bool
    {
        if ($address === null || trim($address) === '') {
            return false;
        }
        $l = strlen(trim($address));

        return $l >= 5 && $l <= 300;
    }

    /** @return array<string, list<string>> */
    public static function validatePersonPayload(array $dto, bool $requireEmergency = true): array
    {
        $errors = [];
        $add = static function (string $field, string $msg) use (&$errors): void {
            $errors[$field][] = $msg;
        };

        $name = isset($dto['name']) ? (string) $dto['name'] : '';
        $father = isset($dto['fatherName']) ? (string) $dto['fatherName'] : '';
        $address = isset($dto['address']) ? (string) $dto['address'] : '';
        $phone = isset($dto['phoneNumber']) ? (string) $dto['phoneNumber'] : '';
        $phoneType = isset($dto['phoneNumberType']) ? (string) $dto['phoneNumberType'] : 'Mobile';
        $em = isset($dto['emergencyContactPhone']) ? (string) $dto['emergencyContactPhone'] : '';
        $emType = isset($dto['emergencyContactPhoneType']) ? (string) $dto['emergencyContactPhoneType'] : 'Mobile';
        $regRaw = isset($dto['vehicleRegistration']) ? (string) $dto['vehicleRegistration'] : '';
        $email = isset($dto['email']) ? (string) $dto['email'] : '';

        if (!self::isValidPersonName($name)) {
            $add('name', 'Enter a valid full name (at least 2 characters, including letters).');
        }
        if (!self::isValidPersonName($father)) {
            $add('fatherName', "Enter a valid father's name (at least 2 characters, including letters).");
        }
        if (!self::isValidAddress($address)) {
            $add('address', 'Address must be at least 5 characters.');
        }
        $ownerDigits = self::digitsOnly($phone);
        if (!self::isValidPhoneForType($ownerDigits, $phoneType)) {
            $add(
                'phoneNumber',
                strcasecmp($phoneType, 'Landline') === 0
                    ? 'Landline: enter 8–11 digits (STD + number).'
                    : 'Mobile: enter a valid 10-digit Indian number (starting 6–9), or 12 digits with 91 prefix.'
            );
        }
        if ($requireEmergency || $em !== '') {
            $emDigits = self::digitsOnly($em);
            if (!self::isValidPhoneForType($emDigits, $emType)) {
                $add(
                    'emergencyContactPhone',
                    strcasecmp($emType, 'Landline') === 0
                        ? 'Emergency landline: 8–11 digits.'
                        : 'Emergency mobile: valid 10-digit Indian number (or 91 + 10 digits).'
                );
            }
        }
        $reg = self::normalizeVehicleRegistration($regRaw);
        if (!self::isValidVehicleRegistration($reg)) {
            $add(
                'vehicleRegistration',
                'Vehicle registration looks invalid. Use format like DL01AB1234 (state + district + series + number), no spaces.'
            );
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $add('email', 'Invalid email format');
        }

        return $errors;
    }

    /** @return array<string, list<string>> */
    public static function validateActivatePayload(array $dto): array
    {
        $errors = self::validatePersonPayload($dto, true);
        $add = static function (string $field, string $msg) use (&$errors): void {
            $errors[$field][] = $msg;
        };

        $payDone = !empty($dto['paymentCompleted']);
        $payRef = isset($dto['paymentReference']) ? trim((string) $dto['paymentReference']) : '';
        if (!$payDone && $payRef === '') {
            $add('paymentReference', 'Payment must be completed before activation (set PaymentCompleted or provide PaymentReference).');
        }

        $pt = isset($dto['phoneNumberType']) ? (string) $dto['phoneNumberType'] : '';
        $et = isset($dto['emergencyContactPhoneType']) ? (string) $dto['emergencyContactPhoneType'] : '';
        if (!PhoneLineType::isValid($pt) || !PhoneLineType::isValid($et)) {
            $add('phoneNumberType', 'Owner and emergency numbers must each specify a type: Mobile or Landline.');
        }

        return $errors;
    }
}
