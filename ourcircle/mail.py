"""Outbound mail for Family Shield Pro password reset and circle invites. Not InPmnt."""
from __future__ import annotations

import os
import re
import smtplib
import ssl
from datetime import datetime, timezone
from email.message import EmailMessage
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent


def _env(key: str, default: str = "") -> str:
    v = (os.environ.get(key) or default).strip()
    if len(v) >= 2 and v[0] == v[-1] and v[0] in "\"'":
        return v[1:-1]
    return v


def mail_configured() -> bool:
    key = _env("RESEND_API_KEY")
    if key and "..." not in key:
        return True
    return bool(
        _env("SMTP_HOST")
        and _env("SMTP_USER")
        and _env("SMTP_PASSWORD")
        and (_env("MAIL_FROM") or _env("SMTP_USER"))
    )


def not_setup_message() -> str:
    return (
        "Reset email is not set up on this site yet. In .env set SMTP_HOST=smtp.hostinger.com, "
        "SMTP_PORT=465, SMTP_SSL=1, SMTP_USER and MAIL_FROM to the Hostinger mailbox, and SMTP_PASSWORD "
        "to that mailbox password (no quotes). Recovery codes on this page still work if you turned on 2FA."
    )


def send_failed_message(detail: str = "") -> str:
    base = (
        "We could not send the reset email. Check SMTP in .env: Hostinger smtp.hostinger.com, port 465, SSL, "
        "and the mailbox password. Recovery codes on this page still work."
    )
    detail = _safe_detail(detail)
    return f"{base} Last error: {detail}" if detail else base


def last_mail_status() -> str:
    path = _status_path()
    if not path.is_file():
        return ""
    try:
        return _safe_detail(path.read_text(encoding="utf-8", errors="replace"))
    except OSError:
        return ""


def public_info() -> dict[str, Any]:
    port = _env("SMTP_PORT") or "587"
    return {
        "configured": mail_configured(),
        "host": _env("SMTP_HOST"),
        "port": port,
        "ssl": _env("SMTP_SSL").lower() in ("1", "true", "yes") or port == "465",
        "user": _env("SMTP_USER"),
        "from": _env("MAIL_FROM") or _env("SMTP_USER"),
        "last": last_mail_status(),
    }


def test_email_body() -> str:
    version = "0.0.0"
    vp = ROOT / "VERSION"
    if vp.is_file():
        version = vp.read_text(encoding="utf-8").strip() or "0.0.0"
    return (
        "If you received this, Family Shield Pro SMTP is working.\n\n"
        f"Product: Family Shield Pro OurCircle v{version}\n"
        "Not InPmnt.\n\n"
        "Invites, password-reset links, and “Please call me before I pay” emails all use this mailbox.\n\n"
        "Open the site:\nhttps://familyshieldpro.com\n"
    )


def send_email(
    *,
    to: str,
    subject: str,
    body: str,
    from_name: str | None = None,
    html: str | None = None,
) -> dict[str, Any]:
    try:
        result = _deliver(to=to, subject=subject, body=body, from_name=from_name, html=html)
        _remember_status("ok")
        return result
    except Exception as exc:
        _remember_status(str(exc))
        raise


def html_from_text(text: str) -> str:
    import html as html_lib

    escaped = html_lib.escape(text or "", quote=True)
    linked = re.sub(r"(https?://[^\s<]+)", r'<a href="\1">\1</a>', escaped)
    linked = re.sub(
        r'(?<!mailto:)([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})',
        r'<a href="mailto:\1">\1</a>',
        linked,
    )
    return (
        '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#1d1e20">'
        f'<div style="white-space:pre-wrap">{linked}</div></body></html>'
    )


def _deliver(
    *,
    to: str,
    subject: str,
    body: str,
    from_name: str | None = None,
    html: str | None = None,
) -> dict[str, Any]:
    to = (to or "").strip()
    if not to or "@" not in to:
        raise RuntimeError("Need an email address")

    mail_from = _env("MAIL_FROM") or _env("SMTP_USER")
    if not mail_from:
        raise RuntimeError("MAIL_FROM is not set")

    display = (from_name or _env("MAIL_FROM_NAME") or "Family Shield Pro").strip()
    from_header = f"{display} <{mail_from}>"
    html_body = html if (html or "").strip() else html_from_text(body)

    resend_key = _env("RESEND_API_KEY")
    if resend_key and "..." not in resend_key:
        return _send_resend(resend_key, from_header, to, subject, body, html_body)

    host = _env("SMTP_HOST")
    if not host:
        raise RuntimeError(
            "Email is not configured. Set SMTP_HOST, SMTP_USER, SMTP_PASSWORD, and MAIL_FROM in .env (or RESEND_API_KEY)."
        )
    return _send_smtp(host, from_header, mail_from, to, subject, body, html_body)


def _send_resend(
    api_key: str, from_header: str, to: str, subject: str, body: str, html: str
) -> dict[str, Any]:
    import json
    import urllib.error
    import urllib.request

    payload = json.dumps(
        {
            "from": from_header,
            "to": [to],
            "subject": subject or "(no subject)",
            "html": html or "",
        }
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
    host: str,
    from_header: str,
    mail_from: str,
    to: str,
    subject: str,
    body: str,
    html: str,
) -> dict[str, Any]:
    port = int(_env("SMTP_PORT") or "587")
    user = _env("SMTP_USER")
    password = _env("SMTP_PASSWORD")
    use_ssl = _env("SMTP_SSL").lower() in ("1", "true", "yes")

    if user and not password:
        raise RuntimeError("SMTP_PASSWORD is not set. Use the Hostinger mailbox password in .env (no quotes).")

    msg = EmailMessage()
    msg["Subject"] = subject or "(no subject)"
    msg["From"] = from_header
    msg["Reply-To"] = mail_from
    msg["To"] = to
    # HTML only — a text/plain alternative is what inboxes were showing as unlinked copy-paste.
    msg.set_content(
        html or html_from_text(body or ""),
        subtype="html",
        charset="utf-8",
        cte="quoted-printable",
    )

    context = ssl.create_default_context()
    if use_ssl or port == 465:
        with smtplib.SMTP_SSL(host, port, context=context, timeout=30) as smtp:
            if user:
                smtp.login(user, password)
            smtp.send_message(msg, from_addr=mail_from, to_addrs=[to])
    else:
        with smtplib.SMTP(host, port, timeout=30) as smtp:
            smtp.ehlo()
            if _env("SMTP_STARTTLS", "1").lower() not in ("0", "false", "no"):
                smtp.starttls(context=context)
                smtp.ehlo()
            if user:
                smtp.login(user, password)
            smtp.send_message(msg, from_addr=mail_from, to_addrs=[to])
    return {"provider": "smtp", "id": None}


def _status_path() -> Path:
    return ROOT / "data" / "mail_last_error.txt"


def _remember_status(message: str) -> None:
    path = _status_path()
    try:
        path.parent.mkdir(parents=True, exist_ok=True)
        stamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
        path.write_text(f"{stamp} {_safe_detail(message)}\n", encoding="utf-8")
    except OSError:
        pass


def _safe_detail(message: str) -> str:
    text = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f]", "", message or "")
    text = re.sub(r"SMTP_PASSWORD\s*=\s*\S+", "SMTP_PASSWORD=***", text, flags=re.I)
    text = text.strip()
    if len(text) > 500:
        return text[:500] + "…"
    return text
