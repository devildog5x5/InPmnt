"""Operator console gate for Family Shield Pro. Not a family page. Not InPmnt."""
from __future__ import annotations

import hmac
import os


def admin_password() -> str:
    return (os.environ.get("ADMIN_PASSWORD") or "").strip()


def admin_configured() -> bool:
    password = admin_password()
    if not password or "..." in password:
        return False
    return len(password) >= 12


def admin_emails() -> set[str]:
    raw = (os.environ.get("ADMIN_EMAIL") or "").strip()
    if not raw or "..." in raw:
        return set()
    out: set[str] = set()
    for part in raw.split(","):
        email = part.strip().lower()
        if email and "@" in email:
            out.add(email)
    return out


def admin_password_ok(given: str) -> bool:
    want = admin_password()
    if not admin_configured():
        return False
    got = given or ""
    if len(got) != len(want):
        hmac.compare_digest(want.encode("utf-8"), want.encode("utf-8"))
        return False
    return hmac.compare_digest(got.encode("utf-8"), want.encode("utf-8"))


def email_is_admin(email: str) -> bool:
    if not admin_configured():
        return False
    return (email or "").strip().lower() in admin_emails()
