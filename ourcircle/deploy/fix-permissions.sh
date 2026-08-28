#!/usr/bin/env bash
# Set Ubuntu-safe permissions. Never uses 777.
# Usage: sudo bash deploy/fix-permissions.sh [/var/www/familyshieldpro]
set -euo pipefail

APP_DIR="${1:-/var/www/familyshieldpro}"
OWNER="${APP_OWNER:-www-data}"
GROUP="${APP_GROUP:-www-data}"

if [[ ! -d "$APP_DIR" ]]; then
  echo "Missing app directory: $APP_DIR"
  exit 1
fi

# Directories 755, files 644
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +

# Executable scripts
if [[ -d "$APP_DIR/deploy" ]]; then
  chmod 755 "$APP_DIR"/deploy/*.sh 2>/dev/null || true
fi
if [[ -f "$APP_DIR/pack_ubuntu.sh" ]]; then
  chmod 755 "$APP_DIR/pack_ubuntu.sh"
fi

# Writable data for gunicorn (www-data) — 775, not 777
mkdir -p "$APP_DIR/data/uploads"
chmod 775 "$APP_DIR/data" "$APP_DIR/data/uploads"
if ls "$APP_DIR/data"/*.db >/dev/null 2>&1; then
  chmod 660 "$APP_DIR/data"/*.db
fi

# Secrets stay group-readable by the service account only
if [[ -f "$APP_DIR/.env" ]]; then
  chmod 640 "$APP_DIR/.env"
fi

# Example env is not secret
if [[ -f "$APP_DIR/.env.example" ]]; then
  chmod 644 "$APP_DIR/.env.example"
fi

chown -R "${OWNER}:${GROUP}" "$APP_DIR"

# Virtualenv binaries must stay executable
if [[ -d "$APP_DIR/.venv/bin" ]]; then
  chmod 755 "$APP_DIR/.venv/bin"/* 2>/dev/null || true
fi

echo "Permissions set under $APP_DIR (dirs 755, files 644, data 775, .env 640). Never 777."
