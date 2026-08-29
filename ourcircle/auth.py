"""TOTP 2FA and password-reset helpers for Family Shield Pro. Not InPmnt."""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import re
import secrets
import struct
import time
from typing import Any


def totp_on(user: dict[str, Any] | None) -> bool:
    if not user:
        return False
    secret = (user.get("totp_secret") or "").strip()
    return int(user.get("totp_enabled") or 0) == 1 and secret != ""


def new_secret() -> str:
    return _b32encode(os.urandom(20))


def otpauth_uri(email: str, secret: str) -> str:
    from urllib.parse import quote, urlencode

    label = quote(f"Family Shield Pro:{email}", safe="")
    q = urlencode(
        {
            "secret": secret,
            "issuer": "Family Shield Pro",
            "algorithm": "SHA1",
            "digits": "6",
            "period": "30",
        }
    )
    return f"otpauth://totp/{label}?{q}"


def totp_at(secret: str, timestamp: int, digits: int = 6, period: int = 30) -> str:
    key = _b32decode(secret)
    counter = timestamp // period
    msg = struct.pack(">Q", counter)
    digest = hmac.new(key, msg, hashlib.sha1).digest()
    offset = digest[-1] & 0x0F
    code = struct.unpack(">I", digest[offset : offset + 4])[0] & 0x7FFFFFFF
    return f"{code % (10 ** digits):0{digits}d}"


def verify_totp(secret: str, code: str, timestamp: int | None = None, window: int = 1) -> bool:
    cleaned = re.sub(r"\s+", "", code or "")
    if not re.fullmatch(r"\d{6}", cleaned):
        return False
    t = int(timestamp if timestamp is not None else time.time())
    for w in range(-window, window + 1):
        if hmac.compare_digest(totp_at(secret, t + w * 30), cleaned):
            return True
    return False


def new_recovery_codes(n: int = 10) -> list[str]:
    codes = []
    for _ in range(n):
        raw = secrets.token_hex(4)
        codes.append(f"{raw[:4]}-{raw[4:]}")
    return codes


def hash_recovery_code(code: str) -> str:
    norm = re.sub(r"[^a-z0-9]", "", (code or "").lower())
    return hashlib.sha256(("ourcircle-recovery|" + norm).encode("utf-8")).hexdigest()


def hash_list(codes: list[str]) -> str:
    return json.dumps([hash_recovery_code(c) for c in codes], separators=(",", ":"))


def consume_recovery(stored_json: str, code: str) -> str | None:
    try:
        hashes = json.loads(stored_json or "[]")
    except json.JSONDecodeError:
        return None
    if not isinstance(hashes, list) or not hashes:
        return None
    want = hash_recovery_code(code)
    found = False
    keep: list[str] = []
    for h in hashes:
        if not found and isinstance(h, str) and hmac.compare_digest(h, want):
            found = True
            continue
        keep.append(h)
    if not found:
        return None
    return json.dumps(keep, separators=(",", ":"))


def new_reset_token() -> tuple[str, str]:
    raw = secrets.token_urlsafe(32)
    return raw, hashlib.sha256(raw.encode("utf-8")).hexdigest()


def group_secret(secret: str) -> str:
    return " ".join(secret[i : i + 4] for i in range(0, len(secret), 4))


def _b32encode(raw: bytes) -> str:
    return base64.b32encode(raw).decode("ascii").rstrip("=")


def _b32decode(secret: str) -> bytes:
    s = re.sub(r"[^A-Z2-7]", "", (secret or "").upper())
    pad = (8 - len(s) % 8) % 8
    return base64.b32decode(s + ("=" * pad))
