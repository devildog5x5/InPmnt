# Build InPmnt release zips into installers\
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
    "requirements.txt", "run.py", "passenger_wsgi.py", "start.ps1", "install.ps1", "uninstall.ps1", "VERSION",
    "Dockerfile", "docker-compose.yml", "docker-entrypoint.sh", ".dockerignore",
    "README.md", "GO_TO_MARKET.md", "LICENSE", ".env.example", ".gitignore", ".gitattributes"
)

$PortableDir = Join-Path $Stage "InPmnt"
New-Item -ItemType Directory -Force -Path $PortableDir | Out-Null
foreach ($item in $include) {
    $src = Join-Path $Root $item
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $PortableDir $item) -Recurse -Force
    }
}

$portableZip = Join-Path $Out "InPmnt-Portable.zip"
$sourceZip = Join-Path $Out "InPmnt-Source.zip"
$iconZip = Join-Path $Out "InPmnt-Icon.zip"
$phpZip = Join-Path $Out "InPmnt-PHP.zip"
foreach ($z in @($portableZip, $sourceZip, $iconZip, $phpZip)) {
    if (Test-Path $z) { Remove-Item $z -Force }
}

Compress-Archive -Path (Join-Path $Stage "InPmnt") -DestinationPath $portableZip -Force

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
Get-ChildItem -Path (Join-Path $phpStage "data") -Filter "*.db" -ErrorAction SilentlyContinue | Remove-Item -Force
# HOSTINGER.txt ships from php/HOSTINGER.txt (copied with php\*)
Get-ChildItem -Path $phpStage -Force | Compress-Archive -DestinationPath $phpZip -Force

Write-Host "Built v$Version"
Write-Host "  $portableZip"
Write-Host "  $sourceZip"
Write-Host "  $iconZip"
Write-Host "  $phpZip"
