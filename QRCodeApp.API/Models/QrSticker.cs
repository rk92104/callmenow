using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QRCodeApp.API.Models
{
    public class QrSticker
    {
        [Key]
        public int Id { get; set; }

        [Required]
        [MaxLength(40)]
        public string PublicId { get; set; } = string.Empty;

        /// <summary>Six-digit keypad code for Exotel Gather → Connect IVR.</summary>
        [MaxLength(6)]
        public string? IvrAccessCode { get; set; }

        /// <summary>Keypad code for Exotel → Emergency Contact.</summary>
        [MaxLength(6)]
        public string? IvrEmergencyAccessCode { get; set; }

        [Required]
        [MaxLength(50)]
        public string ProductType { get; set; } = string.Empty;

        public QrStickerStatus Status { get; set; } = QrStickerStatus.Unused;

        public int? PersonId { get; set; }

        [ForeignKey(nameof(PersonId))]
        public Person? Person { get; set; }

        public int ScanCount { get; set; }

        /// <summary>Distinct visitors (VisitorHash) seen at least once for this tag.</summary>
        public int UniqueScannerCount { get; set; }

        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

        public DateTime? ActivatedAt { get; set; }

        /// <summary>Razorpay payment id (<c>pay_…</c>) or offline payment reference stored at activation.</summary>
        [MaxLength(120)]
        public string? PaymentTransactionId { get; set; }
    }
}
