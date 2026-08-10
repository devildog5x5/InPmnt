# InPmnt — Linux container (Gunicorn)
FROM python:3.12-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PORT=5055 \
    USE_HTTPS=0 \
    DATABASE_PATH=/app/data/inpmnt.db \
    BASE_URL=http://127.0.0.1:5055 \
    WEB_CONCURRENCY=1

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --uid 10001 --shell /usr/sbin/nologin inpmnt

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app
COPY templates ./templates
COPY static ./static
COPY assets ./assets
COPY run.py .
COPY .env.example .
COPY docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh \
    && mkdir -p /app/data \
    && chown -R inpmnt:inpmnt /app

# Entrypoint starts as root to chown the data volume, then drops to uid 10001.
USER root
EXPOSE 5055

HEALTHCHECK --interval=30s --timeout=5s --start-period=25s --retries=3 \
  CMD curl -fsS http://127.0.0.1:5055/ >/dev/null || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]
