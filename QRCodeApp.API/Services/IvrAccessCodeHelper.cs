using Microsoft.EntityFrameworkCore;
using QRCodeApp.API.Data;

namespace QRCodeApp.API.Services;

public static class IvrAccessCodeHelper
{
    public static async Task<string> AllocateNewAsync(AppDbContext db, CancellationToken cancellationToken)
    {
        for (var attempt = 0; attempt < 80; attempt++)
        {
            var code = Random.Shared.Next(0, 1_000_000).ToString("D6");
            if (!await db.QrStickers.AnyAsync(q => q.IvrAccessCode == code, cancellationToken).ConfigureAwait(false))
                return code;
        }

        throw new InvalidOperationException("Could not allocate IVR access code.");
    }
}
