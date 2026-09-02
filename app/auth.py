"""Password reset tokens and local fallback delivery."""
from __future__ import annotations

import hashlib
import secrets
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any

from .database import log_activity, row_to_dict

RESET_TTL = timedelta(hours=1)
RESET_FILENAME = "password-reset.txt"

FORGOT_NOTICE = (
    "If that email is registered, a reset link is on the way. "
    "When email isn't configured, the link is saved as password-reset.txt "
    "in the same folder as your database."
)


def _now() -> str:
    return datetime.utcnow().isoformat(timespec="seconds") + "Z"


def _hash_token(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def reset_file_path(db_path: str) -> Path:
    return Path(db_path).resolve().parent / RESET_FILENAME


def write_reset_file(db_path: str, url: str) -> Path:
    path = reset_file_path(db_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        "InPmnt password reset\n"
        f"Generated: {_now()}\n\n"
        "Open this link in your browser (expires in 1 hour):\n\n"
        f"{url}\n\n"
        "If you did not request this, delete this file.\n",
        encoding="utf-8",
    )
    return path


def clear_reset_file(db_path: str) -> None:
    path = reset_file_path(db_path)
    if path.is_file():
        path.unlink()


def issue_reset_token(conn, user_id: int) -> str:
    token = secrets.token_urlsafe(32)
    now = _now()
    expires = (datetime.utcnow() + RESET_TTL).isoformat(timespec="seconds") + "Z"
    conn.execute(
        "UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL",
        (now, user_id),
    )
    conn.execute(
        """
        INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
        VALUES (?, ?, ?, ?)
        """,
        (user_id, _hash_token(token), expires, now),
    )
    return token


def peek_reset_token(conn, token: str) -> dict[str, Any] | None:
    token = (token or "").strip()
    if not token:
        return None
    row = conn.execute(
        """
        SELECT r.id, r.user_id, r.expires_at, r.used_at, u.email, u.workspace_id
        FROM password_resets r
        JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = ?
        """,
        (_hash_token(token),),
    ).fetchone()
    if not row or row["used_at"]:
        return None
    if (row["expires_at"] or "") < _now():
        return None
    return row_to_dict(row)


def consume_reset_token(conn, token: str, password_hash: str) -> dict[str, Any] | None:
    row = peek_reset_token(conn, token)
    if not row:
        return None
    now = _now()
    conn.execute(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        (password_hash, row["user_id"]),
    )
    conn.execute(
        "UPDATE password_resets SET used_at = ? WHERE id = ?",
        (now, row["id"]),
    )
    log_activity(
        conn,
        "system",
        "Password was reset",
        "user",
        int(row["user_id"]),
        workspace_id=row.get("workspace_id"),
    )
    return row
