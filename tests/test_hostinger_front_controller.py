"""Hostinger leftover WordPress must not be served instead of InPmnt."""
from __future__ import annotations

import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
HTACCESS = ROOT / "php" / ".htaccess"
STUB = ROOT / "php" / "index.html"
CLEAN = ROOT / "php" / "remove-wordpress.sh"
PHP_ZIP = ROOT / "patches" / "InPmnt-PHP.zip"


class HostingerFrontControllerTests(unittest.TestCase):
    def test_htaccess_forces_inpmnt(self) -> None:
        text = HTACCESS.read_text(encoding="utf-8")
        self.assertIn("DirectoryIndex app.php", text)
        self.assertIn("RewriteRule ^wp- - [F,L]", text)
        self.assertIn("CacheDisable public /", text)
        self.assertIn("RewriteCond %{REQUEST_URI} ^/static/", text)
        self.assertIn("RewriteRule ^index\\.php$ app.php [QSA,L]", text)
        self.assertIn("RewriteRule ^ app.php [QSA,L]", text)
        self.assertIn("RewriteRule \\.php$ - [F,L]", text)

    def test_index_html_is_inpmnt_stub(self) -> None:
        text = STUB.read_text(encoding="utf-8")
        self.assertIn('location.replace("/")', text)
        self.assertNotIn("wp-content", text)
        self.assertNotIn("WordPress", text)

    def test_remove_wordpress_script_is_executable(self) -> None:
        self.assertTrue(CLEAN.is_file())
        self.assertEqual(CLEAN.stat().st_mode & 0o777, 0o755)
        body = CLEAN.read_text(encoding="utf-8")
        self.assertIn("wp-admin", body)
        self.assertIn("bootstrap.php", body)

    def test_zip_includes_wordpress_guards(self) -> None:
        self.assertTrue(PHP_ZIP.is_file(), "run python3 scripts/pack_php_zip.py")
        with zipfile.ZipFile(PHP_ZIP) as zf:
            names = {info.filename.rstrip("/") for info in zf.infolist()}
            ht = zf.read(".htaccess").decode("utf-8")
        self.assertIn(".htaccess", names)
        self.assertIn("index.html", names)
        self.assertIn("remove-wordpress.sh", names)
        self.assertIn("DirectoryIndex app.php", ht)
        self.assertIn("RewriteRule ^wp- - [F,L]", ht)
        self.assertIn("app.php", names)


if __name__ == "__main__":
    unittest.main()
