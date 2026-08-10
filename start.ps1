# Launch InPmnt locally over HTTPS (creates .venv + self-signed cert on first run).
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if (-not (Test-Path .\.venv\Scripts\python.exe)) {
    Write-Host "Creating virtual environment..."
    python -m venv .venv
    .\.venv\Scripts\python.exe -m pip install --upgrade pip
    .\.venv\Scripts\pip.exe install -r requirements.txt
}

if (-not (Test-Path .\.env) -and (Test-Path .\.env.example)) {
    Copy-Item .\.env.example .\.env
    Write-Host "Created .env from .env.example - add Stripe keys when ready."
}

# Ensure HTTPS BASE_URL for local runs (self-signed cert).
if (Test-Path .\.env) {
    $envText = Get-Content .\.env -Raw
    if ($envText -match '(?m)^BASE_URL=http://127\.0\.0\.1:5055\s*$') {
        $envText = $envText -replace '(?m)^BASE_URL=http://127\.0\.0\.1:5055\s*$', 'BASE_URL=https://127.0.0.1:5055'
        Set-Content -Path .\.env -Value $envText -NoNewline
    }
}

$env:USE_HTTPS = if ($env:USE_HTTPS) { $env:USE_HTTPS } else { "1" }

Write-Host "Starting InPmnt at https://127.0.0.1:5055"
Write-Host "Demo login: trialuser@inpmnt.app / demo1234"
Write-Host "Self-signed cert - accept the browser warning for local use."
& .\.venv\Scripts\python.exe .\run.py
