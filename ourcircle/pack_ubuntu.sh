#!/usr/bin/env bash
# Build FamilyShieldPro-Ubuntu.tar.gz and FamilyShieldPro-Ubuntu.zip
# with Ubuntu-safe permissions (755 dirs, 644 files, 775 data, 755 scripts).
# Never 777.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PACK_ROOT="$ROOT"
export PACK_NAME="FamilyShieldPro-Ubuntu"
export ARTIFACTS_DIR="${ARTIFACTS_DIR:-/opt/cursor/artifacts}"
export PACK_OUT="${1:-}"

python3 <<'PY'
from __future__ import annotations

import os
import shutil
import stat
import tarfile
import zipfile
from pathlib import Path

root = Path(os.environ["PACK_ROOT"]).resolve()
name = os.environ["PACK_NAME"]
artifacts = Path(os.environ["ARTIFACTS_DIR"])
out_override = os.environ.get("PACK_OUT") or ""

skip_dirs = {".venv", ".git", "dist", "__pycache__"}
skip_files = {".env"}

staging_parent = Path("/tmp") / f"pack-{name}"
if staging_parent.exists():
    shutil.rmtree(staging_parent)
stage = staging_parent / name
stage.mkdir(parents=True)


def ignored(src: str, names: list[str]) -> set[str]:
    src_path = Path(src)
    drop: set[str] = set()
    for n in names:
        if n in skip_dirs or n.endswith(".pyc") or n in skip_files:
            drop.add(n)
        elif src_path.name == "data" and n.endswith(".db"):
            drop.add(n)
    return drop


shutil.copytree(root, stage, dirs_exist_ok=True, ignore=ignored)
uploads = stage / "data" / "uploads"
uploads.mkdir(parents=True, exist_ok=True)
(uploads / ".gitkeep").write_text("", encoding="utf-8")
(stage / "INSTALL.txt").write_text(
    "Family Shield Pro — Ubuntu deploy pack\n"
    "Site: https://familyshieldpro.com\n\n"
    "1. Copy this archive to the VPS.\n"
    "2. Ubuntu 22.04 or 24.04 as root:\n"
    "     sudo apt-get update\n"
    "     sudo apt-get install -y unzip\n"
    "     sudo mkdir -p /tmp/fsp && cd /tmp/fsp\n"
    "     sudo unzip -o /path/to/FamilyShieldPro-Ubuntu.zip\n"
    "     # or: sudo tar -xzf /path/to/FamilyShieldPro-Ubuntu.tar.gz\n"
    "     sudo bash deploy/setup-ubuntu.sh familyshieldpro.com\n"
    "3. Point DNS A records for familyshieldpro.com and www to the VPS.\n"
    "4. sudo certbot --nginx -d familyshieldpro.com -d www.familyshieldpro.com\n\n"
    "Permissions (applied by setup-ubuntu.sh / fix-permissions.sh):\n"
    "  directories 755\n"
    "  files       644\n"
    "  data/       775  (www-data can write SQLite + uploads)\n"
    "  .env        640\n"
    "  Never chmod -R 777\n\n"
    "Full steps: DEPLOY.md\n"
    "Go to market: GO_TO_MARKET.md\n",
    encoding="utf-8",
)


def is_data_dir(rel: Path) -> bool:
    return rel == Path("data") or rel == Path("data/uploads")


def chmod_tree(base: Path) -> None:
    for dirpath, _dirnames, filenames in os.walk(base):
        d = Path(dirpath)
        rel = d.relative_to(base)
        d.chmod(0o775 if is_data_dir(rel) else 0o755)
        for fn in filenames:
            f = d / fn
            f.chmod(0o755 if fn.endswith(".sh") else 0o644)


chmod_tree(stage)

outdir = Path(out_override) if out_override else staging_parent / "out"
outdir.mkdir(parents=True, exist_ok=True)
tar_path = outdir / f"{name}.tar.gz"
zip_path = outdir / f"{name}.zip"


def tar_filter(ti: tarfile.TarInfo) -> tarfile.TarInfo:
    ti.uid = 0
    ti.gid = 0
    ti.uname = "www-data"
    ti.gname = "www-data"
    parts = Path(ti.name).parts[1:]
    rel = Path(*parts) if parts else Path(".")
    if ti.isdir():
        ti.mode = 0o775 if is_data_dir(rel) else 0o755
    elif ti.isfile():
        ti.mode = 0o755 if ti.name.endswith(".sh") else 0o644
    return ti


with tarfile.open(tar_path, "w:gz") as tf:
    tf.add(stage, arcname=name, filter=tar_filter)

if zip_path.exists():
    zip_path.unlink()
with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
    for dirpath, _dirnames, filenames in os.walk(stage):
        d = Path(dirpath)
        rel_dir = d.relative_to(staging_parent)
        info = zipfile.ZipInfo(rel_dir.as_posix() + "/")
        rel_in_app = d.relative_to(stage)
        dir_mode = 0o775 if is_data_dir(rel_in_app) else 0o755
        info.external_attr = (stat.S_IFDIR | dir_mode) << 16
        zf.writestr(info, b"")
        for fn in filenames:
            f = d / fn
            arc = f.relative_to(staging_parent).as_posix()
            zi = zipfile.ZipInfo.from_file(f, arc)
            file_mode = 0o755 if fn.endswith(".sh") else 0o644
            zi.external_attr = (stat.S_IFREG | file_mode) << 16
            zi.compress_type = zipfile.ZIP_DEFLATED
            zf.writestr(zi, f.read_bytes())

artifacts.mkdir(parents=True, exist_ok=True)
shutil.copy2(tar_path, artifacts / tar_path.name)
shutil.copy2(zip_path, artifacts / zip_path.name)
(artifacts / tar_path.name).chmod(0o644)
(artifacts / zip_path.name).chmod(0o644)
print("Built:")
print(f"  {tar_path}")
print(f"  {zip_path}")
print(f"Copied to {artifacts}")
print(f"  {(artifacts / tar_path.name).stat().st_size} bytes tar.gz")
print(f"  {(artifacts / zip_path.name).stat().st_size} bytes zip")
PY
