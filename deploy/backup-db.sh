#!/usr/bin/env bash
# Backup InPmnt SQLite DB (native or Docker volume).
set -euo pipefail

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_DIR="${1:-./backups}"
mkdir -p "$OUT_DIR"

if [[ -n "${DATABASE_PATH:-}" && -f "${DATABASE_PATH}" ]]; then
  SRC="$DATABASE_PATH"
elif [[ -f ./inpmnt.db ]]; then
  SRC="./inpmnt.db"
elif docker compose ps --status running 2>/dev/null | grep -q inpmnt; then
  TMP="$(mktemp)"
  docker compose exec -T inpmnt cat /app/data/inpmnt.db > "$TMP"
  SRC="$TMP"
else
  echo "No database found. Set DATABASE_PATH or run from the app directory / with Docker up." >&2
  exit 1
fi

DEST="$OUT_DIR/inpmnt-$STAMP.db"
cp "$SRC" "$DEST"
# Keep last 14 backups
ls -1t "$OUT_DIR"/inpmnt-*.db 2>/dev/null | tail -n +15 | xargs -r rm --
echo "Wrote $DEST"
