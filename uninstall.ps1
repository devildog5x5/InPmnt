# Uninstall InPmnt from the local install directory.
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\uninstall.ps1
#   powershell -ExecutionPolicy Bypass -File .\uninstall.ps1 -RemoveData
#   powershell -ExecutionPolicy Bypass -File .\uninstall.ps1 -Yes
param(
    [string]$InstallDir = "",
    [switch]$RemoveData,
    [switch]$Yes
)

$ErrorActionPreference = "Stop"

function Get-InPmntInstallDir {
    param([string]$Override)
    if ($Override) { return $Override }
    $reg = Get-ItemProperty -Path "HKCU:\Software\InPmnt" -ErrorAction SilentlyContinue
    if ($reg -and $reg.InstallPath) { return [string]$reg.InstallPath }
    return (Join-Path $env:LOCALAPPDATA "InPmnt")
}

function Test-InPmntInstall {
    param([string]$Path)
    if (-not (Test-Path $Path)) { return $false }
    return (Test-Path (Join-Path $Path "run.py")) -and (Test-Path (Join-Path $Path "start.ps1"))
}

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
    Write-Host "WARNING: InPmnt is running. Stopping PID(s): $($killPids -join ', ')" -ForegroundColor Yellow
    foreach ($procId in $killPids) {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Seconds 1
}

$InstallDir = Get-InPmntInstallDir -Override $InstallDir
Write-Host "Install location: $InstallDir"

if (-not (Test-InPmntInstall -Path $InstallDir)) {
    Write-Host "No InPmnt installation found at that path."
    exit 0
}

$marker = Join-Path $InstallDir "inpmnt-install.json"
$version = "unknown"
if (Test-Path $marker) {
    try {
        $meta = Get-Content $marker -Raw | ConvertFrom-Json
        if ($meta.version) { $version = [string]$meta.version }
    } catch { }
}

if (-not $Yes) {
    Write-Host ""
    Write-Host "Uninstall InPmnt $version from:" -ForegroundColor Yellow
    Write-Host "  $InstallDir"
    if ($RemoveData) {
        Write-Host "  Data will be REMOVED (.env, database, certs)." -ForegroundColor Yellow
    } else {
        Write-Host "  Data will be KEPT (.env, *.db, certs/) unless you pass -RemoveData."
    }
    $answer = Read-Host "Continue? [Y/N]"
    if ($answer -notmatch '^(y|yes)$') {
        Write-Host "Uninstall cancelled."
        exit 0
    }
}

Stop-InPmntProcesses

$dataNames = @(".env", "certs", "data", "inpmnt.db")
$backupRoot = Join-Path $env:TEMP ("InPmnt-data-" + [guid]::NewGuid().ToString("N"))
$saved = @()
if (-not $RemoveData) {
    New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
    foreach ($name in $dataNames) {
        $src = Join-Path $InstallDir $name
        if (Test-Path $src) {
            Copy-Item -Path $src -Destination (Join-Path $backupRoot $name) -Recurse -Force
            $saved += $name
        }
    }
    Get-ChildItem -Path $InstallDir -Filter "*.db" -File -ErrorAction SilentlyContinue | ForEach-Object {
        Copy-Item $_.FullName -Destination (Join-Path $backupRoot $_.Name) -Force
        $saved += $_.Name
    }
}

$shortcut = Join-Path ([Environment]::GetFolderPath("StartMenu")) "Programs\InPmnt.lnk"
if (Test-Path $shortcut) { Remove-Item $shortcut -Force }

if (Test-Path "HKCU:\Software\InPmnt") {
    Remove-Item "HKCU:\Software\InPmnt" -Recurse -Force
}

Remove-Item -LiteralPath $InstallDir -Recurse -Force -ErrorAction SilentlyContinue

if (-not $RemoveData -and $saved.Count -gt 0) {
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
    foreach ($name in $saved | Select-Object -Unique) {
        $src = Join-Path $backupRoot $name
        if (Test-Path $src) {
            Copy-Item -Path $src -Destination (Join-Path $InstallDir $name) -Recurse -Force
        }
    }
    Write-Host "Preserved user data: $($saved -join ', ')"
}

if (Test-Path $backupRoot) {
    Remove-Item -LiteralPath $backupRoot -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host "InPmnt uninstalled."
