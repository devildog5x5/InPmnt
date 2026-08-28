from __future__ import annotations

import json
import sqlite3
from contextlib import contextmanager
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Iterator

from werkzeug.security import check_password_hash, generate_password_hash

# Reserved system admin (not for demo UI).
ADMIN_EMAIL = "admin@inpmnt.app"
ADMIN_NAME = "Admin"
ADMIN_PASSWORD = "LifeMadeUSMCForged100!"

# Demo account used for SHOW_DEMO_LOGIN + sample data.
DEMO_EMAIL = "demouser@inpmnt.app"
DEMO_NAME = "Demo User"
DEMO_PASSWORD = "Demo"

# Blocked at signup (system / legacy aliases).
RESERVED_SIGNUP_EMAILS = frozenset(
    {
        ADMIN_EMAIL,
        DEMO_EMAIL,
        "trialuser@inpmnt.app",
        "robert@inpmnt.app",
    }
)

DEFAULT_TEMPLATE_DEFS = [
    (
        "Invoice",
        "email",
        "Invoice {{number}} from {{business_name}}",
        "Hi {{client_name}},\n\n"
        "Invoice {{number}} is ready for {{title}}.\n\n"
        "Amount due: {{amount_due}}\n"
        "Due date: {{due_date}}\n\n"
        "A PDF copy of this invoice is attached.\n\n"
        "Please reply to this email if you have any questions.\n\n"
        "Thanks,\n{{business_name}}",
        0,
    ),
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
                workspace_id INTEGER,
                role TEXT NOT NULL DEFAULT 'user',
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER,
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
                workspace_id INTEGER NOT NULL DEFAULT 1,
                name TEXT NOT NULL,
                company TEXT,
                email TEXT,
                phone TEXT,
                notes TEXT,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                number TEXT NOT NULL,
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
                updated_at TEXT NOT NULL,
                UNIQUE (workspace_id, number)
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
                workspace_id INTEGER NOT NULL DEFAULT 1,
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
                workspace_id INTEGER NOT NULL DEFAULT 1,
                kind TEXT NOT NULL,
                message TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                created_at TEXT NOT NULL
            );
            """
        )
        _migrate(conn)
        existing = conn.execute("SELECT id FROM settings LIMIT 1").fetchone()
        if not existing:
            _seed(conn)
        else:
            _ensure_system_accounts(conn)
        ensure_missing_templates(conn)


def _table_sql(conn: sqlite3.Connection, name: str) -> str:
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (name,)
    ).fetchone()
    return row["sql"] or "" if row else ""


def _migrate(conn: sqlite3.Connection) -> None:
    # --- settings: drop CHECK(id=1), add owner_user_id ---
    settings_sql = _table_sql(conn, "settings")
    settings_cols = {r["name"] for r in conn.execute("PRAGMA table_info(settings)").fetchall()}
    if settings_cols and (
        "CHECK (id = 1)" in settings_sql.replace("CHECK(id=1)", "CHECK (id = 1)")
        or "owner_user_id" not in settings_cols
    ):
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS settings_v2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER,
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
            """
        )
        # Copy if empty target
        if not conn.execute("SELECT id FROM settings_v2 LIMIT 1").fetchone():
            for row in conn.execute("SELECT * FROM settings").fetchall():
                keys = [k for k in row.keys() if k != "owner_user_id"]
                if "owner_user_id" in settings_cols:
                    keys = list(row.keys())
                cols = ", ".join(keys)
                placeholders = ", ".join(["?"] * len(keys))
                conn.execute(
                    f"INSERT INTO settings_v2 ({cols}) VALUES ({placeholders})",
                    [row[k] for k in keys],
                )
                # Ensure owner_user_id column populated later
        conn.execute("DROP TABLE settings")
        conn.execute("ALTER TABLE settings_v2 RENAME TO settings")
        settings_cols = {r["name"] for r in conn.execute("PRAGMA table_info(settings)").fetchall()}

    if "owner_user_id" not in settings_cols and settings_cols:
        conn.execute("ALTER TABLE settings ADD COLUMN owner_user_id INTEGER")

    # --- users.workspace_id / role ---
    user_cols = {r["name"] for r in conn.execute("PRAGMA table_info(users)").fetchall()}
    if "workspace_id" not in user_cols:
        conn.execute("ALTER TABLE users ADD COLUMN workspace_id INTEGER")
    if "role" not in user_cols:
        conn.execute(
            "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'"
        )

    # --- stamp workspace_id on data tables ---
    for table in ("clients", "templates", "activity"):
        cols = {r["name"] for r in conn.execute(f"PRAGMA table_info({table})").fetchall()}
        if "workspace_id" not in cols:
            conn.execute(
                f"ALTER TABLE {table} ADD COLUMN workspace_id INTEGER NOT NULL DEFAULT 1"
            )
        conn.execute(f"UPDATE {table} SET workspace_id = 1 WHERE workspace_id IS NULL")

    inv_sql = _table_sql(conn, "invoices")
    inv_cols = {r["name"] for r in conn.execute("PRAGMA table_info(invoices)").fetchall()}
    needs_inv_rebuild = "workspace_id" not in inv_cols or (
        "UNIQUE" in inv_sql and "workspace_id, number" not in inv_sql.replace(" ", "")
        and "number TEXT NOT NULL UNIQUE" in inv_sql
    )
    if needs_inv_rebuild and inv_cols:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS invoices_v2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                number TEXT NOT NULL,
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
                updated_at TEXT NOT NULL,
                UNIQUE (workspace_id, number)
            );
            """
        )
        if not conn.execute("SELECT id FROM invoices_v2 LIMIT 1").fetchone():
            for row in conn.execute("SELECT * FROM invoices").fetchall():
                wid = row["workspace_id"] if "workspace_id" in row.keys() else 1
                conn.execute(
                    """
                    INSERT INTO invoices_v2 (
                        id, workspace_id, number, client_id, title, amount, amount_paid,
                        currency, issue_date, due_date, status, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        row["id"],
                        wid or 1,
                        row["number"],
                        row["client_id"],
                        row["title"],
                        row["amount"],
                        row["amount_paid"],
                        row["currency"],
                        row["issue_date"],
                        row["due_date"],
                        row["status"],
                        row["notes"],
                        row["created_at"],
                        row["updated_at"],
                    ),
                )
        conn.execute("DROP TABLE invoices")
        conn.execute("ALTER TABLE invoices_v2 RENAME TO invoices")
    elif "workspace_id" not in inv_cols:
        conn.execute(
            "ALTER TABLE invoices ADD COLUMN workspace_id INTEGER NOT NULL DEFAULT 1"
        )

    conn.execute("UPDATE invoices SET workspace_id = 1 WHERE workspace_id IS NULL")

    # Link first non-admin user to workspace 1 when needed
    ws = conn.execute("SELECT id FROM settings ORDER BY id LIMIT 1").fetchone()
    if ws:
        wid = ws["id"]
        user = conn.execute(
            """
            SELECT id FROM users
            WHERE lower(email) IN (?, ?) OR workspace_id IS NULL
            ORDER BY CASE lower(email)
                WHEN ? THEN 0
                WHEN ? THEN 1
                ELSE 2 END, id
            LIMIT 1
            """,
            (DEMO_EMAIL, "trialuser@inpmnt.app", DEMO_EMAIL, "trialuser@inpmnt.app"),
        ).fetchone()
        if not user:
            user = conn.execute(
                "SELECT id FROM users WHERE lower(role) != 'admin' ORDER BY id LIMIT 1"
            ).fetchone()
        if user:
            conn.execute(
                "UPDATE users SET workspace_id = ? WHERE id = ?", (wid, user["id"])
            )
            conn.execute(
                "UPDATE settings SET owner_user_id = COALESCE(owner_user_id, ?) WHERE id = ?",
                (user["id"], wid),
            )


def _ensure_system_accounts(conn: sqlite3.Connection) -> None:
    """Reserved admin + DemoUser for published / Docker installs."""
    # Legacy robert / trialuser → demouser
    for old_email in ("robert@inpmnt.app", "trialuser@inpmnt.app"):
        legacy = conn.execute(
            "SELECT id FROM users WHERE lower(email) = ?",
            (old_email,),
        ).fetchone()
        if not legacy:
            continue
        taken = conn.execute(
            "SELECT id FROM users WHERE lower(email) = ?",
            (DEMO_EMAIL,),
        ).fetchone()
        if not taken:
            conn.execute(
                "UPDATE users SET email = ?, name = ?, role = 'user' WHERE id = ?",
                (DEMO_EMAIL, DEMO_NAME, legacy["id"]),
            )
        settings = conn.execute(
            "SELECT id FROM settings WHERE lower(email) = ?",
            (old_email,),
        ).fetchone()
        if settings:
            conn.execute(
                "UPDATE settings SET email = ?, owner_name = ? WHERE id = ?",
                (DEMO_EMAIL, DEMO_NAME, settings["id"]),
            )

    demo = conn.execute(
        "SELECT id, password_hash FROM users WHERE lower(email) = ?",
        (DEMO_EMAIL,),
    ).fetchone()
    if demo:
        # System demo account: keep published Demo password.
        if not check_password_hash(demo["password_hash"], DEMO_PASSWORD):
            conn.execute(
                "UPDATE users SET password_hash = ?, name = ?, role = 'user' WHERE id = ?",
                (generate_password_hash(DEMO_PASSWORD), DEMO_NAME, demo["id"]),
            )
        else:
            conn.execute(
                "UPDATE users SET name = ?, role = 'user' WHERE id = ?",
                (DEMO_NAME, demo["id"]),
            )
    else:
        create_workspace(
            conn,
            email=DEMO_EMAIL,
            name=DEMO_NAME,
            password_hash=generate_password_hash(DEMO_PASSWORD),
            business_name="Foster Field Services",
            role="user",
        )

    admin = conn.execute(
        "SELECT id, password_hash FROM users WHERE lower(email) = ?",
        (ADMIN_EMAIL,),
    ).fetchone()
    if admin:
        if not check_password_hash(admin["password_hash"], ADMIN_PASSWORD):
            conn.execute(
                "UPDATE users SET password_hash = ?, name = ?, role = 'admin' WHERE id = ?",
                (generate_password_hash(ADMIN_PASSWORD), ADMIN_NAME, admin["id"]),
            )
        else:
            conn.execute(
                "UPDATE users SET name = ?, role = 'admin' WHERE id = ?",
                (ADMIN_NAME, admin["id"]),
            )
    else:
        create_workspace(
            conn,
            email=ADMIN_EMAIL,
            name=ADMIN_NAME,
            password_hash=generate_password_hash(ADMIN_PASSWORD),
            business_name="InPmnt Admin",
            role="admin",
        )


def insert_default_templates(conn: sqlite3.Connection, workspace_id: int) -> None:
    for name, channel, subject, body, is_default in DEFAULT_TEMPLATE_DEFS:
        conn.execute(
            """
            INSERT INTO templates (workspace_id, name, channel, subject, body, is_default)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (workspace_id, name, channel, subject, body, is_default),
        )


def ensure_missing_templates(conn: sqlite3.Connection) -> None:
    """Add any newly shipped templates to existing workspaces."""
    for row in conn.execute("SELECT id FROM settings").fetchall():
        wid = int(row["id"])
        for name, channel, subject, body, is_default in DEFAULT_TEMPLATE_DEFS:
            exists = conn.execute(
                "SELECT id FROM templates WHERE workspace_id=? AND name=?",
                (wid, name),
            ).fetchone()
            if exists:
                continue
            conn.execute(
                """
                INSERT INTO templates (workspace_id, name, channel, subject, body, is_default)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                (wid, name, channel, subject, body, is_default),
            )


def create_workspace(
    conn: sqlite3.Connection,
    *,
    email: str,
    name: str,
    password_hash: str,
    business_name: str | None = None,
    role: str = "user",
) -> tuple[int, int]:
    """Create user + settings workspace + default templates. Returns (user_id, workspace_id)."""
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    today = date.today()
    offsets = json.dumps([-3, 0, 3, 7, 14])
    biz = (business_name or f"{name}'s business").strip() or "My business"
    user_role = "admin" if (role or "").strip().lower() == "admin" else "user"

    cur = conn.execute(
        """
        INSERT INTO settings (
            business_name, owner_name, email, phone, website, currency,
            reminder_offsets, default_channel, smtp_enabled, trial_ends_on, plan
        ) VALUES (?, ?, ?, NULL, NULL, 'USD', ?, 'email', 0, ?, 'trial')
        """,
        (biz, name, email, offsets, (today + timedelta(days=14)).isoformat()),
    )
    wid = int(cur.lastrowid)
    ucur = conn.execute(
        """
        INSERT INTO users (email, name, password_hash, workspace_id, role, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
        """,
        (email, name, password_hash, wid, user_role, now),
    )
    uid = int(ucur.lastrowid)
    conn.execute("UPDATE settings SET owner_user_id = ? WHERE id = ?", (uid, wid))
    insert_default_templates(conn, wid)
    log_activity(
        conn,
        "system",
        f"Workspace created for {name}",
        "settings",
        wid,
        workspace_id=wid,
    )
    return uid, wid


def _seed(conn: sqlite3.Connection) -> None:
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    today = date.today()

    create_workspace(
        conn,
        email=ADMIN_EMAIL,
        name=ADMIN_NAME,
        password_hash=generate_password_hash(ADMIN_PASSWORD),
        business_name="InPmnt Admin",
        role="admin",
    )

    uid, wid = create_workspace(
        conn,
        email=DEMO_EMAIL,
        name=DEMO_NAME,
        password_hash=generate_password_hash(DEMO_PASSWORD),
        business_name="Foster Field Services",
        role="user",
    )
    conn.execute(
        """
        UPDATE settings SET phone=?, website=? WHERE id=?
        """,
        ("(555) 014-2200", "https://inpmnt.app", wid),
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
            INSERT INTO clients (workspace_id, name, company, email, phone, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (wid, name, company, email, phone, notes, now),
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
                workspace_id, number, client_id, title, amount, amount_paid, currency,
                issue_date, due_date, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'USD', ?, ?, ?, '', ?, ?)
            """,
            (wid, number, client_id, title, amount, paid, issue.isoformat(), due.isoformat(), status, now, now),
        )

    inv = conn.execute(
        "SELECT id FROM invoices WHERE workspace_id=? AND number='INV-1004'", (wid,)
    ).fetchone()
    if inv:
        conn.execute(
            """
            INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at)
            VALUES (?, ?, 'ACH', ?, 'Paid in full', ?)
            """,
            (inv["id"], 1500.00, (today - timedelta(days=24)).isoformat(), now),
        )
    inv2 = conn.execute(
        "SELECT id FROM invoices WHERE workspace_id=? AND number='INV-1002'", (wid,)
    ).fetchone()
    if inv2:
        conn.execute(
            """
            INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at)
            VALUES (?, ?, 'Card', ?, 'Deposit', ?)
            """,
            (inv2["id"], 1000.00, (today - timedelta(days=10)).isoformat(), now),
        )

    open_invoices = conn.execute(
        "SELECT * FROM invoices WHERE workspace_id=? AND status IN ('sent','partial','overdue')",
        (wid,),
    ).fetchall()
    default_offsets = [-3, 0, 3, 7, 14]
    for inv_row in open_invoices:
        due = date.fromisoformat(inv_row["due_date"])
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
                f"Reminder for {inv_row['number']}: "
                f"${inv_row['amount'] - inv_row['amount_paid']:,.2f} due {inv_row['due_date']}."
            )
            conn.execute(
                """
                INSERT INTO reminders (
                    invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at
                ) VALUES (?, 'email', ?, ?, ?, ?, ?, ?)
                """,
                (
                    inv_row["id"],
                    scheduled.isoformat(),
                    status,
                    f"Reminder: {inv_row['number']}",
                    body,
                    sent_at,
                    now,
                ),
            )

    log_activity(
        conn,
        "invoice",
        "Demo invoices and reminder schedule loaded",
        "invoice",
        None,
        workspace_id=wid,
    )


def log_activity(
    conn: sqlite3.Connection,
    kind: str,
    message: str,
    entity_type: str | None = None,
    entity_id: int | None = None,
    workspace_id: int | None = None,
) -> None:
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = workspace_id if workspace_id is not None else 1
    conn.execute(
        """
        INSERT INTO activity (workspace_id, kind, message, entity_type, entity_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
        """,
        (wid, kind, message, entity_type, entity_id, now),
    )


def next_invoice_number(conn: sqlite3.Connection, workspace_id: int) -> str:
    row = conn.execute(
        "SELECT number FROM invoices WHERE workspace_id=? ORDER BY id DESC LIMIT 1",
        (workspace_id,),
    ).fetchone()
    if not row:
        return "INV-1001"
    try:
        n = int(str(row["number"]).split("-")[-1]) + 1
    except ValueError:
        n = (
            conn.execute(
                "SELECT COUNT(*) AS c FROM invoices WHERE workspace_id=?",
                (workspace_id,),
            ).fetchone()["c"]
            + 1001
        )
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
