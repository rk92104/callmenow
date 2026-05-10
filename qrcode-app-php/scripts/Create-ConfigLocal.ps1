# Config.php mat chedna — ye script qrcode-app-php/config.local.php banati hai
# (MYSQL_USER / MYSQL_PASSWORD yahi jaate hain; file .gitignore mein hai)
#
# Chalane ke liye (PowerShell):
#   cd qrcode-app-php\scripts
#   .\Create-ConfigLocal.ps1

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$OutFile = Join-Path $ProjectRoot 'config.local.php'

function ConvertTo-PhpSingleQuotedString {
    param([string]$Value)
    if ($null -eq $Value) { $Value = '' }
    $escaped = $Value.Replace('\', '\\').Replace("'", "\'")
    return "'$escaped'"
}

Write-Host "`n=== config.local.php banega (Config.php same rahega) ===" -ForegroundColor Cyan
Write-Host "Output: $OutFile`n"

$host_ = Read-Host "MYSQL_HOST [localhost]"
if ([string]::IsNullOrWhiteSpace($host_)) { $host_ = 'localhost' }

$port = Read-Host "MYSQL_PORT [3306]"
if ([string]::IsNullOrWhiteSpace($port)) { $port = '3306' }

$db = Read-Host "MYSQL_DATABASE (e.g. u618808554_qrcode)"
if ([string]::IsNullOrWhiteSpace($db)) {
    Write-Error "MYSQL_DATABASE khali nahi ho sakta."
}

$user = Read-Host "MYSQL_USER (e.g. u618808554_raj)"
if ([string]::IsNullOrWhiteSpace($user)) {
    Write-Error "MYSQL_USER khali nahi ho sakta."
}

$passSecure = Read-Host "MYSQL_PASSWORD" -AsSecureString
$ptr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($passSecure)
try {
    $password = [System.Runtime.InteropServices.Marshal]::PtrToStringUni($ptr)
} finally {
    [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
}

$baseUrl = Read-Host "CALLMENOW_PUBLIC_BASE_URL [https://callmenow.in]"
if ([string]::IsNullOrWhiteSpace($baseUrl)) { $baseUrl = 'https://callmenow.in' }
$baseUrl = $baseUrl.TrimEnd('/')

$cors = Read-Host "CORS_ORIGIN [$baseUrl]"
if ([string]::IsNullOrWhiteSpace($cors)) { $cors = $baseUrl }

$debug = Read-Host "APP_DEBUG on? (y/N)"
$debugLine = ''
if ($debug -match '^y|^Y') {
    $debugLine = "    'APP_DEBUG' => '1',`n"
}

$h = ConvertTo-PhpSingleQuotedString $host_
$p = ConvertTo-PhpSingleQuotedString $port
$d = ConvertTo-PhpSingleQuotedString $db
$u = ConvertTo-PhpSingleQuotedString $user
$pw = ConvertTo-PhpSingleQuotedString $password
$b = ConvertTo-PhpSingleQuotedString $baseUrl
$c = ConvertTo-PhpSingleQuotedString $cors

$content = @"
<?php

/**
 * Local / server credentials — git mein commit mat karna.
 * Hostinger: public_html/api/config.local.php par upload karo.
 */
declare(strict_types=1);

return [
    'MYSQL_HOST' => $h,
    'MYSQL_PORT' => $p,
    'MYSQL_DATABASE' => $d,
    'MYSQL_USER' => $u,
    'MYSQL_PASSWORD' => $pw,

    'CALLMENOW_PUBLIC_BASE_URL' => $b,
    'CORS_ORIGIN' => $c,
$debugLine];

"@

if (Test-Path $OutFile) {
    $bak = "$OutFile.bak." + (Get-Date -Format 'yyyyMMdd-HHmmss')
    Copy-Item -LiteralPath $OutFile -Destination $bak -Force
    Write-Host "Purani file backup: $bak" -ForegroundColor Yellow
}

$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($OutFile, $content, $utf8NoBom)
Write-Host "`nHo gaya: $OutFile" -ForegroundColor Green
Write-Host "Hostinger par isi file ko public_html/api/config.local.php par daalna hai.`n"
