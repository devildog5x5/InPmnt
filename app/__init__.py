from pathlib import Path

from dotenv import load_dotenv
from flask import Flask

from .database import init_db
from .routes import bp


def create_app() -> Flask:
    root = Path(__file__).resolve().parent.parent
    load_dotenv(root / ".env")

    app = Flask(
        __name__,
        template_folder=str(root / "templates"),
        static_folder=str(root / "static"),
    )
    import os

    app.config["SECRET_KEY"] = os.environ.get("FLASK_SECRET_KEY") or "inpmnt-dev-change-me"
    app.config["DATABASE"] = str(root / "inpmnt.db")

    init_db(app.config["DATABASE"])
    app.register_blueprint(bp)
    return app
