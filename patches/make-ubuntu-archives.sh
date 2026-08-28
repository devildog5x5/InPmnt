#!/usr/bin/env bash
# Rebuild split Ubuntu patches + Hostinger tarball from git history.
set -euo pipefail
umask 022

Root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$Root"

base="${INPMNT_PATCH_BASE:-main}"
mail_commit="$(git log "$base"..HEAD --reverse --format='%H' --grep='falling back from Resend' | head -1)"
invoice_commit="$(git log "$base"..HEAD --reverse --format='%H' --grep='Clarify invoice detail copy' | head -1)"
if [[ -z "$mail_commit" ]]; then
  echo "Could not find the mail-fallback commit. Set MAIL_COMMIT=... or change the grep." >&2
  exit 1
fi
if [[ -z "$invoice_commit" ]]; then
  echo "Could not find the invoice-send freeze commit (Clarify invoice detail copy)." >&2
  exit 1
fi

excl=(-- . ':(exclude)patches' ':(exclude)installers' ':(exclude).cursor')
git diff --no-ext-diff --binary "$base"..."$mail_commit" "${excl[@]}" > patches/001-mail-smtp-fallback.patch
git diff --no-ext-diff --binary "$mail_commit"..."$invoice_commit" "${excl[@]}" > patches/002-invoice-send-email.patch
git diff --no-ext-diff --binary "$invoice_commit"...HEAD "${excl[@]}" > patches/003-invoice-pdf.patch
git diff --no-ext-diff --binary "$base"...HEAD "${excl[@]}" > patches/inpmnt-mail-invoice-ubuntu.patch
sed -i 's/\r$//' patches/*.patch

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/src" "$stage/static/js" "$stage/static/css" "$stage/static/img"
git show HEAD:php/src/App.php > "$stage/src/App.php"
git show HEAD:php/src/Db.php > "$stage/src/Db.php"
git show HEAD:php/src/Env.php > "$stage/src/Env.php"
git show HEAD:php/src/Mail.php > "$stage/src/Mail.php"
git show HEAD:php/src/Http.php > "$stage/src/Http.php"
git show HEAD:php/src/InvoicePdf.php > "$stage/src/InvoicePdf.php"
git show HEAD:php/bootstrap.php > "$stage/bootstrap.php"
git show HEAD:php/.env.example > "$stage/.env.example"
git show HEAD:static/js/app.js > "$stage/static/js/app.js"
git show HEAD:static/css/app.css > "$stage/static/css/app.css"
git show HEAD:static/img/inpmnt-logo-invoice.jpg > "$stage/static/img/inpmnt-logo-invoice.jpg"
if git cat-file -e HEAD:php/src/inpmnt-logo-invoice.jpg 2>/dev/null; then
  git show HEAD:php/src/inpmnt-logo-invoice.jpg > "$stage/src/inpmnt-logo-invoice.jpg"
fi
if git cat-file -e HEAD:php/fix-ubuntu-perms.sh 2>/dev/null; then
  git show HEAD:php/fix-ubuntu-perms.sh > "$stage/fix-ubuntu-perms.sh"
fi
find "$stage" -type d -exec chmod 755 {} \;
find "$stage" -type f -exec chmod 644 {} \;
if [[ -f "$stage/fix-ubuntu-perms.sh" ]]; then
  chmod 755 "$stage/fix-ubuntu-perms.sh"
fi
( cd "$stage" && tar --numeric-owner --owner=0 --group=0 --format=ustar --sort=name \
    -czf "$Root/patches/inpmnt-hostinger-changed.tar.gz" . )

python3 "$Root/scripts/pack_php_zip.py"

zip_stage="$(mktemp -d)"
mkdir -p "$zip_stage/inpmnt-ubuntu-patches"
cp "$Root/patches/001-mail-smtp-fallback.patch" \
   "$Root/patches/002-invoice-send-email.patch" \
   "$Root/patches/003-invoice-pdf.patch" \
   "$Root/patches/inpmnt-mail-invoice-ubuntu.patch" \
   "$Root/patches/inpmnt-hostinger-changed.tar.gz" \
   "$Root/patches/InPmnt-PHP.zip" \
   "$Root/patches/README.md" \
   "$zip_stage/inpmnt-ubuntu-patches/"
chmod 644 "$zip_stage/inpmnt-ubuntu-patches"/*
(
  cd "$zip_stage"
  rm -f "$Root/patches/inpmnt-ubuntu-patches.zip"
  zip -X -r "$Root/patches/inpmnt-ubuntu-patches.zip" inpmnt-ubuntu-patches
)

chmod 644 patches/*.patch patches/*.tar.gz patches/*.zip patches/README.md
chmod 755 patches/make-ubuntu-archives.sh
if [[ -f php/fix-ubuntu-perms.sh ]]; then
  chmod 755 php/fix-ubuntu-perms.sh
fi
if [[ -f scripts/check-ubuntu-perms.sh ]]; then
  chmod 755 scripts/check-ubuntu-perms.sh
  bash scripts/check-ubuntu-perms.sh
fi
echo "Wrote patches/ (mail $mail_commit invoice $invoice_commit)"
