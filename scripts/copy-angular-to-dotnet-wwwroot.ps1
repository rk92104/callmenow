# Copies Angular production build into .NET wwwroot for combined Kestrel hosting.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$src = Join-Path $root "qrcode-app-ui\dist\qrcode-app-ui\browser"
$dest = Join-Path $root "QRCodeApp.API\wwwroot\browser"

if (-not (Test-Path $src)) {
    Write-Host "Source missing. Run first: cd qrcode-app-ui && npm run build" -ForegroundColor Red
    exit 1
}

New-Item -ItemType Directory -Force -Path $dest | Out-Null
Copy-Item -Path (Join-Path $src "*") -Destination $dest -Recurse -Force
Write-Host "Copied Angular build to: $dest" -ForegroundColor Green
