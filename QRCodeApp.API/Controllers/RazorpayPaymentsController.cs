using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Data;
using QRCodeApp.API.DTOs;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;
using QRCodeApp.API.Services;

namespace QRCodeApp.API.Controllers;

[Route("api/payments/razorpay")]
[ApiController]
public class RazorpayPaymentsController : ControllerBase
{
    private readonly AppDbContext _db;
    private readonly RazorpayPaymentService _razorpay;
    private readonly CallMeNowOptions _opt;

    public RazorpayPaymentsController(
        AppDbContext db,
        RazorpayPaymentService razorpay,
        IOptions<CallMeNowOptions> opt)
    {
        _db = db;
        _razorpay = razorpay;
        _opt = opt.Value;
    }

    [HttpGet("config")]
    public ActionResult<object> Config()
    {
        var on = _razorpay.IsConfigured;
        return Ok(new
        {
            enabled = on,
            keyId = on ? _opt.RazorpayKeyId : null
        });
    }

    [HttpPost("order")]
    public async Task<IActionResult> CreateOrder([FromBody] CreateRazorpayOrderDto dto, CancellationToken ct)
    {
        if (!_razorpay.IsConfigured)
            return StatusCode(503, new { message = "Online payment is not configured." });

        var normalized = (dto.PublicId ?? string.Empty).Trim().ToUpperInvariant();
        if (normalized.Length == 0)
            return BadRequest(new { message = "publicId is required." });

        var sticker = await _db.QrStickers.AsNoTracking()
            .FirstOrDefaultAsync(q => q.PublicId == normalized, ct);
        if (sticker == null)
            return NotFound(new { message = "QR not found." });
        if (sticker.Status != QrStickerStatus.Unused)
            return Conflict(new { message = "This QR is already activated." });

        try
        {
            var r = await _razorpay.CreateOrderAsync(normalized, dto.ReferralCode, ct);
            return Ok(new
            {
                keyId = r.KeyId,
                orderId = r.OrderId,
                amount = r.Amount,
                currency = r.Currency,
                amountInr = r.AmountInr,
                referralApplied = r.ReferralApplied
            });
        }
        catch (RazorpayApiException ex)
        {
            return StatusCode(502, new { message = "Could not start payment. Try again.", detail = ex.Message });
        }
    }
}
