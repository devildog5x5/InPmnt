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
        cert_env = os.environ.get("SSL_CERT_FILE", "").strip()
        key_env = os.environ.get("SSL_KEY_FILE", "").strip()
        if cert_env and key_env:
            cert_path, key_path = Path(cert_env), Path(key_env)
            if not cert_path.is_file() or not key_path.is_file():
                raise SystemExit(
                    f"SSL_CERT_FILE / SSL_KEY_FILE not found:\n  {cert_path}\n  {key_path}"
                )
        else:
            from app.local_ssl import ensure_local_certs

            cert_path, key_path = ensure_local_certs(
                Path(__file__).resolve().parent / "certs"
            )
        ssl_context = (str(cert_path), str(key_path))
        scheme = "https"
        print(f"Using TLS cert: {cert_path}")
        print("If self-signed, browser will warn - Advanced / Proceed (local only).")

    print(f"Open {scheme}://{host}:{port}")
    app.run(host=host, port=port, debug=True, ssl_context=ssl_context)
