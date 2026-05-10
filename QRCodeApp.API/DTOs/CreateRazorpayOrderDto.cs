namespace QRCodeApp.API.DTOs;

public class CreateRazorpayOrderDto
{
    public string PublicId { get; set; } = string.Empty;

    public string? ReferralCode { get; set; }
}
