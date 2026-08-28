#!/usr/bin/env python3
"""Pack InPmnt-PHP.zip with Unix modes (dirs 755, files 644, scripts 755)."""
from __future__ import annotations

import os
import stat
import zipfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "patches" / "InPmnt-PHP.zip"

SKIP_NAMES = {".env", ".DS_Store"}
SKIP_SUFFIXES = {".db"}

HOSTINGER_TXT = """InPmnt PHP — Hostinger / Ubuntu Apache
========================================
Unzip ALL of these files into public_html (or /var/www/html). Do not overwrite a live .env.

1. Copy .env.example to .env and set APP_SECRET and BASE_URL=https://yourdomain.com
2. Email: SMTP_HOST=smtp.hostinger.com SMTP_PORT=465 SMTP_SSL=1
   SMTP_USER and MAIL_FROM = your Hostinger mailbox, plus SMTP_PASSWORD.
3. hPanel → PHP 8.2+ and pdo_sqlite
4. Ubuntu Apache (not Hostinger File Manager):
     sudo bash fix-ubuntu-perms.sh
   dirs 755, files 644, data/ 775, logo 644. Never chmod -R 777.
5. Open https://yourdomain.com → Sign up
6. Settings → Email delivery → Send test email

This zip includes branded invoice PDFs (company logo top-left letterhead),
invoice email on Send, SMTP fallback, edit-after-create, Send now on the
Invoices page, and the invoice JPEG next to InvoicePdf.php.
"""


def unix_attr(mode: int, *, directory: bool) -> int:
    kind = stat.S_IFDIR if directory else stat.S_IFREG
    return (kind | (mode & 0o777)) << 16


def zipinfo(arcname: str, mode: int, *, directory: bool = False) -> zipfile.ZipInfo:
    name = arcname.replace("\\", "/")
    if directory and not name.endswith("/"):
        name += "/"
    info = zipfile.ZipInfo(name)
    info.date_time = datetime.now().timetuple()[:6]
    info.create_system = 3  # Unix
    info.external_attr = unix_attr(mode, directory=directory)
    info.compress_type = zipfile.ZIP_DEFLATED if not directory else zipfile.ZIP_STORED
    return info


def should_skip(path: Path) -> bool:
    if path.name in SKIP_NAMES:
        return True
    if path.suffix in SKIP_SUFFIXES:
        return True
    return False


def file_mode(path: Path) -> int:
    if path.suffix == ".sh" or path.name.endswith(".sh"):
        return 0o755
    return 0o644


def add_tree(zf: zipfile.ZipFile, src: Path, prefix: str, skip_rel_roots: tuple[str, ...] = ()) -> None:
    dirs_added: set[str] = set()

    def ensure_dirs(arc: str) -> None:
        parts = Path(arc).parts
        acc = []
        for p in parts[:-1]:
            acc.append(p)
            d = "/".join(acc)
            if d in dirs_added:
                continue
            zf.writestr(zipinfo(d, 0o755, directory=True), b"")
            dirs_added.add(d)

    if prefix:
        zf.writestr(zipinfo(prefix.rstrip("/"), 0o755, directory=True), b"")
        dirs_added.add(prefix.rstrip("/"))

    for path in sorted(src.rglob("*")):
        if should_skip(path):
            continue
        rel = path.relative_to(src).as_posix()
        if skip_rel_roots and path.relative_to(src).parts and path.relative_to(src).parts[0] in skip_rel_roots:
            continue
        arc = f"{prefix}{rel}" if prefix else rel
        if path.is_dir():
            if arc not in dirs_added:
                zf.writestr(zipinfo(arc, 0o755, directory=True), b"")
                dirs_added.add(arc.rstrip("/"))
            continue
        ensure_dirs(arc)
        zf.writestr(zipinfo(arc, file_mode(path)), path.read_bytes())


def main() -> None:
    OUT.parent.mkdir(parents=True, exist_ok=True)
    if OUT.exists():
        OUT.unlink()
    php = ROOT / "php"
    static = ROOT / "static"
    with zipfile.ZipFile(OUT, "w") as zf:
        add_tree(zf, php, "", skip_rel_roots=("static",))
        add_tree(zf, static, "static/")
        zf.writestr(zipinfo("HOSTINGER.txt", 0o644), HOSTINGER_TXT.encode("utf-8"))
    os.chmod(OUT, 0o644)
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
