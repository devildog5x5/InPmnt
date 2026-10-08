# InvoicePay

**Get paid without the chase.**

Invoice chase + payment reminders for solo trades and freelancers. Built by **Robert Foster**.

InvoicePay helps plumbers, landscapers, photographers, and consultants stop losing cash to late invoices: track open balances, auto-queue polite reminders, send final notices, and record payments — without a full accounting suite.

## Downloads

Packages for **v1.6.5** are on the [v1.6.5 release](https://github.com/devildog5x5/InPmnt/releases/tag/v1.6.5). The GitHub repo name stays `InPmnt` until Robert renames it. Rebuild locally with `powershell -File .\build_release.ps1` → `installers\`.

| Package | What you get | Download |
|---------|----------------|----------|
| **PHP (Hostinger)** | Unzip into `public_html` — no VPS | [invcpay-v1.6.5.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.6.5/invcpay-v1.6.5.zip) |
| **Portable** | Runnable Windows app — `install.ps1` or `start.ps1` | [ReceiptGrid-Portable.v1.6.5.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.6.5/ReceiptGrid-Portable.v1.6.5.zip) |
| **Source** | Full source (Python + PHP + Docker) | [ReceiptGrid-Source.v1.6.5.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.6.5/ReceiptGrid-Source.v1.6.5.zip) |
| **Icon** | Brand icon assets (blue / teal / violet) | [ReceiptGrid-Icon.v1.6.5.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.6.5/ReceiptGrid-Icon.v1.6.5.zip) |
- Sign up: `/signup` · Local demo (optional): set `SHOW_DEMO_LOGIN=1` then `demouser@inpmnt.app` / `Demo`
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

## Factory reset

Wipes **all** users, passwords, invoices, clients, and reminders, then recreates the default admin and demo accounts.

- In the app (admin only): **Settings → Danger zone → Clear database…** and type `RESET`
- From the app folder (stops a running instance first):

```powershell
powershell -ExecutionPolicy Bypass -File .\reset_db.ps1
```

Sign in afterwards as `admin@inpmnt.app` with the initial password (see [deploy/DEPLOY.md](deploy/DEPLOY.md)).

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

InvoicePay now ships a **PHP** build you can drop on Hostinger Web/Cloud (no VPS).

1. Download [invcpay-v1.6.5.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.6.5/invcpay-v1.6.5.zip).
2. In hPanel → **Files → File Manager** (or FTP), unzip **all files into `public_html`**.
3. Copy `.env.example` → `.env`. Set `APP_SECRET` (long random string) and `BASE_URL=https://yourdomain.com`.
4. hPanel → **Advanced → PHP Configuration**: PHP **8.2+**, enable **pdo_sqlite**.
5. Open `https://yourdomain.com` and sign up.

Stripe webhook: `https://yourdomain.com/api/billing/webhook`  
SQLite is created at `data/inpmnt.db` (blocked from the web).

The Windows portable app is still Python (`start.ps1`). Use PHP only on shared hosting.

## FTP / shared hosting

Use **invcpay-vX.Y.Z.zip** on Hostinger Web/Cloud (unzip into `public_html`). The Python app still will not run from `public_html`.

| Host | What to do |
|------|------------|
| **Hostinger Web / Cloud** | Download **invcpay-vX.Y.Z.zip** and unzip into `public_html`. See [Hostinger PHP](#hostinger-php--shared-hosting). |
| **cPanel with Setup Python App** | Optional Python path: FTP source into the app root; startup file `passenger_wsgi.py`. See [deploy/DEPLOY.md](deploy/DEPLOY.md#ftp--cpanel-python-app). |

## Docker (Linux container)

Runs InvoicePay with **Gunicorn** on Linux inside Docker — good for a VPS/VM.

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

InvoicePay uses **Stripe Checkout** and the Customer Portal. The customer picks **$4.99/month** or **$49.99/year** (same features; yearly saves $9.89 versus $4.99 × 12). The 14-day trial does not require a card. Email reminders are in the trial. SMS and unlimited open invoices are included after you subscribe.

Create one product in the [Stripe Dashboard](https://dashboard.stripe.com/products):

| Field | Value |
|---|---|
| Product name | `InvoicePay` |
| Statement descriptor | `INVOICEPAY` (5–22 characters; set on the product, not in this repo) |

Then create two **recurring** prices on that product and paste the price IDs into `.env` (Hostinger: `public_html/.env`):

| Env variable | Amount | Interval |
|---|---|---|
| `STRIPE_PRICE_MONTHLY` | $4.99 USD | Every month |
| `STRIPE_PRICE_YEARLY` | $49.99 USD | Every year |

Also set `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` (endpoint: `POST /api/billing/webhook`), and `BASE_URL` (live site: `https://invcpay.com`). Checkout stays disabled until the secret key and both new price IDs are real Stripe IDs, not the `price_...` placeholders.

Leave `STRIPE_PRICE_STARTER`, `STRIPE_PRICE_PRO`, and `STRIPE_PRICE_ANNUAL` only if existing subscribers are still billed on the old prices ($10/month, $20/month, $100/year). Those keys are not shown as purchase options. Do not point them at the new $4.99 or $49.99 prices.

Set `MAIL_FROM_NAME=InvoicePay` so password-reset mail uses the new name. The env key name itself does not change.

Without those keys, Subscribe stays on the pricing cards and the Billing page and tells customers that payments are being set up (support@invcpay.com). Admins see which keys in `public_html/.env` are missing or still placeholders.

## Features

- Collections dashboard (overdue $, open balance, aging buckets)
- Clients & invoices (draft → sent → partial → overdue → paid)
- Reminder schedules (−3 / 0 / +3 / +7 / +14 vs due date)
- Email & SMS templates with merge fields
- One-tap final notice + payment recording
- Stripe subscriptions + customer portal
- Password reset from the login page (email, or a local `password-reset.txt` if mail isn’t configured)
- Factory reset (admin Settings, or `reset_db.ps1`) wipes users, passwords, and all app data
- Marketing landing page

## Product

**Official product name: InvoicePay.** Tagline: *Get paid without the chase.* The live site stays at invcpay.com. receiptgrid.pro is a separate receipts product and is not named on this site.

| | |
|---|---|
| Brand | **InvoicePay** |
| Tagline | Get paid without the chase |
| Author | Robert Foster |
| Icon | `static/img/inpmnt-icon.png` |
| UI | Teal + slate system aligned with Coalesce ERP |
| Repo / releases | https://github.com/devildog5x5/InPmnt |

## Go to market

See [GO_TO_MARKET.md](GO_TO_MARKET.md).

## Stack

- Python 3 + Flask + SQLite
- Stripe Checkout / Billing Portal / webhooks
- Vanilla HTML / CSS / JS (Source Serif 4 + IBM Plex)
