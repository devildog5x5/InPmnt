"""Create and edit clients after they exist."""
from __future__ import annotations

import os
import tempfile
import unittest

from app import create_app
from app.database import init_db


class ClientEditTests(unittest.TestCase):
    def setUp(self) -> None:
        self._saved = {k: os.environ.get(k) for k in ("DATABASE_PATH",)}
        fd, self.db_path = tempfile.mkstemp(suffix=".db")
        os.close(fd)
        os.environ["DATABASE_PATH"] = self.db_path
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

    def test_create_then_edit_client(self) -> None:
        created = self.client.post(
            "/api/clients",
            json={
                "name": "Maya Chen",
                "email": "old@client.example",
                "company": "Chen Landscape",
                "phone": "555-0100",
            },
        )
        self.assertEqual(created.status_code, 201, created.get_json())
        cid = created.get_json()["id"]
        res = self.client.put(
            f"/api/clients/{cid}",
            json={
                "name": "Maya Chen",
                "email": "maya@client.example",
                "company": "Chen Landscape LLC",
                "phone": "555-0199",
                "notes": "Prefers email",
            },
        )
        self.assertEqual(res.status_code, 200, res.get_json())
        body = res.get_json()
        self.assertEqual(body["email"], "maya@client.example")
        self.assertEqual(body["company"], "Chen Landscape LLC")
        self.assertEqual(body["phone"], "555-0199")
        self.assertEqual(body["notes"], "Prefers email")
        listed = self.client.get("/api/clients").get_json()
        row = next(r for r in listed if r["id"] == cid)
        self.assertEqual(row["email"], "maya@client.example")

    def test_update_rejects_empty_name(self) -> None:
        created = self.client.post("/api/clients", json={"name": "Keep Me"}).get_json()
        res = self.client.put(f"/api/clients/{created['id']}", json={"name": "   "})
        self.assertEqual(res.status_code, 400)
        listed = self.client.get("/api/clients").get_json()
        row = next(r for r in listed if r["id"] == created["id"])
        self.assertEqual(row["name"], "Keep Me")

    def test_update_missing_client_404(self) -> None:
        res = self.client.put("/api/clients/99999", json={"name": "Nope"})
        self.assertEqual(res.status_code, 404)


if __name__ == "__main__":
    unittest.main()
