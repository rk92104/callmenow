using System.Text.Json;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Data;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;

namespace QRCodeApp.API.Controllers;

/// <summary>Exotel App Builder: Gather (dynamic URL) and Connect (dynamic URL) for keypad → owner lookup.</summary>
[Route("api/exotel/ivr")]
[ApiController]
public sealed class ExotelIvrController : ControllerBase
{
    private readonly AppDbContext _db;
    private readonly CallMeNowOptions _opts;

    public ExotelIvrController(AppDbContext db, IOptions<CallMeNowOptions> opts)
    {
        _db = db;
        _opts = opts.Value;
    }

    [HttpGet("gather")]
    public IActionResult Gather()
    {
        if (!IvrWebhookAuthorized())
            return StatusCode(403, new { message = "Forbidden" });

        const string defaultGather =
            "Welcome to Call Me Now. Enter your four digit call code from the vehicle sticker, then press hash. Stay on the line while we connect you.";
        const string defaultRepeat =
            "We did not receive four digits. Please enter your four digit code, then press hash.";

        var gatherText = string.IsNullOrWhiteSpace(_opts.Exotel.IvrGatherPrompt) ? defaultGather : _opts.Exotel.IvrGatherPrompt.Trim();
        var repeatText = string.IsNullOrWhiteSpace(_opts.Exotel.IvrGatherRepeatPrompt) ? defaultRepeat : _opts.Exotel.IvrGatherRepeatPrompt.Trim();

        var payload = new
        {
            gather_prompt = new { text = gatherText },
            max_input_digits = 4,
            finish_on_key = "#",
            input_timeout = 8,
            repeat_menu = 1,
            repeat_gather_prompt = new { text = repeatText },
        };

        return Content(JsonSerializer.Serialize(payload), "application/json");
    }

    [HttpGet("connect")]
    public async Task<IActionResult> Connect(CancellationToken cancellationToken)
    {
        if (!IvrWebhookAuthorized())
            return StatusCode(403, new { message = "Forbidden" });

        // 1. Try seamless routing via Caller ID mapping (if visitor registered their number on the page)
        var fromRaw = Request.Query["From"].ToString();
        var fromDigits = new string(fromRaw.Where(char.IsDigit).ToArray());
        if (!string.IsNullOrEmpty(fromDigits))
        {
            var mapping = await _db.ActiveCallMappings
                .AsNoTracking()
                .Where(m => m.CallerPhoneNormalized == fromDigits && m.ExpiryUtc > DateTime.UtcNow)
                .OrderByDescending(m => m.ExpiryUtc)
                .FirstOrDefaultAsync(cancellationToken);

            if (mapping != null)
            {
                return ConnectTo(mapping.TargetOwnerPhone);
            }
        }

        // 2. Fallback to IVR keypad digits
        var digitsRaw = Request.Query["digits"].ToString();
        digitsRaw = digitsRaw.Trim().Trim('"');
        var digits = new string(digitsRaw.Where(char.IsDigit).ToArray());
        if (string.IsNullOrEmpty(digits))
            return ConnectEmpty();

        // Try owner PIN first
        var ownerRow = await _db.QrStickers
            .AsNoTracking()
            .Include(q => q.Person)
            .Where(q => q.IvrAccessCode == digits && q.Status == QrStickerStatus.Active)
            .Select(q => new { q.Person!.PhoneNumber })
            .FirstOrDefaultAsync(cancellationToken)
            .ConfigureAwait(false);

        if (ownerRow != null)
            return ConnectTo(ownerRow.PhoneNumber);

        // Fallback: try emergency PIN → connect to emergency contact
        var emergencyRow = await _db.QrStickers
            .AsNoTracking()
            .Include(q => q.Person)
            .Where(q => q.IvrEmergencyAccessCode == digits && q.Status == QrStickerStatus.Active)
            .Select(q => new { q.Person!.EmergencyContactPhone })
            .FirstOrDefaultAsync(cancellationToken)
            .ConfigureAwait(false);

        if (emergencyRow != null)
            return ConnectTo(emergencyRow.EmergencyContactPhone);

        return ConnectEmpty();
    }

    private IActionResult ConnectTo(string? rawPhone)
    {
        var e164 = OwnerToE164(rawPhone, _opts.Exotel.DefaultIsd);
        if (string.IsNullOrEmpty(e164))
            return ConnectEmpty();

        var body = new
        {
            fetch_after_attempt = false,
            destination = new { numbers = new[] { e164 } },
            max_ringing_duration = 45,
            max_conversation_duration = 3600,
        };

        return Content(JsonSerializer.Serialize(body), "application/json");
    }

    private bool IvrWebhookAuthorized()
    {
        var secret = (_opts.Exotel.IvrWebhookSecret ?? string.Empty).Trim();
        if (secret.Length == 0)
            return true;
        var got = (Request.Query["secret"].ToString() ?? string.Empty).Trim();
        return string.Equals(secret, got, StringComparison.Ordinal);
    }

    private ContentResult ConnectEmpty()
    {
        var body = new { fetch_after_attempt = false, destination = new { numbers = Array.Empty<string>() } };
        return Content(JsonSerializer.Serialize(body), "application/json");
    }

    private static string? OwnerToE164(string? phoneRaw, string defaultIsd)
    {
        if (string.IsNullOrWhiteSpace(phoneRaw))
            return null;
        var d = new string(phoneRaw.Where(char.IsDigit).ToArray());
        if (d.Length == 0)
            return null;
        var cc = new string(defaultIsd.Where(char.IsDigit).ToArray());
        if (cc.Length == 0)
            cc = "91";
        if (cc == "91")
        {
            if (d.Length == 10)
                return "+91" + d;
            if (d.Length == 12 && d.StartsWith("91", StringComparison.Ordinal))
                return "+" + d;
            if (d.Length == 11 && d[0] == '0')
                return "+91" + d[1..];
        }

        return d.Length is >= 10 and <= 15 ? "+" + d : null;
    }
}
