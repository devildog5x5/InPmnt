from __future__ import annotations

import os
import tempfile
import unittest
from pathlib import Path
import sys

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

from analyze import CORE_RULE, PAUSE, analyze, lookalike_hits  # noqa: E402
from web import create_app  # noqa: E402
import database as db  # noqa: E402


class AnalyzeTests(unittest.TestCase):
    def test_gift_card_is_pause_never_safe(self) -> None:
        out = analyze(text="Your grandson is in jail. Buy $500 in Apple gift cards and keep this secret.")
        self.assertEqual(out["level"], PAUSE)
        self.assertTrue(out["never_safe"])
        self.assertIn(CORE_RULE, out["core_rule"])
        self.assertTrue(any("gift card" in s.lower() or "secret" in s.lower() for s in out["warning_signs"]))

    def test_lookalike_paypal(self) -> None:
        hits = lookalike_hits("paypa1.com", [])
        self.assertTrue(any("paypal.com" in h for h in hits))
        report = analyze(url="https://paypa1.com/help")
        self.assertEqual(report["level"], "lookalike")

    def test_trusted_phone_match(self) -> None:
        trusted = [{"phone": "800-555-0100", "website": "", "name": "Credit union"}]
        out = analyze(phone="8005550100", trusted=trusted)
        self.assertTrue(any("trusted list" in m.lower() for m in out["matches"]))

    def test_empty_is_unknown(self) -> None:
        out = analyze(text="")
        self.assertEqual(out["level"], "unknown")
        self.assertNotEqual(out["title"].lower().find("safe") >= 0, True)


class AppTests(unittest.TestCase):
    def setUp(self) -> None:
        fd, self.db_path = tempfile.mkstemp(suffix=".db")
        os.close(fd)
        os.environ["OURCIRCLE_DB"] = self.db_path
        db.init_db(self.db_path)
        app = create_app()
        app.config["TESTING"] = True
        self.client = app.test_client()

    def tearDown(self) -> None:
        try:
            os.remove(self.db_path)
        except OSError:
            pass
        os.environ.pop("OURCIRCLE_DB", None)

    def test_landing_and_login_demo(self) -> None:
        land = self.client.get("/")
        self.assertEqual(land.status_code, 200)
        self.assertIn(b"OurCircle", land.data)
        self.assertIn(b"Never send money", land.data)
        self.assertIn(b"too good to be true", land.data.lower())
        self.assertIn(b"Really! Really! Really!", land.data)
        self.assertIn(b"Why we built this", land.data)
        self.assertIn(b"on your toes", land.data)
        self.assertIn(b'href="https://familyshieldpro.com"', land.data)
        self.assertIn(b"$14.99", land.data)
        self.assertIn(b"$119.99", land.data)
        self.assertNotIn(b"$7.99", land.data)
        self.assertNotIn(b"Founding year $49", land.data)
        self.assertIn(b"Family monthly", land.data)
        self.assertIn(b"Family yearly", land.data)
        self.assertIn(b"CustomerService@FamilyShieldPro.com", land.data)
        self.assertIn(b'id="contact"', land.data)
        self.assertIn(b"CUSTOMER SERVICE PHONE", land.data)
        self.assertIn(b"fsp-chat", land.data)
        self.assertNotIn(b"not InPmnt", land.data)
        self.assertNotIn(b"Hostinger PHP", land.data)
        self.assertNotIn(b"robots.txt", land.data)
        login_page = self.client.get("/login")
        self.assertIn(b'href="https://familyshieldpro.com"', login_page.data)
        login = self.client.post(
            "/login",
            data={"email": "family@ourcircle.app", "password": "password123"},
            follow_redirects=True,
        )
        self.assertEqual(login.status_code, 200)
        self.assertIn(b"Check this with OurCircle", login.data)
        self.assertIn(b'href="https://familyshieldpro.com"', login.data)
        billing = self.client.get("/billing")
        self.assertEqual(billing.status_code, 200)
        self.assertIn(b"Family monthly", billing.data)
        self.assertIn(b"$14.99/month", billing.data)
        self.assertIn(b"Family yearly", billing.data)
        self.assertIn(b"$119.99/year", billing.data)
        choose = self.client.post(
            "/billing/choose",
            data={"plan": "monthly"},
            follow_redirects=True,
        )
        self.assertEqual(choose.status_code, 200)
        self.assertIn(b"$14.99/month", choose.data)
        self.assertIn(b"This household is on", choose.data)
        self.assertIn(b"monthly", choose.data)
        self.assertIn(b"Stripe keys are not", billing.data)
        health = self.client.get("/healthz")
        self.assertFalse(health.get_json().get("stripe"))
        hook = self.client.post("/billing/webhook", data=b"{}", content_type="application/json")
        self.assertEqual(hook.status_code, 503)

    def test_check_alert_and_reservation(self) -> None:
        self.client.post("/login", data={"email": "family@ourcircle.app", "password": "password123"})
        res = self.client.post(
            "/check",
            data={"text": "Send bitcoin now or your account will be suspended. Keep this secret."},
            follow_redirects=True,
        )
        self.assertEqual(res.status_code, 200)
        self.assertIn(b"Pause", res.data)
        self.assertNotIn(b"this is safe", res.data.lower())
        self.assertIn(b"Please call me before I pay", res.data)
        alert = self.client.post("/checks/1/alert", follow_redirects=True)
        self.assertEqual(alert.status_code, 200)
        home = self.client.get("/home")
        self.assertIn(b"PLEASE CALL", home.data)
        offer = self.client.post(
            "/offers",
            data={
                "product": "ourcircle",
                "name": "Pat",
                "email": "pat@example.com",
                "offer": "$119.99 family year",
            },
            follow_redirects=True,
        )
        self.assertEqual(offer.status_code, 200)
        self.assertIn(b"Reservation saved", offer.data)

    def test_invite_limit_message_and_trusted(self) -> None:
        self.client.post("/login", data={"email": "family@ourcircle.app", "password": "password123"})
        add = self.client.post(
            "/trusted",
            data={"kind": "bank", "name": "Home bank", "phone": "8005550199", "website": "", "notes": ""},
            follow_redirects=True,
        )
        self.assertIn(b"Home bank", add.data)
        inv = self.client.post("/circle", data={"email": "kid@example.com", "name": "Kid"}, follow_redirects=True)
        self.assertEqual(inv.status_code, 200)
        self.assertIn(b"Invite created", inv.data)

    def test_robots_and_sitemap_use_familyshieldpro(self) -> None:
        robots = self.client.get("/robots.txt")
        self.assertEqual(robots.status_code, 200)
        text = robots.data.decode("utf-8")
        self.assertIn("familyshieldpro.com", text)
        self.assertIn("Sitemap: https://familyshieldpro.com/sitemap.xml", text)
        self.assertIn("Disallow: /home", text)
        self.assertIn("Disallow: /uploads", text)
        self.assertIn("Allow: /signup", text)
        self.assertIn("Allow: /forgot", text)
        self.assertIn("Disallow: /account", text)
        self.assertIn("Disallow: /support", text)
        sitemap = self.client.get("/sitemap.xml")
        self.assertEqual(sitemap.status_code, 200)
        xml = sitemap.data.decode("utf-8")
        self.assertIn("https://familyshieldpro.com/", xml)
        self.assertIn("https://familyshieldpro.com/signup", xml)
        self.assertIn("https://familyshieldpro.com/login", xml)
        self.assertIn("https://familyshieldpro.com/forgot", xml)
        self.assertIn("https://familyshieldpro.com/offers", xml)
        self.assertNotIn("/home", xml)
        health = self.client.get("/healthz")
        self.assertEqual(health.status_code, 200)
        self.assertTrue(health.get_json()["ok"])
        self.assertFalse(health.get_json().get("stripe"))

    def test_stripe_webhook_marks_household_paid(self) -> None:
        import hashlib
        import hmac
        import json
        import time

        from billing import construct_event

        payload_body = '{"id":"evt_test"}'
        secret = "whsec_testsecret_abcdefghijklmnopqrstuvwxyz"
        ts = str(int(time.time()))
        sig = hmac.new(secret.encode(), f"{ts}.{payload_body}".encode(), hashlib.sha256).hexdigest()
        event = construct_event(payload_body, f"t={ts},v1={sig}", secret)
        self.assertEqual(event["id"], "evt_test")

        os.environ["STRIPE_SECRET_KEY"] = "sk_test_abcdefghijklmnopqrstuvwxyz0123"
        os.environ["STRIPE_WEBHOOK_SECRET"] = secret
        os.environ["STRIPE_PRICE_MONTHLY"] = "price_abcdefghijklmnopqrstuvwxyz01"
        os.environ["STRIPE_PRICE_YEARLY"] = "price_abcdefghijklmnopqrstuvwxyz02"
        try:
            payload = json.dumps(
                {
                    "id": "evt_paid",
                    "type": "checkout.session.completed",
                    "data": {
                        "object": {
                            "customer": "cus_testfamily",
                            "subscription": "sub_testfamily",
                            "client_reference_id": "1",
                            "metadata": {"plan": "monthly", "household_id": "1"},
                        }
                    },
                }
            )
            ts2 = str(int(time.time()))
            sig2 = hmac.new(secret.encode(), f"{ts2}.{payload}".encode(), hashlib.sha256).hexdigest()
            res = self.client.post(
                "/billing/webhook",
                data=payload,
                headers={"Stripe-Signature": f"t={ts2},v1={sig2}"},
                content_type="application/json",
            )
            self.assertEqual(res.status_code, 200)
            self.assertTrue(res.get_json()["received"])
            with db.session(self.db_path) as conn:
                hh = conn.execute("SELECT * FROM households WHERE id=1").fetchone()
            self.assertEqual(hh["plan"], "monthly")
            self.assertEqual(hh["stripe_customer_id"], "cus_testfamily")
            self.assertEqual(hh["stripe_status"], "active")
        finally:
            for key in (
                "STRIPE_SECRET_KEY",
                "STRIPE_WEBHOOK_SECRET",
                "STRIPE_PRICE_MONTHLY",
                "STRIPE_PRICE_YEARLY",
            ):
                os.environ.pop(key, None)

    def test_forgot_2fa_and_recovery_codes(self) -> None:
        import time

        from auth import hash_list, new_recovery_codes, new_secret, totp_at, verify_totp

        secret = new_secret()
        self.assertTrue(verify_totp(secret, totp_at(secret, 1_111_111_111), 1_111_111_111))
        codes = new_recovery_codes()
        with db.session(self.db_path) as conn:
            conn.execute(
                "UPDATE users SET totp_secret=?, totp_enabled=1, recovery_codes=? WHERE lower(email)=?",
                (secret, hash_list(codes), "family@ourcircle.app"),
            )
        step = self.client.post(
            "/login",
            data={"email": "family@ourcircle.app", "password": "password123"},
            follow_redirects=False,
        )
        self.assertEqual(step.status_code, 302)
        self.assertIn("/login/2fa", step.headers.get("Location", ""))
        blocked = self.client.get("/home", follow_redirects=False)
        self.assertEqual(blocked.status_code, 302)
        bad = self.client.post("/login/2fa", data={"code": "000000"}, follow_redirects=True)
        self.assertIn(b"did not match", bad.data)
        ok = self.client.post(
            "/login/2fa",
            data={"code": totp_at(secret, int(time.time()))},
            follow_redirects=True,
        )
        self.assertEqual(ok.status_code, 200)
        self.assertIn(b"Check this with OurCircle", ok.data)
        self.client.get("/logout")
        forgot = self.client.get("/forgot")
        self.assertEqual(forgot.status_code, 200)
        self.assertIn(b"Forgot password", forgot.data)
        self.assertIn(b"recovery code", forgot.data.lower())
        self.client.post("/forgot", data={"email": "family@ourcircle.app"}, follow_redirects=True)
        with db.session(self.db_path) as conn:
            n = conn.execute("SELECT COUNT(*) AS c FROM password_resets").fetchone()["c"]
        self.assertEqual(n, 1)
        self.client.get("/logout")
        rec = self.client.post(
            "/forgot/code",
            data={
                "email": "family@ourcircle.app",
                "recovery_code": codes[0],
                "password": "newpass123",
            },
            follow_redirects=True,
        )
        self.assertEqual(rec.status_code, 200)
        again = self.client.post(
            "/login",
            data={"email": "family@ourcircle.app", "password": "newpass123"},
            follow_redirects=False,
        )
        self.assertIn("/login/2fa", again.headers.get("Location", ""))
        via_code = self.client.post(
            "/login/2fa",
            data={"recovery_code": codes[1]},
            follow_redirects=True,
        )
        self.assertIn(b"Check this with OurCircle", via_code.data)
        login_page = self.client.get("/login")
        self.assertIn(b"Forgot password", login_page.data)
        chat = self.client.post(
            "/support/chat",
            json={"message": "how much does a family plan cost?"},
        )
        self.assertEqual(chat.status_code, 200)
        chat_body = chat.get_json()
        self.assertIn("14.99", chat_body["reply"])
        self.assertEqual(chat_body["source"], "faq")
        safe_chat = self.client.post(
            "/support/chat",
            json={"message": "is this paypal email safe to pay?"},
        )
        self.assertEqual(safe_chat.status_code, 200)
        safe_reply = safe_chat.get_json()["reply"].lower()
        self.assertNotIn("this is safe", safe_reply)
        self.assertIn("never", safe_reply)
        self.assertIn("CustomerService@FamilyShieldPro.com", self.client.get("/login").data.decode())
        health = self.client.get("/healthz")
        self.assertFalse(health.get_json().get("openai"))


if __name__ == "__main__":
    unittest.main()
