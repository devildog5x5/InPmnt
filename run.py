"""Run InPmnt locally (HTTPS by default with a self-signed cert)."""
import os
from pathlib import Path

from dotenv import load_dotenv

load_dotenv()

from app import create_app

app = create_app()

if __name__ == "__main__":
    host = os.environ.get("HOST", "127.0.0.1")
    port = int(os.environ.get("PORT", "5055"))
    use_https = os.environ.get("USE_HTTPS", "1").strip().lower() not in (
        "0",
        "false",
        "no",
        "off",
    )

    ssl_context = None
    scheme = "http"
    if use_https:
        from app.local_ssl import ensure_local_certs

        cert_path, key_path = ensure_local_certs(Path(__file__).resolve().parent / "certs")
        ssl_context = (str(cert_path), str(key_path))
        scheme = "https"
        print(f"Using self-signed cert: {cert_path}")
        print("Browser will warn once - choose Advanced / Proceed (local only).")

    print(f"Open {scheme}://{host}:{port}")
    app.run(host=host, port=port, debug=True, ssl_context=ssl_context)
