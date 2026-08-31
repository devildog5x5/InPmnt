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
            "SELECT * FROM users WHERE household_id=? ORDER BY id",
            (hid,),
        ).fetchall()
    ]
    invites = [
        dict(r)
        for r in conn.execute(
            "SELECT * FROM invitations WHERE household_id=? AND status='pending' ORDER BY id",
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
    if phone and find_user_by_phone(conn, phone):
        phone = ""
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
        stored = ""
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


def pending_invite_by_id(conn: sqlite3.Connection, invite_id: int) -> dict[str, Any] | None:
    row = conn.execute(
        "SELECT * FROM invitations WHERE id=? AND status='pending'",
        (invite_id,),
    ).fetchone()
    return dict(row) if row else None


def cancel_pending_invite(conn: sqlite3.Connection, invite_id: int) -> bool:
    cur = conn.execute("DELETE FROM invitations WHERE id=? AND status='pending'", (invite_id,))
    return int(cur.rowcount or 0) > 0


def admin_counts(conn: sqlite3.Connection) -> dict[str, int]:
    def count(sql: str) -> int:
        return int(conn.execute(sql).fetchone()[0])

    return {
        "households": count("SELECT COUNT(*) FROM households"),
        "users": count("SELECT COUNT(*) FROM users"),
        "pending_invites": count("SELECT COUNT(*) FROM invitations WHERE status='pending'"),
        "trusted": count("SELECT COUNT(*) FROM trusted_contacts"),
        "checks": count("SELECT COUNT(*) FROM checks"),
    }


def _admin_like(q: str) -> str:
    return "%" + q.lower().replace("%", "").replace("_", "") + "%"


def admin_list_users(conn: sqlite3.Connection, q: str = "") -> list[dict[str, Any]]:
    q = (q or "").strip()
    sql = """
        SELECT u.*, h.name AS household_name, h.plan AS household_plan
        FROM users u
        JOIN households h ON h.id = u.household_id
    """
    params: list[Any] = []
    if q:
        like = _admin_like(q)
        sql += (
            " WHERE lower(u.name) LIKE ? OR lower(u.email) LIKE ?"
            " OR lower(h.name) LIKE ? OR IFNULL(u.phone,'') LIKE ?"
        )
        params = [like, like, like, like]
    sql += " ORDER BY u.id LIMIT 500"
    rows = [dict(r) for r in conn.execute(sql, params).fetchall()]
    decorated, _ = decorate_circle_status(rows, [])
    return decorated


def admin_list_invites(conn: sqlite3.Connection, q: str = "") -> list[dict[str, Any]]:
    q = (q or "").strip()
    sql = """
        SELECT i.*, h.name AS household_name
        FROM invitations i
        JOIN households h ON h.id = i.household_id
        WHERE i.status='pending'
    """
    params: list[Any] = []
    if q:
        like = _admin_like(q)
        sql += " AND (lower(i.email) LIKE ? OR lower(IFNULL(i.name,'')) LIKE ? OR lower(h.name) LIKE ? OR IFNULL(i.phone,'') LIKE ?)"
        params = [like, like, like, like]
    sql += " ORDER BY i.id DESC LIMIT 500"
    rows = [dict(r) for r in conn.execute(sql, params).fetchall()]
    _, pending = decorate_circle_status([], rows)
    return pending


def admin_get_user(conn: sqlite3.Connection, user_id: int) -> dict[str, Any] | None:
    row = conn.execute(
        """
        SELECT u.*, h.name AS household_name, h.plan AS household_plan,
               h.stripe_status AS household_stripe_status,
               h.created_at AS household_created_at
        FROM users u
        JOIN households h ON h.id = u.household_id
        WHERE u.id=?
        """,
        (user_id,),
    ).fetchone()
    if not row:
        return None
    user = dict(row)
    decorated, _ = decorate_circle_status([user], [])
    return decorated[0]


def admin_get_household(conn: sqlite3.Connection, hid: int) -> dict[str, Any] | None:
    row = conn.execute("SELECT * FROM households WHERE id=?", (hid,)).fetchone()
    return dict(row) if row else None


def admin_owner_ids(conn: sqlite3.Connection, hid: int) -> list[int]:
    return [
        int(r["id"])
        for r in conn.execute(
            "SELECT id FROM users WHERE household_id=? AND role='owner' ORDER BY id",
            (hid,),
        ).fetchall()
    ]


def admin_is_last_owner(conn: sqlite3.Connection, user: dict[str, Any]) -> bool:
    if (user.get("role") or "") != "owner":
        return False
    return len(admin_owner_ids(conn, int(user["household_id"]))) <= 1


def admin_list_households(conn: sqlite3.Connection, q: str = "") -> list[dict[str, Any]]:
    q = (q or "").strip()
    sql = """
        SELECT h.*,
            (SELECT COUNT(*) FROM users u WHERE u.household_id=h.id) AS user_count,
            (SELECT COUNT(*) FROM invitations i WHERE i.household_id=h.id AND i.status='pending') AS invite_count,
            (SELECT COUNT(*) FROM trusted_contacts t WHERE t.household_id=h.id) AS trusted_count,
            (SELECT COUNT(*) FROM checks c WHERE c.household_id=h.id) AS check_count
        FROM households h
    """
    params: list[Any] = []
    if q:
        like = _admin_like(q)
        sql += " WHERE lower(h.name) LIKE ? OR CAST(h.id AS TEXT)=?"
        params = [like, q]
    sql += " ORDER BY h.id LIMIT 500"
    return [dict(r) for r in conn.execute(sql, params).fetchall()]


def admin_household_detail(conn: sqlite3.Connection, hid: int) -> dict[str, Any] | None:
    rows = admin_list_households(conn)
    for row in rows:
        if int(row["id"]) == int(hid):
            return row
    return admin_get_household(conn, hid)


def admin_list_checks(conn: sqlite3.Connection, hid: int | None = None, limit: int = 50) -> list[dict[str, Any]]:
    sql = """
        SELECT c.id, c.household_id, c.user_id, c.kind, c.risk, c.created_at,
               u.name AS user_name, u.email AS user_email, h.name AS household_name
        FROM checks c
        JOIN users u ON u.id = c.user_id
        JOIN households h ON h.id = c.household_id
    """
    params: list[Any] = []
    if hid is not None:
        sql += " WHERE c.household_id=?"
        params.append(hid)
    sql += " ORDER BY c.id DESC LIMIT ?"
    params.append(limit)
    return [dict(r) for r in conn.execute(sql, params).fetchall()]


def _delete_check_row(conn: sqlite3.Connection, check_id: int) -> None:
    conn.execute("DELETE FROM reviews WHERE check_id=?", (check_id,))
    conn.execute("DELETE FROM alerts WHERE check_id=?", (check_id,))
    conn.execute("DELETE FROM checks WHERE id=?", (check_id,))


def admin_delete_check(conn: sqlite3.Connection, check_id: int) -> bool:
    row = conn.execute("SELECT id FROM checks WHERE id=?", (check_id,)).fetchone()
    if not row:
        return False
    _delete_check_row(conn, check_id)
    return True


def admin_add_trusted(
    conn: sqlite3.Connection,
    hid: int,
    *,
    kind: str,
    name: str,
    phone: str = "",
    website: str = "",
    notes: str = "",
) -> int:
    if not admin_get_household(conn, hid):
        raise ValueError("That circle is not in Family Shield Pro.")
    kind = (kind or "other").strip()
    if kind not in ("bank", "doctor", "insurer", "utility", "family", "other"):
        kind = "other"
    name = name.strip()
    if not name:
        raise ValueError("Give this contact a name you will recognize.")
    cur = conn.execute(
        """
        INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at)
        VALUES (?,?,?,?,?,?,?)
        """,
        (hid, kind, name, phone.strip() or None, website.strip() or None, notes.strip() or None, now()),
    )
    return int(cur.lastrowid or 0)


def admin_delete_trusted(conn: sqlite3.Connection, contact_id: int) -> bool:
    cur = conn.execute("DELETE FROM trusted_contacts WHERE id=?", (contact_id,))
    return int(cur.rowcount or 0) > 0


def admin_update_user(
    conn: sqlite3.Connection,
    user_id: int,
    *,
    name: str,
    email: str,
    phone: str = "",
    sms_opt_out: bool = False,
    password: str = "",
    role: str | None = None,
    household_id: int | None = None,
) -> dict[str, Any]:
    user = admin_get_user(conn, user_id)
    if not user:
        raise ValueError("That login is not in Family Shield Pro.")
    name = name.strip()
    email = email.lower().strip()
    if not name:
        raise ValueError("Name cannot be empty.")
    if not email or "@" not in email:
        raise ValueError("Need a valid email address.")
    taken = conn.execute(
        "SELECT id FROM users WHERE lower(email)=? AND id<>?",
        (email, user_id),
    ).fetchone()
    if taken:
        raise ValueError("That email already has a Family Shield Pro login.")
    next_role = (role or user.get("role") or "member").strip().lower()
    if next_role not in ("owner", "member"):
        raise ValueError("Role must be owner or member.")
    next_hid = int(household_id) if household_id is not None else int(user["household_id"])
    if not admin_get_household(conn, next_hid):
        raise ValueError("That circle is not in Family Shield Pro.")
    leaving = next_hid != int(user["household_id"]) or next_role != (user.get("role") or "")
    if leaving and admin_is_last_owner(conn, user) and (next_role != "owner" or next_hid != int(user["household_id"])):
        raise ValueError("That login is the last owner. Add another owner first, or delete the whole circle.")
    conn.execute(
        "UPDATE users SET name=?, email=?, role=?, household_id=? WHERE id=?",
        (name, email, next_role, next_hid, user_id),
    )
    set_user_phone(conn, user_id, phone, sms_opt_out)
    password = (password or "").strip()
    if password:
        if len(password) < 8:
            raise ValueError("Use at least 8 characters for a new password.")
        conn.execute(
            "UPDATE users SET password_hash=? WHERE id=?",
            (generate_password_hash(password), user_id),
        )
    updated = admin_get_user(conn, user_id)
    if not updated:
        raise ValueError("That login is not in Family Shield Pro.")
    return updated


def admin_disable_2fa(conn: sqlite3.Connection, user_id: int) -> dict[str, Any]:
    user = admin_get_user(conn, user_id)
    if not user:
        raise ValueError("That login is not in Family Shield Pro.")
    conn.execute(
        "UPDATE users SET totp_secret=NULL, totp_enabled=0, recovery_codes=NULL WHERE id=?",
        (user_id,),
    )
    updated = admin_get_user(conn, user_id)
    if not updated:
        raise ValueError("That login is not in Family Shield Pro.")
    return updated


def admin_create_user(
    conn: sqlite3.Connection,
    hid: int,
    *,
    name: str,
    email: str,
    password: str,
    role: str = "member",
    phone: str = "",
) -> dict[str, Any]:
    if not admin_get_household(conn, hid):
        raise ValueError("That circle is not in Family Shield Pro.")
    name = name.strip()
    email = email.lower().strip()
    role = (role or "member").strip().lower()
    password = (password or "").strip()
    if not name:
        raise ValueError("Name cannot be empty.")
    if not email or "@" not in email:
        raise ValueError("Need a valid email address.")
    if role not in ("owner", "member"):
        raise ValueError("Role must be owner or member.")
    if len(password) < 8:
        raise ValueError("Use at least 8 characters for a new password.")
    taken = conn.execute("SELECT id FROM users WHERE lower(email)=?", (email,)).fetchone()
    if taken:
        raise ValueError("That email already has a Family Shield Pro login.")
    cur = conn.execute(
        """
        INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone)
        VALUES (?,?,?,?,?,?,?)
        """,
        (hid, name, email, generate_password_hash(password), role, now(), phone or None),
    )
    uid = int(cur.lastrowid or 0)
    if phone:
        try:
            set_user_phone(conn, uid, phone, False)
        except ValueError:
            conn.execute("UPDATE users SET phone=NULL WHERE id=?", (uid,))
    created = admin_get_user(conn, uid)
    if not created:
        raise ValueError("Could not create that login.")
    return created


def admin_delete_user(conn: sqlite3.Connection, user_id: int) -> None:
    user = admin_get_user(conn, user_id)
    if not user:
        raise ValueError("That login is not in Family Shield Pro.")
    if admin_is_last_owner(conn, user):
        raise ValueError("That login is the last owner. Add another owner first, or delete the whole circle.")
    check_ids = [
        int(r["id"])
        for r in conn.execute("SELECT id FROM checks WHERE user_id=?", (user_id,)).fetchall()
    ]
    for cid in check_ids:
        _delete_check_row(conn, cid)
    conn.execute("DELETE FROM reviews WHERE requester_id=?", (user_id,))
    conn.execute("DELETE FROM alerts WHERE user_id=?", (user_id,))
    conn.execute("DELETE FROM password_resets WHERE user_id=?", (user_id,))
    conn.execute("DELETE FROM users WHERE id=?", (user_id,))


def admin_create_household(
    conn: sqlite3.Connection,
    *,
    name: str,
    plan: str,
    owner_name: str,
    owner_email: str,
    owner_password: str,
    phone: str = "",
) -> dict[str, Any]:
    owner_email = owner_email.lower().strip()
    owner_name = owner_name.strip()
    name = name.strip() or f"{owner_name}'s circle"
    owner_password = (owner_password or "").strip()
    if not owner_name:
        raise ValueError("Name cannot be empty.")
    if not owner_email or "@" not in owner_email:
        raise ValueError("Need a valid email address.")
    if len(owner_password) < 8:
        raise ValueError("Use at least 8 characters for a new password.")
    if conn.execute("SELECT id FROM users WHERE lower(email)=?", (owner_email,)).fetchone():
        raise ValueError("That email already has a Family Shield Pro login.")
    hid = create_household(
        conn,
        name=name,
        owner_name=owner_name,
        email=owner_email,
        password=owner_password,
        phone=phone,
    )
    admin_update_household(conn, hid, name=name, plan=plan)
    detail = admin_household_detail(conn, hid)
    if not detail:
        raise ValueError("Could not create that circle.")
    return detail


def admin_delete_household(conn: sqlite3.Connection, hid: int) -> None:
    if not admin_get_household(conn, hid):
        raise ValueError("That circle is not in Family Shield Pro.")
    check_ids = [
        int(r["id"])
        for r in conn.execute("SELECT id FROM checks WHERE household_id=?", (hid,)).fetchall()
    ]
    for cid in check_ids:
        _delete_check_row(conn, cid)
    conn.execute("DELETE FROM alerts WHERE household_id=?", (hid,))
    conn.execute("DELETE FROM reviews WHERE household_id=?", (hid,))
    conn.execute("DELETE FROM trusted_contacts WHERE household_id=?", (hid,))
    conn.execute("DELETE FROM invitations WHERE household_id=?", (hid,))
    user_ids = [
        int(r["id"])
        for r in conn.execute("SELECT id FROM users WHERE household_id=?", (hid,)).fetchall()
    ]
    for uid in user_ids:
        conn.execute("DELETE FROM password_resets WHERE user_id=?", (uid,))
    conn.execute("DELETE FROM users WHERE household_id=?", (hid,))
    conn.execute("DELETE FROM households WHERE id=?", (hid,))


def admin_create_invite(
    conn: sqlite3.Connection, hid: int, email: str, name: str = "", phone: str = ""
) -> dict[str, Any]:
    if not admin_get_household(conn, hid):
        raise ValueError("That circle is not in Family Shield Pro.")
    email = email.lower().strip()
    if not email or "@" not in email:
        raise ValueError("Need an email address to invite.")
    phone = (phone or "").strip()
    if phone and find_user_by_phone(conn, phone):
        phone = ""
    token = secrets.token_urlsafe(16)
    cur = conn.execute(
        """
        INSERT INTO invitations (household_id, email, name, token, status, created_at, phone)
        VALUES (?,?,?,?, 'pending', ?, ?)
        """,
        (hid, email, name.strip(), token, now(), phone or None),
    )
    return {"email": email, "token": token, "id": int(cur.lastrowid or 0), "phone": phone}


def admin_update_household(conn: sqlite3.Connection, hid: int, *, name: str, plan: str) -> dict[str, Any]:
    household = admin_get_household(conn, hid)
    if not household:
        raise ValueError("That circle is not in Family Shield Pro.")
    name = name.strip()
    plan = plan.strip().lower()
    if not name:
        raise ValueError("Circle name cannot be empty.")
    if plan not in ("monthly", "yearly"):
        raise ValueError("Plan must be monthly or yearly.")
    conn.execute("UPDATE households SET name=?, plan=? WHERE id=?", (name, plan, hid))
    updated = admin_get_household(conn, hid)
    if not updated:
        raise ValueError("That circle is not in Family Shield Pro.")
    return updated
