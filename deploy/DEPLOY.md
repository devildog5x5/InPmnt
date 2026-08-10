# Deploy InPmnt on a GoDaddy (or any) Ubuntu VPS

**Direct doc:** https://github.com/devildog5x5/InPmnt/blob/main/deploy/DEPLOY.md

Use a **VPS / Cloud Server**, not shared cPanel hosting.

## Fast path

1. Buy a GoDaddy VPS (Ubuntu 22.04+) and note the public IP.
2. In GoDaddy DNS, point `A` records for your domain (and `www`) to that IP.
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

5. Add Stripe keys:

```bash
sudo nano /var/www/inpmnt/.env
sudo systemctl restart inpmnt
```

Set at least:

```env
BASE_URL=https://yourdomain.com
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_PRO=price_...
STRIPE_PRICE_ANNUAL=price_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

6. Stripe webhook endpoint:

`https://yourdomain.com/api/billing/webhook`

Events: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`.

## Files in this folder

| File | Purpose |
|------|---------|
| `setup-vps.sh` | One-shot install (Nginx + Gunicorn + systemd) |
| `inpmnt.service` | systemd unit |
| `nginx.inpmnt.conf` | Nginx reverse-proxy site |
| `DEPLOY.md` | This guide |

## Useful commands

```bash
sudo systemctl status inpmnt
sudo systemctl restart inpmnt
sudo journalctl -u inpmnt -f
sudo nginx -t && sudo systemctl reload nginx
cd /var/www/inpmnt && sudo -u www-data git pull && sudo systemctl restart inpmnt
```

## Demo login (change after go-live)

- URL: `https://yourdomain.com`
- Email: `robert@inpmnt.app`
- Password: `demo1234`

## Taking payments

Customers subscribe from the landing page or **Settings → Billing**. Cards are processed by Stripe Checkout; InPmnt only stores plan + Stripe customer/subscription IDs. Use **Manage billing** for cancel / update card (Stripe Customer Portal).

Test first with `sk_test_` / `pk_test_` keys and card `4242 4242 4242 4242`, then switch to live keys.
