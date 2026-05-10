using QRCoder;

namespace QRCodeApp.API.Services
{
    /// <summary>Renders a scannable PNG; payload must be the full public scan URL (HTTPS recommended for production).</summary>
    public static class QrPngRenderer
    {
        /// <param name="modulePixels">Size of each QR module in pixels; higher = larger file, better for print (try 24–40).</param>
        public static byte[] Render(string urlPayload, int modulePixels = 20)
        {
            var px = Math.Clamp(modulePixels, 6, 60);
            using var gen = new QRCodeGenerator();
            var data = gen.CreateQrCode(urlPayload, QRCodeGenerator.ECCLevel.Q);
            using var qr = new PngByteQRCode(data);
            return qr.GetGraphic(px);
        }
    }
}
