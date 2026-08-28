"""Ubuntu/Apache modes: dirs 755, files 644, invoice logo world-readable."""
from __future__ import annotations

import os
import tarfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TAR = ROOT / "patches" / "inpmnt-hostinger-changed.tar.gz"
LOGO = ROOT / "static" / "img" / "inpmnt-logo-invoice.jpg"


def _mode(path: Path) -> int:
    return path.stat().st_mode & 0o777


class UbuntuPermsTests(unittest.TestCase):
    def test_logo_world_readable(self) -> None:
        self.assertTrue(LOGO.is_file(), "invoice logo JPEG missing")
        self.assertTrue(os.access(LOGO, os.R_OK))
        self.assertEqual(_mode(LOGO), 0o644)

    def test_php_and_scripts_modes(self) -> None:
        pdf = ROOT / "php" / "src" / "InvoicePdf.php"
        self.assertEqual(_mode(pdf), 0o644)
        self.assertFalse(_mode(pdf) & 0o002, "PHP must not be world-writable")
        for rel in (
            "php/fix-ubuntu-perms.sh",
            "scripts/check-ubuntu-perms.sh",
            "patches/make-ubuntu-archives.sh",
            "deploy/setup-vps.sh",
            "docker-entrypoint.sh",
        ):
            path = ROOT / rel
            self.assertTrue(path.is_file(), rel)
            self.assertEqual(_mode(path), 0o755, rel)

    def test_hostinger_tarball_modes(self) -> None:
        self.assertTrue(TAR.is_file(), "regenerate with bash patches/make-ubuntu-archives.sh")
        names: set[str] = set()
        with tarfile.open(TAR, "r:gz") as tf:
            for member in tf.getmembers():
                names.add(member.name.lstrip("./"))
                mode = member.mode & 0o777
                if member.isdir():
                    self.assertEqual(mode, 0o755, member.name)
                elif member.isfile():
                    want = 0o755 if member.name.endswith(".sh") else 0o644
                    self.assertEqual(mode, want, f"{member.name} {mode:03o}")
        for needed in (
            "static/img/inpmnt-logo-invoice.jpg",
            "src/inpmnt-logo-invoice.jpg",
            "src/InvoicePdf.php",
            "fix-ubuntu-perms.sh",
        ):
            self.assertIn(needed, names)


if __name__ == "__main__":
    unittest.main()
