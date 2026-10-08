# Build ReceiptGrid release zips into installers\
$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot
$Out = Join-Path $Root "installers"
$Stage = Join-Path $Root "build\stage"
$VersionFile = Join-Path $Root "VERSION"
$Version = if ($args[0]) {
    $args[0]
} elseif (Test-Path $VersionFile) {
    (Get-Content $VersionFile -Raw).Trim()
} else {
    "1.4.0"
}

New-Item -ItemType Directory -Force -Path $Out | Out-Null
if (Test-Path $Stage) { Remove-Item -Recurse -Force $Stage }
New-Item -ItemType Directory -Force -Path $Stage | Out-Null

$include = @(
    "app", "static", "templates", "assets", "deploy", "php",
    "requirements.txt", "run.py", "passenger_wsgi.py", "start.ps1", "install.ps1", "uninstall.ps1", "reset_db.ps1", "VERSION",
    "Dockerfile", "docker-compose.yml", "docker-entrypoint.sh", ".dockerignore",
    "README.md", "GO_TO_MARKET.md", ".env.example", ".gitignore", ".gitattributes"
)

$PortableDir = Join-Path $Stage "ReceiptGrid"
New-Item -ItemType Directory -Force -Path $PortableDir | Out-Null
foreach ($item in $include) {
    $src = Join-Path $Root $item
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $PortableDir $item) -Recurse -Force
    }
}
# Directory copies can skip dotfiles. Keep the Hostinger files in the portable tree too.
foreach ($rel in @("php\.env.example", "php\.htaccess", "php\.user.ini", "php\data\.htaccess", "php\data\.gitkeep")) {
    $src = Join-Path $Root $rel
    $dest = Join-Path $PortableDir $rel
    if (Test-Path -LiteralPath $src) {
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path -LiteralPath $destDir)) {
            New-Item -ItemType Directory -Force -Path $destDir | Out-Null
        }
        Copy-Item -LiteralPath $src -Destination $dest -Force
    }
}

$portableZip = Join-Path $Out "ReceiptGrid-Portable.v$Version.zip"
$sourceZip = Join-Path $Out "ReceiptGrid-Source.v$Version.zip"
$iconZip = Join-Path $Out "ReceiptGrid-Icon.v$Version.zip"
$phpZip = Join-Path $Out "invcpay-v$Version.zip"
Get-ChildItem -Path $Out -Filter "ReceiptGrid-*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem -Path $Out -Filter "invcpay-v*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem -Path $Out -Filter "ReceiptGrid-PHP*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force
foreach ($z in @($portableZip, $sourceZip, $iconZip, $phpZip)) {
    if (Test-Path $z) { Remove-Item $z -Force }
}

# Compress-Archive drops names that start with a dot (.htaccess, .env.example).
Add-Type -AssemblyName System.IO.Compression.FileSystem
function Write-ZipWithPrefix {
    param([string]$SourceDir, [string]$DestZip, [string]$Prefix)
    if (Test-Path $DestZip) { Remove-Item $DestZip -Force }
    $zip = [System.IO.Compression.ZipFile]::Open($DestZip, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        $root = (Resolve-Path -LiteralPath $SourceDir).Path.TrimEnd('\', '/')
        Get-ChildItem -LiteralPath $root -Recurse -Force -File | ForEach-Object {
            $rel = $_.FullName.Substring($root.Length).TrimStart('\', '/') -replace '\\', '/'
            $entry = if ($Prefix) { "$Prefix/$rel" } else { $rel }
            [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entry)
        }
    } finally {
        $zip.Dispose()
    }
}
Write-ZipWithPrefix -SourceDir (Join-Path $Stage "ReceiptGrid") -DestZip $portableZip -Prefix "ReceiptGrid"

# Source = same tree (documented as source distribution)
Copy-Item $portableZip $sourceZip -Force

$iconStage = Join-Path $Stage "icon"
New-Item -ItemType Directory -Force -Path $iconStage | Out-Null
Copy-Item (Join-Path $Root "assets\inpmnt-icon.png") $iconStage -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $Root "static\img\inpmnt-icon.png") (Join-Path $iconStage "inpmnt-icon-512.png") -Force -ErrorAction SilentlyContinue
Compress-Archive -Path (Join-Path $iconStage "*") -DestinationPath $iconZip -Force

# Hostinger / shared hosting: unzip into public_html
$phpStage = Join-Path $Stage "phpdrop"
New-Item -ItemType Directory -Force -Path $phpStage | Out-Null
Copy-Item -Path (Join-Path $Root "php\*") -Destination $phpStage -Recurse -Force
Copy-Item -Path (Join-Path $Root "static") -Destination (Join-Path $phpStage "static") -Recurse -Force
# Wildcards skip names that start with a dot. The live drop needs these.
foreach ($pair in @(
    @{ Src = "php\.env.example"; Dest = ".env.example" },
    @{ Src = "php\.htaccess"; Dest = ".htaccess" },
    @{ Src = "php\.user.ini"; Dest = ".user.ini" },
    @{ Src = "php\data\.htaccess"; Dest = "data\.htaccess" },
    @{ Src = "php\data\.gitkeep"; Dest = "data\.gitkeep" }
)) {
    $src = Join-Path $Root $pair.Src
    $dest = Join-Path $phpStage $pair.Dest
    if (Test-Path -LiteralPath $src) {
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path -LiteralPath $destDir)) {
            New-Item -ItemType Directory -Force -Path $destDir | Out-Null
        }
        Copy-Item -LiteralPath $src -Destination $dest -Force
    }
}
# Never ship the live database, secrets, or docs into public_html.
$dataDir = Join-Path $phpStage "data"
if (Test-Path -LiteralPath $dataDir) {
    Get-ChildItem -LiteralPath $dataDir -Force -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "*.db" -or $_.Name -like "*.db-wal" -or $_.Name -like "*.db-shm" } |
        Remove-Item -Force
}
foreach ($secretName in @(".env", ".env.local")) {
    $secret = Join-Path $phpStage $secretName
    if (Test-Path -LiteralPath $secret) { Remove-Item -LiteralPath $secret -Force }
}
# Docs stay in the repo. Do not drop README, SOP, or this zip into public_html.
Get-ChildItem -Path $phpStage -Recurse -Include *.md,*.zip,HOSTINGER.txt -ErrorAction SilentlyContinue | Remove-Item -Force
# Forward-slash entry names so Hostinger File Manager extracts the same tree on Windows or Linux.
if (Test-Path $phpZip) { Remove-Item $phpZip -Force }
Write-ZipWithPrefix -SourceDir $phpStage -DestZip $phpZip -Prefix ""

Write-Host "Built v$Version"
Write-Host "  $portableZip"
Write-Host "  $sourceZip"
Write-Host "  $iconZip"
Write-Host "  $phpZip"
