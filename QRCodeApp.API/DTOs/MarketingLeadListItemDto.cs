namespace QRCodeApp.API.DTOs
{
    public class MarketingLeadListItemDto
    {
        public int Id { get; set; }
        public string PhoneNormalized { get; set; } = string.Empty;
        public string? QrPublicId { get; set; }
        public string? ReferralCode { get; set; }
        public string Source { get; set; } = string.Empty;
        public DateTime CreatedAtUtc { get; set; }
    }
}
