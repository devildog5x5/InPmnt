"""Sending the invoice itself emails the client (not just reminders)."""
from __future__ import annotations

import os
import tempfile
import unittest
from datetime import date
from unittest.mock import patch

from app import create_app
from app.database import init_db


class InvoiceSendTests(unittest.TestCase):
    def setUp(self) -> None:
        self._saved = {
            k: os.environ.get(k)
            for k in (
                "DATABASE_PATH",
                "SMTP_HOST",
                "SMTP_USER",
                "MAIL_FROM",
                "SMTP_PASSWORD",
                "SMTP_PORT",
                "SMTP_SSL",
                "RESEND_API_KEY",
                "MAIL_PROVIDER",
            )
        }
        fd, self.db_path = tempfile.mkstemp(suffix=".db")
        os.close(fd)
        os.environ["DATABASE_PATH"] = self.db_path
        os.environ["SMTP_HOST"] = "smtp.hostinger.com"
        os.environ["SMTP_PORT"] = "465"
        os.environ["SMTP_USER"] = "billing@example.com"
        os.environ["SMTP_PASSWORD"] = "secret"
        os.environ["SMTP_SSL"] = "1"
        os.environ["MAIL_FROM"] = "billing@example.com"
        os.environ["MAIL_PROVIDER"] = "smtp"
        os.environ.pop("RESEND_API_KEY", None)
        init_db(self.db_path)
        app = create_app()
        app.config["TESTING"] = True
        app.config["DATABASE"] = self.db_path
        self.client = app.test_client()
        signup = self.client.post(
            "/signup",
            data={
                "name": "Pat",
                "business_name": "Pat Co",
                "email": "pat@example.com",
                "password": "password123",
            },
        )
        self.assertIn(signup.status_code, (302, 303))
        created = self.client.post(
            "/api/clients",
            json={
                "name": "Maya Chen",
                "email": "maya@client.example",
                "company": "Chen Landscape",
            },
        )
        self.assertEqual(created.status_code, 201, created.get_json())
        self.client_id = created.get_json()["id"]

    def tearDown(self) -> None:
        try:
            os.remove(self.db_path)
        except OSError:
            pass
        for key, value in self._saved.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def _create_draft(self) -> dict:
        res = self.client.post(
            "/api/invoices",
            json={
                "client_id": self.client_id,
                "title": "Spring cleanup",
                "amount": 250,
                "status": "draft",
            },
        )
        self.assertEqual(res.status_code, 201, res.get_json())
        body = res.get_json()
        self.assertFalse(body.get("emailed"))
        self.assertEqual(body["status"], "draft")
        return body

    def test_send_emails_client_and_marks_sent(self) -> None:
        inv = self._create_draft()
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.post(f"/api/invoices/{inv['id']}/send", json={})
            self.assertEqual(res.status_code, 200, res.get_json())
            body = res.get_json()
            self.assertTrue(body["emailed"])
            self.assertEqual(body["status"], "sent")
            self.assertEqual(body["client_email"], "maya@client.example")
            self.assertEqual(body.get("mail_provider"), "smtp")
            send.assert_called_once()
            kwargs = send.call_args.kwargs
            self.assertEqual(kwargs["to"], "maya@client.example")
            self.assertIn(inv["number"], kwargs["subject"])
            self.assertIn("Spring cleanup", kwargs["body"])
            self.assertIn("$250.00", kwargs["body"])
            self.assertIn("PDF copy of this invoice is attached", kwargs["body"])
            atts = kwargs.get("attachments") or []
            self.assertEqual(len(atts), 1)
            self.assertTrue(str(atts[0]["filename"]).endswith(".pdf"))
            self.assertTrue(atts[0]["content"].startswith(b"%PDF"))
            self.assertIn(b"/Im1", atts[0]["content"])

    def test_create_send_now_emails(self) -> None:
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.post(
                "/api/invoices",
                json={
                    "client_id": self.client_id,
                    "title": "Fence repair",
                    "amount": 80,
                    "status": "sent",
                },
            )
            self.assertEqual(res.status_code, 201, res.get_json())
            body = res.get_json()
            self.assertTrue(body["emailed"])
            self.assertEqual(body["status"], "sent")
            send.assert_called_once()
            self.assertEqual(send.call_args.kwargs["to"], "maya@client.example")
            self.assertIn("Fence repair", send.call_args.kwargs["body"])
            atts = send.call_args.kwargs.get("attachments") or []
            self.assertEqual(len(atts), 1)
            self.assertTrue(atts[0]["content"].startswith(b"%PDF"))

    def test_download_pdf(self) -> None:
        inv = self._create_draft()
        res = self.client.get(f"/api/invoices/{inv['id']}/pdf")
        self.assertEqual(res.status_code, 200, res.data[:200])
        self.assertTrue(res.data.startswith(b"%PDF"))
        self.assertIn("pdf", (res.mimetype or "").lower())
        self.assertIn(b"INVOICE", res.data)

    def test_missing_client_email_blocks_send(self) -> None:
        no_mail = self.client.post("/api/clients", json={"name": "No Email Co"})
        self.assertEqual(no_mail.status_code, 201)
        cid = no_mail.get_json()["id"]
        inv = self.client.post(
            "/api/invoices",
            json={"client_id": cid, "title": "Job", "amount": 10, "status": "draft"},
        ).get_json()
        res = self.client.post(f"/api/invoices/{inv['id']}/send", json={})
        self.assertEqual(res.status_code, 400)
        self.assertIn("email", (res.get_json().get("error") or "").lower())
        still = self.client.get(f"/api/invoices/{inv['id']}").get_json()
        self.assertEqual(still["status"], "draft")

    def test_update_draft_then_send(self) -> None:
        inv = self._create_draft()
        res = self.client.put(
            f"/api/invoices/{inv['id']}",
            json={
                "title": "Spring cleanup + mulch",
                "amount": 275,
                "notes": "Updated after walkthrough",
            },
        )
        self.assertEqual(res.status_code, 200, res.get_json())
        body = res.get_json()
        self.assertEqual(body["title"], "Spring cleanup + mulch")
        self.assertEqual(body["amount"], 275)
        self.assertEqual(body["notes"], "Updated after walkthrough")
        self.assertEqual(body["status"], "draft")
        self.assertFalse(body.get("emailed"))

        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            sent = self.client.post(f"/api/invoices/{inv['id']}/send", json={})
            self.assertEqual(sent.status_code, 200, sent.get_json())
            out = sent.get_json()
            self.assertTrue(out["emailed"])
            self.assertEqual(out["status"], "sent")
            self.assertIn("Spring cleanup + mulch", send.call_args.kwargs["body"])
            self.assertIn("$275.00", send.call_args.kwargs["body"])

    def test_update_with_send_emails_and_marks_sent(self) -> None:
        inv = self._create_draft()
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.put(
                f"/api/invoices/{inv['id']}",
                json={"title": "Patio reset", "amount": 400, "send": True},
            )
            self.assertEqual(res.status_code, 200, res.get_json())
            body = res.get_json()
            self.assertTrue(body["emailed"])
            self.assertEqual(body["status"], "sent")
            self.assertEqual(body["title"], "Patio reset")
            self.assertEqual(body.get("mail_provider"), "smtp")
            send.assert_called_once()
            self.assertIn("Patio reset", send.call_args.kwargs["body"])
            self.assertIn("$400.00", send.call_args.kwargs["body"])

    def test_update_rejects_empty_title_and_zero_amount(self) -> None:
        inv = self._create_draft()
        bad_title = self.client.put(f"/api/invoices/{inv['id']}", json={"title": "   "})
        self.assertEqual(bad_title.status_code, 400)
        bad_amt = self.client.put(f"/api/invoices/{inv['id']}", json={"amount": 0})
        self.assertEqual(bad_amt.status_code, 400)
        still = self.client.get(f"/api/invoices/{inv['id']}").get_json()
        self.assertEqual(still["title"], "Spring cleanup")
        self.assertEqual(still["amount"], 250)
        self.assertEqual(still["status"], "draft")

    def test_update_sent_invoice_without_emailing(self) -> None:
        inv = self._create_draft()
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}):
            sent = self.client.post(f"/api/invoices/{inv['id']}/send", json={})
            self.assertEqual(sent.status_code, 200, sent.get_json())
        res = self.client.put(
            f"/api/invoices/{inv['id']}",
            json={"title": "Spring cleanup (corrected)", "amount": 275, "notes": "Scope change"},
        )
        self.assertEqual(res.status_code, 200, res.get_json())
        body = res.get_json()
        self.assertEqual(body["title"], "Spring cleanup (corrected)")
        self.assertEqual(body["amount"], 275)
        self.assertEqual(body["notes"], "Scope change")
        self.assertEqual(body["status"], "sent")
        self.assertFalse(body.get("emailed"))

    def test_update_paid_invoice_title(self) -> None:
        inv = self._create_draft()
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}):
            sent = self.client.post(f"/api/invoices/{inv['id']}/send", json={})
            self.assertEqual(sent.status_code, 200, sent.get_json())
        pay = self.client.post(
            f"/api/invoices/{inv['id']}/payments",
            json={"amount": 250, "method": "ACH"},
        )
        self.assertEqual(pay.status_code, 200, pay.get_json())
        self.assertEqual(pay.get_json()["status"], "paid")
        res = self.client.put(
            f"/api/invoices/{inv['id']}",
            json={"title": "Spring cleanup (paid, corrected)"},
        )
        self.assertEqual(res.status_code, 200, res.get_json())
        body = res.get_json()
        self.assertEqual(body["title"], "Spring cleanup (paid, corrected)")
        self.assertEqual(body["status"], "paid")
        self.assertFalse(body.get("emailed"))

    def test_invoice_template_is_present(self) -> None:
        res = self.client.get("/api/templates")
        self.assertEqual(res.status_code, 200)
        names = {t["name"] for t in res.get_json()}
        self.assertIn("Invoice", names)

    def _assert_invoice_pdf_attached(self, send_mock, *, invoice_number: str) -> None:
        kwargs = send_mock.call_args.kwargs
        self.assertIn("PDF copy of this invoice is attached", kwargs["body"])
        atts = kwargs.get("attachments") or []
        self.assertEqual(len(atts), 1, "reminder/invoice emails must attach one PDF")
        self.assertTrue(str(atts[0]["filename"]).endswith(".pdf"))
        self.assertIn(invoice_number.replace("/", "_"), str(atts[0]["filename"]))
        self.assertEqual(atts[0].get("mime"), "application/pdf")
        self.assertTrue(atts[0]["content"].startswith(b"%PDF"))
        self.assertIn(invoice_number.encode("ascii"), atts[0]["content"])

    def _send_invoice(self, *, due_date: str | None = None) -> dict:
        payload: dict = {
            "client_id": self.client_id,
            "title": "Spring cleanup",
            "amount": 250,
            "status": "sent",
        }
        if due_date:
            payload["due_date"] = due_date
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}):
            res = self.client.post("/api/invoices", json=payload)
        self.assertEqual(res.status_code, 201, res.get_json())
        return res.get_json()

    def test_send_reminder_attaches_invoice_pdf(self) -> None:
        inv = self._send_invoice(due_date=date.today().isoformat())
        queue = self.client.get("/api/reminders?status=queue").get_json()
        self.assertTrue(queue)
        rid = queue[0]["id"]
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.post(f"/api/reminders/{rid}/send", json={})
            self.assertEqual(res.status_code, 200, res.get_json())
            self.assertEqual(res.get_json()["status"], "sent")
            send.assert_called_once()
            self.assertEqual(send.call_args.kwargs["to"], "maya@client.example")
            self._assert_invoice_pdf_attached(send, invoice_number=inv["number"])
        self.assertIn("PDF copy of this invoice is attached", res.get_json()["body"])

    def test_send_due_attaches_invoice_pdf(self) -> None:
        inv = self._send_invoice(due_date=date.today().isoformat())
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.post("/api/reminders/send-due", json={})
            self.assertEqual(res.status_code, 200, res.get_json())
            self.assertGreaterEqual(res.get_json()["sent"], 1)
            self.assertGreaterEqual(send.call_count, 1)
            for call in send.call_args_list:
                kwargs = call.kwargs
                self.assertEqual(kwargs["to"], "maya@client.example")
                self.assertIn("PDF copy of this invoice is attached", kwargs["body"])
                atts = kwargs.get("attachments") or []
                self.assertEqual(len(atts), 1)
                self.assertTrue(str(atts[0]["filename"]).endswith(".pdf"))
                self.assertTrue(atts[0]["content"].startswith(b"%PDF"))
                self.assertIn(inv["number"].encode("ascii"), atts[0]["content"])

    def test_final_notice_attaches_invoice_pdf(self) -> None:
        inv = self._send_invoice()
        with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
            res = self.client.post(f"/api/invoices/{inv['id']}/final-notice", json={})
            self.assertEqual(res.status_code, 200, res.get_json())
            send.assert_called_once()
            self.assertEqual(send.call_args.kwargs["to"], "maya@client.example")
            self._assert_invoice_pdf_attached(send, invoice_number=inv["number"])


if __name__ == "__main__":
    unittest.main()
