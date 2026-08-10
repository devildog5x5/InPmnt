# Launch InPmnt locally (creates .venv on first run).
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
    Write-Host "Created .env from .env.example — add Stripe keys when ready."
}

Write-Host "Starting InPmnt at http://127.0.0.1:5055"
Write-Host "Demo login: robert@inpmnt.app / demo1234"
& .\.venv\Scripts\python.exe .\run.py
