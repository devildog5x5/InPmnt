"""Run OurCircle on http://127.0.0.1:5065 by default (InPmnt stays on 5055)."""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent / ".env")
load_dotenv()

from web import create_app

app = create_app()

if __name__ == "__main__":
    host = os.environ.get("OURCIRCLE_HOST", "127.0.0.1")
    port = int(os.environ.get("OURCIRCLE_PORT", "5065"))
    print(f"OurCircle → http://{host}:{port}")
    app.run(host=host, port=port, debug=os.environ.get("FLASK_DEBUG") == "1")
