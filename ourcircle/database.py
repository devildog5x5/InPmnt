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
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'owner',
                created_at TEXT NOT NULL,
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
        n = conn.execute("SELECT COUNT(*) AS c FROM users").fetchone()["c"]
        if n == 0:
            _seed(conn)


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


def create_household(conn: sqlite3.Connection, *, name: str, owner_name: str, email: str, password: str) -> int:
    ts = now()
    cur = conn.execute(
        "INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)",
        (name or f"{owner_name}'s circle", "yearly", 0, ts),
    )
    hid = int(cur.lastrowid)
    conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at)
        VALUES (?,?,?,?,?,?)
        """,
        (hid, owner_name, email.lower().strip(), generate_password_hash(password), "owner", ts),
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
    users = [dict(r) for r in conn.execute("SELECT id, name, email, role FROM users WHERE household_id=? ORDER BY id", (hid,)).fetchall()]
    invites = [dict(r) for r in conn.execute(
        "SELECT id, email, name, status, token FROM invitations WHERE household_id=? AND status='pending' ORDER BY id",
        (hid,),
    ).fetchall()]
    return users, invites


def trusted_list(conn: sqlite3.Connection, hid: int) -> list[dict[str, Any]]:
    return [dict(r) for r in conn.execute(
        "SELECT * FROM trusted_contacts WHERE household_id=? ORDER BY kind, name",
        (hid,),
    ).fetchall()]


def invite_member(conn: sqlite3.Connection, hid: int, email: str, name: str = "") -> dict[str, Any]:
    users, pending = household_members(conn, hid)
    if len(users) + len(pending) >= 5:
        raise ValueError("The family plan includes up to five people. Remove someone or upgrade with us later.")
    email = email.lower().strip()
    token = secrets.token_urlsafe(16)
    conn.execute(
        """
        INSERT INTO invitations (household_id, email, name, token, status, created_at)
        VALUES (?,?,?,?, 'pending', ?)
        """,
        (hid, email, name.strip(), token, now()),
    )
    return {"email": email, "token": token}


def accept_invite(conn: sqlite3.Connection, token: str, name: str, password: str) -> dict[str, Any]:
    inv = conn.execute("SELECT * FROM invitations WHERE token=? AND status='pending'", (token,)).fetchone()
    if not inv:
        raise ValueError("That invite is not valid anymore.")
    existing = conn.execute("SELECT id FROM users WHERE lower(email)=?", (inv["email"],)).fetchone()
    if existing:
        raise ValueError("That email already has an OurCircle login. Sign in instead.")
    conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at)
        VALUES (?,?,?,?, 'member', ?)
        """,
        (inv["household_id"], name.strip(), inv["email"], generate_password_hash(password), now()),
    )
    conn.execute("UPDATE invitations SET status='accepted' WHERE id=?", (inv["id"],))
    user = conn.execute("SELECT * FROM users WHERE lower(email)=?", (inv["email"],)).fetchone()
    return dict(user)
