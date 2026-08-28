"""Mail provider selection and SMTP fallback."""
from __future__ import annotations

import io
import os
import unittest
import urllib.error
from unittest.mock import MagicMock, patch

from app.mail import mail_configured, mail_status, send_email


class MailConfigTests(unittest.TestCase):
    def setUp(self) -> None:
        self._saved = dict(os.environ)
        for key in list(os.environ):
            if key.startswith(("RESEND", "SMTP_", "MAIL_")):
                del os.environ[key]

    def tearDown(self) -> None:
        os.environ.clear()
        os.environ.update(self._saved)

    def _smtp(self) -> None:
        os.environ["SMTP_HOST"] = "smtp.hostinger.com"
        os.environ["SMTP_PORT"] = "465"
        os.environ["SMTP_USER"] = "billing@example.com"
        os.environ["SMTP_PASSWORD"] = "secret"
        os.environ["SMTP_SSL"] = "1"
        os.environ["MAIL_FROM"] = "billing@example.com"
        os.environ["MAIL_FROM_NAME"] = "InPmnt"

    def test_unconfigured(self) -> None:
        self.assertFalse(mail_configured())
        self.assertEqual(mail_status()["provider"], "none")

    def test_placeholder_resend_ignored(self) -> None:
        os.environ["RESEND_API_KEY"] = "re_..."
        self.assertFalse(mail_configured())
        os.environ["RESEND_API_KEY"] = "re_your_api_key"
        self.assertFalse(mail_configured())

    def test_smtp_only_status(self) -> None:
        self._smtp()
        self.assertTrue(mail_configured())
        st = mail_status()
        self.assertEqual(st["provider"], "smtp")
        self.assertTrue(st["smtp"])
        self.assertFalse(st["resend"])
        self.assertEqual(st["smtp_host"], "smtp.hostinger.com")
        self.assertEqual(st["smtp_port"], 465)

    def test_both_providers_report_fallback(self) -> None:
        self._smtp()
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        st = mail_status()
        self.assertEqual(st["provider"], "resend_then_smtp")
        self.assertTrue(st["resend"])
        self.assertTrue(st["smtp"])

    def test_mail_provider_smtp_forces_smtp(self) -> None:
        self._smtp()
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        os.environ["MAIL_PROVIDER"] = "smtp"
        self.assertEqual(mail_status()["provider"], "smtp")

    @patch("app.mail._send_smtp")
    @patch("app.mail._send_resend")
    def test_invalid_resend_falls_back_to_smtp(self, resend: MagicMock, smtp: MagicMock) -> None:
        self._smtp()
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        resend.side_effect = RuntimeError("Resend error 401: invalid api key")
        smtp.return_value = {"provider": "smtp", "id": None}
        out = send_email(to="client@example.com", subject="Due", body="Please pay")
        self.assertEqual(out["provider"], "smtp")
        resend.assert_called_once()
        smtp.assert_called_once()

    @patch("app.mail._send_smtp")
    @patch("app.mail._send_resend")
    def test_mail_provider_smtp_skips_resend(self, resend: MagicMock, smtp: MagicMock) -> None:
        self._smtp()
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        os.environ["MAIL_PROVIDER"] = "smtp"
        smtp.return_value = {"provider": "smtp", "id": None}
        out = send_email(to="client@example.com", subject="Due", body="Please pay")
        self.assertEqual(out["provider"], "smtp")
        resend.assert_not_called()
        smtp.assert_called_once()

    @patch("app.mail._send_resend")
    def test_resend_only_does_not_invent_smtp(self, resend: MagicMock) -> None:
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        os.environ["MAIL_FROM"] = "billing@example.com"
        resend.return_value = {"provider": "resend", "id": "abc"}
        out = send_email(to="client@example.com", subject="Due", body="Please pay")
        self.assertEqual(out["provider"], "resend")

    def test_missing_recipient(self) -> None:
        self._smtp()
        with self.assertRaisesRegex(RuntimeError, "no email"):
            send_email(to="", subject="x", body="y")

    def test_combined_error_when_both_fail(self) -> None:
        self._smtp()
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        with patch("app.mail._send_resend", side_effect=RuntimeError("Resend error 401: nope")):
            with patch("app.mail._send_smtp", side_effect=RuntimeError("SMTP login failed")):
                with self.assertRaisesRegex(RuntimeError, "Resend error 401.*SMTP fallback"):
                    send_email(to="client@example.com", subject="Due", body="Please pay")

    def test_resend_http_error_message(self) -> None:
        os.environ["RESEND_API_KEY"] = "re_not_a_placeholder_key_value"
        os.environ["MAIL_FROM"] = "billing@example.com"
        fp = io.BytesIO(b'{"message":"invalid"}')
        err = urllib.error.HTTPError(
            "https://api.resend.com/emails", 401, "Unauthorized", hdrs=None, fp=fp
        )
        with patch("urllib.request.urlopen", side_effect=err):
            with self.assertRaisesRegex(RuntimeError, "Resend error 401"):
                send_email(to="client@example.com", subject="Due", body="Please pay")


class MailApiSmokeTests(unittest.TestCase):
    def test_status_requires_login(self) -> None:
        from app import create_app

        app = create_app()
        app.config["TESTING"] = True
        with app.test_client() as client:
            res = client.get("/api/mail/status")
            self.assertEqual(res.status_code, 401)

    def test_status_and_test_send_when_logged_in(self) -> None:
        import tempfile
        from unittest.mock import patch

        from app import create_app
        from app.database import init_db

        saved = {
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
        fd, db_path = tempfile.mkstemp(suffix=".db")
        os.close(fd)
        try:
            os.environ["DATABASE_PATH"] = db_path
            os.environ["SMTP_HOST"] = "smtp.hostinger.com"
            os.environ["SMTP_PORT"] = "465"
            os.environ["SMTP_USER"] = "billing@example.com"
            os.environ["SMTP_PASSWORD"] = "secret"
            os.environ["SMTP_SSL"] = "1"
            os.environ["MAIL_FROM"] = "billing@example.com"
            os.environ["MAIL_PROVIDER"] = "smtp"
            os.environ.pop("RESEND_API_KEY", None)
            init_db(db_path)
            app = create_app()
            app.config["TESTING"] = True
            app.config["DATABASE"] = db_path
            with app.test_client() as client:
                signup = client.post(
                    "/signup",
                    data={
                        "name": "Pat",
                        "business_name": "Pat Co",
                        "email": "pat@example.com",
                        "password": "password123",
                    },
                )
                self.assertIn(signup.status_code, (302, 303))
                status = client.get("/api/mail/status")
                self.assertEqual(status.status_code, 200)
                body = status.get_json()
                self.assertTrue(body["configured"])
                self.assertEqual(body["provider"], "smtp")
                self.assertEqual(body["smtp_host"], "smtp.hostinger.com")
                with patch("app.routes.send_email", return_value={"provider": "smtp", "id": None}) as send:
                    test = client.post("/api/mail/test", json={})
                    self.assertEqual(test.status_code, 200, test.get_json())
                    payload = test.get_json()
                    self.assertTrue(payload["ok"])
                    self.assertEqual(payload["to"], "pat@example.com")
                    self.assertEqual(payload["provider"], "smtp")
                    send.assert_called_once()
        finally:
            try:
                os.remove(db_path)
            except OSError:
                pass
            for key, value in saved.items():
                if value is None:
                    os.environ.pop(key, None)
                else:
                    os.environ[key] = value


if __name__ == "__main__":
    unittest.main()
