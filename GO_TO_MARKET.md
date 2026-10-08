# InvoicePay — Go to market

Owner: **Robert Foster** · Product: invoice chase + payment reminders for solo trades / freelancers.

## Positioning

> **InvoicePay — Get paid without the chase.**  
> Paste unpaid invoices, auto-remind clients, and recover cash without awkward follow-ups.

**Not competing with:** QuickBooks, FreshBooks, full CRMs.  
**Competing with:** Spreadsheets, sticky notes, and “I’ll text them later.”

## Who buys first

1. Solo plumbers / HVAC / landscapers (local Facebook & Nextdoor)
2. Photographers & videographers (Instagram / Facebook groups)
3. Independent consultants (LinkedIn DMs)

Pitch in one line: *“Pays for itself after one recovered invoice.”*

## Pricing (live on landing page)

| Plan | Price | Hook |
|------|-------|------|
| Monthly | $4.99/mo | Unlimited open invoices, email and SMS |
| Yearly | $49.99/yr | Same features. Save $9.89 versus $4.99 × 12. |

Offer a **14-day trial** (already seeded in the app). No card is required to start. A card is required only when the customer subscribes.

## 7-day launch plan

### Day 1–2 — Ship the demo
- [x] Working app + demo data
- [x] Landing page + pricing
- [x] App icon
- [x] Docker image + Hostinger deploy docs
- [ ] Deploy to Hostinger VPS + point domain

### Day 3 — Payments & real sends
- [x] Stripe Checkout for $4.99/month or $49.99/year (wired — paste price IDs into `.env`)
- [x] Stripe Customer Portal + webhook (`/api/billing/webhook`)
- [x] Email reminders (Resend or SMTP — add keys to `.env`)
- [ ] Twilio for SMS (included on the paid plan)
- [x] Signup + workspace isolation; demo login gated behind `SHOW_DEMO_LOGIN`

### Stripe setup (keys you must paste)

1. [Stripe API keys](https://dashboard.stripe.com/apikeys) → `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY`
2. Create three Products with recurring Prices → copy IDs into `.env`
3. Webhook to `https://YOUR_HOST/api/billing/webhook` for:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
4. Set `BASE_URL` to your public URL (success/cancel redirects)

### Day 4 — Proof assets
- [ ] 60-second Loom: overdue → send reminder → record payment
- [ ] 3 screenshots (dashboard, reminder queue, invoice)
- [ ] One-page PDF “How trades get paid faster with InvoicePay”

### Day 5–7 — Sell
- [ ] $20–50/day Facebook/Nextdoor ads targeting “plumber / landscaper / photographer” + city
- [ ] 20 cold DMs/day: “Curious if late invoices are a pain — built a tiny tool that auto-nudges clients”
- [ ] Post in 5 local trade / freelancer groups (value first, link in comments)
- [ ] Public price is $4.99/month or $49.99/year

## Ad angles that convert

1. **Money left on the table** — “How much are you owed right now?”
2. **Awkward texts** — “Stop writing ‘just checking in on that invoice…’”
3. **One recovered invoice** — “$4.99/month vs one $640 job paid late”
4. **Not another QuickBooks** — “Reminders only. Takes 2 minutes to set up.”

## Demo script (under 60 seconds)

1. Open dashboard → point at overdue total  
2. Reminder queue → Send now  
3. Invoice → Record payment → watch status flip to paid  
4. Close with pricing + trial

## Technical next steps for production

| Item | Suggestion |
|------|------------|
| Hosting | Render / Railway / Fly.io (Flask + SQLite or Postgres) |
| Auth | Email signup + password reset (or Clerk/Auth0) |
| Billing | Stripe Customer Portal |
| Email | Resend API; map `api_send_reminder` to real send |
| SMS | Twilio; included after subscribe ($4.99/month or $49.99/year) |
| Imports | CSV upload + later QuickBooks/Stripe sync |
| Multi-tenant | `workspace_id` on all tables before selling beyond yourself |

## Success metrics (first 30 days)

- 10 trial signups  
- 3 paid conversions  
- ≥1 testimonial (“Got paid on a 3-week overdue invoice”)  

If you hit that, double ad spend and add QuickBooks import as the next feature.

## Brand kit

- **Name:** **InvoicePay**. The receipts product at receiptgrid.pro is a separate app; do not use that name on invcpay.com.  
- **Tagline:** Get paid without the chase  
- **UI colors:** teal `#0d6b66`, slate sidebar `#101920` (aligned with Coalesce ERP)  
- **Icon:** blue / teal / violet invoice + reminder mark on black (`static/img/inpmnt-icon.png`)  
- **Fonts:** Source Serif 4 (display), IBM Plex Sans (UI)  
- **Repo:** https://github.com/devildog5x5/InPmnt
