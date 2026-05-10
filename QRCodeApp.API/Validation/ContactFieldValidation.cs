using System.Text.RegularExpressions;

namespace QRCodeApp.API.Validation
{
    /// <summary>Shared rules for owner / activation payloads (India-focused).</summary>
    public static class ContactFieldValidation
    {
        /// <summary>Strip spaces/hyphens; keep only letters and digits; uppercase.</summary>
        public static string NormalizeVehicleRegistration(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return string.Empty;
            var chars = raw.Where(char.IsLetterOrDigit).Select(char.ToUpperInvariant).ToArray();
            return new string(chars);
        }

        /// <summary>
        /// Typical Indian plate: SS + DD + 1–3 letters + 4 digits (e.g. DL01AB1234).
        /// Also allows simplified Bharat (BH) style and a loose alphanumeric fallback.
        /// </summary>
        public static bool IsValidVehicleRegistration(string normalized)
        {
            if (normalized.Length is < 9 or > 14) return false;
            if (!normalized.All(char.IsLetterOrDigit)) return false;

            // Standard: XX + 2 district digits + 1–3 series letters + 4 digits
            if (Regex.IsMatch(normalized, @"^[A-Z]{2}\d{2}[A-Z]{1,3}\d{4}$"))
                return true;

            // Some RTOs / older: XX + 1–2 district digits + 2 letters + 4 digits
            if (Regex.IsMatch(normalized, @"^[A-Z]{2}\d{1,2}[A-Z]{2}\d{4}$"))
                return true;

            // Bharat series (approx.): BH + 2 digits + 2 letters + 4 digits + 2 letters
            if (Regex.IsMatch(normalized, @"^BH\d{2}[A-Z]{2}\d{4}[A-Z]{2}$"))
                return true;

            // Fallback: at least 2 letters, 4–10 digits, plausible length
            var letters = normalized.Count(char.IsLetter);
            var digits = normalized.Count(char.IsDigit);
            return letters >= 2 && digits is >= 4 and <= 10 && normalized.Length <= 14;
        }

        public static string DigitsOnly(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return string.Empty;
            return new string(raw.Where(char.IsDigit).ToArray());
        }

        public static bool IsValidPhoneForType(string digits, string lineType)
        {
            var t = (lineType ?? "Mobile").Trim();
            if (t.Equals("Landline", StringComparison.OrdinalIgnoreCase))
                return digits.Length is >= 8 and <= 11;

            // Mobile: 10-digit Indian or 12 with country 91
            if (digits.Length == 10)
                return digits[0] is >= '6' and <= '9';
            if (digits.Length == 12 && digits.StartsWith("91", StringComparison.Ordinal))
                return digits[2] is >= '6' and <= '9';
            return false;
        }

        public static bool IsValidPersonName(string? name)
        {
            if (string.IsNullOrWhiteSpace(name)) return false;
            var t = name.Trim();
            return t.Length is >= 2 and <= 100 && t.Any(char.IsLetter);
        }

        public static bool IsValidAddress(string? address)
        {
            if (string.IsNullOrWhiteSpace(address)) return false;
            return address.Trim().Length is >= 5 and <= 300;
        }
    }
}
