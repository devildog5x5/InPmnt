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
        self.assertIn(b'href="https://familyshieldpro.com"', land.data)
        self.assertIn(b'alt="Family Shield Pro"', land.data)
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
                "offer": "$49 founding",
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
        sitemap = self.client.get("/sitemap.xml")
        self.assertEqual(sitemap.status_code, 200)
        xml = sitemap.data.decode("utf-8")
        self.assertIn("https://familyshieldpro.com/", xml)
        self.assertIn("https://familyshieldpro.com/signup", xml)
        self.assertIn("https://familyshieldpro.com/login", xml)
        self.assertIn("https://familyshieldpro.com/offers", xml)
        self.assertNotIn("/home", xml)
        health = self.client.get("/healthz")
        self.assertEqual(health.status_code, 200)
        self.assertTrue(health.get_json()["ok"])


if __name__ == "__main__":
    unittest.main()
