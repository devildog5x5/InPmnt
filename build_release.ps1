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

# Hostinger / Apache: unzip into public_html or /var/www/html
$phpStage = Join-Path $Stage "phpdrop"
New-Item -ItemType Directory -Force -Path $phpStage | Out-Null
# Copy including dotfiles (.htaccess, .env.example). php\* skips names that start with '.'.
Get-ChildItem -Path (Join-Path $Root "php") -Force | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination (Join-Path $phpStage $_.Name) -Recurse -Force
}
Copy-Item -Path (Join-Path $Root "static") -Destination (Join-Path $phpStage "static") -Recurse -Force
Get-ChildItem -Path (Join-Path $phpStage "data") -Filter "*.db" -ErrorAction SilentlyContinue | Remove-Item -Force
$phpReadme = @"
InPmnt PHP — Hostinger / shared hosting
========================================
1. Unzip ALL of these files into public_html or /var/www/html (the Apache document root).
2. Copy .env.example to .env and set APP_SECRET and BASE_URL=https://yourdomain.com
3. In hPanel → Advanced → PHP Configuration: PHP 8.2+ and enable pdo_sqlite
4. Open https://yourdomain.com  → Sign up
5. Stripe webhook: https://yourdomain.com/api/billing/webhook

Do not upload into a subfolder unless that subfolder is the site document root.
The SQLite database is created automatically at data/inpmnt.db (blocked from the web).
"@
Set-Content -Path (Join-Path $phpStage "HOSTINGER.txt") -Value $phpReadme -Encoding UTF8
Get-ChildItem -Path $phpStage -Force | Compress-Archive -DestinationPath $phpZip -Force

Write-Host "Built v$Version"
Write-Host "  $portableZip"
Write-Host "  $sourceZip"
Write-Host "  $iconZip"
Write-Host "  $phpZip"
