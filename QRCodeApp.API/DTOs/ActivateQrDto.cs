using System.ComponentModel.DataAnnotations;
using QRCodeApp.API.Validation;

namespace QRCodeApp.API.DTOs
{
    public class ActivateQrDto : IValidatableObject
    {
        [Required]
        [MaxLength(100)]
        public string Name { get; set; } = string.Empty;

        [Required]
        [MaxLength(20)]
        public string PhoneNumber { get; set; } = string.Empty;

        /// <summary>Mobile or Landline.</summary>
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

        [Required]
        [MaxLength(20)]
        public string VehicleRegistration { get; set; } = string.Empty;

        [Required]
        [MaxLength(20)]
        public string EmergencyContactPhone { get; set; } = string.Empty;

        [Required]
        [MaxLength(16)]
        public string EmergencyContactPhoneType { get; set; } = "Mobile";

        /// <summary>Gateway transaction id when you integrate Razorpay/Stripe.</summary>
        [MaxLength(120)]
        public string? PaymentReference { get; set; }

        /// <summary>Set true in dev or after your payment webhook confirms success.</summary>
        public bool PaymentCompleted { get; set; }

        /// <summary>After Razorpay Checkout success (required when keys are configured and PaymentCompleted is true).</summary>
        public string? RazorpayOrderId { get; set; }

        public string? RazorpayPaymentId { get; set; }

        public string? RazorpaySignature { get; set; }

        public IEnumerable<ValidationResult> Validate(ValidationContext validationContext)
        {
            if (!ContactFieldValidation.IsValidPersonName(Name))
                yield return new ValidationResult("Enter a valid full name.", [nameof(Name)]);

            if (!ContactFieldValidation.IsValidPersonName(FatherName))
                yield return new ValidationResult("Enter a valid father's name.", [nameof(FatherName)]);

            if (!ContactFieldValidation.IsValidAddress(Address))
                yield return new ValidationResult("Address must be at least 5 characters.", [nameof(Address)]);

            var ownerDigits = ContactFieldValidation.DigitsOnly(PhoneNumber);
            if (!ContactFieldValidation.IsValidPhoneForType(ownerDigits, PhoneNumberType))
                yield return new ValidationResult(
                    PhoneNumberType.Equals("Landline", StringComparison.OrdinalIgnoreCase)
                        ? "Owner landline: 8–11 digits."
                        : "Owner mobile: valid 10-digit Indian number (6–9…) or 91 + 10 digits.",
                    [nameof(PhoneNumber)]);

            var emDigits = ContactFieldValidation.DigitsOnly(EmergencyContactPhone);
            if (!ContactFieldValidation.IsValidPhoneForType(emDigits, EmergencyContactPhoneType))
                yield return new ValidationResult(
                    EmergencyContactPhoneType.Equals("Landline", StringComparison.OrdinalIgnoreCase)
                        ? "Emergency landline: 8–11 digits."
                        : "Emergency mobile: valid 10-digit Indian number.",
                    [nameof(EmergencyContactPhone)]);

            var reg = ContactFieldValidation.NormalizeVehicleRegistration(VehicleRegistration);
            if (!ContactFieldValidation.IsValidVehicleRegistration(reg))
                yield return new ValidationResult(
                    "Invalid vehicle registration. Example: DL01AB1234 (letters and digits only, no spaces).",
                    [nameof(VehicleRegistration)]);
        }
    }
}
