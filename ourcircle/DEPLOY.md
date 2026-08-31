# Family Shield Pro — deploy (no VPS)

**Site:** https://familyshieldpro.com  
**Zip:** https://raw.githubusercontent.com/devildog5x5/InPmnt/cursor/ourcircle-family-shield-4df6/patches/FamilyShieldPro-1.2.19.zip  
(`robots.txt` and `sitemap.xml` are inside the zip.)  
**Do not** put this in an InPmnt folder or overwrite a live `.env`.

You do not need Ubuntu, Nginx, systemd, or SSH.

## Install (Hostinger File Manager)

1. Download **FamilyShieldPro.zip**.
2. hPanel → **Files → File Manager** → open `public_html`.
3. Upload the zip → **Extract** / Unzip **here** (you should see `index.php` and `.htaccess` in `public_html`, not inside a subfolder).
4. hPanel → **Advanced → PHP Configuration**: PHP **8.2+**, enable **pdo_sqlite** and **curl**.
5. Open https://familyshieldpro.com

First visit creates `.env` (mode `640`) and `data/ourcircle.db`. Demo: `family@ourcircle.app` / `password123`.

**Stripe:** the checkout code is already in the zip. Paste keys into the live `.env` — do not overwrite that file. Full steps: **[STRIPE.md](STRIPE.md)**.

**Password reset / 2FA:** **[AUTH.md](AUTH.md)**. Email reset needs SMTP or Resend in `.env`. Recovery codes work without mail.

**Customer service:** homepage email **CustomerService@FamilyShieldPro.com**, site-wide chat widget, commented-out phone — **[SUPPORT.md](SUPPORT.md)**. Circle SMS (invites / call-me / inbound checks) is separate — **[SMS.md](SMS.md)**. Operator console (user list for you) is off until `ADMIN_PASSWORD` — **[ADMIN.md](ADMIN.md)**.

Webhook URL: `https://familyshieldpro.com/billing/webhook`

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

Public URLs: `/`, `/signup`, `/login`, `/forgot`. Logged-in paths are disallowed.

## Later updates

Unzip a new zip over `public_html`. **Skip** `.env` and `data/` (keep the live database).

## Local run (developer)

```bash
cd ourcircle
python3 -m pip install -r requirements.txt
python3 run.py
```

Open http://127.0.0.1:5065 — demo `family@ourcircle.app` / `password123`. This is not how the live site is hosted.
