using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.Models
{
    public class Person
    {
        [Key]
        public int Id { get; set; }

        [Required]
        [MaxLength(100)]
        public string Name { get; set; } = string.Empty;

        [Required]
        [MaxLength(20)]
        public string PhoneNumber { get; set; } = string.Empty;

        /// <summary>Mobile or Landline — how scanners should treat the owner number.</summary>
        [Required]
        [MaxLength(16)]
        public string PhoneNumberType { get; set; } = "Mobile";

        [Required]
        [EmailAddress]
        [MaxLength(150)]
        public string Email { get; set; } = string.Empty;

        [Required]
        [MaxLength(300)]
        public string Address { get; set; } = string.Empty;

        [Required]
        [MaxLength(100)]
        public string FatherName { get; set; } = string.Empty;

        [MaxLength(20)]
        public string VehicleRegistration { get; set; } = string.Empty;

        [MaxLength(20)]
        public string EmergencyContactPhone { get; set; } = string.Empty;

        [Required]
        [MaxLength(16)]
        public string EmergencyContactPhoneType { get; set; } = "Mobile";

        /// <summary>Demo / gateway flag recorded at sticker activation.</summary>
        public bool PaymentCompleted { get; set; }

        [MaxLength(120)]
        public string? PaymentReference { get; set; }

        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
        
        public DateTime? UpdatedAt { get; set; }
    }
}
