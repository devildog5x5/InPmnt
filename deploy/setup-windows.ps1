# Prepare InPmnt on Windows Server (Waitress). Run as Administrator preferred.
# Usage: powershell -ExecutionPolicy Bypass -File .\deploy\setup-windows.ps1 -Domain yourdomain.com
param(
    [Parameter(Mandatory = $true)]
    [string]$Domain,

    [string]$AppRoot = ""
)

$ErrorActionPreference = "Stop"

if (-not $AppRoot) {
    $AppRoot = Split-Path -Parent $PSScriptRoot
}

Set-Location $AppRoot
Write-Host "App root: $AppRoot"

if (-not (Get-Command python -ErrorAction SilentlyContinue)) {
    throw "Python 3 is not on PATH. Install from https://www.python.org/downloads/ and re-open PowerShell."
}

if (-not (Test-Path .\.venv\Scripts\python.exe)) {
    Write-Host "Creating virtual environment..."
    python -m venv .venv
}

& .\.venv\Scripts\python.exe -m pip install --upgrade pip
& .\.venv\Scripts\pip.exe install -r requirements.txt waitress

if (-not (Test-Path .\.env)) {
    Copy-Item .\.env.example .\.env
    $secret = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 48 | ForEach-Object { [char]$_ })
    $envText = Get-Content .\.env -Raw
    $envText = $envText -replace "(?m)^FLASK_SECRET_KEY=.*$", "FLASK_SECRET_KEY=$secret"
    $envText = $envText -replace "(?m)^BASE_URL=.*$", "BASE_URL=https://$Domain"
    Set-Content -Path .\.env -Value $envText -NoNewline
    Write-Host "Created .env with BASE_URL=https://$Domain — add Stripe keys before taking payments."
} else {
    Write-Host ".env already exists — leaving it unchanged."
}

Write-Host ""
Write-Host "Smoke test (Ctrl+C to stop):"
Write-Host "  .\.venv\Scripts\waitress-serve.exe --listen=127.0.0.1:5055 run:app"
Write-Host ""
Write-Host "Install as a Windows Service with NSSM (https://nssm.cc/download):"
Write-Host "  nssm install InPmnt `"$AppRoot\.venv\Scripts\waitress-serve.exe`" `"--listen=127.0.0.1:5055`" `"run:app`""
Write-Host "  nssm set InPmnt AppDirectory `"$AppRoot`""
Write-Host "  nssm set InPmnt AppEnvironmentExtra PYTHONUNBUFFERED=1"
Write-Host "  nssm start InPmnt"
Write-Host ""
Write-Host "Point IIS at deploy\iis (web.config reverse-proxies to 127.0.0.1:5055),"
Write-Host "bind https://$Domain, then set Stripe webhook to:"
Write-Host "  https://$Domain/api/billing/webhook"
Write-Host ""
Write-Host "Full guide: https://github.com/devildog5x5/InPmnt/blob/main/deploy/DEPLOY.md"
