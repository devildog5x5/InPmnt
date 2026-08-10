from pathlib import Path
import os

from dotenv import dotenv_values, load_dotenv
from flask import Flask

from .database import init_db
from .routes import bp


def _load_env(root: Path) -> None:
    """Load .env without letting empty Compose placeholders win forever."""
    load_dotenv(root / ".env")
    file_vals = dotenv_values(root / ".env") or {}
    for key, value in file_vals.items():
        if value is None:
            continue
        current = os.environ.get(key)
        if current is None or current.strip() == "":
            os.environ[key] = value


def create_app() -> Flask:
    root = Path(__file__).resolve().parent.parent
    _load_env(root)

    app = Flask(
        __name__,
        template_folder=str(root / "templates"),
        static_folder=str(root / "static"),
    )

    secret = (os.environ.get("FLASK_SECRET_KEY") or "").strip()
    if not secret or secret == "change-me-to-a-long-random-string":
        secret = "inpmnt-dev-change-me"
    app.config["SECRET_KEY"] = secret
    db_env = (os.environ.get("DATABASE_PATH") or "").strip()
    app.config["DATABASE"] = db_env if db_env else str(root / "inpmnt.db")

    # Harden cookies when serving under HTTPS (local or reverse-proxied).
    base = (os.environ.get("BASE_URL") or "").lower()
    if base.startswith("https://"):
        app.config["SESSION_COOKIE_SECURE"] = True
        app.config["SESSION_COOKIE_SAMESITE"] = "Lax"

    init_db(app.config["DATABASE"])
    app.register_blueprint(bp)
    return app
