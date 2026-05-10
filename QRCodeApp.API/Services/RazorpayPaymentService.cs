using System.Globalization;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Options;

namespace QRCodeApp.API.Services;

public sealed class RazorpayPaymentService
{
    private readonly HttpClient _http;
    private readonly CallMeNowOptions _o;
    private readonly ILogger<RazorpayPaymentService> _log;

    public RazorpayPaymentService(HttpClient http, IOptions<CallMeNowOptions> options, ILogger<RazorpayPaymentService> log)
    {
        _http = http;
        _o = options.Value;
        _log = log;
    }

    public bool IsConfigured =>
        !string.IsNullOrWhiteSpace(_o.RazorpayKeyId) &&
        !string.IsNullOrWhiteSpace(_o.RazorpayKeySecret);

    public (int AmountPaise, int AmountInr, bool ReferralApplied) ComputeActivationAmount(string? referralCode)
    {
        var sticker = _o.StickerPriceInr;
        var discount = _o.ReferralDiscountInr;
        var cfgRef = (_o.ReferralCode ?? string.Empty).Trim().ToUpperInvariant();
        var input = (referralCode ?? string.Empty).Trim().ToUpperInvariant();
        var applied = input.Length > 0 && input == cfgRef;
        var inr = Math.Max(1, sticker - (applied ? discount : 0));
        return (inr * 100, inr, applied);
    }

    public async Task<RazorpayOrderResult> CreateOrderAsync(string normalizedPublicId, string? referralCode, CancellationToken ct)
    {
        if (!IsConfigured)
            throw new InvalidOperationException("Razorpay is not configured.");

        var (paise, inr, referralApplied) = ComputeActivationAmount(referralCode);
        var receipt = Guid.NewGuid().ToString("N")[..Math.Min(20, 32)];
        var body = new
        {
            amount = paise,
            currency = "INR",
            receipt,
            payment_capture = 1,
            notes = new Dictionary<string, string>
            {
                ["public_id"] = normalizedPublicId,
                ["amount_paise"] = paise.ToString(CultureInfo.InvariantCulture),
                ["referral_applied"] = referralApplied ? "1" : "0"
            }
        };

        using var req = new HttpRequestMessage(HttpMethod.Post, "v1/orders");
        req.Content = new StringContent(JsonSerializer.Serialize(body), Encoding.UTF8, "application/json");
        AddBasicAuth(req);

        using var resp = await _http.SendAsync(req, ct);
        var raw = await resp.Content.ReadAsStringAsync(ct);
        if (!resp.IsSuccessStatusCode)
        {
            _log.LogWarning("Razorpay order failed: {Status} {Body}", (int)resp.StatusCode, raw);
            throw new RazorpayApiException(ExtractError(raw) ?? "Razorpay order failed.");
        }

        using var doc = JsonDocument.Parse(raw);
        var root = doc.RootElement;
        var id = root.GetProperty("id").GetString() ?? string.Empty;
        var amount = root.GetProperty("amount").GetInt32();
        var currency = root.TryGetProperty("currency", out var c) ? c.GetString() ?? "INR" : "INR";
        if (string.IsNullOrEmpty(id) || amount <= 0)
            throw new RazorpayApiException("Invalid order response from Razorpay.");

        return new RazorpayOrderResult(_o.RazorpayKeyId, id, amount, currency, inr, referralApplied);
    }

    public async Task<RazorpayVerifyResult> VerifyActivationPaymentAsync(
        string normalizedPublicId,
        string orderId,
        string paymentId,
        string signature,
        CancellationToken ct)
    {
        if (!IsConfigured)
            return new RazorpayVerifyResult(false, "Razorpay is not configured.", null);

        orderId = orderId.Trim();
        paymentId = paymentId.Trim();
        signature = signature.Trim();
        if (orderId.Length == 0 || paymentId.Length == 0 || signature.Length == 0)
            return new RazorpayVerifyResult(false, "Missing Razorpay payment fields.", null);

        if (!VerifySignature(orderId, paymentId, signature))
            return new RazorpayVerifyResult(false, "Invalid Razorpay payment signature.", null);

        JsonElement payRoot;
        JsonElement ordRoot;
        try
        {
            payRoot = await GetJsonAsync($"v1/payments/{Uri.EscapeDataString(paymentId)}", ct);
            ordRoot = await GetJsonAsync($"v1/orders/{Uri.EscapeDataString(orderId)}", ct);
        }
        catch (RazorpayApiException ex)
        {
            return new RazorpayVerifyResult(false, ex.Message, null);
        }

        var status = payRoot.TryGetProperty("status", out var st) ? st.GetString() : null;
        if (status is not ("captured" or "authorized"))
            return new RazorpayVerifyResult(false, "Payment is not completed.", null);

        var payOrderId = payRoot.TryGetProperty("order_id", out var oid) ? oid.GetString() : null;
        if (!string.Equals(payOrderId, orderId, StringComparison.Ordinal))
            return new RazorpayVerifyResult(false, "Payment does not match this order.", null);

        if (!ordRoot.TryGetProperty("notes", out var notes) || notes.ValueKind != JsonValueKind.Object)
            return new RazorpayVerifyResult(false, "Invalid order metadata.", null);

        var notePid = GetNoteString(notes, "public_id");
        if (!string.Equals(notePid?.Trim().ToUpperInvariant(), normalizedPublicId, StringComparison.Ordinal))
            return new RazorpayVerifyResult(false, "This payment is for a different tag.", null);

        var amountPaiseStr = GetNoteString(notes, "amount_paise");
        if (string.IsNullOrEmpty(amountPaiseStr) || !int.TryParse(amountPaiseStr, out var expectedPaise) || expectedPaise <= 0)
            return new RazorpayVerifyResult(false, "Order amount metadata missing.", null);

        var orderAmount = ordRoot.GetProperty("amount").GetInt32();
        if (orderAmount != expectedPaise)
            return new RazorpayVerifyResult(false, "Order amount mismatch.", null);

        var paidAmount = payRoot.GetProperty("amount").GetInt32();
        if (paidAmount != orderAmount)
            return new RazorpayVerifyResult(false, "Paid amount mismatch.", null);

        return new RazorpayVerifyResult(true, null, paymentId);
    }

    private bool VerifySignature(string orderId, string paymentId, string signature)
    {
        try
        {
            using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(_o.RazorpayKeySecret));
            var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes($"{orderId}|{paymentId}"));
            var sigBytes = Convert.FromHexString(signature);
            return hash.Length == sigBytes.Length && CryptographicOperations.FixedTimeEquals(hash, sigBytes);
        }
        catch
        {
            return false;
        }
    }

    private async Task<JsonElement> GetJsonAsync(string relativeUrl, CancellationToken ct)
    {
        using var req = new HttpRequestMessage(HttpMethod.Get, relativeUrl);
        AddBasicAuth(req);
        using var resp = await _http.SendAsync(req, ct);
        var raw = await resp.Content.ReadAsStringAsync(ct);
        if (!resp.IsSuccessStatusCode)
            throw new RazorpayApiException(ExtractError(raw) ?? $"Razorpay API error ({(int)resp.StatusCode}).");
        using var doc = JsonDocument.Parse(raw);
        return doc.RootElement.Clone();
    }

    private void AddBasicAuth(HttpRequestMessage req)
    {
        var token = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{_o.RazorpayKeyId}:{_o.RazorpayKeySecret}"));
        req.Headers.Authorization = new AuthenticationHeaderValue("Basic", token);
    }

    private static string? GetNoteString(JsonElement notes, string key)
    {
        if (!notes.TryGetProperty(key, out var p))
            return null;
        return p.ValueKind == JsonValueKind.String ? p.GetString() : p.ToString();
    }

    private static string? ExtractError(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty("error", out var e) &&
                e.TryGetProperty("description", out var d))
                return d.GetString();
        }
        catch
        {
            // ignore
        }
        return null;
    }
}

public sealed record RazorpayOrderResult(string KeyId, string OrderId, int Amount, string Currency, int AmountInr, bool ReferralApplied);

public sealed record RazorpayVerifyResult(bool Ok, string? Error, string? PaymentId);

public sealed class RazorpayApiException(string message) : Exception(message);
