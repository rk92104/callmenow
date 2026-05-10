namespace QRCodeApp.API.DTOs
{
    public class QrInventoryItemDto
    {
        public int Id { get; set; }
        public string PublicId { get; set; } = string.Empty;
        public string ProductType { get; set; } = string.Empty;
        public string Status { get; set; } = string.Empty;
        public int ScanCount { get; set; }
        public int? OwnerPersonId { get; set; }
        public string? OwnerName { get; set; }

        /// <summary>Razorpay <c>pay_…</c> id or offline payment reference from activation.</summary>
        public string? PaymentTransactionId { get; set; }

        public DateTime CreatedAt { get; set; }
        public DateTime? ActivatedAt { get; set; }
        public string ActivateUrl { get; set; } = string.Empty;
        public string ScanUrl { get; set; } = string.Empty;
        /// <summary>PNG API — encodes /activate/… (packaging / pre-sale print).</summary>
        public string PackagingQrImageApi { get; set; } = string.Empty;
        /// <summary>PNG API — encodes /q/… (vehicle sticker after go-live).</summary>
        public string StickerQrImageApi { get; set; } = string.Empty;
    }

    public class GenerateQrInventoryDto
    {
        public int Count { get; set; } = 1;
        public string ProductType { get; set; } = "CarSticker";
    }

    public class QrInventoryListResponseDto
    {
        public IReadOnlyList<QrInventoryItemDto> Items { get; set; } = Array.Empty<QrInventoryItemDto>();
        public int Total { get; set; }
        public int Page { get; set; }
        public int PageSize { get; set; }
    }

    public class DeleteQrInventoryDto
    {
        public List<string> PublicIds { get; set; } = new();
    }

    public class DeleteQrInventoryResultDto
    {
        public int DeletedCount { get; set; }
        public string Message { get; set; } = string.Empty;
    }
}
