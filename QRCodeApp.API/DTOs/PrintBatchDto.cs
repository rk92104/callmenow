using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.DTOs
{
    public class PrintBatchDto
    {
        [Required]
        [MinLength(1)]
        public List<string> PublicIds { get; set; } = new();

        /// <summary>activate = packaging QR; scan = vehicle sticker layout (QR → /q), unused or active.</summary>
        [MaxLength(16)]
        public string Embed { get; set; } = "activate";

        /// <summary>horizontal (default) or vertical.</summary>
        [MaxLength(16)]
        public string Layout { get; set; } = "horizontal";
    }
}
