"""Outbound email for reminders (Resend API or SMTP)."""
from __future__ import annotations

import os
import smtplib
import ssl
from email.message import EmailMessage
from email.utils import formataddr
from typing import Any, Sequence


_PLACEHOLDER_RESEND = {"re_...", "re_your_api_key", "re_changeme"}


def _clean(value: str | None) -> str:
    return (value or "").strip().strip('"').strip("'")


def _resend_key() -> str:
    key = _clean(os.environ.get("RESEND_API_KEY"))
    if not key:
        return ""
    lower = key.lower()
    if lower in _PLACEHOLDER_RESEND or lower.startswith("re_...") or "changeme" in lower:
        return ""
    return key


def _mail_from() -> str:
    return _clean(os.environ.get("MAIL_FROM") or os.environ.get("SMTP_USER"))


def smtp_configured() -> bool:
    # Hostinger SMTP rejects empty passwords after a long hang; treat as not configured.
    return bool(
        _clean(os.environ.get("SMTP_HOST"))
        and _clean(os.environ.get("SMTP_USER"))
        and _clean(os.environ.get("SMTP_PASSWORD"))
        and _mail_from()
    )


def mail_configured() -> bool:
    return bool(_resend_key() or smtp_configured())


def mail_status() -> dict[str, Any]:
    resend = bool(_resend_key())
    smtp = smtp_configured()
    force = _clean(os.environ.get("MAIL_PROVIDER")).lower()
    provider = "none"
    if force == "smtp" and smtp:
        provider = "smtp"
    elif force == "resend" and resend:
        provider = "resend"
    elif resend and smtp:
        provider = "resend_then_smtp"
    elif resend:
        provider = "resend"
    elif smtp:
        provider = "smtp"
    port = int(_clean(os.environ.get("SMTP_PORT")) or "587") if smtp else 0
    return {
        "configured": mail_configured(),
        "provider": provider,
        "resend": resend,
        "smtp": smtp,
        "mail_from": _mail_from() if mail_configured() else "",
        "smtp_host": _clean(os.environ.get("SMTP_HOST")) if smtp else "",
        "smtp_port": port,
    }


def send_email(
    *,
    to: str,
    subject: str,
    body: str,
    from_name: str | None = None,
    attachments: Sequence[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    """Send a plain-text email, optionally with attachments. Raises RuntimeError on failure."""
    to = (to or "").strip()
    if not to or "@" not in to:
        raise RuntimeError("Client has no email address")

    mail_from = _mail_from()
    if not mail_from:
        raise RuntimeError("MAIL_FROM is not set")

    display = (from_name or os.environ.get("MAIL_FROM_NAME") or "InPmnt").strip()
    from_header = formataddr((display, mail_from)) if display else mail_from
    atts = list(attachments or [])

    force = _clean(os.environ.get("MAIL_PROVIDER")).lower()
    resend_key = _resend_key()
    smtp_ok = smtp_configured()
    try_resend = bool(resend_key) and force != "smtp"
    try_smtp = smtp_ok and (force == "smtp" or not try_resend)

    errors: list[str] = []
    if try_resend:
        try:
            return _send_resend(resend_key, from_header, to, subject, body, atts)
        except Exception as exc:  # noqa: BLE001
            errors.append(str(exc))
            if not smtp_ok or force == "resend":
                if isinstance(exc, RuntimeError):
                    raise
                raise RuntimeError(str(exc)) from exc
            try_smtp = True

    if try_smtp:
        host = _clean(os.environ.get("SMTP_HOST"))
        try:
            return _send_smtp(host, from_header, mail_from, to, subject, body, atts)
        except Exception as exc:  # noqa: BLE001
            detail = str(exc)
            if errors:
                raise RuntimeError(f"{errors[0]}; SMTP fallback: {detail}") from exc
            raise RuntimeError(detail) from exc

    raise RuntimeError(
        "Email is not configured. Set RESEND_API_KEY or SMTP_HOST/SMTP_USER/MAIL_FROM in .env"
    )


def _attachment_bytes(att: dict[str, Any]) -> bytes:
    raw = att.get("content") or b""
    if isinstance(raw, str):
        return raw.encode("latin-1")
    return bytes(raw)


def _send_resend(
    api_key: str,
    from_header: str,
    to: str,
    subject: str,
    body: str,
    attachments: Sequence[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    import base64
    import json
    import urllib.error
    import urllib.request

    payload_obj: dict[str, Any] = {
        "from": from_header,
        "to": [to],
        "subject": subject or "(no subject)",
        "text": body or "",
    }
    if attachments:
        payload_obj["attachments"] = [
            {
                "filename": str(att.get("filename") or "invoice.pdf"),
                "content": base64.b64encode(_attachment_bytes(att)).decode("ascii"),
            }
            for att in attachments
        ]
    payload = json.dumps(payload_obj).encode("utf-8")
    req = urllib.request.Request(
        "https://api.resend.com/emails",
        data=payload,
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        },
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
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Resend error: {exc.reason}") from exc


def _send_smtp(
    host: str,
    from_header: str,
    mail_from: str,
    to: str,
    subject: str,
    body: str,
    attachments: Sequence[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    port = int(_clean(os.environ.get("SMTP_PORT")) or "587")
    user = _clean(os.environ.get("SMTP_USER"))
    password = os.environ.get("SMTP_PASSWORD") or ""
    if password.startswith('"') and password.endswith('"') and len(password) >= 2:
        password = password[1:-1]
    use_ssl = _clean(os.environ.get("SMTP_SSL")).lower() in ("1", "true", "yes", "on")

    msg = EmailMessage()
    msg["Subject"] = subject or "(no subject)"
    msg["From"] = from_header
    msg["To"] = to
    msg["Reply-To"] = mail_from
    msg.set_content(body or "")
    for att in attachments or []:
        mime = str(att.get("mime") or "application/pdf")
        main, _, sub = mime.partition("/")
        msg.add_attachment(
            _attachment_bytes(att),
            maintype=main or "application",
            subtype=sub or "pdf",
            filename=str(att.get("filename") or "invoice.pdf"),
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
            if _clean(os.environ.get("SMTP_STARTTLS") or "1").lower() not in ("0", "false", "no"):
                smtp.starttls(context=context)
                smtp.ehlo()
            if user:
                smtp.login(user, password)
            smtp.send_message(msg, from_addr=mail_from, to_addrs=[to])
    return {"provider": "smtp", "id": None}
