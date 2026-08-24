#!/bin/bash
# Ubuntu Apache: unzip then run this as root from the document root.
#   sudo bash /var/www/html/fix-ubuntu-perms.sh
#
# Verified on Ubuntu 24.04 / Apache 2.4.58 as www-data:
#   unzip as ubuntu, 755/644, data 755 → HTTP 500 (SQLite not writable)
#   this script (www-data, data 775)   → homepage 200, signup 302, PHP 644
#   document root 700 owned by ubuntu  → 403 Forbidden
#   chmod -R 777                       → not required
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
USER_NAME="${1:-www-data}"
GROUP_NAME="${2:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run with sudo: sudo bash $0"
  exit 1
fi

mkdir -p "$ROOT/data"
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
if compgen -G "$ROOT/data/*.db" >/dev/null; then
  chmod 664 "$ROOT/data/"*.db
  chown "$USER_NAME:$GROUP_NAME" "$ROOT/data/"*.db
fi

echo "OK: $ROOT"
echo "  owner  $USER_NAME:$GROUP_NAME"
echo "  dirs   755"
echo "  files  644  (PHP is not 777)"
echo "  data/  775  (SQLite + WAL)"
echo "Reload Apache if needed: sudo systemctl reload apache2"
echo "Open: http://YOUR-SERVER/index.php"
