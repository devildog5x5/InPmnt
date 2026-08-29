"""Outbound mail for Family Shield Pro password reset and circle invites. Not InPmnt."""
from __future__ import annotations

import os
import smtplib
import ssl
from email.message import EmailMessage
from typing import Any


def mail_configured() -> bool:
    key = (os.environ.get("RESEND_API_KEY") or "").strip()
    if key and "..." not in key:
        return True
    return bool(
        (os.environ.get("SMTP_HOST") or "").strip()
        and (os.environ.get("SMTP_USER") or "").strip()
        and (os.environ.get("MAIL_FROM") or os.environ.get("SMTP_USER") or "").strip()
    )


def send_email(*, to: str, subject: str, body: str, from_name: str | None = None) -> dict[str, Any]:
    to = (to or "").strip()
    if not to or "@" not in to:
        raise RuntimeError("Need an email address")

    mail_from = (os.environ.get("MAIL_FROM") or os.environ.get("SMTP_USER") or "").strip()
    if not mail_from:
        raise RuntimeError("MAIL_FROM is not set")

    display = (from_name or os.environ.get("MAIL_FROM_NAME") or "Family Shield Pro").strip()
    from_header = f"{display} <{mail_from}>"

    resend_key = (os.environ.get("RESEND_API_KEY") or "").strip()
    if resend_key and "..." not in resend_key:
        return _send_resend(resend_key, from_header, to, subject, body)

    host = (os.environ.get("SMTP_HOST") or "").strip()
    if not host:
        raise RuntimeError(
            "Email is not configured. Set RESEND_API_KEY or SMTP_HOST/SMTP_USER/MAIL_FROM in .env"
        )
    return _send_smtp(host, from_header, mail_from, to, subject, body)


def _send_resend(api_key: str, from_header: str, to: str, subject: str, body: str) -> dict[str, Any]:
    import json
    import urllib.error
    import urllib.request

    payload = json.dumps(
        {"from": from_header, "to": [to], "subject": subject or "(no subject)", "text": body or ""}
    ).encode("utf-8")
    req = urllib.request.Request(
        "https://api.resend.com/emails",
        data=payload,
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
            data = json.loads(raw) if raw else {}
            return {"provider": "resend", "id": data.get("id")}
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"Resend error {exc.code}: {detail}") from exc


def _send_smtp(
    host: str, from_header: str, mail_from: str, to: str, subject: str, body: str
) -> dict[str, Any]:
    port = int(os.environ.get("SMTP_PORT") or "587")
    user = (os.environ.get("SMTP_USER") or "").strip()
    password = os.environ.get("SMTP_PASSWORD") or ""
    use_ssl = (os.environ.get("SMTP_SSL") or "").strip().lower() in ("1", "true", "yes")

    msg = EmailMessage()
    msg["Subject"] = subject or "(no subject)"
    msg["From"] = from_header
    msg["To"] = to
    msg.set_content(body or "")

    if use_ssl or port == 465:
        context = ssl.create_default_context()
        with smtplib.SMTP_SSL(host, port, context=context, timeout=30) as smtp:
            if user:
                smtp.login(user, password)
            smtp.send_message(msg, from_addr=mail_from, to_addrs=[to])
    else:
        with smtplib.SMTP(host, port, timeout=30) as smtp:
            smtp.ehlo()
            if (os.environ.get("SMTP_STARTTLS") or "1").strip().lower() not in ("0", "false", "no"):
                context = ssl.create_default_context()
                smtp.starttls(context=context)
                smtp.ehlo()
            if user:
                smtp.login(user, password)
            smtp.send_message(msg, from_addr=mail_from, to_addrs=[to])
    return {"provider": "smtp", "id": None}
