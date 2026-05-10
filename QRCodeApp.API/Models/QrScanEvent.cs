using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace QRCodeApp.API.Models
{
    /// <summary>Every /scan hit — powers total scans &amp; unique scanner estimates.</summary>
    public class QrScanEvent
    {
        [Key]
        public long Id { get; set; }

        public int QrStickerId { get; set; }

        [ForeignKey(nameof(QrStickerId))]
        public QrSticker? QrSticker { get; set; }

        [Required]
        [MaxLength(64)]
        public string VisitorHash { get; set; } = string.Empty;

        public DateTime ScannedAtUtc { get; set; } = DateTime.UtcNow;

        [MaxLength(256)]
        public string? UserAgentSnippet { get; set; }
    }
}
