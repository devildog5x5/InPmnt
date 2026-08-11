# Backup InPmnt SQLite DB on Windows (native install).
param(
  [string]$OutDir = ".\backups",
  [string]$DbPath = ""
)
$ErrorActionPreference = "Stop"
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
if (-not $DbPath) {
  if ($env:DATABASE_PATH -and (Test-Path $env:DATABASE_PATH)) { $DbPath = $env:DATABASE_PATH }
  elseif (Test-Path ".\inpmnt.db") { $DbPath = ".\inpmnt.db" }
  else { throw "No database found. Pass -DbPath or set DATABASE_PATH." }
}
$stamp = (Get-Date).ToUniversalTime().ToString("yyyyMMddTHHmmssZ")
$dest = Join-Path $OutDir "inpmnt-$stamp.db"
Copy-Item $DbPath $dest -Force
Get-ChildItem $OutDir -Filter "inpmnt-*.db" | Sort-Object LastWriteTime -Descending | Select-Object -Skip 14 | Remove-Item -Force
Write-Host "Wrote $dest"
