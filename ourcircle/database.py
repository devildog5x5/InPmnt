"""SQLite household, circle, checks, trusted contacts."""
from __future__ import annotations

import json
import os
import sqlite3
import secrets
from contextlib import contextmanager
from datetime import datetime
from pathlib import Path
from typing import Any, Iterator

from werkzeug.security import check_password_hash, generate_password_hash

ROOT = Path(__file__).resolve().parent
DATA = ROOT / "data"
DATA.mkdir(parents=True, exist_ok=True)
(DATA / "uploads").mkdir(parents=True, exist_ok=True)


def db_path() -> str:
    return os.environ.get("OURCIRCLE_DB") or str(DATA / "ourcircle.db")


def connect(path: str | None = None) -> sqlite3.Connection:
    conn = sqlite3.connect(path or db_path())
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


@contextmanager
def session(path: str | None = None) -> Iterator[sqlite3.Connection]:
    conn = connect(path)
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def now() -> str:
    return datetime.utcnow().isoformat(timespec="seconds") + "Z"


def row_dict(row: sqlite3.Row | None) -> dict[str, Any] | None:
    return dict(row) if row else None


def init_db(path: str | None = None) -> None:
    p = path or db_path()
    Path(p).parent.mkdir(parents=True, exist_ok=True)
    with session(p) as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS households (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                plan TEXT NOT NULL DEFAULT 'yearly',
                founding INTEGER NOT NULL DEFAULT 0,
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT,
                stripe_status TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'owner',
                totp_secret TEXT,
                totp_enabled INTEGER NOT NULL DEFAULT 0,
                recovery_codes TEXT,
                created_at TEXT NOT NULL,
                last_access_at TEXT,
                phone TEXT,
                sms_opt_out INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS invitations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                email TEXT NOT NULL,
                name TEXT,
                token TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL,
                email_sent_at TEXT,
                accepted_at TEXT,
                phone TEXT,
                sms_sent_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS trusted_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                kind TEXT NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                website TEXT,
                notes TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                kind TEXT NOT NULL,
                raw_text TEXT,
                phone TEXT,
                url TEXT,
                screenshot TEXT,
                risk TEXT NOT NULL,
                report_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            CREATE TABLE IF NOT EXISTS reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                check_id INTEGER NOT NULL,
                household_id INTEGER NOT NULL,
                requester_id INTEGER NOT NULL,
                comment TEXT,
                status TEXT NOT NULL DEFAULT 'asked',
                created_at TEXT NOT NULL,
                FOREIGN KEY (check_id) REFERENCES checks(id)
            );
            CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                check_id INTEGER,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            CREATE TABLE IF NOT EXISTS reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                offer TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            );
            """
        )
        _migrate_household_stripe(conn)
        _migrate_user_auth(conn)
        _migrate_circle_status(conn)
        _migrate_sms(conn)
        n = conn.execute("SELECT COUNT(*) AS c FROM users").fetchone()["c"]
        if n == 0:
            _seed(conn)


def _migrate_household_stripe(conn: sqlite3.Connection) -> None:
    cols = {row[1] for row in conn.execute("PRAGMA table_info(households)").fetchall()}
    for name, ddl in (
        ("stripe_customer_id", "TEXT"),
        ("stripe_subscription_id", "TEXT"),
        ("stripe_status", "TEXT"),
    ):
        if name not in cols:
            conn.execute(f"ALTER TABLE households ADD COLUMN {name} {ddl}")


def _migrate_user_auth(conn: sqlite3.Connection) -> None:
    cols = {row[1] for row in conn.execute("PRAGMA table_info(users)").fetchall()}
    for name, ddl in (
        ("totp_secret", "TEXT"),
        ("totp_enabled", "INTEGER NOT NULL DEFAULT 0"),
        ("recovery_codes", "TEXT"),
    ):
        if name not in cols:
            conn.execute(f"ALTER TABLE users ADD COLUMN {name} {ddl}")
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
        """
    )


def _migrate_circle_status(conn: sqlite3.Connection) -> None:
    users = {row[1] for row in conn.execute("PRAGMA table_info(users)").fetchall()}
    if "last_access_at" not in users:
        conn.execute("ALTER TABLE users ADD COLUMN last_access_at TEXT")
    inv = {row[1] for row in conn.execute("PRAGMA table_info(invitations)").fetchall()}
    if "email_sent_at" not in inv:
        conn.execute("ALTER TABLE invitations ADD COLUMN email_sent_at TEXT")
    if "accepted_at" not in inv:
        conn.execute("ALTER TABLE invitations ADD COLUMN accepted_at TEXT")


def _migrate_sms(conn: sqlite3.Connection) -> None:
    users = {row[1] for row in conn.execute("PRAGMA table_info(users)").fetchall()}
    if "phone" not in users:
        conn.execute("ALTER TABLE users ADD COLUMN phone TEXT")
    if "sms_opt_out" not in users:
        conn.execute("ALTER TABLE users ADD COLUMN sms_opt_out INTEGER NOT NULL DEFAULT 0")
    inv = {row[1] for row in conn.execute("PRAGMA table_info(invitations)").fetchall()}
    if "phone" not in inv:
        conn.execute("ALTER TABLE invitations ADD COLUMN phone TEXT")
    if "sms_sent_at" not in inv:
        conn.execute("ALTER TABLE invitations ADD COLUMN sms_sent_at TEXT")


def _seed(conn: sqlite3.Connection) -> None:
    ts = now()
    cur = conn.execute(
        "INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)",
        ("Foster family circle", "yearly", 0, ts),
    )
    hid = cur.lastrowid
    conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at)
        VALUES (?,?,?,?,?,?)
        """,
        (
            hid,
            "Pat Foster",
            "family@ourcircle.app",
            generate_password_hash("password123"),
            "owner",
            ts,
        ),
    )
    samples = [
        ("bank", "First National (example)", "8005550100", "https://example-bank.invalid", "Use the number on the back of the debit card."),
        ("doctor", "Family clinic", "8005550142", "", "Ask for the nurse line, not a callback from a text."),
        ("utility", "City power company", "8005550199", "", "Printed on the monthly bill."),
        ("family", "Jordan (adult child)", "5550108888", "", "Call before any unexpected payment request."),
    ]
    for kind, name, phone, site, notes in samples:
        conn.execute(
            """
            INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at)
            VALUES (?,?,?,?,?,?,?)
            """,
            (hid, kind, name, phone, site, notes, ts),
        )


def create_household(
    conn: sqlite3.Connection, *, name: str, owner_name: str, email: str, password: str, phone: str = ""
) -> int:
    ts = now()
    cur = conn.execute(
        "INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)",
        (name or f"{owner_name}'s circle", "yearly", 0, ts),
    )
    hid = int(cur.lastrowid)
    conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone)
        VALUES (?,?,?,?,?,?,?)
        """,
        (hid, owner_name, email.lower().strip(), generate_password_hash(password), "owner", ts, phone or None),
    )
    return hid


def authenticate(conn: sqlite3.Connection, email: str, password: str) -> dict[str, Any] | None:
    row = conn.execute(
        "SELECT * FROM users WHERE lower(email)=?",
        (email.lower().strip(),),
    ).fetchone()
    if not row or not check_password_hash(row["password_hash"], password):
        return None
    return dict(row)


def household_members(conn: sqlite3.Connection, hid: int) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    users = [
        dict(r)
        for r in conn.execute(
            "SELECT id, name, email, role, last_access_at, phone, sms_opt_out FROM users WHERE household_id=? ORDER BY id",
            (hid,),
        ).fetchall()
    ]
    invites = [
        dict(r)
        for r in conn.execute(
            "SELECT id, email, name, status, token, email_sent_at, sms_sent_at, accepted_at, phone FROM invitations WHERE household_id=? AND status='pending' ORDER BY id",
            (hid,),
        ).fetchall()
    ]
    return decorate_circle_status(users, invites)


def decorate_circle_status(
    members: list[dict[str, Any]], pending: list[dict[str, Any]]
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    for m in members:
        if (m.get("role") or "") == "owner" or m.get("last_access_at"):
            m["circle_status"] = "User Accesses the Circle"
            m["circle_status_key"] = "access"
        else:
            m["circle_status"] = "Invite Accepted"
            m["circle_status_key"] = "accepted"
    for p in pending:
        if p.get("email_sent_at") or p.get("sms_sent_at"):
            p["circle_status"] = "Invite sent"
            p["circle_status_key"] = "sent"
        else:
            p["circle_status"] = "Invited"
            p["circle_status_key"] = "invited"
    return members, pending


def mark_invite_sent(conn: sqlite3.Connection, invite_id: int) -> None:
    conn.execute("UPDATE invitations SET email_sent_at=? WHERE id=? AND email_sent_at IS NULL", (now(), invite_id))


def mark_invite_sms_sent(conn: sqlite3.Connection, invite_id: int) -> None:
    conn.execute("UPDATE invitations SET sms_sent_at=? WHERE id=? AND sms_sent_at IS NULL", (now(), invite_id))


def phone_taken(
    conn: sqlite3.Connection,
    phone: str,
    *,
    except_user_id: int | None = None,
    except_invite_id: int | None = None,
) -> bool:
    if not phone:
        return False
    row = conn.execute("SELECT id FROM users WHERE phone=?", (phone,)).fetchone()
    if row and (except_user_id is None or int(row["id"]) != int(except_user_id)):
        return True
    pending = conn.execute(
        "SELECT id FROM invitations WHERE phone=? AND status='pending'",
        (phone,),
    ).fetchone()
    if pending and (except_invite_id is None or int(pending["id"]) != int(except_invite_id)):
        return True
    return False


def find_user_by_phone(conn: sqlite3.Connection, phone: str) -> dict[str, Any] | None:
    if not phone:
        return None
    row = conn.execute("SELECT * FROM users WHERE phone=?", (phone,)).fetchone()
    return dict(row) if row else None


def find_pending_invite_by_phone(conn: sqlite3.Connection, phone: str) -> dict[str, Any] | None:
    if not phone:
        return None
    row = conn.execute(
        "SELECT * FROM invitations WHERE phone=? AND status='pending' ORDER BY id DESC",
        (phone,),
    ).fetchone()
    return dict(row) if row else None


def pending_invite(conn: sqlite3.Connection, hid: int, invite_id: int) -> dict[str, Any] | None:
    row = conn.execute(
        "SELECT * FROM invitations WHERE id=? AND household_id=? AND status='pending'",
        (invite_id, hid),
    ).fetchone()
    return dict(row) if row else None


def set_user_phone(conn: sqlite3.Connection, user_id: int, phone: str, sms_opt_out: bool = False) -> None:
    if phone and phone_taken(conn, phone, except_user_id=user_id):
        raise ValueError("That mobile number is already on another Family Shield Pro login.")
    conn.execute(
        "UPDATE users SET phone=?, sms_opt_out=? WHERE id=?",
        (phone or None, 1 if sms_opt_out else 0, user_id),
    )


def set_sms_opt_out(conn: sqlite3.Connection, user_id: int, opted_out: bool) -> None:
    conn.execute("UPDATE users SET sms_opt_out=? WHERE id=?", (1 if opted_out else 0, user_id))


def touch_last_access(conn: sqlite3.Connection, user_id: int) -> None:
    conn.execute("UPDATE users SET last_access_at=? WHERE id=?", (now(), user_id))


def trusted_list(conn: sqlite3.Connection, hid: int) -> list[dict[str, Any]]:
    return [dict(r) for r in conn.execute(
        "SELECT * FROM trusted_contacts WHERE household_id=? ORDER BY kind, name",
        (hid,),
    ).fetchall()]


def invite_member(conn: sqlite3.Connection, hid: int, email: str, name: str = "", phone: str = "") -> dict[str, Any]:
    users, pending = household_members(conn, hid)
    if len(users) + len(pending) >= 5:
        raise ValueError("The family plan includes up to five people. Remove someone or upgrade with us later.")
    email = email.lower().strip()
    if not email or "@" not in email:
        raise ValueError("Need an email address to invite.")
    phone = (phone or "").strip()
    if phone and phone_taken(conn, phone):
        raise ValueError("That mobile number is already on another Family Shield Pro login or invite.")
    token = secrets.token_urlsafe(16)
    cur = conn.execute(
        """
        INSERT INTO invitations (household_id, email, name, token, status, created_at, phone)
        VALUES (?,?,?,?, 'pending', ?, ?)
        """,
        (hid, email, name.strip(), token, now(), phone or None),
    )
    return {"email": email, "token": token, "id": int(cur.lastrowid or 0), "phone": phone}


def accept_invite(conn: sqlite3.Connection, token: str, name: str, password: str, phone: str = "") -> dict[str, Any]:
    inv = conn.execute("SELECT * FROM invitations WHERE token=? AND status='pending'", (token,)).fetchone()
    if not inv:
        raise ValueError("That invite is not valid anymore.")
    existing = conn.execute("SELECT id FROM users WHERE lower(email)=?", (inv["email"],)).fetchone()
    if existing:
        raise ValueError("That email already has an OurCircle login. Sign in instead.")
    stored = (phone or "").strip() or (inv["phone"] or "")
    if stored and phone_taken(conn, stored, except_invite_id=int(inv["id"])):
        raise ValueError("That mobile number is already on another Family Shield Pro login.")
    conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone)
        VALUES (?,?,?,?, 'member', ?, ?)
        """,
        (inv["household_id"], name.strip(), inv["email"], generate_password_hash(password), now(), stored or None),
    )
    conn.execute(
        "UPDATE invitations SET status='accepted', accepted_at=? WHERE id=?",
        (now(), inv["id"]),
    )
    user = conn.execute("SELECT * FROM users WHERE lower(email)=?", (inv["email"],)).fetchone()
    return dict(user)
