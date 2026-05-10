using System.ComponentModel.DataAnnotations;

namespace QRCodeApp.API.DTOs;

public class ExotelConnectOwnerDto
{
    /// <summary>Scanner's mobile — Exotel calls this number first, then connects to the owner.</summary>
    [Required]
    public string FromPhone { get; set; } = string.Empty;
}
