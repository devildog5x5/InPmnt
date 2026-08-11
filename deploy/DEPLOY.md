# Deploy InPmnt

**Direct doc:** https://github.com/devildog5x5/InPmnt/blob/main/deploy/DEPLOY.md

InPmnt runs on **Linux VPS** or a **Windows server**. Use a real VM / dedicated box — not shared cPanel-only hosting.

| Platform | Recommended stack |
|----------|-------------------|
| **Docker** (any Linux VM) | `docker compose up` → [Docker](#docker-linux-vm) |
| **Linux** (GoDaddy VPS, Ubuntu, etc.) | Nginx + Gunicorn + systemd → [Linux](#linux-ubuntu--godaddy-vps) |
| **Windows Server** | Waitress + Windows Service (NSSM) + IIS reverse proxy → [Windows](#windows-server) |

Download zips: [latest release](https://github.com/devildog5x5/InPmnt/releases/latest)  
(`InPmnt-Portable.zip` works for native Windows/Linux; Docker uses the repo `Dockerfile`.)

---

## Docker (Linux VM)

Easiest way to keep InPmnt “just running” on a VM.

### Hostinger (recommended)

1. Buy a **VPS** (not shared Web hosting). Prefer the **Ubuntu 24.04 with Docker** template.
2. Point your domain `A` record to the VPS IP.
3. SSH in (or use Hostinger Docker Manager → Compose from GitHub):

```bash
git clone https://github.com/devildog5x5/InPmnt.git /opt/inpmnt
cd /opt/inpmnt
cp .env.example .env
nano .env   # set FLASK_SECRET_KEY, BASE_URL=https://yourdomain.com, Stripe, RESEND_API_KEY or SMTP
docker compose up -d --build
```

4. Put HTTPS in front (Hostinger proxy, Caddy, or nginx) → `127.0.0.1:5055`.
5. Stripe webhook: `https://yourdomain.com/api/billing/webhook`
6. Backup DB weekly: `bash deploy/backup-db.sh /root/inpmnt-backups`

Do **not** set `SHOW_DEMO_LOGIN=1` on a public Hostinger site.

### Prerequisites

- Ubuntu/Debian (or any Linux) with [Docker Engine](https://docs.docker.com/engine/install/) + Compose plugin
- Open firewall port **5055** (or put nginx/Caddy in front on 80/443)

### Run

```bash
git clone https://github.com/devildog5x5/InPmnt.git /opt/inpmnt
cd /opt/inpmnt
# optional Stripe / public URL:
#   cp .env.example .env && nano .env
#   export BASE_URL=https://yourdomain.com
docker compose up -d --build
curl -sS http://127.0.0.1:5055/ | head
```

- App: `http://YOUR_VM_IP:5055`
- Sign up at `/signup` (or set `SHOW_DEMO_LOGIN=1` for local trialuser only)
- Data: Docker volume `inpmnt-data` → `/app/data/inpmnt.db` inside the container
- Restart policy: `unless-stopped` (survives reboot)

### Useful commands

```bash
docker compose ps
docker compose logs -f inpmnt
docker compose pull   # if you later publish an image registry
docker compose up -d --build
docker compose down   # stop (volume keeps the DB)
```

### HTTPS in front of Docker

Terminate TLS on the host (nginx/Caddy) and proxy to `127.0.0.1:5055`. Set `BASE_URL=https://yourdomain.com` in the environment or `.env` used by Compose. Do not use the Windows local `certs/` files inside the container.

---

## Linux (Ubuntu / GoDaddy VPS)

### Fast path

1. Buy a VPS (Ubuntu 22.04+) and note the public IP.
2. Point DNS `A` records for your domain (and `www`) to that IP.
3. SSH in and run:

```bash
ssh root@YOUR_SERVER_IP
apt update && apt install -y git
git clone https://github.com/devildog5x5/InPmnt.git /tmp/inpmnt
sudo bash /tmp/inpmnt/deploy/setup-vps.sh yourdomain.com
```

4. After DNS propagates, enable HTTPS:

```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Renew / replace the Let's Encrypt cert later:

```bash
sudo certbot renew
# Or re-issue for the same names:
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
sudo systemctl reload nginx
```

Do **not** copy the local-dev files from `certs/` onto the VPS. Local replace/regenerate steps are in the root [README](../README.md#local-https-certificate).

5. Add Stripe keys and restart:

```bash
sudo nano /var/www/inpmnt/.env
sudo systemctl restart inpmnt
```

### Linux files

| File | Purpose |
|------|---------|
| `setup-vps.sh` | One-shot install (Nginx + Gunicorn + systemd) |
| `inpmnt.service` | systemd unit |
| `nginx.inpmnt.conf` | Nginx reverse-proxy site |

### Useful Linux commands

```bash
sudo systemctl status inpmnt
sudo systemctl restart inpmnt
sudo journalctl -u inpmnt -f
sudo nginx -t && sudo systemctl reload nginx
cd /var/www/inpmnt && sudo -u www-data git pull && sudo systemctl restart inpmnt
```

---

## Windows Server

**Do not use Gunicorn on Windows** — use **Waitress**. Put **IIS** (or another reverse proxy) in front for HTTPS and the public hostname.

### Fast path

1. Install [Python 3](https://www.python.org/downloads/) (check **Add python.exe to PATH**).
2. Install [IIS](https://learn.microsoft.com/en-us/iis/install/installing-iis-85/installing-iis-85-on-windows-server-2012-r2) with the **URL Rewrite** module (and ARR / Application Request Routing if you reverse-proxy).
3. Open **PowerShell as Administrator** and run:

```powershell
# From a clone or extracted InPmnt-Portable.zip
cd C:\inetpub\inpmnt   # or wherever you placed the app
powershell -ExecutionPolicy Bypass -File .\deploy\setup-windows.ps1 -Domain yourdomain.com
```

Or step through manually:

```powershell
cd C:\inetpub\inpmnt
python -m venv .venv
.\.venv\Scripts\pip.exe install -r requirements.txt waitress
copy .env.example .env
notepad .env   # set BASE_URL=https://yourdomain.com and Stripe keys
```

4. Start the app (smoke test):

```powershell
.\.venv\Scripts\waitress-serve.exe --listen=127.0.0.1:5055 run:app
```

Browse `http://127.0.0.1:5055` — then stop with Ctrl+C.

5. Install as a **Windows Service** with [NSSM](https://nssm.cc/download):

```powershell
nssm install InPmnt "C:\inetpub\inpmnt\.venv\Scripts\waitress-serve.exe" "--listen=127.0.0.1:5055" "run:app"
nssm set InPmnt AppDirectory "C:\inetpub\inpmnt"
nssm set InPmnt AppEnvironmentExtra "PYTHONUNBUFFERED=1"
nssm start InPmnt
```

(`setup-windows.ps1` prints the same NSSM commands after it prepares the app.)

6. **IIS reverse proxy**
   - Create a site bound to `yourdomain.com` (and `www`).
   - Point the site physical path at `deploy\iis` (contains `web.config` that proxies to `127.0.0.1:5055`), **or** add a URL Rewrite reverse-proxy rule to `http://127.0.0.1:5055/{R:0}`.
   - Bind an HTTPS certificate (Win-ACME / Let’s Encrypt, or a GoDaddy cert). To **replace** it later: IIS Manager → site → Bindings → https → Edit → select the new cert (or re-run Win-ACME). Do not use the app’s local `certs/` folder for production.
   - Point DNS `A` records at the Windows server’s public IP.

7. Stripe webhook: `https://yourdomain.com/api/billing/webhook`  
   Restart the service after editing `.env`:

```powershell
nssm restart InPmnt
```

### Windows files

| File | Purpose |
|------|---------|
| `setup-windows.ps1` | Creates venv, installs Waitress, seeds `.env` |
| `iis/web.config` | IIS reverse-proxy to Waitress on port 5055 |

### Useful Windows commands

```powershell
nssm status InPmnt
nssm restart InPmnt
nssm stop InPmnt
# Update app
cd C:\inetpub\inpmnt
git pull
.\.venv\Scripts\pip.exe install -r requirements.txt waitress
nssm restart InPmnt
```

---

## Stripe (both platforms)

Put these in `.env` (never commit `.env`):

```env
FLASK_SECRET_KEY=long-random-string
BASE_URL=https://yourdomain.com
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_PRO=price_...
STRIPE_PRICE_ANNUAL=price_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Webhook URL: `https://yourdomain.com/api/billing/webhook`  

Events: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`.

Customers subscribe from the landing page or **Settings → Billing**. Cards stay on Stripe; use **Manage billing** for cancel / update card.

Test with `sk_test_` / `pk_test_` and card `4242 4242 4242 4242`, then switch to live keys.

---

## Demo login (change after go-live)

- URL: `https://yourdomain.com`
- Email: `trialuser@inpmnt.app`
- Password: `demo1234`
