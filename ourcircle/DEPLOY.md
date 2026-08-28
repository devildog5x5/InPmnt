# Family Shield Pro — deploy (no VPS)

**Site:** https://familyshieldpro.com  
**Zip:** [FamilyShieldPro.zip](https://raw.githubusercontent.com/devildog5x5/InPmnt/cursor/ourcircle-family-shield-4df6/patches/FamilyShieldPro.zip) — unzip into Hostinger `public_html`.  
**robots.txt:** [download](https://raw.githubusercontent.com/devildog5x5/InPmnt/cursor/ourcircle-family-shield-4df6/patches/robots.txt)  
**sitemap.xml:** [download](https://raw.githubusercontent.com/devildog5x5/InPmnt/cursor/ourcircle-family-shield-4df6/patches/sitemap.xml)  
**Do not** put this in an InPmnt folder or overwrite a live `.env`.

You do not need Ubuntu, Nginx, systemd, or SSH.

## Install (Hostinger File Manager)

1. Download **FamilyShieldPro.zip**.
2. hPanel → **Files → File Manager** → open `public_html`.
3. Upload the zip → **Extract** / Unzip **here** (you should see `index.php` and `.htaccess` in `public_html`, not inside a subfolder).
4. hPanel → **Advanced → PHP Configuration**: PHP **8.2+**, enable **pdo_sqlite**.
5. Open https://familyshieldpro.com

First visit creates `.env` (mode `640`) and `data/ourcircle.db`. Demo: `family@ourcircle.app` / `password123`.

Point the domain’s DNS at Hostinger as you already do for other sites. Hostinger provides HTTPS.

## Permissions (already in the zip)

Do not run chmod at all unless signup/checks cannot save.

| Path | Mode |
|---|---|
| Folders | `755` |
| `data/` and `data/uploads/` | `775` |
| Files | `644` |
| `.env` (created on first visit) | `640` |

If File Manager dropped modes, set **only** the `data` folder to `775`. **Never 777.**

## robots.txt and sitemap.xml

These files sit in `public_html` and are also served by PHP:

- https://familyshieldpro.com/robots.txt
- https://familyshieldpro.com/sitemap.xml

After the site is live: Google Search Console → add `https://familyshieldpro.com` → submit the sitemap.

Public URLs: `/`, `/signup`, `/login`, `/offers`. Logged-in paths are disallowed.

## Later updates

Unzip a new zip over `public_html`. **Skip** `.env` and `data/` (keep the live database).

## Local run (developer)

```bash
cd ourcircle
python3 -m pip install -r requirements.txt
python3 run.py
```

Open http://127.0.0.1:5065 — demo `family@ourcircle.app` / `password123`. This is not how the live site is hosted.
