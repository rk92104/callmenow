using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QRCodeApp.API.Data;
using QRCodeApp.API.Models;
using QRCodeApp.API.Services;

namespace QRCodeApp.API.Controllers
{
    [Route("api/ecomm")]
    [ApiController]
    public class StickerOrdersController : ControllerBase
    {
        private readonly AppDbContext _db;
        private readonly Options.CallMeNowOptions _options;

        public StickerOrdersController(AppDbContext db, Microsoft.Extensions.Options.IOptions<Options.CallMeNowOptions> options)
        {
            _db = db;
            _options = options.Value;
        }

        [HttpPost("book")]
        public async Task<IActionResult> BookSticker([FromBody] StickerOrder order)
        {
            if (order == null) return BadRequest();

            order.CreatedAtUtc = DateTime.UtcNow;
            order.Status = "Pending";

            // If we have payment details, store them
            // In a real prod app, we'd verify the signature here too, 
            // but for this sim/refinement we'll store them as evidence of the flow.

            // Automatically assign a fresh, unused sticker from inventory
            var sticker = await _db.QrStickers
                .FirstOrDefaultAsync(q => q.Status == QrStickerStatus.Unused && q.PersonId == null);
            
            if (sticker != null)
            {
                order.AssignedPublicId = sticker.PublicId;
                sticker.ProductType = order.ProductId; 
            }

            _db.StickerOrders.Add(order);
            await _db.SaveChangesAsync();

            return Ok(new { message = "Order placed successfully", orderId = order.Id });
        }

        [HttpGet("orders")]
        public IActionResult GetOrders()
        {
            // Simple retrieval for demo purposes
            var orders = _db.StickerOrders.OrderByDescending(o => o.CreatedAtUtc).Take(100).ToList();
            return Ok(orders);
        }

        [HttpPut("orders/{id}/status")]
        public async Task<IActionResult> UpdateStatus(int id, [FromBody] StatusUpdateDto dto)
        {
            var order = await _db.StickerOrders.FindAsync(id);
            if (order == null) return NotFound();
            
            order.Status = dto.Status;
            await _db.SaveChangesAsync();
            return Ok(new { message = "Status updated" });
        }

        [HttpGet("track")]
        public async Task<IActionResult> TrackOrder([FromQuery] string phone)
        {
            if (string.IsNullOrEmpty(phone)) return BadRequest("Phone number is required");
            
            var order = await _db.StickerOrders
                .Where(o => o.CustomerPhone == phone)
                .OrderByDescending(o => o.CreatedAtUtc)
                .Select(o => new {
                    o.Id,
                    o.CustomerName,
                    o.Status,
                    o.CreatedAtUtc,
                    o.ProductName,
                    o.AssignedPublicId
                })
                .FirstOrDefaultAsync();

            if (order == null) return NotFound("No order found for this phone number");
            
            return Ok(order);
        }

        [HttpGet("orders/{id}/print")]
        public async Task<IActionResult> PrintLabel(int id)
        {
            var order = await _db.StickerOrders.FindAsync(id);
            if (order == null) return NotFound();

            if (string.IsNullOrEmpty(order.AssignedPublicId))
            {
                // Assign one now if missed during booking
                var stickerRow = await _db.QrStickers
                    .FirstOrDefaultAsync(q => q.Status == QrStickerStatus.Unused && q.PersonId == null);
                
                if (stickerRow == null)
                {
                    // Emergency generation if inventory is empty
                    string nextId;
                    do { nextId = QrPublicIdGenerator.CreateNext(); } 
                    while (await _db.QrStickers.AnyAsync(q => q.PublicId == nextId));

                    stickerRow = new QrSticker { 
                        PublicId = nextId, 
                        Status = QrStickerStatus.Unused, 
                        CreatedAt = DateTime.UtcNow,
                        ProductType = order.ProductId 
                    };
                    _db.QrStickers.Add(stickerRow);
                }

                order.AssignedPublicId = stickerRow.PublicId;
                stickerRow.ProductType = order.ProductId;
                await _db.SaveChangesAsync();
            }

            var rows = new List<Services.QrLabelHtmlBuilder.LabelRow>();
            var isFamily = IsFamilyPack(order.ProductId);

            var row = new Services.QrLabelHtmlBuilder.LabelRow
            {
                PublicId = order.AssignedPublicId,
                OwnerName = order.CustomerName
            };

            if (isFamily)
            {
                // Family pack gets both layouts printed to match physical package
                rows.Add(new Services.QrLabelHtmlBuilder.LabelRow { PublicId = row.PublicId, OwnerName = row.OwnerName, ForceLayout = "horizontal" });
                rows.Add(new Services.QrLabelHtmlBuilder.LabelRow { PublicId = row.PublicId, OwnerName = row.OwnerName, ForceLayout = "vertical" });
            }
            else
            {
                rows.Add(row);
            }

            var html = Services.QrLabelHtmlBuilder.BuildDocument(
                rows,
                _options.PublicBaseUrl.TrimEnd('/'),
                "activate", 
                "horizontal");

            return Content(html, "text/html; charset=utf-8");
        }

        private static bool IsFamilyPack(string? productType)
        {
            if (string.IsNullOrWhiteSpace(productType)) return false;
            var t = productType.Trim().ToLowerInvariant();
            return t == "family" || t == "familypack" || t == "family pack";
        }
    }

    public class StatusUpdateDto
    {
        public string Status { get; set; } = string.Empty;
    }
}
