"""OurCircle — pause, ask family, then pay. Flask app."""
from __future__ import annotations

import json
import os
from pathlib import Path

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

from analyze import CORE_RULE, DISCLAIMER, analyze
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

ROOT = Path(__file__).resolve().parent
UPLOADS = DATA / "uploads"
ALLOWED_SHOT = {".png", ".jpg", ".jpeg", ".webp", ".gif"}
DEFAULT_SITE_URL = "https://familyshieldpro.com"
PUBLIC_PATHS = ("/", "/signup", "/login", "/offers")
PRIVATE_PREFIXES = (
    "/home",
    "/circle",
    "/trusted",
    "/checks",
    "/uploads",
    "/join",
    "/billing",
    "/report",
    "/logout",
)


def site_url() -> str:
    return (os.environ.get("OURCIRCLE_SITE_URL") or DEFAULT_SITE_URL).rstrip("/")

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
            "user_name": session.get("name"),
            "site_home": site_url(),
        }

    @app.get("/robots.txt")
    def robots_txt():
        lines = [
            "User-agent: *",
            "Allow: /",
            "Allow: /signup",
            "Allow: /login",
            "Allow: /offers",
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
        ver = "0.0.0"
        vp = ROOT / "VERSION"
        if vp.is_file():
            ver = vp.read_text(encoding="utf-8").strip() or ver
        return {
            "ok": True,
            "service": "familyshieldpro",
            "product": "Family Shield Pro",
            "app": "OurCircle",
            "version": ver,
            "not": "InPmnt",
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

    @app.get("/offers")
    def offers():
        return render_template("offers.html")

    @app.post("/offers")
    def offers_reserve():
        product = (request.form.get("product") or "").strip()
        name = (request.form.get("name") or "").strip()
        email = (request.form.get("email") or "").strip().lower()
        offer = (request.form.get("offer") or "").strip()
        note = (request.form.get("note") or "").strip()
        if product not in ("inpmnt", "vendorready", "ourcircle") or not name or "@" not in email:
            flash("Please choose a product and leave a real name and email.", "error")
            return redirect(url_for("offers"))
        with db_session() as conn:
            conn.execute(
                "INSERT INTO reservations (product, name, email, offer, note, created_at) VALUES (?,?,?,?,?,?)",
                (product, name, email, offer or "family year", note, now()),
            )
        flash(
            "Reservation saved. This is a refundable hold, not a charge. "
            "We will email you before anything is billed.",
            "ok",
        )
        return redirect(url_for("offers"))

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
        session["user_id"] = user["id"]
        session["household_id"] = user["household_id"]
        session["name"] = user["name"]
        session["email"] = user["email"]
        return redirect(request.args.get("next") or url_for("home"))

    @app.get("/logout")
    def logout():
        session.clear()
        return redirect(url_for("landing"))

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
        return render_template("billing.html", plans=PLANS, household=dict(hh) if hh else {})

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
        with db_session() as conn:
            conn.execute(
                "UPDATE households SET plan=?, founding=0 WHERE id=?",
                (plan, u["household_id"]),
            )
        label = "$14.99/month" if plan == "monthly" else "$119.99/year"
        flash(f"This circle is on Family {plan} ({label}). No card is charged in this build — this is the plan flag.", "ok")
        return redirect(url_for("billing"))

    return app
