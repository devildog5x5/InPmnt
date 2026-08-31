#!/usr/bin/env bash
# Install Family Shield Pro on a fresh Ubuntu 22.04 / 24.04 VPS.
# Usage (from the unpacked zip/tarball):
#   sudo bash deploy/setup-ubuntu.sh familyshieldpro.com
set -euo pipefail

DOMAIN="${1:-familyshieldpro.com}"
APP_DIR="${APP_DIR:-/var/www/familyshieldpro}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OWNER="${APP_OWNER:-www-data}"
GROUP="${APP_GROUP:-www-data}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash deploy/setup-ubuntu.sh ${DOMAIN}"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y python3 python3-venv python3-pip nginx unzip tar rsync \
  certbot python3-certbot-nginx ca-certificates

id -u "$OWNER" >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin "$OWNER"

mkdir -p "$APP_DIR"
copy_tree() {
  local src="$1"
  local dst="$2"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
      --exclude '.venv' \
      --exclude '.env' \
      --exclude 'data/*.db' \
      --exclude '__pycache__' \
      --exclude '*.pyc' \
      --exclude '.git' \
      "$src/" "$dst/"
  else
    find "$src" -mindepth 1 -maxdepth 1 ! -name '.venv' ! -name '.env' ! -name '.git' \
      -exec cp -a {} "$dst/" \;
    rm -rf "$dst/__pycache__" "$dst/data"/*.db 2>/dev/null || true
  fi
}
if [[ "$SCRIPT_DIR" != "$APP_DIR" ]]; then
  copy_tree "$SCRIPT_DIR" "$APP_DIR"
fi

cd "$APP_DIR"

python3 -m venv .venv
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt

if [[ ! -f .env ]]; then
  cp .env.example .env
  SECRET="$(python3 -c 'import secrets; print(secrets.token_urlsafe(48))')"
  sed -i "s|^OURCIRCLE_SECRET=.*|OURCIRCLE_SECRET=${SECRET}|" .env
  if grep -q '^OURCIRCLE_SITE_URL=' .env; then
    sed -i "s|^OURCIRCLE_SITE_URL=.*|OURCIRCLE_SITE_URL=https://${DOMAIN}|" .env
  else
    echo "OURCIRCLE_SITE_URL=https://${DOMAIN}" >> .env
  fi
  sed -i "s|^OURCIRCLE_HOST=.*|OURCIRCLE_HOST=127.0.0.1|" .env
  sed -i "s|^OURCIRCLE_PORT=.*|OURCIRCLE_PORT=5065|" .env
  echo "Created ${APP_DIR}/.env — existing live .env files are never overwritten."
else
  echo "Keeping existing ${APP_DIR}/.env"
fi

mkdir -p "$APP_DIR/data/uploads"
bash "$APP_DIR/deploy/fix-permissions.sh" "$APP_DIR"

cp "$APP_DIR/deploy/familyshieldpro.service" /etc/systemd/system/familyshieldpro.service
sed "s/YOUR_DOMAIN/${DOMAIN}/g" "$APP_DIR/deploy/nginx.familyshieldpro.conf" \
  > /etc/nginx/sites-available/familyshieldpro
ln -sf /etc/nginx/sites-available/familyshieldpro /etc/nginx/sites-enabled/familyshieldpro
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl daemon-reload
systemctl enable --now familyshieldpro
systemctl reload nginx

if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp >/dev/null 2>&1 || true
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
fi

echo
echo "Family Shield Pro is proxied on http://${DOMAIN}"
echo "Point DNS A records for ${DOMAIN} and www.${DOMAIN} to this server, then:"
echo "  sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo "Health check: curl -sS http://127.0.0.1:5065/healthz"
echo "SEO files:    curl -sS http://127.0.0.1:5065/robots.txt"
echo "              curl -sS http://127.0.0.1:5065/sitemap.xml"
echo "Permissions:  sudo bash ${APP_DIR}/deploy/fix-permissions.sh"
echo "Never run chmod -R 777 on this tree."
