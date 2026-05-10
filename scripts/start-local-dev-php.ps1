# PHP API (8080) + Angular (proxy default -> 8080, MySQL + config.local.php)
# powershell -ExecutionPolicy Bypass -File scripts\start-local-dev-php.ps1

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$PhpDir = Join-Path $Root 'qrcode-app-php'
$UiDir = Join-Path $Root 'qrcode-app-ui'

if (-not (Test-Path $PhpDir)) { throw "Folder nahi mila: $PhpDir" }
if (-not (Test-Path $UiDir)) { throw "Folder nahi mila: $UiDir" }

Write-Host "`nPHP 8080 + npm start (proxy -> 8080)`n" -ForegroundColor Cyan

Start-Process -FilePath 'powershell.exe' -WorkingDirectory $PhpDir -ArgumentList @(
    '-NoExit', '-NoProfile', '-Command', 'php -S 127.0.0.1:8080 -t public public/router.php'
)

Start-Sleep -Seconds 2

Start-Process -FilePath 'powershell.exe' -WorkingDirectory $UiDir -ArgumentList @(
    '-NoExit', '-NoProfile', '-Command', 'npm start'
)

Write-Host "API: http://127.0.0.1:8080  |  UI: http://localhost:4200`n" -ForegroundColor Green
