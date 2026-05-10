using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.Models
{
    public class ActiveCallMapping
    {
        public int Id { get; set; }

        [Required, MaxLength(20)]
        public string CallerPhoneNormalized { get; set; } = string.Empty;

        [Required, MaxLength(20)]
        public string TargetOwnerPhone { get; set; } = string.Empty;

        public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
        public DateTime ExpiryUtc { get; set; } = DateTime.UtcNow.AddMinutes(10);
    }
}
