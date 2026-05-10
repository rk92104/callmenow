using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.Models
{
    /// <summary>Phone captured from scan-page coupon flow (lead capture).</summary>
    public class MarketingLead
    {
        [Key]
        public int Id { get; set; }

        [Required]
        [MaxLength(20)]
        public string PhoneNormalized { get; set; } = string.Empty;

        [MaxLength(40)]
        public string? QrPublicId { get; set; }

        [MaxLength(32)]
        public string? ReferralCode { get; set; }

        [MaxLength(64)]
        public string Source { get; set; } = "scan_page_coupon";

        public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    }
}
