# Dono ek saath: .NET API (SQL Server, http://127.0.0.1:5000) + Angular (/api -> 5000)
# Chalane ke liye:  powershell -ExecutionPolicy Bypass -File scripts\start-local-dev.ps1
# Pehle: SQL Server chal raha ho, QRCodeApp.API\appsettings*.json mein connection string sahi ho

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$ApiDir = Join-Path $Root 'QRCodeApp.API'
$UiDir = Join-Path $Root 'qrcode-app-ui'

if (-not (Test-Path $ApiDir)) { throw "Folder nahi mila: $ApiDir" }
if (-not (Test-Path $UiDir)) { throw "Folder nahi mila: $UiDir" }

Write-Host "`nDono windows: (1) dotnet run (SQL Server)  (2) npm run start:dotnet -> /api -> 5000`n" -ForegroundColor Cyan

Start-Process -FilePath 'powershell.exe' -WorkingDirectory $ApiDir -ArgumentList @(
    '-NoExit', '-NoProfile', '-Command', 'dotnet run'
)

Start-Sleep -Seconds 6

Start-Process -FilePath 'powershell.exe' -WorkingDirectory $UiDir -ArgumentList @(
    '-NoExit', '-NoProfile', '-Command', 'npm run start:dotnet'
)

Write-Host "API: http://127.0.0.1:5000  |  UI: http://localhost:4200 (agar port free ho)`n" -ForegroundColor Green
Write-Host "PHP + MySQL ke liye: scripts\start-local-dev-php.ps1`n" -ForegroundColor DarkGray
