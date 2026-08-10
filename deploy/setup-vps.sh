#!/usr/bin/env bash
# Run on a fresh Ubuntu VPS (GoDaddy / similar) as root.
# Usage: sudo bash deploy/setup-vps.sh yourdomain.com
set -euo pipefail

DOMAIN="${1:-}"
if [[ -z "$DOMAIN" ]]; then
  echo "Usage: sudo bash deploy/setup-vps.sh yourdomain.com"
  exit 1
fi

APP_DIR=/var/www/inpmnt
REPO_URL="${REPO_URL:-https://github.com/devildog5x5/InPmnt.git}"

export DEBIAN_FRONTEND=noninteractive
apt update
apt install -y python3 python3-venv python3-pip nginx git certbot python3-certbot-nginx

if [[ ! -d "$APP_DIR/.git" ]]; then
  mkdir -p "$APP_DIR"
  git clone "$REPO_URL" "$APP_DIR"
else
  git -C "$APP_DIR" pull --ff-only
fi

cd "$APP_DIR"
python3 -m venv .venv
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt gunicorn

if [[ ! -f .env ]]; then
  cp .env.example .env
  SECRET=$(python3 -c 'import secrets; print(secrets.token_urlsafe(48))')
  sed -i "s|^FLASK_SECRET_KEY=.*|FLASK_SECRET_KEY=${SECRET}|" .env
  sed -i "s|^BASE_URL=.*|BASE_URL=https://${DOMAIN}|" .env
  echo "Created $APP_DIR/.env — add your Stripe keys before taking payments."
fi

chown -R www-data:www-data "$APP_DIR"

cp deploy/inpmnt.service /etc/systemd/system/inpmnt.service
sed "s/YOUR_DOMAIN/${DOMAIN}/g" deploy/nginx.inpmnt.conf > /etc/nginx/sites-available/inpmnt
ln -sf /etc/nginx/sites-available/inpmnt /etc/nginx/sites-enabled/inpmnt
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl daemon-reload
systemctl enable --now inpmnt
systemctl reload nginx

echo
echo "App is proxied on http://${DOMAIN}"
echo "Point DNS A records for ${DOMAIN} (and www) to this server, then run:"
echo "  sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo "Edit Stripe keys:  sudo nano ${APP_DIR}/.env && sudo systemctl restart inpmnt"
echo "Webhook URL:       https://${DOMAIN}/api/billing/webhook"
