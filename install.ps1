# Install InPmnt to a local app folder (default: %LOCALAPPDATA%\InPmnt).
# If an older install is present, prompts to uninstall it before installing this version.
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\install.ps1
#   powershell -ExecutionPolicy Bypass -File .\install.ps1 -UninstallFirst
#   powershell -ExecutionPolicy Bypass -File .\install.ps1 -UpdateInPlace
#   powershell -ExecutionPolicy Bypass -File .\install.ps1 -NonInteractive -UpdateInPlace
param(
    [string]$InstallDir = "",
    [switch]$UninstallFirst,
    [switch]$UpdateInPlace,
    [switch]$RemoveData,
    [switch]$NonInteractive,
    [switch]$StartAfter
)

$ErrorActionPreference = "Stop"
$SourceRoot = $PSScriptRoot

function Get-InPmntVersion {
    param([string]$Root)
    $vf = Join-Path $Root "VERSION"
    if (Test-Path $vf) {
        $v = (Get-Content $vf -Raw).Trim()
        if ($v) { return $v }
    }
    return "unknown"
}

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

function Get-InstalledVersion {
    param([string]$Path)
    $marker = Join-Path $Path "inpmnt-install.json"
    if (Test-Path $marker) {
        try {
            $meta = Get-Content $marker -Raw | ConvertFrom-Json
            if ($meta.version) { return [string]$meta.version }
        } catch { }
    }
    return (Get-InPmntVersion -Root $Path)
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

function Copy-InPmntAppFiles {
    param(
        [string]$From,
        [string]$To
    )
    $excludeDirs = @(
        ".git", ".venv", "venv", "installers", "build", "certs", "data",
        "__pycache__", ".cursor", "agent-transcripts", "terminals"
    )
    $excludeFiles = @(".env", "inpmnt.db", "*.db")

    New-Item -ItemType Directory -Force -Path $To | Out-Null
    Get-ChildItem -Path $From -Force | ForEach-Object {
        if ($_.PSIsContainer) {
            if ($excludeDirs -contains $_.Name) { return }
            Copy-Item -Path $_.FullName -Destination (Join-Path $To $_.Name) -Recurse -Force
        } else {
            foreach ($pat in $excludeFiles) {
                if ($_.Name -like $pat) { return }
            }
            Copy-Item -Path $_.FullName -Destination (Join-Path $To $_.Name) -Force
        }
    }
}

function Write-InstallMarker {
    param(
        [string]$Path,
        [string]$Version
    )
    $obj = [ordered]@{
        product     = "InPmnt"
        version     = $Version
        installPath = $Path
        installedAt = (Get-Date).ToUniversalTime().ToString("o")
    }
    $obj | ConvertTo-Json | Set-Content -Path (Join-Path $Path "inpmnt-install.json") -Encoding utf8
}

function Register-InPmntInstall {
    param(
        [string]$Path,
        [string]$Version
    )
    New-Item -Path "HKCU:\Software\InPmnt" -Force | Out-Null
    Set-ItemProperty -Path "HKCU:\Software\InPmnt" -Name "InstallPath" -Value $Path
    Set-ItemProperty -Path "HKCU:\Software\InPmnt" -Name "Version" -Value $Version

    $programs = Join-Path ([Environment]::GetFolderPath("StartMenu")) "Programs"
    New-Item -ItemType Directory -Force -Path $programs | Out-Null
    $shortcutPath = Join-Path $programs "InPmnt.lnk"
    $shell = New-Object -ComObject WScript.Shell
    $sc = $shell.CreateShortcut($shortcutPath)
    $sc.TargetPath = "powershell.exe"
    $sc.Arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$(Join-Path $Path 'start.ps1')`""
    $sc.WorkingDirectory = $Path
    $sc.WindowStyle = 1
    $sc.Description = "Start InPmnt"
    $icon = Join-Path $Path "static\img\inpmnt-icon.png"
    if (Test-Path $icon) { $sc.IconLocation = $icon }
    $sc.Save()
}

$newVersion = Get-InPmntVersion -Root $SourceRoot
$InstallDir = Get-InPmntInstallDir -Override $InstallDir
$sourceFull = [System.IO.Path]::GetFullPath($SourceRoot)
$installFull = [System.IO.Path]::GetFullPath($InstallDir)

Write-Host "InPmnt installer"
Write-Host "  Source : $sourceFull"
Write-Host "  Target : $installFull"
Write-Host "  Version: $newVersion"
Write-Host ""

if (-not (Test-Path (Join-Path $SourceRoot "run.py"))) {
    throw "This folder does not look like an InPmnt package (run.py missing)."
}

$alreadyInstalled = Test-InPmntInstall -Path $InstallDir
$choice = $null

if ($alreadyInstalled) {
    $oldVersion = Get-InstalledVersion -Path $InstallDir
    Write-Host "InPmnt is already installed:" -ForegroundColor Yellow
    Write-Host "  Path   : $installFull"
    Write-Host "  Version: $oldVersion"
    Write-Host ""

    if ($UninstallFirst) {
        $choice = "Y"
    } elseif ($UpdateInPlace) {
        $choice = "N"
    } elseif ($NonInteractive) {
        throw "Existing install detected. Re-run with -UninstallFirst or -UpdateInPlace (or run interactively)."
    } else {
        Write-Host "Uninstall the old version before installing $newVersion?"
        Write-Host "  [Y] Uninstall old version, then install new"
        Write-Host "  [N] Update in place (overwrite app files, keep .env / database / certs)"
        Write-Host "  [C] Cancel"
        $answer = Read-Host "Choice"
        if ($answer -match '^(y|yes)$') { $choice = "Y" }
        elseif ($answer -match '^(n|no)$') { $choice = "N" }
        else { $choice = "C" }
    }

    if ($choice -eq "C") {
        Write-Host "Install cancelled."
        exit 0
    }

    if ($choice -eq "Y") {
        $uninstall = Join-Path $SourceRoot "uninstall.ps1"
        if (-not (Test-Path $uninstall)) {
            throw "uninstall.ps1 not found next to install.ps1."
        }
        Write-Host ""
        Write-Host "Uninstalling existing InPmnt $oldVersion..." -ForegroundColor Yellow
        $uninstallArgs = @{
            InstallDir = $InstallDir
            Yes        = $true
        }
        if ($RemoveData) { $uninstallArgs.RemoveData = $true }
        & $uninstall @uninstallArgs
        Write-Host ""
    }
}

Stop-InPmntProcesses

if ($sourceFull -ne $installFull) {
    Write-Host "Copying application files..."
    Copy-InPmntAppFiles -From $SourceRoot -To $InstallDir
} else {
    Write-Host "Installing from the target folder - skipping file copy."
}

Write-InstallMarker -Path $InstallDir -Version $newVersion
Register-InPmntInstall -Path $InstallDir -Version $newVersion

# Ensure runtime deps via start.ps1's venv path (create venv now).
Set-Location $InstallDir
if (-not (Get-Command python -ErrorAction SilentlyContinue)) {
    Write-Host "WARNING: Python is not on PATH. Install Python 3, then run start.ps1." -ForegroundColor Yellow
} else {
    if (-not (Test-Path .\.venv\Scripts\python.exe)) {
        Write-Host "Creating virtual environment..."
        python -m venv .venv
    }
    & .\.venv\Scripts\python.exe -m pip install --upgrade pip | Out-Null
    & .\.venv\Scripts\pip.exe install -r requirements.txt
    if (-not (Test-Path .\.env) -and (Test-Path .\.env.example)) {
        Copy-Item .\.env.example .\.env
        Write-Host "Created .env from .env.example."
    }
}

$startCmd = 'powershell -File "' + (Join-Path $installFull 'start.ps1') + '"'
$uninstallCmd = 'powershell -File "' + (Join-Path $installFull 'uninstall.ps1') + '"'
Write-Host ""
Write-Host ("Installed InPmnt {0} to:" -f $newVersion) -ForegroundColor Green
Write-Host ("  {0}" -f $installFull)
Write-Host ("Start:  {0}" -f $startCmd)
Write-Host "Or use the Start Menu shortcut: InPmnt"
Write-Host ("Uninstall: {0}" -f $uninstallCmd)

if ($StartAfter) {
    Write-Host ""
    Write-Host "Starting InPmnt..."
    & (Join-Path $InstallDir "start.ps1")
}
