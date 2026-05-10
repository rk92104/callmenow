namespace QRCodeApp.API.Models
{
    public static class PhoneLineType
    {
        public const string Mobile = "Mobile";
        public const string Landline = "Landline";

        public static bool IsValid(string? v) =>
            string.Equals(v, Mobile, StringComparison.OrdinalIgnoreCase) ||
            string.Equals(v, Landline, StringComparison.OrdinalIgnoreCase);

        public static string Normalize(string? v) =>
            string.Equals(v, Landline, StringComparison.OrdinalIgnoreCase) ? Landline : Mobile;
    }
}
