"""Edit invoice must be visible on dashboard, list, and invoice pages."""
from __future__ import annotations

import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
JS = (ROOT / "static" / "js" / "app.js").read_text(encoding="utf-8")
CSS = (ROOT / "static" / "css" / "app.css").read_text(encoding="utf-8")


class InvoiceEditUiTests(unittest.TestCase):
    def test_edit_invoice_label_is_everywhere(self) -> None:
        self.assertGreaterEqual(JS.count("Edit invoice"), 8)
        self.assertIn("openInvoiceEditor", JS)
        self.assertIn("invoice-edit-bar", JS)
        self.assertIn('id="btn-edit"', JS)
        self.assertIn('data-edit-inv="${i.id}"', JS)
        self.assertIn('data-edit-inv="${inv.id}"', JS)
        self.assertIn('data-edit-inv="${r.invoice_id}"', JS)

    def test_details_fields_are_clickable(self) -> None:
        self.assertIn("edit-field", JS)
        self.assertIn("button.edit-field", CSS)
        self.assertIn(".invoice-edit-bar", CSS)

    def test_php_and_flask_cache_bust_app_js(self) -> None:
        php_app = (ROOT / "php" / "views" / "app.php").read_text(encoding="utf-8")
        flask_app = (ROOT / "templates" / "app.html").read_text(encoding="utf-8")
        http = (ROOT / "php" / "src" / "Http.php").read_text(encoding="utf-8")
        self.assertIn("Http::assetUrl('js/app.js')", php_app)
        self.assertIn("Http::assetUrl('css/app.css')", php_app)
        self.assertIn("function assetUrl", http)
        self.assertIn("asset_v('js/app.js')", flask_app)
        self.assertIn("asset_v('css/app.css')", flask_app)


if __name__ == "__main__":
    unittest.main()
