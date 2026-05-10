using System.Net;
using System.Text;

namespace QRCodeApp.API.Services
{
    public static class QrLabelHtmlBuilder
    {
        // A3 pages are much larger than A4; fill the page to reduce total sheets.
        private const int HorizontalCardsPerPageA3 = 28; // 4 columns × 7 rows
        private const int VerticalCardsPerPageA3 = 25; // 4 columns × 5 rows

        private const int QrModulePixels = 14;

        /// <summary>Sticker orange — matches physical label / public scan preview (#EF9523).</summary>
        private const string StickerOrange = "#ef9523";

        private static readonly string PrintCssHorizontal =
            """
            /* A3 landscape: 4×7 stickers @ 10.19×4 cm; tighter side margins so four widths fit. */
            @page{size:A3 landscape;margin:8mm 6mm;}
            *{box-sizing:border-box;}
            body{margin:0;background:#e0e0e0;}
            @media print{
              *{
                -webkit-print-color-adjust:exact !important;
                print-color-adjust:exact !important;
                color-adjust:exact !important;
              }
              body{background:#fff !important;}
              .page{page-break-after:always;}
              .page:last-child{page-break-after:auto;}
            }
            .page{padding:2mm 0;max-width:408mm;margin:0 auto;}
            .grid{
              display:grid;
              grid-template-columns:repeat(4,minmax(0,10.19cm));
              justify-content:center;
              gap:0.13mm 0.13mm;
              align-content:start;
            }
            .grid.grid--vertical{
              grid-template-columns:repeat(3,minmax(0,70mm));
              gap:5mm 5mm;
              align-content:start;
            }
            .card{
              break-inside:avoid;
              display:grid;
              grid-template-columns:31fr 69fr;
              gap:1.25mm 1.58mm;
              align-items:stretch;
              width:10.19cm;
              max-width:10.19cm;
              min-height:4cm;
              height:4cm;
              padding:1.5mm 1.58mm 1.25mm;
              font-family:Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
              background:#fff;
              border:0.15mm solid #d1d5db;
              border-radius:4.6mm;
              box-shadow:none;
              overflow:hidden;
              -webkit-print-color-adjust:exact;
              print-color-adjust:exact;
            }
            .card.card--vertical{
              width:70mm;
              max-width:70mm;
              min-height:100mm;
              height: 95mm;
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              padding:3.5mm 3.5mm 3mm;
            }
            .card.card--vertical .sticker-v-stack{
              width:100%;
              flex:1 1 auto;
              min-height:0;
              display:flex;
              flex-direction:column;
              align-items:center;
              text-align:center;
            }
            .sticker-qr-col{
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              min-width:0;
              background:#fff;
            }
            .card.card--vertical .sticker-v-stack .sticker-qr-col img{
                  width: 68mm;
                        height: 61mm;
                   margin-top: -2.5mm;
            }
            .card.card--vertical .sticker-qr-col{
              width: 68mm;
            }
            .card.card--vertical .sticker-code{
                 width: 100%;
                 max-width: 100%;
                 margin-top: -1.2mm;
                 margin-bottom: 1px;
            }
            .card.card--vertical .sticker-copy-col{
              width:100%;
              align-items:center;
              justify-content:flex-start;
              text-align:center;
             margin-top: -13px;
            }
            .card.card--vertical .sticker-headline{
              margin-top: 1.5mm;
              width: 100%;
              /* max-width: 108mm; */
              font-size: 13px;
              font-weight: 700;
              line-height: 1.05;
              letter-spacing: 0;
              text-transform: uppercase;
              font-family:'Arial Narrow',Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
              font-stretch:condensed;
              color:#000;
            }
            .card.card--vertical .sticker-rule{
               width: 98%;
               margin-top: -2mm !important;
               height: 0.6mm;
               margin: 1mm auto 0 !important;
               margin-right: auto !important;
              margin-left: auto !important;
              background: #ef9523 !important;
            }
            .card.card--vertical .sticker-blurb-wrap{
              margin-top:2.6mm;
              width:90%;
              max-width:60mm;
            }
            .card.card--vertical .sticker-blurb{
                 margin: 0 !important;
            font-size: 12px;
            line-height: 1;
            font-weight: 500;
            text-align: center;
            text-transform: none;
            color: #000;
            margin-top: 0px !important;
            width: 252px;
            }
            .card.card--vertical .sticker-brand{
                 margin-top: 3.5mm;
            padding-top: -1.5mm;
            align-items: center;
            margin-bottom: -21px;
            }
            .card.card--vertical .brand-logo-img{
                  margin-right: 0;
                  margin-top: 0;
                  margin-bottom: 0;
                  width: 36mm;
                  height: auto; 
            }
            
            .sticker-qr-col img{
                 width:32.04mm;
                height:33.63mm;
                display: block;
                margin-top: 0px;
                image-rendering:pixelated;
                image-rendering:crisp-edges;
            }
            .sticker-code{
              margin: -5px;
              font-size:7.7pt;
              font-weight: 800;
              letter-spacing:.02em;
              color:#000;
              text-align:center;
              line-height:1;
              word-break:break-all;
              max-width:24.16mm;
            }
            .sticker-copy-col{
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              min-width:0;
              text-align:left;
              height:100%;
              width:97%;
              background:#fff;
            }
            .sticker-headline{
              margin-top:17.5px;
              width:100%;
              font-size: 14.25px;
              font-weight:bold;
              line-height:0.95;
              letter-spacing:0;
              color:#000;
              text-transform:uppercase;
              font-family:'Arial Narrow', Arial, Helvetica, sans-serif;
              font-stretch:condensed;
            }
            .sticker-rule{
                  height: 0.625mm;
                  width: 98%;
                  background: #ef9523 !important;
                  margin-right: 4.7px !important;
                  margin-top: -8.75px !important;
            }
            .sticker-blurb-wrap{
              display: flex;
              min-height: 0;
              margin-top:8.75px;
            }
            .sticker-blurb{
              margin:0;
              font-size:15.5px;
              font-weight:500;
              line-height:1.2;
              letter-spacing:0;
              color:#000;
              text-align:center;
            }
            .sticker-brand{
              display:flex;
              justify-content:center;
              align-items:flex-end;
              width:100%;
              padding-top:2.5mm;
              padding-bottom:0mm;
              flex: 1 1 auto;
            }
            .sticker-brand svg{
              width:46.22mm;
              height:auto;
              display:block;
            }
            .brand-logo-img{
                   width:166px;
                   margin-bottom:10px;
                   margin-top:2.5px;
                   margin-right:12.6px;
                }

            """;

        private static readonly string PrintCssVertical =
            """
            /* A3 portrait: 5×5 vertical stickers @ 5.5×7.48 cm */
            @page{size:A3;margin:8mm;}
            *{box-sizing:border-box;}
            body{margin:0;background:#e0e0e0;}
            @media print{
              *{
                -webkit-print-color-adjust:exact !important;
                print-color-adjust:exact !important;
                color-adjust:exact !important;
              }
              body{background:#fff !important;}
              .page{page-break-after:always;}
              .page:last-child{page-break-after:auto;}
            }
            .page{padding:2mm 0;max-width:297mm;margin:0 auto;}
            .grid{
              display:grid;
              grid-template-columns:repeat(4,minmax(0,10.19cm));
              justify-content:center;
              gap:0.13mm 0.13mm;
              align-content:start;
            }
            .grid.grid--vertical{
              grid-template-columns:repeat(5,5.5cm);
              gap:1.5mm 1.5mm;
              align-content:start;
            }
            .card{
              break-inside:avoid;
              display:grid;
              grid-template-columns:31fr 69fr;
              gap:1.25mm 1.58mm;
              align-items:stretch;
              width:10.19cm;
              max-width:10.19cm;
              min-height:4cm;
              height:4cm;
              padding:1.5mm 1.58mm 1.25mm;
              font-family:Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
              background:#fff;
              border:0.15mm solid #d1d5db;
              border-radius:4.6mm;
              box-shadow:none;
              overflow:hidden;
              -webkit-print-color-adjust:exact;
              print-color-adjust:exact;
            }
            .card.card--vertical{
              width:5.5cm;
              max-width:5.5cm;
              min-height:7.48cm;
              height:7.48cm;
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              padding:2.2mm 2.2mm 2mm;
              overflow:hidden;
            }
            .card.card--vertical .sticker-v-stack{
              width:100%;
              flex:1 1 auto;
              min-height:0;
              display:flex;
              flex-direction:column;
              align-items:center;
              text-align:center;
            }
            .sticker-qr-col{
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              min-width:0;
              background:#fff;
            }
            .card.card--vertical .sticker-v-stack .sticker-qr-col img{
                  width: 48mm;
                  height: 42mm;
                  margin-top: 0;
            }
            .card.card--vertical .sticker-qr-col{
              width: 48mm;
            }
            .card.card--vertical .sticker-code{
                  width: 100%;
                  max-width: 100%;
                 margin-top: -1.2mm;
                  margin-bottom: 1px;
            }
            .card.card--vertical .sticker-copy-col{
              width:100%;
              align-items:center;
              justify-content:flex-start;
              text-align:center;
              margin-top: 0;
            }
            .card.card--vertical .sticker-headline{
              margin-top: 1.5mm;
              width: 100%;
              /* max-width: 108mm; */
              font-size: 11px;
              font-weight: 700;
              line-height: 1.05;
              letter-spacing: 0;
              text-transform: uppercase;
              font-family:'Arial Narrow',Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
              font-stretch:condensed;
              color:#000;
            }
            .card.card--vertical .sticker-rule{
                  width: 98%;
              margin-top: -1.4mm !important;
              height: 0.55mm;
              margin-bottom: 6px !important;
              margin: 0.6mm auto 0;
              margin-right: auto !important;
              margin-left: auto !important;
              background: #ef9523;
            }
            .card.card--vertical .sticker-blurb-wrap{
              margin-top:0.8mm;
              width:100%;
              max-width:52mm;
            }
            .card.card--vertical .sticker-blurb{
              margin: 0 !important;
              font-size: 12px;
              line-height: 1.1;
              font-weight: 500;
              text-align: center;
              text-transform: none;
              color: #000;
              width: 100%;
            }
            .card.card--vertical .sticker-brand{
              margin-top: 0.9mm;
              padding-top: 0;
              align-items: center;
              margin-bottom: 0;
            }
            .card.card--vertical .brand-logo-img{
                 margin-right: 0;
                 margin-top: 0;
                 margin-bottom: 0;
                 width: 36mm;
                 height: auto;
            }
            
            .sticker-qr-col img{
                 width:32.04mm;
                height:33.63mm;
                display: block;
                margin-top: 0px;
                image-rendering:pixelated;
                image-rendering:crisp-edges;
            }
            .sticker-code{
              margin: -5px;
              font-size:7.7pt;
              font-weight: 800;
              letter-spacing:.02em;
              color:#000;
              text-align:center;
              line-height:1;
              word-break:break-all;
              max-width:24.16mm;
            }
            .sticker-copy-col{
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:flex-start;
              min-width:0;
              text-align:left;
              height:100%;
              width:100%;
              background:#fff;
            }
            .sticker-headline{
              margin-top:17.5px;
              width:100%;
              font-size: 14.25px;
              font-weight:bold;
              line-height:0.95;
              letter-spacing:0;
              color:#000;
              text-transform:uppercase;
              font-family:'Arial Narrow', Arial, Helvetica, sans-serif;
              font-stretch:condensed;
            }
            .sticker-rule{
                  height: 0.625mm;
                  width: 98%;
                  background: #ef9523 !important;
                  margin-right: 4.7px !important;
                 margin-top: -8.75px !important  ;
            }
            .sticker-blurb-wrap{
              display: flex;
              min-height: 0;
              margin-top:8.75px;
            }
            .sticker-blurb{
              margin: 0;
              font-size:15.5px;
              font-weight: 500;
              line-height: 1.2;
              letter-spacing: 0;
              color: #000;
              text-align: center;
            }
            .sticker-brand{
              display:flex;
              justify-content:center;
              align-items:flex-end;
              width:100%;
              padding-top:2.5mm;
              padding-bottom:0mm;
              flex: 1 1 auto;
            }
            .sticker-brand svg{
              width:46.22mm;
              height:auto;
              display:block;
            }
            .brand-logo-img{
                   width:166px;
                   margin-bottom:10px;
                   margin-top:2.5px;
                   margin-right:12.6px;
                }

            """;

        public static string BuildDocument(
            IReadOnlyList<LabelRow> rows,
            string absoluteBaseUrl,
            string embed,
            string layout = "horizontal")
        {
            embed = embed.Equals("scan", StringComparison.OrdinalIgnoreCase) ? "scan" : "activate";
            layout = layout.Equals("vertical", StringComparison.OrdinalIgnoreCase) ? "vertical" : "horizontal";
            var cardsPerPage = layout == "vertical" ? VerticalCardsPerPageA3 : HorizontalCardsPerPageA3;
            var sb = new StringBuilder();
            sb.Append("<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"/><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"/>");
            sb.Append("<meta name=\"color-scheme\" content=\"light\"/><meta name=\"cmn-print-label\" content=\"sticker-h1019x40-v55x748cm-a3\"/>");
            sb.Append("<title>CallMeNow — print</title><style>");
            sb.Append(layout == "vertical" ? PrintCssVertical : PrintCssHorizontal);
            sb.Append("</style></head><body>");

            for (var i = 0; i < rows.Count; i += cardsPerPage)
            {
                var gridClass = layout == "vertical" ? "grid grid--vertical" : "grid";
                sb.Append($"<div class=\"page\"><div class=\"{gridClass}\">");
                foreach (var row in rows.Skip(i).Take(cardsPerPage))
                    sb.Append(BuildCard(row, absoluteBaseUrl, embed, layout));
                sb.Append("</div></div>");
            }

            sb.Append("""
                <script>
                (function(){
                  function waitForImages(timeoutMs){
                    var imgs = Array.prototype.slice.call(document.images || []);
                    if (!imgs.length) return Promise.resolve();
                    var pending = imgs.filter(function(i){ return !i.complete; });
                    if (!pending.length) return Promise.resolve();
                    return new Promise(function(resolve){
                      var done = false;
                      function finish(){ if (done) return; done = true; resolve(); }
                      var t = setTimeout(finish, timeoutMs || 2000);
                      var iv = setInterval(function(){
                        if (pending.every(function(i){ return i.complete; })) { clearTimeout(t); clearInterval(iv); finish(); }
                      }, 50);
                    });
                  }
                  window.addEventListener('load', function(){
                    waitForImages(2000).then(function(){
                      setTimeout(function(){ window.print(); }, 150);
                    });
                  });
                })();
                </script>
                """);
            sb.Append("</body></html>");
            return sb.ToString();
        }

        private static string EncodeQrPayload(string publicBaseUrl, string publicId, string embed)
        {
            var b = publicBaseUrl.TrimEnd('/');
            var enc = Uri.EscapeDataString(publicId);
            return embed == "scan" ? $"{b}/q/{enc}" : $"{b}/activate/{enc}";
        }

        private static string QrDataUri(string publicBaseUrl, string publicId, string embed)
        {
            var url = EncodeQrPayload(publicBaseUrl, publicId, embed);
            var png = QrPngRenderer.Render(url, QrModulePixels);
            return "data:image/png;base64," + Convert.ToBase64String(png);
        }

        private static string QrHttpImageSrc(string publicBaseUrl, string publicId, string embed)
        {
            var b = publicBaseUrl.TrimEnd('/');
            var enc = Uri.EscapeDataString(publicId);
            var e = embed == "scan" ? "scan" : "activate";
            return $"{b}/api/qr/{enc}/image?embed={e}&modulePixels=12";
        }

        private static string StickerBrandSvg(string absoluteBaseUrl)
        {
            var b = absoluteBaseUrl.TrimEnd('/');
            var logo = WebUtility.HtmlEncode($"{b}/assets/marketing/scannerlogo.png");
            return $"""
                <div class="sticker-brand">
                <img src="{logo}" alt="CallMeNow" class="brand-logo-img" loading="eager" decoding="async">
                </div>
                """;
        }

        private static string FormatStickerDisplayCode(string publicId)
        {
            var t = publicId.Trim().ToUpperInvariant();
            var sb = new StringBuilder(t.Length);
            foreach (var c in t)
            {
                if (c is >= 'A' and <= 'Z' or >= '0' and <= '9')
                    sb.Append(c);
            }

            var u = sb.ToString();
            if (u.Length == 0)
                return publicId.Trim();
            return u.StartsWith("CMN", StringComparison.Ordinal) ? u : "CMN" + u;
        }

        private static string BuildCard(LabelRow row, string baseUrl, string embed, string layout)
        {
            var dataUri = QrDataUri(baseUrl, row.PublicId, embed);
            var srcEsc = WebUtility.HtmlEncode(dataUri);
            var httpQrEsc = WebUtility.HtmlEncode(QrHttpImageSrc(baseUrl, row.PublicId, embed));
            var pid = WebUtility.HtmlEncode(FormatStickerDisplayCode(row.PublicId));

            var sb = new StringBuilder();
            var cardClass = layout == "vertical" ? "card card--vertical" : "card";
            sb.Append($"<div class=\"{cardClass}\">");

            if (layout == "vertical")
            {
                sb.Append("<div class=\"sticker-v-stack\">");
                sb.Append("<div class=\"sticker-qr-col\">");
                sb.Append($"<img src=\"{srcEsc}\" alt=\"\" width=\"160\" height=\"160\" onerror=\"this.onerror=null;this.src='{httpQrEsc}'\"/>");
                if (embed != "scan")
                    sb.Append($"<p class=\"sticker-code\">{pid}</p>");
                sb.Append("</div>");

                sb.Append("<div class=\"sticker-copy-col\">");
                sb.Append("<p class=\"sticker-headline\">SCAN TO CONTACT THE VEHICLE OWNER</p>");
                sb.Append("<div class=\"sticker-rule\" aria-hidden=\"true\"></div>");
                sb.Append("<div class=\"sticker-blurb-wrap\"><p class=\"sticker-blurb\">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>");
                sb.Append(StickerBrandSvg(baseUrl));
                sb.Append("</div>");
                sb.Append("</div>");
                sb.Append("</div>");
                return sb.ToString();
            }

            sb.Append("<div class=\"sticker-qr-col\">");
            sb.Append($"<img src=\"{srcEsc}\" alt=\"\" width=\"160\" height=\"160\" onerror=\"this.onerror=null;this.src='{httpQrEsc}'\"/>");
            if (embed != "scan")
                sb.Append($"<p class=\"sticker-code\">{pid}</p>");
            sb.Append("</div>");
            sb.Append("<div class=\"sticker-copy-col\">");

            if (embed == "scan")
            {
                sb.Append("<p class=\"sticker-headline\">SCAN TO CONTACT THE VEHICLE OWNER</p>");
                sb.Append("<div class=\"sticker-rule\" aria-hidden=\"true\"></div>");
                sb.Append("<div class=\"sticker-blurb-wrap\"><p class=\"sticker-blurb\">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>");
            }
            else
            {
                 sb.Append("<p class=\"sticker-headline\">SCAN TO CONTACT THE VEHICLE OWNER</p>");
                sb.Append("<div class=\"sticker-rule\" aria-hidden=\"true\"></div>");
                sb.Append("<div class=\"sticker-blurb-wrap\"><p class=\"sticker-blurb\">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>");
            }

            sb.Append(StickerBrandSvg(baseUrl));
            sb.Append("</div></div>");
            return sb.ToString();
        }

        public sealed class LabelRow
        {
            public string PublicId { get; init; } = string.Empty;
            public string? VehicleRegistration { get; init; }
            public string? EmergencyPhone { get; init; }
            public string? OwnerName { get; init; }
        }
    }
}
