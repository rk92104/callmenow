using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.DTOs
{
    public class SubmitMarketingLeadDto
    {
        [Required]
        [MaxLength(20)]
        public string Phone { get; set; } = string.Empty;

        [MaxLength(40)]
        public string? PublicId { get; set; }

        [MaxLength(32)]
        public string? ReferralCode { get; set; }
    }
}
