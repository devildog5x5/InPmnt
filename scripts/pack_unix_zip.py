#!/usr/bin/env python3
"""Write a zip with Unix modes files=644, directories=755 (Hostinger/Ubuntu unzip)."""
from __future__ import annotations

import os
import stat
import sys
import zipfile


def unix_ext_attr(mode: int, is_dir: bool) -> int:
    full = (stat.S_IFDIR if is_dir else stat.S_IFREG) | (mode & 0o777)
    return (full & 0xFFFF) << 16


def pack(src_dir: str, zip_path: str) -> None:
    src_dir = os.path.abspath(src_dir)
    os.makedirs(os.path.dirname(os.path.abspath(zip_path)) or ".", exist_ok=True)
    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(src_dir):
            dirs[:] = [d for d in dirs if d not in {".git", "__pycache__"}]
            rel_root = os.path.relpath(root, src_dir)
            if rel_root != ".":
                info = zipfile.ZipInfo(rel_root.replace("\\", "/") + "/")
                info.external_attr = unix_ext_attr(0o755, True)
                zf.writestr(info, b"")
            for name in files:
                if name.endswith(".db"):
                    continue
                disk = os.path.join(root, name)
                arc = name if rel_root == "." else f"{rel_root.replace(chr(92), '/')}/{name}"
                info = zipfile.ZipInfo(arc.replace("\\", "/"))
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = unix_ext_attr(0o644, False)
                with open(disk, "rb") as fh:
                    zf.writestr(info, fh.read())


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: pack_unix_zip.py SRC_DIR ZIP_PATH", file=sys.stderr)
        sys.exit(2)
    pack(sys.argv[1], sys.argv[2])
    print(sys.argv[2])
