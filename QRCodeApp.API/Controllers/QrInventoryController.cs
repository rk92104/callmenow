using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Data;
using QRCodeApp.API.DTOs;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;
using QRCodeApp.API.Services;

namespace QRCodeApp.API.Controllers
{
    [Route("api/inventory/qr")]
    [ApiController]
    public class QrInventoryController : ControllerBase
    {
        private readonly AppDbContext _context;
        private readonly CallMeNowOptions _options;

        public QrInventoryController(AppDbContext context, IOptions<CallMeNowOptions> options)
        {
            _context = context;
            _options = options.Value;
        }

        [HttpGet]
        public async Task<ActionResult<QrInventoryListResponseDto>> List(
            [FromQuery] int page = 1,
            [FromQuery] int pageSize = 20,
            [FromQuery] string? from = null,
            [FromQuery] string? to = null,
            [FromQuery] string? search = null,
            [FromQuery] string? status = null,
            CancellationToken ct = default)
        {
            var baseUrl = _options.PublicBaseUrl.TrimEnd('/');
            page = Math.Max(1, page);
            pageSize = NormalizePageSize(pageSize);
            var fromDate = ParseDateQueryBound(from, false);
            var toDate = ParseDateQueryBound(to, true);

            var query = _context.QrStickers
                .AsNoTracking()
                .Include(q => q.Person)
                .AsQueryable();

            if (fromDate.HasValue)
            {
                query = query.Where(q => q.CreatedAt >= fromDate.Value);
            }
            if (toDate.HasValue)
            {
                query = query.Where(q => q.CreatedAt <= toDate.Value);
            }
            if (!string.IsNullOrWhiteSpace(search))
            {
                var term = search.Trim().ToUpperInvariant();
                query = query.Where(q => q.PublicId.Contains(term));
            }
            if (!string.IsNullOrWhiteSpace(status))
            {
                var normalized = status.Trim().ToLowerInvariant();
                if (normalized == "active")
                {
                    query = query.Where(q => q.Status == QrStickerStatus.Active);
                }
                else if (normalized == "unused")
                {
                    query = query.Where(q => q.Status == QrStickerStatus.Unused);
                }
            }

            var total = await query.CountAsync(ct);
            var rows = await query
                .OrderByDescending(q => q.CreatedAt)
                .Skip((page - 1) * pageSize)
                .Take(pageSize)
                .Select(q => new QrInventoryItemDto
                {
                    Id = q.Id,
                    PublicId = q.PublicId,
                    ProductType = q.ProductType,
                    Status = q.Status == QrStickerStatus.Active ? "Active" : "Unused",
                    ScanCount = q.ScanCount,
                    OwnerPersonId = q.PersonId,
                    OwnerName = q.Person != null ? q.Person.Name : null,
                    PaymentTransactionId = q.PaymentTransactionId,
                    CreatedAt = q.CreatedAt,
                    ActivatedAt = q.ActivatedAt,
                    ActivateUrl = $"{baseUrl}/activate/{Uri.EscapeDataString(q.PublicId)}",
                    ScanUrl = $"{baseUrl}/q/{Uri.EscapeDataString(q.PublicId)}",
                    PackagingQrImageApi = $"/api/qr/{Uri.EscapeDataString(q.PublicId)}/image?embed=activate&modulePixels=32",
                    StickerQrImageApi = $"/api/qr/{Uri.EscapeDataString(q.PublicId)}/image?embed=scan&modulePixels=32"
                })
                .ToListAsync(ct);

            return Ok(new QrInventoryListResponseDto
            {
                Items = rows,
                Total = total,
                Page = page,
                PageSize = pageSize
            });
        }

        [HttpGet("marketing-leads")]
        public async Task<ActionResult<IReadOnlyList<MarketingLeadListItemDto>>> ListLeads(
            [FromQuery] int take = 200,
            CancellationToken ct = default)
        {
            take = Math.Clamp(take, 1, 1000);
            var rows = await _context.MarketingLeads
                .AsNoTracking()
                .OrderByDescending(l => l.CreatedAtUtc)
                .Take(take)
                .Select(l => new MarketingLeadListItemDto
                {
                    Id = l.Id,
                    PhoneNormalized = l.PhoneNormalized,
                    QrPublicId = l.QrPublicId,
                    ReferralCode = l.ReferralCode,
                    Source = l.Source,
                    CreatedAtUtc = l.CreatedAtUtc
                })
                .ToListAsync(ct);

            return Ok(rows);
        }

        [HttpPost("generate")]
        public async Task<ActionResult<IReadOnlyList<QrInventoryItemDto>>> Generate([FromBody] GenerateQrInventoryDto dto, CancellationToken ct)
        {
            var count = Math.Clamp(dto.Count, 1, 500);
            var productType = string.IsNullOrWhiteSpace(dto.ProductType) ? "CarSticker" : dto.ProductType.Trim();

            var created = new List<QrSticker>();
            var batchIvr = new HashSet<string>();
            for (var i = 0; i < count; i++)
            {
                string publicId;
                do
                {
                    publicId = QrPublicIdGenerator.CreateNext();
                } while (await _context.QrStickers.AnyAsync(q => q.PublicId == publicId, ct));

                string ivr;
                do
                {
                    ivr = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct);
                } while (!batchIvr.Add(ivr));

                var row = new QrSticker
                {
                    PublicId = publicId,
                    IvrAccessCode = ivr,
                    ProductType = productType,
                    Status = QrStickerStatus.Unused,
                    CreatedAt = DateTime.UtcNow
                };
                _context.QrStickers.Add(row);
                created.Add(row);
            }

            await _context.SaveChangesAsync(ct);

            var baseUrl = _options.PublicBaseUrl.TrimEnd('/');
            var result = created.Select(q => new QrInventoryItemDto
            {
                Id = q.Id,
                PublicId = q.PublicId,
                ProductType = q.ProductType,
                Status = "Unused",
                ScanCount = q.ScanCount,
                OwnerPersonId = null,
                OwnerName = null,
                PaymentTransactionId = null,
                CreatedAt = q.CreatedAt,
                ActivatedAt = null,
                ActivateUrl = $"{baseUrl}/activate/{Uri.EscapeDataString(q.PublicId)}",
                ScanUrl = $"{baseUrl}/q/{Uri.EscapeDataString(q.PublicId)}",
                PackagingQrImageApi = $"/api/qr/{Uri.EscapeDataString(q.PublicId)}/image?embed=activate&modulePixels=32",
                StickerQrImageApi = $"/api/qr/{Uri.EscapeDataString(q.PublicId)}/image?embed=scan&modulePixels=32"
            }).ToList();

            return Ok(result);
        }

        /// <summary>One A4-style label: CallMeNow + vehicle + emergency + QR. embed=activate (packaging) or scan (car).</summary>
        [HttpGet("print/label/{publicId}")]
        public async Task<IActionResult> PrintLabel(string publicId, [FromQuery] string embed = "scan", [FromQuery] string layout = "horizontal", CancellationToken ct = default)
        {
            var norm = publicId.Trim().ToUpperInvariant();
            var mode = embed.Equals("scan", StringComparison.OrdinalIgnoreCase) ? "scan" : "activate";
            var sticker = await _context.QrStickers
                .AsNoTracking()
                .Include(q => q.Person)
                .FirstOrDefaultAsync(q => q.PublicId == norm, ct);
            if (sticker == null)
                return NotFound(new { message = "QR not found." });

            var html = QrLabelHtmlBuilder.BuildDocument(
                new List<QrLabelHtmlBuilder.LabelRow> { ToLabelRow(sticker) },
                _options.PublicBaseUrl.TrimEnd('/'),
                mode,
                layout);
            Response.Headers.Append("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, private");
            Response.Headers.Append("Pragma", "no-cache");
            Response.Headers.Append("Expires", "Thu, 19 Nov 1981 08:52:00 GMT");
            Response.Headers.Append("Vary", "*");
            return Content(html, "text/html; charset=utf-8");
        }

        /// <summary>Up to 500 labels in one print dialog (grid per A4 page).</summary>
        [HttpPost("print/batch")]
        public async Task<IActionResult> PrintBatch([FromBody] PrintBatchDto? dto, CancellationToken ct = default)
        {
            if (dto?.PublicIds == null || dto.PublicIds.Count == 0)
                return BadRequest(new { message = "PublicIds required (max 500)." });

            var mode = (dto.Embed ?? "activate").Equals("scan", StringComparison.OrdinalIgnoreCase) ? "scan" : "activate";
            var layout = (dto.Layout ?? "horizontal").Equals("vertical", StringComparison.OrdinalIgnoreCase) ? "vertical" : "horizontal";
            var ids = dto.PublicIds
                .Select(p => p.Trim().ToUpperInvariant())
                .Where(p => p.Length > 0)
                .Distinct()
                .Take(500)
                .ToList();

            var stickers = await _context.QrStickers
                .AsNoTracking()
                .Include(q => q.Person)
                .Where(q => ids.Contains(q.PublicId))
                .ToListAsync(ct);

            var map = stickers.ToDictionary(q => q.PublicId, q => q);
            var rows = new List<QrLabelHtmlBuilder.LabelRow>();
            foreach (var id in ids)
            {
                if (!map.TryGetValue(id, out var s))
                    continue;
                rows.Add(ToLabelRow(s));
            }

            if (rows.Count == 0)
                return BadRequest(new { message = "No stickers matched." });

            var html = QrLabelHtmlBuilder.BuildDocument(rows, _options.PublicBaseUrl.TrimEnd('/'), mode, layout);
            Response.Headers.Append("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, private");
            Response.Headers.Append("Pragma", "no-cache");
            Response.Headers.Append("Expires", "Thu, 19 Nov 1981 08:52:00 GMT");
            Response.Headers.Append("Vary", "*");
            return Content(html, "text/html; charset=utf-8");
        }

        [HttpPost("delete")]
        public async Task<ActionResult<DeleteQrInventoryResultDto>> DeleteSelected([FromBody] DeleteQrInventoryDto? dto, CancellationToken ct = default)
        {
            var ids = dto?.PublicIds?
                .Select(p => p.Trim().ToUpperInvariant())
                .Where(p => p.Length > 0)
                .Distinct()
                .Take(5000)
                .ToList() ?? new List<string>();

            if (ids.Count == 0)
            {
                return BadRequest(new { message = "PublicIds required." });
            }

            var rows = await _context.QrStickers
                .Where(q => ids.Contains(q.PublicId) && q.Status == QrStickerStatus.Unused)
                .ToListAsync(ct);

            if (rows.Count == 0)
            {
                return Ok(new DeleteQrInventoryResultDto { DeletedCount = 0, Message = "No stickers matched." });
            }

            _context.QrStickers.RemoveRange(rows);
            await _context.SaveChangesAsync(ct);

            return Ok(new DeleteQrInventoryResultDto
            {
                DeletedCount = rows.Count,
                Message = $"Deleted {rows.Count} sticker(s)."
            });
        }

        [HttpPost("delete-all")]
        public async Task<ActionResult<DeleteQrInventoryResultDto>> DeleteAll(
            [FromQuery] string? from = null,
            [FromQuery] string? to = null,
            CancellationToken ct = default)
        {
            var fromDate = ParseDateQueryBound(from, false);
            var toDate = ParseDateQueryBound(to, true);
            var query = _context.QrStickers.AsQueryable();
            query = query.Where(q => q.Status == QrStickerStatus.Unused);
            if (fromDate.HasValue)
            {
                query = query.Where(q => q.CreatedAt >= fromDate.Value);
            }
            if (toDate.HasValue)
            {
                query = query.Where(q => q.CreatedAt <= toDate.Value);
            }

            var rows = await query.ToListAsync(ct);
            if (rows.Count == 0)
            {
                return Ok(new DeleteQrInventoryResultDto { DeletedCount = 0, Message = "No stickers matched." });
            }

            _context.QrStickers.RemoveRange(rows);
            await _context.SaveChangesAsync(ct);

            return Ok(new DeleteQrInventoryResultDto
            {
                DeletedCount = rows.Count,
                Message = $"Deleted {rows.Count} sticker(s)."
            });
        }

        private static QrLabelHtmlBuilder.LabelRow ToLabelRow(QrSticker s)
        {
            return new QrLabelHtmlBuilder.LabelRow
            {
                PublicId = s.PublicId,
                VehicleRegistration = s.Person?.VehicleRegistration,
                EmergencyPhone = s.Person?.EmergencyContactPhone,
                OwnerName = s.Person?.Name
            };
        }

        private static int NormalizePageSize(int pageSize)
        {
            return pageSize switch
            {
                5 or 10 or 20 or 100 or 500 or 1000 or 2000 => pageSize,
                _ => 20
            };
        }

        private static DateTime? ParseDateQueryBound(string? raw, bool isToDate)
        {
            if (string.IsNullOrWhiteSpace(raw))
            {
                return null;
            }

            var value = raw.Trim();
            if (DateTime.TryParseExact(value, "yyyy-MM-dd", null, System.Globalization.DateTimeStyles.None, out var dateOnly))
            {
                return isToDate ? dateOnly.Date.AddDays(1).AddTicks(-1) : dateOnly.Date;
            }

            return DateTime.TryParse(value, out var parsed) ? parsed : null;
        }
    }
}
