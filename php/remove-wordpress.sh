#!/usr/bin/env bash
# Delete leftover WordPress from this public_html so InPmnt always serves /.
# Run from the document root (Hostinger File Manager → Terminal, or SSH):
#   bash remove-wordpress.sh
set -euo pipefail
Root="$(cd "$(dirname "$0")" && pwd)"
cd "$Root"

if [[ ! -f app.php ]] || ! grep -q 'bootstrap.php' app.php; then
  echo "This folder does not look like InPmnt (app.php must load bootstrap.php)." >&2
  echo "cd into public_html first." >&2
  exit 1
fi

removed=0
for path in wp-admin wp-content wp-includes wordpress; do
  if [[ -e "$path" ]]; then
    rm -rf "$path"
    echo "removed $path/"
    removed=$((removed + 1))
  fi
done

# WordPress root files. Keep InPmnt index.php / index.html.
shopt -s nullglob
for path in wp-*.php xmlrpc.php license.txt readme.html \
            wp-config.php wp-config-sample.php default.html home.html coming-soon.html; do
  if [[ -e "$path" ]]; then
    rm -f "$path"
    echo "removed $path"
    removed=$((removed + 1))
  fi
done

# Replace a leftover WordPress/Hostinger homepage with the InPmnt stub.
stub="$(cat <<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="refresh" content="0;url=/" />
  <title>InPmnt</title>
  <script>location.replace("/");</script>
</head>
<body>
  <p><a href="/">Continue to InPmnt</a></p>
</body>
</html>
HTML
)"
printf '%s\n' "$stub" > index.html
chmod 644 index.html
echo "wrote index.html (redirects to InPmnt)"

if [[ -f .htaccess ]] && ! grep -q 'DirectoryIndex app.php' .htaccess; then
  echo "WARNING: .htaccess is not InPmnt's. Re-upload .htaccess from InPmnt-PHP.zip." >&2
fi

echo
echo "Removed $removed WordPress leftover(s)."
echo "Then in hPanel: Cache → Purge All (and CDN purge if you use it)."
echo "Also: WordPress → Delete this installation so Hostinger does not restore it."
