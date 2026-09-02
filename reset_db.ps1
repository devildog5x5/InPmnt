# Factory-reset the InPmnt SQLite database (users, passwords, invoices, clients).
# Stops a running instance, then re-seeds the default admin and demo accounts.
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\reset_db.ps1
#   powershell -ExecutionPolicy Bypass -File .\reset_db.ps1 -Yes
param(
    [switch]$Yes,
    [string]$DbPath = ""
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

function Stop-InPmntProcesses {
    param([int]$Port = 5055)
    $listenPids = @(
        Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
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
    if ($killPids.Count -eq 0) { return }
    Write-Host "Stopping InPmnt PID(s): $($killPids -join ', ')" -ForegroundColor Yellow
    foreach ($procId in $killPids) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Seconds 1
}

$py = Join-Path $PSScriptRoot ".venv\Scripts\python.exe"
if (-not (Test-Path $py)) { $py = "python" }

Write-Host "This deletes every user, password, invoice, client, and reminder, then recreates the default accounts."
if (-not $Yes) {
    $answer = Read-Host "Type RESET to continue"
    if ($answer -ne "RESET") {
        Write-Host "Cancelled."
        exit 1
    }
}

Stop-InPmntProcesses
$pyArgs = @("-m", "app.reset_db", "--yes")
if ($DbPath) { $pyArgs += @("--db", $DbPath) }
& $py @pyArgs
exit $LASTEXITCODE
