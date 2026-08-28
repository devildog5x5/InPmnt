#!/usr/bin/env bash
# Rebuild split Ubuntu patches + Hostinger tarball from git history.
set -euo pipefail
umask 022

Root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$Root"

base="${INPMNT_PATCH_BASE:-main}"
mail_commit="$(git log "$base"..HEAD --reverse --format='%H' --grep='falling back from Resend' | head -1)"
if [[ -z "$mail_commit" ]]; then
  echo "Could not find the mail-fallback commit. Set MAIL_COMMIT=... or change the grep." >&2
  exit 1
fi

git diff --no-ext-diff --binary "$base"..."$mail_commit" > patches/001-mail-smtp-fallback.patch
git diff --no-ext-diff --binary "$mail_commit"...HEAD > patches/002-invoice-send-email.patch
git diff --no-ext-diff --binary "$base"...HEAD > patches/inpmnt-mail-invoice-ubuntu.patch
sed -i 's/\r$//' patches/*.patch

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/src" "$stage/static/js" "$stage/static/css"
git show HEAD:php/src/App.php > "$stage/src/App.php"
git show HEAD:php/src/Db.php > "$stage/src/Db.php"
git show HEAD:php/src/Env.php > "$stage/src/Env.php"
git show HEAD:php/src/Mail.php > "$stage/src/Mail.php"
git show HEAD:php/.env.example > "$stage/.env.example"
git show HEAD:static/js/app.js > "$stage/static/js/app.js"
git show HEAD:static/css/app.css > "$stage/static/css/app.css"
find "$stage" -type d -exec chmod 755 {} \;
find "$stage" -type f -exec chmod 644 {} \;
( cd "$stage" && tar --numeric-owner --owner=0 --group=0 --format=ustar --sort=name \
    -czf "$Root/patches/inpmnt-hostinger-changed.tar.gz" . )

chmod 644 patches/*.patch patches/*.tar.gz patches/README.md
chmod 755 patches/make-ubuntu-archives.sh
echo "Wrote patches/ (mail commit $mail_commit)"
