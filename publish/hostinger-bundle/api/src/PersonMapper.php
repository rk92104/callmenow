<?php

declare(strict_types=1);

namespace QrApp;

final class PersonMapper
{
    /** @param array<string,mixed> $r */
    public static function toApi(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'phoneNumber' => $r['phone_number'],
            'phoneNumberType' => $r['phone_number_type'],
            'email' => $r['email'],
            'address' => $r['address'],
            'fatherName' => $r['father_name'],
            'vehicleRegistration' => $r['vehicle_registration'],
            'emergencyContactPhone' => $r['emergency_contact_phone'],
            'emergencyContactPhoneType' => $r['emergency_contact_phone_type'],
            'paymentCompleted' => (bool) $r['payment_completed'],
            'paymentReference' => $r['payment_reference'],
            'createdAt' => Time::toIso($r['created_at']) ?? $r['created_at'],
            'updatedAt' => Time::toIso($r['updated_at'] ?? null),
        ];
    }
}
