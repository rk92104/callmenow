<?php

declare(strict_types=1);

namespace QrApp\Services;

/**
 * Print labels with inlined QR (data URI). Layout matches .NET QrLabelHtmlBuilder; brand logo uses /assets/marketing/scannerlogo.png like the API.
 */
final class QrLabelHtmlBuilder
{
    /** Same as .NET HorizontalCardsPerPageA3: 4 columns × 7 rows. */
    private const HORIZONTAL_CARDS_PER_PAGE = 28;

    private const VERTICAL_CARDS_PER_PAGE = 25;

    private const QR_MODULE_PX = 14;

    /** @param list<array{publicId:string,vehicleRegistration:?string,emergencyPhone:?string,ownerName:?string}> $rows */
    public static function buildDocument(array $rows, string $absoluteBaseUrl, string $embed, string $layout = 'horizontal'): string
    {
        $embed = strcasecmp($embed, 'scan') === 0 ? 'scan' : 'activate';
        $layout = strcasecmp($layout, 'vertical') === 0 ? 'vertical' : 'horizontal';
        $base = rtrim($absoluteBaseUrl, '/');
        $css = self::printCss($layout);

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>';
        $html .= '<meta name="color-scheme" content="light"/><meta name="cmn-print-label" content="sticker-h1019x40-v55x748cm-a3"/>';
        $html .= '<title>CallMeNow — print</title><style>' . $css . '</style></head><body>';

        $count = count($rows);
        $perPage = $layout === 'vertical' ? self::VERTICAL_CARDS_PER_PAGE : self::HORIZONTAL_CARDS_PER_PAGE;
        for ($i = 0; $i < $count; $i += $perPage) {
            $gridClass = $layout === 'vertical' ? 'grid grid--vertical' : 'grid';
            $html .= '<div class="page"><div class="' . $gridClass . '">';
            for ($j = $i; $j < min($i + $perPage, $count); $j++) {
                $html .= self::buildCard($rows[$j], $base, $embed, $layout);
            }
            $html .= '</div></div>';
        }

        $html .= <<<'JS'
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
JS;
        $html .= '</body></html>';

        return $html;
    }

    private static function printCss(string $layout): string
    {
        return $layout === 'vertical' ? self::printCssVertical() : self::printCssHorizontal();
    }

    /** Mirrors QRCodeApp.API QrLabelHtmlBuilder.PrintCssHorizontal. */
    private static function printCssHorizontal(): string
    {
        return <<<'CSS_H'
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
  height:95mm;
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
  width:68mm;
  height:61mm;
  margin-top:-2.5mm;
}
.card.card--vertical .sticker-qr-col{
  width:68mm;
}
.card.card--vertical .sticker-code{
  width:100%;
  max-width:100%;
  margin-top:-1.2mm;
  margin-bottom:1px;
}
.card.card--vertical .sticker-copy-col{
  width:100%;
  align-items:center;
  justify-content:flex-start;
  text-align:center;
  margin-top:-13px;
}
.card.card--vertical .sticker-headline{
  margin-top:1.5mm;
  width:100%;
  font-size:13px;
  font-weight:700;
  line-height:1.05;
  letter-spacing:0;
  text-transform:uppercase;
  font-family:'Arial Narrow',Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
  font-stretch:condensed;
  color:#000;
}
.card.card--vertical .sticker-rule{
  width:98%;
  margin-top:-2mm !important;
  height:0.6mm;
  margin:1mm auto 0 !important;
  margin-right:auto !important;
  margin-left:auto !important;
  background:#ef9523 !important;
}
.card.card--vertical .sticker-blurb-wrap{
  margin-top:2.6mm;
  width:90%;
  max-width:60mm;
}
.card.card--vertical .sticker-blurb{
  margin:0 !important;
  font-size:12px;
  line-height:1;
  font-weight:500;
  text-align:center;
  text-transform:none;
  color:#000;
  margin-top:0 !important;
  width:252px;
}
.card.card--vertical .sticker-brand{
  margin-top:3.5mm;
  padding-top:-1.5mm;
  align-items:center;
  margin-bottom:-21px;
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
  display:block;
  margin-top:0;
  image-rendering:pixelated;
  image-rendering:crisp-edges;
}
.sticker-code{
  margin:-5px;
  font-size:7.7pt;
  font-weight:800;
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
  font-size:14.25px;
  font-weight:bold;
  line-height:0.95;
  letter-spacing:0;
  color:#000;
  text-transform:uppercase;
  font-family:'Arial Narrow', Arial, Helvetica, sans-serif;
  font-stretch:condensed;
}
.sticker-rule{
  height:0.625mm;
  width:98%;
  background:#ef9523 !important;
  margin-right:4.7px !important;
  margin-top:-8.75px !important;
}
.sticker-blurb-wrap{
  display:flex;
  min-height:0;
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
  flex:1 1 auto;
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
CSS_H;
    }

    /** Mirrors QRCodeApp.API QrLabelHtmlBuilder.PrintCssVertical. */
    private static function printCssVertical(): string
    {
        return <<<'CSS_V'
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
  width:48mm;
  height:42mm;
  margin-top:0;
}
.card.card--vertical .sticker-qr-col{
  width:48mm;
}
.card.card--vertical .sticker-code{
  width:100%;
  max-width:100%;
  margin-top:-1.2mm;
  margin-bottom:1px;
}
.card.card--vertical .sticker-copy-col{
  width:100%;
  align-items:center;
  justify-content:flex-start;
  text-align:center;
  margin-top:0;
}
.card.card--vertical .sticker-headline{
  margin-top:1.5mm;
  width:100%;
  font-size:11px;
  font-weight:700;
  line-height:1.05;
  letter-spacing:0;
  text-transform:uppercase;
  font-family:'Arial Narrow',Arial,Helvetica,system-ui,'Segoe UI',Roboto,sans-serif;
  font-stretch:condensed;
  color:#000;
}
.card.card--vertical .sticker-rule{
  width:98%;
  margin-top:-1.4mm !important;
  height:0.55mm;
  margin-bottom:6px !important;
  margin:0.6mm auto 0;
  margin-right:auto !important;
  margin-left:auto !important;
  background:#ef9523;
}
.card.card--vertical .sticker-blurb-wrap{
  margin-top:0.8mm;
  width:100%;
  max-width:52mm;
}
.card.card--vertical .sticker-blurb{
  margin:0 !important;
  font-size:12px;
  line-height:1.1;
  font-weight:500;
  text-align:center;
  text-transform:none;
  color:#000;
  width:100%;
}
.card.card--vertical .sticker-brand{
  margin-top:0.9mm;
  padding-top:0;
  align-items:center;
  margin-bottom:0;
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
  display:block;
  margin-top:0;
  image-rendering:pixelated;
  image-rendering:crisp-edges;
}
.sticker-code{
  margin:-5px;
  font-size:7.7pt;
  font-weight:800;
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
  font-size:14.25px;
  font-weight:bold;
  line-height:0.95;
  letter-spacing:0;
  color:#000;
  text-transform:uppercase;
  font-family:'Arial Narrow', Arial, Helvetica, sans-serif;
  font-stretch:condensed;
}
.sticker-rule{
  height:0.625mm;
  width:98%;
  background:#ef9523 !important;
  margin-right:4.7px !important;
  margin-top:-8.75px !important;
}
.sticker-blurb-wrap{
  display:flex;
  min-height:0;
  margin-top:8.75px;
}
.sticker-blurb{
  margin:0;
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
  flex:1 1 auto;
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
CSS_V;
    }

    private static function encodeQrPayload(string $publicBaseUrl, string $publicId, string $embed): string
    {
        $b = rtrim($publicBaseUrl, '/');
        $enc = rawurlencode($publicId);

        return $embed === 'scan'
            ? $b . '/q/' . $enc
            : $b . '/activate/' . $enc;
    }

    /**
     * Match .NET: raster PNG for &lt;img&gt; — never SVG data URI (use HTTP PNG if GD unavailable).
     *
     * @return array{0:string,1:string} [primary src escaped, onerror fallback escaped]
     */
    private static function qrImgSrcAndFallback(string $publicBaseUrl, string $publicId, string $embed): array
    {
        $payloadUrl = self::encodeQrPayload($publicBaseUrl, $publicId, $embed);
        $http = self::qrHttpImageSrc($publicBaseUrl, $publicId, $embed);
        $httpEsc = htmlspecialchars($http, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $png = QrPngRenderer::tryPng($payloadUrl, self::QR_MODULE_PX);
        if ($png !== null) {
            $dataUri = 'data:image/png;base64,' . base64_encode($png);
            $srcEsc = htmlspecialchars($dataUri, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return [$srcEsc, $httpEsc];
        }

        return [$httpEsc, $httpEsc];
    }

    /** Same-origin PNG if data URI fails in some mobile / print views. */
    private static function qrHttpImageSrc(string $publicBaseUrl, string $publicId, string $embed): string
    {
        $b = rtrim($publicBaseUrl, '/');
        $enc = rawurlencode($publicId);
        $e = $embed === 'scan' ? 'scan' : 'activate';

        return $b . '/api/qr/' . $e . '/image?embed=' . $e . '&modulePixels=12';
    }

    /** Same as .NET: absolute PNG URL for print (matches QrLabelHtmlBuilder.StickerBrandSvg). */
    private static function stickerBrandImg(string $absoluteBaseUrl): string
    {
        $b = rtrim($absoluteBaseUrl, '/');
        $logo = htmlspecialchars($b . '/assets/marketing/scannerlogo.png', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<div class="sticker-brand">'
            . '<img src="' . $logo . '" alt="CallMeNow" class="brand-logo-img" loading="eager" decoding="async">'
            . '</div>';
    }

    private static function formatStickerDisplayCode(string $publicId): string
    {
        $t = strtoupper(trim($publicId));
        $u = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
        if ($u === '') {
            return trim($publicId);
        }

        return str_starts_with($u, 'CMN') ? $u : 'CMN' . $u;
    }

    /** @param array{publicId:string,vehicleRegistration:?string,emergencyPhone:?string,ownerName:?string} $row */
    private static function buildCard(array $row, string $baseUrl, string $embed, string $layout): string
    {
        $layout = strcasecmp($layout, 'vertical') === 0 ? 'vertical' : 'horizontal';
        [$srcEsc, $httpQrEsc] = self::qrImgSrcAndFallback($baseUrl, $row['publicId'], $embed);
        $pid = htmlspecialchars(self::formatStickerDisplayCode($row['publicId']), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($layout === 'vertical') {
            $h = '<div class="card card--vertical">';
            $h .= '<div class="sticker-v-stack">';
            $h .= '<div class="sticker-qr-col">';
            $h .= '<img src="' . $srcEsc . '" alt="" width="160" height="160" onerror="this.onerror=null;this.src=\'' . $httpQrEsc . '\'"/>';
            if ($embed !== 'scan') {
                $h .= '<p class="sticker-code">' . $pid . '</p>';
            }
            $h .= '</div>';
            $h .= '<div class="sticker-copy-col">';
            $h .= '<p class="sticker-headline">SCAN TO CONTACT THE VEHICLE OWNER</p>';
            $h .= '<div class="sticker-rule" aria-hidden="true"></div>';
            $h .= '<div class="sticker-blurb-wrap"><p class="sticker-blurb">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>';
            $h .= self::stickerBrandImg($baseUrl);
            $h .= '</div></div></div>';

            return $h;
        }

        $h = '<div class="card">';
        $h .= '<div class="sticker-qr-col">';
        $h .= '<img src="' . $srcEsc . '" alt="" width="160" height="160" onerror="this.onerror=null;this.src=\'' . $httpQrEsc . '\'"/>';
        if ($embed !== 'scan') {
            $h .= '<p class="sticker-code">' . $pid . '</p>';
        }
        $h .= '</div>';
        $h .= '<div class="sticker-copy-col">';

        if ($embed === 'scan') {
            $h .= '<p class="sticker-headline">SCAN TO CONTACT THE VEHICLE OWNER</p>';
            $h .= '<div class="sticker-rule" aria-hidden="true"></div>';
            $h .= '<div class="sticker-blurb-wrap"><p class="sticker-blurb">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>';
        } else {
            $h .= '<p class="sticker-headline">SCAN TO CONTACT THE VEHICLE OWNER</p>';
            $h .= '<div class="sticker-rule" aria-hidden="true"></div>';
            $h .= '<div class="sticker-blurb-wrap"><p class="sticker-blurb">Wrong parking, Emergency contact<br/>or any issue with the vehicle</p></div>';
        }

        $h .= self::stickerBrandImg($baseUrl);
        $h .= '</div></div>';

        return $h;
    }
}
