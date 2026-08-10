from __future__ import annotations

import json
import sqlite3
from contextlib import contextmanager
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Iterator

from werkzeug.security import generate_password_hash


def connect(db_path: str) -> sqlite3.Connection:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


@contextmanager
def db_session(db_path: str) -> Iterator[sqlite3.Connection]:
    conn = connect(db_path)
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def row_to_dict(row: sqlite3.Row | None) -> dict[str, Any] | None:
    if row is None:
        return None
    return {k: row[k] for k in row.keys()}


def rows_to_list(rows: list[sqlite3.Row]) -> list[dict[str, Any]]:
    return [row_to_dict(r) for r in rows]  # type: ignore[misc]


def init_db(db_path: str) -> None:
    Path(db_path).parent.mkdir(parents=True, exist_ok=True)
    with db_session(db_path) as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                business_name TEXT NOT NULL,
                owner_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT,
                website TEXT,
                currency TEXT NOT NULL DEFAULT 'USD',
                reminder_offsets TEXT NOT NULL,
                default_channel TEXT NOT NULL DEFAULT 'email',
                smtp_enabled INTEGER NOT NULL DEFAULT 0,
                trial_ends_on TEXT,
                plan TEXT NOT NULL DEFAULT 'trial',
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT
            );

            CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                company TEXT,
                email TEXT,
                phone TEXT,
                notes TEXT,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                number TEXT NOT NULL UNIQUE,
                client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
                title TEXT NOT NULL,
                amount REAL NOT NULL,
                amount_paid REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'USD',
                issue_date TEXT NOT NULL,
                due_date TEXT NOT NULL,
                status TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
                amount REAL NOT NULL,
                method TEXT,
                paid_at TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                channel TEXT NOT NULL,
                subject TEXT,
                body TEXT NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
                channel TEXT NOT NULL,
                scheduled_for TEXT NOT NULL,
                status TEXT NOT NULL,
                subject TEXT,
                body TEXT NOT NULL,
                sent_at TEXT,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kind TEXT NOT NULL,
                message TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                created_at TEXT NOT NULL
            );
            """
        )
        _migrate(conn)
        existing = conn.execute("SELECT id FROM settings WHERE id = 1").fetchone()
        if not existing:
            _seed(conn)


def _migrate(conn: sqlite3.Connection) -> None:
    cols = {r["name"] for r in conn.execute("PRAGMA table_info(settings)").fetchall()}
    if "stripe_customer_id" not in cols:
        conn.execute("ALTER TABLE settings ADD COLUMN stripe_customer_id TEXT")
    if "stripe_subscription_id" not in cols:
        conn.execute("ALTER TABLE settings ADD COLUMN stripe_subscription_id TEXT")

    # Rename legacy demo login robert@ → trialuser@
    legacy = conn.execute(
        "SELECT id FROM users WHERE lower(email) = ?",
        ("robert@inpmnt.app",),
    ).fetchone()
    if legacy:
        taken = conn.execute(
            "SELECT id FROM users WHERE lower(email) = ?",
            ("trialuser@inpmnt.app",),
        ).fetchone()
        if not taken:
            conn.execute(
                "UPDATE users SET email = ?, name = ? WHERE id = ?",
                ("trialuser@inpmnt.app", "Trial User", legacy["id"]),
            )
        settings = conn.execute("SELECT email, owner_name FROM settings WHERE id = 1").fetchone()
        if settings and (settings["email"] or "").lower() == "robert@inpmnt.app":
            conn.execute(
                "UPDATE settings SET email = ?, owner_name = ? WHERE id = 1",
                ("trialuser@inpmnt.app", "Trial User"),
            )


def _seed(conn: sqlite3.Connection) -> None:
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    today = date.today()

    conn.execute(
        """
        INSERT INTO users (email, name, password_hash, created_at)
        VALUES (?, ?, ?, ?)
        """,
        (
            "trialuser@inpmnt.app",
            "Trial User",
            generate_password_hash("demo1234"),
            now,
        ),
    )

    offsets = json.dumps([-3, 0, 3, 7, 14])
    conn.execute(
        """
        INSERT INTO settings (
            id, business_name, owner_name, email, phone, website, currency,
            reminder_offsets, default_channel, smtp_enabled, trial_ends_on, plan
        ) VALUES (1, ?, ?, ?, ?, ?, 'USD', ?, 'email', 0, ?, 'trial')
        """,
        (
            "Foster Field Services",
            "Trial User",
            "trialuser@inpmnt.app",
            "(555) 014-2200",
            "https://inpmnt.app",
            offsets,
            (today + timedelta(days=14)).isoformat(),
        ),
    )

    templates = [
        (
            "Friendly nudge",
            "email",
            "Quick reminder about invoice {{number}}",
            "Hi {{client_name}},\n\nJust a friendly reminder that invoice {{number}} "
            "for {{amount_due}} is due on {{due_date}}.\n\n"
            "You can reply to this email if you have any questions.\n\n"
            "Thanks,\n{{business_name}}",
            1,
        ),
        (
            "Due today",
            "email",
            "Invoice {{number}} is due today",
            "Hi {{client_name}},\n\nInvoice {{number}} for {{amount_due}} is due today. "
            "Please let us know if payment is already on the way.\n\n"
            "Appreciate you,\n{{business_name}}",
            0,
        ),
        (
            "Overdue follow-up",
            "email",
            "Past due: invoice {{number}}",
            "Hi {{client_name}},\n\nInvoice {{number}} for {{amount_due}} was due on "
            "{{due_date}} and remains unpaid.\n\n"
            "Please arrange payment at your earliest convenience, or reply so we can help.\n\n"
            "{{business_name}}",
            0,
        ),
        (
            "SMS short",
            "sms",
            None,
            "Hi {{client_name}} — invoice {{number}} ({{amount_due}}) is {{status}}. "
            "Reply STOP to opt out. — {{business_name}}",
            1,
        ),
        (
            "Final notice",
            "email",
            "Final notice: invoice {{number}}",
            "Hi {{client_name}},\n\nThis is a final notice regarding unpaid invoice "
            "{{number}} ({{amount_due}}), originally due {{due_date}}.\n\n"
            "Please remit payment promptly to avoid further collection steps.\n\n"
            "{{business_name}}",
            0,
        ),
    ]
    for name, channel, subject, body, is_default in templates:
        conn.execute(
            """
            INSERT INTO templates (name, channel, subject, body, is_default)
            VALUES (?, ?, ?, ?, ?)
            """,
            (name, channel, subject, body, is_default),
        )

    clients = [
        ("Maya Chen", "Chen Landscape Co.", "maya@chenlandscape.com", "(555) 201-8841", "Prefers email"),
        ("Jake Ortiz", "Ortiz Plumbing", "jake@ortizplumbing.com", "(555) 441-9022", "Pays by check"),
        ("Priya Shah", "Studio North Photo", "priya@studionorth.co", "(555) 778-1104", ""),
        ("Tom Reeves", "Reeves Consulting", "tom@reeves.co", "(555) 332-6710", "Net 15"),
    ]
    client_ids = []
    for name, company, email, phone, notes in clients:
        cur = conn.execute(
            """
            INSERT INTO clients (name, company, email, phone, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (name, company, email, phone, notes, now),
        )
        client_ids.append(cur.lastrowid)

    invoices = [
        ("INV-1001", client_ids[0], "Spring irrigation tune-up", 1850.00, 0, today - timedelta(days=20), today - timedelta(days=5), "overdue"),
        ("INV-1002", client_ids[1], "Water heater install", 2460.00, 1000.00, today - timedelta(days=12), today + timedelta(days=2), "partial"),
        ("INV-1003", client_ids[2], "Brand shoot — May", 3200.00, 0, today - timedelta(days=3), today + timedelta(days=11), "sent"),
        ("INV-1004", client_ids[3], "Ops retainer — July", 1500.00, 1500.00, today - timedelta(days=40), today - timedelta(days=25), "paid"),
        ("INV-1005", client_ids[0], "Patio lighting package", 980.00, 0, today, today + timedelta(days=14), "draft"),
        ("INV-1006", client_ids[1], "Emergency leak repair", 640.00, 0, today - timedelta(days=18), today - timedelta(days=11), "overdue"),
    ]
    for number, client_id, title, amount, paid, issue, due, status in invoices:
        conn.execute(
            """
            INSERT INTO invoices (
                number, client_id, title, amount, amount_paid, currency,
                issue_date, due_date, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'USD', ?, ?, ?, '', ?, ?)
            """,
            (number, client_id, title, amount, paid, issue.isoformat(), due.isoformat(), status, now, now),
        )

    if invoices[3][4] > 0:
        inv = conn.execute("SELECT id FROM invoices WHERE number = 'INV-1004'").fetchone()
        conn.execute(
            """
            INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at)
            VALUES (?, ?, 'ACH', ?, 'Paid in full', ?)
            """,
            (inv["id"], 1500.00, (today - timedelta(days=24)).isoformat(), now),
        )
        inv2 = conn.execute("SELECT id FROM invoices WHERE number = 'INV-1002'").fetchone()
        conn.execute(
            """
            INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at)
            VALUES (?, ?, 'Card', ?, 'Deposit', ?)
            """,
            (inv2["id"], 1000.00, (today - timedelta(days=10)).isoformat(), now),
        )

    # Schedule reminders for open invoices
    open_invoices = conn.execute(
        "SELECT * FROM invoices WHERE status IN ('sent','partial','overdue')"
    ).fetchall()
    default_offsets = [-3, 0, 3, 7, 14]
    for inv in open_invoices:
        due = date.fromisoformat(inv["due_date"])
        for offset in default_offsets:
            scheduled = due + timedelta(days=offset)
            status = "pending"
            sent_at = None
            if scheduled < today:
                status = "sent"
                sent_at = scheduled.isoformat() + "T09:00:00Z"
            elif scheduled == today:
                status = "due"
            body = (
                f"Reminder for {inv['number']}: "
                f"${inv['amount'] - inv['amount_paid']:,.2f} due {inv['due_date']}."
            )
            conn.execute(
                """
                INSERT INTO reminders (
                    invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at
                ) VALUES (?, 'email', ?, ?, ?, ?, ?, ?)
                """,
                (
                    inv["id"],
                    scheduled.isoformat(),
                    status,
                    f"Reminder: {inv['number']}",
                    body,
                    sent_at,
                    now,
                ),
            )

    conn.execute(
        """
        INSERT INTO activity (kind, message, entity_type, entity_id, created_at)
        VALUES
        ('system', 'InPmnt workspace created for Trial User', 'settings', 1, ?),
        ('invoice', 'Demo invoices and reminder schedule loaded', 'invoice', NULL, ?)
        """,
        (now, now),
    )


def log_activity(
    conn: sqlite3.Connection,
    kind: str,
    message: str,
    entity_type: str | None = None,
    entity_id: int | None = None,
) -> None:
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    conn.execute(
        """
        INSERT INTO activity (kind, message, entity_type, entity_id, created_at)
        VALUES (?, ?, ?, ?, ?)
        """,
        (kind, message, entity_type, entity_id, now),
    )


def next_invoice_number(conn: sqlite3.Connection) -> str:
    row = conn.execute(
        "SELECT number FROM invoices ORDER BY id DESC LIMIT 1"
    ).fetchone()
    if not row:
        return "INV-1001"
    try:
        n = int(str(row["number"]).split("-")[-1]) + 1
    except ValueError:
        n = conn.execute("SELECT COUNT(*) AS c FROM invoices").fetchone()["c"] + 1001
    return f"INV-{n}"


def refresh_invoice_status(conn: sqlite3.Connection, invoice_id: int) -> None:
    inv = conn.execute("SELECT * FROM invoices WHERE id = ?", (invoice_id,)).fetchone()
    if not inv:
        return
    if inv["status"] == "draft":
        return
    balance = round(inv["amount"] - inv["amount_paid"], 2)
    today = date.today().isoformat()
    if balance <= 0:
        status = "paid"
    elif inv["amount_paid"] > 0:
        status = "partial" if inv["due_date"] >= today else "overdue"
    elif inv["due_date"] < today:
        status = "overdue"
    else:
        status = "sent"
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    conn.execute(
        "UPDATE invoices SET status = ?, updated_at = ? WHERE id = ?",
        (status, now, invoice_id),
    )
