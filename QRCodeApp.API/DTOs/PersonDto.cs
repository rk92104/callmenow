using System.ComponentModel.DataAnnotations;
using QRCodeApp.API.Validation;

namespace QRCodeApp.API.DTOs
{
    public class PersonDto : IValidatableObject
    {
        [Required(ErrorMessage = "Name is required")]
        [MaxLength(100)]
        public string Name { get; set; } = string.Empty;

        [Required(ErrorMessage = "Phone Number is required")]
        [MaxLength(20)]
        public string PhoneNumber { get; set; } = string.Empty;

        [MaxLength(16)]
        public string PhoneNumberType { get; set; } = "Mobile";

        [Required(ErrorMessage = "Email is required")]
        [EmailAddress(ErrorMessage = "Invalid email format")]
        [MaxLength(150)]
        public string Email { get; set; } = string.Empty;

        [Required(ErrorMessage = "Address is required")]
        [MaxLength(300)]
        public string Address { get; set; } = string.Empty;

        [Required(ErrorMessage = "Father Name is required")]
        [MaxLength(100)]
        public string FatherName { get; set; } = string.Empty;

        [Required(ErrorMessage = "Vehicle registration is required")]
        [MaxLength(20)]
        public string VehicleRegistration { get; set; } = string.Empty;

        [Required(ErrorMessage = "Emergency contact number is required")]
        [MaxLength(20)]
        public string EmergencyContactPhone { get; set; } = string.Empty;

        [MaxLength(16)]
        public string EmergencyContactPhoneType { get; set; } = "Mobile";

        public IEnumerable<ValidationResult> Validate(ValidationContext validationContext)
        {
            if (!ContactFieldValidation.IsValidPersonName(Name))
                yield return new ValidationResult("Enter a valid full name (at least 2 characters, including letters).", [nameof(Name)]);

            if (!ContactFieldValidation.IsValidPersonName(FatherName))
                yield return new ValidationResult("Enter a valid father's name (at least 2 characters, including letters).", [nameof(FatherName)]);

            if (!ContactFieldValidation.IsValidAddress(Address))
                yield return new ValidationResult("Address must be at least 5 characters.", [nameof(Address)]);

            var ownerDigits = ContactFieldValidation.DigitsOnly(PhoneNumber);
            if (!ContactFieldValidation.IsValidPhoneForType(ownerDigits, PhoneNumberType))
                yield return new ValidationResult(
                    PhoneNumberType.Equals("Landline", StringComparison.OrdinalIgnoreCase)
                        ? "Landline: enter 8–11 digits (STD + number)."
                        : "Mobile: enter a valid 10-digit Indian number (starting 6–9), or 12 digits with 91 prefix.",
                    [nameof(PhoneNumber)]);

            var emDigits = ContactFieldValidation.DigitsOnly(EmergencyContactPhone);
            if (!ContactFieldValidation.IsValidPhoneForType(emDigits, EmergencyContactPhoneType))
                yield return new ValidationResult(
                    EmergencyContactPhoneType.Equals("Landline", StringComparison.OrdinalIgnoreCase)
                        ? "Emergency landline: 8–11 digits."
                        : "Emergency mobile: valid 10-digit Indian number (or 91 + 10 digits).",
                    [nameof(EmergencyContactPhone)]);

            var reg = ContactFieldValidation.NormalizeVehicleRegistration(VehicleRegistration);
            if (!ContactFieldValidation.IsValidVehicleRegistration(reg))
                yield return new ValidationResult(
                    "Vehicle registration looks invalid. Use format like DL01AB1234 (state + district + series + number), no spaces.",
                    [nameof(VehicleRegistration)]);
        }
    }
}
