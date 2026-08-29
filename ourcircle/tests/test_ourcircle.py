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
        for key in ("TWILIO_ACCOUNT_SID", "TWILIO_AUTH_TOKEN", "TWILIO_FROM"):
            os.environ.pop(key, None)

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
        self.assertIn(b"fsp-chat-tab", land.data)
        self.assertIn(b">Help</button>", land.data)
        self.assertIn(b">Hide</button>", land.data)
        self.assertIn(b"fsp-chat.js", land.data)
        self.assertIn(b"css/app.css?v=", land.data)
        self.assertNotIn(b">Offers</a>", land.data)
        self.assertNotIn(b"not InPmnt", land.data)
        self.assertNotIn(b"Hostinger PHP", land.data)
        self.assertNotIn(b"robots.txt", land.data)
        self.assertIn(b"guidance, not a guarantee", land.data)
        self.assertIn(b"Strategy and Tactics", land.data)
        self.assertIn(b"Not a guarantee", land.data)
        self.assertIn(b"Your circle and us", land.data)
        self.assertIn(b"Text with your circle", land.data)
        signup_page = self.client.get("/signup")
        self.assertEqual(signup_page.status_code, 200)
        self.assertIn(b"The Smith circle", signup_page.data)
        self.assertNotIn(b"The Patel circle", signup_page.data)
        self.assertIn(b"guidance, not a guarantee", signup_page.data)
        login_page = self.client.get("/login")
        self.assertIn(b'href="https://familyshieldpro.com"', login_page.data)
        self.assertIn(b"guidance, not a guarantee", login_page.data)
        login = self.client.post(
            "/login",
            data={"email": "family@ourcircle.app", "password": "password123"},
            follow_redirects=True,
        )
        self.assertEqual(login.status_code, 200)
        self.assertIn(b"Check this with OurCircle", login.data)
        self.assertIn(b"guidance, not a guarantee", login.data)
        self.assertIn(b'href="https://familyshieldpro.com"', login.data)
        billing = self.client.get("/billing")
        self.assertEqual(billing.status_code, 200)
        self.assertIn(b"Family monthly", billing.data)
        self.assertIn(b"$14.99/month", billing.data)
        self.assertIn(b"Family yearly", billing.data)
        self.assertIn(b"$119.99/year", billing.data)
        self.assertIn(b"guidance, not a guarantee", billing.data)
        self.assertIn(b"Paying for a plan does not make a request safe", billing.data)
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
        self.assertFalse(health.get_json().get("sms"))
        hook = self.client.post("/billing/webhook", data=b"{}", content_type="application/json")
        self.assertEqual(hook.status_code, 503)

    def test_check_alert(self) -> None:
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
        self.assertIn(b"guidance, not a guarantee", res.data)
        alert = self.client.post("/checks/1/alert", follow_redirects=True)
        self.assertEqual(alert.status_code, 200)
        home = self.client.get("/home")
        self.assertIn(b"PLEASE CALL", home.data)
        empty = self.client.post("/check", data={"text": ""}, follow_redirects=True)
        self.assertEqual(empty.status_code, 200)
        self.assertIn(b"Paste the message", empty.data)
        look = self.client.post(
            "/check",
            data={"url": "https://paypa1.com/help"},
            follow_redirects=True,
        )
        self.assertEqual(look.status_code, 200)
        self.assertIn(b"lookalike", look.data.lower())
        self.assertNotIn(b"this is safe", look.data.lower())
        gone = self.client.get("/offers")
        self.assertEqual(gone.status_code, 404)
        unauth = self.client.get("/logout", follow_redirects=False)
        self.assertEqual(unauth.status_code, 302)
        blocked = self.client.get("/home", follow_redirects=False)
        self.assertEqual(blocked.status_code, 302)
        self.assertIn("/login", blocked.headers.get("Location", ""))
        forgot = self.client.get("/forgot")
        self.assertEqual(forgot.status_code, 200)
        self.assertIn(b"Forgot password", forgot.data)
        signup = self.client.post(
            "/signup",
            data={"name": "", "email": "not-an-email", "password": "short"},
            follow_redirects=True,
        )
        self.assertEqual(signup.status_code, 200)
        self.assertIn(b"required", signup.data.lower())

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
        self.assertIn(b"/join/", inv.data)
        self.assertIn(b"Send invite", inv.data)
        self.assertIn(b"Mobile (optional)", inv.data)
        self.assertIn(b"Resend invite", inv.data)
        self.assertIn(b"kid@example.com", inv.data)
        kid_at = inv.data.rfind(b"kid@example.com")
        kid_chunk = inv.data[kid_at : kid_at + 500]
        self.assertIn(b"Invited", kid_chunk)
        self.assertIn(b"User Accesses the Circle", inv.data)
        self.assertIn(b"Invite sent", inv.data)  # legend
        self.assertIn(b"Invite Accepted", inv.data)  # legend
        token = None
        with db.session() as conn:
            row = conn.execute(
                "SELECT token FROM invitations WHERE email=?",
                ("kid@example.com",),
            ).fetchone()
            token = row["token"] if row else None
        self.assertTrue(token)
        other = create_app().test_client()
        join_page = other.get(f"/join/{token}")
        self.assertEqual(join_page.status_code, 200)
        self.assertIn(b"Join this family circle", join_page.data)
        self.assertIn(b"kid@example.com", join_page.data)

        sent: list[dict] = []

        def capture_send(**kwargs):  # type: ignore[no-untyped-def]
            sent.append(kwargs)
            return {"provider": "test"}

        os.environ["SMTP_HOST"] = "smtp.example.test"
        os.environ["SMTP_USER"] = "mail@example.test"
        os.environ["MAIL_FROM"] = "mail@example.test"
        os.environ["OURCIRCLE_SITE_URL"] = "https://sandbox.familyshieldpro.com"
        try:
            from unittest.mock import patch

            with patch("web.send_email", side_effect=capture_send):
                mailed = self.client.post(
                    "/circle",
                    data={"email": "cousin@example.com", "name": "Cousin"},
                    follow_redirects=True,
                )
            self.assertEqual(mailed.status_code, 200)
            self.assertIn(b"Invite emailed", mailed.data)
            self.assertEqual(len(sent), 1)
            self.assertEqual(sent[0]["to"], "cousin@example.com")
            self.assertIn("https://sandbox.familyshieldpro.com/join/", sent[0]["body"])
            self.assertIn("guidance, not a guarantee", sent[0]["body"])
            cousin_at = mailed.data.rfind(b"cousin@example.com")
            cousin_chunk = mailed.data[cousin_at : cousin_at + 500]
            self.assertIn(b"Invite sent", cousin_chunk)
        finally:
            for k in ("SMTP_HOST", "SMTP_USER", "MAIL_FROM", "OURCIRCLE_SITE_URL"):
                os.environ.pop(k, None)

        joined = other.post(
            f"/join/{token}",
            data={"name": "Kid", "password": "password123"},
            follow_redirects=True,
        )
        self.assertEqual(joined.status_code, 200)
        as_owner = self.client.get("/circle")
        kid_at = as_owner.data.rfind(b"kid@example.com")
        kid_after = as_owner.data[kid_at : kid_at + 400]
        self.assertIn(b"Invite Accepted", kid_after)
        other.get("/home")
        as_owner = self.client.get("/circle")
        kid_at = as_owner.data.rfind(b"kid@example.com")
        kid_active = as_owner.data[kid_at : kid_at + 400]
        self.assertIn(b"User Accesses the Circle", kid_active)

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
        self.assertNotIn("Allow: /offers", text)
        self.assertIn("Disallow: /account", text)
        self.assertIn("Disallow: /support", text)
        self.assertIn("Disallow: /sms", text)
        sitemap = self.client.get("/sitemap.xml")
        self.assertEqual(sitemap.status_code, 200)
        xml = sitemap.data.decode("utf-8")
        self.assertIn("https://familyshieldpro.com/", xml)
        self.assertIn("https://familyshieldpro.com/signup", xml)
        self.assertIn("https://familyshieldpro.com/login", xml)
        self.assertIn("https://familyshieldpro.com/forgot", xml)
        self.assertNotIn("/offers", xml)
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


    def test_2fa_setup_keeps_secret_and_shows_qr(self) -> None:
        import re

        from auth import otpauth_uri

        uri = otpauth_uri("family@ourcircle.app", "JBSWY3DPEHPK3PXP")
        self.assertTrue(uri.startswith("otpauth://totp/"))
        self.assertIn("secret=JBSWY3DPEHPK3PXP", uri)
        self.client.post("/login", data={"email": "family@ourcircle.app", "password": "password123"})
        first = self.client.get("/account/2fa/setup")
        self.assertEqual(first.status_code, 200)
        self.assertIn(b"otpauth://totp/", first.data)
        self.assertIn(b'id="otp-qr"', first.data)
        self.assertIn(b"qrcode.min.js", first.data)
        self.assertIn(b"Open authenticator app", first.data)
        grouped = re.search(rb'id="otp-secret"[^>]*>([^<]+)', first.data)
        self.assertIsNotNone(grouped)
        second = self.client.get("/account/2fa/setup")
        self.assertIn(grouped.group(1), second.data)
        rotated = self.client.post("/account/2fa/setup", data={"new_key": "1"}, follow_redirects=True)
        self.assertEqual(rotated.status_code, 200)
        again = re.search(rb'id="otp-secret"[^>]*>([^<]+)', rotated.data)
        self.assertIsNotNone(again)
        self.assertNotEqual(grouped.group(1), again.group(1))
        chat = self.client.post(
            "/support/chat",
            json={"message": "how much does a family plan cost?"},
        )
        self.assertEqual(chat.status_code, 200)
        chat_body = chat.get_json()
        self.assertIn("14.99", chat_body["reply"])
        self.assertIn("guidance, not a guarantee", chat_body["reply"])
        self.assertEqual(chat_body["source"], "faq")
        safe_chat = self.client.post(
            "/support/chat",
            json={"message": "is this paypal email safe to pay?"},
        )
        self.assertEqual(safe_chat.status_code, 200)
        safe_reply = safe_chat.get_json()["reply"].lower()
        self.assertNotIn("this is safe", safe_reply)
        self.assertIn("never", safe_reply)
        money_chat = self.client.post(
            "/support/chat",
            json={"message": "if someone asks me to send them money, should i do it?"},
        )
        self.assertEqual(money_chat.status_code, 200)
        money_reply = money_chat.get_json()["reply"]
        self.assertIn("NO!!!", money_reply)
        self.assertIn("without a doubt", money_reply.lower())
        invite_chat = self.client.post(
            "/support/chat",
            json={"message": "why does the invite email link not work?"},
        )
        self.assertEqual(invite_chat.status_code, 200)
        invite_reply = invite_chat.get_json()["reply"].lower()
        self.assertIn("join", invite_reply)
        self.assertIn("circle", invite_reply)
        sms_chat = self.client.post(
            "/support/chat",
            json={"message": "can we get and send sms messages on our phones?"},
        )
        self.assertEqual(sms_chat.status_code, 200)
        sms_reply = sms_chat.get_json()["reply"].lower()
        self.assertIn("sms", sms_reply)
        self.assertIn("stop", sms_reply)
        self.assertNotIn("this is safe", sms_reply)
        self.assertIn("family member", money_reply.lower())
        self.assertIn("CustomerService@FamilyShieldPro.com", self.client.get("/login").data.decode())
        health = self.client.get("/healthz")
        self.assertFalse(health.get_json().get("openai"))

    def test_sms_invite_account_and_inbound(self) -> None:
        import base64
        import hashlib
        import hmac
        from unittest.mock import patch

        import sms as sms_mod

        self.assertEqual(sms_mod.normalize_phone("555-010-1234"), "+15550101234")
        self.assertEqual(sms_mod.classify_inbound("STOP"), "stop")
        self.assertEqual(sms_mod.classify_inbound("buy gift cards now"), "check")
        self.assertIn("<Message>", sms_mod.twiml("Hello & goodbye"))
        self.assertNotIn("this is safe", sms_mod.check_sms_body("Pause.", "https://example.test/checks/1").lower())
        self.assertFalse(sms_mod.sms_configured())

        off = self.client.post("/sms/inbound", data={"From": "+15555550100", "Body": "HELP"})
        self.assertEqual(off.status_code, 503)

        self.client.post("/login", data={"email": "family@ourcircle.app", "password": "password123"})
        saved = self.client.post(
            "/account/phone",
            data={"phone": "555-010-8888"},
            follow_redirects=True,
        )
        self.assertEqual(saved.status_code, 200)
        self.assertIn(b"Mobile number saved", saved.data)
        self.assertIn(b"+15550108888", saved.data)
        acct = self.client.get("/account")
        self.assertIn(b"Mobile and SMS", acct.data)

        sent: list[dict] = []

        def capture_sms(*, to: str, body: str):  # type: ignore[no-untyped-def]
            sent.append({"to": to, "body": body})
            return {"provider": "test"}

        os.environ["TWILIO_ACCOUNT_SID"] = "ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
        os.environ["TWILIO_AUTH_TOKEN"] = "testtoken1234567890"
        os.environ["TWILIO_FROM"] = "+15555550100"
        os.environ["OURCIRCLE_SITE_URL"] = "https://familyshieldpro.com"
        try:
            health = self.client.get("/healthz")
            self.assertTrue(health.get_json().get("sms"))
            with patch("web.send_sms", side_effect=capture_sms):
                invited = self.client.post(
                    "/circle",
                    data={"email": "aunt@example.com", "name": "Aunt", "phone": "555-010-7777"},
                    follow_redirects=True,
                )
            self.assertEqual(invited.status_code, 200)
            self.assertIn(b"texted to +15550107777", invited.data)
            self.assertEqual(sent[0]["to"], "+15550107777")
            self.assertIn("familyshieldpro.com/join/", sent[0]["body"])
            self.assertIn("STOP", sent[0]["body"])
            aunt_at = invited.data.rfind(b"aunt@example.com")
            aunt_chunk = invited.data[aunt_at : aunt_at + 700]
            self.assertIn(b"Invite sent", aunt_chunk)
            self.assertIn(b"+15550107777", aunt_chunk)
            self.assertIn(b"Resend invite", aunt_chunk)

            params = {"AccountSid": os.environ["TWILIO_ACCOUNT_SID"], "From": "+15550108888", "Body": "HELP"}
            data = "https://familyshieldpro.com/sms/inbound"
            for key in sorted(params):
                data += key + params[key]
            digest = hmac.new(os.environ["TWILIO_AUTH_TOKEN"].encode(), data.encode(), hashlib.sha1).digest()
            sig = base64.b64encode(digest).decode("ascii")
            help_res = self.client.post(
                "/sms/inbound",
                data=params,
                headers={"X-Twilio-Signature": sig},
            )
            self.assertEqual(help_res.status_code, 200)
            self.assertIn(b"<Message>", help_res.data)
            self.assertIn(b"STOP", help_res.data)
            self.assertNotIn(b"this is safe", help_res.data.lower())

            check_params = {
                "AccountSid": os.environ["TWILIO_ACCOUNT_SID"],
                "From": "+15550108888",
                "Body": "Grandson in jail. Buy Apple gift cards and keep this secret.",
            }
            data2 = "https://familyshieldpro.com/sms/inbound"
            for key in sorted(check_params):
                data2 += key + check_params[key]
            digest2 = hmac.new(os.environ["TWILIO_AUTH_TOKEN"].encode(), data2.encode(), hashlib.sha1).digest()
            sig2 = base64.b64encode(digest2).decode("ascii")
            check_res = self.client.post(
                "/sms/inbound",
                data=check_params,
                headers={"X-Twilio-Signature": sig2},
            )
            self.assertEqual(check_res.status_code, 200)
            self.assertIn(b"Pause", check_res.data)
            self.assertNotIn(b"this is safe", check_res.data.lower())
            with db.session(self.db_path) as conn:
                n = conn.execute("SELECT COUNT(*) AS c FROM checks WHERE kind='sms'").fetchone()["c"]
            self.assertEqual(n, 1)
        finally:
            for key in ("TWILIO_ACCOUNT_SID", "TWILIO_AUTH_TOKEN", "TWILIO_FROM", "OURCIRCLE_SITE_URL"):
                os.environ.pop(key, None)


if __name__ == "__main__":
    unittest.main()
