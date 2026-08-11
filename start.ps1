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

$port = 5055
if ($env:PORT -and ($env:PORT -as [int])) {
    $port = [int]$env:PORT
} elseif (Test-Path .\.env) {
    $portLine = Select-String -Path .\.env -Pattern '^(?i)PORT\s*=\s*(\d+)\s*$' | Select-Object -First 1
    if ($portLine) { $port = [int]$portLine.Matches[0].Groups[1].Value }
}

# If already running, warn then stop the existing instance before starting again.
$listenPids = @(
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique
)
$runPids = @(
    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object {
            $_.Name -match '^(python|pythonw)\.exe$' -and
            $_.CommandLine -and
            $_.CommandLine -match '[\\/]run\.py(\s|$|"|'')'
        } |
        Select-Object -ExpandProperty ProcessId
)
$killPids = @($listenPids + $runPids | Where-Object { $_ -and $_ -gt 0 } | Select-Object -Unique)
if ($killPids.Count -gt 0) {
    Write-Host ""
    Write-Host "WARNING: InPmnt is already running (port $port / run.py). Killing it so this start can continue." -ForegroundColor Yellow
    Write-Host "Stopping PID(s): $($killPids -join ', ')" -ForegroundColor Yellow
    Write-Host ""
    foreach ($procId in $killPids) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Seconds 1
}

Write-Host "Starting InPmnt at https://127.0.0.1:$port"
Write-Host "Sign up: /signup  |  optional demo: set SHOW_DEMO_LOGIN=1 (demouser / Demo)"
Write-Host "Self-signed cert - accept the browser warning for local use."
& .\.venv\Scripts\python.exe .\run.py
