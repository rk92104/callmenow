namespace QRCodeApp.API.Options
{
    public class CallMeNowOptions
    {
        public const string SectionName = "CallMeNow";

        public string PublicBaseUrl { get; set; } = "http://localhost:4200";
        public string TrustedOwnersLine { get; set; } = "Trusted by 12,000+ vehicle owners";
        public string RegionTagline { get; set; } = "Used across Chandigarh & Tricity";
        public string ReferralCode { get; set; } = "SCAN50";
        public int StickerPriceInr { get; set; } = 299;
        public int ReferralDiscountInr { get; set; } = 50;
        public string LocalizedCityLine { get; set; } = "People in Chandigarh use CallMeNow to solve parking problems — without sharing their number.";

        /// <summary>Razorpay Key ID (public). Leave empty to disable Checkout on activate.</summary>
        public string RazorpayKeyId { get; set; } = string.Empty;

        /// <summary>Razorpay Key Secret — server only, never expose to the browser.</summary>
        public string RazorpayKeySecret { get; set; } = string.Empty;

        /// <summary>Optional: Exotel masked two-leg calling for scan page (Connect API).</summary>
        public ExotelOptions Exotel { get; set; } = new();
    }

    public class ExotelOptions
    {
        /// <summary>Exotel Account SID (API settings page — not the API Key).</summary>
        public string AccountSid { get; set; } = string.Empty;

        /// <summary>API Key (username) from Exotel dashboard.</summary>
        public string ApiKey { get; set; } = string.Empty;

        /// <summary>API Token (password) — server only.</summary>
        public string ApiToken { get; set; } = string.Empty;

        /// <summary>api.exotel.com (Singapore) or api.in.exotel.com (Mumbai).</summary>
        public string Subdomain { get; set; } = "api.exotel.com";

        /// <summary>Your ExoPhone / company number (digits; Exotel shows this as CLI).</summary>
        public string CallerId { get; set; } = string.Empty;

        public string CallType { get; set; } = "trans";

        /// <summary>Default ISD when normalizing numbers (India: 91).</summary>
        public string DefaultIsd { get; set; } = "91";

        /// <summary>ExoPhone DID that callers dial for IVR (digits or +E.164). Scan page shows tel: when set.</summary>
        public string IvrDid { get; set; } = string.Empty;

        /// <summary>Optional: Gather/Connect URLs must include <c>?secret=</c> matching this value.</summary>
        public string IvrWebhookSecret { get; set; } = string.Empty;

        /// <summary>When true, Exotel Connect rings the owner (From) first, then the scanner (To). When false, scanner first (default).</summary>
        public bool RingOwnerFirst { get; set; }

        /// <summary>When true with IVR DID + code, scan page uses <c>tel:</c> to the IVR line only (no web phone form).</summary>
        public bool ScanDirectTel { get; set; }

        /// <summary>Optional full Gather <c>gather_prompt</c> text (Exotel plays this at Gather; put a greeting + instructions in one line, or use a Greeting applet before Gather in the flow builder).</summary>
        public string IvrGatherPrompt { get; set; } = string.Empty;

        /// <summary>Optional Gather repeat prompt when caller enters nothing.</summary>
        public string IvrGatherRepeatPrompt { get; set; } = string.Empty;

        /// <summary>Optional: Exotel Flow URL for notify-only calling (via customer_to_flow).</summary>
        public string OwnerAlertAppUrl { get; set; } = string.Empty;

        /// <summary>Exotel Application ID for WebRTC/Voice SDK (Browser call).</summary>
        public string AppId { get; set; } = string.Empty;
    }
}
