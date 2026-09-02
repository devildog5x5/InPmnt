# AGENTS.md

## Cursor Cloud specific instructions

InPmnt is one invoice-chase app with two runtimes: **Python/Flask** (`run.py`, canonical for local/cloud) and **PHP** (`php/`, Hostinger drop-in). There is no Node build, no Redis/Postgres, and no extra sidecar services. Persistence is a SQLite file (`inpmnt.db`).

### Python (use this in Cloud Agent VMs)

A venv lives at `.venv/`. Refresh deps with `.venv/bin/pip install -r requirements.txt` (also the startup update script).

Copy `.env.example` → `.env` if `.env` is missing. For this Linux VM, keep HTTP (not the Windows default HTTPS):

- `USE_HTTPS=0`
- `BASE_URL=http://127.0.0.1:5055`
- `SHOW_DEMO_LOGIN=1` — login page pre-fills `demouser@inpmnt.app` / `Demo`
- `ALLOW_FAKE_EMAIL=1` — reminder “send” succeeds without Resend/SMTP

`run.py` defaults `USE_HTTPS=1` when that env var is unset, which creates a self-signed cert and will fail or warn in headless browsers. Do not rely on README’s `https://127.0.0.1:5055` here.

Start the app (do not also bind Docker to 5055):

```bash
.venv/bin/python run.py
```

App: `http://127.0.0.1:5055` · signup: `/signup` · SPA: `/app`.

Stripe keys in `.env.example` (`sk_test_...`, `price_...`) are stubs; Checkout returns 503 until real test keys exist. Core clients/invoices/payments/reminders work without Stripe.

### Lint / test / build

There is no pytest, ESLint, or CI. Smoke instead of a test suite:

```bash
.venv/bin/python -m compileall -q app run.py
.venv/bin/python -c "from app import create_app; c=create_app().test_client(); r=c.get('/'); assert r.status_code==200"
```

Windows scripts (`start.ps1`, `build_release.ps1`) are the portable/release path, not this VM.

### PHP flavor (optional)

Same product; not required for Flask E2E. From `php/` with PHP 8.2+ `pdo_sqlite`: `php -S 127.0.0.1:8080`. See README Hostinger section.
