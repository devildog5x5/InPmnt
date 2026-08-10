# Build InPmnt release zips into installers\
$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot
$Out = Join-Path $Root "installers"
$Stage = Join-Path $Root "build\stage"
$Version = if ($args[0]) { $args[0] } else { "1.1.3" }

New-Item -ItemType Directory -Force -Path $Out | Out-Null
if (Test-Path $Stage) { Remove-Item -Recurse -Force $Stage }
New-Item -ItemType Directory -Force -Path $Stage | Out-Null

$include = @(
    "app", "static", "templates", "assets",
    "requirements.txt", "run.py", "start.ps1",
    "README.md", "GO_TO_MARKET.md", "LICENSE", ".env.example", ".gitignore"
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
foreach ($z in @($portableZip, $sourceZip, $iconZip)) {
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

Write-Host "Built v$Version"
Write-Host "  $portableZip"
Write-Host "  $sourceZip"
Write-Host "  $iconZip"
