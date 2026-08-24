# InPmnt

**Get paid without the chase.**

Invoice chase + payment reminders for solo trades and freelancers. Built by **Robert Foster**.

InPmnt helps plumbers, landscapers, photographers, and consultants stop losing cash to late invoices: track open balances, auto-queue polite reminders, send final notices, and record payments — without a full accounting suite.

## Downloads

Packages are published on the [GitHub Releases](https://github.com/devildog5x5/InPmnt/releases) page. Each release ships **all three** archives.

| Package | What you get | Download |
|---------|----------------|----------|
| **PHP (Hostinger)** | Unzip into `public_html` — no VPS | [InPmnt-PHP.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.4.3/InPmnt-PHP.zip) |
| **Portable** | Runnable Windows app — `install.ps1` or `start.ps1` | [InPmnt-Portable.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.4.3/InPmnt-Portable.zip) |
| **Source** | Full source (Python + PHP + Docker) | [InPmnt-Source.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.4.3/InPmnt-Source.zip) |
| **Icon** | Brand icon assets (blue / teal / violet) | [InPmnt-Icon.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.4.3/InPmnt-Icon.zip) |

- Latest release: [v1.4.3](https://github.com/devildog5x5/InPmnt/releases/tag/v1.4.3)
- Sign up: `/index.php/signup` (or `/signup` once Apache rewrite is on) · Local demo (optional): set `SHOW_DEMO_LOGIN=1` then `demouser@inpmnt.app` / `Demo`
- App URL (local): `https://127.0.0.1:5055` (self-signed cert; accept the browser warning)
- Rebuild locally: `powershell -File .\build_release.ps1` → `installers\*.zip`

## Install (Windows)

```powershell
# From the extracted Portable zip:
powershell -ExecutionPolicy Bypass -File .\install.ps1
```

Installs to `%LOCALAPPDATA%\InPmnt`. If an older copy is already installed, you get a choice:

- **Y** — uninstall the old version, then install the new one  
- **N** — update in place (keeps `.env`, database, certs)  
- **C** — cancel  

Uninstall later: `powershell -File .\uninstall.ps1` (add `-RemoveData` to delete the database too).

## Quick start

```powershell
cd c:\Users\rober\Documents\GitHub\InPmnt
powershell -File .\start.ps1
```

Opens **HTTPS** on `https://127.0.0.1:5055`. First run writes a self-signed cert under `certs/` (gitignored). Your browser will warn once — use Advanced → Proceed (local only). Set `USE_HTTPS=0` in `.env` for plain HTTP.

Or manually:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy .env.example .env
python run.py
```

## Local HTTPS certificate

Default files (created automatically, **not** committed):

| File | Role |
|------|------|
| `certs/localhost.pem` | Certificate (PEM) |
| `certs/localhost-key.pem` | Private key (PEM) |

### Regenerate the self-signed cert

```powershell
# Option A - helper (overwrites both files)
.\.venv\Scripts\python.exe -m app.local_ssl --force

# Option B - delete and let the next start recreate them
Remove-Item .\certs\localhost.pem, .\certs\localhost-key.pem -ErrorAction SilentlyContinue
```

Then restart: `powershell -File .\start.ps1`

### Replace with your own certificate

1. Convert your cert + key to **PEM** if needed (not PFX/P12 alone).
2. Either:
   - Overwrite `certs/localhost.pem` and `certs/localhost-key.pem`, **or**
   - Point `.env` at custom paths:

```env
USE_HTTPS=1
SSL_CERT_FILE=C:\path\to\fullchain.pem
SSL_KEY_FILE=C:\path\to\privkey.pem
BASE_URL=https://127.0.0.1:5055
```

3. Restart the app. Browsers trust public CAs; self-signed and private CAs still show a warning unless you trust them in the OS/browser store.

Production TLS (Let's Encrypt / IIS) is handled by nginx or IIS in front of the app — see [deploy/DEPLOY.md](deploy/DEPLOY.md). Do not use the local `certs/` files on a public server.

## Hostinger (PHP — shared hosting)

InPmnt now ships a **PHP** build you can drop on Hostinger Web/Cloud (no VPS).

1. Download [InPmnt-PHP.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.4.3/InPmnt-PHP.zip).
2. In hPanel → **Files → File Manager** (or FTP), unzip **all files into `public_html`**.
3. Copy `.env.example` → `.env`. Set `APP_SECRET` (long random string) and `BASE_URL=https://yourdomain.com`.
4. hPanel → **Advanced → PHP Configuration**: PHP **8.2+**, enable **pdo_sqlite**.
5. Open `https://yourdomain.com/index.php` then **Start free trial** (`/index.php/signup`). Pricing is on the same homepage (`#pricing`); Log in / Sign up need that `/index.php/...` form when Apache rewrite is off.

Stripe webhook: `https://yourdomain.com/api/billing/webhook`  
SQLite is created at `data/inpmnt.db` (blocked from the web).

On Apache2 (Ubuntu/Debian), unzip into `/var/www/html` (the document root) so `src/Env.php` sits next to `bootstrap.php`. Do not leave `src/` in `/var/www/src`. Enable `mod_rewrite`, `AllowOverride All`, and `php-sqlite3`. See [deploy/DEPLOY.md](deploy/DEPLOY.md#apache2-ubuntu--debian).

The Windows portable app is still Python (`start.ps1`). Use PHP only on shared hosting.

## FTP / shared hosting

Use **InPmnt-PHP.zip** on Hostinger Web/Cloud (unzip into `public_html`). The Python app still will not run from `public_html`.

| Host | What to do |
|------|------------|
| **Hostinger Web / Cloud** | Download **InPmnt-PHP.zip** and unzip into `public_html`. See [Hostinger PHP](#hostinger-php--shared-hosting). |
| **cPanel with Setup Python App** | Optional Python path: FTP source into the app root; startup file `passenger_wsgi.py`. See [deploy/DEPLOY.md](deploy/DEPLOY.md#ftp--cpanel-python-app). |

## Docker (Linux container)

Runs InPmnt with **Gunicorn** on Linux inside Docker — good for a VPS/VM.

```bash
# On a machine with Docker installed:
git clone https://github.com/devildog5x5/InPmnt.git
cd InPmnt
# optional: cp .env.example .env  && edit Stripe keys / BASE_URL
docker compose up -d --build
```

Open **http://127.0.0.1:5055** (or `http://VM_IP:5055`).  
Demo: `demouser@inpmnt.app` / `Demo` (admin account is reserved)  
SQLite persists in the Docker volume `inpmnt-data`. TLS belongs on the host (nginx/Caddy/Traefik) or cloud load balancer — the container serves plain HTTP on port 5055.

```bash
docker compose logs -f        # logs
docker compose down           # stop
docker compose up -d --build  # rebuild after pulls
```

## Deploy (Linux VPS or Windows Server)

Full guide (Linux + Windows + Docker): **[deploy/DEPLOY.md](https://github.com/devildog5x5/InPmnt/blob/main/deploy/DEPLOY.md)**

```bash
# Linux / GoDaddy VPS (native)
sudo bash deploy/setup-vps.sh yourdomain.com

# Or Docker on any Linux VM
docker compose up -d --build
```

```powershell
# Windows Server (Waitress + IIS)
powershell -ExecutionPolicy Bypass -File .\deploy\setup-windows.ps1 -Domain yourdomain.com
```

## Stripe billing

InPmnt ships with **Stripe Checkout** (Starter $19 / Pro $39 / Annual $99) and the Customer Portal.

1. Create products + recurring prices in the [Stripe Dashboard](https://dashboard.stripe.com/products).
2. Copy `.env.example` → `.env` and set:
   - `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`
   - `STRIPE_PRICE_STARTER`, `STRIPE_PRICE_PRO`, `STRIPE_PRICE_ANNUAL`
   - `STRIPE_WEBHOOK_SECRET` (endpoint: `POST /api/billing/webhook`)
   - `BASE_URL` (e.g. `https://your-domain.com`)
3. Subscribe from the landing page or **Settings → Billing**.

Without keys, the app still runs in demo/trial mode.

## Features

- Collections dashboard (overdue $, open balance, aging buckets)
- Clients & invoices (draft → sent → partial → overdue → paid)
- Reminder schedules (−3 / 0 / +3 / +7 / +14 vs due date)
- Email & SMS templates with merge fields
- One-tap final notice + payment recording
- Stripe subscriptions + customer portal
- Marketing landing page

## Product

**Official product name: InPmnt** (locked). Tagline only — not the brand: *Get paid without the chase.*

| | |
|---|---|
| Brand | **InPmnt** |
| Tagline | Get paid without the chase |
| Author | Robert Foster |
| License | MIT |
| Icon | `static/img/inpmnt-icon.png` |
| UI | Teal + slate system aligned with Coalesce ERP |
| Repo / releases | https://github.com/devildog5x5/InPmnt |

## Go to market

See [GO_TO_MARKET.md](GO_TO_MARKET.md).

## Stack

- Python 3 + Flask + SQLite
- Stripe Checkout / Billing Portal / webhooks
- Vanilla HTML / CSS / JS (Source Serif 4 + IBM Plex)

## License

MIT © 2026 Robert Foster
