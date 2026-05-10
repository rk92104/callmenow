namespace QRCodeApp.API.DTOs
{
    public class QrScanResponseDto
    {
        public string PublicId { get; set; } = string.Empty;
        public string Status { get; set; } = string.Empty;
        public string ProductType { get; set; } = string.Empty;
        public string ProductLabel { get; set; } = string.Empty;
        public int ScanCount { get; set; }
        public int UniqueScannerCount { get; set; }
        public string ActivatePageUrl { get; set; } = string.Empty;
        public string ScanPageUrl { get; set; } = string.Empty;
        public string? VehicleRegistration { get; set; }
        public string? EmergencyDialUri { get; set; }
        public string? EmergencyContactMasked { get; set; }
        public string? OwnerPhoneMasked { get; set; }
        /// <summary>Mobile or Landline — owner primary number.</summary>
        public string? PrimaryPhoneType { get; set; }
        /// <summary>Mobile or Landline — emergency contact.</summary>
        public string? EmergencyPhoneType { get; set; }
        public string Headline { get; set; } = string.Empty;
        public string Subtitle { get; set; } = string.Empty;
        public string? DialUri { get; set; }
        /// <summary>When true, UI uses Exotel connect (server rings scanner first). When false, owner row uses tel: if <see cref="DialUri"/> is set.</summary>
        public bool OwnerConnectViaExotel { get; set; }

        /// <summary>When true with <see cref="OwnerConnectViaExotel"/>, Exotel rings owner before scanner.</summary>
        public bool ExotelRingOwnerFirst { get; set; }

        /// <summary>When true, only the owner is called (e.g. for a notification blast). Scanner's phone does not ring.</summary>
        public bool ExotelOwnerOnlyAlert { get; set; }

        public string? ExotelAppId { get; set; }
        public string? ExotelCallerId { get; set; }

        /// <summary>When set with <see cref="IvrAccessCode"/>, user can dial this ExoPhone and enter the code in IVR.</summary>
        public string? ExotelIvrDialUri { get; set; }

        /// <summary>Six-digit IVR keypad code for this sticker (shown on scan when Exotel IVR is configured).</summary>
        public string? IvrAccessCode { get; set; }
        public string? IvrEmergencyAccessCode { get; set; }
        public bool OwnerNumberHiddenOnPage { get; set; } = true;
        public string MaskingNote { get; set; } = string.Empty;
        public string TrustedOwnersLine { get; set; } = string.Empty;
        public string RegionTagline { get; set; } = string.Empty;
        public string ReferralCode { get; set; } = string.Empty;
        public int ReferralDiscountInr { get; set; }
        public int StickerPriceInr { get; set; }
        public string LocalizedCityLine { get; set; } = string.Empty;
        public IReadOnlyList<ProductVariantDto> ProductVariants { get; set; } = Array.Empty<ProductVariantDto>();
        /// <summary>Marketing share target (usually /q/…).</summary>
        public string SharePageUrl { get; set; } = string.Empty;

        /// <summary>True when server has Razorpay keys configured (Checkout available).</summary>
        public bool RazorpayEnabled { get; set; }

        /// <summary>Razorpay Key ID for Checkout.js when <see cref="RazorpayEnabled"/> is true.</summary>
        public string? RazorpayKeyId { get; set; }
    }

    public class ProductVariantDto
    {
        public string Icon { get; set; } = string.Empty;
        public string Name { get; set; } = string.Empty;
        public string SkuHint { get; set; } = string.Empty;
    }
}
