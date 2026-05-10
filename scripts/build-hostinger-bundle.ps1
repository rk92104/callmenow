
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
if (-not $root) { $root = Get-Location }

$bundleDir = Join-Path $root "publish\hostinger-bundle"
if (Test-Path $bundleDir) { 
    Write-Host "Cleaning existing bundle directory..."
    Remove-Item -Path $bundleDir -Recurse -Force 
}
New-Item -ItemType Directory -Path $bundleDir -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $bundleDir "api") -Force | Out-Null

# Copy Angular
$angularBrowser = Join-Path $root "qrcode-app-ui\dist\qrcode-app-ui\browser"
Write-Host "Copying Angular build from $angularBrowser..."
Copy-Item -Path "$angularBrowser\*" -Destination $bundleDir -Recurse -Force

# Copy .htaccess
$rootHtaccess = Join-Path $root "qrcode-app-php\deploy\public_html_ROOT.htaccess"
Write-Host "Copying root .htaccess..."
Copy-Item -Path $rootHtaccess -Destination (Join-Path $bundleDir ".htaccess") -Force

# Copy PHP API
$phpSrc = Join-Path $root "qrcode-app-php"
$phpDest = Join-Path $bundleDir "api"
Write-Host "Copying PHP API from $phpSrc..."
Get-ChildItem -Path $phpSrc -Exclude "composer.phar", ".git", ".gitignore", "deploy", "scripts", "tools" | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $phpDest -Recurse -Force
}

# Create ZIP
$zipFile = Join-Path $root "publish\hostinger-bundle.zip"
if (Test-Path $zipFile) { Remove-Item $zipFile -Force }
Write-Host "Creating ZIP: $zipFile..."
Compress-Archive -Path "$bundleDir\*" -DestinationPath $zipFile -Force

Write-Host "Done! Deployment bundle ready at $zipFile" -ForegroundColor Green
