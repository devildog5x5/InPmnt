#!/bin/bash
# Ubuntu Apache: unzip then run this as root from the document root.
#   sudo bash /var/www/html/fix-ubuntu-perms.sh
#
# Verified layout on Ubuntu 24.04 / Apache 2.4.58 as www-data:
#   dirs 755, files 644, data/ 775 → homepage works, SQLite can create
#   document root 700 owned by ubuntu → 403 Forbidden
#   chmod -R 777 → not required (and Hostinger can 500 on 777 PHP)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
USER_NAME="${1:-www-data}"
GROUP_NAME="${2:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run with sudo: sudo bash $0"
  exit 1
fi

mkdir -p "$ROOT/data" "$ROOT/static/img"
chown -R "$USER_NAME:$GROUP_NAME" "$ROOT"
find "$ROOT" -type d -exec chmod 755 {} \;
find "$ROOT" -type f -exec chmod 644 {} \;
chmod 775 "$ROOT/data"
if [ -f "$ROOT/.env" ]; then
  chmod 640 "$ROOT/.env"
fi
if [ -f "$ROOT/fix-ubuntu-perms.sh" ]; then
  chmod 755 "$ROOT/fix-ubuntu-perms.sh"
fi
# Invoice letterhead JPEG must be world-readable or the PDF has no logo.
if [ -f "$ROOT/static/img/inpmnt-logo-invoice.jpg" ]; then
  chmod 644 "$ROOT/static/img/inpmnt-logo-invoice.jpg"
fi
if [ -f "$ROOT/static/img/inpmnt-icon.png" ]; then
  chmod 644 "$ROOT/static/img/inpmnt-icon.png"
fi
if compgen -G "$ROOT/data/*.db" >/dev/null; then
  chmod 664 "$ROOT/data/"*.db
  chown "$USER_NAME:$GROUP_NAME" "$ROOT/data/"*.db
fi

echo "OK: $ROOT"
echo "  owner  $USER_NAME:$GROUP_NAME"
echo "  dirs   755"
echo "  files  644  (PHP is not 777)"
echo "  data/  775  (SQLite + WAL)"
echo "  logo   static/img/inpmnt-logo-invoice.jpg 644"
echo "Reload Apache if needed: sudo systemctl reload apache2"
echo "Open: http://YOUR-SERVER/index.php"
