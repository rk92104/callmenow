using System.Net.Http.Headers;
using System.Text;
using System.Text.RegularExpressions;
using System.Text.Json;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Options;

namespace QRCodeApp.API.Services;

/// <summary>Exotel Service supporting both Direct IVR Dialing and Server-Side Callbacks.</summary>
public sealed class ExotelConnectService
{
    private readonly HttpClient _http;
    private readonly IOptions<CallMeNowOptions> _opts;

    public ExotelConnectService(HttpClient http, IOptions<CallMeNowOptions> opts)
    {
        _http = http;
        _opts = opts;
    }

    private ExotelOptions Ex => _opts.Value.Exotel;

    public bool IsConfigured =>
        !string.IsNullOrWhiteSpace(Ex.AccountSid) &&
        !string.IsNullOrWhiteSpace(Ex.ApiKey) &&
        !string.IsNullOrWhiteSpace(Ex.ApiToken) &&
        !string.IsNullOrWhiteSpace(Ex.CallerId);

    /// <param name="from">Number to ring first (e.g. scanner or owner depending on order).</param>
    /// <param name="to">Number to connect second.</param>
    public async Task<(bool Ok, string? ErrorMessage)> ConnectAsync(string from, string to, CancellationToken cancellationToken)
    {
        if (!IsConfigured)
            return (false, "Exotel is not configured.");

        var host = NormalizeHost(Ex.Subdomain);
        var sid = Uri.EscapeDataString(Ex.AccountSid.Trim());
        var url = $"https://{host}/v1/Accounts/{sid}/Calls/connect.json";

        using var req = new HttpRequestMessage(HttpMethod.Post, url);
        var raw = $"{Ex.ApiKey.Trim()}:{Ex.ApiToken.Trim()}";
        req.Headers.Authorization = new AuthenticationHeaderValue(
            "Basic",
            Convert.ToBase64String(Encoding.UTF8.GetBytes(raw)));

        var form = new Dictionary<string, string>
        {
            ["From"] = FormatForExotel(from, Ex.DefaultIsd) ?? from,
            ["To"] = FormatForExotel(to, Ex.DefaultIsd) ?? to,
            ["CallerId"] = FormatForExotel(Ex.CallerId, Ex.DefaultIsd) ?? Ex.CallerId
        };
        req.Content = new FormUrlEncodedContent(form);

        HttpResponseMessage resp;
        try
        {
            resp = await _http.SendAsync(req, cancellationToken).ConfigureAwait(false);
        }
        catch
        {
            return (false, "Could not reach Exotel.");
        }

        using (resp)
        {
            var text = await resp.Content.ReadAsStringAsync(cancellationToken).ConfigureAwait(false);
            if (resp.IsSuccessStatusCode)
                return (true, null);

            return (false, FormatExotelFailure((int)resp.StatusCode, text));
        }
    }

    private static string NormalizeHost(string? subdomain)
    {
        var h = (subdomain ?? "api.exotel.com").Trim();
        h = h.Replace("https://", "", StringComparison.OrdinalIgnoreCase)
            .Replace("http://", "", StringComparison.OrdinalIgnoreCase)
            .TrimEnd('/');
        return string.IsNullOrEmpty(h) ? "api.exotel.com" : h;
    }

    private static string? FormatForExotel(string digitsRaw, string defaultIsd)
    {
        var d = new string((digitsRaw ?? string.Empty).Where(char.IsDigit).ToArray());
        if (d.Length == 0) return null;
        if ((defaultIsd ?? "91") == "91" && d.Length == 10) return "0" + d;
        return d;
    }

    private static string FormatExotelFailure(int httpCode, string body)
    {
        var m = Regex.Match(body, @"<Message>(?<m>[^<]+)</Message>", RegexOptions.IgnoreCase);
        if (m.Success) return "Exotel: " + System.Net.WebUtility.HtmlDecode(m.Groups["m"].Value.Trim());
        return $"Exotel HTTP {httpCode}";
    }
}
