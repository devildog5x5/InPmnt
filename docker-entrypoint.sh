#!/bin/sh
set -eu

cd /app
mkdir -p /app/data

if [ ! -f /app/.env ]; then
  if [ -f /app/.env.example ]; then
    cp /app/.env.example /app/.env
  else
    touch /app/.env
  fi
fi

# Force container-friendly defaults (TLS belongs on the host / reverse proxy).
if ! grep -q '^USE_HTTPS=' /app/.env 2>/dev/null; then
  echo 'USE_HTTPS=0' >> /app/.env
else
  sed -i 's/^USE_HTTPS=.*/USE_HTTPS=0/' /app/.env
fi

if ! grep -q '^DATABASE_PATH=' /app/.env 2>/dev/null; then
  echo "DATABASE_PATH=${DATABASE_PATH:-/app/data/inpmnt.db}" >> /app/.env
fi

# Prefer a real secret from the environment; otherwise generate one into .env.
if [ -n "${FLASK_SECRET_KEY:-}" ] && [ "${FLASK_SECRET_KEY}" != "change-me-to-a-long-random-string" ]; then
  if grep -q '^FLASK_SECRET_KEY=' /app/.env 2>/dev/null; then
    sed -i "s|^FLASK_SECRET_KEY=.*|FLASK_SECRET_KEY=${FLASK_SECRET_KEY}|" /app/.env
  else
    echo "FLASK_SECRET_KEY=${FLASK_SECRET_KEY}" >> /app/.env
  fi
elif grep -qE '^FLASK_SECRET_KEY=(change-me-to-a-long-random-string)?$' /app/.env 2>/dev/null \
   || ! grep -q '^FLASK_SECRET_KEY=' /app/.env 2>/dev/null; then
  SECRET="$(python -c 'import secrets; print(secrets.token_hex(32))')"
  if grep -q '^FLASK_SECRET_KEY=' /app/.env 2>/dev/null; then
    sed -i "s|^FLASK_SECRET_KEY=.*|FLASK_SECRET_KEY=${SECRET}|" /app/.env
  else
    echo "FLASK_SECRET_KEY=${SECRET}" >> /app/.env
  fi
  export FLASK_SECRET_KEY="${SECRET}"
  echo "Generated FLASK_SECRET_KEY for this container."
fi

# Sync common Stripe / URL vars from the process environment into .env when set.
for key in BASE_URL STRIPE_SECRET_KEY STRIPE_PUBLISHABLE_KEY STRIPE_WEBHOOK_SECRET \
           STRIPE_PRICE_STARTER STRIPE_PRICE_PRO STRIPE_PRICE_ANNUAL DATABASE_PATH; do
  eval "val=\${$key:-}"
  if [ -n "$val" ]; then
    if grep -q "^${key}=" /app/.env 2>/dev/null; then
      sed -i "s|^${key}=.*|${key}=${val}|" /app/.env
    else
      echo "${key}=${val}" >> /app/.env
    fi
  fi
done

export DATABASE_PATH="${DATABASE_PATH:-/app/data/inpmnt.db}"
export USE_HTTPS=0
export PORT="${PORT:-5055}"
# Clear empty secret so Flask can load the generated value from .env
if [ -z "${FLASK_SECRET_KEY:-}" ] || [ "${FLASK_SECRET_KEY}" = "change-me-to-a-long-random-string" ]; then
  unset FLASK_SECRET_KEY || true
fi

echo "Starting InPmnt (Gunicorn) on 0.0.0.0:${PORT}"
echo "Demo login: trialuser@inpmnt.app / demo1234"
exec gunicorn \
  --bind "0.0.0.0:${PORT}" \
  --workers "${WEB_CONCURRENCY:-2}" \
  --timeout 120 \
  --access-logfile - \
  --error-logfile - \
  "run:app"
