using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Data;
using QRCodeApp.API.DTOs;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;
using QRCodeApp.API.Services;
using QRCodeApp.API.Validation;

namespace QRCodeApp.API.Controllers
{
    [Route("api/qr")]
    [ApiController]
    public class QrController : ControllerBase
    {
        private readonly AppDbContext _context;
        private readonly CallMeNowOptions _options;
        private readonly IHttpContextAccessor _http;
        private readonly RazorpayPaymentService _razorpay;
        private readonly ExotelConnectService _exotel;

        public QrController(
            AppDbContext context,
            IOptions<CallMeNowOptions> options,
            IHttpContextAccessor http,
            RazorpayPaymentService razorpay,
            ExotelConnectService exotel)
        {
            _context = context;
            _options = options.Value;
            _http = http;
            _razorpay = razorpay;
            _exotel = exotel;
        }

        [HttpPost("marketing/leads")]
        public async Task<IActionResult> SubmitMarketingLead([FromBody] SubmitMarketingLeadDto dto, CancellationToken ct)
        {
            if (!ModelState.IsValid)
                return BadRequest(ModelState);

            var digits = new string((dto.Phone ?? string.Empty).Where(char.IsDigit).ToArray());
            if (digits.Length < 8)
                return BadRequest(new { message = "Enter a valid phone number." });

            _context.MarketingLeads.Add(new MarketingLead
            {
                PhoneNormalized = digits,
                QrPublicId = string.IsNullOrWhiteSpace(dto.PublicId) ? null : NormalizePublicId(dto.PublicId),
                ReferralCode = string.IsNullOrWhiteSpace(dto.ReferralCode) ? null : dto.ReferralCode.Trim(),
                Source = "scan_page_coupon",
                CreatedAtUtc = DateTime.UtcNow
            });
            await _context.SaveChangesAsync(ct);
            return Ok(new { message = "Lead saved." });
        }

        [HttpGet("{publicId}/scan")]
        public async Task<ActionResult<QrScanResponseDto>> Scan(string publicId, CancellationToken ct)
        {
            var normalized = NormalizePublicId(publicId);
            var sticker = await _context.QrStickers
                .Include(q => q.Person)
                .FirstOrDefaultAsync(q => q.PublicId == normalized, ct);

            if (sticker == null)
                return NotFound(new { message = "QR not found or invalid." });

            var baseUrl = _options.PublicBaseUrl.TrimEnd('/');
            var scanPageUrl = $"{baseUrl}/q/{Uri.EscapeDataString(sticker.PublicId)}";
            var activatePageUrl = $"{baseUrl}/activate/{Uri.EscapeDataString(sticker.PublicId)}";

            var ip = _http.HttpContext?.Connection.RemoteIpAddress?.ToString();
            var ua = _http.HttpContext?.Request.Headers.UserAgent.ToString() ?? string.Empty;
            var hash = VisitorFingerprint.Compute(normalized, ip, ua);
            var snippet = ua.Length > 250 ? ua[..250] : ua;

            var seenBefore = await _context.QrScanEvents.AnyAsync(
                e => e.QrStickerId == sticker.Id && e.VisitorHash == hash, ct);

            _context.QrScanEvents.Add(new QrScanEvent
            {
                QrStickerId = sticker.Id,
                VisitorHash = hash,
                ScannedAtUtc = DateTime.UtcNow,
                UserAgentSnippet = string.IsNullOrEmpty(snippet) ? null : snippet
            });
            sticker.ScanCount++;
            if (!seenBefore)
                sticker.UniqueScannerCount++;

            await _context.SaveChangesAsync(ct);

            var shareUrl = scanPageUrl;

            if (sticker.Status == QrStickerStatus.Unused)
            {
                var unusedDto = new QrScanResponseDto
                {
                    PublicId = sticker.PublicId,
                    Status = "unused",
                    ProductType = sticker.ProductType,
                    ProductLabel = ProductLabel(sticker.ProductType),
                    ScanCount = sticker.ScanCount,
                    UniqueScannerCount = sticker.UniqueScannerCount,
                    ActivatePageUrl = activatePageUrl,
                    ScanPageUrl = scanPageUrl,
                    Headline = "This CallMeNow tag is not activated yet",
                    Subtitle = "Owner completes setup and payment after purchase. Packaging QR opens activate; public sticker uses /q after go-live.",
                    MaskingNote = "Once active, callers reach the owner without seeing their private number on this page.",
                    TrustedOwnersLine = _options.TrustedOwnersLine,
                    RegionTagline = _options.RegionTagline,
                    ReferralCode = _options.ReferralCode,
                    ReferralDiscountInr = _options.ReferralDiscountInr,
                    StickerPriceInr = _options.StickerPriceInr,
                    LocalizedCityLine = _options.LocalizedCityLine,
                    ProductVariants = ProductVariants(),
                    SharePageUrl = shareUrl,
                    DialUri = null,
                    VehicleRegistration = null,
                    EmergencyDialUri = null,
                    EmergencyContactMasked = null,
                    PrimaryPhoneType = null,
                    EmergencyPhoneType = null
                };
                ApplyRazorpay(unusedDto);
                return Ok(unusedDto);
            }

            var person = sticker.Person;
            if (person == null)
            {
                var inactive = BuildInactiveResponse(sticker, shareUrl, activatePageUrl, scanPageUrl);
                ApplyRazorpay(inactive);
                return Ok(inactive);
            }

            var tel = NormalizeTel(person.PhoneNumber);
            var em = NormalizeTel(person.EmergencyContactPhone);
            var reg = string.IsNullOrWhiteSpace(person.VehicleRegistration)
                ? null
                : ContactFieldValidation.NormalizeVehicleRegistration(person.VehicleRegistration);

            var useExotel = _exotel.IsConfigured && !string.IsNullOrEmpty(tel);
            string? ivrDialUri = null;
            string? ivrCode = null;
            if (useExotel && !string.IsNullOrWhiteSpace(_options.Exotel.IvrDid))
            {
                if (string.IsNullOrEmpty(sticker.IvrAccessCode))
                {
                    sticker.IvrAccessCode = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct);
                    await _context.SaveChangesAsync(ct);
                }

                if (string.IsNullOrEmpty(sticker.IvrEmergencyAccessCode))
                {
                    sticker.IvrEmergencyAccessCode = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct);
                    await _context.SaveChangesAsync(ct);
                }

                ivrCode = sticker.IvrAccessCode;
                ivrDialUri = TelUriFromDid(_options.Exotel.IvrDid.Trim(), _options.Exotel.DefaultIsd);
            }

            var ivrDirectOk = useExotel && _options.Exotel.ScanDirectTel && !string.IsNullOrEmpty(ivrDialUri) && !string.IsNullOrEmpty(ivrCode);
            var ownerAlertUrl = (_options.Exotel.OwnerAlertAppUrl ?? string.Empty).Trim();
            var ownerAlertOnly = useExotel && ownerAlertUrl != "" && !ivrDirectOk;

            var ownerConnectViaExotel = useExotel;
            var dialUriActive = !string.IsNullOrEmpty(ivrDialUri) && !string.IsNullOrEmpty(ivrCode)
                ? ivrDialUri
                : (string.IsNullOrEmpty(tel) ? null : $"tel:{tel}");

            var exotelIvrDialUriOut = ivrDialUri;

            string maskingActive;
            if (useExotel)
            {
                maskingActive = "Fastest: use Open dialer — your phone app opens right away and you reach the owner after entering your 6-digit code. Below: Exotel API can ring your phone if you prefer not to dial.";
            }
            else
            {
                maskingActive = "Tap Call owner to place a direct call from your phone.";
            }

            var activeScanDto = new QrScanResponseDto
            {
                PublicId = sticker.PublicId,
                Status = "active",
                ProductType = sticker.ProductType,
                ProductLabel = ProductLabel(sticker.ProductType),
                ScanCount = sticker.ScanCount,
                UniqueScannerCount = sticker.UniqueScannerCount,
                ActivatePageUrl = activatePageUrl,
                ScanPageUrl = scanPageUrl,
                VehicleRegistration = reg,
                EmergencyDialUri = !string.IsNullOrEmpty(exotelIvrDialUriOut) ? exotelIvrDialUriOut : (string.IsNullOrEmpty(em) ? null : $"tel:{em}"),
                EmergencyContactMasked = MaskPhoneTail(person.EmergencyContactPhone),
                OwnerPhoneMasked = MaskPhoneTail(person.PhoneNumber),
                PrimaryPhoneType = PhoneLineType.Normalize(person.PhoneNumberType),
                EmergencyPhoneType = PhoneLineType.Normalize(person.EmergencyContactPhoneType),
                Headline = "Need the vehicle owner?",
                Subtitle = "Connect privately. Your number is not shown to the owner from this page.",
                DialUri = dialUriActive, 
                OwnerConnectViaExotel = ownerConnectViaExotel,
                ExotelRingOwnerFirst = _options.Exotel.RingOwnerFirst,
                ExotelOwnerOnlyAlert = false,
                ExotelAppId = null,
                ExotelCallerId = _options.Exotel.CallerId,
                ExotelIvrDialUri = exotelIvrDialUriOut,
                IvrAccessCode = ivrCode,
                IvrEmergencyAccessCode = sticker.IvrEmergencyAccessCode,
                OwnerNumberHiddenOnPage = true,
                MaskingNote = maskingActive,
                TrustedOwnersLine = _options.TrustedOwnersLine,
                RegionTagline = _options.RegionTagline,
                ReferralCode = _options.ReferralCode,
                ReferralDiscountInr = _options.ReferralDiscountInr,
                StickerPriceInr = _options.StickerPriceInr,
                LocalizedCityLine = _options.LocalizedCityLine,
                ProductVariants = ProductVariants(),
                SharePageUrl = shareUrl
            };
            ApplyRazorpay(activeScanDto);
            return Ok(activeScanDto);
        }

        /// <summary>Registers caller mapping for seamless direct calling (No callback API triggered).</summary>
        [HttpPost("{publicId}/exotel/connect-owner")]
        public async Task<IActionResult> ExotelConnectOwner(string publicId, [FromBody] ExotelConnectOwnerDto dto, [FromQuery] bool emergency = false, CancellationToken ct = default)
        {
            if (!_exotel.IsConfigured)
                return StatusCode(503, new { message = "Exotel is not configured." });

            if (dto == null || string.IsNullOrWhiteSpace(dto.FromPhone))
                return BadRequest(new { message = "Enter your phone number." });

            var normalized = NormalizePublicId(publicId);
            var sticker = await _context.QrStickers
                .Include(q => q.Person)
                .FirstOrDefaultAsync(q => q.PublicId == normalized, ct);

            if (sticker == null || sticker.Status != QrStickerStatus.Active)
                return NotFound(new { message = "QR not found or not active." });

            var person = sticker.Person;
            if (person == null)
                return NotFound(new { message = "Owner record missing." });

            var targetDigits = emergency 
                ? NormalizeTel(person.EmergencyContactPhone ?? string.Empty)
                : NormalizeTel(person.PhoneNumber ?? string.Empty);

            if (string.IsNullOrEmpty(targetDigits))
                return BadRequest(new { message = emergency ? "No emergency phone on file." : "Owner has no phone on file." });

            var fromDigits = NormalizeTel(dto.FromPhone);
            if (string.IsNullOrEmpty(fromDigits) || fromDigits.Length < 8)
                return BadRequest(new { message = "Enter a valid mobile number." });

            // Mapping for Direct IVR
            _context.ActiveCallMappings.Add(new ActiveCallMapping
            {
                CallerPhoneNormalized = fromDigits,
                TargetOwnerPhone = targetDigits,
                ExpiryUtc = DateTime.UtcNow.AddMinutes(15)
            });
            await _context.SaveChangesAsync(ct);

            // API Callback
            var exotelRes = await _exotel.ConnectAsync(fromDigits, targetDigits, ct).ConfigureAwait(false);
            if (!exotelRes.Ok)
                return StatusCode(502, new { message = exotelRes.ErrorMessage ?? "Call failed." });

            return Ok(new { message = "Request sent. Your phone will ring shortly." });
        }

        [HttpPost("{publicId}/activate")]
        public async Task<ActionResult> Activate(string publicId, [FromBody] ActivateQrDto dto, CancellationToken ct)
        {
            if (!ModelState.IsValid)
                return BadRequest(ModelState);

            if (!dto.PaymentCompleted && string.IsNullOrWhiteSpace(dto.PaymentReference))
                return BadRequest(new { message = "Payment must be completed before activation (set PaymentCompleted or provide PaymentReference)." });

            var normalized = NormalizePublicId(publicId);
            var sticker = await _context.QrStickers.FirstOrDefaultAsync(q => q.PublicId == normalized, ct);
            if (sticker == null)
                return NotFound(new { message = "QR not found." });

            if (sticker.Status != QrStickerStatus.Unused)
                return Conflict(new { message = "This QR is already activated." });

            if (!PhoneLineType.IsValid(dto.PhoneNumberType) || !PhoneLineType.IsValid(dto.EmergencyContactPhoneType))
                return BadRequest(new { message = "Owner and emergency numbers must each specify a type: Mobile or Landline." });

            if (_razorpay.IsConfigured && dto.PaymentCompleted)
            {
                if (string.IsNullOrWhiteSpace(dto.RazorpayOrderId) ||
                    string.IsNullOrWhiteSpace(dto.RazorpayPaymentId) ||
                    string.IsNullOrWhiteSpace(dto.RazorpaySignature))
                    return BadRequest(new { message = "Complete payment with Razorpay, or clear payment received and enter an offline reference." });

                var verify = await _razorpay.VerifyActivationPaymentAsync(
                    normalized,
                    dto.RazorpayOrderId!,
                    dto.RazorpayPaymentId!,
                    dto.RazorpaySignature!,
                    ct);
                if (!verify.Ok)
                    return BadRequest(new { message = verify.Error ?? "Payment verification failed." });

                if (await _context.Persons.AnyAsync(p => p.PaymentReference == verify.PaymentId, ct))
                    return Conflict(new { message = "This payment was already used." });

                dto.PaymentReference = verify.PaymentId;
                dto.PaymentCompleted = true;
            }

            var payRef = string.IsNullOrWhiteSpace(dto.PaymentReference) ? null : dto.PaymentReference.Trim();
            if (payRef != null && await _context.Persons.AnyAsync(p => p.PaymentReference == payRef, ct))
                return Conflict(new { message = "This payment reference was already used." });

            var person = new Person
            {
                Name = dto.Name,
                PhoneNumber = dto.PhoneNumber,
                PhoneNumberType = PhoneLineType.Normalize(dto.PhoneNumberType),
                Email = dto.Email,
                Address = dto.Address,
                FatherName = dto.FatherName,
                VehicleRegistration = ContactFieldValidation.NormalizeVehicleRegistration(dto.VehicleRegistration),
                EmergencyContactPhone = dto.EmergencyContactPhone.Trim(),
                EmergencyContactPhoneType = PhoneLineType.Normalize(dto.EmergencyContactPhoneType),
                PaymentCompleted = dto.PaymentCompleted,
                PaymentReference = payRef,
                CreatedAt = DateTime.UtcNow
            };

            _context.Persons.Add(person);
            await _context.SaveChangesAsync(ct);

            sticker.PersonId = person.Id;
            sticker.Status = QrStickerStatus.Active;
            sticker.ActivatedAt = DateTime.UtcNow;
            sticker.PaymentTransactionId = payRef != null && payRef.Length > 120 ? payRef[..120] : payRef;
            if (string.IsNullOrEmpty(sticker.IvrAccessCode))
                sticker.IvrAccessCode = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct);

            await _context.SaveChangesAsync(ct);

            return Ok(new
            {
                message = "Activation successful.",
                sticker.PublicId,
                scanUrl = $"{_options.PublicBaseUrl.TrimEnd('/')}/q/{Uri.EscapeDataString(sticker.PublicId)}",
                stickerQrImageApi = $"/api/qr/{Uri.EscapeDataString(sticker.PublicId)}/image?embed=scan&modulePixels=32"
            });
        }

        /// <summary>Admin only: Force activate a sticker for free (bypassing Razorpay).</summary>
        [HttpPost("{publicId}/activate-free")]
        public async Task<ActionResult> ActivateFree(string publicId, [FromBody] ActivateQrDto dto, CancellationToken ct)
        {
            if (!ModelState.IsValid)
                return BadRequest(ModelState);

            var normalized = NormalizePublicId(publicId);
            var sticker = await _context.QrStickers.FirstOrDefaultAsync(q => q.PublicId == normalized, ct);
            if (sticker == null)
                return NotFound(new { message = "QR not found." });

            if (sticker.Status != QrStickerStatus.Unused)
                return Conflict(new { message = "This QR is already activated." });

            var payRef = $"Admin-Free-{DateTime.UtcNow:yyyyMMddHHmmss}";
            
            var person = new Person
            {
                Name = dto.Name,
                PhoneNumber = dto.PhoneNumber,
                PhoneNumberType = PhoneLineType.Normalize(dto.PhoneNumberType),
                Email = dto.Email,
                Address = dto.Address,
                FatherName = dto.FatherName,
                VehicleRegistration = ContactFieldValidation.NormalizeVehicleRegistration(dto.VehicleRegistration),
                EmergencyContactPhone = dto.EmergencyContactPhone.Trim(),
                EmergencyContactPhoneType = PhoneLineType.Normalize(dto.EmergencyContactPhoneType),
                PaymentCompleted = true,
                PaymentReference = payRef,
                CreatedAt = DateTime.UtcNow
            };

            _context.Persons.Add(person);
            await _context.SaveChangesAsync(ct);

            sticker.PersonId = person.Id;
            sticker.Status = QrStickerStatus.Active;
            sticker.ActivatedAt = DateTime.UtcNow;
            sticker.PaymentTransactionId = payRef; 
            if (string.IsNullOrEmpty(sticker.IvrAccessCode))
                sticker.IvrAccessCode = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct);

            await _context.SaveChangesAsync(ct);
            return Ok(new { message = "Free activation successful.", sticker.PublicId });
        }





        /// <param name="embed">auto (unused→activate, active→/q), activate (packaging), scan (vehicle sticker).</param>
        [HttpGet("{publicId}/image")]
        public async Task<IActionResult> QrImage(
            string publicId,
            [FromQuery] int? modulePixels,
            [FromQuery] string? embed,
            CancellationToken ct)
        {
            var normalized = NormalizePublicId(publicId);
            var row = await _context.QrStickers.AsNoTracking()
                .FirstOrDefaultAsync(q => q.PublicId == normalized, ct);
            if (row == null)
                return NotFound(new { message = "QR not found." });

            var baseUrl = _options.PublicBaseUrl.TrimEnd('/');
            var url = ResolveEmbedUrl(baseUrl, normalized, embed, row.Status);
            var bytes = QrPngRenderer.Render(url, modulePixels ?? 20);
            return File(bytes, "image/png");
        }

        [HttpGet("by-owner/{personId:int}/image")]
        public async Task<IActionResult> QrImageForOwner(int personId, [FromQuery] int? modulePixels, CancellationToken ct)
        {
            var sticker = await _context.QrStickers
                .AsNoTracking()
                .Where(q => q.PersonId == personId && q.Status == QrStickerStatus.Active)
                .OrderByDescending(q => q.ActivatedAt)
                .FirstOrDefaultAsync(ct);

            if (sticker == null)
                return NotFound(new { message = "No active CallMeNow QR linked to this owner." });

            return await QrImage(sticker.PublicId, modulePixels, "scan", ct);
        }

        [HttpGet("by-owner/{personId:int}/base64")]
        public async Task<ActionResult<object>> QrBase64ForOwner(int personId, [FromQuery] int? modulePixels, CancellationToken ct)
        {
            var sticker = await _context.QrStickers
                .AsNoTracking()
                .Where(q => q.PersonId == personId && q.Status == QrStickerStatus.Active)
                .OrderByDescending(q => q.ActivatedAt)
                .FirstOrDefaultAsync(ct);

            if (sticker == null)
                return NotFound(new { message = "No active CallMeNow QR linked to this owner." });

            var baseUrl = _options.PublicBaseUrl.TrimEnd('/');
            var url = ResolveEmbedUrl(baseUrl, sticker.PublicId, "scan", sticker.Status);
            var bytes = QrPngRenderer.Render(url, modulePixels ?? 20);
            var base64 = Convert.ToBase64String(bytes);
            return Ok(new { qrCodeImage = $"data:image/png;base64,{base64}", qrText = url, publicId = sticker.PublicId });
        }

        private static string ResolveEmbedUrl(string baseUrl, string publicId, string? embed, QrStickerStatus status)
        {
            var mode = string.IsNullOrWhiteSpace(embed) ? "auto" : embed.Trim().ToLowerInvariant();
            if (mode == "activate")
                return $"{baseUrl}/activate/{Uri.EscapeDataString(publicId)}";
            if (mode == "scan")
                return $"{baseUrl}/q/{Uri.EscapeDataString(publicId)}";
            return status == QrStickerStatus.Unused
                ? $"{baseUrl}/activate/{Uri.EscapeDataString(publicId)}"
                : $"{baseUrl}/q/{Uri.EscapeDataString(publicId)}";
        }

        private void ApplyRazorpay(QrScanResponseDto dto)
        {
            var on = !string.IsNullOrWhiteSpace(_options.RazorpayKeyId) &&
                     !string.IsNullOrWhiteSpace(_options.RazorpayKeySecret);
            dto.RazorpayEnabled = on;
            dto.RazorpayKeyId = on ? _options.RazorpayKeyId : null;
        }

        private QrScanResponseDto BuildInactiveResponse(
            QrSticker sticker,
            string shareUrl,
            string activatePageUrl,
            string scanPageUrl)
        {
            return new QrScanResponseDto
            {
                PublicId = sticker.PublicId,
                Status = "invalid",
                ProductType = sticker.ProductType,
                ProductLabel = ProductLabel(sticker.ProductType),
                ScanCount = sticker.ScanCount,
                UniqueScannerCount = sticker.UniqueScannerCount,
                ActivatePageUrl = activatePageUrl,
                ScanPageUrl = scanPageUrl,
                Headline = "This tag cannot be used right now",
                Subtitle = "Owner record is missing. Contact support.",
                MaskingNote = string.Empty,
                TrustedOwnersLine = _options.TrustedOwnersLine,
                RegionTagline = _options.RegionTagline,
                ReferralCode = _options.ReferralCode,
                ReferralDiscountInr = _options.ReferralDiscountInr,
                StickerPriceInr = _options.StickerPriceInr,
                LocalizedCityLine = _options.LocalizedCityLine,
                ProductVariants = ProductVariants(),
                SharePageUrl = shareUrl,
                VehicleRegistration = null,
                EmergencyDialUri = null,
                EmergencyContactMasked = null,
                PrimaryPhoneType = null,
                EmergencyPhoneType = null
            };
        }

        private static string? MaskPhoneTail(string? phone)
        {
            var d = new string((phone ?? string.Empty).Where(char.IsDigit).ToArray());
            if (d.Length == 0)
                return null;
            if (d.Length <= 4)
                return "****" + d;
            return new string('*', Math.Min(6, d.Length - 4)) + d[^4..];
        }

        private static string NormalizePublicId(string publicId) => publicId.Trim().ToUpperInvariant();

        private static string? NormalizeTel(string phone)
        {
            var digits = new string(phone.Where(char.IsDigit).ToArray());
            return string.IsNullOrEmpty(digits) ? null : digits;
        }

        private static string? TelUriFromDid(string didRaw, string defaultIsd)
        {
            var s = didRaw.Trim();
            if (s.Length == 0)
                return null;
            if (s.StartsWith("tel:", StringComparison.OrdinalIgnoreCase))
                return s;
            var d = new string(s.Where(char.IsDigit).ToArray());
            if (d.Length == 0)
                return null;
            var cc = new string(defaultIsd.Where(char.IsDigit).ToArray());
            if (cc.Length == 0)
                cc = "91";
            if (cc == "91" && d.Length == 10)
                return "tel:+91" + d;
            if (d.StartsWith("00", StringComparison.Ordinal))
                return "tel:+" + d[2..];

            return "tel:+" + d;
        }

        private static string ProductLabel(string type) => type switch
        {
            "KeyFinder" => "Key Finder QR",
            "LuggageTag" => "Luggage QR Tag",
            _ => "Car QR Sticker"
        };

        private static IReadOnlyList<ProductVariantDto> ProductVariants() =>
        [
            new ProductVariantDto { Icon = "🚗", Name = "Car QR Sticker", SkuHint = "CarSticker" },
            new ProductVariantDto { Icon = "🔑", Name = "Key Finder QR", SkuHint = "KeyFinder" },
            new ProductVariantDto { Icon = "🧳", Name = "Luggage QR Tag", SkuHint = "LuggageTag" }
        ];
    }
}
