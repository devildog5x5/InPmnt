# Family Shield Pro — Ubuntu deployment

**Site:** https://familyshieldpro.com  
**App name in the UI:** OurCircle  
**This pack is separate from InPmnt.** Do not drop it into an InPmnt tree or overwrite a live InPmnt `.env`.

Use the Ubuntu zip or tarball. Both contain the same app. The **tarball keeps Unix permissions**; the **zip is easier to download**. `setup-ubuntu.sh` always resets permissions after extract, so either format is safe.

## What you get

| File | Use |
|---|---|
| `FamilyShieldPro-Ubuntu.zip` | Copy to the VPS and unzip |
| `FamilyShieldPro-Ubuntu.tar.gz` | Same contents; preserves 755/644/775 |
| `deploy/setup-ubuntu.sh` | One-shot install (Python venv, Gunicorn, Nginx, systemd) |
| `deploy/fix-permissions.sh` | Re-apply Ubuntu-safe modes (never 777) |
| `/robots.txt` and `/sitemap.xml` | Served by the app for Google Search Console |

Gunicorn binds **127.0.0.1:5065**. Nginx is the public front on ports 80/443.

## Permissions (required)

Never run `chmod -R 777`.

| Path | Mode | Owner |
|---|---|---|
| App directories | `755` | `www-data:www-data` |
| App files | `644` | `www-data:www-data` |
| `deploy/*.sh`, `pack_ubuntu.sh` | `755` | `www-data:www-data` |
| `data/` and `data/uploads/` | `775` | `www-data:www-data` |
| `data/*.db` | `660` | `www-data:www-data` |
| `.env` | `640` | `www-data:www-data` |

`sudo bash deploy/fix-permissions.sh` applies this map.

## One-time VPS setup (Ubuntu 22.04 or 24.04)

1. Create a VPS (1 vCPU / 1 GB RAM is enough to start). Note the public IP.
2. In DNS for **familyshieldpro.com**:
   - `A` → VPS IPv4 for `@` (apex)
   - `A` → same IP for `www`
3. Copy the zip to the server (scp, SFTP, or paste).
4. As root:

```bash
sudo apt-get update
sudo apt-get install -y unzip
sudo mkdir -p /tmp/fsp && cd /tmp/fsp
sudo unzip -o /path/to/FamilyShieldPro-Ubuntu.zip
sudo bash deploy/setup-ubuntu.sh familyshieldpro.com
```

Tarball instead of zip:

```bash
sudo mkdir -p /tmp/fsp && cd /tmp/fsp
sudo tar -xzf /path/to/FamilyShieldPro-Ubuntu.tar.gz --strip-components=1
sudo bash deploy/setup-ubuntu.sh familyshieldpro.com
```

5. After DNS answers, issue TLS:

```bash
sudo certbot --nginx -d familyshieldpro.com -d www.familyshieldpro.com
```

6. Smoke-check:

```bash
curl -sS http://127.0.0.1:5065/healthz
curl -sS https://familyshieldpro.com/robots.txt
curl -sS https://familyshieldpro.com/sitemap.xml
```

The installer **does not overwrite** an existing `/var/www/familyshieldpro/.env`.

## Search Console

1. Open [Google Search Console](https://search.google.com/search-console).
2. Add the URL prefix property `https://familyshieldpro.com`.
3. Verify (HTML file, DNS TXT, or meta tag).
4. Sitemaps → submit `https://familyshieldpro.com/sitemap.xml`.

Public URLs in the sitemap: `/`, `/signup`, `/login`, `/offers`.  
Private app paths (`/home`, `/circle`, `/trusted`, `/checks`, `/uploads`, `/join`, `/billing`, `/report`, `/logout`) are disallowed in `robots.txt` and marked `noindex` in the logged-in shell.

## Rebuild the pack from source

```bash
cd ourcircle
bash pack_ubuntu.sh
```

Outputs land in `/opt/cursor/artifacts/` when that directory exists.

## Updates

```bash
# unpack a new zip over /tmp/fsp, then:
sudo rsync -a --exclude '.venv' --exclude '.env' --exclude 'data/*.db' /tmp/fsp/ /var/www/familyshieldpro/
sudo bash /var/www/familyshieldpro/deploy/fix-permissions.sh
sudo systemctl restart familyshieldpro
```

## Backup

Copy `/var/www/familyshieldpro/.env` and `/var/www/familyshieldpro/data/` off the box on a schedule. SQLite is the whole family database.

## Local run (not production)

```bash
cd ourcircle
python3 -m pip install -r requirements.txt
python3 run.py
```

Open http://127.0.0.1:5065 — demo `family@ourcircle.app` / `password123`.
