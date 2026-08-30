"""Twilio SMS for Family Shield Pro circle invites, alerts, and inbound checks. Not InPmnt."""
from __future__ import annotations

import base64
import hashlib
import hmac
import os
import re
import urllib.error
import urllib.parse
import urllib.request
from typing import Any
from xml.sax.saxutils import escape as xml_escape

from analyze import CORE_RULE, GUIDANCE, digits_only


def sms_configured() -> bool:
    sid = (os.environ.get("TWILIO_ACCOUNT_SID") or "").strip()
    token = (os.environ.get("TWILIO_AUTH_TOKEN") or "").strip()
    frm = (os.environ.get("TWILIO_FROM") or "").strip()
    if "..." in sid or "..." in token or "..." in frm:
        return False
    return sid.startswith("AC") and len(token) >= 16 and frm.startswith("+")


def normalize_phone(raw: str) -> str:
    s = (raw or "").strip()
    if not s:
        return ""
    digits = re.sub(r"\D+", "", s)
    if len(digits) == 11 and digits.startswith("1"):
        digits = digits[1:]
    if len(digits) == 10:
        return "+1" + digits
    if s.startswith("+") and 10 <= len(digits) <= 15:
        return "+" + digits
    return ""


def invite_sms_body(join: str, inviter: str = "") -> str:
    who = (inviter or "Your family").strip() or "Your family"
    return (
        f"Family Shield Pro: {who} invited you to their circle.\n"
        f"{join}\n"
        f"Tap the link to join. {CORE_RULE} Reply STOP to opt out."
    )


def alert_sms_body(name: str, check_url: str) -> str:
    return (
        f"PLEASE CALL {name} before they pay. Open {check_url} "
        f"{CORE_RULE} Reply STOP to opt out."
    )


def check_sms_body(title: str, check_url: str) -> str:
    return f"OurCircle: {title} Open {check_url} {GUIDANCE} {CORE_RULE}"


def classify_inbound(body: str) -> str:
    text = (body or "").strip().upper()
    if text in {"STOP", "STOPALL", "UNSUBSCRIBE", "CANCEL", "END", "QUIT"}:
        return "stop"
    if text in {"START", "YES", "UNSTOP"}:
        return "start"
    if text in {"HELP", "INFO"}:
        return "help"
    return "check"


def inbound_auto_reply(action: str) -> str:
    if action == "stop":
        return "Family Shield Pro: you are opted out of SMS. Reply START to turn texts back on."
    if action == "start":
        return "Family Shield Pro: SMS is on. Forward a sketchy text here to pause with your circle. Reply STOP to opt out."
    if action == "help":
        return (
            "Family Shield Pro (OurCircle): forward a suspicious text here to pause with your circle. "
            f"{CORE_RULE} Reply STOP to opt out. Email CustomerService@FamilyShieldPro.com"
        )
    return ""


def twiml(message: str) -> str:
    body = xml_escape((message or "").strip() or " ")
    return (
        '<?xml version="1.0" encoding="UTF-8"?>'
        f"<Response><Message>{body}</Message></Response>"
    )


def valid_signature(url: str, params: dict[str, str], header: str) -> bool:
    token = (os.environ.get("TWILIO_AUTH_TOKEN") or "").strip()
    if not token or "..." in token:
        return False
    data = url
    for key in sorted(params):
        data += key + params[key]
    digest = hmac.new(token.encode("utf-8"), data.encode("utf-8"), hashlib.sha1).digest()
    expected = base64.b64encode(digest).decode("ascii")
    return hmac.compare_digest(expected, (header or "").strip())


def send_sms(*, to: str, body: str) -> dict[str, Any]:
    dest = normalize_phone(to)
    if not dest:
        raise RuntimeError("Need a mobile number")
    if not sms_configured():
        raise RuntimeError(
            "SMS is not configured. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_FROM in .env"
        )
    sid = (os.environ.get("TWILIO_ACCOUNT_SID") or "").strip()
    token = (os.environ.get("TWILIO_AUTH_TOKEN") or "").strip()
    frm = (os.environ.get("TWILIO_FROM") or "").strip()
    payload = urllib.parse.urlencode({"To": dest, "From": frm, "Body": body or ""}).encode("utf-8")
    url = f"https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json"
    auth = base64.b64encode(f"{sid}:{token}".encode("ascii")).decode("ascii")
    req = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Authorization": f"Basic {auth}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
            return {"provider": "twilio", "raw": raw}
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"Twilio error {exc.code}: {detail}") from exc
