using System.Security.Cryptography;
using System.Text;

namespace QRCodeApp.API.Services
{
    /// <summary>Privacy-preserving visitor key for unique-scanner analytics (no raw PII stored).</summary>
    public static class VisitorFingerprint
    {
        public static string Compute(string publicId, string? remoteIp, string? userAgent)
        {
            var ua = userAgent ?? string.Empty;
            if (ua.Length > 400)
                ua = ua[..400];
            var ip = string.IsNullOrWhiteSpace(remoteIp) ? "unknown" : remoteIp.Trim();
            var raw = $"{publicId}|{ip}|{ua}";
            var bytes = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
            return Convert.ToHexString(bytes).ToLowerInvariant();
        }
    }
}
