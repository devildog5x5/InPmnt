from __future__ import annotations

import json
import os
import re
from datetime import date, datetime, timedelta
from functools import wraps
from typing import Any

from flask import (
    Blueprint,
    current_app,
    g,
    jsonify,
    redirect,
    render_template,
    request,
    session,
    url_for,
)
from werkzeug.security import check_password_hash, generate_password_hash

from .billing import (
    PLANS,
    create_checkout_session,
    create_portal_session,
    load_stripe_config,
    plan_from_price_id,
)
from .database import (
    RESERVED_SIGNUP_EMAILS,
    create_workspace,
    db_session,
    log_activity,
    next_invoice_number,
    refresh_invoice_status,
    row_to_dict,
    rows_to_list,
)
from .mail import mail_configured, send_email
from .workspace import (
    assert_can_add_open_invoice,
    effective_plan,
    get_settings,
    plan_allows_sms,
    require_workspace_id,
)

bp = Blueprint("main", __name__)


def db_path() -> str:
    return current_app.config["DATABASE"]


def login_required(fn):
    @wraps(fn)
    def wrapper(*args, **kwargs):
        if not session.get("user_id"):
            if request.path.startswith("/api/"):
                return jsonify({"error": "Unauthorized"}), 401
            return redirect(url_for("main.login"))
        return fn(*args, **kwargs)

    return wrapper


@bp.before_app_request
def load_user() -> None:
    g.user = None
    uid = session.get("user_id")
    if not uid:
        return
    with db_session(db_path()) as conn:
        row = conn.execute(
            "SELECT id, email, name, workspace_id FROM users WHERE id = ?", (uid,)
        ).fetchone()
        g.user = row_to_dict(row)


# ---------- Pages ----------

@bp.get("/")
def landing():
    if session.get("user_id"):
        return redirect(url_for("main.app_home"))
    cfg = load_stripe_config()
    return render_template(
        "landing.html",
        stripe_enabled=cfg.enabled,
        publishable_key=cfg.publishable_key,
        plans=PLANS,
        show_demo_login=_show_demo_login(),
    )


def _show_demo_login() -> bool:
    import os

    return (os.environ.get("SHOW_DEMO_LOGIN") or "").strip().lower() in (
        "1",
        "true",
        "yes",
    )


def _safe_next_url(raw: str | None) -> str | None:
    """Allow only same-origin relative paths (blocks open redirects)."""
    if not raw:
        return None
    next_url = raw.strip()
    if next_url.startswith("/") and not next_url.startswith("//"):
        return next_url
    return None


@bp.route("/login", methods=["GET", "POST"])
def login():
    next_url = _safe_next_url(request.args.get("next") or request.form.get("next"))
    if session.get("user_id"):
        return redirect(next_url or url_for("main.app_home"))
    error = None
    if request.method == "POST":
        email = (request.form.get("email") or "").strip().lower()
        password = request.form.get("password") or ""
        with db_session(db_path()) as conn:
            user = conn.execute(
                "SELECT * FROM users WHERE lower(email) = ?", (email,)
            ).fetchone()
            if user and check_password_hash(user["password_hash"], password):
                session["user_id"] = user["id"]
                return redirect(next_url or url_for("main.app_home"))
        error = "Invalid email or password."
    return render_template(
        "login.html",
        error=error,
        next=next_url or "",
        show_demo_login=_show_demo_login(),
    )


@bp.route("/signup", methods=["GET", "POST"])
def signup():
    if session.get("user_id"):
        return redirect(url_for("main.app_home"))
    error = None
    if request.method == "POST":
        email = (request.form.get("email") or "").strip().lower()
        name = (request.form.get("name") or "").strip()
        business = (request.form.get("business_name") or "").strip()
        password = request.form.get("password") or ""
        if not email or "@" not in email:
            error = "Enter a valid email."
        elif email in RESERVED_SIGNUP_EMAILS:
            error = "That email is reserved. Choose another or log in."
        elif len(password) < 8:
            error = "Password must be at least 8 characters."
        elif not name:
            error = "Name is required."
        else:
            try:
                with db_session(db_path()) as conn:
                    taken = conn.execute(
                        "SELECT id FROM users WHERE lower(email)=?", (email,)
                    ).fetchone()
                    if taken:
                        error = "That email is already registered. Log in instead."
                    else:
                        uid, _wid = create_workspace(
                            conn,
                            email=email,
                            name=name,
                            password_hash=generate_password_hash(password),
                            business_name=business or None,
                        )
                        session["user_id"] = uid
                        return redirect(url_for("main.app_home"))
            except Exception as exc:  # noqa: BLE001
                error = str(exc)
    return render_template("signup.html", error=error)


@bp.get("/logout")
def logout():
    session.clear()
    return redirect(url_for("main.landing"))


@bp.get("/app")
@bp.get("/app/<path:_>")
@login_required
def app_home(_=None):
    return render_template("app.html", user=g.user)


# ---------- Helpers ----------

def money(n: float) -> str:
    return f"${n:,.2f}"


def render_template_vars(text: str | None, ctx: dict[str, Any]) -> str:
    if not text:
        return ""
    out = text
    for key, value in ctx.items():
        out = out.replace("{{" + key + "}}", str(value))
    return out


def invoice_balance(inv: dict[str, Any]) -> float:
    return round(float(inv["amount"]) - float(inv["amount_paid"]), 2)


def schedule_reminders_for_invoice(conn, invoice_id: int, due_date: str, force: bool = False) -> int:
    inv0 = conn.execute("SELECT workspace_id FROM invoices WHERE id = ?", (invoice_id,)).fetchone()
    if not inv0:
        return 0
    wid = inv0["workspace_id"]
    settings = get_settings(conn, wid)
    offsets = json.loads(settings["reminder_offsets"] or "[-3,0,3,7,14]")
    channel = settings["default_channel"] or "email"
    due = date.fromisoformat(due_date)
    today = date.today()
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"

    if force:
        conn.execute(
            "DELETE FROM reminders WHERE invoice_id = ? AND status IN ('pending','due')",
            (invoice_id,),
        )

    existing = {
        r["scheduled_for"]
        for r in conn.execute(
            "SELECT scheduled_for FROM reminders WHERE invoice_id = ?", (invoice_id,)
        ).fetchall()
    }

    tmpl = conn.execute(
        """
        SELECT * FROM templates
        WHERE workspace_id = ? AND channel = ? AND is_default = 1
        ORDER BY id LIMIT 1
        """,
        (wid, channel),
    ).fetchone()
    inv = conn.execute(
        """
        SELECT i.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone
        FROM invoices i JOIN clients c ON c.id = i.client_id
        WHERE i.id = ? AND i.workspace_id = ?
        """,
        (invoice_id, wid),
    ).fetchone()
    if not inv:
        return 0

    ctx = {
        "number": inv["number"],
        "client_name": inv["client_name"],
        "amount_due": money(invoice_balance(dict(inv))),
        "due_date": inv["due_date"],
        "status": inv["status"],
        "business_name": settings["business_name"],
    }
    created = 0
    for offset in offsets:
        scheduled = (due + timedelta(days=int(offset))).isoformat()
        if scheduled in existing:
            continue
        status = "pending"
        if scheduled < today.isoformat():
            continue  # don't backfill past reminders on new schedule
        if scheduled == today.isoformat():
            status = "due"
        subject = render_template_vars(tmpl["subject"] if tmpl else f"Reminder: {inv['number']}", ctx)
        body = render_template_vars(
            tmpl["body"] if tmpl else f"Reminder for invoice {inv['number']}.",
            ctx,
        )
        conn.execute(
            """
            INSERT INTO reminders (
                invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)
            """,
            (invoice_id, channel, scheduled, status, subject, body, now),
        )
        created += 1
    return created


def invoice_detail(conn, invoice_id: int, workspace_id: int | None = None) -> dict[str, Any] | None:
    wid = workspace_id if workspace_id is not None else require_workspace_id()
    row = conn.execute(
        """
        SELECT i.*, c.name AS client_name, c.company AS client_company,
               c.email AS client_email, c.phone AS client_phone
        FROM invoices i
        JOIN clients c ON c.id = i.client_id
        WHERE i.id = ? AND i.workspace_id = ?
        """,
        (invoice_id, wid),
    ).fetchone()
    if not row:
        return None
    data = row_to_dict(row)
    assert data
    data["balance"] = invoice_balance(data)
    data["payments"] = rows_to_list(
        conn.execute(
            "SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC, id DESC",
            (invoice_id,),
        ).fetchall()
    )
    data["reminders"] = rows_to_list(
        conn.execute(
            "SELECT * FROM reminders WHERE invoice_id = ? ORDER BY scheduled_for",
            (invoice_id,),
        ).fetchall()
    )
    return data


# ---------- API: me / dashboard ----------

@bp.get("/api/me")
@login_required
def api_me():
    with db_session(db_path()) as conn:
        wid = require_workspace_id()
        settings = row_to_dict(get_settings(conn, wid))
    return jsonify({"user": g.user, "settings": settings})


@bp.get("/api/dashboard")
@login_required
def api_dashboard():
    today = date.today().isoformat()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        open_inv = rows_to_list(
            conn.execute(
                """
                SELECT i.*, c.name AS client_name
                FROM invoices i JOIN clients c ON c.id = i.client_id
                WHERE i.workspace_id = ? AND i.status IN ('sent','partial','overdue')
                ORDER BY i.due_date
                """,
                (wid,),
            ).fetchall()
        )
        for inv in open_inv:
            inv["balance"] = invoice_balance(inv)

        overdue = [i for i in open_inv if i["status"] == "overdue" or i["due_date"] < today]
        overdue_total = sum(i["balance"] for i in overdue)
        open_total = sum(i["balance"] for i in open_inv)
        due_soon = [
            i for i in open_inv
            if today <= i["due_date"] <= (date.today() + timedelta(days=7)).isoformat()
        ]
        paid_30 = conn.execute(
            """
            SELECT COALESCE(SUM(p.amount), 0) AS total
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE i.workspace_id = ? AND p.paid_at >= ?
            """,
            (wid, (date.today() - timedelta(days=30)).isoformat()),
        ).fetchone()["total"]

        aging = {"current": 0.0, "d1_30": 0.0, "d31_60": 0.0, "d60_plus": 0.0}
        for inv in open_inv:
            days = (date.today() - date.fromisoformat(inv["due_date"])).days
            bal = inv["balance"]
            if days <= 0:
                aging["current"] += bal
            elif days <= 30:
                aging["d1_30"] += bal
            elif days <= 60:
                aging["d31_60"] += bal
            else:
                aging["d60_plus"] += bal

        due_reminders = rows_to_list(
            conn.execute(
                """
                SELECT r.*, i.number AS invoice_number, i.amount, i.amount_paid,
                       c.name AS client_name
                FROM reminders r
                JOIN invoices i ON i.id = r.invoice_id
                JOIN clients c ON c.id = i.client_id
                WHERE i.workspace_id = ? AND r.status IN ('due','pending') AND r.scheduled_for <= ?
                ORDER BY r.scheduled_for
                LIMIT 12
                """,
                (wid, today),
            ).fetchall()
        )
        for r in due_reminders:
            r["balance"] = round(r["amount"] - r["amount_paid"], 2)
            if r["scheduled_for"] < today and r["status"] == "pending":
                r["severity"] = "critical"
            elif r["scheduled_for"] <= today:
                r["severity"] = "warning"
            else:
                r["severity"] = "normal"

        activity = rows_to_list(
            conn.execute(
                "SELECT * FROM activity WHERE workspace_id = ? ORDER BY id DESC LIMIT 10",
                (wid,),
            ).fetchall()
        )

        recovered = conn.execute(
            """
            SELECT COUNT(*) AS c FROM invoices
            WHERE workspace_id = ? AND status = 'paid' AND updated_at >= ?
            """,
            (wid, (date.today() - timedelta(days=30)).isoformat()),
        ).fetchone()["c"]

    return jsonify(
        {
            "kpis": {
                "overdue_total": overdue_total,
                "overdue_count": len(overdue),
                "open_total": open_total,
                "open_count": len(open_inv),
                "due_soon_count": len(due_soon),
                "collected_30": paid_30,
                "recovered_invoices_30": recovered,
            },
            "aging": aging,
            "open_invoices": open_inv[:8],
            "due_reminders": due_reminders,
            "activity": activity,
        }
    )


# ---------- Clients ----------

@bp.get("/api/clients")
@login_required
def api_clients():
    q = (request.args.get("q") or "").strip().lower()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        rows = rows_to_list(
            conn.execute(
                """
                SELECT c.*,
                  (SELECT COUNT(*) FROM invoices i WHERE i.client_id = c.id) AS invoice_count,
                  (SELECT COALESCE(SUM(i.amount - i.amount_paid), 0)
                     FROM invoices i
                    WHERE i.client_id = c.id AND i.status IN ('sent','partial','overdue')) AS open_balance
                FROM clients c
                WHERE c.workspace_id = ?
                ORDER BY c.name
                """,
                (wid,),
            ).fetchall()
        )
    if q:
        rows = [
            r for r in rows
            if q in (r["name"] or "").lower()
            or q in (r["company"] or "").lower()
            or q in (r["email"] or "").lower()
        ]
    return jsonify(rows)


@bp.post("/api/clients")
@login_required
def api_create_client():
    data = request.get_json(force=True) or {}
    name = (data.get("name") or "").strip()
    if not name:
        return jsonify({"error": "Name is required"}), 400
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        cur = conn.execute(
            """
            INSERT INTO clients (workspace_id, name, company, email, phone, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (
                wid,
                name,
                (data.get("company") or "").strip() or None,
                (data.get("email") or "").strip() or None,
                (data.get("phone") or "").strip() or None,
                (data.get("notes") or "").strip() or None,
                now,
            ),
        )
        log_activity(conn, "client", f"Added client {name}", "client", cur.lastrowid, workspace_id=wid)
        row = conn.execute(
            "SELECT * FROM clients WHERE id = ? AND workspace_id = ?",
            (cur.lastrowid, wid),
        ).fetchone()
    return jsonify(row_to_dict(row)), 201


@bp.put("/api/clients/<int:client_id>")
@login_required
def api_update_client(client_id: int):
    data = request.get_json(force=True) or {}
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        existing = conn.execute(
            "SELECT * FROM clients WHERE id = ? AND workspace_id = ?",
            (client_id, wid),
        ).fetchone()
        if not existing:
            return jsonify({"error": "Not found"}), 404
        name = (data.get("name") or existing["name"]).strip()
        conn.execute(
            """
            UPDATE clients SET name=?, company=?, email=?, phone=?, notes=?
            WHERE id=? AND workspace_id=?
            """,
            (
                name,
                (data.get("company") if "company" in data else existing["company"]),
                (data.get("email") if "email" in data else existing["email"]),
                (data.get("phone") if "phone" in data else existing["phone"]),
                (data.get("notes") if "notes" in data else existing["notes"]),
                client_id,
                wid,
            ),
        )
        log_activity(conn, "client", f"Updated client {name}", "client", client_id, workspace_id=wid)
        row = conn.execute(
            "SELECT * FROM clients WHERE id = ? AND workspace_id = ?",
            (client_id, wid),
        ).fetchone()
    return jsonify(row_to_dict(row))


@bp.delete("/api/clients/<int:client_id>")
@login_required
def api_delete_client(client_id: int):
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        row = conn.execute(
            "SELECT * FROM clients WHERE id = ? AND workspace_id = ?",
            (client_id, wid),
        ).fetchone()
        if not row:
            return jsonify({"error": "Not found"}), 404
        conn.execute("DELETE FROM clients WHERE id = ? AND workspace_id = ?", (client_id, wid))
        log_activity(conn, "client", f"Deleted client {row['name']}", "client", client_id, workspace_id=wid)
    return jsonify({"ok": True})


# ---------- Invoices ----------

@bp.get("/api/invoices")
@login_required
def api_invoices():
    status = (request.args.get("status") or "").strip().lower()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        for inv in conn.execute(
            "SELECT id FROM invoices WHERE workspace_id=? AND status IN ('sent','partial')",
            (wid,),
        ).fetchall():
            refresh_invoice_status(conn, inv["id"])

        sql = """
            SELECT i.*, c.name AS client_name, c.company AS client_company
            FROM invoices i JOIN clients c ON c.id = i.client_id
            WHERE i.workspace_id = ?
        """
        params: list[Any] = [wid]
        if status and status != "all":
            sql += " AND i.status = ?"
            params.append(status)
        sql += " ORDER BY i.due_date DESC, i.id DESC"
        rows = rows_to_list(conn.execute(sql, params).fetchall())
        for r in rows:
            r["balance"] = invoice_balance(r)
    return jsonify(rows)


@bp.get("/api/invoices/<int:invoice_id>")
@login_required
def api_invoice(invoice_id: int):
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        inv = conn.execute(
            "SELECT id FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if not inv:
            return jsonify({"error": "Not found"}), 404
        refresh_invoice_status(conn, invoice_id)
        data = invoice_detail(conn, invoice_id, wid)
    if not data:
        return jsonify({"error": "Not found"}), 404
    return jsonify(data)


@bp.post("/api/invoices")
@login_required
def api_create_invoice():
    data = request.get_json(force=True) or {}
    client_id = data.get("client_id")
    title = (data.get("title") or "").strip()
    try:
        amount = float(data.get("amount") or 0)
    except (TypeError, ValueError):
        return jsonify({"error": "Invalid amount"}), 400
    if not client_id or not title or amount <= 0:
        return jsonify({"error": "Client, title, and amount are required"}), 400

    issue = data.get("issue_date") or date.today().isoformat()
    due = data.get("due_date") or (date.today() + timedelta(days=14)).isoformat()
    status = data.get("status") or "draft"
    if status not in ("draft", "sent", "partial", "overdue", "paid"):
        status = "draft"
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = require_workspace_id()

    with db_session(db_path()) as conn:
        client = conn.execute(
            "SELECT id FROM clients WHERE id=? AND workspace_id=?",
            (int(client_id), wid),
        ).fetchone()
        if not client:
            return jsonify({"error": "Client not found"}), 404
        settings = get_settings(conn, wid)
        if status in ("sent", "partial", "overdue"):
            blocked = assert_can_add_open_invoice(conn, wid, settings)
            if blocked:
                return jsonify({"error": blocked}), 403
        number = (data.get("number") or "").strip() or next_invoice_number(conn, wid)
        cur = conn.execute(
            """
            INSERT INTO invoices (
                workspace_id, number, client_id, title, amount, amount_paid, currency,
                issue_date, due_date, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                wid,
                number,
                int(client_id),
                title,
                amount,
                data.get("currency") or "USD",
                issue,
                due,
                status,
                (data.get("notes") or "").strip(),
                now,
                now,
            ),
        )
        invoice_id = cur.lastrowid
        if status in ("sent", "partial", "overdue"):
            schedule_reminders_for_invoice(conn, invoice_id, due, force=True)
        log_activity(
            conn, "invoice", f"Created invoice {number}", "invoice", invoice_id, workspace_id=wid
        )
        data_out = invoice_detail(conn, invoice_id, wid)
    return jsonify(data_out), 201


@bp.put("/api/invoices/<int:invoice_id>")
@login_required
def api_update_invoice(invoice_id: int):
    data = request.get_json(force=True) or {}
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        existing = conn.execute(
            "SELECT * FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if not existing:
            return jsonify({"error": "Not found"}), 404
        fields = {
            "title": data.get("title", existing["title"]),
            "amount": float(data.get("amount", existing["amount"])),
            "issue_date": data.get("issue_date", existing["issue_date"]),
            "due_date": data.get("due_date", existing["due_date"]),
            "notes": data.get("notes", existing["notes"]),
            "client_id": int(data.get("client_id", existing["client_id"])),
            "status": data.get("status", existing["status"]),
        }
        client = conn.execute(
            "SELECT id FROM clients WHERE id=? AND workspace_id=?",
            (fields["client_id"], wid),
        ).fetchone()
        if not client:
            return jsonify({"error": "Client not found"}), 404
        becoming_open = (
            fields["status"] in ("sent", "partial", "overdue")
            and existing["status"] == "draft"
        )
        if becoming_open:
            settings = get_settings(conn, wid)
            blocked = assert_can_add_open_invoice(conn, wid, settings)
            if blocked:
                return jsonify({"error": blocked}), 403
        conn.execute(
            """
            UPDATE invoices
            SET title=?, amount=?, issue_date=?, due_date=?, notes=?, client_id=?, status=?, updated_at=?
            WHERE id=? AND workspace_id=?
            """,
            (
                fields["title"],
                fields["amount"],
                fields["issue_date"],
                fields["due_date"],
                fields["notes"],
                fields["client_id"],
                fields["status"],
                now,
                invoice_id,
                wid,
            ),
        )
        if becoming_open:
            schedule_reminders_for_invoice(conn, invoice_id, fields["due_date"], force=True)
        elif fields["due_date"] != existing["due_date"] and fields["status"] != "draft":
            schedule_reminders_for_invoice(conn, invoice_id, fields["due_date"], force=True)
        refresh_invoice_status(conn, invoice_id)
        log_activity(
            conn,
            "invoice",
            f"Updated invoice {existing['number']}",
            "invoice",
            invoice_id,
            workspace_id=wid,
        )
        out = invoice_detail(conn, invoice_id, wid)
    return jsonify(out)


@bp.post("/api/invoices/<int:invoice_id>/send")
@login_required
def api_send_invoice(invoice_id: int):
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        inv = conn.execute(
            "SELECT * FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if not inv:
            return jsonify({"error": "Not found"}), 404
        if inv["status"] == "draft":
            settings = get_settings(conn, wid)
            blocked = assert_can_add_open_invoice(conn, wid, settings)
            if blocked:
                return jsonify({"error": blocked}), 403
        status = "paid" if inv["amount_paid"] >= inv["amount"] else (
            "partial" if inv["amount_paid"] > 0 else "sent"
        )
        if inv["due_date"] < date.today().isoformat() and status != "paid":
            status = "overdue" if inv["amount_paid"] == 0 else "partial"
        conn.execute(
            "UPDATE invoices SET status=?, updated_at=? WHERE id=? AND workspace_id=?",
            (status, now, invoice_id, wid),
        )
        schedule_reminders_for_invoice(conn, invoice_id, inv["due_date"], force=True)
        log_activity(
            conn,
            "invoice",
            f"Marked {inv['number']} as sent — reminders scheduled",
            "invoice",
            invoice_id,
            workspace_id=wid,
        )
        out = invoice_detail(conn, invoice_id, wid)
    return jsonify(out)


@bp.post("/api/invoices/<int:invoice_id>/payments")
@login_required
def api_record_payment(invoice_id: int):
    data = request.get_json(force=True) or {}
    try:
        amount = float(data.get("amount") or 0)
    except (TypeError, ValueError):
        return jsonify({"error": "Invalid amount"}), 400
    if amount <= 0:
        return jsonify({"error": "Amount must be positive"}), 400
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    paid_at = data.get("paid_at") or date.today().isoformat()
    wid = require_workspace_id()

    with db_session(db_path()) as conn:
        inv = conn.execute(
            "SELECT * FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if not inv:
            return jsonify({"error": "Not found"}), 404
        conn.execute(
            """
            INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (
                invoice_id,
                amount,
                (data.get("method") or "Other").strip(),
                paid_at,
                (data.get("note") or "").strip(),
                now,
            ),
        )
        new_paid = round(float(inv["amount_paid"]) + amount, 2)
        conn.execute(
            "UPDATE invoices SET amount_paid=?, updated_at=? WHERE id=? AND workspace_id=?",
            (new_paid, now, invoice_id, wid),
        )
        refresh_invoice_status(conn, invoice_id)
        # cancel pending reminders if paid
        refreshed = conn.execute(
            "SELECT * FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if refreshed["status"] == "paid":
            conn.execute(
                "UPDATE reminders SET status='cancelled' WHERE invoice_id=? AND status IN ('pending','due')",
                (invoice_id,),
            )
        log_activity(
            conn,
            "payment",
            f"Recorded {money(amount)} on {inv['number']}",
            "invoice",
            invoice_id,
            workspace_id=wid,
        )
        out = invoice_detail(conn, invoice_id, wid)
    return jsonify(out)


@bp.delete("/api/invoices/<int:invoice_id>")
@login_required
def api_delete_invoice(invoice_id: int):
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        inv = conn.execute(
            "SELECT * FROM invoices WHERE id=? AND workspace_id=?",
            (invoice_id, wid),
        ).fetchone()
        if not inv:
            return jsonify({"error": "Not found"}), 404
        conn.execute("DELETE FROM invoices WHERE id=? AND workspace_id=?", (invoice_id, wid))
        log_activity(
            conn,
            "invoice",
            f"Deleted invoice {inv['number']}",
            "invoice",
            invoice_id,
            workspace_id=wid,
        )
    return jsonify({"ok": True})


# ---------- Reminders ----------

def _allow_fake_email() -> bool:
    return (os.environ.get("ALLOW_FAKE_EMAIL") or "").strip().lower() in (
        "1",
        "true",
        "yes",
    )


def _email_not_configured_response():
    return (
        jsonify(
            {
                "error": (
                    "Email is not configured. Set RESEND_API_KEY or "
                    "SMTP_HOST/SMTP_USER/MAIL_FROM in .env "
                    "(or ALLOW_FAKE_EMAIL=1 for local testing)."
                )
            }
        ),
        503,
    )


@bp.get("/api/reminders")
@login_required
def api_reminders():
    status = (request.args.get("status") or "queue").strip()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        # promote pending that are due
        today = date.today().isoformat()
        conn.execute(
            """
            UPDATE reminders SET status='due'
            WHERE status='pending' AND scheduled_for <= ?
              AND invoice_id IN (SELECT id FROM invoices WHERE workspace_id=?)
            """,
            (today, wid),
        )
        sql = """
            SELECT r.*, i.number AS invoice_number, i.amount, i.amount_paid, i.due_date,
                   c.name AS client_name, c.email AS client_email, c.phone AS client_phone
            FROM reminders r
            JOIN invoices i ON i.id = r.invoice_id
            JOIN clients c ON c.id = i.client_id
            WHERE i.workspace_id = ?
        """
        params: list[Any] = [wid]
        if status == "queue":
            sql += " AND r.status IN ('due','pending') ORDER BY r.scheduled_for, r.id"
        elif status == "sent":
            sql += " AND r.status = 'sent' ORDER BY r.sent_at DESC, r.id DESC LIMIT 100"
        else:
            sql += " ORDER BY r.scheduled_for DESC LIMIT 200"
        rows = rows_to_list(conn.execute(sql, params).fetchall())
        for r in rows:
            r["balance"] = round(r["amount"] - r["amount_paid"], 2)
            if r["status"] in ("due", "pending") and r["scheduled_for"] < today:
                r["severity"] = "critical"
            elif r["status"] == "due" or r["scheduled_for"] == today:
                r["severity"] = "warning"
            else:
                r["severity"] = "normal"
    return jsonify(rows)


@bp.post("/api/reminders/<int:reminder_id>/send")
@login_required
def api_send_reminder(reminder_id: int):
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        r = conn.execute(
            """
            SELECT r.*, i.number, i.workspace_id, c.name AS client_name, c.email, c.phone
            FROM reminders r
            JOIN invoices i ON i.id = r.invoice_id
            JOIN clients c ON c.id = i.client_id
            WHERE r.id = ? AND i.workspace_id = ?
            """,
            (reminder_id, wid),
        ).fetchone()
        if not r:
            return jsonify({"error": "Not found"}), 404
        if r["status"] == "cancelled":
            return jsonify({"error": "Reminder was cancelled"}), 400

        settings = get_settings(conn, wid)
        channel = (r["channel"] or "email").lower()

        if channel == "sms":
            if not plan_allows_sms(effective_plan(settings)):
                return jsonify({"error": "SMS reminders require the Pro plan."}), 403
            log_activity(
                conn,
                "reminder",
                f"SMS stub (not wired yet) for {r['number']} to {r['phone'] or r['client_name']}",
                "reminder",
                reminder_id,
                workspace_id=wid,
            )
            conn.execute(
                "UPDATE reminders SET status='sent', sent_at=? WHERE id=?",
                (now, reminder_id),
            )
        elif channel == "email":
            if not mail_configured():
                if not _allow_fake_email():
                    return _email_not_configured_response()
            else:
                try:
                    send_email(
                        to=r["email"] or "",
                        subject=r["subject"] or "",
                        body=r["body"] or "",
                        from_name=settings["business_name"] if settings else None,
                    )
                except Exception as exc:  # noqa: BLE001
                    return jsonify({"error": str(exc)}), 502
            conn.execute(
                "UPDATE reminders SET status='sent', sent_at=? WHERE id=?",
                (now, reminder_id),
            )
            dest = r["email"]
            log_activity(
                conn,
                "reminder",
                f"Sent EMAIL reminder for {r['number']} to {dest or r['client_name']}",
                "reminder",
                reminder_id,
                workspace_id=wid,
            )
        else:
            return jsonify({"error": f"Unknown channel: {channel}"}), 400

        row = conn.execute("SELECT * FROM reminders WHERE id = ?", (reminder_id,)).fetchone()
    return jsonify(row_to_dict(row))


@bp.post("/api/reminders/send-due")
@login_required
def api_send_due():
    today = date.today().isoformat()
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    sent = 0
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        settings = get_settings(conn, wid)
        rows = conn.execute(
            """
            SELECT r.id, r.channel, r.subject, r.body, i.number, c.name AS client_name,
                   c.email, c.phone
            FROM reminders r
            JOIN invoices i ON i.id = r.invoice_id
            JOIN clients c ON c.id = i.client_id
            WHERE i.workspace_id = ? AND r.status IN ('due','pending') AND r.scheduled_for <= ?
            """,
            (wid, today),
        ).fetchall()

        needs_email = any((r["channel"] or "").lower() == "email" for r in rows)
        if needs_email and not mail_configured() and not _allow_fake_email():
            return _email_not_configured_response()

        for r in rows:
            channel = (r["channel"] or "email").lower()
            if channel == "sms":
                if not plan_allows_sms(effective_plan(settings)):
                    continue
                log_activity(
                    conn,
                    "reminder",
                    f"SMS stub (not wired yet) for {r['number']} to {r['phone'] or r['client_name']}",
                    "reminder",
                    r["id"],
                    workspace_id=wid,
                )
            elif channel == "email":
                if mail_configured():
                    try:
                        send_email(
                            to=r["email"] or "",
                            subject=r["subject"] or "",
                            body=r["body"] or "",
                            from_name=settings["business_name"] if settings else None,
                        )
                    except Exception as exc:  # noqa: BLE001
                        return jsonify({"error": str(exc), "sent": sent}), 502
                dest = r["email"]
                log_activity(
                    conn,
                    "reminder",
                    f"Sent EMAIL reminder for {r['number']} to {dest or r['client_name']}",
                    "reminder",
                    r["id"],
                    workspace_id=wid,
                )
            else:
                continue
            conn.execute(
                "UPDATE reminders SET status='sent', sent_at=? WHERE id=?",
                (now, r["id"]),
            )
            sent += 1
    return jsonify({"sent": sent})


@bp.post("/api/invoices/<int:invoice_id>/final-notice")
@login_required
def api_final_notice(invoice_id: int):
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"
    today = date.today().isoformat()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        inv = conn.execute(
            """
            SELECT i.*, c.name AS client_name, c.email AS client_email
            FROM invoices i JOIN clients c ON c.id = i.client_id
            WHERE i.id = ? AND i.workspace_id = ?
            """,
            (invoice_id, wid),
        ).fetchone()
        if not inv:
            return jsonify({"error": "Not found"}), 404
        settings = get_settings(conn, wid)
        tmpl = conn.execute(
            "SELECT * FROM templates WHERE workspace_id=? AND name='Final notice' LIMIT 1",
            (wid,),
        ).fetchone()
        ctx = {
            "number": inv["number"],
            "client_name": inv["client_name"],
            "amount_due": money(invoice_balance(dict(inv))),
            "due_date": inv["due_date"],
            "status": inv["status"],
            "business_name": settings["business_name"] if settings else "InPmnt",
        }
        subject = render_template_vars(tmpl["subject"] if tmpl else "Final notice", ctx)
        body = render_template_vars(tmpl["body"] if tmpl else "Final notice", ctx)

        if not mail_configured():
            if not _allow_fake_email():
                return _email_not_configured_response()
        else:
            try:
                send_email(
                    to=inv["client_email"] or "",
                    subject=subject,
                    body=body,
                    from_name=settings["business_name"] if settings else None,
                )
            except Exception as exc:  # noqa: BLE001
                return jsonify({"error": str(exc)}), 502

        cur = conn.execute(
            """
            INSERT INTO reminders (
                invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at
            ) VALUES (?, 'email', ?, 'sent', ?, ?, ?, ?)
            """,
            (invoice_id, today, subject, body, now, now),
        )
        log_activity(
            conn,
            "reminder",
            f"Sent final notice for {inv['number']}",
            "reminder",
            cur.lastrowid,
            workspace_id=wid,
        )
    return jsonify({"ok": True, "subject": subject})


# ---------- Templates & settings ----------

@bp.get("/api/templates")
@login_required
def api_templates():
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        rows = rows_to_list(
            conn.execute(
                "SELECT * FROM templates WHERE workspace_id=? ORDER BY channel, name",
                (wid,),
            ).fetchall()
        )
    return jsonify(rows)


@bp.put("/api/templates/<int:template_id>")
@login_required
def api_update_template(template_id: int):
    data = request.get_json(force=True) or {}
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        existing = conn.execute(
            "SELECT * FROM templates WHERE id=? AND workspace_id=?",
            (template_id, wid),
        ).fetchone()
        if not existing:
            return jsonify({"error": "Not found"}), 404
        conn.execute(
            """
            UPDATE templates SET name=?, channel=?, subject=?, body=?, is_default=?
            WHERE id=? AND workspace_id=?
            """,
            (
                data.get("name", existing["name"]),
                data.get("channel", existing["channel"]),
                data.get("subject", existing["subject"]),
                data.get("body", existing["body"]),
                1 if data.get("is_default", existing["is_default"]) else 0,
                template_id,
                wid,
            ),
        )
        row = conn.execute(
            "SELECT * FROM templates WHERE id=? AND workspace_id=?",
            (template_id, wid),
        ).fetchone()
    return jsonify(row_to_dict(row))


@bp.get("/api/settings")
@login_required
def api_get_settings():
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        settings = row_to_dict(get_settings(conn, wid))
    if settings:
        settings["reminder_offsets"] = json.loads(settings["reminder_offsets"] or "[]")
        settings["plan"] = settings.get("plan") or "trial"
        settings["trial_ends_on"] = settings.get("trial_ends_on")
    return jsonify(settings)


@bp.put("/api/settings")
@login_required
def api_put_settings():
    data = request.get_json(force=True) or {}
    offsets = data.get("reminder_offsets", [-3, 0, 3, 7, 14])
    if isinstance(offsets, str):
        offsets = [int(x.strip()) for x in re.split(r"[,\s]+", offsets) if x.strip()]
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        conn.execute(
            """
            UPDATE settings SET
                business_name=?, owner_name=?, email=?, phone=?, website=?,
                currency=?, reminder_offsets=?, default_channel=?, smtp_enabled=?
            WHERE id=?
            """,
            (
                data.get("business_name"),
                data.get("owner_name"),
                data.get("email"),
                data.get("phone"),
                data.get("website"),
                data.get("currency") or "USD",
                json.dumps(offsets),
                data.get("default_channel") or "email",
                1 if data.get("smtp_enabled") else 0,
                wid,
            ),
        )
        log_activity(conn, "settings", "Updated workspace settings", "settings", wid, workspace_id=wid)
        settings = row_to_dict(get_settings(conn, wid))
    assert settings
    settings["reminder_offsets"] = json.loads(settings["reminder_offsets"] or "[]")
    return jsonify(settings)


@bp.get("/api/activity")
@login_required
def api_activity():
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        rows = rows_to_list(
            conn.execute(
                "SELECT * FROM activity WHERE workspace_id=? ORDER BY id DESC LIMIT 50",
                (wid,),
            ).fetchall()
        )
    return jsonify(rows)


# ---------- Billing (Stripe) ----------

@bp.get("/api/billing/status")
@login_required
def api_billing_status():
    cfg = load_stripe_config()
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        settings = row_to_dict(get_settings(conn, wid))
    return jsonify(
        {
            "enabled": cfg.enabled,
            "publishable_key": cfg.publishable_key,
            "plan": settings.get("plan") if settings else "trial",
            "trial_ends_on": settings.get("trial_ends_on") if settings else None,
            "has_customer": bool(settings and settings.get("stripe_customer_id")),
            "plans": {
                key: {"name": meta["name"], "amount_label": meta["amount_label"]}
                for key, meta in PLANS.items()
            },
        }
    )


@bp.post("/api/billing/checkout")
@login_required
def api_billing_checkout():
    data = request.get_json(force=True) or {}
    plan = (data.get("plan") or "").strip().lower()
    if plan not in PLANS:
        return jsonify({"error": "Unknown plan. Use starter, pro, or annual."}), 400
    cfg = load_stripe_config()
    if not cfg.enabled:
        return jsonify(
            {
                "error": "Stripe is not configured. Add keys and price IDs to .env (see .env.example).",
                "demo": True,
            }
        ), 503

    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        settings = get_settings(conn, wid)
        user = conn.execute("SELECT * FROM users WHERE id = ?", (session["user_id"],)).fetchone()
        try:
            sess = create_checkout_session(
                plan=plan,
                customer_email=user["email"],
                client_reference_id=str(user["id"]),
                customer_id=settings["stripe_customer_id"] if settings else None,
                workspace_id=wid,
            )
        except Exception as exc:  # noqa: BLE001
            return jsonify({"error": str(exc)}), 400
        log_activity(
            conn, "billing", f"Started Stripe checkout for {plan}", "settings", wid, workspace_id=wid
        )
    return jsonify({"url": sess.url, "id": sess.id})


@bp.post("/api/billing/portal")
@login_required
def api_billing_portal():
    cfg = load_stripe_config()
    if not cfg.enabled:
        return jsonify({"error": "Stripe is not configured."}), 503
    wid = require_workspace_id()
    with db_session(db_path()) as conn:
        settings = get_settings(conn, wid)
        if not settings or not settings["stripe_customer_id"]:
            return jsonify({"error": "No Stripe customer yet — subscribe first."}), 400
        try:
            sess = create_portal_session(settings["stripe_customer_id"])
        except Exception as exc:  # noqa: BLE001
            return jsonify({"error": str(exc)}), 400
    return jsonify({"url": sess.url})


@bp.get("/billing/success")
@login_required
def billing_success():
    session_id = request.args.get("session_id")
    cfg = load_stripe_config()
    if session_id and cfg.enabled:
        try:
            import stripe

            stripe.api_key = cfg.secret_key
            checkout = stripe.checkout.Session.retrieve(
                session_id, expand=["subscription", "subscription.items.data.price"]
            )
            _apply_checkout_session(checkout)
        except Exception:  # noqa: BLE001
            pass
    return redirect(url_for("main.app_home") + "#/settings")


@bp.post("/api/billing/webhook")
def stripe_webhook():
    cfg = load_stripe_config()
    payload = request.get_data()
    sig = request.headers.get("Stripe-Signature", "")
    if not cfg.secret_key:
        return jsonify({"error": "Stripe not configured"}), 503

    import stripe

    if not cfg.webhook_secret or "..." in cfg.webhook_secret or len(cfg.webhook_secret) < 20:
        return jsonify({"error": "STRIPE_WEBHOOK_SECRET is required"}), 503

    stripe.api_key = cfg.secret_key
    try:
        event = stripe.Webhook.construct_event(payload, sig, cfg.webhook_secret)
    except Exception as exc:  # noqa: BLE001
        return jsonify({"error": str(exc)}), 400

    etype = event["type"]
    obj = event["data"]["object"]

    if etype == "checkout.session.completed":
        _apply_checkout_session(obj)
    elif etype in ("customer.subscription.updated", "customer.subscription.created"):
        _apply_subscription(obj)
    elif etype == "customer.subscription.deleted":
        with db_session(db_path()) as conn:
            conn.execute(
                """
                UPDATE settings SET plan='trial', stripe_subscription_id=NULL
                WHERE stripe_customer_id=?
                """,
                (obj.get("customer"),),
            )
            log_activity(conn, "billing", "Subscription cancelled — back to trial", "settings", 1)

    return jsonify({"received": True})


def _resolve_settings_id(conn, *, workspace_id=None, user_id=None, customer=None) -> int | None:
    if workspace_id is not None and str(workspace_id).strip():
        try:
            return int(workspace_id)
        except (TypeError, ValueError):
            pass
    if user_id is not None and str(user_id).strip():
        row = conn.execute(
            "SELECT workspace_id FROM users WHERE id = ?", (int(user_id),)
        ).fetchone()
        if row and row["workspace_id"] is not None:
            return int(row["workspace_id"])
    if customer:
        row = conn.execute(
            "SELECT id FROM settings WHERE stripe_customer_id = ?", (customer,)
        ).fetchone()
        if row:
            return int(row["id"])
    return None


def _apply_checkout_session(checkout) -> None:
    """checkout may be Stripe object or dict-like."""
    customer = _get(checkout, "customer")
    subscription = _get(checkout, "subscription")
    sub_id = subscription if isinstance(subscription, str) else _get(subscription, "id")
    plan = None
    meta = _get(checkout, "metadata") or {}
    if isinstance(meta, dict):
        plan = meta.get("plan")
    if not plan and subscription and not isinstance(subscription, str):
        plan = _plan_from_subscription(subscription)
    if not plan:
        plan = "starter"
    user_id = meta.get("user_id") if isinstance(meta, dict) else None
    workspace_id = meta.get("workspace_id") if isinstance(meta, dict) else None
    if not user_id:
        user_id = _get(checkout, "client_reference_id")
    with db_session(db_path()) as conn:
        sid = _resolve_settings_id(
            conn, workspace_id=workspace_id, user_id=user_id, customer=customer
        )
        if sid is None:
            return
        conn.execute(
            """
            UPDATE settings
            SET plan=?, stripe_customer_id=COALESCE(?, stripe_customer_id),
                stripe_subscription_id=COALESCE(?, stripe_subscription_id)
            WHERE id=?
            """,
            (plan, customer, sub_id, sid),
        )
        log_activity(
            conn,
            "billing",
            f"Subscribed to {plan} via Stripe",
            "settings",
            sid,
            workspace_id=sid,
        )


def _apply_subscription(sub) -> None:
    customer = _get(sub, "customer")
    sub_id = _get(sub, "id")
    plan = _plan_from_subscription(sub) or "starter"
    status = _get(sub, "status")
    meta = _get(sub, "metadata") or {}
    user_id = meta.get("user_id") if isinstance(meta, dict) else None
    workspace_id = meta.get("workspace_id") if isinstance(meta, dict) else None
    with db_session(db_path()) as conn:
        if status in ("canceled", "unpaid", "incomplete_expired"):
            conn.execute(
                """
                UPDATE settings SET plan='trial', stripe_subscription_id=NULL
                WHERE stripe_customer_id=?
                """,
                (customer,),
            )
        else:
            sid = _resolve_settings_id(
                conn, workspace_id=workspace_id, user_id=user_id, customer=customer
            )
            if sid is None:
                return
            conn.execute(
                """
                UPDATE settings
                SET plan=?, stripe_customer_id=?, stripe_subscription_id=?
                WHERE id=?
                """,
                (plan, customer, sub_id, sid),
            )
        log_activity(
            conn,
            "billing",
            f"Subscription updated → {plan} ({status})",
            "settings",
            1,
            workspace_id=workspace_id or 1,
        )


def _plan_from_subscription(sub) -> str | None:
    items = _get(sub, "items")
    data = _get(items, "data") if items else None
    if not data:
        meta = _get(sub, "metadata") or {}
        if isinstance(meta, dict):
            return meta.get("plan")
        return None
    first = data[0]
    price = _get(first, "price")
    price_id = price if isinstance(price, str) else _get(price, "id")
    return plan_from_price_id(price_id) or (_get(_get(sub, "metadata") or {}, "plan") if isinstance(_get(sub, "metadata"), dict) else None)


def _get(obj, key, default=None):
    if obj is None:
        return default
    if isinstance(obj, dict):
        return obj.get(key, default)
    try:
        return obj[key]
    except Exception:  # noqa: BLE001
        return getattr(obj, key, default)
