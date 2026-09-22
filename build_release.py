#!/usr/bin/env python3
"""Build InPmnt release zips into installers/. Linux-friendly twin of build_release.ps1."""
from __future__ import annotations

import shutil
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "installers"
STAGE = ROOT / "build" / "stage"

INCLUDE = [
    "app",
    "static",
    "templates",
    "assets",
    "deploy",
    "php",
    "requirements.txt",
    "run.py",
    "passenger_wsgi.py",
    "start.ps1",
    "install.ps1",
    "uninstall.ps1",
    "reset_db.ps1",
    "VERSION",
    "Dockerfile",
    "docker-compose.yml",
    "docker-entrypoint.sh",
    ".dockerignore",
    "README.md",
    "GO_TO_MARKET.md",
    ".env.example",
    ".gitignore",
    ".gitattributes",
]

PHP_HOSTINGER = """InPmnt PHP — Hostinger / shared hosting
========================================
1. Unzip ALL of these files into public_html (File Manager or FTP).
2. Copy .env.example to .env and set APP_SECRET and BASE_URL=https://yourdomain.com
3. In hPanel → Advanced → PHP Configuration: PHP 8.2+ and enable pdo_sqlite
4. Open https://yourdomain.com  → Sign up
5. Stripe webhook: https://yourdomain.com/api/billing/webhook
6. Search engines: robots.txt and sitemap.xml are in this zip (site root).
   The app fills Sitemap URLs from BASE_URL.

Do not upload into a subfolder unless that subfolder is the site document root.
The SQLite database is created automatically at data/inpmnt.db (blocked from the web).
"""


def version() -> str:
    if len(sys.argv) > 1 and sys.argv[1].strip():
        return sys.argv[1].strip()
    vf = ROOT / "VERSION"
    if vf.is_file():
        return vf.read_text(encoding="utf-8").strip() or "1.4.0"
    return "1.4.0"


def zip_tree(src: Path, dest_zip: Path, arc_prefix: str | None = None) -> None:
    if dest_zip.exists():
        dest_zip.unlink()
    dest_zip.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(dest_zip, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        files = [src] if src.is_file() else sorted(p for p in src.rglob("*") if p.is_file())
        for p in files:
            rel = p.name if src.is_file() else p.relative_to(src).as_posix()
            arc = f"{arc_prefix}/{rel}" if arc_prefix else rel
            zf.write(p, arc)


def copy_item(src: Path, dest: Path) -> None:
    if src.is_dir():
        shutil.copytree(src, dest, dirs_exist_ok=True)
    else:
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dest)


def main() -> None:
    ver = version()
    OUT.mkdir(parents=True, exist_ok=True)
    if STAGE.exists():
        shutil.rmtree(STAGE)
    STAGE.mkdir(parents=True)

    portable_dir = STAGE / "InPmnt"
    portable_dir.mkdir(parents=True)
    for item in INCLUDE:
        src = ROOT / item
        if src.exists():
            copy_item(src, portable_dir / item)

    for old in OUT.glob("InPmnt-*.zip"):
        old.unlink()

    portable_zip = OUT / f"InPmnt-Portable.v{ver}.zip"
    source_zip = OUT / f"InPmnt-Source.v{ver}.zip"
    icon_zip = OUT / f"InPmnt-Icon.v{ver}.zip"
    php_zip = OUT / f"InPmnt-PHP.v{ver}.zip"

    zip_tree(portable_dir, portable_zip, arc_prefix="InPmnt")
    shutil.copy2(portable_zip, source_zip)

    icon_stage = STAGE / "icon"
    icon_stage.mkdir()
    src_icon = ROOT / "assets" / "inpmnt-icon.png"
    if src_icon.exists():
        shutil.copy2(src_icon, icon_stage / "inpmnt-icon.png")
    src_512 = ROOT / "static" / "img" / "inpmnt-icon.png"
    if src_512.exists():
        shutil.copy2(src_512, icon_stage / "inpmnt-icon-512.png")
    zip_tree(icon_stage, icon_zip)

    php_stage = STAGE / "phpdrop"
    php_stage.mkdir()
    for p in (ROOT / "php").iterdir():
        if p.name == ".env":
            continue
        copy_item(p, php_stage / p.name)
    static_src = ROOT / "static"
    if static_src.exists():
        copy_item(static_src, php_stage / "static")
    data_dir = php_stage / "data"
    if data_dir.is_dir():
        for db in data_dir.glob("*.db"):
            db.unlink()
    (php_stage / "HOSTINGER.txt").write_text(PHP_HOSTINGER, encoding="utf-8")
    zip_tree(php_stage, php_zip)

    print(f"Built v{ver}")
    for z in (portable_zip, source_zip, icon_zip, php_zip):
        print(f"  {z}  ({z.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
