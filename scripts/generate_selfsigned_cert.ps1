<#
generate_selfsigned_cert.ps1

Generates a self-signed TLS certificate and private key for XAMPP/Apache on Windows.

Usage:
  .\generate_selfsigned_cert.ps1 -OutputDir "C:\xampp\apache\certs" -CommonName "localhost" -Days 365

This script prefers OpenSSL (from PATH or C:\xampp\apache\bin\openssl.exe). If OpenSSL is not found,
it will exit with an error and point you to `README_TLS.md` for manual instructions.
#>

param(
    [string]$OutputDir = "C:\xampp\apache\certs",
    [string]$CommonName = "localhost",
    [int]$Days = 365
)

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
}

# Try to find OpenSSL
$openssl = $null
try { $openssl = (Get-Command openssl -ErrorAction SilentlyContinue).Source } catch { }
if (-not $openssl) {
    $possible = "C:\xampp\apache\bin\openssl.exe"
    if (Test-Path $possible) { $openssl = $possible }
}

if (-not $openssl) {
    Write-Error "OpenSSL not found. Install OpenSSL or follow README_TLS.md to generate certs manually."
    exit 1
}

$certPath = Join-Path $OutputDir "server.crt"
$keyPath = Join-Path $OutputDir "server.key"

$subject = "/C=US/ST=State/L=City/O=KNBTS/OU=IT/CN=$CommonName"

Write-Host "Using OpenSSL: $openssl"
Write-Host "Generating certificate for CN=$CommonName (valid for $Days days)"

# Use XAMPP's OpenSSL config if available, otherwise generate without it
$opensslConf = "C:\xampp\apache\conf\openssl.cnf"
if (Test-Path $opensslConf) {
    $env:OPENSSL_CONF = $opensslConf
}

& $openssl req -x509 -nodes -days $Days -newkey rsa:2048 -keyout "$keyPath" -out "$certPath" -subj "$subject" -config $opensslConf 2>$null
if ($LASTEXITCODE -ne 0) {
    # Fallback: try without config file
    & $openssl req -x509 -nodes -days $Days -newkey rsa:2048 -keyout "$keyPath" -out "$certPath" -subj "$subject"
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "Success: created:`n  Certificate: $certPath`n  Private Key: $keyPath"
    Write-Host "Update your Apache SSL config with these paths and restart Apache (see README_TLS.md)."
    exit 0
} else {
    Write-Error "OpenSSL failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}
