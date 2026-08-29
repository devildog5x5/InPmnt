# OurCircle

OurCircle is the family product at **[familyshieldpro.com](https://familyshieldpro.com)**. The logo on every page links to that site.

**Pause. Ask family. Then pay.**

OurCircle is a **trusted family circle** for sketchy texts, emails, phone numbers, websites, and payment asks. It is not a generic “AI scam detector” and it will **never tell you something is safe**.

The rule on every screen:

> Never send money, cryptocurrency, gift cards, passwords, or account information until the request is independently verified.

## Run it

From this folder (InPmnt stays on port 5055):

```bash
cd ourcircle
python3 run.py
```

Open **http://127.0.0.1:5065**

Demo circle: `family@ourcircle.app` / `password123`

## What families can do

- Paste a suspicious email or text, a phone number, a website, or an offer
- Upload a screenshot
- Read a plain-language risk explanation and specific warning signs
- See whether a number or domain matches the household trusted list — or resembles a known brand
- Ask someone in the circle to review it
- Fire an urgent **Please call me before I pay** alert
- Keep a protected list of banks, doctors, insurers, utilities, and family contacts
- Follow reporting steps (FTC, IC3, card freeze) if money already moved

Family plan: up to **five people**. **$14.99/month** or **$119.99/year**. Stripe Checkout is wired — paste keys per **[STRIPE.md](STRIPE.md)**. Password reset and 2FA: **[AUTH.md](AUTH.md)**. Customer service email, chat widget, and the commented-out phone: **[SUPPORT.md](SUPPORT.md)**.

## Paid validation (seven days)

Share the homepage and `/signup` with real buyers. Count **Family yearly signups at $119.99**, not opinions:

| Product | Seven-day target |
|---|---|
| InPmnt | Five $99 annual customers |
| VendorReady | Two $500 setup deposits |
| OurCircle | Ten $119.99 family years |

If none hits the bar, change the offer before writing more product code.

## Downloads

**https://raw.githubusercontent.com/devildog5x5/InPmnt/cursor/ourcircle-family-shield-4df6/patches/FamilyShieldPro-1.2.13.zip**

That zip is the Hostinger site. `robots.txt` and `sitemap.xml` are already inside it (next to `index.php`). Unzip into `public_html`.

## Hostinger deploy (no VPS)

Same motion as InPmnt: unzip **FamilyShieldPro.zip** into `public_html`. Full steps: **[DEPLOY.md](DEPLOY.md)**. Go-to-market: **[GO_TO_MARKET.md](GO_TO_MARKET.md)**.

1. Upload `FamilyShieldPro.zip` in hPanel File Manager → `public_html` → Unzip here.
2. PHP 8.2+ with **pdo_sqlite** and **curl**. Stripe keys go in `.env` — **[STRIPE.md](STRIPE.md)**.
3. Open https://familyshieldpro.com

Permissions are preset in the zip: folders `755`, `data/` `775`, files `644`. Never `777`. Rebuild with `bash pack_zip.sh`.

Public SEO files:

- https://familyshieldpro.com/robots.txt
- https://familyshieldpro.com/sitemap.xml

## Tests

```bash
cd ourcircle
python3 -m unittest tests.test_ourcircle -q
php tests/test_php.php
```
