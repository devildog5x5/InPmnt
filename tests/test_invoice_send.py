"""Sending the invoice itself emails the client (not just reminders)."""
from __future__ import annotations

import os
import tempfile
import unittest
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
            send.assert_called_once()
            kwargs = send.call_args.kwargs
            self.assertEqual(kwargs["to"], "maya@client.example")
            self.assertIn(inv["number"], kwargs["subject"])
            self.assertIn("Spring cleanup", kwargs["body"])
            self.assertIn("$250.00", kwargs["body"])

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

    def test_invoice_template_is_present(self) -> None:
        res = self.client.get("/api/templates")
        self.assertEqual(res.status_code, 200)
        names = {t["name"] for t in res.get_json()}
        self.assertIn("Invoice", names)


if __name__ == "__main__":
    unittest.main()
