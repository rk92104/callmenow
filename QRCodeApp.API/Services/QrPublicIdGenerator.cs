using System.Security.Cryptography;

namespace QRCodeApp.API.Services
{
    public static class QrPublicIdGenerator
    {
        /// <summary>Format CMN-YY-XXXXXX (upper hex, non-guessable sequential IDs).</summary>
        public static string CreateNext()
        {
            var yy = DateTime.UtcNow.ToString("yy");
            Span<byte> bytes = stackalloc byte[4];
            RandomNumberGenerator.Fill(bytes);
            var suffix = Convert.ToHexString(bytes)[..6];
            return $"CMN-{yy}-{suffix}";
        }
    }
}
