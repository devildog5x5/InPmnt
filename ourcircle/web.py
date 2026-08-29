"""OurCircle — pause, ask family, then pay. Flask app."""
from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any

from flask import (
    Flask,
    abort,
    flash,
    redirect,
    render_template,
    request,
    send_from_directory,
    session,
    url_for,
)
from werkzeug.utils import secure_filename

from analyze import CORE_RULE, DISCLAIMER, GUIDANCE, analyze
from auth import (
    consume_recovery,
    group_secret,
    hash_list,
    new_recovery_codes,
    new_reset_token,
    new_secret,
    otpauth_uri,
    totp_on,
    verify_totp,
)
from billing import (
    PLAN_LABELS,
    construct_event,
    create_checkout_session,
    create_portal_session,
    load_stripe_config,
    plan_from_price_id,
    retrieve_checkout,
)
from database import (
    DATA,
    accept_invite,
    authenticate,
    create_household,
    household_members,
    init_db,
    invite_member,
    now,
    session as db_session,
    trusted_list,
)
from mail import mail_configured, send_email
from support_chat import handle_chat, openai_configured
from werkzeug.security import check_password_hash, generate_password_hash

ROOT = Path(__file__).resolve().parent
UPLOADS = DATA / "uploads"
ALLOWED_SHOT = {".png", ".jpg", ".jpeg", ".webp", ".gif"}
DEFAULT_SITE_URL = "https://familyshieldpro.com"
PUBLIC_PATHS = ("/", "/signup", "/login", "/forgot")
PRIVATE_PREFIXES = (
    "/home",
    "/circle",
    "/trusted",
    "/checks",
    "/uploads",
    "/join",
    "/billing",
    "/report",
    "/account",
    "/logout",
    "/support",
)


def site_url() -> str:
    return (os.environ.get("OURCIRCLE_SITE_URL") or DEFAULT_SITE_URL).rstrip("/")


def product_version() -> str:
    vp = ROOT / "VERSION"
    if vp.is_file():
        return vp.read_text(encoding="utf-8").strip() or "0.0.0"
    return "0.0.0"

PLANS = [
    {
        "id": "monthly",
        "name": "Family monthly",
        "price": "$14.99/month",
        "detail": "Up to five people in one circle. Pause, trusted list, and call-me-before-I-pay.",
        "featured": False,
    },
    {
        "id": "yearly",
        "name": "Family yearly",
        "price": "$119.99/year",
        "detail": "Same circle. Pay once a year — about $10 a month. Best for families.",
        "featured": True,
    },
]


def create_app() -> Flask:
    init_db()
    app = Flask(
        __name__,
        template_folder=str(ROOT / "templates"),
        static_folder=str(ROOT / "static"),
    )
    secret = (os.environ.get("OURCIRCLE_SECRET") or os.environ.get("FLASK_SECRET_KEY") or "ourcircle-dev").strip()
    app.config["SECRET_KEY"] = secret
    app.config["MAX_CONTENT_LENGTH"] = 8 * 1024 * 1024
    app.config["TEMPLATES_AUTO_RELOAD"] = True

    @app.context_processor
    def inject():
        return {
            "core_rule": CORE_RULE,
            "disclaimer": DISCLAIMER,
            "guidance": GUIDANCE,
            "user_name": session.get("name"),
            "site_home": site_url(),
            "app_version": product_version(),
            "stripe_enabled": load_stripe_config().enabled,
        }

    @app.get("/robots.txt")
    def robots_txt():
        lines = [
            "User-agent: *",
            "Allow: /",
            "Allow: /signup",
            "Allow: /login",
            "Allow: /forgot",
        ]
        for path in PRIVATE_PREFIXES:
            lines.append(f"Disallow: {path}")
        lines.extend(
            [
                "",
                f"Host: {site_url().replace('https://', '').replace('http://', '')}",
                f"Sitemap: {site_url()}/sitemap.xml",
                "",
            ]
        )
        return app.response_class("\n".join(lines), mimetype="text/plain; charset=utf-8")

    @app.get("/sitemap.xml")
    def sitemap_xml():
        lastmod = now()[:10]
        urls = []
        for path in PUBLIC_PATHS:
            loc = f"{site_url()}/" if path == "/" else f"{site_url()}{path}"
            priority = "1.0" if path == "/" else ("0.9" if path == "/signup" else "0.8")
            changefreq = "weekly" if path != "/login" else "monthly"
            if path == "/login":
                priority = "0.6"
            urls.append(
                "  <url>\n"
                f"    <loc>{loc}</loc>\n"
                f"    <lastmod>{lastmod}</lastmod>\n"
                f"    <changefreq>{changefreq}</changefreq>\n"
                f"    <priority>{priority}</priority>\n"
                "  </url>"
            )
        body = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
            + "\n".join(urls)
            + "\n</urlset>\n"
        )
        return app.response_class(body, mimetype="application/xml; charset=utf-8")

    @app.get("/healthz")
    def healthz():
        return {
            "ok": True,
            "service": "familyshieldpro",
            "product": "Family Shield Pro",
            "app": "OurCircle",
            "version": product_version(),
            "not": "InPmnt",
            "stripe": load_stripe_config().enabled,
            "mail": mail_configured(),
            "openai": openai_configured(),
        }

    def current_user():
        uid = session.get("user_id")
        hid = session.get("household_id")
        if not uid or not hid:
            return None
        return {"id": uid, "household_id": hid, "name": session.get("name"), "email": session.get("email")}

    def login_required():
        if not current_user():
            return redirect(url_for("login", next=request.path))
        return None

    @app.get("/")
    def landing():
        if current_user():
            return redirect(url_for("home"))
        return render_template("landing.html", plans=PLANS)

    @app.post("/support/chat")
    def support_chat():
        data = request.get_json(silent=True) or {}
        message = (data.get("message") or request.form.get("message") or "").strip()
        history = data.get("history") if isinstance(data.get("history"), list) else []
        n = int(session.get("support_chat_n") or 0)
        started = int(session.get("support_chat_t") or 0)
        now_ts = int(time.time())
        if started and now_ts - started > 3600:
            n = 0
            started = now_ts
        if not started:
            started = now_ts
        if n >= 30:
            return {
                "reply": "Please email CustomerService@FamilyShieldPro.com — this chat has a short hourly limit.",
                "source": "limit",
            }, 429
        session["support_chat_n"] = n + 1
        session["support_chat_t"] = started
        reply, source = handle_chat(message, history)
        return {"reply": reply, "source": source}

    @app.route("/signup", methods=["GET", "POST"])
    def signup():
        if request.method == "GET":
            return render_template("signup.html")
        name = (request.form.get("name") or "").strip()
        household = (request.form.get("household") or "").strip()
        email = (request.form.get("email") or "").strip().lower()
        password = request.form.get("password") or ""
        if not name or "@" not in email or len(password) < 8:
            flash("Name, email, and an 8+ character password are required.", "error")
            return render_template("signup.html")
        with db_session() as conn:
            taken = conn.execute("SELECT id FROM users WHERE lower(email)=?", (email,)).fetchone()
            if taken:
                flash("That email already has a login. Sign in instead.", "error")
                return redirect(url_for("login"))
            hid = create_household(conn, name=household or f"{name}'s circle", owner_name=name, email=email, password=password)
            user = conn.execute("SELECT * FROM users WHERE lower(email)=?", (email,)).fetchone()
        session["user_id"] = user["id"]
        session["household_id"] = hid
        session["name"] = user["name"]
        session["email"] = user["email"]
        flash("Welcome. Add two trusted contacts, then invite someone who will pick up the phone.", "ok")
        return redirect(url_for("home"))

    @app.route("/login", methods=["GET", "POST"])
    def login():
        if request.method == "GET":
            return render_template("login.html")
        email = request.form.get("email") or ""
        password = request.form.get("password") or ""
        with db_session() as conn:
            user = authenticate(conn, email, password)
        if not user:
            flash("Email or password did not match.", "error")
            return render_template("login.html")
        if totp_on(user):
            session.clear()
            session["pending_2fa"] = user["id"]
            session["pending_2fa_tries"] = 0
            session["pending_next"] = request.args.get("next") or url_for("home")
            return redirect(url_for("login_2fa"))
        session["user_id"] = user["id"]
        session["household_id"] = user["household_id"]
        session["name"] = user["name"]
        session["email"] = user["email"]
        return redirect(request.args.get("next") or url_for("home"))

    @app.get("/logout")
    def logout():
        session.clear()
        return redirect(url_for("landing"))

    def _user_by_id(uid: int) -> dict[str, Any] | None:
        with db_session() as conn:
            row = conn.execute("SELECT * FROM users WHERE id=?", (uid,)).fetchone()
        return dict(row) if row else None

    def _verify_second(user: dict[str, Any], code: str, recovery: str) -> bool:
        secret = (user.get("totp_secret") or "").strip()
        if code and secret and verify_totp(secret, code):
            return True
        if not recovery:
            return False
        nxt = consume_recovery(user.get("recovery_codes") or "", recovery)
        if nxt is None:
            return False
        with db_session() as conn:
            conn.execute("UPDATE users SET recovery_codes=? WHERE id=?", (nxt, user["id"]))
        user["recovery_codes"] = nxt
        return True

    @app.route("/login/2fa", methods=["GET", "POST"])
    def login_2fa():
        uid = int(session.get("pending_2fa") or 0)
        if uid < 1:
            return redirect(url_for("login"))
        if request.method == "GET":
            return render_template("login_2fa.html")
        user = _user_by_id(uid)
        if not user:
            session.clear()
            return redirect(url_for("login"))
        tries = int(session.get("pending_2fa_tries") or 0)
        if tries >= 8:
            session.clear()
            flash("Too many codes. Sign in again.", "error")
            return redirect(url_for("login"))
        code = (request.form.get("code") or "").strip()
        recovery = (request.form.get("recovery_code") or "").strip()
        if not _verify_second(user, code, recovery):
            session["pending_2fa_tries"] = tries + 1
            flash("That code did not match.", "error")
            return redirect(url_for("login_2fa"))
        nxt = session.get("pending_next") or url_for("home")
        session.clear()
        session["user_id"] = user["id"]
        session["household_id"] = user["household_id"]
        session["name"] = user["name"]
        session["email"] = user["email"]
        return redirect(nxt if isinstance(nxt, str) and nxt.startswith("/") else url_for("home"))

    @app.route("/forgot", methods=["GET", "POST"])
    def forgot():
        generic = "If that email is on a circle, we sent reset instructions. Check spam. You can also use a recovery code on this page."
        if request.method == "GET":
            return render_template("forgot.html")
        email = (request.form.get("email") or "").lower().strip()
        if "@" in email:
            with db_session() as conn:
                user = conn.execute("SELECT * FROM users WHERE lower(email)=?", (email,)).fetchone()
                if user:
                    raw, token_hash = new_reset_token()
                    conn.execute("DELETE FROM password_resets WHERE user_id=?", (user["id"],))
                    from datetime import datetime, timedelta, timezone

                    exp = (datetime.now(timezone.utc) + timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M:%SZ")
                    conn.execute(
                        "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)",
                        (user["id"], token_hash, exp, now()),
                    )
                    link = site_url() + "/reset/" + raw
                    body = (
                        "Someone asked to reset the Family Shield Pro password for this email.\n\n"
                        f"Open this link within one hour:\n{link}\n\n"
                        "If you did not ask, ignore this message.\n"
                    )
                    try:
                        send_email(
                            to=user["email"],
                            subject="Reset your Family Shield Pro password",
                            body=body,
                        )
                    except Exception:
                        pass
        flash(generic, "ok")
        return redirect(url_for("forgot"))

    @app.post("/forgot/code")
    def forgot_code():
        email = (request.form.get("email") or "").lower().strip()
        recovery = (request.form.get("recovery_code") or "").strip()
        password = request.form.get("password") or ""
        generic = "If that email and recovery code matched, the password is updated. Sign in."
        if "@" in email and recovery and len(password) >= 8:
            with db_session() as conn:
                user = conn.execute("SELECT * FROM users WHERE lower(email)=?", (email,)).fetchone()
                if user:
                    nxt = consume_recovery(user["recovery_codes"] or "", recovery)
                    if nxt is not None:
                        conn.execute(
                            "UPDATE users SET password_hash=?, recovery_codes=? WHERE id=?",
                            (generate_password_hash(password), nxt, user["id"]),
                        )
                        conn.execute("DELETE FROM password_resets WHERE user_id=?", (user["id"],))
        flash(generic, "ok")
        return redirect(url_for("login"))

    @app.route("/reset/<token>", methods=["GET", "POST"])
    def reset_password(token: str):
        import hashlib

        token_hash = hashlib.sha256(token.encode("utf-8")).hexdigest()
        with db_session() as conn:
            row = conn.execute(
                "SELECT * FROM password_resets WHERE token_hash=? AND expires_at >= ?",
                (token_hash, now()),
            ).fetchone()
        if not row:
            flash("That reset link is invalid or expired. Request a new one.", "error")
            return redirect(url_for("forgot"))
        if request.method == "GET":
            return render_template("reset.html")
        password = request.form.get("password") or ""
        if len(password) < 8:
            flash("Use at least 8 characters.", "error")
            return redirect(url_for("reset_password", token=token))
        with db_session() as conn:
            conn.execute(
                "UPDATE users SET password_hash=? WHERE id=?",
                (generate_password_hash(password), row["user_id"]),
            )
            conn.execute("DELETE FROM password_resets WHERE user_id=?", (row["user_id"],))
        flash("Password saved. Sign in. If 2FA is on, you still need the authenticator.", "ok")
        return redirect(url_for("login"))

    @app.get("/account")
    def account():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        user = _user_by_id(int(u["id"]))
        codes = session.pop("show_recovery", [])
        return render_template(
            "account.html",
            totp_on=totp_on(user),
            recovery_codes=codes if isinstance(codes, list) else [],
        )

    @app.post("/account/password")
    def account_password():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        user = _user_by_id(int(u["id"]))
        current = request.form.get("current_password") or ""
        password = request.form.get("password") or ""
        if not user or not check_password_hash(user["password_hash"], current):
            flash("Current password did not match.", "error")
            return redirect(url_for("account"))
        if len(password) < 8:
            flash("Use at least 8 characters.", "error")
            return redirect(url_for("account"))
        with db_session() as conn:
            conn.execute(
                "UPDATE users SET password_hash=? WHERE id=?",
                (generate_password_hash(password), user["id"]),
            )
        flash("Password updated.", "ok")
        return redirect(url_for("account"))

    @app.route("/account/2fa/setup", methods=["GET", "POST"])
    def account_2fa_setup():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        user = _user_by_id(int(u["id"]))
        if totp_on(user):
            return redirect(url_for("account"))
        if request.method == "POST" and (request.form.get("new_key") or "") == "1":
            session.pop("totp_pending_secret", None)
        if not session.get("totp_pending_secret"):
            session["totp_pending_secret"] = new_secret()
        secret = session["totp_pending_secret"]
        return render_template(
            "account_2fa_setup.html",
            secret_grouped=group_secret(secret),
            otpauth=otpauth_uri(u["email"], secret),
        )

    @app.post("/account/2fa/enable")
    def account_2fa_enable():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        secret = session.get("totp_pending_secret") or ""
        code = (request.form.get("code") or "").strip()
        if not secret or not verify_totp(secret, code):
            flash("That code did not match. Scan the key again and retry.", "error")
            return redirect(url_for("account_2fa_setup"))
        codes = new_recovery_codes()
        with db_session() as conn:
            conn.execute(
                "UPDATE users SET totp_secret=?, totp_enabled=1, recovery_codes=? WHERE id=?",
                (secret, hash_list(codes), u["id"]),
            )
        session.pop("totp_pending_secret", None)
        session["show_recovery"] = codes
        flash("Two-factor authentication is on. Save the recovery codes.", "ok")
        return redirect(url_for("account"))

    @app.post("/account/2fa/disable")
    def account_2fa_disable():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        user = _user_by_id(int(u["id"]))
        code = (request.form.get("code") or "").strip()
        if not user or not _verify_second(user, code, code):
            flash("That code did not match.", "error")
            return redirect(url_for("account"))
        with db_session() as conn:
            conn.execute(
                "UPDATE users SET totp_secret=NULL, totp_enabled=0, recovery_codes=NULL WHERE id=?",
                (u["id"],),
            )
        flash("Two-factor authentication is off.", "ok")
        return redirect(url_for("account"))

    @app.post("/account/2fa/recovery")
    def account_2fa_recovery():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        user = _user_by_id(int(u["id"]))
        code = (request.form.get("code") or "").strip()
        secret = (user.get("totp_secret") or "") if user else ""
        if not user or not secret or not verify_totp(secret, code):
            flash("That authenticator code did not match.", "error")
            return redirect(url_for("account"))
        codes = new_recovery_codes()
        with db_session() as conn:
            conn.execute("UPDATE users SET recovery_codes=? WHERE id=?", (hash_list(codes), u["id"]))
        session["show_recovery"] = codes
        flash("New recovery codes — save them now.", "ok")
        return redirect(url_for("account"))

    @app.get("/home")
    def home():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        with db_session() as conn:
            members, pending = household_members(conn, u["household_id"])
            trusted = trusted_list(conn, u["household_id"])
            checks = [dict(r) for r in conn.execute(
                "SELECT id, kind, risk, created_at, raw_text, phone, url FROM checks WHERE household_id=? ORDER BY id DESC LIMIT 8",
                (u["household_id"],),
            ).fetchall()]
            alerts = [dict(r) for r in conn.execute(
                "SELECT * FROM alerts WHERE household_id=? ORDER BY id DESC LIMIT 5",
                (u["household_id"],),
            ).fetchall()]
        return render_template(
            "home.html",
            members=members,
            pending=pending,
            trusted=trusted,
            checks=checks,
            alerts=alerts,
        )

    @app.post("/check")
    def create_check():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        text = (request.form.get("text") or "").strip()
        phone = (request.form.get("phone") or "").strip()
        url = (request.form.get("url") or "").strip()
        shot_name = ""
        file = request.files.get("screenshot")
        if file and file.filename:
            ext = Path(file.filename).suffix.lower()
            if ext not in ALLOWED_SHOT:
                flash("Please upload a PNG, JPG, WEBP, or GIF screenshot.", "error")
                return redirect(url_for("home"))
            shot_name = f"{u['household_id']}-{u['id']}-{now().replace(':','')}-{secure_filename(file.filename)}"
            file.save(UPLOADS / shot_name)
            if not text:
                text = "(Screenshot uploaded — describe what it says if you can.)"
        if not text and not phone and not url:
            flash("Paste the message, a phone number, a website, or upload a screenshot.", "error")
            return redirect(url_for("home"))
        with db_session() as conn:
            trusted = trusted_list(conn, u["household_id"])
            report = analyze(text=text, phone=phone, url=url, trusted=trusted)
            kind = "screenshot" if shot_name else ("phone" if phone and not text else "message")
            cur = conn.execute(
                """
                INSERT INTO checks (household_id, user_id, kind, raw_text, phone, url, screenshot, risk, report_json, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                """,
                (
                    u["household_id"],
                    u["id"],
                    kind,
                    text,
                    phone,
                    url,
                    shot_name,
                    report["level"],
                    json.dumps(report),
                    now(),
                ),
            )
            cid = cur.lastrowid
        return redirect(url_for("show_check", check_id=cid))

    @app.get("/checks/<int:check_id>")
    def show_check(check_id: int):
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        with db_session() as conn:
            row = conn.execute(
                "SELECT * FROM checks WHERE id=? AND household_id=?",
                (check_id, u["household_id"]),
            ).fetchone()
            if not row:
                abort(404)
            members, _pending = household_members(conn, u["household_id"])
            reviews = [dict(r) for r in conn.execute(
                "SELECT * FROM reviews WHERE check_id=? ORDER BY id DESC",
                (check_id,),
            ).fetchall()]
        report = json.loads(row["report_json"])
        return render_template("check.html", item=dict(row), report=report, members=members, reviews=reviews)

    @app.post("/checks/<int:check_id>/review")
    def ask_review(check_id: int):
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        comment = (request.form.get("comment") or "Please look at this with me before I do anything.").strip()
        with db_session() as conn:
            row = conn.execute(
                "SELECT id FROM checks WHERE id=? AND household_id=?",
                (check_id, u["household_id"]),
            ).fetchone()
            if not row:
                abort(404)
            conn.execute(
                """
                INSERT INTO reviews (check_id, household_id, requester_id, comment, status, created_at)
                VALUES (?,?,?,?, 'asked', ?)
                """,
                (check_id, u["household_id"], u["id"], comment, now()),
            )
        flash("Your circle can see this review request. Call them too if it feels urgent.", "ok")
        return redirect(url_for("show_check", check_id=check_id))

    @app.post("/checks/<int:check_id>/review/reply")
    def reply_review(check_id: int):
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        comment = (request.form.get("reply") or "").strip()
        status = request.form.get("status") or "looked"
        if status not in ("looked", "scam_likely", "wait", "call_me"):
            status = "looked"
        if not comment:
            flash("Add a short note for your family member.", "error")
            return redirect(url_for("show_check", check_id=check_id))
        with db_session() as conn:
            row = conn.execute(
                "SELECT id FROM checks WHERE id=? AND household_id=?",
                (check_id, u["household_id"]),
            ).fetchone()
            if not row:
                abort(404)
            conn.execute(
                """
                INSERT INTO reviews (check_id, household_id, requester_id, comment, status, created_at)
                VALUES (?,?,?,?,?,?)
                """,
                (check_id, u["household_id"], u["id"], comment, status, now()),
            )
        flash("Your note is on this check for the whole circle.", "ok")
        return redirect(url_for("show_check", check_id=check_id))

    @app.post("/checks/<int:check_id>/alert")
    def send_alert(check_id: int):
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        with db_session() as conn:
            row = conn.execute(
                "SELECT * FROM checks WHERE id=? AND household_id=?",
                (check_id, u["household_id"]),
            ).fetchone()
            if not row:
                abort(404)
            members, _p = household_members(conn, u["household_id"])
            names = ", ".join(m["name"] for m in members)
            msg = (
                f"PLEASE CALL {u['name']} BEFORE THEY PAY. "
                f"They asked the circle ({names}) to stop a payment or information request. "
                f"Open OurCircle and look at check #{check_id}."
            )
            conn.execute(
                "INSERT INTO alerts (check_id, household_id, user_id, message, created_at) VALUES (?,?,?,?,?)",
                (check_id, u["household_id"], u["id"], msg, now()),
            )
        flash("Urgent alert is on the circle home. Call them by voice if you can — do not rely on a banner alone.", "ok")
        return redirect(url_for("show_check", check_id=check_id))

    @app.get("/uploads/<path:name>")
    def uploaded(name: str):
        gate = login_required()
        if gate:
            return gate
        return send_from_directory(UPLOADS, name)

    @app.route("/circle", methods=["GET", "POST"])
    def circle():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        if request.method == "POST":
            email = (request.form.get("email") or "").strip()
            name = (request.form.get("name") or "").strip()
            try:
                with db_session() as conn:
                    inv = invite_member(conn, u["household_id"], email, name)
                join = url_for("join", token=inv["token"], _external=True)
                flash(f"Invite created for {inv['email']}. Share this join link: {join}", "ok")
            except ValueError as exc:
                flash(str(exc), "error")
            return redirect(url_for("circle"))
        with db_session() as conn:
            members, pending = household_members(conn, u["household_id"])
            alerts = [dict(r) for r in conn.execute(
                "SELECT * FROM alerts WHERE household_id=? ORDER BY id DESC LIMIT 12",
                (u["household_id"],),
            ).fetchall()]
        return render_template("circle.html", members=members, pending=pending, alerts=alerts)

    @app.route("/join/<token>", methods=["GET", "POST"])
    def join(token: str):
        if request.method == "GET":
            with db_session() as conn:
                inv = conn.execute("SELECT * FROM invitations WHERE token=? AND status='pending'", (token,)).fetchone()
            if not inv:
                flash("That invite is expired or already used.", "error")
                return redirect(url_for("login"))
            return render_template("join.html", invite=dict(inv), token=token)
        name = (request.form.get("name") or "").strip()
        password = request.form.get("password") or ""
        if not name or len(password) < 8:
            flash("Name and an 8+ character password are required.", "error")
            return render_template("join.html", invite={"email": ""}, token=token)
        try:
            with db_session() as conn:
                user = accept_invite(conn, token, name, password)
        except ValueError as exc:
            flash(str(exc), "error")
            return redirect(url_for("login"))
        session["user_id"] = user["id"]
        session["household_id"] = user["household_id"]
        session["name"] = user["name"]
        session["email"] = user["email"]
        flash("You are in the circle. If someone asks you to look, pause with them — do not rush.", "ok")
        return redirect(url_for("home"))

    @app.route("/trusted", methods=["GET", "POST"])
    def trusted():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        if request.method == "POST":
            kind = (request.form.get("kind") or "other").strip()
            if kind not in ("bank", "doctor", "insurer", "utility", "family", "other"):
                kind = "other"
            name = (request.form.get("name") or "").strip()
            if not name:
                flash("Give this contact a name you will recognize.", "error")
                return redirect(url_for("trusted"))
            with db_session() as conn:
                conn.execute(
                    """
                    INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at)
                    VALUES (?,?,?,?,?,?,?)
                    """,
                    (
                        u["household_id"],
                        kind,
                        name,
                        (request.form.get("phone") or "").strip(),
                        (request.form.get("website") or "").strip(),
                        (request.form.get("notes") or "").strip(),
                        now(),
                    ),
                )
            flash("Saved on your protected list. Prefer numbers from statements and cards, not from unexpected texts.", "ok")
            return redirect(url_for("trusted"))
        with db_session() as conn:
            rows = trusted_list(conn, u["household_id"])
        return render_template("trusted.html", rows=rows)

    @app.post("/trusted/<int:contact_id>/delete")
    def delete_trusted(contact_id: int):
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        with db_session() as conn:
            conn.execute(
                "DELETE FROM trusted_contacts WHERE id=? AND household_id=?",
                (contact_id, u["household_id"]),
            )
        flash("Removed from the trusted list.", "ok")
        return redirect(url_for("trusted"))

    @app.get("/report")
    def report():
        gate = login_required()
        if gate:
            return gate
        return render_template("report.html")

    @app.get("/billing")
    def billing():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        with db_session() as conn:
            hh = conn.execute("SELECT * FROM households WHERE id=?", (u["household_id"],)).fetchone()
        cfg = load_stripe_config()
        return render_template(
            "billing.html",
            plans=PLANS,
            household=dict(hh) if hh else {},
            stripe_enabled=cfg.enabled,
        )

    @app.post("/billing/choose")
    def choose_plan():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        plan = (request.form.get("plan") or "").strip()
        if plan not in ("monthly", "yearly"):
            flash("Choose Family monthly or Family yearly.", "error")
            return redirect(url_for("billing"))
        cfg = load_stripe_config()
        if cfg.enabled:
            with db_session() as conn:
                hh = conn.execute("SELECT * FROM households WHERE id=?", (u["household_id"],)).fetchone()
            try:
                sess = create_checkout_session(
                    plan=plan,
                    customer_email=u["email"],
                    household_id=u["household_id"],
                    user_id=u["id"],
                    customer_id=(dict(hh).get("stripe_customer_id") if hh else None) or None,
                )
            except Exception as exc:
                flash(f"Stripe could not start checkout: {exc}", "error")
                return redirect(url_for("billing"))
            url = sess.get("url") or ""
            if not url:
                flash("Stripe did not return a checkout URL.", "error")
                return redirect(url_for("billing"))
            return redirect(url)
        with db_session() as conn:
            conn.execute(
                "UPDATE households SET plan=?, founding=0 WHERE id=?",
                (plan, u["household_id"]),
            )
        label = PLAN_LABELS[plan]
        flash(f"This circle is on Family {plan} ({label}). Add Stripe keys to .env to charge a card.", "ok")
        return redirect(url_for("billing"))

    @app.post("/billing/portal")
    def billing_portal():
        gate = login_required()
        if gate:
            return gate
        u = current_user()
        cfg = load_stripe_config()
        if not cfg.enabled:
            flash("Stripe is not configured yet. Add keys to .env (see STRIPE.md).", "error")
            return redirect(url_for("billing"))
        with db_session() as conn:
            hh = conn.execute("SELECT * FROM households WHERE id=?", (u["household_id"],)).fetchone()
        cid = (dict(hh).get("stripe_customer_id") if hh else None) or ""
        if not cid:
            flash("No Stripe customer yet — choose a plan and pay first.", "error")
            return redirect(url_for("billing"))
        try:
            sess = create_portal_session(cid)
        except Exception as exc:
            flash(f"Stripe portal: {exc}", "error")
            return redirect(url_for("billing"))
        url = sess.get("url") or ""
        if not url:
            flash("Stripe did not return a portal URL. Enable Customer portal in the Stripe Dashboard.", "error")
            return redirect(url_for("billing"))
        return redirect(url)

    @app.get("/billing/success")
    def billing_success():
        gate = login_required()
        if gate:
            return gate
        session_id = (request.args.get("session_id") or "").strip()
        cfg = load_stripe_config()
        if session_id and cfg.enabled:
            try:
                checkout = retrieve_checkout(session_id)
                _apply_checkout_session(checkout)
                flash("Payment received. This circle is on a paid Family plan.", "ok")
            except Exception:
                flash("Paid, but we could not read the Stripe session yet. The webhook will finish this.", "error")
        return redirect(url_for("billing"))

    @app.post("/billing/webhook")
    def stripe_webhook():
        cfg = load_stripe_config()
        payload = request.get_data(as_text=True) or ""
        sig = request.headers.get("Stripe-Signature") or ""
        if not cfg.secret_key or len(cfg.secret_key) < 20 or "..." in cfg.secret_key:
            return {"error": "Stripe not configured"}, 503
        wh = cfg.webhook_secret
        if not wh or "..." in wh or not wh.startswith("whsec_") or len(wh) < 20:
            return {"error": "STRIPE_WEBHOOK_SECRET is required"}, 503
        try:
            event = construct_event(payload, sig, wh)
        except Exception as exc:
            return {"error": str(exc)}, 400
        etype = event.get("type") or ""
        obj = (event.get("data") or {}).get("object") or {}
        if not isinstance(obj, dict):
            obj = {}
        if etype == "checkout.session.completed":
            _apply_checkout_session(obj)
        elif etype in ("customer.subscription.updated", "customer.subscription.created"):
            _apply_subscription(obj)
        elif etype == "customer.subscription.deleted":
            with db_session() as conn:
                conn.execute(
                    "UPDATE households SET stripe_subscription_id=NULL, stripe_status='canceled' WHERE stripe_customer_id=?",
                    (obj.get("customer"),),
                )
        return {"received": True}

    def _household_id_from(meta: dict, reference: Any, customer: Any) -> int | None:
        hid = meta.get("household_id") if isinstance(meta, dict) else None
        if hid not in (None, ""):
            return int(hid)
        if reference not in (None, ""):
            return int(reference)
        if customer:
            with db_session() as conn:
                row = conn.execute(
                    "SELECT id FROM households WHERE stripe_customer_id=?",
                    (str(customer),),
                ).fetchone()
            if row:
                return int(row["id"])
        return None

    def _plan_from_subscription(sub: dict) -> str | None:
        data = ((sub.get("items") or {}).get("data")) or None
        if data:
            price = data[0].get("price")
            price_id = price if isinstance(price, str) else (price or {}).get("id")
            found = plan_from_price_id(price_id)
            if found:
                return found
        meta = sub.get("metadata") if isinstance(sub.get("metadata"), dict) else {}
        plan = meta.get("plan")
        return plan if isinstance(plan, str) else None

    def _apply_checkout_session(checkout: dict) -> None:
        customer = checkout.get("customer")
        if isinstance(customer, dict):
            customer = customer.get("id")
        subscription = checkout.get("subscription")
        sub_id = subscription if isinstance(subscription, str) else (subscription or {}).get("id") if isinstance(subscription, dict) else None
        meta = checkout.get("metadata") if isinstance(checkout.get("metadata"), dict) else {}
        plan = meta.get("plan")
        if not plan and isinstance(subscription, dict):
            plan = _plan_from_subscription(subscription)
        if plan not in ("monthly", "yearly"):
            plan = "yearly"
        hid = _household_id_from(meta, checkout.get("client_reference_id"), customer)
        if hid is None:
            return
        with db_session() as conn:
            conn.execute(
                """
                UPDATE households SET plan=?, founding=0,
                    stripe_customer_id=COALESCE(?, stripe_customer_id),
                    stripe_subscription_id=COALESCE(?, stripe_subscription_id),
                    stripe_status=?
                WHERE id=?
                """,
                (plan, customer, sub_id, "active", hid),
            )

    def _apply_subscription(sub: dict) -> None:
        customer = sub.get("customer")
        if isinstance(customer, dict):
            customer = customer.get("id")
        sub_id = sub.get("id")
        plan = _plan_from_subscription(sub)
        if plan not in ("monthly", "yearly"):
            plan = "yearly"
        status = str(sub.get("status") or "")
        meta = sub.get("metadata") if isinstance(sub.get("metadata"), dict) else {}
        if status in ("canceled", "unpaid", "incomplete_expired"):
            with db_session() as conn:
                conn.execute(
                    "UPDATE households SET stripe_subscription_id=NULL, stripe_status=? WHERE stripe_customer_id=?",
                    (status, customer),
                )
            return
        hid = _household_id_from(meta, meta.get("household_id"), customer)
        if hid is None:
            return
        with db_session() as conn:
            conn.execute(
                "UPDATE households SET plan=?, stripe_customer_id=?, stripe_subscription_id=?, stripe_status=? WHERE id=?",
                (plan, customer, sub_id, status or "active", hid),
            )

    return app
