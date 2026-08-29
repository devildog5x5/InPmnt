# Family Shield Pro — Stripe setup

The app is wired. You only add keys. Until they are in `.env`, choosing a plan **does not charge a card**.

**Site:** https://familyshieldpro.com  
**Plans:** Family monthly **$14.99** · Family yearly **$119.99**  
**Not InPmnt.** Do not paste these keys into an InPmnt `.env`.

## 1. Products and prices (once)

1. Open [Stripe Dashboard → Products](https://dashboard.stripe.com/products) (use **Test mode** first).
2. **Add product** named **Family Shield Pro**.
3. Add two **recurring** prices on that product:
   - **$14.99 USD / month**
   - **$119.99 USD / year**
4. Copy each price ID (`price_...`). Those go in `STRIPE_PRICE_MONTHLY` and `STRIPE_PRICE_YEARLY`.

## 2. API keys

[Stripe → API keys](https://dashboard.stripe.com/apikeys):

- `STRIPE_SECRET_KEY` — `sk_test_...` (later `sk_live_...`)
- `STRIPE_PUBLISHABLE_KEY` — `pk_test_...` (later `pk_live_...`)

## 3. Customer portal

[Stripe → Settings → Billing → Customer portal](https://dashboard.stripe.com/settings/billing/portal): turn it **on** (test, then live). That is the “Update card or cancel” button after someone has paid.

## 4. Webhook (required for cancel / plan changes)

[Stripe → Developers → Webhooks](https://dashboard.stripe.com/webhooks) → **Add endpoint**:

**URL:** `https://familyshieldpro.com/billing/webhook`

Events:

- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`

Copy the signing secret (`whsec_...`) into `STRIPE_WEBHOOK_SECRET`.

If the live site is not this domain, use that HTTPS origin + `/billing/webhook`. Stripe cannot reach `127.0.0.1`.

## 5. Paste into Hostinger `.env`

hPanel → **Files → File Manager** → `public_html/.env` (mode **640**).

**Do not replace the whole file** (that would wipe `APP_SECRET` and the database path). Add or edit these lines:

```
BASE_URL=https://familyshieldpro.com
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_MONTHLY=price_...
STRIPE_PRICE_YEARLY=price_...
```

Save. PHP **8.2+** with **pdo_sqlite** and **curl**. If checkout errors mention curl, enable **curl** in hPanel → Advanced → PHP Configuration.

Checkout turns on only when the secret key starts with `sk_` and both price IDs start with `price_` (stubs that contain `...` stay off).

## 6. Prove it with a test card

1. Open https://familyshieldpro.com → sign in (or sign up).
2. **Plans** → **Pay $119.99/year** (or monthly).
3. Stripe Checkout → test card `4242 4242 4242 4242`, any future expiry, any CVC.
4. You return to Plans; status should show **active**.
5. **Update card or cancel** opens the Stripe portal.

Then switch the Dashboard to **Live**, create live prices if needed, paste `sk_live_` / `pk_live_` / live `price_` / live webhook secret, and add a **live** webhook to the same URL.

## Local Flask (optional)

`ourcircle/.env` — same Stripe names. `BASE_URL=http://127.0.0.1:5065` if you test Checkout locally. Webhooks still need a public HTTPS URL (Stripe CLI `stripe listen --forward-to localhost:5065/billing/webhook`).
