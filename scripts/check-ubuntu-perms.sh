#!/usr/bin/env bash
# Fail if Linux/Ubuntu modes are wrong for Apache (www-data).
# Dirs 755, files 644, scripts 755, invoice logo world-readable.
set -euo pipefail

Root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$Root"
fail=0

note() { echo "PERM $*" >&2; fail=1; }

mode_of() { stat -c '%a' "$1"; }

# World-readable: other-read bit (004).
world_readable() {
  local m
  m=$(stat -c '%a' "$1")
  local last="${m: -1}"
  [[ "$last" == "4" || "$last" == "5" || "$last" == "6" || "$last" == "7" ]]
}

check_tree() {
  local base="$1"
  [[ -d "$base" ]] || return 0
  while IFS= read -r -d '' d; do
    local m
    m=$(mode_of "$d")
    if [[ "$d" == */data && ( "$m" == "755" || "$m" == "775" ) ]]; then
      continue
    fi
    if [[ "$m" != "755" ]]; then
      note "DIR $d is $m, want 755"
    fi
  done < <(find "$base" -type d -print0)

  while IFS= read -r -d '' f; do
    case "$f" in
      *.db|*/.env) continue ;;
    esac
    local m want="644"
    m=$(mode_of "$f")
    if [[ "$f" == *.sh ]]; then
      want="755"
    fi
    if [[ "$m" != "$want" ]]; then
      note "FILE $f is $m, want $want"
    fi
  done < <(find "$base" -type f -print0)
}

check_tree php
check_tree static
check_tree scripts
check_tree patches

for sh in deploy/setup-vps.sh deploy/backup-db.sh docker-entrypoint.sh patches/make-ubuntu-archives.sh php/fix-ubuntu-perms.sh scripts/check-ubuntu-perms.sh; do
  if [[ -f "$sh" ]]; then
    m=$(mode_of "$sh")
    if [[ "$m" != "755" ]]; then
      note "$sh is $m, want 755"
    fi
  fi
done

logo="static/img/inpmnt-logo-invoice.jpg"
if [[ ! -f "$logo" ]]; then
  note "missing $logo (invoice PDF has no company logo)"
elif ! world_readable "$logo"; then
  note "$logo is $(mode_of "$logo") — www-data cannot read it (want 644)"
fi

icon="static/img/inpmnt-icon.png"
if [[ -f "$icon" ]] && ! world_readable "$icon"; then
  note "$icon is $(mode_of "$icon") — www-data cannot read it (want 644)"
fi

# PHP must not be world-writable (Hostinger 500 on 777).
while IFS= read -r -d '' f; do
  m=$(mode_of "$f")
  last="${m: -1}"
  if [[ "$last" == "2" || "$last" == "3" || "$last" == "6" || "$last" == "7" ]]; then
    note "PHP $f is world-writable ($m) — set 644"
  fi
done < <(find php -name '*.php' -type f -print0)

tar="patches/inpmnt-hostinger-changed.tar.gz"
if [[ -f "$tar" ]]; then
  python3 - "$tar" <<'PY' || fail=1
import sys
import tarfile

path = sys.argv[1]
needed = {
    "./static/img/inpmnt-logo-invoice.jpg",
    "./src/inpmnt-logo-invoice.jpg",
    "./src/InvoicePdf.php",
    "./static/js/app.js",
    "./static/css/app.css",
    "./fix-ubuntu-perms.sh",
}
found = set()
bad = False
with tarfile.open(path, "r:gz") as tf:
    for m in tf.getmembers():
        name = m.name if m.name.startswith("./") else "./" + m.name.lstrip("/")
        found.add(name)
        mode = m.mode & 0o777
        if m.isdir():
            if mode != 0o755:
                print(f"PERM TAR DIR {m.name} is {mode:03o}, want 755", file=sys.stderr)
                bad = True
            continue
        if m.isfile():
            want = 0o755 if m.name.endswith(".sh") else 0o644
            if mode != want:
                print(f"PERM TAR FILE {m.name} is {mode:03o}, want {want:03o}", file=sys.stderr)
                bad = True
missing = needed - found
# tar may store without ./
if missing:
    found2 = {n[2:] if n.startswith("./") else n for n in found}
    missing = {n for n in needed if n not in found and n[2:] not in found2}
if missing:
    print("PERM TAR missing: " + ", ".join(sorted(missing)), file=sys.stderr)
    bad = True
sys.exit(1 if bad else 0)
PY
else
  note "missing $tar"
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Ubuntu permission check FAILED." >&2
  exit 1
fi
echo "Ubuntu permission check OK (dirs 755, files 644, scripts 755, logo readable)."
