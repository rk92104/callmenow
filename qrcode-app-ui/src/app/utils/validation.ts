/** Mirrors API `ContactFieldValidation` (India-focused). */

export function digitsOnly(raw: string | null | undefined): string {
    if (raw == null || !raw.trim()) return '';
    return raw.replace(/\D/g, '');
}

export function normalizeVehicleRegistration(raw: string | null | undefined): string {
    if (raw == null || !raw.trim()) return '';
    return raw
        .toUpperCase()
        .split('')
        .filter((c) => /[A-Z0-9]/i.test(c))
        .join('');
}

/** Live UX under the input: empty → no message; partial → hint; invalid complete → error. */
export type VehicleRegistrationFeedback = { kind: 'hint' | 'error'; text: string };

export function vehicleRegistrationFeedback(raw: string | null | undefined): VehicleRegistrationFeedback | null {
    const n = normalizeVehicleRegistration(raw);
    if (!n) return null;
    if (n.length > 14) {
        return { kind: 'error', text: 'Too long — maximum 14 characters (no spaces).' };
    }
    if (n.length < 9) {
        return {
            kind: 'hint',
            text: `Full number plate is usually 9–10 characters (${n.length} entered).`,
        };
    }
    if (!isValidVehicleRegistration(n)) {
        return {
            kind: 'error',
            text: 'Invalid format. Example: DL01AB1234 — state, district, series, number (A–Z and 0–9 only).',
        };
    }
    return null;
}

export function isValidVehicleRegistration(normalized: string): boolean {
    if (normalized.length < 9 || normalized.length > 14) return false;
    if (!/^[A-Z0-9]+$/.test(normalized)) return false;
    if (/^[A-Z]{2}\d{2}[A-Z]{1,3}\d{4}$/.test(normalized)) return true;
    if (/^[A-Z]{2}\d{1,2}[A-Z]{2}\d{4}$/.test(normalized)) return true;
    if (/^BH\d{2}[A-Z]{2}\d{4}[A-Z]{2}$/.test(normalized)) return true;
    let letters = 0;
    let digits = 0;
    for (const c of normalized) {
        if (/[A-Z]/.test(c)) letters++;
        else if (/\d/.test(c)) digits++;
    }
    return letters >= 2 && digits >= 4 && digits <= 10 && normalized.length <= 14;
}

export function isValidPhoneForType(digits: string, lineType: string | null | undefined): boolean {
    const t = (lineType ?? 'Mobile').trim();
    if (/^landline$/i.test(t)) return digits.length >= 8 && digits.length <= 11;
    if (digits.length === 10) return /^[6-9]/.test(digits);
    if (digits.length === 12 && digits.startsWith('91')) return /^[6-9]/.test(digits.charAt(2));
    return false;
}

export function isValidPersonName(name: string | null | undefined): boolean {
    if (name == null || !name.trim()) return false;
    const t = name.trim();
    return t.length >= 2 && t.length <= 100 && /\p{L}/u.test(t);
}

export function isValidAddress(address: string | null | undefined): boolean {
    if (address == null || !address.trim()) return false;
    const len = address.trim().length;
    return len >= 5 && len <= 300;
}

export function isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export type PersonContactFields = {
    name: string;
    phoneNumber: string;
    phoneNumberType: string;
    email: string;
    address: string;
    fatherName: string;
    vehicleRegistration: string;
    emergencyContactPhone: string;
    emergencyContactPhoneType: string;
};

/** First validation error, or null if OK (same order as server `PersonDto.Validate`). */
export function validatePersonContactPayload(p: PersonContactFields): string | null {
    if (!isValidPersonName(p.name)) {
        return 'Enter a valid full name (at least 2 characters, including letters).';
    }
    if (!isValidPersonName(p.fatherName)) {
        return "Enter a valid father's name (at least 2 characters, including letters).";
    }
    if (!isValidAddress(p.address)) {
        return 'Address must be at least 5 characters.';
    }
    if (!isValidEmail(p.email.trim())) {
        return 'Please enter a valid email address.';
    }
    const ownerDigits = digitsOnly(p.phoneNumber);
    if (!isValidPhoneForType(ownerDigits, p.phoneNumberType)) {
        return /^landline$/i.test((p.phoneNumberType ?? '').trim())
            ? 'Owner landline: enter 8–11 digits (STD + number).'
            : 'Owner mobile: enter a valid 10-digit Indian number (starting 6–9), or 12 digits with 91 prefix.';
    }
    const emDigits = digitsOnly(p.emergencyContactPhone);
    if (!isValidPhoneForType(emDigits, p.emergencyContactPhoneType)) {
        return /^landline$/i.test((p.emergencyContactPhoneType ?? '').trim())
            ? 'Emergency landline: 8–11 digits.'
            : 'Emergency mobile: valid 10-digit Indian number (or 91 + 10 digits).';
    }
    const reg = normalizeVehicleRegistration(p.vehicleRegistration);
    if (!isValidVehicleRegistration(reg)) {
        return 'Vehicle registration looks invalid. Use format like DL01AB1234 (state + district + series + number), no spaces.';
    }
    return null;
}
