namespace QRCodeApp.API.DTOs;

public class CreateRazorpayOrderDto
{
    public string? PublicId { get; set; }

    public string? ReferralCode { get; set; }

    public decimal? Amount { get; set; }
}
