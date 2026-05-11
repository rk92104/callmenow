using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.Models
{
    public class StickerOrder
    {
        [Key]
        public int Id { get; set; }

        [Required]
        [MaxLength(100)]
        public string CustomerName { get; set; } = string.Empty;

        [Required]
        [MaxLength(20)]
        public string CustomerPhone { get; set; } = string.Empty;

        [Required]
        [MaxLength(500)]
        public string ShippingAddress { get; set; } = string.Empty;

        [Required]
        [MaxLength(50)]
        public string City { get; set; } = string.Empty;

        [Required]
        [MaxLength(10)]
        public string Pincode { get; set; } = string.Empty;

        [Required]
        [MaxLength(50)]
        public string ProductId { get; set; } = "single";

        [Required]
        [MaxLength(100)]
        public string ProductName { get; set; } = "Solo Pack";

        public decimal Amount { get; set; }

        [MaxLength(20)]
        public string Status { get; set; } = "Pending"; // Pending, Paid, Shipped, Delivered

        [MaxLength(50)]
        public string? AssignedPublicId { get; set; }

        public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;

        [MaxLength(100)]
        public string? RazorpayOrderId { get; set; }

        [MaxLength(100)]
        public string? RazorpayPaymentId { get; set; }

        [MaxLength(200)]
        public string? RazorpaySignature { get; set; }
    }
}
