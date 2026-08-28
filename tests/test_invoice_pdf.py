"""Professional invoice PDF generator."""
from __future__ import annotations

import unittest

from app.invoice_pdf import (
    build_invoice_pdf,
    invoice_pdf_payload,
    logo_path,
    mention_attachment,
    pdf_filename,
)


SAMPLE = {
    "business_name": "Foster Field Services",
    "number": "INV-1042",
    "client_name": "Maya Chen",
    "client_company": "Chen Landscape",
    "client_email": "maya@client.example",
    "client_phone": "555-0100",
    "title": "Spring cleanup",
    "notes": "Thank you for choosing us this season.",
    "issue_date": "2026-04-01",
    "due_date": "2026-04-15",
    "status": "sent",
    "amount": "$1,250.00",
    "amount_paid": "$0.00",
    "amount_due": "$1,250.00",
    "owner_name": "Robert Foster",
    "business_email": "billing@example.com",
    "business_phone": "555-0199",
    "website": "inpmnt.com",
    "currency": "USD",
}


class InvoicePdfTests(unittest.TestCase):
    def test_pdf_magic_and_branding(self) -> None:
        pdf = build_invoice_pdf(SAMPLE)
        self.assertTrue(pdf.startswith(b"%PDF-1.4"))
        self.assertIn(b"%%EOF", pdf[-32:])
        self.assertIn(b"/BaseFont /Helvetica", pdf)
        self.assertIn(b"INVOICE", pdf)
        self.assertIn(b"INV-1042", pdf)
        self.assertIn(b"Foster Field Services", pdf)
        self.assertIn(b"Maya Chen", pdf)
        self.assertIn(b"Spring cleanup", pdf)
        self.assertIn(b"InPmnt  \xb7  Professional", pdf)
        self.assertNotIn(b"? Professional", pdf)
        self.assertTrue(logo_path() is not None)
        self.assertIn(b"/Im1", pdf)
        self.assertIn(b"/DCTDecode", pdf)
        # Company logo sits top-left on the letterhead (88pt at x=40, y=680), not the old 52pt dark-header slot.
        self.assertIn(b"q 88.00 0 0 88.00 40.00 680.00 cm /Im1 Do Q", pdf)
        self.assertNotIn(b"q 52 0 0 52 40 698", pdf)
        self.assertIn(b"/Width 160", pdf)
        self.assertIn(b"/Height 160", pdf)
        self.assertNotIn(b"endstreamendobj", pdf)

    def test_payload_and_filename(self) -> None:
        payload = invoice_pdf_payload(
            {"number": "INV-7", "amount": 10, "amount_paid": 2.5, "title": "Job"},
            {"business_name": "Pat Co", "currency": "USD"},
        )
        self.assertEqual(payload["amount"], "$10.00")
        self.assertEqual(payload["amount_paid"], "$2.50")
        self.assertEqual(payload["amount_due"], "$7.50")
        self.assertEqual(pdf_filename("INV-7"), "INV-7.pdf")
        self.assertEqual(pdf_filename("weird name.pdf"), "weird_name.pdf")

    def test_mention_attachment_once(self) -> None:
        body = "Hi Maya,\n\nPlease pay."
        once = mention_attachment(body)
        self.assertIn("PDF copy of this invoice is attached", once)
        twice = mention_attachment(once)
        self.assertEqual(twice.count("PDF copy of this invoice is attached"), 1)


if __name__ == "__main__":
    unittest.main()
