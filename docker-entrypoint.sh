#!/bin/sh
set -eu

cd /app
mkdir -p /app/data

# Named volumes often mount as root — fix ownership before dropping privileges.
if [ "$(id -u)" = "0" ]; then
  chown -R inpmnt:inpmnt /app/data /app/.env 2>/dev/null || chown -R inpmnt:inpmnt /app/data
fi

if [ ! -f /app/.env ]; then
  if [ -f /app/.env.example ]; then
    cp /app/.env.example /app/.env
  else
    touch /app/.env
  fi
  if [ "$(id -u)" = "0" ]; then
    chown inpmnt:inpmnt /app/.env
  fi
fi

# Merge container env into .env (safe for special characters) + force Docker defaults.
python - <<'PY'
import os
import secrets
from pathlib import Path

env_path = Path("/app/.env")
lines = env_path.read_text(encoding="utf-8").splitlines() if env_path.exists() else []
data: dict[str, str] = {}
order: list[str] = []
for line in lines:
    if not line.strip() or line.lstrip().startswith("#") or "=" not in line:
        continue
    k, v = line.split("=", 1)
    k = k.strip()
    if k and k not in data:
        order.append(k)
    data[k] = v

def set_key(key: str, value: str) -> None:
    if key not in data:
        order.append(key)
    data[key] = value

set_key("USE_HTTPS", "0")
set_key("DATABASE_PATH", os.environ.get("DATABASE_PATH") or data.get("DATABASE_PATH") or "/app/data/inpmnt.db")

secret = (os.environ.get("FLASK_SECRET_KEY") or data.get("FLASK_SECRET_KEY") or "").strip()
if not secret or secret == "change-me-to-a-long-random-string":
    secret = secrets.token_hex(32)
    print("Generated FLASK_SECRET_KEY for this container.")
set_key("FLASK_SECRET_KEY", secret)

for key in (
    "BASE_URL",
    "STRIPE_SECRET_KEY",
    "STRIPE_PUBLISHABLE_KEY",
    "STRIPE_WEBHOOK_SECRET",
    "STRIPE_PRICE_STARTER",
    "STRIPE_PRICE_PRO",
    "STRIPE_PRICE_ANNUAL",
):
    val = (os.environ.get(key) or "").strip()
    if val:
        set_key(key, val)

out = []
for key in order:
    out.append(f"{key}={data[key]}")
env_path.write_text("\n".join(out) + "\n", encoding="utf-8")
PY

export DATABASE_PATH="${DATABASE_PATH:-/app/data/inpmnt.db}"
export USE_HTTPS=0
export PORT="${PORT:-5055}"
# Prefer values from .env over empty Compose placeholders.
unset FLASK_SECRET_KEY || true

echo "Starting InPmnt (Gunicorn) on 0.0.0.0:${PORT}"
echo "Demo login: trialuser@inpmnt.app / demo1234"

# SQLite + multi-worker is unsafe; default to 1 unless overridden carefully.
WORKERS="${WEB_CONCURRENCY:-1}"

run_gunicorn() {
  exec gunicorn \
    --bind "0.0.0.0:${PORT}" \
    --workers "${WORKERS}" \
    --timeout 120 \
    --access-logfile - \
    --error-logfile - \
    "run:app"
}

export HOME=/home/inpmnt
export USER=inpmnt

if [ "$(id -u)" = "0" ]; then
  chown -R inpmnt:inpmnt /app/data /app/.env /home/inpmnt
  exec setpriv --reuid=10001 --regid=10001 --clear-groups -- \
    gunicorn \
      --bind "0.0.0.0:${PORT}" \
      --workers "${WORKERS}" \
      --timeout 120 \
      --access-logfile - \
      --error-logfile - \
      "run:app"
else
  run_gunicorn
fi
