# Family Shield Pro changelog

Product: **Family Shield Pro** (app name in the UI: **OurCircle**).  
Not InPmnt. Version file: `ourcircle/VERSION` (independent of repo-root InPmnt `VERSION`).

## 1.2.1 — 2026-08-29

- Homepage footer no longer shows version, Hostinger, robots.txt, or sitemap.xml.

## 1.2.0 — 2026-08-29

- Customer-service email on the homepage: **CustomerService@FamilyShieldPro.com**.
- Site-wide **Chat with us** widget (FAQ; optional OpenAI key — see **SUPPORT.md**). Never says a request is “safe.”
- Phone number slot is in the homepage code, commented out, until a number is assigned.

## 1.1.1 — 2026-08-29

- Homepage explains the service, why we built it (we have been scammed; they keep getting more believable), the core pause rule, and “if it sounds too good to be true, it usually is.”

## 1.1.0 — 2026-08-29

- Password recovery: email reset link (SMTP/Resend) and one-time **recovery codes**.
- Two-factor authentication (authenticator app TOTP) on **Account**. Recovery codes also complete sign-in.

## 1.0.2 — 2026-08-29

- Stripe Checkout, Customer Portal, and webhook are wired for Family monthly **$14.99** and Family yearly **$119.99**.
- Add keys to `.env` (see **STRIPE.md**). Until then, choosing a plan only saves the flag — no charge.

## 1.0.1 — 2026-08-29

- Public pricing is two Family plans only: **$14.99/month** or **$119.99/year** (yearly highlighted).
- Removed Individual, founding $49 SKU, and group tile from the public menu.

## 1.0.0 — 2026-08-28

- First Hostinger PHP drop (`FamilyShieldPro-1.0.0.zip`) — unzip into `public_html`, no VPS.
- `robots.txt` and `sitemap.xml` ship **inside that zip** (zip root, next to `index.php`). Do not fetch them as GitHub raw text.
- Family circle, pause-and-verify checks, trusted list, “Please call me before I pay,” founding $49 reservation.
- Logo always links to https://familyshieldpro.com.
