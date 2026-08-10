# InPmnt

**Get paid without the chase.**

Invoice chase + payment reminders for solo trades and freelancers. Built by **Robert Foster**.

InPmnt helps plumbers, landscapers, photographers, and consultants stop losing cash to late invoices: track open balances, auto-queue polite reminders, send final notices, and record payments — without a full accounting suite.

## Downloads

Packages are published on the [GitHub Releases](https://github.com/devildog5x5/InPmnt/releases) page. Each release ships **all three** archives.

| Package | What you get | Download |
|---------|----------------|----------|
| **Portable** | Runnable app — extract and run `start.ps1` | [InPmnt-Portable.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.0.0/InPmnt-Portable.zip) |
| **Source** | Source distribution | [InPmnt-Source.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.0.0/InPmnt-Source.zip) |
| **Icon** | Brand icon assets (blue / teal / violet) | [InPmnt-Icon.zip](https://github.com/devildog5x5/InPmnt/releases/download/v1.0.0/InPmnt-Icon.zip) |

- Latest release: [v1.0.0](https://github.com/devildog5x5/InPmnt/releases/tag/v1.0.0)
- Demo login: `robert@inpmnt.app` / `demo1234`
- App URL (local): `http://127.0.0.1:5055`
- Rebuild locally: `powershell -File .\build_release.ps1` → `installers\*.zip`

## Quick start

```powershell
cd c:\Users\rober\Documents\GitHub\InPmnt
powershell -File .\start.ps1
```

Or manually:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy .env.example .env
python run.py
```

## Deploy (GoDaddy VPS)

Step-by-step + one-shot script: **[deploy/DEPLOY.md](https://github.com/devildog5x5/InPmnt/blob/main/deploy/DEPLOY.md)**

```bash
sudo bash deploy/setup-vps.sh yourdomain.com
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

| | |
|---|---|
| Brand | InPmnt |
| Tagline | Get paid without the chase |
| Author | Robert Foster |
| License | MIT |
| Icon | `static/img/inpmnt-icon.png` |
| UI | Teal + slate system aligned with Coalesce ERP |

## Go to market

See [GO_TO_MARKET.md](GO_TO_MARKET.md).

## Stack

- Python 3 + Flask + SQLite
- Stripe Checkout / Billing Portal / webhooks
- Vanilla HTML / CSS / JS (Source Serif 4 + IBM Plex)

## License

MIT © 2026 Robert Foster
