#!/usr/bin/env bash
# Build FamilyShieldPro.zip — unzip into Hostinger public_html. No VPS.
# Permissions are baked in: dirs 755, data 775, files 644. Never 777.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PACK_ROOT="$ROOT"
export ARTIFACTS_DIR="${ARTIFACTS_DIR:-/opt/cursor/artifacts}"
python3 <<'PY'
from __future__ import annotations

import os
import shutil
import stat
import zipfile
from pathlib import Path

root = Path(os.environ["PACK_ROOT"]).resolve()
artifacts = Path(os.environ["ARTIFACTS_DIR"])
staging_parent = Path("/tmp/pack-FamilyShieldPro")
if staging_parent.exists():
    shutil.rmtree(staging_parent)
stage = staging_parent / "drop"
stage.mkdir(parents=True)

php = root / "php"
skip = {".env", "__pycache__"}


def ignored(src: str, names: list[str]) -> set[str]:
    src_path = Path(src)
    drop: set[str] = set()
    for n in names:
        if n in skip or n.endswith(".pyc") or n.endswith(".db"):
            drop.add(n)
    return drop


shutil.copytree(php, stage, dirs_exist_ok=True, ignore=ignored)
# Logos + CSS from the Flask tree (same files the site already uses)
static_src = root / "static"
static_dst = stage / "static"
if static_src.is_dir():
    shutil.copytree(static_src, static_dst, dirs_exist_ok=True)

version = (root / "VERSION").read_text(encoding="utf-8").strip() or "0.0.0"
shutil.copy2(root / "VERSION", stage / "VERSION")
stripe_doc = root / "STRIPE.md"
if stripe_doc.is_file():
    shutil.copy2(stripe_doc, stage / "STRIPE.md")
import subprocess
git = ""
try:
    git = subprocess.check_output(
        ["git", "-C", str(root.parent), "rev-parse", "--short", "HEAD"],
        text=True,
    ).strip()
except Exception:
    git = "unknown"
from datetime import datetime, timezone
stamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
(stage / "BUILD.txt").write_text(
    "Product: Family Shield Pro (OurCircle)\n"
    f"Version: {version}\n"
    "Channel: Hostinger-PHP\n"
    "Site: https://familyshieldpro.com\n"
    "Not: InPmnt\n"
    f"Git: {git}\n"
    f"Built: {stamp}\n"
    "Unzip into public_html. Never chmod 777.\n",
    encoding="utf-8",
)

uploads = stage / "data" / "uploads"
uploads.mkdir(parents=True, exist_ok=True)
(uploads / ".gitkeep").write_text("", encoding="utf-8")
ht = (php / "data" / "uploads" / ".htaccess")
if ht.is_file():
    shutil.copy2(ht, uploads / ".htaccess")


def is_data_dir(rel: Path) -> bool:
    return rel == Path("data") or rel == Path("data/uploads")


for dirpath, _dns, filenames in os.walk(stage):
    d = Path(dirpath)
    rel = d.relative_to(stage)
    d.chmod(0o775 if is_data_dir(rel) else 0o755)
    for fn in filenames:
        (d / fn).chmod(0o644)

outdir = staging_parent / "out"
outdir.mkdir(parents=True, exist_ok=True)
zip_alias = outdir / "FamilyShieldPro.zip"
zip_versioned = outdir / f"FamilyShieldPro-{version}.zip"
if zip_alias.exists():
    zip_alias.unlink()

with zipfile.ZipFile(zip_alias, "w", compression=zipfile.ZIP_DEFLATED) as zf:
    # Files at zip root so unzip-in-public_html works (no extra folder).
    for dirpath, _dns, filenames in os.walk(stage):
        d = Path(dirpath)
        rel = d.relative_to(stage)
        if rel != Path("."):
            info = zipfile.ZipInfo(rel.as_posix() + "/")
            mode = 0o775 if is_data_dir(rel) else 0o755
            info.create_system = 3
            info.external_attr = (stat.S_IFDIR | mode) << 16
            zf.writestr(info, b"")
        for fn in filenames:
            f = d / fn
            arc = f.relative_to(stage).as_posix()
            zi = zipfile.ZipInfo.from_file(f, arc)
            zi.create_system = 3
            zi.external_attr = (stat.S_IFREG | 0o644) << 16
            zi.compress_type = zipfile.ZIP_DEFLATED
            zf.writestr(zi, f.read_bytes())
shutil.copy2(zip_alias, zip_versioned)

artifacts.mkdir(parents=True, exist_ok=True)
patches = root / "patches"
patches.mkdir(parents=True, exist_ok=True)
repo_patches = root.parent / "patches"
repo_patches.mkdir(parents=True, exist_ok=True)

def publish(src: Path, *dests: Path) -> None:
    for dest in dests:
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dest)
        try:
            dest.chmod(0o644)
        except OSError:
            pass

publish(
    zip_alias,
    artifacts / "FamilyShieldPro.zip",
    patches / "FamilyShieldPro.zip",
    repo_patches / "FamilyShieldPro.zip",
)
publish(
    zip_versioned,
    artifacts / zip_versioned.name,
    patches / zip_versioned.name,
    repo_patches / zip_versioned.name,
)
for name in ("robots.txt", "sitemap.xml"):
    publish(
        stage / name,
        artifacts / name,
        patches / name,
        repo_patches / name,
    )
print(f"Built Family Shield Pro v{version} (not InPmnt)")
print(f"  {artifacts / zip_versioned.name}")
print(f"  {artifacts / 'FamilyShieldPro.zip'}")
print("Unzip into public_html. Folders 755, data 775, files 644. Never 777.")
PY
